<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Sales']);

/* ─────────────────── Pagination setup ─────────────────── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));

function orderStatusClass($status)
{
    return match ($status) {
        'Completed'    => 'status-completed',
        'Cancelled'    => 'status-cancelled',
        'On Hold'      => 'status-onhold',
        'Pending'      => 'status-pending',
        'In Progress'  => 'status-inprogress',
        'In Review'    => 'status-inreview',
        'To be Tested' => 'status-testing',
        'Closed'       => 'status-completed',
        default        => 'status-inreview',
    };
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM orders WHERE id = $id");
    header("Location: order.php");
    exit;
}

/* ---------- ORDERS TABLE FUNCTION ---------- */
function getOrdersTable($conn, $search = "", $filter = "", $page = 1, $per_page = 20)
{
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE 1=1";

    if (!empty($filter)) {
        if ($filter !== 'total') {
            $safeFilter = mysqli_real_escape_string($conn, $filter);
            $where .= " AND o.status = '$safeFilter'";
        }
    }

    if (trim($search) !== '') {
        $safe   = mysqli_real_escape_string($conn, trim($search));
        $where .= " AND (
            o.code               LIKE '%$safe%' OR
            o.rfq_title          LIKE '%$safe%' OR
            o.customer_po_number LIKE '%$safe%' OR
            o.customer_name      LIKE '%$safe%'
        )";
    }

    /* total count for pagination */
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(DISTINCT o.id) AS total
        FROM orders o
        LEFT JOIN customers   c  ON o.customer_id    = c.id
        LEFT JOIN salesperson sp ON o.salesPerson_id = sp.id
        LEFT JOIN billto      bt ON o.billTo_id      = bt.id
        LEFT JOIN buyer       b  ON o.buyer_id       = b.id
        LEFT JOIN shipto      st ON o.shipTo_id      = st.id
        $where
    "));
    $total = (int)($total_row['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $per_page));

    $orders = mysqli_query($conn, "
        SELECT
            o.id,
            o.code,
            o.rfq_title,
            o.customer_po_number,
            o.created_at,
            o.status,
            o.quote_date,
            o.lead_time,
            COALESCE(o.customer_name, c.name)  AS customer,
            sp.name    AS sales_person,
            bt.title   AS bill_to,
            b.name     AS buyer,
            st.address AS ship_to,
            IFNULL(SUM(ol.qty), 0) AS quantity
        FROM orders o
        LEFT JOIN customers   c  ON o.customer_id    = c.id
        LEFT JOIN salesperson sp ON o.salesPerson_id = sp.id
        LEFT JOIN billto      bt ON o.billTo_id      = bt.id
        LEFT JOIN buyer       b  ON o.buyer_id       = b.id
        LEFT JOIN shipto      st ON o.shipTo_id      = st.id
        LEFT JOIN order_lines ol ON ol.order_id      = o.id
        $where
        GROUP BY o.id
        ORDER BY o.id DESC
        LIMIT $offset, $per_page
    ");

    ob_start();
    $count = mysqli_num_rows($orders);
?>
    <?php if ($total === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-bag-x"></i></div>
            <div class="empty-state-title">No Orders Found</div>
            <div class="empty-state-sub">Try adjusting your search or filter criteria</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table order-table mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer PO #</th>
                        <th>Title</th>
                        <th>Customer</th>
                        <th>Sales Person</th>
                        <th>Qty</th>
                        <th>Bill To</th>
                        <th>Buyer</th>
                        <th>Ship To</th>
                        <th>Created</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($o = mysqli_fetch_assoc($orders)):
                        $o['sales_person']       = $o['sales_person']       ?? '—';
                        $o['bill_to']            = $o['bill_to']            ?? '—';
                        $o['buyer']              = $o['buyer']              ?? '—';
                        $o['ship_to']            = $o['ship_to']            ?? '—';
                        $o['customer']           = $o['customer']           ?? '—';
                        $o['rfq_title']          = $o['rfq_title']          ?? '—';
                        $o['customer_po_number'] = $o['customer_po_number'] ?? '—';

                        $orderCode     = htmlspecialchars($o['code']);
                        $orderCustomer = htmlspecialchars($o['customer']);
                        $orderStatus   = htmlspecialchars($o['status']);
                        $orderCreated  = date('M j, Y', strtotime($o['created_at']));
                        $orderPO       = htmlspecialchars($o['customer_po_number']);
                    ?>
                        <tr class="order-data-row" data-href="order_view.php?id=<?= $o['id'] ?>" style="cursor:pointer;">
                            <td class="row-nav">
                                <span class="ref-badge ref-badge-blue"><?= $orderCode ?></span>
                            </td>
                            <td class="row-nav text-muted fw-medium" style="font-size:.82rem;">
                                <?= $orderPO ?>
                            </td>
                            <td class="row-nav">
                                <span class="cell-truncate" title="<?= htmlspecialchars($o['rfq_title']) ?>">
                                    <?= htmlspecialchars($o['rfq_title']) ?>
                                </span>
                            </td>
                            <td class="row-nav">
                                <span class="cell-truncate fw-semibold" title="<?= $orderCustomer ?>">
                                    <?= $orderCustomer ?>
                                </span>
                            </td>
                            <td class="row-nav">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="mini-avatar">
                                        <?= strtoupper(substr($o['sales_person'], 0, 1)) ?>
                                    </div>
                                    <span style="font-size:.8rem;"><?= htmlspecialchars($o['sales_person']) ?></span>
                                </div>
                            </td>
                            <td class="row-nav">
                                <span class="qty-badge"><?= number_format($o['quantity']) ?></span>
                            </td>
                            <td class="row-nav text-muted" style="font-size:.8rem;">
                                <span class="cell-truncate" title="<?= htmlspecialchars($o['bill_to']) ?>">
                                    <?= htmlspecialchars($o['bill_to']) ?>
                                </span>
                            </td>
                            <td class="row-nav text-muted" style="font-size:.8rem;">
                                <?= htmlspecialchars($o['buyer']) ?>
                            </td>
                            <td class="row-nav text-muted" style="font-size:.8rem;">
                                <span class="cell-truncate" title="<?= htmlspecialchars($o['ship_to']) ?>">
                                    <?= htmlspecialchars($o['ship_to']) ?>
                                </span>
                            </td>
                            <td class="row-nav text-muted" style="font-size:.8rem;white-space:nowrap;">
                                <i class="bi bi-calendar3 me-1" style="opacity:.5;font-size:.7rem;"></i>
                                <?= $orderCreated ?>
                            </td>
                            <td class="row-nav">
                                <?php
                                $quoteDate        = !empty($o['quote_date']) ? new DateTime($o['quote_date']) : null;
                                $leadTime         = trim($o['lead_time'] ?? '');
                                $validityInterval = null;

                                if ($quoteDate && $leadTime) {
                                    $lt = strtolower($leadTime);
                                    preg_match('/(\d+)/', $lt, $numMatch);
                                    $num = isset($numMatch[1]) ? (int)$numMatch[1] : 0;
                                    if (strpos($lt, 'month') !== false && $num > 0)
                                        $validityInterval = new DateInterval("P{$num}M");
                                    elseif (strpos($lt, 'day') !== false && $num > 0)
                                        $validityInterval = new DateInterval("P{$num}D");
                                    elseif (is_numeric($lt) && (int)$lt > 0)
                                        $validityInterval = new DateInterval("P{$lt}D");
                                }

                                if ($quoteDate && $validityInterval) {
                                    $expiryDate = (clone $quoteDate)->add($validityInterval);
                                    $today      = new DateTime();
                                    $daysLeft   = (int)$today->diff($expiryDate)->days;
                                    if ($today > $expiryDate)
                                        echo "<span class='validity-badge expired'>Expired</span>";
                                    else
                                        echo "<span class='validity-badge valid'>{$daysLeft}d left</span>";
                                } else {
                                    echo "<span class='validity-badge na'>N/A</span>";
                                }
                                ?>
                            </td>
                            <td class="row-nav">
                                <span class="status-pill <?= orderStatusClass($o['status']) ?>">
                                    <?= $orderStatus ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="row-actions">
                                    <a href="order_view.php?id=<?= $o['id'] ?>"
                                        class="row-action-btn view"
                                        title="View"
                                        onclick="event.stopPropagation();">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button
                                        type="button"
                                        class="row-action-btn delete"
                                        title="Delete"
                                        onclick="event.stopPropagation(); openDeleteModal(<?= $o['id'] ?>, '<?= addslashes($orderCode) ?>', '<?= addslashes($orderCustomer) ?>', '<?= addslashes($orderPO) ?>', '<?= addslashes($orderStatus) ?>', '<?= addslashes($orderCreated) ?>');">
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
                Showing <strong><?= $count ?></strong> of <strong><?= $total ?></strong> order<?= $total !== 1 ? 's' : '' ?>
            </span>

            <?php if ($pages > 1): ?>
            <nav aria-label="Orders pagination">
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
    <?php endif; ?>
<?php
    return ob_get_clean();
}

/* ---------- AJAX ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getOrdersTable($conn, $_GET['search'] ?? '', $_GET['filter'] ?? '', $page, $per_page);
    exit;
}

/* ---------- STAT COUNTS ---------- */
$cnt_total      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders"))['c'];
$cnt_pending    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status='Pending'"))['c'];
$cnt_inprogress = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status='In Progress'"))['c'];
$cnt_completed  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status='Completed'"))['c'];
$cnt_cancelled  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status='Cancelled'"))['c'];
$cnt_onhold     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status='On Hold'"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-bag-check-fill"></i>
                    Orders
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="order_search"
                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search orders…">
                </div>
                <a href="order_add.php" class="page-action-btn primary">
                    <i class="bi bi-plus-circle"></i>Create Order
                </a>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 stat-cards-row">
        <?php
        $stat_cards = [
            ['total',       'Total',       $cnt_total,      '#64748b', '#f1f5f9', 'layers-fill'],
            ['Pending',     'Pending',     $cnt_pending,    '#f59e0b', '#fef9c3', 'hourglass-split'],
            ['In Progress', 'In Progress', $cnt_inprogress, '#3b82f6', '#dbeafe', 'gear-fill'],
            ['Completed',   'Completed',   $cnt_completed,  '#10b981', '#d1fae5', 'check-circle-fill'],
            ['Cancelled',   'Cancelled',   $cnt_cancelled,  '#ef4444', '#fee2e2', 'x-circle-fill'],
            ['On Hold',     'On Hold',     $cnt_onhold,     '#8b5cf6', '#ede9fe', 'pause-circle-fill'],
        ];
        $activeFilter = $_GET['filter'] ?? '';
        foreach ($stat_cards as [$filter_val, $label_s, $count_s, $color, $bg, $icon]):
            $is_active = ($activeFilter === $filter_val);
        ?>
            <div class="col-6 col-md-2">
                <div class="order-stat-card <?= $is_active ? 'active-filter' : '' ?>"
                    data-filter="<?= htmlspecialchars($filter_val) ?>"
                    style="--accent:<?= $color ?>;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="order-stat-label"><?= $label_s ?></div>
                            <div class="order-stat-num" style="color:<?= $color ?>;"><?= number_format($count_s) ?></div>
                        </div>
                        <div class="order-stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>;">
                            <i class="bi bi-<?= $icon ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="table-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Orders
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
        <div id="ordersTable" style="overflow-x:auto;overflow-y:visible;min-height:400px;">
            <?= getOrdersTable($conn, $_GET['search'] ?? '', $_GET['filter'] ?? '', $page, $per_page) ?>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════
     DELETE ORDER MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap">
                            <i class="bi bi-bag-x"></i>
                        </div>
                        <div>
                            <div class="delete-modal-title">Delete Order</div>
                            <div class="delete-modal-sub" id="deleteOrderModalSub">Confirm order removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body px-4 py-4">
                <div class="delete-order-chip mb-3">
                    <div class="delete-order-avatar" id="deleteOrderAvatar">
                        <i class="bi bi-bag"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-order-code" id="deleteOrderCode">—</div>
                        <div class="delete-order-meta" id="deleteOrderCustomer">—</div>
                        <div class="delete-order-meta" id="deleteOrderPO">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Status</span>
                                <span class="chip-detail-value" id="deleteOrderStatus">—</span>
                            </div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Created</span>
                                <span class="chip-detail-value" id="deleteOrderCreated">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        This action is <strong>permanent and cannot be undone.</strong>
                        The order and all associated line items will be permanently removed.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 px-4 py-3"
                style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteOrderConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Order
                </a>
            </div>

        </div>
    </div>
</div>

<script>
    /* ── Row navigation ── */
    document.addEventListener('click', function (e) {
        const navCell = e.target.closest('td.row-nav');
        if (navCell) {
            const row = navCell.closest('tr.order-data-row');
            if (row && row.dataset.href) window.location = row.dataset.href;
        }
    });

    /* ── Delete modal ── */
    function openDeleteModal(id, code, customer, poNumber, status, created) {
        document.getElementById('deleteOrderModalSub').textContent = 'Removing: ' + code;
        document.getElementById('deleteOrderCode').textContent     = code;
        document.getElementById('deleteOrderCustomer').textContent = customer;
        document.getElementById('deleteOrderPO').textContent       = poNumber ? 'PO: ' + poNumber : '';
        document.getElementById('deleteOrderStatus').textContent   = status;
        document.getElementById('deleteOrderCreated').textContent  = created;
        document.getElementById('deleteOrderConfirmBtn').href      = '?delete=' + id;
        const avatarEl = document.getElementById('deleteOrderAvatar');
        avatarEl.textContent = code ? code.charAt(0).toUpperCase() : '?';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteOrderModal')).show();
    }

    /* ── Load table via AJAX ── */
    let searchTimer;
    let activeFilter = "<?= htmlspecialchars($_GET['filter'] ?? '') ?>";

    function loadTable(search, filter, page = 1) {
        document.getElementById("ordersTable").innerHTML =
            '<div class="table-loading"><div class="spinner-border"></div></div>';

        const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;
        let url = `order.php?ajax=1&page=${page}&per_page=${perPage}`;
        if (search) url += '&search=' + encodeURIComponent(search);
        if (filter) url += '&filter=' + encodeURIComponent(filter);

        fetch(url)
            .then(r => r.text())
            .then(html => {
                document.getElementById("ordersTable").innerHTML = html;
                /* sync browser URL */
                let qs = [];
                if (search) qs.push('search=' + encodeURIComponent(search));
                if (filter) qs.push('filter=' + encodeURIComponent(filter));
                qs.push('page=' + page);
                qs.push('per_page=' + perPage);
                history.pushState(null, '', qs.length ? 'order.php?' + qs.join('&') : 'order.php');
            });
    }

    /* ── Stat card filter ── */
    function filterByCard(filter) {
        activeFilter = (activeFilter === filter) ? '' : filter;
        document.querySelectorAll('.order-stat-card').forEach(c => {
            c.classList.toggle('active-filter', c.dataset.filter === activeFilter);
        });
        document.getElementById('order_search').value = '';
        loadTable('', activeFilter, 1);
    }

    document.querySelectorAll('.order-stat-card').forEach(card => {
        card.addEventListener('click', () => filterByCard(card.dataset.filter));
    });

    /* ── Search ── */
    document.getElementById('order_search').addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value;
        searchTimer = setTimeout(() => {
            activeFilter = '';
            document.querySelectorAll('.order-stat-card').forEach(c => c.classList.remove('active-filter'));
            loadTable(q.trim(), '', 1);
        }, 350);
    });

    document.getElementById('order_search').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            activeFilter = '';
            document.querySelectorAll('.order-stat-card').forEach(c => c.classList.remove('active-filter'));
            loadTable(this.value.trim(), '', 1);
        }
    });

    /* ── Pagination click (delegated) ── */
    document.addEventListener('click', function (e) {
        const pg = e.target.closest('.pagination .page-link');
        if (!pg || !pg.dataset.page) return;
        e.preventDefault();
        const page   = parseInt(pg.dataset.page, 10) || 1;
        const search = document.getElementById('order_search').value;
        loadTable(search, activeFilter, page);
    });

    /* ── Per-page change ── */
    document.getElementById('perPage').addEventListener('change', function () {
        const search = document.getElementById('order_search').value;
        loadTable(search, activeFilter, 1);
    });

    /* ── Browser back/forward ── */
    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        const q  = params.get('search') || '';
        const f  = params.get('filter') || '';
        const pg = parseInt(params.get('page') || '1', 10);
        activeFilter = f;
        document.getElementById('order_search').value = q;
        document.querySelectorAll('.order-stat-card').forEach(c => {
            c.classList.toggle('active-filter', c.dataset.filter === f);
        });
        loadTable(q, f, pg);
    });
</script>

<?php include("../templates/footer.php"); ?>