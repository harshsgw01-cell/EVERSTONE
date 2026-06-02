<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Account']);

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
    o.rfq_title,
    o.quote_date,
    o.note_to_buyer,
    c.name            AS customer_name,
    bt.title          AS billto_title,
    bt.address        AS billto_address,
    b.name            AS buyer_name,
    b.email           AS buyer_email,
    b.phone           AS buyer_phone,
    b.address         AS buyer_address,
    st.name           AS shipto_name,
    st.company        AS shipto_company,
    st.address        AS shipto_address,
    st.email          AS shipto_email,
    st.phone          AS shipto_phone
FROM order_lines ol
LEFT JOIN orders     o  ON ol.order_id   = o.id
LEFT JOIN customers  c  ON o.customer_id = c.id
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

/* ---------- Company settings ---------- */
$settings = [];
$sr = mysqli_query($conn, "SELECT setting_key, setting_val FROM company_settings");
if ($sr) {
    while ($s = mysqli_fetch_assoc($sr)) $settings[$s['setting_key']] = $s['setting_val'];
}

$terms_text  = $settings['terms_conditions'] ?? 'Payment due within 30 days of invoice date.';
$payment_days = (int)($settings['payment_terms'] ?? 30);

/* ---------- Next invoice number (starts at 1201) ---------- */
$last     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) AS max_id FROM invoices"));
$next_num = 'INV-' . (1201 + (int)($last['max_id'] ?? 0));

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper invoice-preview-page">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-receipt"></i>
                    Invoice Preview
                </div>
                <div class="page-header-sub" style="color: #fff; border-radius: 9px !important;">Review and confirm details before generating PDF</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="order-num-badge">
                    <i class="bi bi-hash"></i><?= htmlspecialchars($next_num) ?>
                </div>
                <?php if (!empty($order['order_number'])): ?>
                <div class="order-num-badge" style="background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.15);color:rgba(255,255,255,.6);">
                    <i class="bi bi-bag"></i><?= htmlspecialchars($order['order_number']) ?>
                </div>
                <?php endif; ?>
                <button type="button" class="back-btn" onclick="history.go(-1);" style="border-radius: 9px !important; background: transparent; border: 1px solid rgba(255,255,255,0.15); color: rgba(255,255,255,.75); padding: 7px 14px; font-size: 0.8rem; font-weight: 600; transition: all .15s;">
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
                    13455 94a Ave #104<br>Surrey, BC V3V 1M9 Canada
                </div>
            </div>
        </div>
        <div class="letterhead-divider d-none d-sm-block"></div>
        <div class="letterhead-contact">
            <a href="https://everstonetech.ca/" target="_blank">everstonetech.ca</a><br>
            Global Customer Support<br>236-953-7860<br>sales@everstonetech.ca
        </div>
    </div>

    <form method="post" action="invoice_generate.php">
        <input type="hidden" name="ids" value="<?= htmlspecialchars(json_encode($ids)) ?>">

        <!-- ══════════ INVOICE DETAILS ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="form-card-header-title">Invoice Details</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" name="invoice_number" class="form-control form-input"
                            value="<?= htmlspecialchars($next_num) ?>">
                        <div class="form-hint">Auto-generated (starts at INV-1201)</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Invoice Date</label>
                        <input type="date" name="invoice_date" class="form-control form-input"
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control form-input"
                            value="<?= date('Y-m-d', strtotime("+{$payment_days} days")) ?>">
                        <div class="form-hint">Net <?= $payment_days ?> days</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Terms (days)</label>
                        <input type="number" name="payment_terms" class="form-control form-input"
                            value="<?= htmlspecialchars($payment_days) ?>">
                    </div>
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
                    <div class="col-12">
                        <label class="form-label">
                            Note to Buyer
                            <span class="label-hint">(printed on invoice)</span>
                        </label>
                        <input type="text" name="note_to_buyer" class="form-control form-input"
                            value="<?= htmlspecialchars($order['note_to_buyer'] ?? '') ?>"
                            placeholder="Optional note printed at the bottom of the invoice">
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
                <div class="row g-3">

                    <!-- Bill To -->
                    <div class="col-md-4">
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
                            <textarea name="billto_address" class="form-control form-input" rows="3"><?= htmlspecialchars($order['billto_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Ship To -->
                    <div class="col-md-4">
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
                        <div>
                            <label class="form-label">Address</label>
                            <textarea name="shipto_address" class="form-control form-input" rows="3"><?= htmlspecialchars($order['shipto_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Buyer -->
                    <div class="col-md-4">
                        <div class="address-section-title ast-buyer">
                            <i class="bi bi-person-fill"></i> Buyer
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
                    Invoice Line Items
                    <span>(<?= count($lines) ?> item<?= count($lines) !== 1 ? 's' : '' ?>)</span>
                </div>
            </div>
            <div class="lines-table-wrap">
                <table class="lines-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:32px;">
                                <input type="checkbox" class="line-check" id="selectAll" title="Select All" checked>
                            </th>
                            <th class="text-center" style="width:38px;">#</th>
                            <th style="min-width:110px;">Part #</th>
                            <th style="min-width:110px;">Manufacturer</th>
                            <th>Description</th>
                            <th style="min-width:75px;">Qty</th>
                            <th style="min-width:120px;">Unit Price</th>
                            <th style="min-width:100px;" class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $i => $line): ?>
                        <tr>
                            <td class="line-check-wrap">
                                <input type="checkbox" name="line_id[]" value="<?= $line['id'] ?>"
                                    class="line-check line-checkbox" checked>
                            </td>
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
                                <input type="number" step="1" min="0" name="qty[]"
                                    class="line-input qty"
                                    value="<?= htmlspecialchars($line['qty']) ?>">
                            </td>
                            <td>
                                <div class="input-prefix-group">
                                    <input type="number" step="1" min="0" name="price[]"
                                        class="price line-input"
                                        value="<?= htmlspecialchars($line['unit_price']) ?>">
                                </div>
                            </td>
                            <td class="line-amount amount">€0.00</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="grand-total-bar">
                <span class="grand-total-label">Total Due</span>
                <span class="grand-total-value">€  <span id="grandTotal">0.00</span></span>
            </div>
        </div>

        <!-- ══════════ BANK & PAYMENT DETAILS — SELECTABLE BANK ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#eff6ff;color:#0369a1;">
                    <i class="bi bi-bank2"></i>
                </div>
                <div class="form-card-header-title">
                    Bank &amp; Payment Details
                    <span style="font-size:.7rem;font-weight:400;color:#94a3b8;margin-left:6px;">(selected bank printed on invoice)</span>
                </div>
            </div>
            <div class="form-card-body">

                <!-- Bank Selection Dropdown -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Select Bank for Payment</label>
                        <select name="selected_bank" id="bankSelector" class="form-control form-input">
                            <option value="chase">JPMorgan Chase Bank (USA)</option>
                            <option value="wise">WISE (International)</option>
                        </select>
                        <div class="form-hint">Choose which bank account details to display on the invoice</div>
                    </div>
                </div>

                <!-- Bank Details Container -->
                <div id="bankDetailsContainer">

                    <!-- ── JPMORGAN CHASE BANK ── -->
                    <div id="chaseBankDetails" class="bank-details-section">
                        <div class="bank-section-title bank-primary-title mb-3">
                            <i class="bi bi-bank"></i> JPMorgan Chase Bank Details
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control form-input bank-field"
                                    value="JPMorgan Chase Bank" data-bank="chase">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Account Name</label>
                                <input type="text" name="bank_account_name" class="form-control form-input bank-field"
                                    value="EVERSTONE TECHNOLOGY SYSTEMS INC." data-bank="chase">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="bank_account_number" class="form-control form-input bank-field"
                                    value="2900355517" data-bank="chase">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Routing Number</label>
                                <input type="text" name="bank_routing" class="form-control form-input bank-field"
                                    value="021000021" data-bank="chase">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SWIFT / BIC</label>
                                <input type="text" name="bank_swift" class="form-control form-input bank-field"
                                    value="CHASUS33" data-bank="chase">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IBAN</label>
                                <input type="text" name="bank_iban" class="form-control form-input bank-field"
                                    placeholder="N/A" data-bank="chase">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bank Address</label>
                                <input type="text" name="bank_address" class="form-control form-input bank-field"
                                    value="13455 94a Ave #104 Surrey, BC V3V 1M9 Canada" data-bank="chase">
                            </div>
                        </div>
                    </div>

                    <!-- ── WISE BANK ── -->
                    <div id="wiseBankDetails" class="bank-details-section" style="display:none;">
                        <div class="bank-section-title bank-secondary-title mb-3">
                            <i class="bi bi-globe"></i> WISE Bank Details
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name_wise" class="form-control form-input bank-field-wise"
                                    value="WISE">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Account Name</label>
                                <input type="text" name="bank_account_name_wise" class="form-control form-input bank-field-wise"
                                    value="EVERSTONE TECHNOLOGY SYSTEMS INC.">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IBAN Number</label>
                                <input type="text" name="bank_iban_wise" class="form-control form-input bank-field-wise"
                                    value="BE93905230080367">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SWIFT / BIC</label>
                                <input type="text" name="bank_swift_wise" class="form-control form-input bank-field-wise"
                                    value="TRWIBEB1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="bank_account_number_wise" class="form-control form-input bank-field-wise"
                                    placeholder="N/A">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Routing Number</label>
                                <input type="text" name="bank_routing_wise" class="form-control form-input bank-field-wise"
                                    placeholder="N/A">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bank Address</label>
                                <input type="text" name="bank_address_wise" class="form-control form-input bank-field-wise"
                                    value="Rue du Trône 100, 3rd floor, Brussels, 1050, Belgium">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Terms -->
                <div class="row g-3 mt-3">
                    <div class="col-12">
                        <label class="form-label">Terms &amp; Conditions</label>
                        <textarea name="terms_conditions" class="form-control form-input" rows="2"><?= htmlspecialchars($terms_text) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ ACTION FOOTER ══════════ -->
        <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
            <div class="action-footer-left">
                <i class="bi bi-shield-check"></i>
                <?= count($lines) ?> line<?= count($lines) !== 1 ? 's' : '' ?> ready for invoice
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="action-btn cancel" onclick="history.go(-1);">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
                <button type="button" class="action-btn save" onclick="submitSelected()">
                    <i class="bi bi-filetype-pdf"></i> Download Invoice PDF
                </button>
            </div>
        </div>

    </form>
</div>

<script>
function recalc() {
    let total = 0;
    document.querySelectorAll("tbody tr").forEach(row => {
        const qtyEl   = row.querySelector(".qty");
        const priceEl = row.querySelector(".price");
        const amtEl   = row.querySelector(".amount");
        if (!qtyEl || !priceEl || !amtEl) return;
        const amt = (parseFloat(qtyEl.value) || 0) * (parseFloat(priceEl.value) || 0);
        amtEl.textContent = "€" + amt.toFixed(2);
        total += amt;
    });
    document.getElementById("grandTotal").textContent = total.toFixed(2);
}
document.addEventListener("input", e => {
    if (e.target.classList.contains("qty") || e.target.classList.contains("price")) recalc();
});

const selectAll  = document.getElementById("selectAll");
const checkboxes = document.querySelectorAll(".line-checkbox");
selectAll.addEventListener("change", function () {
    checkboxes.forEach(cb => cb.checked = this.checked);
});
checkboxes.forEach(cb => cb.addEventListener("change", () => {
    const all     = Array.from(checkboxes);
    const checked = all.filter(c => c.checked).length;
    selectAll.indeterminate = checked > 0 && checked < all.length;
    selectAll.checked = checked === all.length;
}));

function submitSelected() {
    const checked = document.querySelectorAll('input[name="line_id[]"]:checked');
    if (checked.length === 0) { alert('Please select at least one line item.'); return; }
    document.querySelectorAll('input[name="line_id[]"]').forEach(cb => { if (!cb.checked) cb.disabled = true; });
    document.querySelector('form').submit();
}

recalc();

/* ── Bank Selection Toggle ── */
const bankSelector = document.getElementById('bankSelector');
const chaseDetails = document.getElementById('chaseBankDetails');
const wiseDetails = document.getElementById('wiseBankDetails');

function toggleBankDetails() {
    const selected = bankSelector.value;
    if (selected === 'chase') {
        chaseDetails.style.display = 'block';
        wiseDetails.style.display = 'none';
    } else {
        chaseDetails.style.display = 'none';
        wiseDetails.style.display = 'block';
    }
}

bankSelector.addEventListener('change', toggleBankDetails);
toggleBankDetails(); // Initialize on page load
</script>

<?php include("../templates/footer.php"); ?>
