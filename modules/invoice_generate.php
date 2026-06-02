<?php
require '../vendor/autoload.php';
include("../includes/auth.php");
include("../config/database.php");
require_once __DIR__ . '/../includes/pdf_styles.php';
check_auth();
require_role(['Admin']);

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ids'])) {
    die("Invalid request.");
}

/* ── STATUS UPDATE ── */
$inv_code      = mysqli_real_escape_string($conn, trim($_POST['invoice_number'] ?? ''));
$inv_po_number = mysqli_real_escape_string($conn, trim($_POST['customer_po_number'] ?? ''));
$inv_due_date  = mysqli_real_escape_string($conn, trim($_POST['due_date'] ?? ''));

$from_invoice_list = !empty($_POST['invoice_id']);

if ($from_invoice_list) {
    $invoice_id   = (int)$_POST['invoice_id'];
    $inv_record   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM invoices WHERE id=$invoice_id LIMIT 1"));
    $order_id     = (int)($inv_record['order_id'] ?? 0);
    $line_ids_raw = $inv_record['line_ids'] ?? '';

    if (!empty($line_ids_raw)) {
        $safe_ids = implode(',', array_map('intval', explode(',', $line_ids_raw)));
    } else {
        $all = mysqli_query($conn, "SELECT id FROM order_lines WHERE order_id=$order_id");
        $tmp = [];
        while ($r = mysqli_fetch_assoc($all)) $tmp[] = $r['id'];
        $safe_ids = implode(',', $tmp);
    }

    $res = mysqli_query($conn, "
        SELECT ol.*, b.name AS buyer_name, b.email AS buyer_email,
               bt.title AS billto_title, bt.address AS billto_address,
               st.name AS shipto_name, st.company AS shipto_company,
               st.address AS shipto_address, st.email AS shipto_email,
               st.phone AS shipto_phone
        FROM order_lines ol
        LEFT JOIN orders o  ON ol.order_id = o.id
        LEFT JOIN buyer b   ON o.buyer_id  = b.id
        LEFT JOIN billto bt ON o.billTo_id = bt.id
        LEFT JOIN shipto st ON o.shipTo_id = st.id
        WHERE ol.id IN ($safe_ids)
    ");

    $lines = []; $total = 0;
    $buyer_name = $buyer_email = $billto_title = $billto_address = '';
    $shipto_name = $shipto_company = $shipto_address = $shipto_email = $shipto_phone = '';

    while ($r = mysqli_fetch_assoc($res)) {
        $qty    = (float)$r['qty'];
        $price  = (float)$r['unit_price'];
        $amount = $qty * $price;
        $total += $amount;

        $lines[] = [
            'description' => $r['description'] ?? $r['product'] ?? '',
            'qty'         => $qty,
            'price'       => $price,
            'amount'      => $amount
        ];

        $buyer_name     = $r['buyer_name'];
        $buyer_email    = $r['buyer_email'];
        $billto_title   = $r['billto_title'];
        $billto_address = $r['billto_address'];
        $shipto_name    = $r['shipto_name'];
        $shipto_company = $r['shipto_company'];
        $shipto_address = $r['shipto_address'];
        $shipto_email   = $r['shipto_email'];
        $shipto_phone   = $r['shipto_phone'];
    }

    $ord = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT customer_id, billTo_id, shipTo_id, customer_po_number FROM orders WHERE id=$order_id LIMIT 1"
    ));
    $customer_id   = (int)($ord['customer_id'] ?? 0);
    $billto_id     = (int)($ord['billTo_id'] ?? 0);
    $shipto_id     = (int)($ord['shipTo_id'] ?? 0);
    $inv_po_number = $inv_po_number ?: ($ord['customer_po_number'] ?? '');

} else {
    $ids    = json_decode($_POST['ids'], true);
    $ids    = array_map('intval', (array)$ids);
    $idList = implode(',', $ids);

    mysqli_query($conn, "UPDATE order_lines SET status = 'Completed' WHERE id IN ($idList)");

    $res      = mysqli_query($conn, "SELECT order_id FROM order_lines WHERE id = {$ids[0]}");
    $row      = mysqli_fetch_assoc($res);
    $order_id = (int)($row['order_id'] ?? 0);

    $chk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS pending FROM order_lines WHERE order_id=$order_id AND status!='Completed'"
    ));
    if ($chk['pending'] == 0) {
        mysqli_query($conn, "UPDATE orders SET status='Completed' WHERE id=$order_id");
    }

    $inv_total = 0;
    foreach ((array)$_POST['qty'] as $i => $qty) {
        $inv_total += (float)$qty * (float)$_POST['price'][$i];
    }

    $ord = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT customer_id, billTo_id, shipTo_id FROM orders WHERE id=$order_id LIMIT 1"
    ));
    $customer_id = (int)($ord['customer_id'] ?? 0);
    $billto_id   = (int)($ord['billTo_id'] ?? 0);
    $shipto_id   = (int)($ord['shipTo_id'] ?? 0);

    $inv_line_ids = implode(',', array_map('intval', (array)$_POST['line_id']));
    $exists = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM invoices WHERE code='$inv_code' LIMIT 1"
    ));
    if (!$exists) {
        mysqli_query($conn, "
            INSERT INTO invoices (code, order_id, customer_id, billto_id, shipto_id, po_number, status, total_amount, due_date, line_ids, created_at)
            VALUES ('$inv_code', $order_id, $customer_id, $billto_id, $shipto_id, '$inv_po_number', 'Draft', $inv_total, '$inv_due_date', '$inv_line_ids', NOW())
        ");
    }

    $lines = []; $total = 0;
    foreach ((array)$_POST['line_id'] as $i => $id) {
        $qty    = (float)$_POST['qty'][$i];
        $price  = (float)$_POST['price'][$i];
        $amount = $qty * $price;
        $total += $amount;

        $lines[] = [
            'description' => $_POST['description'][$i],
            'qty'         => $qty,
            'price'       => $price,
            'amount'      => $amount
        ];
    }

    $billto_title   = $_POST['billto_title']   ?? '';
    $billto_address = $_POST['billto_address'] ?? '';
    $buyer_name     = $_POST['buyer_name']     ?? '';
    $buyer_email    = $_POST['buyer_email']    ?? '';
    $shipto_name    = $_POST['shipto_name']    ?? '';
    $shipto_company = $_POST['shipto_company'] ?? '';
    $shipto_address = $_POST['shipto_address'] ?? '';
    $shipto_email   = $_POST['shipto_email']   ?? '';
    $shipto_phone   = $_POST['shipto_phone']   ?? '';
}

