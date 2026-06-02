<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

$ids = json_decode($_POST['ids'], true);
if (!is_array($ids) || !count($ids)) die("Invalid request.");

/* ---------- BUILD LINE DATA ---------- */
$lines = [];
foreach ($_POST['line_id'] as $i => $id) {
    $lines[] = [
        'description'     => $_POST['description'][$i]    ?? '',
        'qty'             => $_POST['qty'][$i]             ?? '',
        'delivered'       => $_POST['delivered'][$i]       ?? '',
        'unit'            => $_POST['unit'][$i]            ?? '',
        'part_number'     => $_POST['part_number'][$i]     ?? '',
        'manufacturer'    => $_POST['manufacturer'][$i]    ?? '',
        'tracking_number' => $_POST['tracking_number'][$i] ?? '',
        'tracking_url'    => $_POST['tracking_url'][$i]    ?? '',
    ];
}

$shipto_company     = $_POST['shipto_company']     ?? '';
$shipto_name        = $_POST['shipto_name']        ?? '';
$shipto_address     = $_POST['shipto_address']     ?? '';
$shipto_email       = $_POST['shipto_email']       ?? '';
$shipto_phone       = $_POST['shipto_phone']       ?? '';
$billto_title       = $_POST['billto_title']       ?? '';
$billto_address     = $_POST['billto_address']     ?? '';
$buyer_name         = $_POST['buyer_name']         ?? '';
$buyer_email        = $_POST['buyer_email']        ?? '';
$buyer_phone        = $_POST['buyer_phone']        ?? '';
$customer_po_number = $_POST['customer_po_number'] ?? '';
$order_number       = $_POST['order_number']       ?? '';
$rfq_number         = $_POST['rfq_number']         ?? '';
$ship_date          = $_POST['ship_date']          ?? date('Y-m-d');
$note_to_buyer      = $_POST['note_to_buyer']      ?? '';

/* ---------- ASSETS → BASE64 ---------- */
function asset_b64(string $path, string $mime): string {
    return pdf_asset_b64($path, $mime, 420, 80);
}

$logo_b64      = asset_b64(__DIR__ . '/../assets/Everstone.jpg',         'image/png');
$signature_b64 = asset_b64(__DIR__ . '/../assets/ceo-signature.png', 'image/png');

/* ---------- DOMPDF SETUP ---------- */
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../'));
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

$ship_date_fmt = date('M d, Y', strtotime($ship_date));

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

