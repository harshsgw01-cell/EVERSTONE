<?php

include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

/* ---------- HANDLE POST ---------- */
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $vendor_id = (int)$_POST['vendor_id'];

    $vendorQuery = mysqli_query($conn, "SELECT name FROM vendors WHERE id = $vendor_id LIMIT 1");
    $vendorData  = mysqli_fetch_assoc($vendorQuery);

    if (!$vendorData) {
        die("Invalid Vendor Selected");
    }

    $vendor_name   = $vendorData['name'];
    $po_number     = $_POST['po_number'];
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

    $shipto_selected = $_POST['shipto_id'];

    if ($shipto_selected === 'end_user') {
        $ship_to        = 'End User';
        $shipto_address = $_POST['end_user_address']; // use end user address
    } else {
        $shipto_row     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM shipto WHERE id = " . (int)$shipto_selected . " LIMIT 1"));
        $shipto_name    = strtolower($shipto_row['name'] ?? '');
        $shipto_address = $_POST['shipto_address']; // use company address

        if (str_contains($shipto_name, 'end user') || str_contains($shipto_name, 'end-user')) {
            $ship_to = 'End User';
        } elseif (str_contains($shipto_name, 'other')) {
            $ship_to = 'Other';
        } else {
            $ship_to = 'Company';
        }
    }

    $end_user_name      = $_POST['end_user_name']      ?? '';
    $end_user_email     = $_POST['end_user_email']     ?? '';
    $end_user_contact   = $_POST['end_user_contact']   ?? '';
    $end_user_address   = $_POST['end_user_address']   ?? '';
    $end_user_reference = $_POST['end_user_reference'] ?? '';

    $stmt = $conn->prepare("INSERT INTO purchase_orders 
    (po_number, vendor_id, vendor_name, title, expected_delivery, status, notes, 
     payment_terms, ship_to, shipto_address, end_user_name, end_user_email,
     end_user_contact, end_user_address, end_user_reference, currency, created_at) 
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

    $stmt->bind_param(
        "sissssssssssssss",
        $po_number,
        $vendor_id,
        $vendor_name,
        $title,
        $expected_date,
        $status,
        $notes,
        $payment_terms,
        $ship_to,
        $shipto_address,
        $end_user_name,
        $end_user_email,
        $end_user_contact,
        $end_user_address,
        $end_user_reference,
        $currency
    );

    if (!$stmt->execute()) {
        die("Insert failed: " . $stmt->error); // temporary debug line
    }

    $purchase_order_id = $stmt->insert_id;

    /* SAVE ITEMS */
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

                $itemStmt = $conn->prepare("INSERT INTO purchase_order_items 
                    (purchase_order_id, description, manufacturer, part_number, unit, qty, unit_price) 
                    VALUES (?,?,?,?,?,?,?)");

                $itemStmt->bind_param(
                    "issssid",
                    $purchase_order_id,
                    $desc,
                    $manu,
                    $part,
                    $unit,
                    $qty,
                    $price
                );
                $itemStmt->execute();
            }
        }
    }

    $updateTotal = $conn->prepare("UPDATE purchase_orders SET total_amount=? WHERE id=?");
    $updateTotal->bind_param("di", $total_amount, $purchase_order_id);
    $updateTotal->execute();

    header("Location: purchase_order.php");
    exit;
}


