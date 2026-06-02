<?php
ini_set('display_errors', 0);
error_reporting(0);

require '../vendor/autoload.php';
include("../config/database.php");
include("../includes/auth.php");
require_once __DIR__ . '/../includes/pdf_styles_modern.php';
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

/* ── DOMPDF ── */
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../'));
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);

$css = get_modern_pdf_css();
$header = get_modern_pdf_header([
    'doc_type' => 'PURCHASE ORDER',
    'doc_number' => $po['po_number'],
    'company_name' => 'EVERSTONE TECHNOLOGY SYSTEMS INC.'
]);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Order <?= htmlspecialchars($po['po_number']) ?></title>
    <style><?= $css ?></style>
</head>
<body>
    <div class="pdf-page">
        
        <?= $header ?>

        <!-- Main Content Grid -->
        <div class="grid-container">
            
            <!-- PO Details -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Purchase Order Details</h6>
                    <p><strong>PO Number:</strong> <?= htmlspecialchars($po['po_number']) ?></p>
                    <p><strong>Revision:</strong> <?= htmlspecialchars($po['revision']) ?></p>
                    <p><strong>Issue Date:</strong> <?= date("M d, Y", strtotime($po['created_at'])) ?></p>
                    <p><strong>Expected Delivery:</strong> <?= $po['expected_delivery'] ? date("M d, Y", strtotime($po['expected_delivery'])) : '—' ?></p>
                    <p><strong>Currency:</strong> <?= htmlspecialchars($po['currency'] ?? 'USD') ?></p>
                </div>
            </div>

            <!-- Bill To -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Bill To</h6>
                    <p><strong>EVERSTONE TECHNOLOGY SYSTEMS INC.</strong></p>
                    <p>13455 94a Ave #104</p>
                    <p>Surrey, BC V3V 1M9 Canada</p>
                    <p><strong>Email:</strong> sales@everstonetech.ca</p>
                    <p><strong>Phone:</strong> 236-953-7860</p>
                </div>
            </div>

            <!-- Vendor -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Vendor / Supplier</h6>
                    <p><strong><?= htmlspecialchars($po['vendor_name']) ?></strong></p>
                    <p><?= htmlspecialchars($po['vendor_email'] ?? '—') ?></p>
                    <p><?= htmlspecialchars($po['vendor_phone'] ?? '—') ?></p>
                    <?php if (!empty($po['vendor_address'])): ?>
                        <p><?= htmlspecialchars(substr($po['vendor_address'], 0, 50)) ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Ship To Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Ship To Information</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <div class="col-6">
                    <div class="info-card">
                        <h6>End User / Ship To</h6>
                        <?php if (!empty($po['end_user_name'])): ?>
                            <p><strong><?= htmlspecialchars($po['end_user_name']) ?></strong></p>
                            <?php if (!empty($po['end_user_email'])): ?>
                                <p><?= htmlspecialchars($po['end_user_email']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($po['end_user_contact'])): ?>
                                <p><?= htmlspecialchars($po['end_user_contact']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($po['end_user_address'])): ?>
                                <p><?= htmlspecialchars(substr($po['end_user_address'], 0, 70)) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($po['end_user_reference'])): ?>
                                <p><strong>Ref:</strong> <?= htmlspecialchars($po['end_user_reference']) ?></p>
                            <?php endif; ?>
                        <?php elseif (!empty($po['ship_address'])): ?>
                            <p><?= htmlspecialchars(substr($po['ship_address'], 0, 70)) ?></p>
                        <?php else: ?>
                            <p style="color: var(--color-muted);">Not specified</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-6">
                    <div class="info-card">
                        <h6>Order Information</h6>
                        <p><strong>Title:</strong> <?= htmlspecialchars($po['title']) ?></p>
                        <?php
                        $payment_terms = $po['payment_terms'] ?? '—';
                        if (str_contains($payment_terms, 'Net')) {
                            $payment_terms = preg_replace('/Net\s*(\d+)/i', 'Net $1 Days', $payment_terms);
                        }
                        ?>
                        <p><strong>Payment Terms:</strong> <?= htmlspecialchars($payment_terms) ?></p>
                        <p><strong>Expected Delivery:</strong> <?= $po['expected_delivery'] ? date("M d, Y", strtotime($po['expected_delivery'])) : '—' ?></p>
                        
                        <!-- Barcode -->
                        <?php
                        $generator = new BarcodeGeneratorSVG();
                        $barcode_svg = $generator->getBarcode($po['po_number'], $generator::TYPE_CODE_128, 2, 50);
                        $barcode_b64 = 'data:image/svg+xml;base64,' . base64_encode($barcode_svg);
                        ?>
                        <div style="margin-top: 12px; text-align: center;">
                            <img src="<?= $barcode_b64 ?>" alt="PO Barcode" style="width: 100%; max-height: 60px;">
                            <p style="font-size: 9px; margin-top: 4px; color: var(--color-muted);"><?= htmlspecialchars($po['po_number']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Line Items Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Line Items</h2>
            </div>
            
            <?php
            $headers = [
                ['text' => '#', 'align' => 'center'],
                ['text' => 'Description', 'align' => 'left'],
                ['text' => 'Manufacturer', 'align' => 'left'],
                ['text' => 'Part Number', 'align' => 'left'],
                ['text' => 'Unit', 'align' => 'center'],
                ['text' => 'Qty', 'align' => 'center'],
                ['text' => 'Unit Price', 'align' => 'right'],
                ['text' => 'Line Total', 'align' => 'right']
            ];
            
            $rows = [];
            $count = 1;
            $grandTotal = 0;
            while ($item = mysqli_fetch_assoc($items_q)) {
                $lineTotal = $item['qty'] * $item['unit_price'];
                $grandTotal += $lineTotal;
                
                $rows[] = [
                    ['text' => $count++, 'align' => 'center'],
                    ['text' => $item['description'], 'align' => 'left'],
                    ['text' => $item['manufacturer'] ?? '—', 'align' => 'left'],
                    ['text' => $item['part_number'] ?? '—', 'align' => 'left'],
                    ['text' => $item['unit'] ?? '—', 'align' => 'center'],
                    ['text' => $item['qty'], 'align' => 'center'],
                    ['text' => $symbol . number_format($item['unit_price'], 2), 'align' => 'right'],
                    ['text' => $symbol . number_format($lineTotal, 2), 'align' => 'right']
                ];
            }
            
            $footers = [
                [
                    ['text' => '', 'align' => 'left', 'colspan' => 7],
                    ['text' => 'Grand Total', 'align' => 'right'],
                    ['text' => $symbol . number_format($grandTotal, 2), 'align' => 'right', 'color' => '#e8b4a8']
                ]
            ];
            
            echo get_modern_table($headers, $rows, $footers);
            ?>
        </section>

        <!-- Notes Section -->
        <?php if (!empty($po['notes'])): ?>
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Notes & Comments</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <div class="col-12">
                    <div class="info-card">
                        <h6>Additional Notes</h6>
                        <p class="text-body"><?= nl2br(htmlspecialchars($po['notes'])) ?></p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Authorization Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Authorization & Acceptance</h2>
            </div>
            
            <div class="signature-grid">
                <!-- Everstone Signature -->
                <div class="signature-box">
                    <h6>Authorized Signature (Everstone)</h6>
                    <div class="signature-line">
                        <span style="font-family: 'Georgia', serif; font-size: 14px; color: var(--color-primary);">Emma William</span>
                    </div>
                    <p class="signature-name">Emma William</p>
                    <p class="signature-title">Procurement Manager</p>
                </div>

                <!-- Vendor Acceptance -->
                <div class="signature-box">
                    <h6>Vendor Acceptance</h6>
                    <p class="text-body" style="font-size: 10px; margin-bottom: 16px;">
                        By signing below, Vendor acknowledges receipt and acceptance of this Purchase Order and agrees to all terms and conditions.
                    </p>
                    <div class="signature-line" style="border-style: dashed;">
                        <span style="font-size: 10px; color: var(--color-muted);">Vendor Signature</span>
                    </div>
                    <p class="signature-name">_____________________</p>
                    <p class="signature-title">Date: ________________</p>
                </div>
            </div>
        </section>

        <!-- Terms & Conditions -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Terms & Conditions</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <div class="col-12">
                    <div class="info-card">
                        <h6>Supplier Terms & Conditions</h6>
                        <p class="text-body" style="font-size: 10px;">
                            <strong>Acceptance:</strong> This PO becomes binding upon written, electronic, or performance-based acceptance by Supplier.<br><br>
                            <strong>Delivery:</strong> Supplier shall deliver all items in accordance with the specified delivery dates and locations.<br><br>
                            <strong>Quality:</strong> All items must be new, unused, and meet all specifications and quality standards.<br><br>
                            <strong>Pricing:</strong> Prices are fixed and shall not increase without prior written approval.<br><br>
                            <strong>Payment:</strong> Payment terms as specified in this purchase order.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Everstone -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">About Everstone</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <div class="col-12">
                    <p class="text-body" style="font-size: 12px; line-height: 1.8;">
                        <strong>EVERSTONE TECHNOLOGY SYSTEMS INC.</strong> is a Canada-based technology systems and procurement company specializing in mission-critical equipment delivery. We provide end-to-end supply chain solutions including procurement, quality assurance, export compliance, and global logistics coordination. Our commitment is to deliver authentic, high-quality products through authorized channels while maintaining strict adherence to regulatory, contractual, and operational requirements.
                    </p>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="doc-footer">
            <div class="footer-content">
                <span>EVERSTONE TECHNOLOGY SYSTEMS INC.</span> · 13455 94a Ave #104 Surrey, BC V3V 1M9 Canada · sales@everstonetech.ca · 236-953-7860 · everstonetech.ca
            </div>
        </footer>

    </div>
</body>
</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->render();

$dompdf->stream("Purchase-Order-{$po['po_number']}.pdf", ['Attachment' => true]);
