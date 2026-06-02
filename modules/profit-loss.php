<?php
session_start();
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Account']);

/* ---------------- PAGINATION SETUP ---------------- */
$default_per_page = 20;
$per_page = $default_per_page;
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ---------------- OTHER EXPENSE HANDLING ---------------- */
if (!isset($_SESSION['other_expenses'])) {
    $_SESSION['other_expenses'] = [];
}

if (isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $_SESSION['other_expenses'][] = [
        'name'   => trim($_POST['expense_name']),
        'amount' => floatval($_POST['expense_amount'])
    ];
}
if (isset($_POST['action']) && $_POST['action'] === 'delete_expense') {
    $index = intval($_POST['index']);
    unset($_SESSION['other_expenses'][$index]);
    $_SESSION['other_expenses'] = array_values($_SESSION['other_expenses']);
}
if (isset($_POST['action']) && $_POST['action'] === 'edit_expense') {
    $index = intval($_POST['index']);
    $_SESSION['other_expenses'][$index] = [
        'name'   => trim($_POST['expense_name']),
        'amount' => floatval($_POST['expense_amount'])
    ];
}

$otherExpenses = $_SESSION['other_expenses'];

/* ---------------- REPORT FILTER LOGIC ---------------- */
function getProfitLossData($conn, $fromDate, $toDate, $orderNumber)
{
    $where = "WHERE ol.status != 'Cancelled'";
    if ($fromDate && $toDate && !$orderNumber) {
        $where .= " AND DATE(o.created_at) BETWEEN '"
            . mysqli_real_escape_string($conn, $fromDate) . "' AND '"
            . mysqli_real_escape_string($conn, $toDate) . "'";
    }
    if ($orderNumber) {
        $where .= " AND (o.order_number = " . (int)$orderNumber
            . " OR o.code LIKE '%" . mysqli_real_escape_string($conn, $orderNumber) . "%')";
    }

    $result = mysqli_query($conn, "
        SELECT ol.id, ol.order_id, o.order_number, o.code, o.created_at,
               ol.description, ol.qty, ol.unit_price, ol.cost_price,
               (ol.qty * ol.unit_price) AS line_revenue,
               (ol.qty * ol.cost_price) AS line_cost
        FROM order_lines ol
        JOIN orders o ON ol.order_id = o.id
        $where
        ORDER BY o.created_at DESC
    ");

    $totalRevenue = 0;
    $totalCOGS = 0;
    $orderLines = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $totalRevenue += $row['line_revenue'];
        $totalCOGS    += $row['line_cost'];
        $orderLines[]  = $row;
    }
    return [$totalRevenue, $totalCOGS, $orderLines];
}

