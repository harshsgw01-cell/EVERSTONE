<?php
session_start();
date_default_timezone_set('America/Los_Angeles');
include("../config/database.php");
include("../includes/auth.php");
include("../includes/dashboard_chart.php");
check_auth();

/* ═══════════════════════════════════════════════
   GLOBAL COUNTS
═══════════════════════════════════════════════ */
$total_customers = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM customers"))[0];
$total_rfqs      = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM rfqs"))[0];
$total_orders    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders"))[0];
$total_invoices  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM invoices"))[0];
$total_revenue   = mysqli_fetch_row(mysqli_query($conn, "SELECT IFNULL(SUM(total_amount),0) FROM invoices WHERE status='Paid'"))[0];
$role      = $_SESSION['role']     ?? 'Sales';
$user_id   = $_SESSION['user_id']  ?? 0;
$user_name = $_SESSION['username'] ?? 'User';

/* ═══════════════════════════════════════════════
   DATE FILTER
═══════════════════════════════════════════════ */
$allowed = ['today', 'month', 'year'];
$filter  = in_array($_GET['filter'] ?? '', $allowed) ? $_GET['filter'] : 'month';
switch ($filter) {
    case 'today':
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        $label = 'Today';
        break;
    case 'year':
        $date_from = date('Y-01-01');
        $date_to = date('Y-12-t', mktime(0, 0, 0, 12, 1));
        $label = 'This Year';
        break;
    default:
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
        $label = 'This Month';
        $filter = 'month';
}
$df = mysqli_real_escape_string($conn, $date_from);
$dt = mysqli_real_escape_string($conn, $date_to);

