<?php

include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Sales']);

function display_text($row)
{
    foreach ($row as $key => $val) {
        if (!is_numeric($key) && $key !== 'id' && $val !== null && $val !== '') return htmlspecialchars($val);
    }
    return '';
}

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $customer_id        = (int)$_POST['customer_id'];
    $rfq_id             = !empty($_POST['rfq_id']) ? (int)$_POST['rfq_id'] : null;
    $status             = mysqli_real_escape_string($conn, $_POST['status']);
    $salesperson_id     = (int)$_POST['salesperson_id'];
    $billto_id          = (int)$_POST['billto_id'];
    $buyer_id           = (int)$_POST['buyer_id'];
    $shipto_id          = (int)$_POST['shipto_id'];
    $validity           = !empty($_POST['validity']) ? mysqli_real_escape_string($conn, $_POST['validity']) : null;
    $customer_po_number = mysqli_real_escape_string($conn, trim($_POST['customer_po_number'] ?? ''));
    $note_to_buyer      = mysqli_real_escape_string($conn, trim($_POST['note_to_buyer'] ?? ''));
    $currency           = mysqli_real_escape_string($conn, trim($_POST['currency'] ?? 'USD'));
    $lead_time          = mysqli_real_escape_string($conn, trim($_POST['lead_time'] ?? ''));
    $rfq_title          = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
    $rfq_number         = mysqli_real_escape_string($conn, trim($_POST['rfq_number'] ?? ''));
    $quote_date  = date('Y-m-d');
    $created_by  = (int)$_SESSION['user_id'];

    // Generate order code e.g. ORD-2026-0001
    $year = date("Y");
    $lastCode = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT code FROM orders
     WHERE code LIKE 'ORD-$year-%'
     ORDER BY id DESC LIMIT 1"
    ));
    if ($lastCode && preg_match(
        '/ORD-\d{4}-(\d+)$/',
        $lastCode['code'],
        $m
    )) {
        $nextCode = (int)$m[1] + 1;
    } else {
        $nextCode = 1;
    }
    $order_code = "ORD-$year-" . str_pad($nextCode, 4, '0', STR_PAD_LEFT);
    $order_code = mysqli_real_escape_string($conn, $order_code);

    // Get customer name
    $cust_row      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM customers WHERE id = $customer_id LIMIT 1"));
    $customer_name = mysqli_real_escape_string($conn, $cust_row['name'] ?? '');

    // ── INSERT ORDER ──
    $rfq_id_val = $rfq_id ? "'$rfq_id'" : "NULL";
    $validity_val = $validity ? "'$validity'" : "NULL";

    $q = "INSERT INTO orders
        (customer_id, customer_name, rfq_id, status,
         salesPerson_id, billTo_id, buyer_id, shipTo_id,
         validity, customer_po_number, note_to_buyer,
         rfq_title, rfq_number, currency,
         quote_date, created_by, code, lead_time)
          VALUES
    ('$customer_id', '$customer_name', $rfq_id_val, '$status',
     '$salesperson_id', '$billto_id', '$buyer_id', '$shipto_id',
     $validity_val,
     '$customer_po_number', '$note_to_buyer',
     '$rfq_title', '$rfq_number', '$currency',
     '$quote_date', '$created_by', '$order_code', '$lead_time')";

    if (!mysqli_query($conn, $q)) {
        die("Order insert failed: " . mysqli_error($conn));
    }

    $order_id = mysqli_insert_id($conn);

    // ── INSERT ORDER LINES ──
    $products    = $_POST['product']      ?? [];
    $qtys        = $_POST['qty']          ?? [];
    $unit_prices = $_POST['unit_price']   ?? [];
    $cost_prices = $_POST['cost_price']   ?? [];
    $parts       = $_POST['part_number']  ?? [];
    $mfgs        = $_POST['manufacturer'] ?? [];
    $descs       = $_POST['line_description'] ?? [];
    $units       = $_POST['unit']         ?? [];

    $total_cost  = 0;
    $total_price = 0;

    foreach ($products as $i => $product) {
        $product = trim($product);
        if ($product === "") continue;

        // Validate product exists
        $check = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT id FROM products WHERE name = '" . mysqli_real_escape_string($conn, $product) . "' LIMIT 1"
        ));
        if (!$check) {
            // Rollback the order we just inserted
            mysqli_query($conn, "DELETE FROM orders WHERE id = $order_id");
            echo "<script>alert('Invalid product: " . addslashes($product) . ". Please select from the list.'); window.location.href='orders_create.php';</script>";
            exit;
        }

        $qty        = (int)($qtys[$i] ?? 1);
        $unit_price = (float)($unit_prices[$i] ?? 0);
        $cost_price = ($cost_prices[$i] !== "") ? (float)$cost_prices[$i] : $unit_price;
        $part       = mysqli_real_escape_string($conn, trim($parts[$i] ?? ''));
        $mfg        = mysqli_real_escape_string($conn, trim($mfgs[$i] ?? ''));
        $desc       = mysqli_real_escape_string($conn, trim($descs[$i] ?? ''));
        $unit_val   = mysqli_real_escape_string($conn, trim($units[$i] ?? 'Each'));
        $line_total = $qty * $unit_price;

        $total_cost  += $qty * $cost_price;
        $total_price += $line_total;

        $product_escaped = mysqli_real_escape_string($conn, $product);

        $line_q = "INSERT INTO order_lines
            (order_id, product, qty, unit_price,
             cost_price, total_price,
             part_number, manufacturer,
             description, unit)
                   VALUES
                    ('$order_id', '$product_escaped', '$qty', '$unit_price', '$cost_price', '$line_total',
                     '$part', '$mfg', '$desc', '$unit_val')";

        if (!mysqli_query($conn, $line_q)) {
            die("Order line insert failed: " . mysqli_error($conn));
        }
    }

    // ── UPDATE ORDER TOTALS ──
    $stmt = $conn->prepare("UPDATE orders SET total_cost = ?, total_price = ? WHERE id = ?");
    $stmt->bind_param("ddi", $total_cost, $total_price, $order_id);
    $stmt->execute();
    $stmt->close();

    header("Location: order.php");
    exit;
}

