<?php
ini_set('display_errors', 0);
error_reporting(0);

require '../vendor/autoload.php';
include("../config/database.php");
include("../includes/auth.php");
require_once __DIR__ . '/../includes/pdf_styles.php';
check_auth();
require_role(['Admin', 'Sales', 'Account']);

use Dompdf\Dompdf;
use Dompdf\Options;
use Picqer\Barcode\BarcodeGeneratorSVG;

if (!isset($_GET['id'])) {
    header("Location: purchase_order.php");
    exit;
}
$id = (int)$_GET['id'];

/* ── FETCH PO ── */
$po_q = mysqli_query($conn, "
    SELECT po.*, v.email AS vendor_email, v.phone AS vendor_phone, v.address AS vendor_address
    FROM purchase_orders po
    LEFT JOIN vendors v ON po.vendor_id = v.id
    WHERE po.id = $id
");
$po = mysqli_fetch_assoc($po_q);
if (!$po) die("Purchase Order not found.");

/* ── FETCH ITEMS ── */
$items_q = mysqli_query($conn, "
    SELECT * FROM purchase_order_items
    WHERE purchase_order_id = $id ORDER BY id ASC
");

$currencySymbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];
$symbol = $currencySymbols[$po['currency'] ?? 'USD'] ?? '$';

/* ── ASSETS ── */
function asset_b64(string $path, string $mime): string {
    return pdf_asset_b64($path, $mime, 420, 80);
}

/**
 * Optionally convert a PNG signature (or other format) to JPG
 * before embedding as base64 to keep consistency with other docs.
 */
function image_to_jpg_b64(string $path): string {
    if (!file_exists($path)) {
        return '';
    }
    $src = @imagecreatefromstring(file_get_contents($path));
    if (!$src) {
        return '';
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

    ob_start();
    imagejpeg($dst, null, 90);
    $jpgData = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    return $jpgData
        ? 'data:image/jpeg;base64,' . base64_encode($jpgData)
        : '';
}

// Use JPG versions for consistency with your optimized invoice/RFQ
$logo_b64 = asset_b64(__DIR__ . '/../assets/Everstone.jpg', 'image/png');
$emma_b64 = asset_b64(__DIR__ . '/../assets/emma-wiliam.jpg', 'image/png');
$sig_b64  = image_to_jpg_b64(__DIR__ . '/../assets/ceo-signature.png'); // converted to JPG in-memory

/* ── DOMPDF ── */
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../'));
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body {
    margin: 0;
    font-family: 'Calibri', 'Segoe UI', sans-serif;
    color: #202839;
    /* background: #eff2f7; */
}

.page-shell {
    width: 100%;
    max-width: 1080px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #d6dbe8;
    position: relative;
}

.doc-header {
    padding: 28px 32px 24px;
    background: linear-gradient(135deg, #0d4c92 0%, #112f55 100%);
    color: #0d4c92;
}

.hdr-tbl {
    width: 100%;
    border-collapse: collapse;
}

.hdr-tbl td {
    vertical-align: top;
    padding: 0;
}

.logo-cell {
    width: 28%;
}

.logo-cell img {
    max-height: 80px;
    display: block;
}

.co-info {
    width: 42%;
    padding-left: 22px;
}

.co-name {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 10px;
    letter-spacing: 0.06em;
    color: #0d4c92;
}

.co-sub {
    font-size: 10px;
    line-height: 1.85;
    color: #0d4c92
}

.doc-type-cell {
    width: 30%;
    text-align: right;
}

.doc-type {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    margin-bottom: 10px;
    color: #0d4c92;
}

.doc-identity {
    margin-bottom: 10px;
}

.doc-num-badge {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 30px;
    background: #f9a826;
    color: #102a43;
    font-weight: 700;
    font-size: 14px;
    min-width: 180px;
    box-shadow: inset 0 0 0 1px rgba(16, 42, 67, 0.12);
}

.doc-num-rev {
    display: block;
    margin-top: 8px;
    font-size: 11px;
    color: #0d4c92;
}

.doc-contact {
    font-size: 10px;
    color: #0d4c92;
    line-height: 1.8;
}

.agree-note {
    margin-top: 22px;
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.24);
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 14px;
    color: #0d4c92;
    font-size: 10px;
    line-height: 1.7;
}

.section-po-details,
.section-address,
.section-order-details,
.section-line-items,
.section-notes,
.section-authorization,
.section-about-terms {
    padding: 26px 32px;
}

.sec-lbl {
    font-size: 12px;
    font-weight: 800;
    color: #102a43;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    margin-bottom: 14px;
}

.po-details-band {
    background: #f8fafc;
    border: 1px solid #d7e1ed;
    border-radius: 14px;
    padding: 18px 20px;
}

.po-details-grid {
    width: 100%;
    border-collapse: collapse;
}

.po-detail-item {
    width: 33%;
    padding: 10px 8px;
    vertical-align: top;
}

.po-detail-item.po-title-item {
    padding-top: 18px;
}

.pd-label {
    display: block;
    font-size: 9px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #5a7184;
    margin-bottom: 6px;
}

.pd-value {
    display: block;
    font-size: 13px;
    color: #102a43;
    font-weight: 700;
    line-height: 1.5;
}

.address-grid {
    width: 100%;
    border-collapse: collapse;
}

.address-grid td {
    vertical-align: top;
    padding: 0 10px 0 0;
}

.address-card {
    width: 33%;
    padding-bottom: 0;
}

.ibox {
    background: #f8fbff;
    border: 1px solid #dbe7f1;
    border-radius: 14px;
    padding: 18px 18px 20px;
    min-height: 126px;
}

.ibox h6 {
    margin: 0 0 12px;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #0c3c6d;
}

.address-content strong {
    display: block;
    font-size: 13px;
    color: #102a43;
    margin-bottom: 8px;
}

.sub,
.address-content span {
    display: block;
    font-size: 10px;
    color: #435468;
    line-height: 1.75;
}

.contact-info {
    margin-top: 4px;
}

.reference-info {
    margin-top: 10px;
    font-size: 10px;
    color: #0c3c6d;
}

.not-specified {
    font-size: 10px;
    color: #8d9fb2;
}

.order-details-wrapper {
    width: 100%;
    border-collapse: collapse;
}

.order-details-card,
.barcode-card {
    padding: 0 10px 0 0;
    vertical-align: top;
}

.order-details-card {
    width: 66%;
}

.barcode-card {
    width: 34%;
}

.detail-table {
    width: 100%;
    border-collapse: collapse;
}

.detail-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #d7e1ed;
    font-size: 10px;
    color: #435468;
}

.detail-table .detail-label {
    width: 36%;
    font-weight: 700;
    color: #0f355d;
}

.detail-table .detail-value {
    width: 64%;
}

.barcode-box {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.barcode-wrapper {
    margin-top: 12px;
    text-align: center;
}

.barcode-wrapper img {
    width: 100%;
    max-height: 88px;
    object-fit: contain;
}

.barcode-lbl {
    margin-top: 10px;
    font-size: 10px;
    color: #102a43;
}

.table-container {
    overflow-x: auto;
}

.po-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
}

.po-tbl th,
.po-tbl td {
    padding: 12px 10px;
    border: 1px solid #d7e1ed;
}

.po-tbl th {
    background: #0a4a83;
    color: #0d4c92;
    text-align: left;
    font-size: 10px;
    letter-spacing: 0.12em;
}

.po-tbl td {
    background: #ffffff;
    vertical-align: top;
    color: #324156;
}

.po-tbl tr.ev td {
    background: #f7faff;
}

.col-num,
.col-unit,
.col-qty,
.col-price,
.col-total {
    text-align: right;
}

.cell-desc {
    min-width: 240px;
}

.qty-bx {
    display: inline-block;
    padding: 6px 10px;
    background: #f3f6fb;
    border-radius: 8px;
    color: #0f3561;
    font-weight: 700;
}

.total-row td {
    border-top: 2px solid #0a4a83;
    font-weight: 700;
    background: #f8fbff;
}

.total-label {
    text-align: right;
    font-size: 10px;
    letter-spacing: 0.08em;
    color: #102a43;
}

.total-value {
    text-align: right;
    font-size: 11px;
    color: #102a43;
}

.notes-box {
    background: #f7faff;
    border: 1px solid #dbe7f1;
    border-radius: 14px;
    padding: 18px;
}

.notes-header {
    font-size: 11px;
    font-weight: 700;
    color: #102a43;
    margin-bottom: 10px;
}

.notes-content {
    font-size: 10px;
    line-height: 1.7;
    color: #415165;
}

.signatures-wrapper {
    width: 100%;
    border-collapse: collapse;
}

.signature-block {
    width: 50%;
    padding: 0 10px 0 0;
    vertical-align: top;
}

.sig-card {
    background: #f8fbff;
    border: 1px solid #dbe7f1;
    border-radius: 14px;
    padding: 18px;
    min-height: 170px;
}

.sig-card h6 {
    margin: 0 0 12px;
    font-size: 11px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #0c3c6d;
}

.signature-area {
    min-height: 88px;
    border: 1px dashed #c4d1e0;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
}

.sig-img {
    max-width: 100%;
    max-height: 84px;
}

.sig-placeholder {
    width: 100%;
    height: 70px;
    background: #f4f7fb;
    border-radius: 10px;
}

.sig-line,
.vendor-line {
    display: flex;
    justify-content: space-between;
    margin-top: 14px;
    font-size: 10px;
    color: #415165;
}

.sig-name {
    font-weight: 700;
    color: #0f3561;
}

.sig-role,
.sig-ack-label,
.sig-ack-date {
    font-size: 10px;
    color: #546d86;
}

.vendor-card {
    min-height: 186px;
}

.ack-text {
    font-size: 10px;
    color: #435468;
    line-height: 1.6;
}

.section-about-terms {
    padding: 24px 32px 32px;
}

.about-header h2 {
    margin: 0;
    font-size: 18px;
    color: #102a43;
}

.about-content {
    margin-top: 16px;
}

.intro-lead,
.intro-body {
    margin: 0 0 12px;
    font-size: 10px;
    color: #435468;
    line-height: 1.75;
}

.terms-box {
    background: #f8fbff;
    border: 1px solid #dbe7f1;
    border-radius: 14px;
    padding: 18px;
    margin-top: 18px;
}

.t-title {
    font-size: 11px;
    font-weight: 700;
    color: #102a43;
    margin-bottom: 10px;
}

.terms-list {
    padding-left: 18px;
    margin: 0;
}

.terms-list li {
    margin-bottom: 10px;
    font-size: 10px;
    color: #415165;
    line-height: 1.6;
}

.terms-list li strong {
    color: #0f3561;
}

.watermark {
    position: absolute;
    bottom: 24px;
    right: 26px;
    opacity: 0.035;
    width: 180px;
    z-index: 1;
}

.doc-footer {
    display: none;
}
</style>
</head>
<body class="purchase-pdf-root">

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
                    <div class="doc-type">PURCHASE ORDER</div>
                    <div class="doc-identity">
                        <span class="doc-num-badge"><?= htmlspecialchars($po['po_number']) ?></span>
                        <span class="doc-num-rev">Rev <?= htmlspecialchars($po['revision']) ?></span>
                    </div>
                    <div class="doc-contact">
                        <span>sales@everstonetech.ca</span> | <span>236-953-7860</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Legal Agreement Notice -->
        <div class="agree-note">
            <strong>Legally Binding Agreement:</strong> This Purchase Order is issued by EVERSTONE TECHNOLOGY SYSTEMS INC. and constitutes a legally binding agreement upon acceptance by the Vendor. All terms and conditions apply.
        </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════
         PURCHASE ORDER DETAILS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-po-details">
        <div class="sec-lbl">Purchase Order Details</div>
        <div class="po-details-band">
            <table class="po-details-grid">
                <tr>
                <td class="po-detail-item">
                    <span class="pd-label">Issue Date</span>
                    <span class="pd-value"><?= date("M d, Y", strtotime($po['created_at'])) ?></span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Expected Delivery</span>
                    <span class="pd-value">
                        <?= $po['expected_delivery'] ? date("M d, Y", strtotime($po['expected_delivery'])) : '—' ?>
                    </span>
                </td>
                <td class="po-detail-item">
                    <span class="pd-label">Currency</span>
                    <span class="pd-value"><?= htmlspecialchars($po['currency'] ?? 'USD') ?></span>
                </td>
                </tr>
                <tr>
                <td class="po-detail-item po-title-item" colspan="3">
                    <span class="pd-label">PO Title</span>
                    <span class="pd-value"><?= htmlspecialchars($po['title']) ?></span>
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
            <!-- Bill To -->
            <td class="address-card">
                <div class="ibox">
                    <h6>Bill To / Invoice Address</h6>
                    <div class="address-content">
                        <strong>EVERSTONE TECHNOLOGY SYSTEMS INC.</strong>
                        <span class="sub">13455 94a Ave #104</span>
                        <span class="sub">Surrey, BC V3V 1M9 Canada</span>
                        <span class="sub contact-info">sales@everstonetech.ca</span>
                        <span class="sub contact-info">236-953-7860</span>
                    </div>
                </div>
            </td>

            <!-- Vendor -->
            <td class="address-card">
                <div class="ibox" style="min-height: 113px;">
                    <h6>Vendor / Supplier</h6>
                    <div class="address-content">
                        <strong><?= htmlspecialchars($po['vendor_name']) ?></strong>
                        <span class="sub"><?= htmlspecialchars($po['vendor_email'] ?? '—') ?></span>
                        <span class="sub"><?= htmlspecialchars($po['vendor_phone'] ?? '—') ?></span>
                        <?php if (!empty($po['vendor_address'])): ?>
                            <span class="sub"><?= htmlspecialchars(substr($po['vendor_address'], 0, 50)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- Ship To -->
            <td class="address-card">
                <div class="ibox" style="min-height: 113px;">
                    <h6>Ship To</h6>
                    <div class="address-content">
                        <?php if (!empty($po['end_user_name'])): ?>
                            <strong><?= htmlspecialchars($po['end_user_name']) ?></strong>
                            <?php if (!empty($po['end_user_email'])): ?>
                                <span class="sub"><?= htmlspecialchars($po['end_user_email']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($po['end_user_contact'])): ?>
                                <span class="sub"><?= htmlspecialchars($po['end_user_contact']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($po['end_user_address'])): ?>
                                <span class="sub"><?= htmlspecialchars(substr($po['end_user_address'], 0, 50)) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($po['end_user_reference'])): ?>
                                <span class="reference-info"><strong>Ref:</strong> <?= htmlspecialchars($po['end_user_reference']) ?></span>
                            <?php endif; ?>
                        <?php elseif (!empty($po['ship_address'])): ?>
                            <span class="sub"><?= htmlspecialchars(substr($po['ship_address'], 0, 70)) ?></span>
                        <?php else: ?>
                            <span class="not-specified">Not specified</span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
            </tr>
        </table>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         ORDER DETAILS & BARCODE
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-order-details">
        <div class="sec-lbl">Order Details</div>
        <table class="order-details-wrapper">
            <tr>
            <td class="order-details-card">
                <div class="ibox">
                    <h6>Additional Information</h6>
                    <table class="detail-table">
                        <tr>
                            <td class="detail-label">Title</td>
                            <td class="detail-value"><?= htmlspecialchars($po['title']) ?></td>
                        </tr>
                        <tr>
                            <td class="detail-label">Payment Terms</td>
                            <td class="detail-value">
                            <?php
                            $payment_terms = $po['payment_terms'] ?? '—';
                            if (str_contains($payment_terms, 'Net')) {
                                $payment_terms = preg_replace('/Net\s*(\d+)/i', 'Net $1 Days', $payment_terms);
                            }
                            echo htmlspecialchars($payment_terms);
                            ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="detail-label">Expected Delivery</td>
                            <td class="detail-value">
                            <?= $po['expected_delivery'] ? date("M d, Y", strtotime($po['expected_delivery'])) : '—' ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <td class="barcode-card">
                <div class="ibox barcode-box" style="min-height: 122px;">
                    <h6>PO Tracking</h6>
                    <?php
                    $generator   = new BarcodeGeneratorSVG();
                    $barcode_svg = $generator->getBarcode(
                        $po['po_number'], $generator::TYPE_CODE_128, 2, 50
                    );
                    $barcode_b64 = 'data:image/svg+xml;base64,' . base64_encode($barcode_svg);
                    ?>
                    <div class="barcode-wrapper">
                        <img src="<?= $barcode_b64 ?>" alt="PO Barcode: <?= htmlspecialchars($po['po_number']) ?>">
                        <div class="barcode-lbl"><?= htmlspecialchars($po['po_number']) ?></div>
                    </div>
                </div>
            </td>
            </tr>
        </table>
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
                        <th class="col-price">Unit Price</th>
                        <th class="col-total">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                $grandTotal = 0;
                while ($item = mysqli_fetch_assoc($items_q)):
                    $lineTotal   = $item['qty'] * $item['unit_price'];
                    $grandTotal += $lineTotal;
                ?>
                    <tr class="<?= ($count % 2 === 0) ? 'ev' : '' ?>">
                        <td class="tc row-num"><?= $count++ ?></td>
                        <td class="cell-desc"><?= htmlspecialchars($item['description']) ?></td>
                        <td class="cell-mfg"><?= htmlspecialchars($item['manufacturer'] ?? '—') ?></td>
                        <td class="cell-pn"><?= htmlspecialchars($item['part_number'] ?? '—') ?></td>
                        <td class="tc cell-unit"><?= htmlspecialchars($item['unit'] ?? '—') ?></td>
                        <td class="tc cell-qty"><span class="qty-bx"><?= $item['qty'] ?></span></td>
                        <td class="tr cell-price"><?= $symbol ?><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="tr cell-total"><?= $symbol ?><?= number_format($lineTotal, 2) ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr class="total-row">
                        <td colspan="7" class="total-label">Grand Total</td>
                        <td class="total-value"><?= $symbol ?><?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         NOTES SECTION
         ════════════════════════════════════════════════════════════════ -->
    <?php if (!empty($po['notes'])): ?>
    <section class="section-notes">
        <div class="sec-lbl">Notes & Comments</div>
        <div class="notes-box">
            <div class="notes-header">Additional Notes</div>
            <div class="notes-content">
                <?= nl2br(htmlspecialchars($po['notes'])) ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════════════════
         AUTHORIZATION & SIGNATURES
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-authorization">
        <div class="sec-lbl">Authorization & Acceptance</div>
        <table class="signatures-wrapper">
            <tr>
            <!-- Everstone Authorized Signature -->
            <td class="signature-block">
                <div class="sig-card">
                    <h6>Authorized Signature (Everstone)</h6>
                    <div class="signature-area">
                        <?php if ($sig_b64): ?>
                            <img src="<?= $sig_b64 ?>" class="sig-img" alt="Authorized Signature">
                        <?php else: ?>
                            <div class="sig-placeholder"></div>
                        <?php endif; ?>
                    </div>
                    <div class="sig-line">
                        <span class="sig-name">Emma William</span>
                        <span class="sig-role">Procurement Manager</span>
                    </div>
                </div>
            </td>

            <!-- Vendor Acknowledgement -->
            <td class="signature-block">
                <div class="sig-card vendor-card" style="min-height: 146px;">
                    <h6>Vendor Acceptance</h6>
                    <div class="signature-area vendor-area">
                        <div class="ack-text">
                            By signing below, Vendor acknowledges receipt and acceptance of this Purchase Order and agrees to all terms and conditions.
                        </div>
                        <div class="vendor-line">
                            <span class="sig-ack-label">Vendor Signature</span>
                            <span class="sig-ack-date">Date: ________________</span>
                        </div>
                    </div>
                </div>
            </td>
            </tr>
        </table>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         COMPANY INFO & TERMS AND CONDITIONS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-about-terms" style="page-break-inside: avoid;">
        <!-- About Everstone Header -->
        <div class="about-header">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="vertical-align: middle;">
                        <h2 style="margin:0; font-size:20px; font-weight:700; color:#1f2937;">About EVERSTONE TECHNOLOGY SYSTEMS INC.</h2>
                    </td>
                    <td style="text-align: right; width: 60px;">
                        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Everstone Logo" style="width:50px; height:auto;"><?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Company Overview -->
        <div class="about-content">
            <p class="intro-lead">
                EVERSTONE TECHNOLOGY SYSTEMS INC. (Everstone) is a Canada-based technology systems and procurement company specializing in mission-critical equipment delivery.
            </p>
            <p class="intro-body">
                Everstone specializes in the sourcing and delivery of mission-critical equipment for government, defense, and international organizations. We provide end-to-end supply chain solutions including procurement, quality assurance, export compliance, and global logistics coordination. Our commitment is to deliver authentic, high-quality products through authorized channels while maintaining strict adherence to regulatory, contractual, and operational requirements.
            </p>
        </div>

        <!-- Terms & Conditions -->
        <div class="terms-section">
            <div class="sec-lbl">Supplier Terms & Conditions</div>
            <div class="terms-box">
                <div class="t-title">The following terms govern this Purchase Order:</div>
                <ol class="terms-list">
                    <li><span class="term-item"><strong>Acceptance:</strong> This PO becomes binding upon written, electronic, or performance-based acceptance by Supplier.</span></li>
                    <li><span class="term-item"><strong>Pricing:</strong> All prices are firm-fixed and include packaging and handling unless otherwise agreed in writing.</span></li>
                    <li><span class="term-item"><strong>Payment:</strong> Payment shall be made per stated terms after receipt of proper invoice and acceptance of goods.</span></li>
                    <li><span class="term-item"><strong>Delivery:</strong> Time is of the essence. Supplier shall notify Everstone immediately of any delay.</span></li>
                    <li><span class="term-item"><strong>Inspection:</strong> All goods are subject to inspection and acceptance by Everstone or its customer.</span></li>
                    <li><span class="term-item"><strong>Warranty:</strong> Supplier warrants goods are new, genuine, free from defects, and covered by manufacturer warranty.</span></li>
                    <li><span class="term-item"><strong>Anti-Counterfeit:</strong> Supplier certifies products are authentic and sourced through authorized channels.</span></li>
                    <li><span class="term-item"><strong>Compliance:</strong> Supplier shall comply with all applicable export, trade, anti-corruption, and regulatory laws.</span></li>
                    <li><span class="term-item"><strong>Indemnification:</strong> Supplier agrees to indemnify Everstone against claims arising from defective or non-compliant goods.</span></li>
                    <li><span class="term-item"><strong>Confidentiality:</strong> Supplier shall protect all confidential information received from Everstone.</span></li>
                    <li><span class="term-item"><strong>Termination:</strong> Everstone reserves the right to terminate for convenience or default.</span></li>
                    <li><span class="term-item"><strong>Assignment:</strong> Supplier may not assign this PO without written consent from Everstone.</span></li>
                </ol>
            </div>
        </div>
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
$dompdf->stream("Purchase-Order-" . $po['po_number'] . ".pdf", ["Attachment" => true]);
exit;
