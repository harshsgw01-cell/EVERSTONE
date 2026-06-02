<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Sales', 'Account']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "<div class='alert alert-danger'>Invalid order ID.</div>"; exit; }

$query = "
SELECT
    o.*,
    c.name AS customer_name,
    c.address AS customer_address,
    sp.name AS salesperson_name,
    sp.title AS salesperson_title,
    sp.email AS salesperson_email,
    sp.phone AS salesperson_phone,
    sp.signature AS salesperson_signature,
    bt.title AS billto_title,
    bt.address AS billto_address,
    b.name AS buyer_name,
    b.email AS buyer_email,
    b.phone AS buyer_phone,
    b.address AS buyer_address,
    st.name AS shipto_name,
    st.company AS shipto_company,
    st.email AS shipto_email,
    st.phone AS shipto_phone,
    st.address AS shipto_address
FROM orders o
LEFT JOIN customers  c  ON o.customer_id   = c.id
LEFT JOIN salesperson sp ON o.salesPerson_id = sp.id
LEFT JOIN billto     bt ON o.billTo_id      = bt.id
LEFT JOIN buyer      b  ON o.buyer_id       = b.id
LEFT JOIN shipto     st ON o.shipTo_id      = st.id
WHERE o.id = $id
";

$order = mysqli_fetch_assoc(mysqli_query($conn, $query));
if (!$order) { echo "<div class='alert alert-danger'>Order not found.</div>"; exit; }

$lines_result = mysqli_query($conn, "SELECT * FROM order_lines WHERE order_id = $id");

include("../templates/header.php");
include("../templates/navbar.php");
?>

<!-- <link rel="stylesheet" href="../assets/css/order-view.css"> -->

