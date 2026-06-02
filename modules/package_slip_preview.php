<?php
// Cache this page for 5 minutes (user rarely changes line items between requests)
header('Cache-Control: max-age=300, public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

if (empty($_GET['ids'])) {
    die("Invalid request.");
}

$ids = json_decode($_GET['ids'], true);
if (!is_array($ids) || !count($ids)) {
    die("Invalid request.");
}

$ids    = array_map('intval', $ids);
$idList = implode(',', $ids);

$query = "
SELECT
    ol.*,
    o.id              AS order_id,
    o.order_number,
    o.customer_po_number,
    o.rfq_number,
    o.quote_date,
    o.note_to_buyer,
    bt.title          AS billto_title,
    bt.address        AS billto_address,
    b.name            AS buyer_name,
    b.email           AS buyer_email,
    b.phone           AS buyer_phone,
    st.name           AS shipto_name,
    st.company        AS shipto_company,
    st.address        AS shipto_address,
    st.email          AS shipto_email,
    st.phone          AS shipto_phone
FROM order_lines ol
LEFT JOIN orders     o  ON ol.order_id   = o.id
LEFT JOIN billto     bt ON o.billTo_id   = bt.id
LEFT JOIN buyer      b  ON o.buyer_id    = b.id
LEFT JOIN shipto     st ON o.shipTo_id   = st.id
WHERE ol.id IN ($idList)
ORDER BY ol.id
";

$result = mysqli_query($conn, $query);
$lines  = [];
$order  = null;
while ($row = mysqli_fetch_assoc($result)) {
    $lines[] = $row;
    if (!$order) $order = $row;
}
if (empty($lines)) die("No lines found.");

include("../templates/header.php");
include("../templates/navbar.php");
?>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUarbnLTab7gOsHXXIV4y/WMPo3jTn4nfoFspJ1UkL5VMVDiPvTu" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-b6lVK+yci+bfDmaY1u0zE8YYJt0TZxLEAFyYSLHId4xoVvsrQu3INevPatvLZLOKG" crossorigin="anonymous">
<!-- <link rel="stylesheet" href="../assets/css/package_slip_preview.css"> -->

<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-box-seam-fill"></i>
                    Packaging Slip Preview
                </div>
                <div class="page-header-sub">
                    Review and confirm details before generating PDF
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if (!empty($order['order_number'])): ?>
                <div class="order-num-badge">
                    <i class="bi bi-hash"></i>
                    <?= htmlspecialchars($order['order_number']) ?>
                </div>
                <?php endif; ?>
                <button type="button" class="back-btn" onclick="history.go(-1);">
                    <i class="bi bi-arrow-left"></i>Back
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════ LETTERHEAD ══════════ -->
    <div class="letterhead-strip">
        <div class="d-flex align-items-center gap-3">
            <img src="../assets/Everstone.jpg" alt="Logo">
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

    <form method="post" action="package_slip_generate.php">
        <input type="hidden" name="ids" value="<?= htmlspecialchars(json_encode($ids)) ?>">

        <!-- ══════════ ORDER REFERENCE ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="form-card-header-title">Order Reference</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Customer PO #</label>
                        <input type="text" name="customer_po_number" class="form-control form-input"
                               value="<?= htmlspecialchars($order['customer_po_number'] ?? '') ?>"
                               placeholder="Customer's Purchase Order number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Our Order #</label>
                        <input type="text" name="order_number" class="form-control form-input"
                               value="<?= htmlspecialchars($order['order_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">RFQ #</label>
                        <input type="text" name="rfq_number" class="form-control form-input"
                               value="<?= htmlspecialchars($order['rfq_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ship Date</label>
                        <input type="date" name="ship_date" class="form-control form-input"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Note to Buyer <span style="font-weight:400;color:#94a3b8;text-transform:none;letter-spacing:0;">(printed on slip)</span></label>
                        <input type="text" name="note_to_buyer" class="form-control form-input"
                               value="<?= htmlspecialchars($order['note_to_buyer'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ ADDRESSES ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div class="form-card-header-title">Addresses</div>
            </div>
            <div class="form-card-body">
                <div class="row g-0">

                    <!-- Bill To -->
                    <div class="col-md-3 pe-md-3">
                        <div class="address-section-title ast-billto">
                            <i class="bi bi-building"></i> Bill To
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Company / Entity</label>
                            <input type="text" name="billto_title" class="form-control form-input"
                                   value="<?= htmlspecialchars($order['billto_title'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Address</label>
                            <textarea name="billto_address" class="form-control form-input" rows="3"
                                ><?= htmlspecialchars($order['billto_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="address-divider d-none d-md-block"></div>

                    <!-- Ship To -->
                    <div class="col-md-3 px-md-3">
                        <div class="address-section-title ast-shipto">
                            <i class="bi bi-truck"></i> Ship To
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="shipto_name" class="form-control form-input"
                                   value="<?= htmlspecialchars($order['shipto_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" name="shipto_company" class="form-control form-input"
                                   value="<?= htmlspecialchars($order['shipto_company'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="shipto_address" class="form-control form-input" rows="3"
                                ><?= htmlspecialchars($order['shipto_address'] ?? '') ?></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Email</label>
                                <input type="text" name="shipto_email" class="form-control form-input"
                                       value="<?= htmlspecialchars($order['shipto_email'] ?? '') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="shipto_phone" class="form-control form-input"
                                       value="<?= htmlspecialchars($order['shipto_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="address-divider d-none d-md-block"></div>

                    <!-- Buyer -->
                    <div class="col-md-3 ps-md-3">
                        <div class="address-section-title ast-buyer">
                            <i class="bi bi-person-fill"></i> Buyer / Attention
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="buyer_name" class="form-control form-input"
                                   value="<?= htmlspecialchars($order['buyer_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="buyer_email" class="form-control form-input"
                                   value="<?= htmlspecialchars($order['buyer_email'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input type="text" name="buyer_phone" class="form-control form-input"
                                   value="<?= htmlspecialchars($order['buyer_phone'] ?? '') ?>">
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ══════════ LINE ITEMS ══════════ -->
        <div class="rfq-lines-card">
            <div class="rfq-lines-header">
                <div class="rfq-lines-title">
                    <i class="bi bi-list-ul"></i>
                    Line Items
                    <span>(<?= count($lines) ?> item<?= count($lines) !== 1 ? 's' : '' ?>)</span>
                </div>
            </div>

            <div class="lines-table-wrap">
                <table class="lines-table">
                    <thead>
                        <tr>
                            <th width="38" class="text-center">#</th>
                            <th style="min-width:110px;">Part #</th>
                            <th style="min-width:110px;">Manufacturer</th>
                            <th>Description</th>
                            <th style="min-width:75px;">Qty</th>
                            <th style="min-width:90px;">Shipped</th>
                            <th style="min-width:165px;">Tracking #</th>
                            <th style="min-width:200px;">Tracking URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $i => $line): ?>
                        <tr>
                            <td class="row-num-cell"><?= $i + 1 ?></td>
                            <td>
                                <input type="text" name="part_number[]" class="line-input"
                                       value="<?= htmlspecialchars($line['part_number'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" name="manufacturer[]" class="line-input"
                                       value="<?= htmlspecialchars($line['manufacturer'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" name="description[]" class="line-input"
                                       value="<?= htmlspecialchars($line['description'] ?? $line['product'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="number" name="qty[]" class="line-input"
                                       value="<?= htmlspecialchars($line['qty']) ?>">
                            </td>
                            <td>
                                <input type="number" name="delivered[]" class="line-input"
                                       value="<?= htmlspecialchars($line['delivered'] ?? $line['qty']) ?>"
                                       title="Qty being shipped in this slip">
                            </td>
                            <td>
                                <input type="text" name="tracking_number[]" class="line-input"
                                       value="<?= htmlspecialchars($line['tracking_number'] ?? '') ?>"
                                       placeholder="e.g. 1Z999AA10123456784">
                            </td>
                            <td>
                                <input type="url" name="tracking_url[]" class="line-input"
                                       value="<?= htmlspecialchars($line['tracking_url'] ?? '') ?>"
                                       placeholder="https://…">
                            </td>
                        </tr>
                        <input type="hidden" name="line_id[]" value="<?= $line['id'] ?>">
                        <input type="hidden" name="product[]"  value="<?= htmlspecialchars($line['product'] ?? '') ?>">
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tip-box">
                <i class="bi bi-info-circle-fill me-1"></i>
                <strong>Tip:</strong> Set <em>Delivered</em> to the quantity being shipped in this packing slip.
                Tracking URLs will become clickable links in the generated PDF.
            </div>

        <!-- ══════════ ACTION FOOTER ══════════ -->
        <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
            <div class="action-footer-left">
                <i class="bi bi-shield-check"></i>
                <?= count($lines) ?> line<?= count($lines) !== 1 ? 's' : '' ?> selected for this slip
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="action-btn cancel" onclick="history.go(-1);">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
                <button type="submit" class="action-btn save">
                    <i class="bi bi-filetype-pdf"></i> Download Packing Slip PDF
                </button>
            </div>
        </div>

    </form>
</div><!-- /page-wrapper -->

<?php include("../templates/footer.php"); ?>