/* ── BANK DETAILS FROM POST ── */
$selected_bank = $_POST['selected_bank'] ?? 'chase';

if ($selected_bank === 'wise') {
    $bank_name           = $_POST['bank_name_wise']           ?? 'WISE';
    $bank_account_name   = $_POST['bank_account_name_wise']   ?? 'EVERSTONE TECHNOLOGY SYSTEMS INC.';
    $bank_account_number = $_POST['bank_account_number_wise'] ?? '';
    $bank_routing        = $_POST['bank_routing_wise']        ?? '';
    $bank_swift          = $_POST['bank_swift_wise']          ?? 'TRWIBEB1';
    $bank_iban           = $_POST['bank_iban_wise']           ?? 'BE93905230080367';
    $bank_address        = $_POST['bank_address_wise']        ?? 'Rue du Trône 100, 3rd floor, Brussels, 1050, Belgium';
} else {
    // Default to Chase
    $bank_name           = $_POST['bank_name']           ?? 'JPMorgan Chase Bank';
    $bank_account_name   = $_POST['bank_account_name']   ?? 'EVERSTONE TECHNOLOGY SYSTEMS INC.';
    $bank_account_number = $_POST['bank_account_number'] ?? '2900355517';
    $bank_routing        = $_POST['bank_routing']        ?? '021000021';
    $bank_swift          = $_POST['bank_swift']          ?? 'CHASUS33';
    $bank_iban           = $_POST['bank_iban']           ?? '';
    $bank_address        = $_POST['bank_address']        ?? '13455 94a Ave #104 Surrey, BC V3V 1M9 Canada';
}

$terms_text = $_POST['terms_conditions'] ?? 'Payment due within 30 days of invoice date.';

/* ── ASSETS ── */
function asset_b64(string $path, string $mime): string {
    return pdf_asset_b64($path, $mime, 420, 80);
}
$logo_b64 = asset_b64(__DIR__ . '/../assets/Everstone.jpg', 'image/png');

