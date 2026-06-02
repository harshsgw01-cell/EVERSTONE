<?php
include("../includes/auth.php");
include("../config/database.php");
require_once __DIR__ . '/../includes/pdf_styles_modern.php';
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

$shipping = isset($rfq['shipping']) ? (float)$rfq['shipping'] : 0;
$total    = $subtotal + $shipping;

$css = get_modern_pdf_css();
$header = get_modern_pdf_header([
    'doc_type' => 'REQUEST FOR QUOTATION',
    'doc_number' => $rfq['rfq_number'],
    'company_name' => 'EVERSTONE TECHNOLOGY SYSTEMS INC.'
]);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RFQ <?= htmlspecialchars($rfq['rfq_number']) ?></title>
    <style><?= $css ?></style>
</head>
<body>
    <div class="pdf-page">
        
        <?= $header ?>

        <!-- Main Content Grid -->
        <div class="grid-container">
            
            <!-- RFQ Details -->
            <div class="col-4">
                <div class="info-card">
                    <h6>RFQ Details</h6>
                    <p><strong>RFQ Number:</strong> <?= htmlspecialchars($rfq['rfq_number']) ?></p>
                    <p><strong>RFQ Title:</strong> <?= htmlspecialchars($rfq['rfq_title']) ?></p>
                    <p><strong>Quote Date:</strong> <?= date("M d, Y", strtotime($rfq['quote_date'])) ?></p>
                    <p><strong>Lead Time:</strong> <?= htmlspecialchars($rfq['lead_time']) ?> Days</p>
                    <p><strong>Validity:</strong> <?= htmlspecialchars($rfq['validity']) ?></p>
                    <p><strong>Status:</strong> <span class="status-badge <?= strtolower($rfq['status'] ?? 'draft') ?>"><?= htmlspecialchars($rfq['status'] ?? 'DRAFT') ?></span></p>
                </div>
            </div>

            <!-- Sales Person -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Sales Person</h6>
                    <p><strong><?= htmlspecialchars($rfq['salesperson_name']) ?></strong></p>
                    <?php if ($rfq['salesperson_title']): ?>
                        <p><?= htmlspecialchars($rfq['salesperson_title']) ?></p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($rfq['salesperson_email']) ?></p>
                    <p><?= htmlspecialchars($rfq['salesperson_phone']) ?></p>
                </div>
            </div>

            <!-- Customer -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Customer</h6>
                    <p><strong><?= htmlspecialchars($rfq['customer_name']) ?></strong></p>
                    <p><?= nl2br(htmlspecialchars($rfq['customer_address'])) ?></p>
                </div>
            </div>

        </div>

        <!-- Address Information Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Address Information</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <!-- Bill To -->
                <div class="col-4">
                    <div class="info-card">
                        <h6>Bill To</h6>
                        <p><strong><?= htmlspecialchars($rfq['billto_title']) ?></strong></p>
                        <p><?= nl2br(htmlspecialchars($rfq['billto_address'])) ?></p>
                    </div>
                </div>

                <!-- Ship To -->
                <div class="col-4">
                    <div class="info-card">
                        <h6>Ship To</h6>
                        <p><strong><?= htmlspecialchars($rfq['shipto_name']) ?></strong></p>
                        <?php if ($rfq['shipto_company']): ?>
                            <p><?= htmlspecialchars($rfq['shipto_company']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($rfq['shipto_email']) ?></p>
                        <p><?= htmlspecialchars($rfq['shipto_phone']) ?></p>
                        <p><?= nl2br(htmlspecialchars($rfq['shipto_address'])) ?></p>
                    </div>
                </div>

                <!-- Buyer -->
                <div class="col-4">
                    <div class="info-card">
                        <h6>Buyer</h6>
                        <p><strong><?= htmlspecialchars($rfq['buyer_name']) ?></strong></p>
                        <p><?= htmlspecialchars($rfq['buyer_email']) ?></p>
                        <p><?= htmlspecialchars($rfq['buyer_phone']) ?></p>
                        <p><?= nl2br(htmlspecialchars($rfq['buyer_address'])) ?></p>
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
                ['text' => 'Unit Price (€)', 'align' => 'right'],
                ['text' => 'Line Total', 'align' => 'right']
            ];
            
            $rows = [];
            $count = 1;
            foreach ($lines as $line) {
                $rows[] = [
                    ['text' => $count++, 'align' => 'center'],
                    ['text' => $line['description'], 'align' => 'left'],
                    ['text' => $line['mfg'] ?? '—', 'align' => 'left'],
                    ['text' => $line['part'] ?? '—', 'align' => 'left'],
                    ['text' => $line['unit'] ?? '—', 'align' => 'center'],
                    ['text' => $line['qty'], 'align' => 'center'],
                    ['text' => '€' . number_format($line['unit_price'], 2), 'align' => 'right'],
                    ['text' => '€' . number_format($line['total_price'], 2), 'align' => 'right']
                ];
            }
            
            $footers = [
                [
                    ['text' => '', 'align' => 'left', 'colspan' => 7],
                    ['text' => 'Grand Total', 'align' => 'right'],
                    ['text' => '€' . number_format($total, 2), 'align' => 'right', 'color' => '#e8b4a8']
                ]
            ];
            
            echo get_modern_table($headers, $rows, $footers);
            ?>
        </section>

        <!-- Payment & Terms Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Payment & Terms</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <div class="col-12">
                    <div class="info-card">
                        <h6>Quotation Information</h6>
                        <p class="text-body">
                            <strong>Remit To:</strong> EVERSTONE TECHNOLOGY SYSTEMS INC.<br>
                            <strong>Email:</strong> sales@everstonetech.ca<br>
                            <strong>Phone:</strong> 236-953-7860<br><br>
                            Prices in EUR (€). Valid for <?= htmlspecialchars($rfq['validity']) ?> from <?= date("M d, Y", strtotime($rfq['quote_date'])) ?>.<br>
                            Shipping: €<?= number_format($shipping, 2) ?><br>
                            <strong>Grand Total: €<?= number_format($total, 2) ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Authorization Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Authorization</h2>
            </div>
            
            <div class="signature-grid">
                <!-- Everstone Signature -->
                <div class="signature-box">
                    <h6>Authorized Signature (Everstone)</h6>
                    <div class="signature-line">
                        <span style="font-family: 'Georgia', serif; font-size: 14px; color: var(--color-primary);"><?= htmlspecialchars($rfq['salesperson_name']) ?></span>
                    </div>
                    <p class="signature-name"><?= htmlspecialchars($rfq['salesperson_name']) ?></p>
                    <p class="signature-title"><?= htmlspecialchars($rfq['salesperson_title'] ?? 'Sales Representative') ?></p>
                </div>

                <!-- Customer Acceptance -->
                <div class="signature-box">
                    <h6>Customer Acceptance</h6>
                    <p class="text-body" style="font-size: 10px; margin-bottom: 16px;">
                        By signing below, Customer acknowledges receipt and acceptance of this Quotation and agrees to all terms and conditions.
                    </p>
                    <div class="signature-line" style="border-style: dashed;">
                        <span style="font-size: 10px; color: var(--color-muted);">Customer Signature</span>
                    </div>
                    <p class="signature-name">_____________________</p>
                    <p class="signature-title">Date: ________________</p>
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

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('defaultPaperSize', 'A4');
$options->set('defaultPaperOrientation', 'portrait');
$options->set('dpi', 96);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($rfq['rfq_number'] . ".pdf", ["Attachment" => true]);
