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
    font-family: 'Helvetica', Arial, sans-serif;
    font-size: 12px;
    color: #2f2f2f;
    background: #eef3f8;
    margin: 0;
}

.invoice-shell {
    max-width: 1050px;
    margin: 16px auto;
    background: #ffffff;
    border-radius: 20px;
    padding: 28px 34px;
    box-shadow: 0 24px 60px rgba(18, 42, 66, 0.08);
}

.top-panel {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 26px;
    border-bottom: 2px solid #d9e2ec;
    padding-bottom: 18px;
}

.branding {
    display: flex;
    align-items: center;
    gap: 14px;
}

.branding .logo-mark {
    width: 58px;
    height: 58px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: #1b4f72;
    color: #0d4c92;
    font-size: 26px;
    font-weight: 900;
}

.branding .brand-text h1 {
    margin: 0;
    font-size: 26px;
    color: #142f46;
}

.branding .brand-text p {
    margin: 6px 0 0;
    color: #566d7a;
    font-size: 11px;
    line-height: 1.55;
}

.document-meta {
    background: #f2f7fb;
    border: 1px solid #d8e4ee;
    border-radius: 14px;
    padding: 18px 20px;
    min-width: 240px;
    text-align: right;
}

.document-meta .tag {
    display: inline-block;
    font-size: 10px;
    letter-spacing: 0.12em;
    color: #406d98;
    text-transform: uppercase;
}

.document-meta h2 {
    margin: 10px 0 8px;
    font-size: 26px;
    color: #1c3650;
}

.document-meta p {
    margin: 6px 0 0;
    color: #526879;
    font-size: 11px;
}

.info-panel {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr;
    gap: 16px;
    margin: 26px 0 22px;
}

.info-card {
    border: 1px solid #d8e4ee;
    border-radius: 14px;
    background: #f6f9fb;
    padding: 18px;
}

.info-card h3 {
    margin: 0 0 10px;
    font-size: 12px;
    color: #2f4b5e;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.info-card p {
    margin: 5px 0;
    color: #4f6777;
    font-size: 11px;
    line-height: 1.55;
}

.info-card strong {
    display: block;
    margin-bottom: 6px;
    color: #1f3545;
}

.line-items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
}

.line-items th,
.line-items td {
    border: 1px solid #d9e3ee;
    padding: 14px 12px;
}

.line-items th {
    background: #1b4f72;
    color: #ffffff;
    font-size: 11px;
    letter-spacing: 0.08em;
    text-align: left;
}

.line-items td {
    background: #fafcfe;
    color: #4c5e6f;
    font-size: 11px;
}

.line-items tr:nth-child(even) td {
    background: #f3f7fb;
}

.line-items .text-right {
    text-align: right;
}

.summary-row {
    display: grid;
    grid-template-columns: 1fr 260px;
    gap: 18px;
    margin-bottom: 24px;
}

.summary-box {
    border: 1px solid #d8e4ee;
    background: #f6f9fb;
    border-radius: 14px;
    padding: 18px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 12px;
    color: #4c5e6f;
}

.summary-line.total {
    font-size: 14px;
    color: #1d3648;
    font-weight: 800;
    border-top: 1px solid #d9e3ee;
    padding-top: 12px;
    margin-top: 14px;
}

.footer-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.footer-box {
    background: #f6f9fb;
    border: 1px solid #d8e4ee;
    border-radius: 14px;
    padding: 18px;
}

.footer-box h4 {
    margin: 0 0 10px;
    color: #2f4b5e;
    font-size: 12px;
}

.footer-box p {
    margin: 0;
    color: #4f6777;
    font-size: 11px;
    line-height: 1.55;
}
</style>
</head>

<body>

<div class="invoice-shell">
    <div class="top-panel">
        <div class="branding">
            <div class="logo-mark">E</div>
            <div class="brand-text">
                <h1>Everstone Technology</h1>
                <p>Technology procurement and enterprise service management.</p>
            </div>
        </div>
        <div class="document-meta">
            <span class="tag">Invoice</span>
            <h2><?= htmlspecialchars($invoice['code']) ?></h2>
            <p>Order #: <?= htmlspecialchars($invoice['order_code']) ?></p>
            <p>Date: <?= date("M d, Y", strtotime($invoice['created_at'])) ?></p>
        </div>
    </div>

    <div class="info-panel">
        <div class="info-card">
            <h3>From</h3>
            <strong>Everstone Technology Systems Inc.</strong>
            <p>13455 94a Ave #104</p>
            <p>Surrey, BC V3V 1M9 Canada</p>
            <p>sales@everstonetech.ca</p>
        </div>
        <div class="info-card">
            <h3>Bill To</h3>
            <strong><?= htmlspecialchars($invoice['customer']) ?></strong>
            <p><?= nl2br(htmlspecialchars($invoice['address'])) ?></p>
            <p>Email: <?= htmlspecialchars($invoice['email']) ?></p>
            <p>Phone: <?= htmlspecialchars($invoice['phone']) ?></p>
        </div>
        <div class="info-card">
            <h3>Details</h3>
            <p><strong>Status:</strong> <?= htmlspecialchars($invoice['status']) ?></p>
            <p><strong>Due Date:</strong> <?= htmlspecialchars($invoice['due_date']) ?></p>
            <p><strong>Paid Date:</strong> <?= htmlspecialchars($invoice['paid_date']) ?></p>
        </div>
    </div>

    <table class="line-items">
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount</th>
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
                <td class="text-right"><?= $line['qty'] ?></td>
                <td class="text-right">$<?= number_format($line['unit_price'], 2) ?></td>
                <td class="text-right">$<?= number_format($line['qty'] * $line['unit_price'], 2) ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <div class="summary-row">
        <div></div>
        <div class="summary-box">
            <div class="summary-line">
                <span>Subtotal</span>
                <span>$<?= number_format($ol_total, 2) ?></span>
            </div>
            <div class="summary-line total">
                <span>Total Due</span>
                <span>$<?= number_format($ol_total, 2) ?></span>
            </div>
        </div>
    </div>

    <div class="footer-section">
        <div class="footer-box">
            <h4>Notes</h4>
            <p>Thank you for your business. Please remit payment according to the terms outlined in this invoice.</p>
        </div>
        <div class="footer-box">
            <h4>Payment Terms</h4>
            <p>Payment is due within 30 days of the invoice date unless otherwise agreed. Late payments may incur additional fees.</p>
        </div>
    </div>
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