/* ---------------- SHARED RENDER FUNCTION ---------------- */
function renderPLContent($totalRevenue, $totalCOGS, $grossProfit, $operatingExpenses, $fixedExpenses, $otherExpenses, $totalExpenses, $netProfit, $orderLines, $fromDate, $toDate, $orderNumber, $page = 1, $per_page = 20)
{
    $page       = max(1, (int)$page);
    $per_page   = max(5, (int)$per_page);
    $totalLines = count($orderLines);
    $totalPages = max(1, (int)ceil($totalLines / $per_page));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $per_page;
    $pagedLines = array_slice($orderLines, $offset, $per_page);
?>
    <!-- P&L STATEMENT -->
    <div class="pl-kpi-grid">
        <div class="pl-kpi-card revenue">
            <span class="pl-kpi-label">Revenue</span>
            <strong>€<?= number_format($totalRevenue, 2) ?></strong>
            <small>Total invoiced sales</small>
        </div>
        <div class="pl-kpi-card cost">
            <span class="pl-kpi-label">COGS</span>
            <strong>€<?= number_format($totalCOGS, 2) ?></strong>
            <small>Direct order costs</small>
        </div>
        <div class="pl-kpi-card gross">
            <span class="pl-kpi-label">Gross Profit</span>
            <strong>€<?= number_format($grossProfit, 2) ?></strong>
            <small><?= $totalRevenue > 0 ? number_format(($grossProfit / $totalRevenue) * 100, 1) : '0.0' ?>% gross margin</small>
        </div>
        <div class="pl-kpi-card net <?= $netProfit >= 0 ? 'positive' : 'negative' ?>">
            <span class="pl-kpi-label">Net <?= $netProfit >= 0 ? 'Profit' : 'Loss' ?></span>
            <strong>€<?= number_format(abs($netProfit), 2) ?></strong>
            <small>After operating expenses</small>
        </div>
    </div>
    <div class="pl-statement-card">
        <div class="pl-card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="pl-card-title"><i class="bi bi-graph-up-arrow pl-header-icon"></i>Profit & Loss Statement</div>
                <div class="pl-card-sub">
                    <?= $orderNumber
                        ? "Order #" . htmlspecialchars($orderNumber)
                        : ($fromDate && $toDate
                            ? date("M j, Y", strtotime($fromDate)) . " — " . date("M j, Y", strtotime($toDate))
                            : date("F Y")) ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="page-action-btn success" data-bs-toggle="modal" data-bs-target="#expenseModal">
                    <i class="bi bi-plus-circle"></i>Add Expense
                </button>
            </div>
        </div>
        <div class="pl-card-body">
            <div class="pl-section">
                <div class="pl-section-label">Revenue</div>
                <div class="pl-line">
                    <span>Total Sales</span>
                    <span class="pl-amount positive">€<?= number_format($totalRevenue, 2) ?>
                    </span>
                </div>
            </div>
            <div class="pl-section">
                <div class="pl-section-label">Cost of Goods Sold</div>
                <div class="pl-line">
                    <span>COGS</span>
                    <span class="pl-amount negative">−€<?= number_format($totalCOGS, 2) ?>
                    </span>
                </div>
                <div class="pl-line pl-subtotal">
                    <span>Gross Profit</span>
                    <span class="pl-amount <?= $grossProfit >= 0 ? 'positive' : 'negative' ?>">
                        €<?= number_format($grossProfit, 2) ?>
                    </span>
                </div>
            </div>
            <div class="pl-section">
                <div class="pl-section-label">Operating Expenses</div>
                <?php foreach ($fixedExpenses as $expense): ?>
                    <div class="pl-line">
                        <span><?= htmlspecialchars($expense[0]) ?>
                        </span>
                        <span class="pl-amount negative">−€<?= number_format($expense[1], 2) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($otherExpenses as $index => $expense): ?>
                    <div class="pl-line pl-other-expense">
                        <span class="d-flex align-items-center gap-2">
                            <?= htmlspecialchars($expense['name']) ?>
                            <span class="pl-other-tag">Custom</span>
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="pl-amount negative">−€<?= number_format($expense['amount'], 2) ?>
                            </span>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="delete_expense">
                                <input type="hidden" name="index" value="<?= $index ?>">
                                <button type="submit" class="pl-delete-btn"
                                    onclick="return confirm('Delete this expense?')" title="Remove">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>
                <div class="pl-line pl-subtotal">
                    <span>Total Operating Expenses</span>
                    <span class="pl-amount negative">−€<?= number_format($totalExpenses, 2) ?>
                    </span>
                </div>
            </div>
            <!-- NET RESULT -->
            <div class="pl-net <?= $netProfit >= 0 ? 'pl-net-profit' : 'pl-net-loss' ?>">
                <div class="pl-net-left">
                    <div class="pl-net-icon">
                        <i class="bi bi-<?= $netProfit >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
                    </div>
                    <div class="pl-net-label">Net <?= $netProfit >= 0 ? 'Profit' : 'Loss' ?>
                    </div>
                </div>
                <div class="pl-net-amount">€<?= number_format(abs($netProfit), 2) ?>
                </div>
            </div>
        </div>
    </div>
    <!-- FILTER BAR — just above order breakdown -->
    <div class="filter-card">
        <div class="row g-2 align-items-end" style="margin-top: -25px;">
            <div class="col-12 col-sm-6 col-md-3">
                <input type="date" id="filter_from" value="<?= htmlspecialchars($fromDate) ?>" class="form-control filter-input">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <input type="date" id="filter_to" value="<?= htmlspecialchars($toDate) ?>" class="form-control filter-input">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <input type="text" id="filter_order" value="<?= htmlspecialchars($orderNumber) ?>" class="form-control filter-input" placeholder="Search Order #">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="filter-label">&nbsp;</div>
                <div class="d-flex gap-2">
                    <button type="button" id="filterSearchBtn" class="btn btn-primary filter-btn flex-fill">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                    <button type="button" id="filterResetBtn" class="btn btn-outline-secondary filter-btn flex-fill">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </button>
                    <form method="post" action="loss-profit-pdf.php" id="pdfForm" class="flex-fill">
                        <input type="hidden" name="from" id="pdf_from" value="<?= htmlspecialchars($fromDate) ?>">
                        <input type="hidden" name="to" id="pdf_to" value="<?= htmlspecialchars($toDate) ?>">
                        <input type="hidden" name="order_number" id="pdf_order" value="<?= htmlspecialchars($orderNumber) ?>">
                        <button type="submit" id="downloadPdfBtn" class="btn btn-danger filter-btn w-100">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- ORDER BREAKDOWN -->
    <div class="breakdown-header">
        <div class="breakdown-title"><i class="bi bi-table me-2"></i>Order Line Breakdown</div>
        <span class="breakdown-count"><?= $totalLines ?>
            line<?= $totalLines !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="breakdown-card">
        <?php if (empty($orderLines)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                <div class="empty-state-title">No order lines found</div>
                <div class="empty-state-sub">Try adjusting your date range or order number filter</div>
            </div>
        <?php else: ?>
            <form method="post" action="loss-profit-pdf.php" id="orderForm" class="card border-0">
                <div class="table-responsive breakdown-table-scroll">
                    <table class="table breakdown-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Cost</th>
                                <th class="text-end">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagedLines as $line):
                                $lineProfit = $line['line_revenue'] - $line['line_cost'];
                            ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input row-check" name="orders[]" value="<?= $line['order_id'] ?>"></td>
                                    <td>
                                        <span class="ref-badge ref-badge-slate">
                                            <?= htmlspecialchars($line['code'] ?: $line['order_number']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted" style="font-size:.8rem;white-space:nowrap;">
                                        <?= date("M j, Y", strtotime($line['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="cell-truncate" title="<?= htmlspecialchars($line['description'] ?? '') ?>">
                                            <?= htmlspecialchars($line['description'] ?? '—') ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><span class="amount-badge amount-blue">€<?= number_format($line['line_revenue'], 2) ?>
                                        </span></td>
                                    <td class="text-end"><span class="amount-badge amount-red">€<?= number_format($line['line_cost'], 2) ?>
                                        </span></td>
                                    <td class="text-end fw-bold <?= $lineProfit >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:.83rem;">
                                        <?= $lineProfit >= 0 ? '' : '−' ?>

                                        €<?= number_format(abs($lineProfit), 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer-bar">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                        <span class="text-muted" style="font-size:.78rem;">
                            <i class="bi bi-list-ul me-1"></i>
                            Showing <strong><?= count($pagedLines) ?>
                            </strong> of <strong><?= $totalLines ?>
                            </strong> line<?= $totalLines !== 1 ? 's' : '' ?>
                        </span>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Order line pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="javascript:void(0)" data-page="<?= max(1, $page - 1) ?>">Prev</a>
                                    </li>
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end   = min($totalPages, $page + 2);
                                    for ($p = $start; $p <= $end; $p++): ?>
                                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="javascript:void(0)" data-page="<?= $p ?>"><?= $p ?>

                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="javascript:void(0)" data-page="<?= min($totalPages, $page + 1) ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>


    </div>
<?php
} // end renderPLContent()

/* ---------------- AJAX REQUEST ---------------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $fromDate    = $_GET['from']         ?? '';
    $toDate      = $_GET['to']           ?? '';
    $orderNumber = $_GET['order_number'] ?? '';
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $per_page    = $default_per_page;

    [$totalRevenue, $totalCOGS, $orderLines] = getProfitLossData($conn, $fromDate, $toDate, $orderNumber);
    $grossProfit = $totalRevenue - $totalCOGS;

    $fixedExpenses = [
        ["Salaries & Wages", 20000],
        ["Rent & Utilities", 5000],
        ["Marketing",        3000],
    ];
    $operatingExpenses = $fixedExpenses;
    foreach ($otherExpenses as $exp) {
        $operatingExpenses[] = [$exp['name'], $exp['amount']];
    }
    $totalExpenses = array_sum(array_column($operatingExpenses, 1));
    $netProfit     = $grossProfit - $totalExpenses;

    // Output the full HTML content for AJAX
    renderPLContent($totalRevenue, $totalCOGS, $grossProfit, $operatingExpenses, $fixedExpenses, $otherExpenses, $totalExpenses, $netProfit, $orderLines, $fromDate, $toDate, $orderNumber, $page, $per_page);
    exit;
}

/* ---------------- NORMAL PAGE LOAD ---------------- */
$fromDate    = $_GET['from']         ?? '';
$toDate      = $_GET['to']           ?? '';
$orderNumber = $_GET['order_number'] ?? '';

[$totalRevenue, $totalCOGS, $orderLines] = getProfitLossData($conn, $fromDate, $toDate, $orderNumber);
$grossProfit = $totalRevenue - $totalCOGS;

$fixedExpenses = [
    ["Salaries & Wages", 20000],
    ["Rent & Utilities", 5000],
    ["Marketing",        3000],
];
$operatingExpenses = $fixedExpenses;
foreach ($otherExpenses as $exp) {
    $operatingExpenses[] = [$exp['name'], $exp['amount']];
}
$totalExpenses = array_sum(array_column($operatingExpenses, 1));
$netProfit     = $grossProfit - $totalExpenses;

include("../templates/header.php");
include("../templates/navbar.php");
?>
<div class="page-wrapper">

    <!-- DYNAMIC CONTENT — replaced by AJAX -->
    <div id="plContent">
        <?php renderPLContent($totalRevenue, $totalCOGS, $grossProfit, $operatingExpenses, $fixedExpenses, $otherExpenses, $totalExpenses, $netProfit, $orderLines, $fromDate, $toDate, $orderNumber, $page, $per_page); ?>


    </div>

</div><!-- /page-wrapper -->

<!-- ADD EXPENSE MODAL -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="action" value="add_expense">
            <div class="modal-header border-0 rounded-top-3" style="background:linear-gradient(135deg,#10b981,#059669);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-plus-circle fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add Expense</h5>
                        <small class="text-white opacity-75">Add a custom operating expense</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-uppercase text-muted">
                        Expense Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="expense_name" class="form-control modal-input" placeholder="e.g., Office Supplies" required>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold small text-uppercase text-muted">
                        Amount (€) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light" style="border-radius:9px 0 0 9px;border:1.5px solid #e2e8f0;border-right:none;">€</span>
                        <input type="number" step="0.01" name="expense_amount" class="form-control modal-input" style="border-radius:0 9px 9px 0 !important;" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-success btn-sm px-4">
                    <i class="bi bi-plus-circle me-1"></i>Add Expense
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    /* ── helpers ── */
    function getFilterParams() {
        return {
            from: (document.getElementById('filter_from') || {
                value: ''
            }).value.trim(),
            to: (document.getElementById('filter_to') || {
                value: ''
            }).value.trim(),
            order_number: (document.getElementById('filter_order') || {
                value: ''
            }).value.trim()
        };
    }

    function syncPdfFields(p) {
        var pf = document.getElementById('pdf_from');
        var pt = document.getElementById('pdf_to');
        var po = document.getElementById('pdf_order');
        if (pf) pf.value = p.from;
        if (pt) pt.value = p.to;
        if (po) po.value = p.order_number;
    }

    /* ── main filter fetch ── */
    function doFilter(page) {
        var p = getFilterParams();
        syncPdfFields(p);
        page = page || 1;

        var qs = new URLSearchParams();
        if (p.from) qs.set('from', p.from);
        if (p.to) qs.set('to', p.to);
        if (p.order_number) qs.set('order_number', p.order_number);
        qs.set('page', page);

        document.getElementById('plContent').innerHTML =
            '<div class="text-center py-5">' +
            '<div class="spinner-border text-success opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
            '<p class="text-muted small mt-2">Loading…</p></div>';

        qs.set('ajax', '1');
        fetch('profit-loss.php?' + qs.toString())
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function(html) {
                document.getElementById('plContent').innerHTML = html;
                bindEvents(); // re-bind because filter bar is inside plContent
                qs.delete('ajax');
                history.pushState(null, '', qs.toString() ? 'profit-loss.php?' + qs.toString() : 'profit-loss.php');
            })
            .catch(function(err) {
                document.getElementById('plContent').innerHTML =
                    '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle me-2"></i>Failed to load data. Please try again.</div>';
                console.error('Filter error:', err);
            });
    }

    /* ── bind all interactive elements inside plContent ── */
    function bindEvents() {
        var searchBtn = document.getElementById('filterSearchBtn');
        var resetBtn = document.getElementById('filterResetBtn');
        var orderInput = document.getElementById('filter_order');
        var pdfBtn = document.getElementById('downloadPdfBtn');
        var selectAll = document.getElementById('selectAll');

        if (searchBtn) searchBtn.onclick = function() {
            doFilter(1);
        };

        if (resetBtn) resetBtn.onclick = function() {
            document.getElementById('filter_from').value = '';
            document.getElementById('filter_to').value = '';
            document.getElementById('filter_order').value = '';
            doFilter(1);
        };

        if (orderInput) orderInput.onkeydown = function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doFilter(1);
            }
        };

        document.querySelectorAll('.pagination .page-link[data-page]').forEach(function(link) {
            link.onclick = function(e) {
                e.preventDefault();
                doFilter(parseInt(link.dataset.page, 10) || 1);
            };
        });

        if (pdfBtn) pdfBtn.onclick = function(e) {
            e.preventDefault();
            var checked = document.querySelectorAll('input[name="orders[]"]:checked');
            if (checked.length === 0) {
                alert('Please select at least one order to download the PDF.');
                return;
            }
            // Sync current filter values into pdfForm hidden fields
            var p = getFilterParams();
            syncPdfFields(p);
            // Copy checked order IDs into pdfForm
            document.querySelectorAll('.pdf-order-input').forEach(function(el) {
                el.remove();
            });
            checked.forEach(function(cb) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'orders[]';
                inp.value = cb.value;
                inp.className = 'pdf-order-input';
                document.getElementById('pdfForm').appendChild(inp);
            });
            document.getElementById('pdfForm').submit();
        };

        if (selectAll) selectAll.onchange = function() {
            document.querySelectorAll('.row-check').forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
        };
    }

    /* ── browser back/forward ── */
    window.addEventListener('popstate', function() {
        var p = new URLSearchParams(window.location.search);
        var fi = document.getElementById('filter_from');
        var ti = document.getElementById('filter_to');
        var oi = document.getElementById('filter_order');
        if (fi) fi.value = p.get('from') || '';
        if (ti) ti.value = p.get('to') || '';
        if (oi) oi.value = p.get('order_number') || '';
        doFilter(parseInt(p.get('page') || '1', 10) || 1);
    });

    /* ── initial bind on page load ── */
    bindEvents();
</script>

<?php include("../templates/footer.php"); ?>