function formatRfqNumber($rfq_number, $created_at)
{
    if (preg_match('/^RFQ-\d{4}-/i', $rfq_number)) return $rfq_number;
    $year = date('Y', strtotime($created_at));
    return 'RFQ-' . $year . '-' . $rfq_number;
}

/* ---------- PAGE LOAD ---------- */
$customers   = mysqli_query($conn, "SELECT id,name FROM customers");
// Replace the existing $rfqs query with this:
$rfqs = mysqli_query($conn, "
    SELECT r.id, r.rfq_number, r.rfq_title, r.created_at,
           r.customer_id, r.salesPerson_id, r.billTo_id,
           r.buyer_id, r.shipTo_id, r.customer_name
    FROM rfqs r
    LEFT JOIN orders o ON r.id = o.rfq_id
    WHERE r.status IN ('Approved', 'Open', 'Ready for Review')
      AND o.id IS NULL
    ORDER BY r.id DESC
");
$salespeople = mysqli_query($conn, "SELECT * FROM salesperson");
$billtos     = mysqli_query($conn, "SELECT * FROM billto");
$buyers      = mysqli_query($conn, "SELECT * FROM buyer");
$shiptos     = mysqli_query($conn, "SELECT * FROM shipto");

$year = date("Y");
$lastOrder = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT customer_po_number FROM orders
    WHERE customer_po_number LIKE 'PO-$year-%'
    ORDER BY id DESC LIMIT 1
"));

if ($lastOrder && preg_match('/PO-\d{4}-(\d+)$/', $lastOrder['customer_po_number'], $m)) {
    $nextNum = (int)$m[1] + 1;
} else {
    $nextNum = 1;
}
$poNumber = "PO-$year-" . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

include("../templates/header.php");
include("../templates/navbar.php");
?>
<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-bag-plus-fill"></i>
                    Create New Order
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

    <form method="post" id="orderForm">

        <!-- ══════════ ORDER DETAILS ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#fef3c7;color:#d97706;">
                    <i class="bi bi-info-circle-fill"></i>
                </div>
                <div class="form-card-header-title">Order Details</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="customer_id" required>
                            <option value="">Select Customer</option>
                            <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Customer PO Number</label>
                        <input type="text" name="customer_po_number" class="form-control form-input"
                            value="<?= htmlspecialchars($poNumber) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-input"
                            placeholder="Order title" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Currency <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="currency" id="currencySelect">
                            <option value="EUR" selected>EUR — Euro (€)</option>
                            <option value="USD">USD — US Dollar ($)</option>
                            <option value="GBP">GBP — British Pound (£)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Linked RFQ <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;">(optional)</span></label>
                        <select class="form-control form-input" name="rfq_id" id="rfqSelect">
                            <option value="">None</option>
                            <?php while ($r = mysqli_fetch_assoc($rfqs)):
                                $rfqNum = htmlspecialchars(formatRfqNumber($r['rfq_number'], $r['created_at']));
                            ?>
                                <option value="<?= $r['id'] ?>"
                                    data-rfq-number="<?= $rfqNum ?>"
                                    data-customer-id="<?= $r['customer_id'] ?>"
                                    data-salesperson-id="<?= $r['salesPerson_id'] ?>"
                                    data-billto-id="<?= $r['billTo_id'] ?>"
                                    data-buyer-id="<?= $r['buyer_id'] ?>"
                                    data-shipto-id="<?= $r['shipTo_id'] ?>">
                                    <?= $rfqNum ?> — <?= htmlspecialchars($r['rfq_title']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="hidden" name="rfq_number" id="rfqNumberHidden" value="">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-control form-input" name="status">
                            <option>Pending</option>
                            <option>In Progress</option>
                            <option>Delivered</option>
                            <option>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sales Person <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="salesperson_id" required>
                            <option value="">Select Sales Person</option>
                            <?php while ($s = mysqli_fetch_assoc($salespeople)): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ PARTIES ══════════ -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="form-card-header-title">Parties &amp; Addresses</div>
            </div>
            <div class="form-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Bill To</label>
                        <select class="form-control form-input" name="billto_id">
                            <option value="">Select Billing Address</option>
                            <?php while ($bt = mysqli_fetch_assoc($billtos)): ?>
                                <option value="<?= $bt['id'] ?>"><?= htmlspecialchars($bt['title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ship To</label>
                        <select class="form-control form-input" name="shipto_id">
                            <option value="">Select Shipping Address</option>
                            <?php while ($st = mysqli_fetch_assoc($shiptos)): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Buyer</label>
                        <select class="form-control form-input" name="buyer_id">
                            <option value="">Select Buyer</option>
                            <?php while ($b = mysqli_fetch_assoc($buyers)): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Validity</label>
                        <input type="text" name="validity"
                            class="form-control form-input"
                            placeholder="e.g. 30 days, 2 months">
                        <!-- Also add lead_time field anywhere in Parties card -->
                        <input type="text" name="lead_time"
                            class="form-control form-input"
                            placeholder="e.g. 45 days">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Note to Buyer</label>
                        <input type="text" name="note_to_buyer" class="form-control form-input"
                            placeholder="Optional note printed on invoice and packing slip">
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ ORDER LINES ══════════ -->
        <div class="rfq-lines-card">
            <div class="rfq-lines-header">
                <div class="rfq-lines-title">
                    <i class="bi bi-list-ul"></i>
                    Order Lines
                </div>
                <button type="button" class="add-row-btn" id="addLineBtn">
                    <i class="bi bi-plus-lg"></i>Add Line
                </button>
            </div>

            <div class="lines-table-wrap" style="min-height: 150px;">
                <table class="lines-table" id="linesTable">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th style="min-width:180px;">Product <span class="text-danger">*</span></th>
                            <th style="min-width:110px;">Part Number</th>
                            <th style="min-width:110px;">Manufacturer</th>
                            <th style="min-width:160px;">Description</th>
                            <th style="min-width:65px;">Qty</th>
                            <th style="min-width:110px;">Unit Price</th>
                            <th style="min-width:110px;">Cost Price</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="lines">
                        <tr class="line">
                            <td class="row-num-cell">1</td>
                            <td style="position:relative;">
                                <input type="text" name="product[]" class="line-input productSearch"
                                    placeholder="Type product…" autocomplete="off" required>
                                <div class="productDropdown"></div>
                            </td>
                            <td><input type="text" name="part_number[]" class="line-input" placeholder="Part #"></td>
                            <td><input type="text" name="manufacturer[]" class="line-input" placeholder="Manufacturer"></td>
                            <td><input type="text" name="line_description[]" class="line-input" placeholder="Description"></td>
                            <td><input type="number" min="0" value="1" class="line-input" name="qty[]" required></td>
                            <td><input type="number" min="0" step="0.01" class="line-input unit_price"
                                    name="unit_price[]" placeholder="0.00" required></td>
                            <td><input type="number" min="0" step="0.01" class="line-input cost_price"
                                    name="cost_price[]" placeholder="0.00"></td>
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
        <div class="action-footer">
            <div class="action-footer-left">
                <i class="bi bi-info-circle"></i>
                All fields marked <span class="text-danger fw-bold ms-1">*</span> are required
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="action-btn cancel" onclick="history.go(-1);">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <button type="submit" class="action-btn save">
                    <i class="bi bi-floppy-fill"></i> Save Order
                </button>
            </div>
        </div>

    </form>
</div><!-- /page-wrapper -->

<?php include("add-product-model.php"); ?>

<script>
    /* ── Track which line triggered the modal ── */
    let _activeProductLine = null;

    /* ── Row numbering ── */
    function reIndex() {
        document.querySelectorAll('#lines tr.line').forEach((tr, i) => {
            const cell = tr.querySelector('.row-num-cell');
            if (cell) cell.textContent = i + 1;
        });
    }

    /* ── Product autocomplete ── */
    function initProductAutocomplete(line) {
        const input = line.querySelector('.productSearch');
        const dropdown = line.querySelector('.productDropdown');
        const modal = document.getElementById('newProductModal');
        let timeout = null;

        function placeDropdown() {
            const rect = input.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.top = (rect.bottom + 2) + 'px';
            dropdown.style.width = rect.width + 'px';
            dropdown.style.zIndex = '1055';
        }

        if (dropdown.parentElement !== document.body) {
            document.body.appendChild(dropdown);
        }

        function renderItems(data) {
            dropdown.innerHTML = '';

            /* ── Always show Add New Product at top ── */
            const addA = document.createElement('a');
            addA.href = '#';
            addA.className = 'add-new';
            addA.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add New Product';
            addA.addEventListener('click', e => {
                e.preventDefault();
                _activeProductLine = line; /* remember which line opened the modal */
                dropdown.style.display = 'none';
                new bootstrap.Modal(modal).show();
            });
            dropdown.appendChild(addA);

            if (data.length === 0) {
                const empty = document.createElement('div');
                empty.style.cssText = 'padding:8px 12px;font-size:.78rem;color:#94a3b8;border-top:1px solid #f1f5f9;';
                empty.textContent = 'No products found';
                dropdown.appendChild(empty);
            }

            data.forEach(p => {
                const a = document.createElement('a');
                a.href = '#';
                a.textContent = p.name;
                a.addEventListener('click', e => {
                    e.preventDefault();
                    input.value = p.name;
                    line.querySelector('.unit_price').value = p.sales_price || '';
                    line.querySelector('.cost_price').value = p.cost_price || '';
                    line.querySelector('input[name="part_number[]"]').value = p.part_number || '';
                    line.querySelector('input[name="manufacturer[]"]').value = p.manufacturer || '';
                    line.querySelector('input[name="line_description[]"]').value = p.description || '';
                    dropdown.style.display = 'none';
                    calculateTotals();
                });
                dropdown.appendChild(a);
            });

            placeDropdown();
            dropdown.style.display = 'block';
        }

        /* ── Show dropdown on FOCUS ── */
        input.addEventListener('focus', () => {
            _activeProductLine = line;
            fetch('search-products.php?term=' + encodeURIComponent(input.value.trim()))
                .then(r => r.json())
                .then(renderItems);
        });

        /* ── Filter while typing ── */
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                fetch('search-products.php?term=' + encodeURIComponent(this.value.trim()))
                    .then(r => r.json())
                    .then(renderItems);
            }, 250);
        });

        /* ── Close when clicking outside ── */
        document.addEventListener('click', e => {
            if (!line.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
        });

        window.addEventListener('scroll', () => {
            if (dropdown.style.display === 'block') placeDropdown();
        }, true);
        window.addEventListener('resize', () => {
            if (dropdown.style.display === 'block') placeDropdown();
        });
    }

    /* ── Build a new line row ── */
    function buildLineRow() {
        const tr = document.createElement('tr');
        tr.className = 'line';
        tr.innerHTML = `
        <td class="row-num-cell"></td>
        <td style="position:relative;">
            <input type="text" name="product[]" class="line-input productSearch" placeholder="Type product…" autocomplete="off" required>
            <div class="productDropdown"></div>
        </td>
        <td><input type="text" name="part_number[]" class="line-input" placeholder="Part #"></td>
        <td><input type="text" name="manufacturer[]" class="line-input" placeholder="Manufacturer"></td>
        <td><input type="text" name="line_description[]" class="line-input" placeholder="Description"></td>
        <td><input type="number" min="0" value="1" class="line-input" name="qty[]" required></td>
        <td><input type="number" min="0" step="0.01" class="line-input unit_price" name="unit_price[]" placeholder="0.00" required></td>
        <td><input type="number" min="0" step="0.01" class="line-input cost_price" name="cost_price[]" placeholder="0.00"></td>
        <td class="text-center">
            <button type="button" class="remove-row-btn removeRow" title="Remove">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;
        return tr;
    }

    document.getElementById('addLineBtn').addEventListener('click', function() {
        const tbody = document.getElementById('lines');
        const tr = buildLineRow();
        tbody.appendChild(tr);
        initProductAutocomplete(tr);
        reIndex();
        tr.querySelector('.productSearch').focus();
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.removeRow');
        if (!btn) return;
        const rows = document.querySelectorAll('#lines tr.line');
        if (rows.length > 1) {
            btn.closest('tr').remove();
            reIndex();
            calculateTotals();
        } else {
            alert('At least one order line is required.');
        }
    });

    /* ── Totals ── */
    const currencySymbols = {
        EUR: '€',
        USD: '$',
        GBP: '£'
    };

    document.getElementById('currencySelect').addEventListener('change', function() {
        document.getElementById('currencySymbol').textContent = currencySymbols[this.value] || '€';
        calculateTotals();
    });

    function calculateTotals() {
        let grand = 0;
        document.querySelectorAll('#lines tr.line').forEach(row => {
            const qty = parseFloat(row.querySelector("input[name='qty[]']")?.value) || 0;
            const price = parseFloat(row.querySelector('.unit_price')?.value) || 0;
            grand += qty * price;
        });
        document.getElementById('grandTotal').textContent = grand.toFixed(2);
    }

    document.addEventListener('input', function(e) {
        if (e.target.name === 'qty[]' || e.target.classList.contains('unit_price')) calculateTotals();
    });

    /* ── RFQ number fill ── */
    document.getElementById('rfqSelect').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];

        // Always sync the hidden rfq_number field
        document.getElementById('rfqNumberHidden').value = opt.dataset.rfqNumber || '';

        // If a real RFQ is selected, auto-fill the form fields
        if (opt.value) {
            const customerId = opt.dataset.customerId;
            const salespersonId = opt.dataset.salespersonId;
            const billtoId = opt.dataset.billtoId;
            const buyerId = opt.dataset.buyerId;
            const shiptoId = opt.dataset.shiptoId;

            if (customerId) document.querySelector('select[name="customer_id"]').value = customerId;
            if (salespersonId) document.querySelector('select[name="salesperson_id"]').value = salespersonId;
            if (billtoId) document.querySelector('select[name="billto_id"]').value = billtoId;
            if (buyerId) document.querySelector('select[name="buyer_id"]').value = buyerId;
            if (shiptoId) document.querySelector('select[name="shipto_id"]').value = shiptoId;
        }
    });

    /* ── New product modal submit ── */
    document.getElementById('newProductForm')?.addEventListener('submit', function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        const msgDiv = document.getElementById('newProductMsg');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving…';
        msgDiv.style.display = 'none';

        fetch('save-product.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Save Product';

                if (data.success) {
                    /* Fill the line that triggered the modal */
                    const targetLine = _activeProductLine ||
                        document.querySelector('#lines tr.line:last-child');

                    if (targetLine) {
                        targetLine.querySelector('input[name="product[]"]').value = data.name;
                        targetLine.querySelector('input[name="part_number[]"]').value = data.part_number || '';
                        targetLine.querySelector('input[name="manufacturer[]"]').value = data.manufacturer || '';
                        targetLine.querySelector('input[name="line_description[]"]').value = data.description || '';
                        targetLine.querySelector('.unit_price').value = data.sales_price || '';
                        targetLine.querySelector('.cost_price').value = data.cost_price || '';
                    }

                    bootstrap.Modal.getInstance(document.getElementById('newProductModal')).hide();
                    this.reset();
                    _activeProductLine = null;
                    calculateTotals();
                } else {
                    msgDiv.style.display = 'block';
                    msgDiv.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:8px 12px;font-size:.8rem;"><i class="bi bi-exclamation-circle me-1"></i>' + (data.message || 'Error saving product') + '</div>';
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Save Product';
                msgDiv.style.display = 'block';
                msgDiv.innerHTML = '<div style="background:#fee2e2;color:#991b1b;border-radius:8px;padding:8px 12px;font-size:.8rem;"><i class="bi bi-exclamation-circle me-1"></i>Server error. Please try again.</div>';
            });
    });

    /* ── Init first row ── */
    document.querySelectorAll('.line').forEach(initProductAutocomplete);
    reIndex();
</script>

<?php include("../templates/footer.php"); ?>
