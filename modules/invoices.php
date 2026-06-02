<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Account']);

/* ---------- PAGINATION SETUP ---------- */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page']     ?? 1));

/* ---------- STATUS CLASS ---------- */
function orderStatusClass($status) {
    return match ($status) {
        'Paid'    => 'status-paid',
        'Overdue' => 'status-overdue',
        'Sent'    => 'status-sent',
        'Draft'   => 'status-draft',
        default   => 'status-default',
    };
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM invoices WHERE id = $id");
    header("Location: invoices.php");
    exit;
}

/* ---------- INVOICES TABLE FUNCTION ---------- */
function getInvoicesTable($conn, $search = "", $filter = "", $page = 1, $per_page = 20) {
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE 1=1";

    if (!empty($filter) && $filter !== 'total') {
        $safeFilter = mysqli_real_escape_string($conn, $filter);
        $where .= " AND i.status = '$safeFilter'";
    }

    if (trim($search) !== '') {
        $s     = mysqli_real_escape_string($conn, trim($search));
        $where .= " AND (
            i.code          LIKE '%$s%' OR
            i.customer_name LIKE '%$s%' OR
            o.code          LIKE '%$s%' OR
            i.status        LIKE '%$s%' OR
            i.po_number     LIKE '%$s%'
        )";
    }

    /* -- total count for pagination -- */
    $count_sql = "
        SELECT COUNT(*) AS total
        FROM invoices i
        LEFT JOIN orders    o ON i.order_id    = o.id
        LEFT JOIN customers c ON i.customer_id = c.id
        $where
    ";
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, $count_sql));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $invoices = mysqli_query($conn, "
        SELECT i.*, o.code AS order_code,
               COALESCE(i.customer_name, c.name, '—') AS customer
        FROM invoices i
        LEFT JOIN orders    o ON i.order_id    = o.id
        LEFT JOIN customers c ON i.customer_id = c.id
        $where
        ORDER BY i.id DESC
        LIMIT $offset, $per_page
    ");

    $count = mysqli_num_rows($invoices);
    ob_start();

    if ($count === 0): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
        <div class="empty-state-title">No Invoices Found</div>
        <div class="empty-state-sub">Try adjusting your search or filter criteria</div>
    </div>
    <?php else: ?>
    <table class="table inv-table mb-0">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Order #</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Due Date</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($i = mysqli_fetch_assoc($invoices)):
            $dueDate      = $i['due_date'] ?? null;
            $isOverdue    = ($i['status'] === 'Overdue');
            $isDueSoon    = false;
            if ($dueDate && $i['status'] === 'Sent') {
                $diff      = (new DateTime($dueDate))->diff(new DateTime())->days;
                $isDueSoon = $diff <= 7;
            }
            $customerName = htmlspecialchars($i['customer'] ?? '—');
            $invoiceCode  = htmlspecialchars($i['code'] ?? $i['id']);

            $pdfInputs = '';
            $line_ids_raw = $i['line_ids'] ?? '';

            if (!empty($line_ids_raw)) {
                $safe_line_ids = implode(',', array_map('intval', explode(',', $line_ids_raw)));
                $inv_lines = mysqli_query($conn, "
                    SELECT id, description, qty, unit_price
                    FROM order_lines
                    WHERE id IN ($safe_line_ids)
                ");
            } else {
                $inv_lines = mysqli_query($conn, "
                    SELECT id, description, qty, unit_price
                    FROM order_lines
                    WHERE order_id = " . (int)$i['order_id']
                );
            }

            while ($il = mysqli_fetch_assoc($inv_lines)) {
                $pdfInputs .= '<input type="hidden" name="line_id[]"     value="' . $il['id'] . '">';
                $pdfInputs .= '<input type="hidden" name="description[]" value="' . htmlspecialchars($il['description'] ?? '') . '">';
                $pdfInputs .= '<input type="hidden" name="qty[]"         value="' . $il['qty'] . '">';
                $pdfInputs .= '<input type="hidden" name="price[]"       value="' . $il['unit_price'] . '">';
            }
        ?>
        <tr class="inv-data-row" data-href="invoice_edit.php?id=<?= $i['id'] ?>" style="cursor:pointer;">
            <td class="row-nav">
                <span class="ref-badge ref-badge-violet"><?= $invoiceCode ?></span>
            </td>
            <td class="row-nav">
                <?php if (!empty($i['order_code'])): ?>
                <span class="ref-badge ref-badge-slate"><?= htmlspecialchars($i['order_code']) ?></span>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="row-nav">
                <div class="d-flex align-items-center gap-2">
                    <div class="mini-avatar">
                        <?= strtoupper(substr($i['customer'] ?? '?', 0, 1)) ?>
                    </div>
                    <span class="cell-truncate fw-semibold" style="font-size:.83rem;max-width:140px;"
                        title="<?= $customerName ?>">
                        <?= $customerName ?>
                    </span>
                </div>
            </td>
            <td class="row-nav">
                <span class="amount-badge">$<?= number_format($i['total_amount'] ?? 0, 2) ?></span>
            </td>
            <td class="row-nav">
                <span class="status-pill <?= orderStatusClass($i['status']) ?>">
                    <span class="status-dot"></span>
                    <?= htmlspecialchars($i['status']) ?>
                </span>
            </td>
            <td class="row-nav" style="font-size:.8rem;white-space:nowrap;">
                <?php if ($dueDate): ?>
                <span class="<?= $isOverdue ? 'text-danger fw-semibold' : ($isDueSoon ? 'text-warning fw-semibold' : 'text-muted') ?>">
                    <i class="bi bi-calendar3 me-1" style="opacity:.5;font-size:.7rem;"></i>
                    <?= date("M j, Y", strtotime($dueDate)) ?>
                </span>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
            </td>

            <td class="text-center">
                <div class="row-actions">

                    <!-- VIEW -->
                    <a href="view_invoice.php?id=<?= $i['id'] ?>"
                       class="row-action-btn view"
                       title="View Invoice"
                       onclick="event.stopPropagation();">
                        <i class="bi bi-eye"></i>
                    </a>

                    <!-- PDF -->
                    <form method="post" action="invoice_generate.php" class="pdf-form"
                          style="display:contents;">
                        <input type="hidden" name="ids"                value="<?= htmlspecialchars(json_encode([$i['id']])) ?>">
                        <input type="hidden" name="invoice_number"     value="<?= htmlspecialchars($i['code'] ?? '') ?>">
                        <input type="hidden" name="customer_po_number" value="<?= htmlspecialchars($i['po_number'] ?? '') ?>">
                        <input type="hidden" name="due_date"           value="<?= htmlspecialchars($i['due_date'] ?? '') ?>">
                        <input type="hidden" name="invoice_id"         value="<?= $i['id'] ?>">
                        <?= $pdfInputs ?>
                        <button type="submit"
                                class="row-action-btn pdf"
                                title="Download PDF"
                                onclick="event.stopPropagation();">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </button>
                    </form>

                    <!-- DELETE -->
                    <button
                        type="button"
                        class="row-action-btn delete"
                        title="Delete Invoice"
                        onclick="event.stopPropagation(); openDeleteModal(<?= $i['id'] ?>, '<?= addslashes($invoiceCode) ?>', '<?= addslashes($customerName) ?>', '$<?= number_format($i['total_amount'] ?? 0, 2) ?>', '<?= addslashes(htmlspecialchars($i['status'])) ?>', '<?= $dueDate ? date('M j, Y', strtotime($dueDate)) : '—' ?>');">
                        <i class="bi bi-trash"></i>
                    </button>

                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <!-- ── PAGINATION FOOTER ── -->
    <div class="table-footer-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="text-muted" style="font-size:.78rem;">
            <i class="bi bi-list-ul me-1"></i>
            Showing <strong><?= $count ?></strong> of <strong><?= $total ?></strong>
            invoice<?= $total !== 1 ? 's' : '' ?>
        </span>

        <?php if ($pages > 1): ?>
        <nav aria-label="Invoice pagination">
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
    $page     = max(1, (int)($_GET['page']     ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getInvoicesTable($conn, $_GET['search'] ?? '', $_GET['filter'] ?? '', $page, $per_page);
    exit;
}

/* ---------- STAT COUNTS ---------- */
$cnt_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM invoices"))['c'];
$cnt_draft   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM invoices WHERE status='Draft'"))['c'];
$cnt_sent    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM invoices WHERE status='Sent'"))['c'];
$cnt_paid    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM invoices WHERE status='Paid'"))['c'];
$cnt_overdue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM invoices WHERE status='Overdue'"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>


<div class="page-wrapper">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-receipt-cutoff"></i>
                    Invoices
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="inv_search"
                        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search invoice #, customer, order…">
                </div>
                <a href="invoice_add.php" class="page-action-btn primary">
                    <i class="bi bi-plus-circle"></i>New Invoice
                </a>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="row g-2 g-md-3 mb-4">
        <?php
        $inv_stat_cards = [
            ['total',   'Total',   $cnt_total,   '#7c3aed', '#ede9fe', 'receipt'],
            ['Draft',   'Draft',   $cnt_draft,   '#f59e0b', '#fef9c3', 'pencil-square'],
            ['Sent',    'Sent',    $cnt_sent,    '#64748b', '#f1f5f9', 'send'],
            ['Paid',    'Paid',    $cnt_paid,    '#10b981', '#d1fae5', 'check-circle-fill'],
            ['Overdue', 'Overdue', $cnt_overdue, '#ef4444', '#fee2e2', 'exclamation-circle-fill'],
        ];
        $activeFilterPHP = $_GET['filter'] ?? '';
        foreach ($inv_stat_cards as [$filter_val, $label_s, $count_s, $color, $bg, $icon]):
            $is_active = ($activeFilterPHP === $filter_val);
        ?>
            <div class="col-6 col-md">
                <div class="inv-stat-card <?= $is_active ? 'active-filter' : '' ?>"
                    data-filter="<?= htmlspecialchars($filter_val) ?>"
                    style="--card-color:<?= $color ?>;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="inv-stat-label"><?= $label_s ?></div>
                            <div class="inv-stat-num" style="color:<?= $color ?>;"><?= number_format($count_s) ?></div>
                        </div>
                        <div class="inv-stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>;">
                            <i class="bi bi-<?= $icon ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="inv-table-card card border-0">
        <!-- Card header with per-page selector (matches rfqs.php style) -->
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Invoices
                </span>
            </div>
            <div class="d-flex gap-2">
                <select id="perPage" class="form-select form-select-sm"
                        style="min-width:90px;font-size:.8rem;">
                    <option value="10"  <?= $per_page == 10  ? 'selected' : '' ?>>10 / page</option>
                    <option value="20"  <?= $per_page == 20  ? 'selected' : '' ?>>20 / page</option>
                    <option value="50"  <?= $per_page == 50  ? 'selected' : '' ?>>50 / page</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="invoicesTable" style="overflow-x:auto;min-height:300px;">
                <?= getInvoicesTable($conn, $_GET['search'] ?? '', $_GET['filter'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ══════════════════════════════════════════
     DELETE INVOICE MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                        <div>
                            <div class="delete-modal-title">Delete Invoice</div>
                            <div class="delete-modal-sub" id="deleteInvoiceModalSub">Confirm invoice removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body px-4 py-4">
                <div class="delete-invoice-chip mb-3">
                    <div class="delete-invoice-avatar" id="deleteInvoiceAvatar">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-invoice-code" id="deleteInvoiceCode">—</div>
                        <div class="delete-invoice-meta" id="deleteInvoiceCustomer">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Amount</span>
                                <span class="chip-detail-value" id="deleteInvoiceAmount">—</span>
                            </div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Status</span>
                                <span class="chip-detail-value" id="deleteInvoiceStatus">—</span>
                            </div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Due Date</span>
                                <span class="chip-detail-value" id="deleteInvoiceDue">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        This action is <strong>permanent and cannot be undone.</strong>
                        The invoice and all associated line items will be permanently removed.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 px-4 py-3"
                 style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteInvoiceConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Invoice
                </a>
            </div>

        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════
   ROW NAVIGATION
══════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const navCell = e.target.closest('td.row-nav');
    if (navCell) {
        const row = navCell.closest('tr.inv-data-row');
        if (row && row.dataset.href) window.location = row.dataset.href;
    }
});

/* ══════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════ */
function openDeleteModal(id, code, customer, amount, status, due) {
    document.getElementById('deleteInvoiceModalSub').textContent = 'Removing: ' + code;
    document.getElementById('deleteInvoiceCode').textContent     = code;
    document.getElementById('deleteInvoiceCustomer').textContent = customer;
    document.getElementById('deleteInvoiceAmount').textContent   = amount;
    document.getElementById('deleteInvoiceStatus').textContent   = status;
    document.getElementById('deleteInvoiceDue').textContent      = due;
    document.getElementById('deleteInvoiceConfirmBtn').href      = '?delete=' + id;

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('deleteInvoiceModal')
    ).show();
}

/* ══════════════════════════════════════════
   TABLE AJAX + PAGINATION
══════════════════════════════════════════ */
let searchTimer;
let activeFilter = "<?= htmlspecialchars($_GET['filter'] ?? '') ?>";

function loadTable(search, filter, page = 1) {
    document.getElementById("invoicesTable").innerHTML =
        '<div class="text-center py-5">' +
        '<div class="spinner-border text-secondary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
        '<p class="text-muted small mt-2">Loading...</p></div>';

    const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;

    let url = 'invoices.php?ajax=1&page=' + page + '&per_page=' + perPage;
    if (search) url += '&search=' + encodeURIComponent(search);
    if (filter) url += '&filter=' + encodeURIComponent(filter);

    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById("invoicesTable").innerHTML = html;

            // push state so URL stays in sync
            let qs = [];
            if (search) qs.push('search=' + encodeURIComponent(search));
            if (filter) qs.push('filter=' + encodeURIComponent(filter));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
}

/* Pagination click — delegate from document so it works after AJAX re-render */
document.addEventListener('click', function (e) {
    const pg = e.target.closest('.pagination .page-link');
    if (!pg || !pg.dataset.page) return;
    e.preventDefault();
    const page   = parseInt(pg.dataset.page, 10) || 1;
    const search = document.getElementById('inv_search').value.trim();
    loadTable(search, activeFilter, page);
});

/* Per-page selector */
document.getElementById('perPage').addEventListener('change', function () {
    const search = document.getElementById('inv_search').value.trim();
    loadTable(search, activeFilter, 1);
});

/* Stat card filter */
function filterByCard(filter) {
    activeFilter = activeFilter === filter ? '' : filter;
    document.querySelectorAll('.inv-stat-card').forEach(c =>
        c.classList.toggle('active-filter', c.dataset.filter === activeFilter));
    document.getElementById('inv_search').value = '';
    loadTable('', activeFilter, 1);
}

function doSearch(query) {
    query = query.trim();
    activeFilter = '';
    document.querySelectorAll('.inv-stat-card').forEach(c => c.classList.remove('active-filter'));
    loadTable(query, '', 1);
}

document.querySelectorAll('.inv-stat-card').forEach(card =>
    card.addEventListener('click', function () { filterByCard(this.dataset.filter); }));

document.getElementById('inv_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value;
    searchTimer = setTimeout(() => doSearch(q), 350);
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('#inv_search')) {
        e.preventDefault();
        clearTimeout(searchTimer);
        doSearch(e.target.value);
    }
});

window.addEventListener('popstate', function () {
    const params  = new URLSearchParams(window.location.search);
    const q       = params.get('search') || '';
    const f       = params.get('filter') || '';
    const pg      = parseInt(params.get('page') || '1', 10);
    activeFilter  = f;
    document.getElementById('inv_search').value = q;
    document.querySelectorAll('.inv-stat-card').forEach(c =>
        c.classList.toggle('active-filter', c.dataset.filter === f));
    loadTable(q, f, pg);
});
</script>

<?php include("../templates/footer.php"); ?>