$invoice_date = date('M d, Y');
$due_date_fmt = !empty($inv_due_date)
    ? date('M d, Y', strtotime($inv_due_date))
    : date('M d, Y', strtotime('+30 days'));

$shipping = 0;

$dompdf_options = new Options();
$dompdf_options->set('isHtml5ParserEnabled', true);
$dompdf_options->set('isRemoteEnabled', true);
$dompdf_options->set('defaultFont', 'DejaVu Sans');
$dompdf_options->set('defaultPaperSize', 'A4');
$dompdf_options->set('defaultPaperOrientation', 'portrait');
$dompdf_options->set('dpi', 96);

$dompdf = new Dompdf($dompdf_options);
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style><?= tcs_unified_css_block('purchase-pdf') ?></style>
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
                    <div class="doc-type">INVOICE</div>
                    <div class="doc-identity">
                        <span class="doc-num-badge"><?= htmlspecialchars($inv_code ?: ($_POST['invoice_number'] ?? 'N/A')) ?></span>
                    </div>
                    <div class="doc-contact">
                        <span>sales@everstonetech.ca</span> | <span>236-953-7860</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Legal Notice -->
        <div class="agree-note">
            <strong>Payment Required:</strong> This invoice is due by <?= $due_date_fmt ?>. Please reference invoice number <?= htmlspecialchars($inv_code) ?> in all payment correspondence.
        </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════
         INVOICE DETAILS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-po-details">
        <div class="sec-lbl">Invoice Details</div>
        <div class="po-details-band">
            <table class="po-details-grid">
                <tr>
                <td class="po-detail-item">
                    <span class="pd-label">Invoice Number</span>
                    <span class="pd-value"><?= htmlspecialchars($inv_code ?: '—') ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Invoice Date</span>
                    <span class="pd-value"><?= $invoice_date ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Payment Due</span>
                    <span class="pd-value"><?= $due_date_fmt ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">PO Reference</span>
                    <span class="pd-value"><?= htmlspecialchars($inv_po_number ?: '—') ?></span>
                </td>
                </tr>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ADDRESS INFORMATION
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-address">
        <div class="sec-lbl">Address Information</div>
        <table class="address-grid">
            <tr>
            <!-- Invoice To -->
            <td class="address-card">
                <div class="ibox" style="min-height: 114px;">
                    <h6>Invoice To</h6>
                    <div class="address-content">
                        <?php if (!empty($buyer_name)): ?>
                            <strong><?= htmlspecialchars($buyer_name) ?></strong>
                            <?php if (!empty($buyer_email)): ?>
                                <span class="sub"><?= htmlspecialchars($buyer_email) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="not-specified">Not specified</span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- Bill To -->
            <td class="address-card">
                <div class="ibox" style="min-height: 114px;">
                    <h6>Bill To / Invoice Address</h6>
                    <div class="address-content">
                        <?php if (!empty($billto_title)): ?>
                            <strong><?= htmlspecialchars($billto_title) ?></strong>
                            <span class="sub"><?= nl2br(htmlspecialchars($billto_address)) ?></span>
                        <?php else: ?>
                            <span class="not-specified">Not specified</span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- Ship To -->
            <td class="address-card">
                <div class="ibox" style="min-height: 114px;">
                    <h6>Ship To</h6>
                    <div class="address-content">
                        <?php if (!empty($shipto_name)): ?>
                            <strong><?= htmlspecialchars($shipto_name) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($shipto_company)): ?>
                            <span class="sub"><?= htmlspecialchars($shipto_company) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($shipto_email)): ?>
                            <span class="sub"><?= htmlspecialchars($shipto_email) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($shipto_phone)): ?>
                            <span class="sub"><?= htmlspecialchars($shipto_phone) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($shipto_address)): ?>
                            <span class="sub"><?= nl2br(htmlspecialchars($shipto_address)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            </tr>
        </table>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         PAYMENT DETAILS — SELECTED BANK
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-notes bank-details-section">
        <div class="sec-lbl">Payment Details — <?= $selected_bank === 'wise' ? 'International Transfer (WISE)' : 'Wire Transfer (USA)' ?></div>
        <div class="sig-card">
            <h6><?= htmlspecialchars($bank_name) ?></h6>
            <div class="signature-area">
                <table class="detail-table" style="font-size:8.5px;">
                    <tr><td>Account Name:</td><td><?= htmlspecialchars($bank_account_name) ?></td></tr>
                    <?php if ($bank_account_number): ?><tr><td>Account Number:</td><td><?= htmlspecialchars($bank_account_number) ?></td></tr><?php endif; ?>
                    <?php if ($bank_routing): ?><tr><td>Routing Number:</td><td><?= htmlspecialchars($bank_routing) ?></td></tr><?php endif; ?>
                    <?php if ($bank_swift): ?><tr><td>SWIFT Code:</td><td><?= htmlspecialchars($bank_swift) ?></td></tr><?php endif; ?>
                    <?php if ($bank_iban): ?><tr><td>IBAN:</td><td><?= htmlspecialchars($bank_iban) ?></td></tr><?php endif; ?>
                    <?php if ($bank_address): ?><tr><td>Bank Address:</td><td><?= htmlspecialchars($bank_address) ?></td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         LINE ITEMS TABLE
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-line-items">
        <div class="sec-lbl">Line Items</div>
        <div class="table-container">
            <table class="po-tbl">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-desc">Description</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-unit">Unit</th>
                        <th class="col-price">Unit Price (€)</th>
                        <th class="col-total">Total Amount (€)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                foreach ($lines as $line):
                ?>
                    <tr class="<?= ($count % 2 === 0) ? 'ev' : '' ?>">
                        <td class="tc row-num"><?= $count++ ?></td>
                        <td class="cell-desc"><?= nl2br(htmlspecialchars($line['description'])) ?></td>
                        <td class="tc cell-qty"><span class="qty-bx"><?= number_format($line['qty'], 0) ?></span></td>
                        <td class="tc cell-unit">EACH</td>
                        <td class="tr cell-price">€<?= number_format($line['price'], 2) ?></td>
                        <td class="tr cell-total">€<?= number_format($line['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="5" class="total-label">Grand Total</td>
                        <td class="total-value">€<?= number_format($total, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         TERMS & TOTALS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-notes">
        <div class="notes-box">
            <div class="notes-header">Terms & Conditions</div>
            <div class="notes-content">
                <?= nl2br(htmlspecialchars($terms_text)) ?>
                <div style="margin-top:8px; font-size:7.8px; color:#9CA3AF;">
                    Reference invoice <strong style="color:#374151;"><?= htmlspecialchars($inv_code) ?></strong> in all payment correspondence.
                </div>
            </div>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         AUTHORIZATION & TOTAL DUE
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-authorization">
        <div class="sec-lbl">Authorization</div>
        <table class="signatures-wrapper">
            <tr>
            <!-- Certification -->
            <td class="signature-block">
                <div class="sig-card" style="min-height: 146px;">
                    <h6>Certification</h6>
                    <div class="signature-area" style="padding: 8px 12px;">
                        <div style="font-size:8px; line-height:1.6;">
                            I hereby certify that this invoice is true and correct, and that the articles or services
                            invoiced above have been furnished or rendered to the U.S. Government or other specified entity.<br><br>
                            <strong>EVERSTONE TECHNOLOGY SYSTEMS INC.</strong><br>
                            Email: sales@everstonetech.ca | Phone: 236-953-7860
                        </div>
                    </div>
                </div>
            </td>

            <!-- Total Due -->
            <td class="signature-block">
                <div class="sig-card vendor-card" style="min-height: 146px;">
                    <h6>Total Amount Due</h6>
                    <div class="signature-area vendor-area">
                        <div class="total-amt">€<?= number_format($total, 2) ?></div>
                        <div class="total-sub">Due by: <strong><?= $due_date_fmt ?></strong></div>
                        <div class="vendor-line" style="margin-top:10px; font-size:8px;">
                            Invoice: <?= htmlspecialchars($inv_code) ?><br>
                            PO: <?= htmlspecialchars($inv_po_number ?: '—') ?>
                        </div>
                    </div>
                </div>
            </td>
            </tr>
        </table>
    </section>

</div>
</body>
</html>
<?php
$html = ob_get_clean();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
if (ob_get_length()) ob_end_clean();
$dompdf->stream("Invoice.pdf", ["Attachment" => true]);
exit;