/* ═══════════════════════════════════════════════
   ALERT TRIGGERS (all roles)
═══════════════════════════════════════════════ */
$overdue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt, IFNULL(SUM(total_amount),0) AS amt FROM invoices WHERE status != 'Paid' AND due_date < CURDATE()"));
$expiring = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE status NOT IN ('Lost','Converted') AND DATE_ADD(quote_date, INTERVAL CAST(validity AS UNSIGNED) MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"));
$stalled  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE status NOT IN ('Completed','Cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"));

/* ═══════════════════════════════════════════════
   HERO STRIP COUNTS
═══════════════════════════════════════════════ */
$count_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM customers"))['cnt'];
$count_rfqs      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE status != 'Lost'"))['cnt'];
$count_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders"))['cnt'];
$count_invoices  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM invoices"))['cnt'];

/* ═══════════════════════════════════════════════
   ADMIN QUERIES
═══════════════════════════════════════════════ */
if ($role === 'Admin') {
    $rev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        IFNULL(SUM(total_amount),0) AS invoiced,
        IFNULL(SUM(CASE WHEN status='Paid' THEN total_amount ELSE 0 END),0) AS paid,
        IFNULL(SUM(CASE WHEN status!='Paid' THEN total_amount ELSE 0 END),0) AS outstanding
        FROM invoices WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"));

    $profit_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        IFNULL(SUM(ol.qty * ol.unit_price),0) AS revenue,
        IFNULL(SUM(ol.qty * ol.cost_price),0) AS cost
        FROM order_lines ol JOIN orders o ON ol.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN '$df' AND '$dt'"));
    $net_profit  = $profit_row['revenue'] - $profit_row['cost'];
    $margin_pct  = $profit_row['revenue'] > 0 ? round(($net_profit / $profit_row['revenue']) * 100, 1) : 0;

    /* NEW KPI: avg order value */
    $avg_order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        IFNULL(AVG(ol.qty * ol.unit_price),0) AS avg_val
        FROM order_lines ol JOIN orders o ON ol.order_id=o.id
        WHERE DATE(o.created_at) BETWEEN '$df' AND '$dt'"))['avg_val'];

    /* NEW KPI: new customers this period */
    $new_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM customers WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"))['cnt'];

    /* NEW KPI: RFQ → Order conversion rate */
    $rfq_conv_num   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE status='Converted' AND DATE(created_at) BETWEEN '$df' AND '$dt'"))['cnt'];
    $rfq_conv_denom = max(1, mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"))['cnt']);
    $rfq_conv_rate  = round(($rfq_conv_num / $rfq_conv_denom) * 100);

    /* NEW KPI: payment collection rate */
    $collection_rate = $rev['invoiced'] > 0 ? round(($rev['paid'] / $rev['invoiced']) * 100) : 0;

    $chart_data = dashboard_fetch_chart($conn, $filter);
    extract($chart_data, EXTR_PREFIX_ALL, 'chart');

    $top_customers = mysqli_query($conn, "SELECT c.id, c.name,
        IFNULL(SUM(i.total_amount),0) AS total
        FROM customers c JOIN invoices i ON i.customer_id = c.id
        WHERE DATE(i.created_at) BETWEEN '$df' AND '$dt'
        GROUP BY c.id, c.name ORDER BY total DESC LIMIT 5");

    /* Order status breakdown */
    $order_breakdown = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        SUM(CASE WHEN status='Pending'     THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status='Completed'   THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status='Cancelled'   THEN 1 ELSE 0 END) AS cancelled
        FROM orders WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"));

    $act_rfqs     = mysqli_query($conn, "SELECT r.id, r.rfq_number AS ref, r.status, r.created_at, c.name AS customer FROM rfqs r LEFT JOIN customers c ON r.customer_id = c.id ORDER BY r.created_at DESC LIMIT 6");
    $act_orders   = mysqli_query($conn, "SELECT o.id, o.code AS ref, o.status, o.created_at, c.name AS customer FROM orders o LEFT JOIN customers c ON o.customer_id = c.id ORDER BY o.created_at DESC LIMIT 6");
    $act_invoices = mysqli_query($conn, "SELECT i.id, i.code AS ref, i.status, i.created_at, i.total_amount AS amount, c.name AS customer FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id ORDER BY i.created_at DESC LIMIT 6");
}

/* ═══════════════════════════════════════════════
   ACCOUNT QUERIES
═══════════════════════════════════════════════ */
if ($role === 'Account') {
    $inv_stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='Draft'   THEN 1 ELSE 0 END) AS draft,
        SUM(CASE WHEN status='Sent'    THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status='Paid'    THEN 1 ELSE 0 END) AS paid_cnt,
        SUM(CASE WHEN status='Overdue' THEN 1 ELSE 0 END) AS overdue_cnt,
        IFNULL(SUM(CASE WHEN status='Draft'   THEN total_amount ELSE 0 END),0) AS draft_amt,
        IFNULL(SUM(CASE WHEN status='Sent'    THEN total_amount ELSE 0 END),0) AS sent_amt,
        IFNULL(SUM(CASE WHEN status='Paid'    THEN total_amount ELSE 0 END),0) AS paid_amt,
        IFNULL(SUM(CASE WHEN status='Overdue' THEN total_amount ELSE 0 END),0) AS overdue_amt,
        IFNULL(SUM(CASE WHEN status='Paid' AND DATE(created_at) BETWEEN '$df' AND '$dt' THEN total_amount ELSE 0 END),0) AS paid_this_period,
        IFNULL(SUM(CASE WHEN DATE(created_at) BETWEEN '$df' AND '$dt' THEN total_amount ELSE 0 END),0) AS expected_this_period
        FROM invoices"));

    /* NEW: avg days overdue */
    $avg_days_overdue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(AVG(DATEDIFF(CURDATE(),due_date)),0) AS avg_days FROM invoices WHERE status!='Paid' AND due_date < CURDATE()"))['avg_days'];

    /* NEW: invoices due this week */
    $due_this_week = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt, IFNULL(SUM(total_amount),0) AS amt FROM invoices WHERE status NOT IN ('Paid') AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)"));

    $overdue_list = mysqli_query($conn, "SELECT i.code, c.name AS customer, i.total_amount, i.due_date,
        DATEDIFF(CURDATE(), i.due_date) AS days_overdue
        FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.status != 'Paid' AND i.due_date < CURDATE()
        ORDER BY days_overdue DESC LIMIT 10");

    $upcoming = mysqli_query($conn, "SELECT i.code, c.name AS customer, i.total_amount, i.due_date
        FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.status NOT IN ('Paid') AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        ORDER BY i.due_date ASC LIMIT 10");
}

/* ═══════════════════════════════════════════════
   SALES QUERIES
═══════════════════════════════════════════════ */
if ($role === 'Sales') {
    $my_rfqs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status NOT IN ('Lost','Converted') AND DATE_ADD(quote_date, INTERVAL CAST(validity AS UNSIGNED) MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS expiring,
        SUM(CASE WHEN status='Converted' AND DATE(created_at) BETWEEN '$df' AND '$dt' THEN 1 ELSE 0 END) AS won,
        SUM(CASE WHEN status='Lost' AND DATE(created_at) BETWEEN '$df' AND '$dt' THEN 1 ELSE 0 END) AS lost,
        SUM(CASE WHEN status NOT IN ('Lost','Converted') THEN 1 ELSE 0 END) AS open_cnt
        FROM rfqs"));

    $won_this   = $my_rfqs['won'];
    $total_this = max(1, mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"))['cnt']);
    $conv_rate  = round(($won_this / $total_this) * 100);

    $my_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='Pending'     THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status='Completed'   THEN 1 ELSE 0 END) AS completed
        FROM orders WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"));

    /* NEW: pipeline value */
    $pipeline_value = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(total_amount),0) AS val FROM rfqs r LEFT JOIN invoices i ON i.customer_id=r.customer_id WHERE r.status NOT IN ('Lost','Converted')"))['val'] ?? 0;

    /* NEW: RFQs created this period */
    $rfqs_created = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs WHERE DATE(created_at) BETWEEN '$df' AND '$dt'"))['cnt'];
}

$body_class = 'dashboard-page';
include("../templates/header.php");
include("../templates/navbar.php");
?>

<?php
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$role_bg  = $role === 'Admin' ? 'linear-gradient(135deg,#6366f1,#4f46e5)' : ($role === 'Sales' ? 'linear-gradient(135deg,#8b5cf6,#7c3aed)' : 'linear-gradient(135deg,#10b981,#059669)');
$role_icon = $role === 'Admin' ? 'shield-fill-check' : ($role === 'Sales' ? 'graph-up-arrow' : 'receipt-cutoff');
?>


<div class="dw">

    <!-- ▌HERO ▐ -->
    <div class="anim-in">
        <div class="hero-glow"></div>
        <div class="hero-top">
            <div>
                <h1 class="hero-name">
                    <?= $greeting ?>, <span><?= htmlspecialchars($user_name) ?></span>
                </h1>
                <p class="hero-sub">
                    <?= date('l, F j Y') ?>
                    <span class="role-pill">
                        <i class="bi bi-<?= $role_icon ?>" style="font-size:.6rem;"></i>
                        <?= htmlspecialchars($role) ?>
                    </span>
                </p>
            </div>
            <div class="hero-right">
                <div class="hero-date-chip">
                    <i class="bi bi-calendar3" style="color:rgba(255,255,255,.5);"></i>
                    <?= date('M j, Y') ?>
                </div>
                <div class="hero-filters">
                    <a href="?filter=today" class="hf-btn <?= $filter === 'today' ? 'on' : '' ?>">Today</a>
                    <a href="?filter=month" class="hf-btn <?= $filter === 'month' ? 'on' : '' ?>">Month</a>
                    <a href="?filter=year" class="hf-btn <?= $filter === 'year' ? 'on' : '' ?>">Year</a>
                </div>
            </div>
        </div>

        <!-- strip -->
        <div class="hero-strip">
            <?php foreach (
                [
                    ['Customers',   $count_customers, '../modules/customers.php', '#3b82f6', 'rgba(59,130,246,.14)', 'people-fill'],
                    ['Active RFQs', $count_rfqs,      '../modules/rfqs.php',      '#f59e0b', 'rgba(245,158,11,.16)', 'clipboard-check'],
                    ['Orders',      $count_orders,    '../modules/order.php',     '#10b981', 'rgba(16,185,129,.16)', 'bag-check'],
                    ['Invoices',    $count_invoices,  '../modules/invoices.php',  '#8b5cf6', 'rgba(139,92,246,.14)', 'receipt'],
                ] as [$k, $v, $h, $col, $bg, $ico]
            ): ?>
                <a href="<?= $h ?>" class="hstrip-item rfq-stat-card" style="--stat-color:<?= $col ?>;--stat-bg:<?= $bg ?>;">
                    <div>
                        <div class="hstrip-lbl"><?= $k ?></div>
                        <div class="hstrip-num"><?= number_format($v) ?></div>
                    </div>
                    <div class="hstrip-icon"><i class="bi bi-<?= $ico ?>"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ▌ALERTS ▐ -->
    <?php if ($overdue['cnt'] > 0 || $expiring['cnt'] > 0 || $stalled['cnt'] > 0): ?>
        <div class="alert-row">
            <?php if ($overdue['cnt'] > 0): ?>
                <div class="da da-red">
                    <div class="da-ico"><i class="bi bi-exclamation-circle-fill"></i></div>
                    <div class="da-body"><strong><?= $overdue['cnt'] ?> overdue invoice<?= $overdue['cnt'] > 1 ? 's' : '' ?></strong> — $<?= number_format($overdue['amt'], 2) ?> outstanding</div>
                    <a href="../modules/invoices.php?status=Overdue" class="da-cta"><span>View</span></a>
                </div>
            <?php endif; ?>
            <?php if ($expiring['cnt'] > 0): ?>
                <div class="da da-amber">
                    <div class="da-ico"><i class="bi bi-clock-fill"></i></div>
                    <div class="da-body"><strong><?= $expiring['cnt'] ?> RFQ<?= $expiring['cnt'] > 1 ? 's' : '' ?></strong> expiring within 7 days</div>
                    <a href="../modules/rfqs.php" class="da-cta"><span>Review</span></a>
                </div>
            <?php endif; ?>
            <?php if ($stalled['cnt'] > 0): ?>
                <div class="da da-blue">
                    <div class="da-ico"><i class="bi bi-hourglass-split"></i></div>
                    <div class="da-body"><strong><?= $stalled['cnt'] ?> order<?= $stalled['cnt'] > 1 ? 's' : '' ?></strong> stalled 30+ days</div>
                    <a href="../modules/order.php" class="da-cta"><span>View</span></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════════════════
     ████████  ADMIN  ████████
══════════════════════════════════════════════════════ -->
    <?php if ($role === 'Admin'): ?>

        <!-- Quick KPI Row -->
        <div class="row g-3 mb-4">

            <?php $adm_kpis = [
                ['Total Invoiced', '$' . number_format($rev['invoiced'], 2), '#3b82f6', 'rgba(59,130,246,.14)', 'receipt', '../modules/invoices.php', $label],
                ['Total Paid',    '$' . number_format($rev['paid'], 2),    '#10b981', 'rgba(16,185,129,.16)', 'check-circle-fill', '../modules/invoices.php?filter=Paid', $label],
                ['Outstanding',   '$' . number_format($rev['outstanding'], 2), '#f59e0b', 'rgba(245,158,11,.16)', 'hourglass-split', '../modules/invoices.php', $label],
                ['Net Profit',    '$' . number_format($net_profit, 2), $net_profit >= 0 ? '#10b981' : '#ef4444', $net_profit >= 0 ? 'rgba(16,185,129,.16)' : 'rgba(239,68,68,.14)', 'graph-up-arrow', '../modules/profit-loss.php', $label],
            ];
            foreach ($adm_kpis as [$lbl, $val, $col, $bg, $ico, $href, $sub]):
            ?>
                <div class="col-6 col-md-3 anim-in">
                    <a href="<?= $href ?>" class="dc hov kc d-block rfq-stat-card" style="--kc-color:<?= $col ?>;--kc-bg:<?= $bg ?>;">
                        <div class="kc-strip"></div>
                        <div class="kc-glow"></div>
                        <div class="kc-row1">
                            <div class="kc-icon"><i class="bi bi-<?= $ico ?>"></i></div>
                            <span class="kc-badge"><?= $label ?></span>
                        </div>
                        <div class="kc-lbl"><?= $lbl ?></div>
                        <div class="kc-num"><?= $val ?></div>
                        <?php if ($sub): ?><div class="kc-sub"><?= $sub ?></div><?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- NEW KPI row: operational metrics -->
        <div class="sec-label">Operational KPIs — <?= $label ?></div>
        <div class="row g-3 mb-4">

            <div class="col-6 col-md-3 anim-in">
                <?php $rc = $rfq_conv_rate >= 50 ? '#10b981' : ($rfq_conv_rate >= 25 ? '#f59e0b' : '#ef4444');
                $rb = $rfq_conv_rate >= 50 ? 'rgba(16,185,129,.1)' : ($rfq_conv_rate >= 25 ? 'rgba(245,158,11,.1)' : 'rgba(239,68,68,.1)'); ?>
                <div class="dc hov kc rfq-stat-card" style="--kc-color:<?= $rc ?>;--kc-bg:<?= $rb ?>;">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-funnel-fill"></i></div>
                        <span class="kc-badge"><?= $label ?></span>
                    </div>
                    <div class="kc-lbl">RFQ Conversion</div>
                    <div class="kc-num"><?= $rfq_conv_rate ?>%</div>
                    <div class="kc-sub"><?= $rfq_conv_num ?> of <?= $rfq_conv_denom ?> RFQs converted</div>
                </div>
            </div>

            <div class="col-6 col-md-3 anim-in">
                <div class="dc hov kc rfq-stat-card" style="--kc-color:#6366f1;--kc-bg:rgba(99,102,241,.1);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-bag-fill"></i></div>
                        <span class="kc-badge"><?= $label ?></span>
                    </div>
                    <div class="kc-lbl">Avg Order Value</div>
                    <div class="kc-num sm">$<?= number_format($avg_order, 0) ?></div>
                    <div class="kc-sub">Per order line</div>
                </div>
            </div>

            <div class="col-6 col-md-3 anim-in">
                <?php $pending_c = $order_breakdown['pending'] ?? 0;
                $inprog_c = $order_breakdown['in_progress'] ?? 0;
                $comp_c = $order_breakdown['completed'] ?? 0; ?>
                <div class="dc dc-body" style="padding: 14px 14px;">
                    <div class="ct" style="margin-bottom:10px;font-size:.75rem;">
                        <div class="ct-bar" style="background:#8b5cf6;"></div>
                        Order Status — <?= $label ?>
                    </div>
                    <div class="bk-grid cols3">
                        <?php foreach (
                            [
                                [$pending_c, '#f59e0b', 'rgba(245,158,11,.08)', 'hourglass-split', 'Pending'],
                                [$inprog_c, '#3b82f6', 'rgba(59,130,246,.08)', 'gear-fill', 'Active'],
                                [$comp_c, '#10b981', 'rgba(16,185,129,.08)', 'check-circle-fill', 'Done'],
                            ] as [$n, $col, $bg, $ico, $lbl]
                        ): ?>
                            <div class="bk" style="--bk-bg:<?= $bg ?>;">
                                <div class="bk-ico" style="color:<?= $col ?>;"><i class="bi bi-<?= $ico ?>"></i></div>
                                <div class="bk-num" style="color:<?= $col ?>;"><?= $n ?></div>
                                <div class="bk-lbl"><?= $lbl ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3 anim-in">
                <div class="dc hov kc rfq-stat-card" style="--kc-color:#ef4444;--kc-bg:rgba(239,68,68,.08);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <span class="kc-badge">Live</span>
                    </div>
                    <div class="kc-lbl">Overdue Invoices</div>
                    <div class="kc-num sm">$<?= number_format($overdue['amt'], 0) ?></div>
                    <div class="kc-sub"><?= $overdue['cnt'] ?> invoice<?= $overdue['cnt'] != 1 ? 's' : '' ?> unpaid</div>
                    <?php if ($overdue['cnt'] > 0): ?>
                        <div class="kc-chip warn"><i class="bi bi-clock" style="font-size:.6rem;"></i>Needs attention</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chart + Top Customers -->
        <div class="sec-label" id="dashAnalyticsLabel">Analytics — <?= htmlspecialchars($chart_label ?? $label) ?></div>
        <div class="row g-3 mb-4">

            <div class="col-12 col-lg-8 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#4f46e5;"></div>
                            Revenue vs Cost
                            <span class="ct-badge" id="dashChartGranularity"><?= htmlspecialchars($chart_granularity ?? '') ?></span>
                        </div>
                        <span class="ct-badge" id="dashChartMargin" style="background:rgba(16,185,129,.1);color:#059669;border-color:rgba(16,185,129,.2);">Margin: <?= $chart_margin_pct ?? $margin_pct ?>%</span>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="revenueChart"
                            data-initial-chart="<?= htmlspecialchars(json_encode($chart_data ?? []), ENT_QUOTES, 'UTF-8') ?>"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#f59e0b;"></div>
                            Top Customers
                            <span class="ct-badge"><?= $label ?></span>
                        </div>
                        <a href="../modules/customers.php" class="c-link" style="color:#f59e0b;">All →</a>
                    </div>
                    <?php if (mysqli_num_rows($top_customers) > 0):
                        $tc_rows = [];
                        while ($tc = mysqli_fetch_assoc($top_customers)) $tc_rows[] = $tc;
                        $max_tc = max(array_column($tc_rows, 'total')) ?: 1;
                        foreach ($tc_rows as $idx => $tc):
                            $rank = $idx + 1;
                            $rc = match ($rank) {
                                1 => 'r1',
                                2 => 'r2',
                                3 => 'r3',
                                default => 'rn'
                            };
                    ?>
                            <div class="tcr">
                                <span class="tcr-rank <?= $rc ?>"><?= $rank ?></span>
                                <a href="../modules/customers.php" class="tcr-name"><?= htmlspecialchars($tc['name']) ?></a>
                                <span class="tcr-amt">$<?= number_format($tc['total'], 0) ?></span>
                            </div>
                            <div class="tcr-prog">
                                <div class="tcr-prog-fill" style="width:<?= round(($tc['total'] / $max_tc) * 100) ?>%;"></div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-people display-5 d-block mb-2" style="color:var(--color-border);"></i>
                            <p class="mb-0" style="font-size:.78rem;color:var(--color-text-muted);">No data for this period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Invoice trend (new chart) + Recent Activity 3 col -->
        <div class="sec-label">Recent Activity</div>
        <div class="row g-3 mb-3">

            <!-- Recent RFQs -->
            <div class="col-12 col-md-4 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#3b82f6;"></div>Recent RFQs
                        </div>
                        <a href="../modules/rfqs.php" class="c-link" style="color:#3b82f6;">All →</a>
                    </div>
                    <div class="dt-wrap">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Ref</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($act = mysqli_fetch_assoc($act_rfqs)):
                                    $sc = match ($act['status']) {
                                        'Converted' => 'sb-ok',
                                        'Lost' => 'sb-red',
                                        'Open' => 'sb-blue',
                                        default => 'sb-dim'
                                    };
                                ?>
                                    <tr onclick="window.location='../modules/rfq_edit.php?id=<?= (int)$act['id'] ?>'">
                                        <td><span class="ref-p" style="background:rgba(59,130,246,.1);color:#2563eb;"><?= htmlspecialchars($act['ref']) ?></span></td>
                                        <td class="td-cut td-dim"><?= htmlspecialchars($act['customer'] ?? '—') ?></td>
                                        <td><span class="sb <?= $sc ?>"><?= htmlspecialchars($act['status']) ?></span></td>
                                        <td class="td-dim"><?= date('M j', strtotime($act['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="col-12 col-md-4 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#10b981;"></div>Recent Orders
                        </div>
                        <a href="../modules/order.php" class="c-link" style="color:#10b981;">All →</a>
                    </div>
                    <div class="dt-wrap">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Ref</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($act = mysqli_fetch_assoc($act_orders)):
                                    $sc = match ($act['status']) {
                                        'Completed' => 'sb-ok',
                                        'In Progress' => 'sb-blue',
                                        'Pending' => 'sb-warn',
                                        'Cancelled' => 'sb-red',
                                        default => 'sb-dim'
                                    };
                                ?>
                                    <tr onclick="window.location='../modules/order_view.php?id=<?= (int)$act['id'] ?>'">
                                        <td><span class="ref-p" style="background:rgba(16,185,129,.1);color:#059669;"><?= htmlspecialchars($act['ref']) ?></span></td>
                                        <td class="td-cut td-dim"><?= htmlspecialchars($act['customer'] ?? '—') ?></td>
                                        <td><span class="sb <?= $sc ?>"><?= htmlspecialchars($act['status']) ?></span></td>
                                        <td class="td-dim"><?= date('M j', strtotime($act['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="col-12 col-md-4 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#f59e0b;"></div>Recent Invoices
                        </div>
                        <a href="../modules/invoices.php" class="c-link" style="color:#f59e0b;">All →</a>
                    </div>
                    <div class="dt-wrap">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Ref</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($act = mysqli_fetch_assoc($act_invoices)):
                                    $sc = match ($act['status']) {
                                        'Paid' => 'sb-ok',
                                        'Sent' => 'sb-sky',
                                        'Draft' => 'sb-dim',
                                        'Overdue' => 'sb-red',
                                        default => 'sb-dim'
                                    };
                                ?>
                                    <tr onclick="window.location='../modules/view_invoice.php?id=<?= (int)$act['id'] ?>'">
                                        <td><span class="ref-p" style="background:rgba(245,158,11,.1);color:#d97706;"><?= htmlspecialchars($act['ref']) ?></span></td>
                                        <td class="td-cut td-dim"><?= htmlspecialchars($act['customer'] ?? '—') ?></td>
                                        <td style="font-weight:700;font-size:.77rem;">$<?= number_format($act['amount'] ?? 0, 2) ?></td>
                                        <td><span class="sb <?= $sc ?>"><?= htmlspecialchars($act['status']) ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?= htmlspecialchars(asset_url('js/dashboard-chart.js')) ?>"></script>


        <!-- ══════════════════════════════════════════════════════
     ████████  ACCOUNT  ████████
══════════════════════════════════════════════════════ -->
    <?php elseif ($role === 'Account'): ?>

        <!-- 4 status KPI cards -->
        <div class="sec-label">Invoice Overview</div>
        <div class="row g-3 mb-4">
            <?php $inv_cards = [
                ['Draft',   $inv_stats['draft'],      $inv_stats['draft_amt'],   '#64748b', 'rgba(100,116,139,.1)', 'file-earmark'],
                ['Sent',    $inv_stats['sent'],        $inv_stats['sent_amt'],    '#0ea5e9', 'rgba(14,165,233,.1)', 'send-fill'],
                ['Paid',    $inv_stats['paid_cnt'],    $inv_stats['paid_amt'],    '#10b981', 'rgba(16,185,129,.1)', 'check-circle-fill'],
                ['Overdue', $inv_stats['overdue_cnt'], $inv_stats['overdue_amt'],  '#ef4444', 'rgba(239,68,68,.1)',  'exclamation-circle-fill'],
            ];
            foreach ($inv_cards as [$status, $cnt, $amt, $col, $bg, $ico]): ?>
                <div class="col-6 col-md-3 anim-in">
                    <a href="../modules/invoices.php?status=<?= $status ?>"
                        class="dc hov kc d-block" style="--kc-color:<?= $col ?>;--kc-bg:<?= $bg ?>;">
                        <div class="kc-strip"></div>
                        <div class="kc-glow"></div>
                        <div class="kc-row1">
                            <div class="kc-icon"><i class="bi bi-<?= $ico ?>"></i></div>
                            <span class="kc-badge"><?= $status ?></span>
                        </div>
                        <div class="kc-lbl"><?= $status ?> Invoices</div>
                        <div class="kc-num"><?= number_format($cnt) ?></div>
                        <div class="kc-sub" style="color:<?= $col ?>;font-weight:700;">$<?= number_format($amt, 2) ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- New: 3 extra KPIs + collection progress -->
        <div class="row g-3 mb-4">

            <!-- Collection progress card -->
            <?php $pct = $inv_stats['expected_this_period'] > 0
                ? min(100, round(($inv_stats['paid_this_period'] / $inv_stats['expected_this_period']) * 100)) : 0;
            $pc = $pct >= 70 ? '#10b981' : ($pct >= 40 ? '#f59e0b' : '#ef4444');
            $pg = $pct >= 70 ? 'linear-gradient(90deg,#10b981,#059669)'
                : ($pct >= 40 ? 'linear-gradient(90deg,#f59e0b,#d97706)'
                    : 'linear-gradient(90deg,#ef4444,#dc2626)');
            ?>
            <div class="col-12 col-md-6 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#10b981;"></div>Collection Progress<span class="ct-badge"><?= $label ?></span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px;">
                        <div>
                            <span style="font-size:.78rem;color:var(--color-text-secondary);">
                                <strong style="color:var(--color-text-primary);">$<?= number_format($inv_stats['paid_this_period'], 2) ?></strong>
                                collected of
                                <strong style="color:var(--color-text-primary);">$<?= number_format($inv_stats['expected_this_period'], 2) ?></strong>
                            </span>
                        </div>
                        <div class="pbar-pct" style="color:<?= $pc ?>;"><?= $pct ?>%</div>
                    </div>
                    <div class="pbar-track">
                        <div class="pbar-fill" style="width:<?= $pct ?>%;background:<?= $pg ?>;"></div>
                    </div>
                    <div class="pbar-note"><?= $pct >= 70 ? '✓ On track' : ($pct >= 40 ? '⚠ Below target' : '⚡ Urgent — chase overdue') ?></div>

                    <!-- quick mini stats -->
                    <div class="mstat-grid" style="margin-top:12px;">
                        <div class="mstat">
                            <div class="mstat-num" style="color:#ef4444;"><?= round($avg_days_overdue) ?>d</div>
                            <div class="mstat-lbl">Avg Days Overdue</div>
                        </div>
                        <div class="mstat">
                            <div class="mstat-num" style="color:#f59e0b;"><?= $due_this_week['cnt'] ?></div>
                            <div class="mstat-lbl">Due This Week</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Due this week value + total overdue -->
            <div class="col-12 col-md-6 anim-in">
                <div class="row g-3 h-100">
                    <div class="col-6">
                        <div class="dc hov kc h-100" style="--kc-color:#f59e0b;--kc-bg:rgba(245,158,11,.1);">
                            <div class="kc-strip"></div>
                            <div class="kc-glow"></div>
                            <div class="kc-row1">
                                <div class="kc-icon"><i class="bi bi-calendar-week-fill"></i></div>
                                <span class="kc-badge">7 days</span>
                            </div>
                            <div class="kc-lbl">Due This Week</div>
                            <div class="kc-num sm">$<?= number_format($due_this_week['amt'], 0) ?></div>
                            <div class="kc-sub"><?= $due_this_week['cnt'] ?> invoice<?= $due_this_week['cnt'] != 1 ? 's' : '' ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="dc hov kc h-100" style="--kc-color:#8b5cf6;--kc-bg:rgba(139,92,246,.1);">
                            <div class="kc-strip"></div>
                            <div class="kc-glow"></div>
                            <div class="kc-row1">
                                <div class="kc-icon"><i class="bi bi-receipt-cutoff"></i></div>
                                <span class="kc-badge">All time</span>
                            </div>
                            <div class="kc-lbl">Total Invoiced</div>
                            <div class="kc-num sm">$<?= number_format($inv_stats['paid_amt'] + $inv_stats['sent_amt'] + $inv_stats['draft_amt'] + $inv_stats['overdue_amt'], 0) ?></div>
                            <div class="kc-sub"><?= $inv_stats['total'] ?> invoices</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue + Upcoming tables -->
        <div class="sec-label">Invoice Details</div>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#ef4444;"></div>Overdue Invoices
                        </div>
                    </div>
                    <div class="dt-wrap">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ov = mysqli_fetch_assoc($overdue_list)): ?>
                                    <tr onclick="window.location='../modules/invoices.php'">
                                        <td><span class="ref-p" style="background:rgba(239,68,68,.1);color:#dc2626;"><?= htmlspecialchars($ov['code']) ?></span></td>
                                        <td class="td-cut td-dim"><?= htmlspecialchars($ov['customer']) ?></td>
                                        <td style="font-weight:700;color:#ef4444;font-size:.77rem;">$<?= number_format($ov['total_amount'], 2) ?></td>
                                        <td><span class="sb sb-red"><?= $ov['days_overdue'] ?>d</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 anim-in">
                <div class="dc dc-body h-100">
                    <div class="ch">
                        <div class="ct">
                            <div class="ct-bar" style="background:#3b82f6;"></div>Due in Next 14 Days
                        </div>
                    </div>
                    <div class="dt-wrap">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($up = mysqli_fetch_assoc($upcoming)): ?>
                                    <tr onclick="window.location='../modules/invoices.php'">
                                        <td><span class="ref-p" style="background:rgba(59,130,246,.1);color:#2563eb;"><?= htmlspecialchars($up['code']) ?></span></td>
                                        <td class="td-cut td-dim"><?= htmlspecialchars($up['customer']) ?></td>
                                        <td style="font-weight:700;font-size:.77rem;">$<?= number_format($up['total_amount'], 2) ?></td>
                                        <td class="td-dim"><i class="bi bi-calendar3 me-1"></i><?= date('M j', strtotime($up['due_date'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <!-- ══════════════════════════════════════════════════════
     ████████  SALES  ████████
══════════════════════════════════════════════════════ -->
    <?php elseif ($role === 'Sales'): ?>

        <!-- Quick actions -->
        <div class="qa-bar">
            <a href="../modules/add-rfq.php" class="qa qa-indigo"><i class="bi bi-plus-circle"></i>New RFQ</a>
            <a href="../modules/orders_create.php" class="qa qa-green"><i class="bi bi-plus-circle"></i>New Order</a>
            <a href="../modules/customers.php" class="qa qa-ghost"><i class="bi bi-person-plus"></i>New Customer</a>
        </div>

        <!-- KPI strip -->
        <div class="sec-label">Sales Performance — <?= $label ?></div>
        <div class="row g-3 mb-4">

            <div class="col-6 col-md-3 anim-in">
                <a href="../modules/rfqs.php" class="dc hov kc d-block" style="--kc-color:#3b82f6;--kc-bg:rgba(59,130,246,.1);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-journal-check"></i></div>
                        <span class="kc-badge">Open</span>
                    </div>
                    <div class="kc-lbl">Open RFQs</div>
                    <div class="kc-num"><?= $my_rfqs['open_cnt'] ?></div>
                    <?php if ($my_rfqs['expiring'] > 0): ?>
                        <div class="kc-chip warn"><i class="bi bi-clock" style="font-size:.6rem;"></i><?= $my_rfqs['expiring'] ?> expiring</div>
                    <?php endif; ?>
                </a>
            </div>

            <div class="col-6 col-md-3 anim-in">
                <a href="../modules/rfqs.php" class="dc hov kc d-block" style="--kc-color:#10b981;--kc-bg:rgba(16,185,129,.1);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-trophy-fill"></i></div>
                        <span class="kc-badge"><?= $label ?></span>
                    </div>
                    <div class="kc-lbl">Won</div>
                    <div class="kc-num"><?= $my_rfqs['won'] ?></div>
                    <div class="kc-chip dn"><i class="bi bi-x-circle" style="font-size:.6rem;"></i><?= $my_rfqs['lost'] ?> lost</div>
                </a>
            </div>

            <div class="col-6 col-md-3 anim-in">
                <a href="../modules/order.php" class="dc hov kc d-block" style="--kc-color:#8b5cf6;--kc-bg:rgba(139,92,246,.1);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-bag-check-fill"></i></div>
                        <span class="kc-badge"><?= $label ?></span>
                    </div>
                    <div class="kc-lbl">Orders</div>
                    <div class="kc-num"><?= $my_orders['total'] ?></div>
                    <div class="kc-sub"><?= $my_orders['pending'] ?> pending</div>
                </a>
            </div>

            <?php $cc = $conv_rate >= 50 ? '#10b981' : ($conv_rate >= 25 ? '#f59e0b' : '#ef4444');
            $cb = $conv_rate >= 50 ? 'rgba(16,185,129,.1)' : ($conv_rate >= 25 ? 'rgba(245,158,11,.1)' : 'rgba(239,68,68,.1)'); ?>
            <div class="col-6 col-md-3 anim-in">
                <div class="dc hov kc" style="--kc-color:<?= $cc ?>;--kc-bg:<?= $cb ?>;">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-graph-up-arrow"></i></div>
                        <span class="kc-badge"><?= $label ?></span>
                    </div>
                    <div class="kc-lbl">Win Rate</div>
                    <div class="kc-num"><?= $conv_rate ?>%</div>
                    <div class="kc-sub">Quotes → Orders</div>
                </div>
            </div>
        </div>

        <!-- New KPI row: pipeline + RFQs created -->
        <div class="row g-3 mb-4">

            <div class="col-6 col-md-3 anim-in">
                <div class="dc hov kc" style="--kc-color:#6366f1;--kc-bg:rgba(99,102,241,.1);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-layers-fill"></i></div>
                        <span class="kc-badge">Live</span>
                    </div>
                    <div class="kc-lbl">Pipeline Value</div>
                    <div class="kc-num sm">$<?= number_format($pipeline_value, 0) ?></div>
                    <div class="kc-sub">Open RFQ potential</div>
                </div>
            </div>

            <div class="col-6 col-md-3 anim-in">
                <div class="dc hov kc" style="--kc-color:#f59e0b;--kc-bg:rgba(245,158,11,.1);">
                    <div class="kc-strip"></div>
                    <div class="kc-glow"></div>
                    <div class="kc-row1">
                        <div class="kc-icon"><i class="bi bi-file-earmark-plus-fill"></i></div>
                        <span class="kc-badge"><?= $label ?></span>
                    </div>
                    <div class="kc-lbl">RFQs Created</div>
                    <div class="kc-num"><?= $rfqs_created ?></div>
                    <div class="kc-sub"><?= $my_rfqs['won'] ?> converted</div>
                </div>
            </div>

            <!-- Conversion progress + order breakdown -->
            <div class="col-12 col-md-6 anim-in">
                <div class="dc dc-body">
                    <?php $cpg = $conv_rate >= 50 ? 'linear-gradient(90deg,#10b981,#059669)' : ($conv_rate >= 25 ? 'linear-gradient(90deg,#f59e0b,#d97706)' : 'linear-gradient(90deg,#ef4444,#dc2626)'); ?>
                    <div class="ch" style="margin-bottom:8px;">
                        <div class="ct">
                            <div class="ct-bar" style="background:<?= $cc ?>;"></div>Conversion &mdash; <?= $label ?>
                        </div>
                        <div class="pbar-pct" style="color:<?= $cc ?>;font-size:1.4rem;"><?= $conv_rate ?>%</div>
                    </div>
                    <div class="pbar-track" style="margin-bottom:10px;">
                        <div class="pbar-fill" style="width:<?= $conv_rate ?>%;background:<?= $cpg ?>;"></div>
                    </div>
                    <div style="font-size:.74rem;color:var(--color-text-secondary);">
                        <strong style="color:var(--color-text-primary);"><?= $my_rfqs['won'] ?> won</strong>
                        of <strong style="color:var(--color-text-primary);"><?= $total_this ?></strong> quotes · <?= $my_rfqs['lost'] ?> lost
                    </div>
                    <div class="bk-grid cols3" style="margin-top:12px;">
                        <?php foreach (
                            [
                                [$my_orders['pending'],    '#f59e0b', 'rgba(245,158,11,.08)', 'hourglass-split', 'Pending'],
                                [$my_orders['in_progress'], '#3b82f6', 'rgba(59,130,246,.08)', 'gear-fill',      'Active'],
                                [$my_orders['completed'],  '#10b981', 'rgba(16,185,129,.08)', 'check-circle-fill', 'Done'],
                            ] as [$n, $col, $bg, $ico, $lbl]
                        ): ?>
                            <div class="bk" style="--bk-bg:<?= $bg ?>;">
                                <div class="bk-ico" style="color:<?= $col ?>;"><i class="bi bi-<?= $ico ?>"></i></div>
                                <div class="bk-num" style="color:<?= $col ?>;"><?= $n ?></div>
                                <div class="bk-lbl"><?= $lbl ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div><!-- .dw -->

<?php include("../templates/footer.php"); ?>
