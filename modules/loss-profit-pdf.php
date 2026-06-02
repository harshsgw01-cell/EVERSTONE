<?php
session_start();

require '../vendor/autoload.php';
include("../includes/auth.php");
include("../config/database.php");
require_once __DIR__ . '/../includes/pdf_styles.php';

check_auth();
require_role(['Admin']);

if (empty($_POST['orders'])) {
    echo "<script>alert('Please select at least one order to download the PDF.'); window.history.back();</script>";
    exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/* ---------------- INPUT HANDLING ---------------- */

$fromDate      = $_POST['from']         ?? $_GET['from']         ?? '';
$toDate        = $_POST['to']           ?? $_GET['to']           ?? '';
$orderNumber   = $_POST['order_number'] ?? $_GET['order_number'] ?? '';
$selectedOrders = $_POST['orders']      ?? [];

/* ---------------- FILTER LOGIC ---------------- */

$where = "WHERE ol.status != 'Cancelled'";

if (!empty($selectedOrders)) {
    $safeOrders = array_map('intval', $selectedOrders);
    $orderList  = implode(',', $safeOrders);
    $where     .= " AND o.id IN ($orderList)";
} elseif ($orderNumber) {
    $where .= " AND o.id = " . (int)$orderNumber;
} elseif ($fromDate && $toDate) {
    $where .= " AND DATE(o.created_at) BETWEEN '" . mysqli_real_escape_string($conn, $fromDate) . "'
                AND '" . mysqli_real_escape_string($conn, $toDate) . "'";
}

/* ---------------- DATA FETCH ---------------- */

$query = "
    SELECT 
        ol.id,
        ol.order_id,
        o.order_number,
        o.code,
        o.created_at,
        ol.description,
        ol.qty,
        ol.unit_price,
        ol.cost_price,
        (ol.qty * ol.unit_price) AS line_revenue,
        (ol.qty * ol.cost_price) AS line_cost
    FROM order_lines ol
    JOIN orders o ON ol.order_id = o.id
    $where
    ORDER BY o.created_at DESC
";
$result = mysqli_query($conn, $query);

$totalRevenue = 0;
$totalCOGS    = 0;
$orderLines   = [];

while ($row = mysqli_fetch_assoc($result)) {
    $totalRevenue += $row['line_revenue'];
    $totalCOGS    += $row['line_cost'];
    $orderLines[]  = $row;
}

$grossProfit = $totalRevenue - $totalCOGS;

/* ---------------- OPERATING EXPENSES ---------------- */

$fixedExpenses = [
    ["Salaries & Wages", 20000],
    ["Rent & Utilities", 5000],
    ["Marketing", 3000],
];

$otherExpenses = $_SESSION['other_expenses'] ?? [];

$operatingExpenses = $fixedExpenses;
foreach ($otherExpenses as $exp) {
    $operatingExpenses[] = [$exp['name'], $exp['amount']];
}

$totalExpenses = array_sum(array_column($operatingExpenses, 1));
$netProfit     = $grossProfit - $totalExpenses;

/* ---------------- ASSETS (JPG) ---------------- */

function asset_b64(string $path, string $mime): string {
    return pdf_asset_b64($path, $mime, 420, 80);
}

// use the same JPG logo you already created for speed
$logo_b64 = asset_b64(__DIR__ . '/../assets/Everstone.jpg', 'image/png');

/* ---------------- DOMPDF SETUP ---------------- */

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        <?= tcs_unified_css_block('purchase-pdf') ?>
        .doc-header {
            border-top: 5px solid #0f172a;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .08);
        }
        .doc-type {
            color: #0f172a;
            letter-spacing: .5px;
        }
        .doc-num-badge {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .agree-note {
            background: #f8fafc;
            border-color: #dbeafe;
            color: #334155;
        }
        .report-summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px;
            margin: 2px -7px 8px;
        }
        .report-summary-grid td {
            width: 25%;
            padding: 10px 11px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #ffffff;
        }
        .report-summary-grid .metric-label {
            display: block;
            color: #64748b;
            font-size: 6.8px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .report-summary-grid .metric-value {
            display: block;
            margin-top: 5px;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
        }
        .report-summary-grid .metric-note {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 6.8px;
        }
        .report-summary-grid .metric-positive { color: #059669; }
        .report-summary-grid .metric-negative { color: #dc2626; }
        .po-tbl th {
            background: #0f172a;
            border-color: #334155;
        }
        .po-tbl tr.ev td {
            background: #f8fafc;
        }
        .total-row td {
            background: #0f172a !important;
        }
    </style>
</head>
<body class="purchase-pdf-page">

<?php if ($logo_b64): ?>
<img src="<?= $logo_b64 ?>" class="watermark" alt="">
<?php endif; ?>

<div class="doc-footer">
    <span>EVERSTONE TECHNOLOGY SYSTEMS INC. </span> · 13455 94a Ave #104 Surrey, BC V3V 1M9 Canada
    · <span>sales@everstonetech.ca</span> · 236-953-7860 · <span>everstonetech.ca</span>
</div>

<div class="page-shell">

    <!-- ════════════════════════════════════════════════════════════════
         DOCUMENT HEADER
         ════════════════════════════════════════════════════════════════ -->
    <header class="doc-header">
        <table class="hdr-tbl">
            <tr>
                <td class="logo-cell">
                    <?php if ($logo_b64): ?>
                        <img src="<?= $logo_b64 ?>" alt="EVERSTONE TECHNOLOGY SYSTEMS INC.">
                    <?php endif; ?>
                </td>
                <td class="co-info">
                    <div class="co-name">EVERSTONE TECHNOLOGY SYSTEMS INC.</div>
                    <div class="co-sub">
                        13455 94a Ave #104<br>
                        Surrey, BC V3V 1M9 Canada<br>
                        Email: sales@everstonetech.ca | Phone: 236-953-7860 | Website: everstonetech.ca
                    </div>
                </td>
                <td class="doc-type-cell">
                    <div class="doc-type">PROFIT &amp; LOSS</div>
                    <div class="doc-identity">
                        <span class="doc-num-badge">Financial Report</span>
                    </div>
                    <div class="doc-contact">
                        <span>sales@everstonetech.ca</span> | <span>236-953-7860</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Report Info Notice -->
        <div class="agree-note">
            <strong>Financial Summary:</strong> This report provides profit and loss analysis for the selected period. All figures are in EUR (€).
        </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════
         REPORT DETAILS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-po-details">
        <div class="sec-lbl">Report Details</div>
        <div class="po-details-band">
            <table class="po-details-grid">
                <tr>
                <td class="po-detail-item">
                    <span class="pd-label">Report Type</span>
                    <span class="pd-value">Profit &amp; Loss Statement</span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Period</span>
                    <span class="pd-value">
                        <?php
                        echo !empty($selectedOrders)
                            ? "Multiple Orders (" . count($selectedOrders) . " selected)"
                            : ($orderNumber
                                ? "Order #" . htmlspecialchars($orderNumber)
                                : ($fromDate && $toDate ? "$fromDate to $toDate" : date("F Y")));
                        ?>
                    </span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Generated On</span>
                    <span class="pd-value"><?= date("M d, Y") ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Currency</span>
                    <span class="pd-value">EUR (€)</span>
                </td>
                </tr>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         FINANCIAL SUMMARY
         ════════════════════════════════════════════════════════════════ -->
    <table class="report-summary-grid">
        <tr>
            <td>
                <span class="metric-label">Revenue</span>
                <span class="metric-value">€<?= number_format($totalRevenue, 2) ?></span>
                <span class="metric-note">Total sales</span>
            </td>
            <td>
                <span class="metric-label">COGS</span>
                <span class="metric-value metric-negative">€<?= number_format($totalCOGS, 2) ?></span>
                <span class="metric-note">Direct costs</span>
            </td>
            <td>
                <span class="metric-label">Gross Profit</span>
                <span class="metric-value metric-positive">€<?= number_format($grossProfit, 2) ?></span>
                <span class="metric-note"><?= $totalRevenue > 0 ? number_format(($grossProfit / $totalRevenue) * 100, 1) : '0.0' ?>% margin</span>
            </td>
            <td>
                <span class="metric-label">Net <?= $netProfit >= 0 ? 'Profit' : 'Loss' ?></span>
                <span class="metric-value <?= $netProfit >= 0 ? 'metric-positive' : 'metric-negative' ?>">€<?= number_format(abs($netProfit), 2) ?></span>
                <span class="metric-note">After expenses</span>
            </td>
        </tr>
    </table>
    <section class="section-line-items">
        <div class="sec-lbl">Financial Summary</div>
        <div class="table-container">
            <table class="po-tbl">
                <thead>
                    <tr>
                        <th class="col-desc">Category</th>
                        <th class="col-desc">Item</th>
                        <th class="col-total">Amount (€)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Revenue -->
                    <tr>
                        <td rowspan="1" style="font-weight:bold; background:#f8f9fa;">Revenue</td>
                        <td>Total Sales</td>
                        <td class="tr cell-total" style="color:#059669;">€<?= number_format($totalRevenue, 2) ?></td>
                    </tr>

                    <!-- COGS -->
                    <tr>
                        <td rowspan="2" style="font-weight:bold; background:#f8f9fa;">Cost of Goods Sold</td>
                        <td>COGS</td>
                        <td class="tr cell-total" style="color:#DC2626;">-€<?= number_format($totalCOGS, 2) ?></td>
                    </tr>
                    <tr class="ev" style="background:#fef3c7;">
                        <td><strong>Gross Profit</strong></td>
                        <td class="tr cell-total"><strong>€<?= number_format($grossProfit, 2) ?></strong></td>
                    </tr>

                    <!-- Operating Expenses -->
                    <?php
                    $expCount = count($operatingExpenses);
                    $first = true;
                    foreach ($operatingExpenses as $expense):
                    ?>
                    <tr <?= $first ? '' : 'class="ev"' ?>>
                        <?php if ($first): ?>
                        <td rowspan="<?= $expCount + 1 ?>" style="font-weight:bold; background:#f8f9fa;">Operating Expenses</td>
                        <?php $first = false; endif; ?>
                        <td>
                            <?= htmlspecialchars($expense[0]) ?>
                            <?php if (!in_array($expense, $fixedExpenses, true)): ?>
                                <span style="font-size:7px; background:#f59e0b; color:#fff; padding:1px 4px; border-radius:2px;">Other</span>
                            <?php endif; ?>
                        </td>
                        <td class="tr cell-total" style="color:#DC2626;">-€<?= number_format($expense[1], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="ev">
                        <td><strong>Total Operating Expenses</strong></td>
                        <td class="tr cell-total" style="color:#DC2626;">-€<?= number_format($totalExpenses, 2) ?></td>
                    </tr>

                    <!-- Net Profit -->
                    <tr class="total-row" style="background:#d1fae5;">
                        <td colspan="2" class="total-label" style="font-size:11px;">NET PROFIT</td>
                        <td class="total-value" style="font-size:11px; color:<?= $netProfit >= 0 ? '#059669' : '#DC2626' ?>">
                            €<?= number_format($netProfit, 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ORDER BREAKDOWN
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-line-items">
        <div class="sec-lbl">Order Breakdown</div>
        <div class="table-container">
            <table class="po-tbl">
                <thead>
                    <tr>
                        <th class="col-num">Order #</th>
                        <th class="col-pn">Date</th>
                        <th class="col-desc">Description</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-price">Unit Price</th>
                        <th class="col-price">Revenue</th>
                        <th class="col-price">Cost</th>
                        <th class="col-total">Profit</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                foreach ($orderLines as $line):
                    $lineProfit = $line['line_revenue'] - $line['line_cost'];
                ?>
                    <tr class="<?= ($count % 2 === 0) ? 'ev' : '' ?>">
                        <td class="tc row-num"><?= htmlspecialchars($line['code'] ?: $line['order_number']) ?></td>
                        <td class="cell-pn"><?= date("m/d/Y", strtotime($line['created_at'])) ?></td>
                        <td class="cell-desc"><?= htmlspecialchars($line['description']) ?></td>
                        <td class="tc cell-qty"><span class="qty-bx"><?= $line['qty'] ?></span></td>
                        <td class="tr cell-price">€<?= number_format($line['unit_price'], 2) ?></td>
                        <td class="tr cell-price">€<?= number_format($line['line_revenue'], 2) ?></td>
                        <td class="tr cell-price">€<?= number_format($line['line_cost'], 2) ?></td>
                        <td class="tr cell-total" style="font-weight:bold;color:<?= $lineProfit >= 0 ? '#059669' : '#DC2626' ?>">
                            €<?= number_format($lineProfit, 2) ?>
                        </td>
                    </tr>
                <?php $count++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Profit-Loss-Report.pdf", ["Attachment" => true]);
exit;
