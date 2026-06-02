<?php
include("../includes/auth.php");
include("../config/database.php");
require_once __DIR__ . '/../includes/pdf_styles.php';
check_auth();
require_role(['Admin']);

require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id'])) die("RFQ ID is required.");
$rfq_id = intval($_GET['id']);

$sql = "SELECT r.*,
               c.name AS customer_name, c.address AS customer_address,
               sp.name AS salesperson_name, sp.title AS salesperson_title,
               sp.email AS salesperson_email, sp.phone AS salesperson_phone,
               sp.signature AS salesperson_signature,
               bt.title AS billto_title, bt.address AS billto_address,
               b.name AS buyer_name, b.email AS buyer_email,
               b.phone AS buyer_phone, b.address AS buyer_address,
               st.name AS shipto_name, st.company AS shipto_company,
               st.email AS shipto_email, st.phone AS shipto_phone,
               st.address AS shipto_address
        FROM rfqs r
        JOIN customers c   ON r.customer_id    = c.id
        JOIN salesperson sp ON r.salesPerson_id = sp.id
        JOIN billto bt     ON r.billTo_id      = bt.id
        JOIN buyer b       ON r.buyer_id       = b.id
        JOIN shipto st     ON r.shipTo_id      = st.id
        WHERE r.id = $rfq_id";

$result = $conn->query($sql);
if ($result->num_rows == 0) die("RFQ not found.");
$rfq = $result->fetch_assoc();

// Auto-format rfq_number if it's a plain integer
if (is_numeric($rfq['rfq_number'])) {
    $rfq['rfq_number'] = 'RFQ-' . date('Y', strtotime($rfq['quote_date'])) . '-' . $rfq['rfq_number'];
}

$result_lines = $conn->query("SELECT * FROM rfq_lines WHERE rfq_id = $rfq_id");
$subtotal = 0;
$lines    = [];
while ($row = $result_lines->fetch_assoc()) {
    $row['total_price'] = $row['qty'] * $row['unit_price'];
    $subtotal += $row['total_price'];
    $lines[] = $row;
}

/**
 * Convert image to base64
 */
function asset_b64(string $path, string $mime): string {
    return pdf_asset_b64($path, $mime, 420, 80);
}

/**
 * Convert a PNG signature to JPG and then to base64.
 * We keep the same file path convention but always output image/jpeg for Dompdf.
 */
function signature_to_jpg_b64(?string $relativePath): string {
    if (empty($relativePath)) {
        return '';
    }
    $full = __DIR__ . '/../' . ltrim($relativePath, '/');
    if (!file_exists($full)) {
        return '';
    }

    // Source image (PNG or other)
    $src = @imagecreatefromstring(file_get_contents($full));
    if (!$src) {
        return '';
    }

    $w = imagesx($src);
    $h = imagesy($src);

    // Create truecolor canvas with white background
    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

    // Capture JPG into memory
    ob_start();
    imagejpeg($dst, null, 90);
    $jpgData = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    return $jpgData
        ? 'data:image/jpeg;base64,' . base64_encode($jpgData)
        : '';
}

$logo_b64 = asset_b64(__DIR__ . '/../assets/Everstone.jpg', 'image/png'); // use JPG logo as you did for invoice
$signature_b64 = signature_to_jpg_b64($rfq['salesperson_signature']);

