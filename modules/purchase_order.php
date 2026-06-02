<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Sales', 'Account']);

/* ─────────────────── Pagination setup ─────────────────── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));

function poStatusClass($status) {
    return match ($status) {
        'Ordered'   => 'status-ordered',
        'Shipped'   => 'status-shipped',
        'Received'  => 'status-received',
        'Pending'   => 'status-pending',
        'Cancelled' => 'status-cancelled',
        default     => 'status-default',
    };
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM purchase_orders WHERE id = $id");
    header("Location: purchase_order.php");
    exit;
}

function getPurchaseOrdersTable($conn, $search = "", $filter = "", $page = 1, $per_page = 20) {
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE 1=1";
    if (!empty($filter) && $filter !== 'total') {
        $safeFilter = mysqli_real_escape_string($conn, $filter);
        $where .= " AND po.status = '$safeFilter'";
    }
    if (trim($search) !== '') {
        $safe  = mysqli_real_escape_string($conn, trim($search));
        $where .= " AND (po.po_number LIKE '%$safe%' OR po.title LIKE '%$safe%' OR po.vendor_name LIKE '%$safe%' OR po.status LIKE '%$safe%')";
    }

    /* total count for pagination */
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS total FROM purchase_orders po $where
    "));
    $total = (int)($total_row['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $per_page));

    $purchaseOrders = mysqli_query($conn, "
        SELECT * FROM purchase_orders po
        $where
        ORDER BY po.created_at DESC
        LIMIT $offset, $per_page
    ");
    $count = mysqli_num_rows($purchaseOrders);
    ob_start();

    if ($total === 0): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-cart-x"></i></div>
        <div class="empty-state-title">No Purchase Orders Found</div>
        <div class="empty-state-sub">Try adjusting your search or filter criteria</div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table po-table mb-0">
            <thead>
                <tr>
                    <th>PO #</th><th>Title</th><th>Vendor</th><th>Total Amount</th>
                    <th>Expected Delivery</th><th>Created</th><th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($po = mysqli_fetch_assoc($purchaseOrders)):
                $po['title']             = $po['title']             ?? '—';
                $po['vendor_name']       = $po['vendor_name']       ?? '—';
                $po['total_amount']      = $po['total_amount']      ?? 0;
                $po['expected_delivery'] = $po['expected_delivery'] ?? null;
                $poNumber   = htmlspecialchars($po['po_number'] ?? '—');
                $vendorName = htmlspecialchars($po['vendor_name']);
                $poTitle    = htmlspecialchars($po['title']);
                $poAmount   = '$' . number_format($po['total_amount'], 2);
                $poStatus   = htmlspecialchars($po['status']);
                $poDelivery = $po['expected_delivery'] ? date('M j, Y', strtotime($po['expected_delivery'])) : '—';
                $poCreated  = date('M j, Y', strtotime($po['created_at']));
            ?>
            <tr class="po-data-row" data-href="purchase_order_view.php?id=<?= $po['id'] ?>" style="cursor:pointer;">
                <td class="row-nav">
                    <?php if (preg_match('/^(PO-\d{4}-\d+-)(\d+)$/', $po['po_number'], $m)): ?>
                        <span class="ref-badge ref-badge-teal"><?= htmlspecialchars($m[1]) ?><span style="font-weight:800;"><?= htmlspecialchars($m[2]) ?></span></span>
                    <?php else: ?>
                        <span class="ref-badge ref-badge-teal"><?= $poNumber ?></span>
                    <?php endif; ?>
                </td>
                <td class="row-nav"><span class="cell-truncate fw-semibold" style="max-width:180px;" title="<?= $poTitle ?>"><?= $poTitle ?></span></td>
                <td class="row-nav">
                    <div class="d-flex align-items-center gap-2">
                        <div class="vendor-avatar"><?= strtoupper(substr($po['vendor_name'], 0, 1)) ?></div>
                        <span class="cell-truncate" style="font-size:.82rem;max-width:120px;" title="<?= $vendorName ?>"><?= $vendorName ?></span>
                    </div>
                </td>
                <td class="row-nav"><span class="amount-badge"><?= $poAmount ?></span></td>
                <td class="row-nav text-muted" style="font-size:.8rem;white-space:nowrap;">
                    <?php if ($po['expected_delivery']): ?><i class="bi bi-truck me-1" style="opacity:.5;font-size:.7rem;"></i><?= $poDelivery ?><?php else: ?>—<?php endif; ?>
                </td>
                <td class="row-nav text-muted" style="font-size:.8rem;white-space:nowrap;">
                    <i class="bi bi-calendar3 me-1" style="opacity:.5;font-size:.7rem;"></i><?= $poCreated ?>
                </td>
                <td class="row-nav">
                    <span class="status-pill <?= poStatusClass($po['status']) ?>">
                        <span class="status-dot"></span><?= $poStatus ?>
                    </span>
                </td>
                <td class="text-center">
                    <div class="row-actions">
                        <a href="purchase_order_pdf.php?id=<?= $po['id'] ?>" class="row-action-btn pdf" title="Download PDF" onclick="event.stopPropagation();"><i class="bi bi-file-earmark-pdf"></i></a>
                        <a href="edit_purchase_order.php?id=<?= $po['id'] ?>" class="row-action-btn edit" title="Edit PO" onclick="event.stopPropagation();"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="row-action-btn delete" title="Delete PO"
                            onclick="event.stopPropagation(); openDeleteModal(<?= $po['id'] ?>, '<?= addslashes($poNumber) ?>', '<?= addslashes($vendorName) ?>', '<?= addslashes($poTitle) ?>', '<?= addslashes($poAmount) ?>', '<?= addslashes($poStatus) ?>', '<?= addslashes($poDelivery) ?>');">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="table-footer-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="text-muted" style="font-size:.78rem;">
            <i class="bi bi-list-ul me-1"></i>
            Showing <strong><?= $count ?></strong> of <strong><?= $total ?></strong> purchase order<?= $total !== 1 ? 's' : '' ?>
        </span>

        <?php if ($pages > 1): ?>
        <nav aria-label="Purchase Orders pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="javascript:void(0)" data-page="<?= max(1, $page - 1) ?>">Prev</a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="javascript:void(0)" data-page="<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="javascript:void(0)" data-page="<?= min($pages, $page + 1) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif;
    return ob_get_clean();
}

/* ---------- AJAX ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getPurchaseOrdersTable($conn, $_GET['search'] ?? '', $_GET['filter'] ?? '', $page, $per_page);
    exit;
}

/* ---------- STAT COUNTS ---------- */
$cnt_total     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM purchase_orders"))['c'];
$cnt_pending   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM purchase_orders WHERE status='Pending'"))['c'];
$cnt_ordered   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM purchase_orders WHERE status='Ordered'"))['c'];
$cnt_shipped   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM purchase_orders WHERE status='Shipped'"))['c'];
$cnt_received  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM purchase_orders WHERE status='Received'"))['c'];
$cnt_cancelled = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM purchase_orders WHERE status='Cancelled'"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-cart-check-fill"></i>Purchase Orders
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="po_search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                        class="form-control page-search-input" placeholder="Search PO #, title, vendor…">
                </div>
                <a href="create_purchase_order.php" class="page-action-btn primary">
                    <i class="bi bi-plus-circle"></i>New PO
                </a>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="po-stat-card <?= (($_GET['filter']??'')==='total')?'active-filter':'' ?>" data-filter="total" style="--card-color:#0d9488;">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="po-stat-label">Total</div><div class="po-stat-num" style="color:#0d9488;"><?= $cnt_total ?></div></div>
                    <div class="po-stat-icon" style="background:#ccfbf1;color:#0d9488;"><i class="bi bi-cart4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="po-stat-card <?= (($_GET['filter']??'')==='Pending')?'active-filter':'' ?>" data-filter="Pending" style="--card-color:#f59e0b;">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="po-stat-label">Pending</div><div class="po-stat-num" style="color:#f59e0b;"><?= $cnt_pending ?></div></div>
                    <div class="po-stat-icon" style="background:#fef3c7;color:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="po-stat-card <?= (($_GET['filter']??'')==='Ordered')?'active-filter':'' ?>" data-filter="Ordered" style="--card-color:#0ea5e9;">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="po-stat-label">Ordered</div><div class="po-stat-num" style="color:#0ea5e9;"><?= $cnt_ordered ?></div></div>
                    <div class="po-stat-icon" style="background:#e0f2fe;color:#0ea5e9;"><i class="bi bi-bag-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="po-stat-card <?= (($_GET['filter']??'')==='Shipped')?'active-filter':'' ?>" data-filter="Shipped" style="--card-color:#7c3aed;">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="po-stat-label">Shipped</div><div class="po-stat-num" style="color:#7c3aed;"><?= $cnt_shipped ?></div></div>
                    <div class="po-stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bi bi-truck"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="po-stat-card <?= (($_GET['filter']??'')==='Received')?'active-filter':'' ?>" data-filter="Received" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="po-stat-label">Received</div><div class="po-stat-num" style="color:#10b981;"><?= $cnt_received ?></div></div>
                    <div class="po-stat-icon" style="background:#d1fae5;color:#10b981;"><i class="bi bi-box-seam"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="po-stat-card <?= (($_GET['filter']??'')==='Cancelled')?'active-filter':'' ?>" data-filter="Cancelled" style="--card-color:#ef4444;">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="po-stat-label">Cancelled</div><div class="po-stat-num" style="color:#ef4444;"><?= $cnt_cancelled ?></div></div>
                    <div class="po-stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="bi bi-x-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="po-table-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Purchase Orders
                </span>
            </div>
            <div class="d-flex gap-2">
                <select id="perPage" class="form-select form-select-sm"
                        style="min-width:90px;font-size:.8rem;">
                    <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10 / page</option>
                    <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20 / page</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50 / page</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="poTable" style="overflow-x:auto;overflow-y:visible;min-height:400px;">
                <?= getPurchaseOrdersTable($conn, $_GET['search'] ?? '', $_GET['filter'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div>

<!-- DELETE PO MODAL -->
<div class="modal fade" id="deletePOModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-cart-x"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete Purchase Order</div>
                            <div class="delete-modal-sub" id="deletePOModalSub">Confirm PO removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-po-chip mb-3">
                    <div class="delete-po-avatar" id="deletePOAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-po-code" id="deletePONumber">—</div>
                        <div class="delete-po-meta" id="deletePOTitle">—</div>
                        <div class="delete-po-meta" id="deletePOVendor">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item"><span class="chip-detail-label">Amount</span><span class="chip-detail-value" id="deletePOAmount">—</span></div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item"><span class="chip-detail-label">Status</span><span class="chip-detail-value" id="deletePOStatus">—</span></div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item"><span class="chip-detail-label">Delivery</span><span class="chip-detail-value" id="deletePODelivery">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong> The purchase order and all associated data will be permanently removed.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                <a href="#" id="deletePOConfirmBtn" class="btn-delete-confirm"><i class="bi bi-trash-fill"></i> Yes, Delete PO</a>
            </div>
        </div>
    </div>
</div>

<script>
    /* ── Row navigation ── */
    document.addEventListener('click', function (e) {
        const navCell = e.target.closest('td.row-nav');
        if (navCell) { const row = navCell.closest('tr.po-data-row'); if (row && row.dataset.href) window.location = row.dataset.href; }
    });

    /* ── Delete modal ── */
    function openDeleteModal(id, poNumber, vendor, title, amount, status, delivery) {
        document.getElementById('deletePOModalSub').textContent = 'Removing: ' + poNumber;
        document.getElementById('deletePONumber').textContent   = poNumber;
        document.getElementById('deletePOTitle').textContent    = title;
        document.getElementById('deletePOVendor').textContent   = vendor;
        document.getElementById('deletePOAmount').textContent   = amount;
        document.getElementById('deletePOStatus').textContent   = status;
        document.getElementById('deletePODelivery').textContent = delivery;
        document.getElementById('deletePOConfirmBtn').href      = '?delete=' + id;
        document.getElementById('deletePOAvatar').textContent   = vendor ? vendor.charAt(0).toUpperCase() : '?';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deletePOModal')).show();
    }

    /* ── Load table via AJAX ── */
    let searchTimer, activeFilter = "<?= htmlspecialchars($_GET['filter'] ?? '') ?>";

    function loadTable(search, filter, page = 1) {
        document.getElementById("poTable").innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-secondary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div><p class="text-muted small mt-2">Loading...</p></div>';

        const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;
        let url = `purchase_order.php?ajax=1&page=${page}&per_page=${perPage}`;
        if (search) url += '&search=' + encodeURIComponent(search);
        if (filter) url += '&filter=' + encodeURIComponent(filter);

        fetch(url).then(r => r.text()).then(html => {
            document.getElementById("poTable").innerHTML = html;
            let qs = [];
            if (search) qs.push('search=' + encodeURIComponent(search));
            if (filter) qs.push('filter=' + encodeURIComponent(filter));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? 'purchase_order.php?' + qs.join('&') : 'purchase_order.php');
        });
    }

    /* ── Stat card filter ── */
    function filterByCard(filter) {
        activeFilter = activeFilter === filter ? '' : filter;
        document.querySelectorAll('.po-stat-card').forEach(c => c.classList.toggle('active-filter', c.dataset.filter === activeFilter));
        document.getElementById('po_search').value = '';
        loadTable('', activeFilter, 1);
    }

    /* ── Search ── */
    function doSearch(query) {
        activeFilter = '';
        document.querySelectorAll('.po-stat-card').forEach(c => c.classList.remove('active-filter'));
        loadTable(query.trim(), '', 1);
    }

    document.querySelectorAll('.po-stat-card').forEach(card => card.addEventListener('click', function () { filterByCard(this.dataset.filter); }));
    document.getElementById('po_search').addEventListener('input', function () { clearTimeout(searchTimer); const q = this.value; searchTimer = setTimeout(() => doSearch(q), 350); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.matches('#po_search')) { e.preventDefault(); clearTimeout(searchTimer); doSearch(e.target.value); } });

    /* ── Pagination click (delegated) ── */
    document.addEventListener('click', function (e) {
        const pg = e.target.closest('.pagination .page-link');
        if (!pg || !pg.dataset.page) return;
        e.preventDefault();
        const page   = parseInt(pg.dataset.page, 10) || 1;
        const search = document.getElementById('po_search').value;
        loadTable(search, activeFilter, page);
    });

    /* ── Per-page change ── */
    document.getElementById('perPage').addEventListener('change', function () {
        const search = document.getElementById('po_search').value;
        loadTable(search, activeFilter, 1);
    });

    /* ── Browser back/forward ── */
    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        const q  = params.get('search') || '';
        const f  = params.get('filter') || '';
        const pg = parseInt(params.get('page') || '1', 10);
        activeFilter = f;
        document.getElementById('po_search').value = q;
        document.querySelectorAll('.po-stat-card').forEach(c => c.classList.toggle('active-filter', c.dataset.filter === f));
        loadTable(q, f, pg);
    });
</script>

<?php include("../templates/footer.php"); ?>