<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-bag-check-fill"></i>
                    Order #<?= htmlspecialchars($order['order_number'] ?? $order['code'] ?? $order['id']) ?>
                    <?php if (!empty($order['customer_po_number'])): ?>
                        <span style="font-size:.75rem;color:rgba(255,255,255,.4);font-weight:400;">
                            | <strong style="color:rgba(255,255,255,.7);"><?= htmlspecialchars($order['customer_po_number']) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php
                $statusRaw = strtolower($order['status'] ?? '');
                $statusClass = match(true) {
                    str_contains($statusRaw, 'complete') => 'status-completed',
                    str_contains($statusRaw, 'open')     => 'status-open',
                    str_contains($statusRaw, 'cancel')   => 'status-cancelled',
                    str_contains($statusRaw, 'pending')  => 'status-pending',
                    default => 'status-draft',
                };
                ?>
                <span class="status-badge <?= $statusClass ?>">
                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                    <?= htmlspecialchars($order['status'] ?? 'Draft') ?>
                </span>
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

    <!-- ══════════ TOP ROW: PARTIES + META ══════════ -->
    <div class="row g-3 mb-3">

        <!-- Bill To / Buyer / Ship To -->
        <div class="col-lg-7">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <div class="info-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="info-card-header-title">Parties & Contacts</div>
                </div>
                <div class="info-card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="party-block">
                                <strong><i class="bi bi-building me-1 text-muted"></i>Bill To</strong>
                                <span><?= htmlspecialchars($order['billto_title'] ?? '—') ?></span>
                                <?php if (!empty($order['billto_address'])): ?>
                                    <span><?= nl2br(htmlspecialchars($order['billto_address'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="party-block">
                                <strong><i class="bi bi-person-fill me-1 text-muted"></i>Buyer</strong>
                                <span><?= htmlspecialchars($order['buyer_name'] ?? '—') ?></span>
                                <?php if (!empty($order['buyer_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($order['buyer_email']) ?>">
                                        <?= htmlspecialchars($order['buyer_email']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($order['buyer_phone'])): ?>
                                    <span><?= htmlspecialchars($order['buyer_phone']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="party-block">
                                <strong><i class="bi bi-truck me-1 text-muted"></i>Ship To</strong>
                                <span>
                                    <?= htmlspecialchars($order['shipto_name'] ?? '—') ?>
                                    <?= $order['shipto_company'] ? ' <em>(' . htmlspecialchars($order['shipto_company']) . ')</em>' : '' ?>
                                </span>
                                <?php if (!empty($order['shipto_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($order['shipto_email']) ?>">
                                        <?= htmlspecialchars($order['shipto_email']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($order['shipto_address'])): ?>
                                    <span><?= nl2br(htmlspecialchars($order['shipto_address'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($order['salesperson_name'])): ?>
                    <hr style="border-color:#f1f5f9;margin:14px 0 10px;">
                    <div class="party-block">
                        <strong style="display:inline;"><i class="bi bi-briefcase-fill me-1 text-muted"></i>Sales Rep:</strong>
                        <span style="margin-left:6px;">
                            <?= htmlspecialchars($order['salesperson_name']) ?>
                            <?= !empty($order['salesperson_title']) ? ' &mdash; ' . htmlspecialchars($order['salesperson_title']) : '' ?>
                            <?php if (!empty($order['salesperson_email'])): ?>
                                &nbsp;<a href="mailto:<?= htmlspecialchars($order['salesperson_email']) ?>">
                                    <?= htmlspecialchars($order['salesperson_email']) ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($order['salesperson_phone'])): ?>
                                &nbsp;&bull;&nbsp;<?= htmlspecialchars($order['salesperson_phone']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Meta -->
        <div class="col-lg-5">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <div class="info-card-header-icon" style="background:#fef3c7;color:#d97706;">
                        <i class="bi bi-info-circle-fill"></i>
                    </div>
                    <div class="info-card-header-title">Order Details</div>
                </div>
                <div class="info-card-body">
                    <div class="meta-grid">
                        <div>
                            <div class="meta-item-label">RFQ #</div>
                            <div class="meta-item-value"><?= htmlspecialchars($order['rfq_number'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="meta-item-label">RFQ Title</div>
                            <div class="meta-item-value"><?= htmlspecialchars($order['rfq_title'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="meta-item-label">Customer PO #</div>
                            <div class="meta-item-value"><?= htmlspecialchars($order['customer_po_number'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="meta-item-label">Quote Date</div>
                            <div class="meta-item-value"><?= htmlspecialchars($order['quote_date'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="meta-item-label">Lead Time</div>
                            <div class="meta-item-value"><?= htmlspecialchars($order['lead_time'] ?? '—') ?></div>
                        </div>
                        <div>
                            <div class="meta-item-label">Customer</div>
                            <div class="meta-item-value"><?= htmlspecialchars($order['customer_name'] ?? '—') ?></div>
                        </div>
                    </div>

                    <?php if (!empty($order['note_to_buyer'])): ?>
                    <div class="note-box mt-3">
                        <strong><i class="bi bi-sticky-fill me-1"></i>Note to Buyer:</strong><br>
                        <?= nl2br(htmlspecialchars($order['note_to_buyer'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ ORDER LINES ══════════ -->
    <?php
    $grand_total = 0;
    $grand_cost  = 0;
    $lines_arr   = [];
    while ($line = mysqli_fetch_assoc($lines_result)) $lines_arr[] = $line;
    ?>
    <div class="rfq-lines-card">
        <div class="rfq-lines-header">
            <div class="rfq-lines-title">
                <i class="bi bi-list-ul"></i>
                Order Line Items
                <span>(<?= count($lines_arr) ?> item<?= count($lines_arr) !== 1 ? 's' : '' ?>)</span>
            </div>
        </div>

        <div class="order-table-wrap">
            <table class="order-line-table">
                <thead>
                    <tr>
                        <th class="text-center" width="32">
                            <input type="checkbox" class="line-check" id="selectAll" title="Select All">
                        </th>
                        <th width="38" class="text-center">#</th>
                        <th>Part / Product</th>
                        <th width="60" class="text-center">Qty</th>
                        <th width="70" class="text-center">Delivered</th>
                        <th width="70" class="text-center">Remaining</th>
                        <th width="130">Fulfillment</th>
                        <th width="130">Tracking</th>
                        <th width="90" class="text-center">Status</th>
                        <th width="65" class="text-center">Invoiced</th>
                        <th width="90" class="text-right">Unit Price</th>
                        <th width="100" class="text-right">Cost</th>
                        <th width="95" class="text-right">Margin</th>
                        <th width="95" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 1;
                    foreach ($lines_arr as $line):
                        $qty           = (float)($line['qty'] ?? 0);
                        $unit_price    = (float)($line['unit_price'] ?? 0);
                        $cost_price    = (float)($line['cost_price'] ?? 0);
                        $shipping_cost = (float)($line['shipping_cost'] ?? 0);
                        $delivered     = (float)($line['delivered'] ?? 0);
                        $remaining     = max(0, $qty - $delivered);
                        $amount        = $qty * $unit_price;
                        $total_cost    = ($qty * $cost_price) + $shipping_cost;
                        $margin        = $amount - $total_cost;
                        $grand_total  += $amount;
                        $grand_cost   += $total_cost;
                        $is_completed  = ($line['status'] === 'Completed');

                        $fm = $line['fulfillment_method'] ?? 'Stock';
                        $fmClass = match($fm) {
                            'Drop Ship'           => 'fm-dropship',
                            'Warehouse Delivery'  => 'fm-warehouse',
                            default               => 'fm-stock',
                        };
                    ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="line-check line-checkbox"
                                   data-ol-id="<?= $line['id'] ?>"
                                   <?= $is_completed ? 'disabled checked' : '' ?>>
                        </td>
                        <td class="text-center" style="font-size:.72rem;font-weight:700;color:#94a3b8;padding-top:14px;">
                            <?= $i++ ?>
                        </td>
                        <td>
                            <a href="handle-order-line.php?ol_id=<?= $line['id'] ?>&i=<?= $i-1 ?>"
                               class="product-link">
                                <?= htmlspecialchars($line['product'] ?? $line['description'] ?? 'N/A') ?>
                            </a>
                            <div class="product-meta">
                                <?php if (!empty($line['part_number'])): ?>
                                    <b>PART:</b> <?= htmlspecialchars($line['part_number']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($line['manufacturer'])): ?>
                                    <b>MFG:</b> <?= htmlspecialchars($line['manufacturer']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($line['description']) && $line['description'] !== ($line['product'] ?? '')): ?>
                                    <b>DESC:</b> <?= htmlspecialchars($line['description']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="num-cell"><?= number_format($qty, 0) ?></td>
                        <td class="num-cell delivered-val"><?= number_format($delivered, 0) ?></td>
                        <td class="num-cell <?= $remaining > 0 ? 'remaining-danger' : 'remaining-ok' ?>">
                            <?= number_format($remaining, 0) ?>
                        </td>
                        <td>
                            <span class="fulfillment-badge <?= $fmClass ?>">
                                <?= htmlspecialchars($fm) ?>
                            </span>
                            <?php if (!empty($line['supplier_name'])): ?>
                                <div class="product-meta mt-1"><?= htmlspecialchars($line['supplier_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($line['supplier_po_number'])): ?>
                                <div class="product-meta"><b>PO:</b> <?= htmlspecialchars($line['supplier_po_number']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($line['tracking_number'])): ?>
                                <?php if (!empty($line['tracking_url'])): ?>
                                    <a href="<?= htmlspecialchars($line['tracking_url']) ?>"
                                       class="tracking-link" target="_blank">
                                        <i class="bi bi-truck"></i>
                                        <?= htmlspecialchars($line['tracking_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="tracking-plain">
                                        <i class="bi bi-truck"></i>
                                        <?= htmlspecialchars($line['tracking_number']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#cbd5e1;font-size:.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="line-status-badge <?= $is_completed ? 'ls-completed' : 'ls-pending' ?>">
                                <i class="bi bi-circle-fill" style="font-size:.45rem;"></i>
                                <?= htmlspecialchars($line['status'] ?? 'Pending') ?>
                            </span>
                        </td>
                        <td class="num-cell"><?= number_format($line['invoiced'] ?? 0, 0) ?></td>
                        <td class="num-cell" style="color:#0f172a;">$<?= number_format($unit_price, 2) ?></td>
                        <td class="num-cell" style="color:#64748b;">
                            $<?= number_format($cost_price, 2) ?>
                            <?php if ($shipping_cost > 0): ?>
                                <div class="cost-ship">+$<?= number_format($shipping_cost, 2) ?> ship</div>
                            <?php endif; ?>
                        </td>
                        <td class="num-cell <?= $margin >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                            $<?= number_format($margin, 2) ?>
                        </td>
                        <td class="num-cell" style="font-weight:700;color:#0f172a;">
                            $<?= number_format($amount, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <?php $net = $grand_total - $grand_cost; ?>
        <div class="totals-section">
            <div class="total-row revenue">
                <span class="total-label"><i class="bi bi-graph-up me-1"></i>Total Revenue</span>
                <span class="total-value">$<?= number_format($grand_total, 2) ?></span>
            </div>
            <div class="total-row cost">
                <span class="total-label"><i class="bi bi-box me-1"></i>Total Cost</span>
                <span class="total-value" style="color:#64748b;">$<?= number_format($grand_cost, 2) ?></span>
            </div>
        </div>
        <div class="grand-total-bar">
                <span class="grand-total-label"><i class="bi bi-currency-dollar me-1"></i>Net Profit / Loss</span>
                <span class="grand-total-value <?= $net >= 0 ? 'net-positive' : 'net-negative' ?>">
                    $ <?= number_format($net, 2) ?>
                </span>
            </div>
    </div>

    <!-- ══════════ ACTION FOOTER ══════════ -->
    <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
        <div class="action-footer-left">
            <i class="bi bi-calendar3"></i>
            <?php if (!empty($order['created_at'])): ?>
                Order created <?= date('d M Y', strtotime($order['created_at'])) ?>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button id="btnPackagingSlip" class="action-btn info-btn" disabled>
                <i class="bi bi-filetype-pdf"></i> Packaging Slip
            </button>
            <button id="btnInvoice" class="action-btn info-btn" disabled>
                <i class="bi bi-receipt"></i> Create Invoice
            </button>
        </div>
    </div>

</div><!-- /page-wrapper -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll  = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.line-checkbox');
    const btnPS      = document.getElementById('btnPackagingSlip');
    const btnInv     = document.getElementById('btnInvoice');

    function getSelectedIds() {
        return Array.from(checkboxes).filter(cb => cb.checked && !cb.disabled).map(cb => cb.dataset.olId);
    }
    function updateButtons() {
        const has = getSelectedIds().length > 0;
        btnPS.disabled  = !has;
        btnInv.disabled = !has;
    }
    function updateHeader() {
        const enabled = Array.from(checkboxes).filter(cb => !cb.disabled);
        const checked = enabled.filter(cb => cb.checked).length;
        selectAll.indeterminate = checked > 0 && checked < enabled.length;
        selectAll.checked = checked === enabled.length && enabled.length > 0;
    }

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(cb => { if (!cb.disabled) cb.checked = this.checked; });
        updateButtons();
    });
    checkboxes.forEach(cb => cb.addEventListener('change', () => { updateHeader(); updateButtons(); }));

    btnPS.addEventListener('click', function () {
        const ids = getSelectedIds();
        if (!ids.length) return;
        window.location.href = 'package_slip_preview.php?ids=' + encodeURIComponent(JSON.stringify(ids));
    });
    btnInv.addEventListener('click', function () {
        const ids = getSelectedIds();
        if (!ids.length) return;
        window.location.href = 'invoice_preview.php?ids=' + encodeURIComponent(JSON.stringify(ids));
    });

    updateHeader();
    updateButtons();
});
</script>

<?php include("../templates/footer.php"); ?>