<!-- FIXED FOOTER -->
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
                    <div class="doc-type">PACKING SLIP</div>
                    <div class="doc-identity">
                        <?php if ($order_number): ?>
                            <span class="doc-num-badge"><?= htmlspecialchars($order_number) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="doc-contact">
                        <span>sales@everstonetech.ca</span> | <span>236-953-7860</span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Legal Notice -->
        <div class="agree-note">
            <strong>Shipping Document:</strong> This Packing Slip accompanies the shipment and verifies contents. Please inspect all items upon receipt and report any discrepancies within 48 hours.
        </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════
         PACKING SLIP DETAILS
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-po-details">
        <div class="sec-lbl">Packing Slip Details</div>
        <div class="po-details-band">
            <table class="po-details-grid">
                <tr>
                <?php if ($order_number): ?>
                <td class="po-detail-item">
                    <span class="pd-label">Our Order #</span>
                    <span class="pd-value"><?= htmlspecialchars($order_number) ?></span>
                </td>
                <?php endif; ?>
                <?php if ($customer_po_number): ?>
                <td class="po-detail-item">
                    <span class="pd-label">Customer PO #</span>
                    <span class="pd-value"><?= htmlspecialchars($customer_po_number) ?></span>
                </td>
                <?php endif; ?>
                <?php if ($rfq_number): ?>
                <td class="po-detail-item">
                    <span class="pd-label">RFQ #</span>
                    <span class="pd-value"><?= htmlspecialchars($rfq_number) ?></span>
                </td>
                <?php endif; ?>
                <td class="po-detail-item">
                    <span class="pd-label">Ship Date</span>
                    <span class="pd-value"><?= $ship_date_fmt ?></span>
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
                        <?php if (!empty($shipto_company)): ?>
                            <strong><?= htmlspecialchars($shipto_company) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($shipto_name)): ?>
                            <span class="sub"><?= htmlspecialchars($shipto_name) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($shipto_address)): ?>
                            <span class="sub"><?= nl2br(htmlspecialchars($shipto_address)) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($shipto_email)): ?>
                            <span class="sub"><?= htmlspecialchars($shipto_email) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($shipto_phone)): ?>
                            <span class="sub"><?= htmlspecialchars($shipto_phone) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- Buyer -->
            <td class="address-card">
                <div class="ibox" style="min-height: 114px;">
                    <h6>Buyer / Attention</h6>
                    <div class="address-content">
                        <?php if (!empty($buyer_name)): ?>
                            <strong><?= htmlspecialchars($buyer_name) ?></strong>
                            <?php if (!empty($buyer_email)): ?>
                                <span class="sub"><?= htmlspecialchars($buyer_email) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($buyer_phone)): ?>
                                <span class="sub"><?= htmlspecialchars($buyer_phone) ?></span>
                            <?php endif; ?>
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
         LINE ITEMS TABLE
         ════════════════════════════════════════════════════════════════ -->
    <section class="section-line-items">
        <div class="sec-lbl">Line Items</div>
        <div class="table-container">
            <table class="po-tbl">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-pn">Part #</th>
                        <th class="col-mfg">Manufacturer</th>
                        <th class="col-desc">Description</th>
                        <th class="col-qty">Ordered Qty</th>
                        <th class="col-qty">Delivered Qty</th>
                        <th class="col-price">Tracking #</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                $total_ordered = 0;
                $total_delivered = 0;
                foreach ($lines as $line):
                    $total_ordered += (int)$line['qty'];
                    $total_delivered += (int)$line['delivered'];
                ?>
                    <tr class="<?= ($count % 2 === 0) ? 'ev' : '' ?>">
                        <td class="tc row-num"><?= $count++ ?></td>
                        <td class="cell-pn"><?= htmlspecialchars($line['part_number']) ?></td>
                        <td class="cell-mfg"><?= htmlspecialchars($line['manufacturer']) ?></td>
                        <td class="cell-desc"><?= htmlspecialchars($line['description']) ?></td>
                        <td class="tc cell-qty"><span class="qty-bx"><?= htmlspecialchars($line['qty']) ?></span></td>
                        <td class="tc cell-qty"><span class="qty-bx"><?= htmlspecialchars($line['delivered']) ?></span></td>
                        <td class="cell-pn" style="font-size:8px;">
                            <?php if (!empty($line['tracking_url'])): ?>
                                <a href="<?= htmlspecialchars($line['tracking_url']) ?>" style="color:#1d4ed8;">
                                    <?= htmlspecialchars($line['tracking_number'] ?: $line['tracking_url']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($line['tracking_number']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="4" class="total-label">TOTALS</td>
                        <td class="total-value"><?= $total_ordered ?></td>
                        <td class="total-value"><?= $total_delivered ?></td>
                        <td class="total-value"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ════════════════════════════════════════════════════════════════
         NOTES TO BUYER
         ════════════════════════════════════════════════════════════════ -->
    <?php if (!empty($note_to_buyer)): ?>
    <section class="section-notes">
        <div class="sec-lbl">Note to Buyer</div>
        <div class="notes-box">
            <div class="notes-header">Additional Information</div>
            <div class="notes-content">
                <?= nl2br(htmlspecialchars($note_to_buyer)) ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
                        <span class="sig-name">Sajid Ahmadzai</span>
                        <span class="sig-role">CEO & Contract Manager</span>
                    </div>
                </div>
            </td>

            <!-- Recipient Acknowledgement -->
            <td class="signature-block">
                <div class="sig-card vendor-card" style="min-height: 146px;">
                    <h6>Recipient Acknowledgement</h6>
                    <div class="signature-area vendor-area">
                        <div class="ack-text">
                            Recipient signature confirms acceptance of this shipment and all contents as described herein.
                        </div>
                        <div class="vendor-line">
                            <span class="sig-ack-label">Signature</span>
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

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

if (ob_get_length()) ob_end_clean();
$dompdf->stream("Packaging-Slip.pdf", ["Attachment" => true]);
exit;
