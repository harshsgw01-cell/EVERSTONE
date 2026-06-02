<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

if (!isset($_GET['id'])) {
    header("Location: purchase_order.php");
    exit;
}

$id = (int)$_GET['id'];

/* ---------- FETCH EXISTING PO ---------- */
$poQuery = mysqli_query($conn, "SELECT * FROM purchase_orders WHERE id = $id");
$po = mysqli_fetch_assoc($poQuery);

if (!$po) {
    die("Purchase Order not found");
}

$itemsQuery = mysqli_query($conn, "
    SELECT * FROM purchase_order_items
    WHERE purchase_order_id = $id
    ORDER BY id ASC
");
$existingItems = [];
while ($row = mysqli_fetch_assoc($itemsQuery)) {
    $existingItems[] = $row;
}

/* ---------- HANDLE POST (SAVE EDIT) ---------- */
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $basePONumber = $po['po_number'];
    if ($po['revision'] > 0) {
        $basePONumber = preg_replace('/-\d+$/', '', $po['po_number']);
    }
    $newRevision = (int)$po['revision'] + 1;
    $newPONumber = $basePONumber . '-' . $newRevision;

    $title         = $_POST['title'];
    $expected_date = $_POST['expected_date'] ?: NULL;
    $status        = $_POST['status'];
    $notes         = $_POST['notes'];
    $currency      = $_POST['currency'];

    if ($_POST['payment_terms_select'] === "Custom") {
        $payment_terms = $_POST['payment_terms_custom'] . ' Days';
    } else {
        $payment_terms = $_POST['payment_terms_select'];
    }

    $ship_address       = $_POST['shipto_address']     ?? '';
    $end_user_name      = $_POST['end_user_name']      ?? '';
    $end_user_email     = $_POST['end_user_email']     ?? '';
    $end_user_contact   = $_POST['end_user_contact']   ?? '';
    $end_user_address   = $_POST['end_user_address']   ?? '';
    $end_user_reference = $_POST['end_user_reference'] ?? '';
    $user               = $_SESSION['user'] ?? 'Unknown';

    $stmt = $conn->prepare("
        INSERT INTO purchase_orders
        (po_number, vendor_id, vendor_name, title, expected_delivery, status, notes,
         payment_terms, ship_address, end_user_name, end_user_email, end_user_contact,
         end_user_address, end_user_reference, currency, revision, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param(
        "sisssssssssssssi",
        $newPONumber, $po['vendor_id'], $po['vendor_name'],
        $title, $expected_date, $status, $notes, $payment_terms,
        $ship_address, $end_user_name, $end_user_email, $end_user_contact,
        $end_user_address, $end_user_reference, $currency, $newRevision
    );
    $stmt->execute();
    $newId = $conn->insert_id;

    $total_amount = 0;
    if (!empty($_POST['description'])) {
        for ($i = 0; $i < count($_POST['description']); $i++) {
            $desc  = $_POST['description'][$i];
            $manu  = $_POST['manufacturer'][$i];
            $part  = $_POST['part_number'][$i];
            $unit  = $_POST['unit'][$i];
            $qty   = (float)$_POST['qty'][$i];
            $price = (float)$_POST['unit_price'][$i];

            if ($desc != '') {
                $line_total    = $qty * $price;
                $total_amount += $line_total;

                $itemStmt = $conn->prepare("
                    INSERT INTO purchase_order_items
                    (purchase_order_id, description, manufacturer, part_number, unit, qty, unit_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $itemStmt->bind_param("issssdd", $newId, $desc, $manu, $part, $unit, $qty, $price);
                $itemStmt->execute();
            }
        }
    }

    $updateTotal = $conn->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
    $updateTotal->bind_param("di", $total_amount, $newId);
    $updateTotal->execute();

    $logStmt = $conn->prepare("
        INSERT INTO purchase_order_revisions (purchase_order_id, revision, changed_at, changed_by)
        VALUES (?, ?, NOW(), ?)
    ");
    $logStmt->bind_param("iis", $newId, $newRevision, $user);
    $logStmt->execute();

    header("Location: purchase_order_view.php?id=$newId");
    exit;
}

/* ---------- LOAD VENDORS ---------- */
$vendors = mysqli_query($conn, "SELECT id, name FROM vendors");

$currencySymbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];
$savedCurrency   = $po['currency'] ?? 'USD';
$savedSymbol     = $currencySymbols[$savedCurrency] ?? '$';

$currentTerm = $po['payment_terms'] ?? '30 Days';
$termOptions = ['15 Days', '30 Days', '45 Days', 'Prepaid', 'Custom'];
$isCustom    = !in_array($currentTerm, $termOptions);

include("../templates/header.php");
include("../templates/navbar.php");
?>

<!-- <link rel="stylesheet" href="../assets/css/edit-po.css"> -->

<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-pencil-square"></i>
                    Edit Purchase Order
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($po['revision'] >= 0): ?>
                <span class="rev-badge">
                    <i class="bi bi-arrow-clockwise" style="font-size:.65rem;"></i>
                    Current: Rev. <?= $po['revision'] ?>
                </span>
                <span class="next-rev-badge">
                    <i class="bi bi-plus-circle" style="font-size:.65rem;"></i>
                    Saves as Rev. <?= (int)$po['revision'] + 1 ?>
                </span>
                <?php endif; ?>
                <div class="po-num-badge">
                    <i class="bi bi-hash"></i>
                    <?= htmlspecialchars($po['po_number']) ?>
                </div>
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

    <form method="post" id="poForm">

        <!-- ══════════ PO DETAILS ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="form-card-header-title">Purchase Order Details</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Vendor</label>
                        <input type="text" class="form-control form-input"
                               value="<?= htmlspecialchars($po['vendor_name']) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PO Number</label>
                        <input type="text" class="form-control form-input"
                               value="<?= htmlspecialchars($po['po_number']) ?>" readonly>
                        <div class="form-hint">Read-only — auto-increments on save</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Currency <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="currency" id="currencySelect">
                            <option value="USD" <?= $savedCurrency == 'USD' ? 'selected' : '' ?>>USD — $</option>
                            <option value="EUR" <?= $savedCurrency == 'EUR' ? 'selected' : '' ?>>EUR — €</option>
                            <option value="GBP" <?= $savedCurrency == 'GBP' ? 'selected' : '' ?>>GBP — £</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-input"
                               value="<?= htmlspecialchars($po['title']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expected Delivery Date</label>
                        <input type="date" name="expected_date" class="form-control form-input"
                               value="<?= htmlspecialchars($po['expected_delivery'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-control form-input" name="status">
                            <?php foreach (['Pending', 'Ordered', 'Shipped', 'Received', 'Cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $po['status'] == $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Payment Terms <span class="text-danger">*</span></label>
                        <select name="payment_terms_select" id="payment_terms_select"
                                class="form-control form-input">
                            <?php foreach ($termOptions as $t): ?>
                                <option value="<?= $t ?>"
                                    <?= (!$isCustom && $currentTerm == $t) ? 'selected' : '' ?>
                                    <?= ($isCustom && $t === 'Custom') ? 'selected' : '' ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="payment_terms_custom" id="payment_terms_custom"
                               class="form-control form-input mt-2"
                               value="<?= $isCustom ? htmlspecialchars($currentTerm) : '' ?>"
                               placeholder="Custom payment terms"
                               style="display:<?= $isCustom ? 'block' : 'none' ?>; height:38px;">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control form-input" rows="3"
                            ><?= htmlspecialchars($po['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ SHIPPING + END USER ══════════ -->
        <div class="row g-3 mb-3">

            <!-- Ship To -->
            <div class="col-lg-4">
                <div class="form-card h-100" style="margin-bottom:0;">
                    <div class="form-card-header">
                        <div class="form-card-header-icon" style="background:#fff7ed;color:#ea580c;">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div class="form-card-header-title">Ship To</div>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label">Ship To Address</label>
                        <textarea name="shipto_address" class="form-control form-input" rows="5"
                            ><?= htmlspecialchars($po['ship_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- End User -->
            <div class="col-lg-8">
                <div class="form-card h-100" style="margin-bottom:0;">
                    <div class="form-card-header">
                        <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <div class="form-card-header-title">End User Information</div>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="end_user_name" class="form-control form-input"
                                       value="<?= htmlspecialchars($po['end_user_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="end_user_email" class="form-control form-input"
                                       value="<?= htmlspecialchars($po['end_user_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="end_user_contact" class="form-control form-input"
                                       value="<?= htmlspecialchars($po['end_user_contact'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference</label>
                                <input type="text" name="end_user_reference" class="form-control form-input"
                                       value="<?= htmlspecialchars($po['end_user_reference'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="end_user_address" class="form-control form-input" rows="2"
                                    ><?= htmlspecialchars($po['end_user_address'] ?? '') ?></textarea>
                            </div>
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
                    Purchase Order Items
                    <span style="font-size:.72rem;color:rgba(255,255,255,.4);font-weight:400;">
                        (<?= count($existingItems) ?> item<?= count($existingItems) !== 1 ? 's' : '' ?> loaded)
                    </span>
                </div>
                <button type="button" class="add-row-btn" id="addRowBtn">
                    <i class="bi bi-plus-lg"></i>Add Item
                </button>
            </div>

            <div class="lines-table-wrap">
                <table class="lines-table" id="itemsTable" style="min-height: 150px;">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>Description</th>
                            <th style="min-width:110px;">Manufacturer</th>
                            <th style="min-width:110px;">Part Number</th>
                            <th style="min-width:80px;" class="text-center">Unit</th>
                            <th style="min-width:70px;">Qty</th>
                            <th style="min-width:120px;">
                                Unit Price <span class="currency-symbol"><?= $savedSymbol ?></span>
                            </th>
                            <th style="min-width:110px;">
                                Total <span class="currency-symbol"><?= $savedSymbol ?></span>
                            </th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fallback = [[
                            'description' => '', 'manufacturer' => '',
                            'part_number' => '', 'unit' => 'pcs', 'qty' => 1, 'unit_price' => 0
                        ]];
                        $displayItems = !empty($existingItems) ? $existingItems : $fallback;
                        foreach ($displayItems as $i => $item):
                        ?>
                        <tr>
                            <td class="row-num-cell row-index"><?= $i + 1 ?></td>
                            <td>
                                <input type="text" name="description[]" class="line-input"
                                       value="<?= htmlspecialchars($item['description'] ?? '') ?>" required>
                            </td>
                            <td>
                                <input type="text" name="manufacturer[]" class="line-input"
                                       value="<?= htmlspecialchars($item['manufacturer'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" name="part_number[]" class="line-input"
                                       value="<?= htmlspecialchars($item['part_number'] ?? '') ?>">
                            </td>
                            <td>
                                <select name="unit[]" class="line-input">
                                    <?php foreach (['pcs', 'box', 'set', 'kg', 'lot', 'ea'] as $u): ?>
                                        <option value="<?= $u ?>"
                                            <?= ($item['unit'] ?? '') == $u ? 'selected' : '' ?>>
                                            <?= $u ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="qty[]" class="line-input qty"
                                       value="<?= htmlspecialchars($item['qty'] ?? 1) ?>" min="1">
                            </td>
                            <td>
                                <input type="number" name="unit_price[]" step="0.01"
                                       class="line-input price"
                                       value="<?= htmlspecialchars($item['unit_price'] ?? 0) ?>">
                            </td>
                            <td>
                                <input type="text" class="line-input total" readonly
                                       value="<?= number_format(($item['qty'] ?? 1) * ($item['unit_price'] ?? 0), 2) ?>">
                            </td>
                            <td class="text-center">
                                <button type="button" class="remove-row-btn removeRow" title="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="grand-total-bar">
                <span class="grand-total-label">Grand Total</span>
                <span class="grand-total-currency currency-symbol" style="font-size: 18px; font-weight: 700;"><?= $savedSymbol ?></span>
                <span class="grand-total-value" id="grandTotal">0.00</span>
            </div>
        </div>

        <!-- ══════════ ACTION FOOTER ══════════ -->
        <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
            <div class="action-footer-left">
                <i class="bi bi-clock-history"></i>
                Last revision: <?= date('d M Y', strtotime($po['created_at'])) ?>
                &nbsp;&bull;&nbsp; Saving creates <strong>Rev. <?= (int)$po['revision'] + 1 ?></strong>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="purchase_order_view.php?id=<?= $id ?>" class="action-btn cancel">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="action-btn save">
                    <i class="bi bi-floppy-fill"></i> Save &amp; Create Revision
                </button>
            </div>
        </div>

    </form>
</div><!-- /page-wrapper -->

<script>
/* ── Currency symbol sync ── */
const currencySymbols = { USD: '$', EUR: '€', GBP: '£' };

document.getElementById('currencySelect').addEventListener('change', function () {
    const sym = currencySymbols[this.value] || '$';
    document.querySelectorAll('.currency-symbol').forEach(el => el.textContent = sym);
    calculateTotals();
});

/* ── Row calculations ── */
function calculateTotals() {
    let grandTotal = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
        const qty   = parseFloat(row.querySelector('.qty')?.value)   || 0;
        const price = parseFloat(row.querySelector('.price')?.value) || 0;
        const total = qty * price;
        if (row.querySelector('.total')) row.querySelector('.total').value = total.toFixed(2);
        grandTotal += total;
    });
    document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
}

calculateTotals();

document.addEventListener('input', function (e) {
    if (e.target.classList.contains('qty') || e.target.classList.contains('price')) {
        calculateTotals();
    }
});

/* ── Reindex row numbers ── */
function reIndex() {
    Array.from(document.querySelectorAll('#itemsTable tbody tr')).forEach((row, idx) => {
        const cell = row.querySelector('.row-index');
        if (cell) cell.textContent = idx + 1;
    });
}

/* ── Add row ── */
document.getElementById('addRowBtn').addEventListener('click', function () {
    const tbody  = document.querySelector('#itemsTable tbody');
    const newRow = tbody.rows[0].cloneNode(true);
    newRow.querySelectorAll('input:not([readonly])').forEach(inp => inp.value = '');
    newRow.querySelector('.qty').value = 1;
    newRow.querySelector('.price').value = '0';
    newRow.querySelector('.total').value = '0.00';
    newRow.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    tbody.appendChild(newRow);
    reIndex();
    calculateTotals();
    newRow.querySelector('input:not([readonly])').focus();
});

/* ── Remove row ── */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.removeRow');
    if (!btn) return;
    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        reIndex();
        calculateTotals();
    } else {
        alert('At least one line item is required.');
    }
});

/* ── Payment terms toggle ── */
document.getElementById('payment_terms_select').addEventListener('change', function () {
    const custom = document.getElementById('payment_terms_custom');
    if (this.value === 'Custom') {
        custom.style.display = 'block';
        custom.required = true;
    } else {
        custom.style.display = 'none';
        custom.required = false;
    }
});
</script>

<?php include("../templates/footer.php"); ?>