$shipping = isset($rfq['shipping']) ? (float)$rfq['shipping'] : 0;
$total    = $subtotal + $shipping;

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
                        <img src="<?= $logo_b64 ?>" alt="Logo">
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
                    <div class="doc-type">REQUEST FOR QUOTATION</div>
                    <div class="doc-identity">
                        <span class="doc-num-badge">RFQ # <?= htmlspecialchars($rfq['rfq_number']) ?></span>
                    </div>
                    <div class="doc-contact">
                        <span>sales@everstonetech.ca</span> | <span>236-953-7860</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Legal Agreement Notice -->
        <div class="agree-note">
            <strong>Quotation Validity:</strong> This Request for Quotation is valid for <?= htmlspecialchars($rfq['validity']) ?> from <?= date("M d, Y", strtotime($rfq['quote_date'])) ?>. Prices and terms are subject to change after expiry date.
        </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════
         ADDRESS INFORMATION
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-address">
        <div class="sec-lbl">Address Information</div>
        <table class="address-grid">
            <tr>
            <!-- Bill To -->
            <td class="address-card">
                <div class="ibox" style="min-height: 114px;">
                    <h6>Bill To / Invoice Address</h6>
                    <div class="address-content">
                        <strong><?= htmlspecialchars($rfq['billto_title']) ?></strong>
                        <span class="sub"><?= nl2br(htmlspecialchars($rfq['billto_address'])) ?></span>
                    </div>
                </div>
            </td>

            <!-- Ship To -->
            <td class="address-card">
                <div class="ibox">
                    <h6>Ship To</h6>
                    <div class="address-content">
                        <strong><?= htmlspecialchars($rfq['shipto_name']) ?></strong>
                        <?php if ($rfq['shipto_company']): ?>
                            <span class="sub"><?= htmlspecialchars($rfq['shipto_company']) ?></span>
                        <?php endif; ?>
                        <span class="sub"><?= htmlspecialchars($rfq['shipto_email']) ?></span>
                        <span class="sub"><?= htmlspecialchars($rfq['shipto_phone']) ?></span>
                        <span class="sub"><?= nl2br(htmlspecialchars($rfq['shipto_address'])) ?></span>
                    </div>
                </div>
            </td>

            <!-- Buyer -->
            <td class="address-card">
                <div class="ibox" style="min-height: 114px;">
                    <h6>Buyer</h6>
                    <div class="address-content">
                        <strong><?= htmlspecialchars($rfq['buyer_name']) ?></strong>
                        <span class="sub"><?= htmlspecialchars($rfq['buyer_email']) ?></span>
                        <span class="sub"><?= htmlspecialchars($rfq['buyer_phone']) ?></span>
                        <span class="sub"><?= nl2br(htmlspecialchars($rfq['buyer_address'])) ?></span>
                    </div>
                </div>
            </td>
            </tr>
        </table>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         QUOTATION DETAILS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-po-details">
        <div class="sec-lbl">Quotation Details</div>
        <div class="po-details-band">
            <table class="po-details-grid">
                <tr>
                <td class="po-detail-item">
                    <span class="pd-label">RFQ Number</span>
                    <span class="pd-value"><?= htmlspecialchars($rfq['rfq_number']) ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">RFQ Title</span>
                    <span class="pd-value"><?= htmlspecialchars($rfq['rfq_title']) ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Quote Date</span>
                    <span class="pd-value"><?= date("M d, Y", strtotime($rfq['quote_date'])) ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Lead Time</span>
                    <span class="pd-value"><?= htmlspecialchars($rfq['lead_time']) ?> Days</span>
                </td>
                </tr>
                <tr>
                <td class="po-detail-item">
                    <span class="pd-label">Sales Person</span>
                    <span class="pd-value">
                        <?= htmlspecialchars($rfq['salesperson_name']) ?>
                        <?php if ($rfq['salesperson_title']): ?>
                            — <?= htmlspecialchars($rfq['salesperson_title']) ?>
                        <?php endif; ?>
                    </span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Quote Validity</span>
                    <span class="pd-value"><?= htmlspecialchars($rfq['validity']) ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Customer</span>
                    <span class="pd-value"><?= htmlspecialchars($rfq['customer_name']) ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Status</span>
                    <span class="pd-value status-<?= strtolower(htmlspecialchars($rfq['status'] ?? 'draft')) ?>">
                        <?= htmlspecialchars($rfq['status'] ?? 'DRAFT') ?>
                    </span>
                </td>
                </tr>
            </table>
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
                        <th class="col-mfg">Manufacturer</th>
                        <th class="col-pn">Part Number</th>
                        <th class="col-unit">Unit</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-price">Unit Price (€)</th>
                        <th class="col-total">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                foreach ($lines as $line):
                ?>
                    <tr class="<?= ($count % 2 === 0) ? 'ev' : '' ?>">
                        <td class="tc row-num"><?= $count++ ?></td>
                        <td class="cell-desc"><?= htmlspecialchars($line['description']) ?></td>
                        <td class="cell-mfg"><?= htmlspecialchars($line['mfg'] ?? '—') ?></td>
                        <td class="cell-pn"><?= htmlspecialchars($line['part'] ?? '—') ?></td>
                        <td class="tc cell-unit"><?= htmlspecialchars($line['unit'] ?? '—') ?></td>
                        <td class="tc cell-qty"><span class="qty-bx"><?= $line['qty'] ?></span></td>
                        <td class="tr cell-price">€<?= number_format($line['unit_price'], 2) ?></td>
                        <td class="tr cell-total">€<?= number_format($line['total_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="7" class="total-label">Grand Total</td>
                        <td class="total-value">€<?= number_format($total, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         NOTES & TERMS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-notes">
        <div class="sec-lbl">Payment & Terms</div>
        <div class="notes-box">
            <div class="notes-header">Quotation Information</div>
            <div class="notes-content">
                <strong>Remit To:</strong> EVERSTONE TECHNOLOGY SYSTEMS INC.<br>
                <strong>Email:</strong> sales@everstonetech.ca<br>
                <strong>Phone:</strong> 236-953-7860<br><br>
                Prices in EUR (€). Valid for <?= htmlspecialchars($rfq['validity']) ?> from <?= date("M d, Y", strtotime($rfq['quote_date'])) ?>.<br>
                Shipping: €<?= number_format($shipping, 2) ?><br>
                <strong>Grand Total: €<?= number_format($total, 2) ?></strong>
            </div>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         AUTHORIZATION & SIGNATURES
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-authorization">
        <div class="sec-lbl">Authorization</div>
        <table class="signatures-wrapper">
            <tr>
            <!-- Everstone Authorized Signature -->
            <td class="signature-block">
                <div class="sig-card">
                    <h6>Authorized Signature (Everstone)</h6>
                    <div class="signature-area">
                        <?php if ($signature_b64): ?>
                            <img src="<?= $signature_b64 ?>" class="sig-img" alt="Authorized Signature">
                        <?php else: ?>
                            <div class="sig-placeholder"></div>
                        <?php endif; ?>
                    </div>
                    <div class="sig-line">
                        <span class="sig-name"><?= htmlspecialchars($rfq['salesperson_name']) ?></span>
                        <span class="sig-role"><?= htmlspecialchars($rfq['salesperson_title'] ?? 'Sales Representative') ?></span>
                    </div>
                </div>
            </td>

            <!-- Customer Acceptance -->
            <td class="signature-block">
                <div class="sig-card vendor-card" style="min-height: 146px;">
                    <h6>Customer Acceptance</h6>
                    <div class="signature-area vendor-area">
                        <div class="ack-text">
                            By signing below, Customer acknowledges receipt and acceptance of this Quotation and agrees to all terms and conditions.
                        </div>
                        <div class="vendor-line">
                            <span class="sig-ack-label">Customer Signature</span>
                            <span class="sig-ack-date">Date: ________________</span>
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

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('defaultPaperSize', 'A4');
$options->set('defaultPaperOrientation', 'portrait');
$options->set('dpi', 96);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($rfq['rfq_number'] . ".pdf", ["Attachment" => true]);