/* ---------- LOAD DATA ---------- */
$vendors = mysqli_query($conn, "SELECT id,name FROM vendors");
$shiptos = mysqli_query($conn, "
    SELECT * FROM shipto 
    WHERE name IN ('US Forces Technical Supply', 'End User', 'Other')
");

$company = [
    'name'         => 'US Forces Technical Supply',
    'contact_name' => 'Sajid Ahmadzai',
    'email'        => 'sales@everstonetech.ca',
    'address'      => '321 Industrial Park, Dallas, TX 75001',
];

include("../templates/header.php");
include("../templates/navbar.php");

$year = date("Y");

$lastPO = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT po_number FROM purchase_orders 
    WHERE po_number LIKE 'PO-$year-%'
    AND po_number NOT LIKE 'PO-$year-%-%-' 
    AND po_number NOT REGEXP 'PO-$year-[0-9]+-[0-9]+'
    ORDER BY CAST(SUBSTRING_INDEX(po_number, '-', -1) AS UNSIGNED) DESC
    LIMIT 1
"));

if ($lastPO) {
    $parts   = explode('-', $lastPO['po_number']);
    $lastNum = (int) end($parts);
    $nextNum = $lastNum + 1;
} else {
    $nextNum = 1;
}

$poNumber = "PO-$year-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
?>

<div class="page-wrapper create-po-page">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-cart-plus-fill"></i>
                    Create Purchase Order
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="po-num-badge">
                    <i class="bi bi-hash"></i>
                    <?= htmlspecialchars($poNumber) ?>
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

        <!-- ══════════ ORDER DETAILS ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#ffedd5;color:#ea580c;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="form-card-header-title">Purchase Order Details</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Vendor <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="vendor_id" id="vendorSelect" required>
                            <option value="">Select Vendor</option>
                            <?php while ($v = mysqli_fetch_assoc($vendors)): ?>
                                <option value="<?= htmlspecialchars($v['id']) ?>">
                                    <?= htmlspecialchars($v['name']) ?>
                                </option>
                            <?php endwhile; ?>
                            <option value="add_new" style="color:#0284c7;font-weight:700;">
                                ＋ Create New Vendor
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PO Number <span class="text-danger">*</span></label>
                        <input type="text" name="po_number" class="form-control form-input"
                            value="<?= $poNumber ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Currency <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="currency" id="currencySelect">
                            <option value="EUR">EUR — Euro (€)</option>
                            <option value="USD">USD — US Dollar ($)</option>
                            <option value="GBP">GBP — British Pound (£)</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-input"
                            placeholder="Purchase Order Title" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Expected Delivery</label>
                        <input type="date" name="expected_date" class="form-control form-input">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-control form-input" name="status">
                            <option value="Pending">Pending</option>
                            <option value="Ordered">Ordered</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Received">Received</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control form-input" rows="2"
                            placeholder="Additional notes or instructions"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ PAYMENT & SHIPPING ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="bi bi-truck-fill"></i>
                </div>
                <div class="form-card-header-title">Payment &amp; Shipping</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Payment Terms <span class="text-danger">*</span></label>
                        <select name="payment_terms_select" id="payment_terms_select"
                            class="form-control form-input" onchange="toggleCustomPayment()">
                            <option value="Net 15">15 Days</option>
                            <option value="Net 30" selected>30 Days</option>
                            <option value="Net 45">45 Days</option>
                            <option value="Prepaid">Prepaid</option>
                            <option value="Custom">Custom</option>
                        </select>
                        <input type="text" name="payment_terms_custom" id="payment_terms_custom"
                            class="form-control form-input mt-2"
                            placeholder="Enter custom number of days"
                            style="display:none;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ship To <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="shipto_id" id="shipToSelect"
                            onchange="handleShipTo()" required>
                            <option value="">Select Shipping Destination</option>
                            <option value="end_user" data-name="End User">End User</option>
                            <?php while ($st = mysqli_fetch_assoc($shiptos)): ?>
                                <option value="<?= $st['id'] ?>"
                                    data-name="<?= htmlspecialchars($st['name']) ?>">
                                    <?= htmlspecialchars($st['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- End User Section -->
                <div id="endUserSection" style="display:none;">
                    <hr class="section-divider mt-4">
                    <div class="form-card-header px-0 bg-transparent border-0 pb-2 mb-1">
                        <div class="form-card-header-icon" style="background:#ede9fe;color:#7c3aed;">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>
                        <div class="form-card-header-title">End User Information</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">End User Name</label>
                            <input type="text" name="end_user_name" class="form-control form-input">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End User Email</label>
                            <input type="email" name="end_user_email" class="form-control form-input">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End User Phone</label>
                            <input type="text" name="end_user_contact" class="form-control form-input">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End User Reference / PO Ref</label>
                            <input type="text" name="end_user_reference" class="form-control form-input">
                        </div>
                        <div class="col-12">
                            <label class="form-label">End User Address</label>
                            <textarea name="end_user_address" class="form-control form-input" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Company Ship Section -->
                <div id="companyShipSection" style="display:none;">
                    <hr class="section-divider mt-4">
                    <div class="form-card-header px-0 bg-transparent border-0 pb-2 mb-1">
                        <div class="form-card-header-icon" style="background:#e0f2fe;color:#0284c7;">
                            <i class="bi bi-building-fill"></i>
                        </div>
                        <div class="form-card-header-title">Ship To — Our Details</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="ship_company_name" class="form-control form-input"
                                value="<?= htmlspecialchars($company['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Point of Contact</label>
                            <input type="text" name="ship_contact_name" class="form-control form-input"
                                value="<?= htmlspecialchars($company['contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="ship_contact_email" class="form-control form-input"
                                value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ship To Address</label>
                            <textarea name="shipto_address" class="form-control form-input" rows="2"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
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
                            <th style="min-width:220px;">Description <span class="text-danger">*</span></th>
                            <th style="min-width:130px;">Manufacturer</th>
                            <th style="min-width:130px;">Part Number</th>
                            <th style="min-width:80px;">Unit</th>
                            <th style="min-width:70px;">Qty</th>
                            <th style="min-width:120px;">Unit Price</th>
                            <th style="min-width:110px;">Total</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row">
                            <td class="row-num-cell">1</td>
                            <td><input type="text" name="description[]" class="line-input" placeholder="Item description" required></td>
                            <td><input type="text" name="manufacturer[]" class="line-input" placeholder="Manufacturer"></td>
                            <td><input type="text" name="part_number[]" class="line-input" placeholder="Part #"></td>
                            <td>
                                <select name="unit[]" class="line-input">
                                    <option value="pcs">pcs</option>
                                    <option value="box">box</option>
                                    <option value="set">set</option>
                                    <option value="kg">kg</option>
                                    <option value="lot">lot</option>
                                    <option value="ea">ea</option>
                                </select>
                            </td>
                            <td><input type="number" name="qty[]" class="line-input qty" value="1" min="1"></td>
                            <td><input type="number" name="unit_price[]" class="line-input price" step="0.01" placeholder="0.00"></td>
                            <td><input type="text" class="line-input total" readonly placeholder="0.00" style="background:#f8fafc;color:#64748b;"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grand-total-bar">
                <span class="grand-total-label">Grand Total</span>
                <span class="grand-total-currency currency-symbol" id="currencySymbol" style="font-size: 18px; font-weight: 700;">€</span>
                <span class="grand-total-value" id="grandTotal">0.00</span>
            </div>
        </div>

        <!-- ══════════ ACTION FOOTER ══════════ -->
        <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
            <div class="action-footer-left">
                <i class="bi bi-info-circle"></i>
                All fields marked <span class="text-danger fw-bold ms-1">*</span> are required
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="action-btn cancel" onclick="purchase_order.php">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <button type="submit" class="action-btn save">
                    <i class="bi bi-floppy-fill"></i> Save Purchase Order
                </button>
            </div>
        </div>

    </form>
</div><!-- /page-wrapper -->

<!-- ══════════════════════════════════════════
     ADD VENDOR MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#ea580c,#c2410c);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-building-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New Vendor</h5>
                        <small class="text-white opacity-75">Fill in the vendor details below</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <form id="addVendorForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="modal-label">Vendor Name <span class="text-danger">*</span></div>
                            <input type="text" name="name" class="form-control modal-input"
                                placeholder="e.g. Acme Supplies Inc." required>
                        </div>
                        <div class="col-md-6">
                            <div class="modal-label">Email</div>
                            <input type="email" name="email" class="form-control modal-input"
                                placeholder="e.g. contact@vendor.com">
                        </div>
                        <div class="col-md-6">
                            <div class="modal-label">Phone</div>
                            <input type="text" name="phone" class="form-control modal-input"
                                placeholder="e.g. +1 555 000 0000">
                        </div>
                        <div class="col-12">
                            <div class="modal-label">Address</div>
                            <textarea name="address" class="form-control modal-input" rows="2"
                                placeholder="Street, City, State, ZIP"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                    data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-sm px-4" onclick="saveVendorAjax()"
                    style="background:#ea580c;color:#fff;border-color:#c2410c;">
                    <i class="bi bi-plus-circle me-1"></i>Save Vendor
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    /* ── Currency symbols ── */
    const currencySymbols = {
        USD: '$',
        EUR: '€',
        GBP: '£'
    };

    document.getElementById('currencySelect').addEventListener('change', function() {
        document.getElementById('currencySymbol').textContent = currencySymbols[this.value] || '$';
        calculateTotals();
    });

    /* ── Ship To toggle ── */
    function handleShipTo() {
        const select = document.getElementById('shipToSelect');
        const selected = select.options[select.selectedIndex];
        const name = (selected.dataset.name || '').toLowerCase();

        document.getElementById('endUserSection').style.display = 'none';
        document.getElementById('companyShipSection').style.display = 'none';

        if (name.includes('end user') || name.includes('end-user')) {
            document.getElementById('endUserSection').style.display = 'block';
        } else if (name !== '') {
            document.getElementById('companyShipSection').style.display = 'block';
        }
    }

    /* ── Payment terms toggle ── */
    function toggleCustomPayment() {
        const select = document.getElementById('payment_terms_select');
        const custom = document.getElementById('payment_terms_custom');
        if (select.value === 'Custom') {
            custom.style.display = 'block';
            custom.required = true;
        } else {
            custom.style.display = 'none';
            custom.required = false;
        }
    }

    /* ── Row numbering ── */
    function reIndex() {
        document.querySelectorAll('#itemsBody tr.item-row').forEach((tr, i) => {
            const cell = tr.querySelector('.row-num-cell');
            if (cell) cell.textContent = i + 1;
        });
    }

    /* ── Calculate totals ── */
    function calculateTotals() {
        let grand = 0;
        document.querySelectorAll('#itemsBody tr.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.price')?.value) || 0;
            const total = qty * price;
            const totalInput = row.querySelector('.total');
            if (totalInput) totalInput.value = total > 0 ? total.toFixed(2) : '';
            grand += total;
        });
        document.getElementById('grandTotal').textContent = grand.toFixed(2);
    }

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('qty') || e.target.classList.contains('price')) {
            calculateTotals();
        }
    });

    /* ── Build new item row ── */
    function buildItemRow() {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML = `
        <td class="row-num-cell"></td>
        <td><input type="text" name="description[]" class="line-input" placeholder="Item description" required></td>
        <td><input type="text" name="manufacturer[]" class="line-input" placeholder="Manufacturer"></td>
        <td><input type="text" name="part_number[]" class="line-input" placeholder="Part #"></td>
        <td>
            <select name="unit[]" class="line-input">
                <option value="pcs">pcs</option>
                <option value="box">box</option>
                <option value="set">set</option>
                <option value="kg">kg</option>
                <option value="lot">lot</option>
                <option value="ea">ea</option>
            </select>
        </td>
        <td><input type="number" name="qty[]" class="line-input qty" value="1" min="1"></td>
        <td><input type="number" name="unit_price[]" class="line-input price" step="0.01" placeholder="0.00"></td>
        <td><input type="text" class="line-input total" readonly placeholder="0.00" style="background:#f8fafc;color:#64748b;"></td>
        <td class="text-center">
            <button type="button" class="remove-row-btn removeRow" title="Remove row">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;
        return tr;
    }

    document.getElementById('addRowBtn').addEventListener('click', function() {
        const tbody = document.getElementById('itemsBody');
        const tr = buildItemRow();
        tbody.appendChild(tr);
        reIndex();
        tr.querySelector('input[name="description[]"]').focus();
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.removeRow');
        if (!btn) return;
        const rows = document.querySelectorAll('#itemsBody tr.item-row');
        if (rows.length > 1) {
            btn.closest('tr').remove();
            reIndex();
            calculateTotals();
        } else {
            alert('At least one item is required.');
        }
    });

    /* ── Vendor select → open modal ── */
    document.getElementById('vendorSelect').addEventListener('change', function() {
        if (this.value === 'add_new') {
            new bootstrap.Modal(document.getElementById('addVendorModal')).show();
            this.value = '';
        }
    });

    /* ── Save vendor via AJAX ── */
    function saveVendorAjax() {
        const form = document.getElementById('addVendorForm');
        if (!form.name.value.trim()) {
            alert('Vendor Name is required.');
            return;
        }
        const formData = new FormData(form);
        formData.append('action', 'add_vendor');
        fetch('add_vendor_ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const sel = document.getElementById('vendorSelect');
                    const option = document.createElement('option');
                    option.value = data.id;
                    option.text = data.name;
                    sel.insertBefore(option, sel.lastElementChild);
                    sel.value = data.id;
                    bootstrap.Modal.getInstance(document.getElementById('addVendorModal')).hide();
                    form.reset();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(() => alert('Server error. Please try again.'));
    }

    /* ── Init ── */
    reIndex();
</script>

<?php include("../templates/footer.php"); ?>