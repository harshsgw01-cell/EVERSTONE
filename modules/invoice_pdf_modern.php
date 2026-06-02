<?php
session_start();
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

if (!isset($_GET['id'])) {
    header("Location: invoices.php");
    exit;
}

$id = (int) $_GET['id'];

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

if (!$invoice) {
    include("../templates/header.php");
    echo "<div class='container mt-5'><div class='alert alert-danger'>Invoice not found.</div></div>";
    include("../templates/footer.php");
    exit;
}

$order_lines = mysqli_query(
    $conn,
    "SELECT * FROM order_lines WHERE order_id=" . (int)$invoice['order_id']
);

require_once __DIR__ . '/../includes/pdf_styles_modern.php';

$css = get_modern_pdf_css();
$header = get_modern_pdf_header([
    'doc_type' => 'INVOICE',
    'doc_number' => $invoice['code'],
    'company_name' => 'EVERSTONE TECHNOLOGY SYSTEMS INC.'
]);

// Calculate totals
$ol = mysqli_query(
    $conn,
    "SELECT SUM(qty * unit_price) AS total_amount 
     FROM order_lines 
     WHERE order_id=" . (int)$invoice['order_id']
);
$tot = mysqli_fetch_assoc($ol);
$ol_total = $tot['total_amount'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($invoice['code']) ?></title>
    <style><?= $css ?></style>
</head>
<body>
    <div class="pdf-page">
        
        <?= $header ?>

        <!-- Main Content Grid -->
        <div class="grid-container">
            
            <!-- Document Details -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Invoice Details</h6>
                    <p><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['code']) ?></p>
                    <p><strong>Order #:</strong> <?= htmlspecialchars($invoice['order_code']) ?></p>
                    <p><strong>Date:</strong> <?= date("M d, Y", strtotime($invoice['created_at'])) ?></p>
                    <p><strong>Due Date:</strong> <?= htmlspecialchars($invoice['due_date']) ?></p>
                    <p><strong>Paid Date:</strong> <?= htmlspecialchars($invoice['paid_date']) ?></p>
                    <p><strong>Status:</strong> <span class="status-badge <?= strtolower($invoice['status']) ?>"><?= htmlspecialchars($invoice['status']) ?></span></p>
                </div>
            </div>

            <!-- Bill To -->
            <div class="col-4">
                <div class="info-card">
                    <h6>Bill To</h6>
                    <p><strong><?= htmlspecialchars($invoice['customer']) ?></strong></p>
                    <p><?= nl2br(htmlspecialchars($invoice['address'])) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($invoice['email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($invoice['phone']) ?></p>
                </div>
            </div>

            <!-- Company Info -->
            <div class="col-4">
                <div class="info-card">
                    <h6>From</h6>
                    <p><strong>EVERSTONE TECHNOLOGY SYSTEMS INC.</strong></p>
                    <p>13455 94a Ave #104</p>
                    <p>Surrey, BC V3V 1M9 Canada</p>
                    <p><strong>Email:</strong> sales@everstonetech.ca</p>
                    <p><strong>Phone:</strong> 236-953-7860</p>
                    <p><strong>Website:</strong> everstonetech.ca</p>
                </div>
            </div>

        </div>

        <!-- Line Items Section -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Line Items</h2>
            </div>
            
            <?php
            $headers = [
                ['text' => '#', 'align' => 'center'],
                ['text' => 'Product', 'align' => 'left'],
                ['text' => 'Quantity', 'align' => 'center'],
                ['text' => 'Unit Price', 'align' => 'right'],
                ['text' => 'Sub Total', 'align' => 'right']
            ];
            
            $rows = [];
            $i = 1;
            while ($line = mysqli_fetch_assoc($order_lines)) {
                $rows[] = [
                    ['text' => $i++, 'align' => 'center'],
                    ['text' => $line['product'], 'align' => 'left'],
                    ['text' => $line['qty'], 'align' => 'center'],
                    ['text' => '$' . number_format($line['unit_price'], 2), 'align' => 'right'],
                    ['text' => '$' . number_format($line['qty'] * $line['unit_price'], 2), 'align' => 'right']
                ];
            }
            
            $footers = [
                [
                    ['text' => '', 'align' => 'left'],
                    ['text' => '', 'align' => 'left'],
                    ['text' => '', 'align' => 'left'],
                    ['text' => 'Grand Total', 'align' => 'right'],
                    ['text' => '$' . number_format($ol_total, 2), 'align' => 'right', 'color' => '#e8b4a8']
                ]
            ];
            
            echo get_modern_table($headers, $rows, $footers);
            ?>
        </section>

        <!-- Company Overview -->
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

        <!-- Payment Terms -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">Payment Terms</h2>
            </div>
            
            <div class="grid-container" style="padding: 0;">
                <div class="col-6">
                    <div class="info-card">
                        <h6>Bank Information</h6>
                        <p><strong>Bank:</strong> [Bank Name]</p>
                        <p><strong>Account Number:</strong> [Account Number]</p>
                        <p><strong>Routing Number:</strong> [Routing Number]</p>
                        <p><strong>SWIFT Code:</strong> [SWIFT Code]</p>
                    </div>
                </div>
                <div class="col-6">
                    <div class="info-card">
                        <h6>Company Identifiers</h6>
                        <p><strong>UEI:</strong> KQM1HHYHLHR5</p>
                        <p><strong>NCAGE:</strong> 9EAX7</p>
                        <p><strong>DUNS:</strong> 12-346-8707</p>
                        <p><strong>EIN:</strong> 92-2840560</p>
                    </div>
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
