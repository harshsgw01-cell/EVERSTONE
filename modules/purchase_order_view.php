<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Sales', 'Account']);

if (!isset($_GET['id'])) {
    header("Location: purchase_order.php");
    exit;
}

$id = (int)$_GET['id'];

/* ---------- FETCH PO ---------- */
$poQuery = mysqli_query($conn, "
    SELECT po.*, v.name AS vendor_name_full, v.email AS vendor_email,
           v.phone AS vendor_phone, v.address AS vendor_address
    FROM purchase_orders po
    LEFT JOIN vendors v ON po.vendor_id = v.id
    WHERE po.id = $id
");

$po = mysqli_fetch_assoc($poQuery);
if (!$po) die("Purchase Order not found");

/* ---------- FETCH ITEMS ---------- */
$items = mysqli_query($conn, "
    SELECT * FROM purchase_order_items
    WHERE purchase_order_id = $id
    ORDER BY id ASC
");

/* ---------- FETCH REVISION HISTORY ---------- */
$revisions = mysqli_query($conn, "
    SELECT * FROM purchase_order_revisions
    WHERE purchase_order_id = $id
    ORDER BY revision ASC
");

$currencySymbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];
$symbol = $currencySymbols[$po['currency'] ?? 'USD'] ?? '$';

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper po-view-page">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-cart-check-fill"></i>
                    Purchase Order #<?= htmlspecialchars($po['po_number']) ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php
                $statusRaw = strtolower($po['status'] ?? '');
                $statusClass = match(true) {
                    str_contains($statusRaw, 'ordered')   => 'sb-ordered',
                    str_contains($statusRaw, 'shipped')   => 'sb-shipped',
                    str_contains($statusRaw, 'received')  => 'sb-received',
                    str_contains($statusRaw, 'pending')   => 'sb-pending',
                    str_contains($statusRaw, 'cancelled') => 'sb-cancelled',
                    default => 'sb-default',
                };
                ?>
                <span class="status-badge <?= $statusClass ?>">
                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                    <?= htmlspecialchars($po['status'] ?? 'Draft') ?>
                </span>
                <?php if ($po['revision'] >= 0): ?>
                <span class="rev-badge">
                    <i class="bi bi-arrow-clockwise" style="font-size:.65rem;"></i>
                    Rev. <?= $po['revision'] ?>
                </span>
                <?php endif; ?>
                <div class="po-num-badge">
                    <i class="bi bi-hash"></i>
                    <?= htmlspecialchars($po['po_number']) ?>
                </div>
                <a href="edit_purchase_order.php?id=<?= $po['id'] ?>" class="header-action-btn hab-edit po-num-badge">
                    <i class="bi bi-pencil-square"></i> Edit PO
                </a>
                <a href="purchase_order_pdf.php?id=<?= $po['id'] ?>" class="header-action-btn hab-pdf po-num-badge`">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
                <button type="button" class="back-btn" onclick="history.go(-1);">
                    <i class="bi bi-arrow-left"></i>Back
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════ LETTERHEAD ══════════ -->
    <div class="letterhead-strip">
        <div class="d-flex align-items-center gap-3">
            <img src="../assets/Everstone.png" alt="Logo">
            <div>
                <div class="letterhead-company-name">EVERSTONE TECHNOLOGY SYSTEMS INC. </div>
                <div class="letterhead-address">
                    13455 94a Ave #104<br>
                    Surrey, BC V3V 1M9 Canada
                </div>
            </div>
        </div>
        <div class="letterhead-divider d-none d-sm-block"></div>
        <div class="letterhead-contact">
            <a href="https://everstonetech.ca/" target="_blank">everstonetech.ca</a><br>
            Global Customer Support<br>
            236-953-7860<br>
            sales@everstonetech.ca
        </div>
    </div>

    <!-- ══════════ PO DETAILS + VENDOR ══════════ -->
    <div class="row g-3 mb-3">
        <!-- PO Details -->
        <div class="col-lg-6">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <div class="info-card-header-icon" style="background:#fef3c7;color:#d97706;">
                        <i class="bi bi-info-circle-fill"></i>
                    </div>
                    <div class="info-card-header-title">Purchase Order Details</div>
                </div>
                <div class="info-card-body">
                    <table class="meta-table">
                        <tr><td>PO Number</td>
                            <td><strong><?= htmlspecialchars($po['po_number']) ?></strong></td></tr>
                        <tr><td>Revision</td>
                            <td><?= $po['revision'] > 0
                                ? '<span class="rev-chip">R' . $po['revision'] . '</span>'
                                : '<span style="color:#64748b;">Original</span>' ?></td></tr>
                        <tr><td>Title</td>
                            <td><?= htmlspecialchars($po['title'] ?? '—') ?></td></tr>
                        <tr><td>Status</td>
                            <td>
                                <span class="status-badge <?= $statusClass ?>" style="font-size:.7rem;padding:3px 8px;">
                                    <?= htmlspecialchars($po['status']) ?>
                                </span>
                            </td></tr>
                        <tr><td>Currency</td>
                            <td><?= htmlspecialchars($po['currency'] ?? 'USD') ?></td></tr>
                        <tr><td>Payment Terms</td>
                            <td><?= htmlspecialchars($po['payment_terms'] ?? '—') ?></td></tr>
                        <tr><td>Expected Delivery</td>
                            <td><?= $po['expected_delivery'] ? date("m/d/Y", strtotime($po['expected_delivery'])) : '—' ?></td></tr>
                        <tr><td>Created</td>
                            <td><?= date("m/d/Y", strtotime($po['created_at'])) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Vendor -->
        <div class="col-lg-6">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <div class="info-card-header-icon" style="background:#eff6ff;color:#0369a1;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="info-card-header-title">Vendor Information</div>
                </div>
                <div class="info-card-body">
                    <table class="meta-table">
                        <tr><td>Name</td>
                            <td><?= htmlspecialchars($po['vendor_name_full'] ?? $po['vendor_name'] ?? '—') ?></td></tr>
                        <tr><td>Email</td>
                            <td>
                                <?php if (!empty($po['vendor_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($po['vendor_email']) ?>"
                                       style="color:var(--sky);font-weight:500;text-decoration:none;">
                                        <?= htmlspecialchars($po['vendor_email']) ?>
                                    </a>
                                <?php else: ?>—<?php endif; ?>
                            </td></tr>
                        <tr><td>Phone</td>
                            <td><?= htmlspecialchars($po['vendor_phone'] ?? '—') ?></td></tr>
                        <tr><td>Address</td>
                            <td><?= nl2br(htmlspecialchars($po['vendor_address'] ?? '—')) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ END USER + SHIP TO ══════════ -->
    <?php if (!empty($po['end_user_name']) || !empty($po['ship_address'])): ?>
    <div class="row g-3 mb-3">
        <?php if (!empty($po['end_user_name'])): ?>
        <div class="col-lg-6">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <div class="info-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <div class="info-card-header-title">End User Information</div>
                </div>
                <div class="info-card-body">
                    <table class="meta-table">
                        <tr><td>Name</td>
                            <td><?= htmlspecialchars($po['end_user_name']) ?></td></tr>
                        <tr><td>Email</td>
                            <td>
                                <?php if (!empty($po['end_user_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($po['end_user_email']) ?>"
                                       style="color:var(--sky);font-weight:500;text-decoration:none;">
                                        <?= htmlspecialchars($po['end_user_email']) ?>
                                    </a>
                                <?php else: ?>—<?php endif; ?>
                            </td></tr>
                        <tr><td>Phone</td>
                            <td><?= htmlspecialchars($po['end_user_contact'] ?? '—') ?></td></tr>
                        <tr><td>Address</td>
                            <td><?= nl2br(htmlspecialchars($po['end_user_address'] ?? '—')) ?></td></tr>
                        <tr><td>Reference</td>
                            <td><?= htmlspecialchars($po['end_user_reference'] ?? '—') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($po['ship_address'])): ?>
        <div class="col-lg-6">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <div class="info-card-header-icon" style="background:#fff7ed;color:#ea580c;">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div class="info-card-header-title">Ship To</div>
                </div>
                <div class="info-card-body" style="font-size:.84rem;color:#475569;line-height:1.7;">
                    <?= nl2br(htmlspecialchars($po['ship_address'])) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════ NOTES ══════════ -->
    <?php if (!empty($po['notes'])): ?>
    <div class="note-box mb-3">
        <strong><i class="bi bi-sticky-fill me-1"></i>Notes:</strong><br>
        <?= nl2br(htmlspecialchars($po['notes'])) ?>
    </div>
    <?php endif; ?>

    <!-- ══════════ LINE ITEMS ══════════ -->
    <?php
    $items_arr = [];
    $grandTotal = 0;
    while ($item = mysqli_fetch_assoc($items)) {
        $item['_line_total'] = $item['qty'] * $item['unit_price'];
        $grandTotal += $item['_line_total'];
        $items_arr[] = $item;
    }
    ?>
    <div class="rfq-lines-card">
        <div class="rfq-lines-header">
            <div class="rfq-lines-title">
                <i class="bi bi-list-ul"></i>
                Line Items
                <span>(<?= count($items_arr) ?> item<?= count($items_arr) !== 1 ? 's' : '' ?>)</span>
            </div>
        </div>

        <div class="lines-table-wrap">
            <table class="lines-table" style="min-height: 150px;">
                <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th>Description</th>
                        <th style="min-width:110px;">Manufacturer</th>
                        <th style="min-width:110px;">Part Number</th>
                        <th style="min-width:65px;" class="text-center">Unit</th>
                        <th style="min-width:60px;" class="text-center">Qty</th>
                        <th style="min-width:110px;" class="text-right">Unit Price</th>
                        <th style="min-width:110px;" class="text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_arr as $i => $item): ?>
                    <tr>
                        <td class="row-num-cell"><?= $i + 1 ?></td>
                        <td style="font-weight:500;color:#0f172a;"><?= htmlspecialchars($item['description']) ?></td>
                        <td style="color:#475569;"><?= htmlspecialchars($item['manufacturer'] ?? '—') ?></td>
                        <td style="color:#475569;font-family:monospace;font-size:.78rem;"><?= htmlspecialchars($item['part_number'] ?? '—') ?></td>
                        <td class="text-center" style="color:#64748b;"><?= htmlspecialchars($item['unit'] ?? '—') ?></td>
                        <td class="num-cell text-center"><?= htmlspecialchars($item['qty']) ?></td>
                        <td class="num-cell"><?= $symbol ?><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="num-cell" style="font-weight:700;"><?= $symbol ?><?= number_format($item['_line_total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="grand-total-bar">
            <span class="grand-total-label">Grand Total</span>
            <span class="grand-total-value"><?= $symbol ?><?= number_format($grandTotal, 2) ?></span>
        </div>
    </div>

    <!-- ══════════ REVISION HISTORY ══════════ -->
    <?php if (mysqli_num_rows($revisions) > 0): ?>
    <div class="info-card mb-3">
        <div class="info-card-header">
            <div class="info-card-header-icon" style="background:#f5f3ff;color:#7c3aed;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="info-card-header-title">Revision History</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="lines-table">
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Changed At</th>
                        <th>Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($rev = mysqli_fetch_assoc($revisions)): ?>
                    <tr>
                        <td><span class="rev-chip">R<?= $rev['revision'] ?></span></td>
                        <td><?= date("m/d/Y H:i", strtotime($rev['changed_at'])) ?></td>
                        <td><?= htmlspecialchars($rev['changed_by'] ?? '—') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════ ACTION FOOTER ══════════ -->
    <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
        <div class="action-footer-left">
            <i class="bi bi-calendar3"></i>
            Created <?= date("m/d/Y", strtotime($po['created_at'])) ?>
            <?php if ($po['revision'] > 0): ?>
                &nbsp;&bull;&nbsp; Revision R<?= $po['revision'] ?>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="action-btn cancel" onclick="history.go(-1);">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <a href="edit_purchase_order.php?id=<?= $po['id'] ?>" class="action-btn edit">
                <i class="bi bi-pencil-square"></i> Edit PO
            </a>
            <a href="purchase_order_pdf.php?id=<?= $po['id'] ?>" class="action-btn pdf">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
        </div>
    </div>

</div><!-- /page-wrapper -->

<?php include("../templates/footer.php"); ?>
