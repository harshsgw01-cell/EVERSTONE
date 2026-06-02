<?php
require '../vendor/autoload.php';
include("../config/database.php");
include("../includes/auth.php");
require_once __DIR__ . '/../includes/pdf_styles.php';
check_auth();
require_role(['Admin']);

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$id = (int)$_GET['id'];

/* ---------- LOAD INVOICE ---------- */
$invoice_q = mysqli_query($conn, "
    SELECT i.*, 
           o.code AS order_code, 
           o.created_at AS order_date,
           c.name AS customer, 
           c.email, 
           c.phone, 
           c.address
    FROM invoices i
    JOIN orders o ON i.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    WHERE i.id = $id
");

$invoice = mysqli_fetch_assoc($invoice_q);
if (!$invoice) die("Invoice not found.");

/* ---------- LOAD ORDER LINES ---------- */
$order_lines = mysqli_query(
    $conn,
    "SELECT * FROM order_lines WHERE order_id=" . (int)$invoice['order_id']
);

/* ---------- TOTAL ---------- */
$tot_q = mysqli_query(
    $conn,
    "SELECT SUM(qty * unit_price) AS total_amount 
     FROM order_lines 
     WHERE order_id=" . (int)$invoice['order_id']
);
$tot = mysqli_fetch_assoc($tot_q);
$ol_total = $tot['total_amount'] ?? 0;

/* ---------- DOMPDF ---------- */
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<style>
body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #000;
}

.header-img {
    width: 100%;
}

.section {
    margin: 20px;
}

.flex {
    display: flex;
    justify-content: space-between;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.table th, .table td {
    border: 1px solid #ccc;
    padding: 6px;
}

.table th {
    background: #f0f0f0;
}

.badge {
    padding: 4px 8px;
    background: #0dcaf0;
    color: #000;
    border-radius: 4px;
    font-size: 11px;
}
</style>
</head>

<body>

<img src="<?= pdf_asset_b64(__DIR__ . '/../assets/pdf-head-img.jpg', 'image/jpeg', 1200, 80) ?>" class="header-img">

<div class="section flex">
    <div>
        <h2>EVERSTONE TECHNOLOGY SYSTEMS INC.</h2>
        <p>
            13455 94a Ave #104<br>
            Surrey, BC V3V 1M9 Canada<br>
            United States
        </p>
    </div>
    <div>
        <img src="<?= pdf_asset_b64(__DIR__ . '/../assets/Everstone.png', 'image/png', 420, 80) ?>" width="120">
    </div>
</div>

<div class="section flex">
    <div>
        <p>
            Pascaru, Ms. Anamaria<br>
            Brussels, BE (NCIA HQ - Ship-To) New NATO HQ<br>
            -Industrial Infrastructure Building - Reception<br>
            Service Rue Arthur Maes 1, 1130 BRUSSELS,<br>
            Belgium BRUSSELS 1130 Belgium
        </p>
    </div>
</div>

<div class="section">
    <div class="flex">
        <div>
            <h3>Invoice <?= htmlspecialchars($invoice['code']) ?></h3>
            <p>Order #: <?= htmlspecialchars($invoice['order_code']) ?></p>
            <p>Date: <?= date("m/d/Y", strtotime($invoice['created_at'])) ?></p>
            <span class="badge"><?= htmlspecialchars($invoice['status']) ?></span>
        </div>
        <div style="text-align:right">
            <h4>Your Company</h4>
            <p>
                123 Business St<br>
                City, Country<br>
                Email: info@company.com
            </p>
        </div>
    </div>
</div>

<div class="section">
    <h4>Bill To:</h4>
    <p>
        Customer: <?= htmlspecialchars($invoice['customer']) ?><br>
        Email: <?= htmlspecialchars($invoice['email']) ?> |
        Phone: <?= htmlspecialchars($invoice['phone']) ?><br>
        Address: <?= nl2br(htmlspecialchars($invoice['address'])) ?>
    </p>
</div>

<div class="section">
<table class="table">
<thead>
<tr>
    <th>#</th>
    <th>Product</th>
    <th>Qty</th>
    <th>Unit Price</th>
    <th>Sub Total</th>
</tr>
</thead>
<tbody>
<?php
$i = 1;
while ($line = mysqli_fetch_assoc($order_lines)) {
?>
<tr>
    <td><?= $i++ ?></td>
    <td><?= htmlspecialchars($line['product']) ?></td>
    <td><?= $line['qty'] ?></td>
    <td>$<?= number_format($line['unit_price'], 2) ?></td>
    <td>$<?= number_format($line['qty'] * $line['unit_price'], 2) ?></td>
</tr>
<?php } ?>
<tr>
    <th></th>
    <th></th>
    <th>Due Date</th>
    <th>Paid Date</th>
    <th>Total</th>
</tr>
<tr>
    <td></td>
    <td></td>
    <td><strong><?= htmlspecialchars($invoice['due_date']) ?></strong></td>
    <td><strong><?= htmlspecialchars($invoice['paid_date']) ?></strong></td>
    <td><strong>$<?= number_format($ol_total, 2) ?></strong></td>
</tr>
</tbody>
</table>
</div>

<div class="section flex">
    <div>
        <img src="<?= pdf_asset_b64(__DIR__ . '/../assets/Everstone.png', 'image/png', 420, 80) ?>" width="120">
        <p>
            EVERSTONE TECHNOLOGY SYSTEMS INC. <br>
            13455 94a Ave #104<br>
            Surrey, BC V3V 1M9 Canada<br>
            United States
        </p>
    </div>
</div>

<hr>

<div class="section flex">
    <h2>INTRODUCTION</h2>
    <img src="<?= pdf_asset_b64(__DIR__ . '/../assets/Everstone.png', 'image/png', 420, 80) ?>" width="120">
</div>

<div class="section">
    <h1 style="font-size:36px">
        Everstone provides technology systems, procurement, and operational support across a broad range of industries.
    </h1>
    <p style="font-size:14px">
        Everstone brings practical experience in delivering reliable technology, procurement, and logistical
        support. The company aims to provide tailored solutions to enhance operational efficiency and adaptability
        for its clients. The company is dedicated to quality and precision, meeting the needs of government and
        corporate clients through innovative and reliable solutions.
    </p>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Invoice-{$invoice['code']}.pdf", ["Attachment" => true]);
exit;
