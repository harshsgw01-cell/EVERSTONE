<?php
session_start();
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin', 'Account']);

$form_error = '';

function next_order_code(mysqli $conn): string
{
    $year = date("Y");
    $lastCode = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT code FROM orders WHERE code LIKE 'ORD-$year-%' ORDER BY id DESC LIMIT 1"
    ));

    if ($lastCode && preg_match('/ORD-\d{4}-(\d+)$/', $lastCode['code'], $m)) {
        $nextCode = (int)$m[1] + 1;
    } else {
        $nextCode = 1;
    }

    return "ORD-$year-" . str_pad($nextCode, 4, '0', STR_PAD_LEFT);
}

function order_total(mysqli $conn, int $order_id): float
{
    $total_row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT IFNULL(SUM(qty * unit_price), 0) AS total FROM order_lines WHERE order_id = $order_id"
    ));

    return (float)($total_row['total'] ?? 0);
}

function order_line_ids(mysqli $conn, int $order_id): string
{
    $ids = [];
    $result = mysqli_query($conn, "SELECT id FROM order_lines WHERE order_id = $order_id ORDER BY id ASC");

    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = (int)$row['id'];
    }

    return implode(',', $ids);
}

/* ---------- SAVE ---------- */
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $use_custom   = isset($_POST['use_custom']) ? 1 : 0;
    $customer_id  = (int)($_POST['customer_id'] ?? 0);
    $billto_id    = (int)($_POST['billto_id']   ?? 0);
    $shipto_id    = (int)($_POST['shipto_id']   ?? 0);
    $po_number    = trim($_POST['po_number']    ?? '');
    $order_id     = (int)($_POST['order_id']    ?? 0);
    $status       = $_POST['status']            ?? 'Draft';
    $due_date     = $_POST['due_date']          ?? null;
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $created_by   = (int)($_SESSION['user_id'] ?? 0);
    $line_ids     = '';

    if ($customer_id <= 0) {
        $form_error = "Please select a valid customer.";
    } elseif ($billto_id <= 0) {
        $form_error = "Please select a valid bill-to address.";
    } elseif ($shipto_id <= 0) {
        $form_error = "Please select a valid ship-to address.";
    } elseif (!$use_custom && $order_id <= 0) {
        $form_error = "Please select a valid order before saving the invoice.";
    } elseif (!$use_custom) {
        $order_check = mysqli_prepare($conn, "SELECT id FROM orders WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($order_check, "i", $order_id);
        mysqli_stmt_execute($order_check);
        mysqli_stmt_store_result($order_check);

        if (mysqli_stmt_num_rows($order_check) === 0) {
            $form_error = "The selected order no longer exists. Please choose another order.";
        }
        mysqli_stmt_close($order_check);
    }

    if ($form_error === '') {
        mysqli_begin_transaction($conn);

        try {
            if ($use_custom) {
                $products      = $_POST['product']      ?? [];
                $parts         = $_POST['part_no']      ?? [];
                $manufacturers = $_POST['manufacturer'] ?? [];
                $descriptions  = $_POST['description']  ?? [];
                $qtys          = $_POST['qty']          ?? [];
                $prices        = $_POST['price']        ?? [];
                $custom_line_ids = [];
                $line_total    = 0;
                $has_line      = false;

                $customer = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT name FROM customers WHERE id = $customer_id LIMIT 1"
                ));
                $customer_name = $customer['name'] ?? '';
                $order_code    = next_order_code($conn);
                $quote_date    = date('Y-m-d');
                $order_status  = 'Pending';
                $currency      = 'USD';
                $rfq_title     = 'Custom Invoice';

                $order_stmt = mysqli_prepare($conn, "
                    INSERT INTO orders
                    (customer_id, customer_name, status, billTo_id, shipTo_id, customer_po_number,
                     rfq_title, currency, quote_date, created_by, code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param(
                    $order_stmt,
                    "issiissssis",
                    $customer_id,
                    $customer_name,
                    $order_status,
                    $billto_id,
                    $shipto_id,
                    $po_number,
                    $rfq_title,
                    $currency,
                    $quote_date,
                    $created_by,
                    $order_code
                );
                mysqli_stmt_execute($order_stmt);
                $order_id = mysqli_insert_id($conn);
                mysqli_stmt_close($order_stmt);

                $line_stmt = mysqli_prepare($conn, "
                    INSERT INTO order_lines
                    (order_id, product, part_number, manufacturer, description, qty, unit_price, total_price, unit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($products as $i => $product) {
                    $product      = trim((string)$product);
                    $part         = trim((string)($parts[$i] ?? ''));
                    $manufacturer = trim((string)($manufacturers[$i] ?? ''));
                    $description  = trim((string)($descriptions[$i] ?? ''));
                    $qty          = max(1, (int)($qtys[$i] ?? 1));
                    $price        = max(0, (float)($prices[$i] ?? 0));

                    if ($product === '' && $description === '') {
                        continue;
                    }

                    $has_line = true;
                    $amount   = $qty * $price;
                    $unit     = 'Each';
                    $line_total += $amount;

                    mysqli_stmt_bind_param(
                        $line_stmt,
                        "issssidds",
                        $order_id,
                        $product,
                        $part,
                        $manufacturer,
                        $description,
                        $qty,
                        $price,
                        $amount,
                        $unit
                    );
                    mysqli_stmt_execute($line_stmt);
                    $custom_line_ids[] = mysqli_insert_id($conn);
                }
                mysqli_stmt_close($line_stmt);

                if (!$has_line) {
                    throw new RuntimeException("Please add at least one custom line item.");
                }

                $total_amount = $line_total;
                $line_ids     = implode(',', $custom_line_ids);
                $total_cost   = 0.0;
                $total_stmt = mysqli_prepare($conn, "UPDATE orders SET total_cost = ?, total_price = ? WHERE id = ?");
                mysqli_stmt_bind_param($total_stmt, "ddi", $total_cost, $total_amount, $order_id);
                mysqli_stmt_execute($total_stmt);
                mysqli_stmt_close($total_stmt);
            } else {
                if ($po_number === '') {
                    $order = mysqli_fetch_assoc(mysqli_query(
                        $conn,
                        "SELECT customer_po_number FROM orders WHERE id = $order_id LIMIT 1"
                    ));
                    $po_number = $order['customer_po_number'] ?? '';
                }

                $line_ids = order_line_ids($conn, $order_id);

                if ($total_amount <= 0) {
                    $total_amount = order_total($conn, $order_id);
                }
            }

            $stmt = mysqli_prepare($conn, "
                INSERT INTO invoices
                (order_id, customer_id, billto_id, shipto_id, po_number, status, total_amount, due_date, use_custom_lines, line_ids, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param(
                $stmt,
                "iiiissdsisi",
                $order_id,
                $customer_id,
                $billto_id,
                $shipto_id,
                $po_number,
                $status,
                $total_amount,
                $due_date,
                $use_custom,
                $line_ids,
                $created_by
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            mysqli_commit($conn);

            header("Location: invoices.php");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $form_error = $e->getMessage();
        }
    }
}

/* ---------- DROPDOWNS ---------- */
$orders    = mysqli_query($conn, "SELECT o.id, o.code, o.customer_po_number FROM orders o ORDER BY o.id DESC");
$customers = mysqli_query($conn, "SELECT id, name FROM customers");
$billtos   = mysqli_query($conn, "SELECT id, title FROM billto");
$shiptos   = mysqli_query($conn, "SELECT id, name FROM shipto");

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-receipt"></i>
                    Create Invoice
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
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

    <form method="post" id="invoiceForm">
        <?php if ($form_error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= htmlspecialchars($form_error) ?></span>
            </div>
        <?php endif; ?>

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

                    <!-- Mode toggle -->
                    <div class="col-12">
                        <label class="mode-toggle-wrap" for="useCustom">
                            <input type="checkbox" id="useCustom" name="use_custom">
                            <span class="mode-toggle-label">
                                <i class="bi bi-pencil-square me-1"></i>
                                Use custom line items instead of linking to an order
                            </span>
                        </label>
                    </div>

                    <!-- Order (order mode) -->
                    <div class="col-md-6" id="orderMode">
                        <label class="form-label">Linked Order <span class="text-danger">*</span></label>
                        <select class="form-control form-input" name="order_id" id="orderSelect" required>
                            <option value="">Select Order</option>
                            <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                                <option value="<?= $o['id'] ?>"
                                    data-po-number="<?= htmlspecialchars($o['customer_po_number'] ?? '') ?>">
                                    <?= htmlspecialchars($o['code']) ?>
                                    <?= !empty($o['customer_po_number']) ? ' — ' . htmlspecialchars($o['customer_po_number']) : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">PO Reference</label>
                        <input type="text" name="po_number" id="poNumberInput"
                            class="form-control form-input"
                            placeholder="Customer PO #">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-control form-input" name="status">
                            <option>Draft</option>
                            <option>Sent</option>
                            <option>Paid</option>
                            <option>Overdue</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control form-input" required>
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
                    <!-- Customer -->
                    <div class="col-md-4">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <div class="input-add-group">
                            <select class="form-control form-input" name="customer_id" id="customerSelect" required>
                                <option value="">Select Customer</option>
                                <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="add-inline-btn"
                                data-bs-toggle="modal" data-bs-target="#addCustomerModal"
                                title="Add new customer">+</button>
                        </div>
                    </div>
                    <!-- Bill To -->
                    <div class="col-md-4">
                        <label class="form-label">Bill To <span class="text-danger">*</span></label>
                        <div class="input-add-group">
                            <select class="form-control form-input" name="billto_id" id="billtoSelect" required>
                                <option value="">Select Bill To</option>
                                <?php while ($b = mysqli_fetch_assoc($billtos)): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="add-inline-btn"
                                data-bs-toggle="modal" data-bs-target="#addBillToModal"
                                title="Add new bill-to">+</button>
                        </div>
                    </div>
                    <!-- Ship To -->
                    <div class="col-md-4">
                        <label class="form-label">Ship To <span class="text-danger">*</span></label>
                        <div class="input-add-group">
                            <select class="form-control form-input" name="shipto_id" id="shiptoSelect" required>
                                <option value="">Select Ship To</option>
                                <?php while ($s = mysqli_fetch_assoc($shiptos)): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="button" class="add-inline-btn"
                                data-bs-toggle="modal" data-bs-target="#addShipToModal"
                                title="Add new ship-to">+</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ CUSTOM LINE ITEMS ══════════ -->
        <div id="customMode" style="display:none;">
            <div class="rfq-lines-card">
                <div class="rfq-lines-header">
                    <div class="rfq-lines-title">
                        <i class="bi bi-list-ul"></i>
                        Custom Line Items
                    </div>
                    <button type="button" class="add-row-btn" id="addLine">
                        <i class="bi bi-plus-lg"></i>Add Line
                    </button>
                </div>

                <div class="lines-table-wrap">
                    <table class="lines-table" style="min-height:150px;">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th style="min-width:130px;">Product</th>
                                <th style="min-width:110px;">Part #</th>
                                <th style="min-width:110px;">Manufacturer</th>
                                <th style="min-width:160px;">Description</th>
                                <th style="min-width:65px;">Qty</th>
                                <th style="min-width:110px;">Unit Price</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="lineItems">
                            <tr class="line-item">
                                <td class="row-num-cell">1</td>
                                <td><input type="text"   name="product[]"      class="line-input" placeholder="Product"></td>
                                <td><input type="text"   name="part_no[]"      class="line-input" placeholder="Part #"></td>
                                <td><input type="text"   name="manufacturer[]" class="line-input" placeholder="Manufacturer"></td>
                                <td><input type="text"   name="description[]"  class="line-input" placeholder="Description"></td>
                                <td><input type="number" name="qty[]"          class="line-input qty"   value="1" min="1"></td>
                                <td><input type="number" name="price[]"        class="line-input price" value="0" min="0" step="0.01"></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grand-total-bar">
                    <span class="grand-total-label">Total Amount</span>
                    <span class="grand-total-value">$<span id="grandTotalDisplay">0.00</span></span>
                </div>
            </div>
        </div>

        <!-- Hidden total field always submitted -->
        <input type="hidden" name="total_amount" id="total_amount" value="">

        <!-- ══════════ ACTION FOOTER ══════════ -->
        <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
            <div class="action-footer-left">
                <i class="bi bi-info-circle"></i>
                Fields marked <span class="text-danger fw-bold ms-1">*</span> are required
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="action-btn cancel" onclick="history.go(-1);">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <button type="submit" class="action-btn save">
                    <i class="bi bi-floppy-fill"></i> Save Invoice
                </button>
            </div>
        </div>

    </form>
</div><!-- /page-wrapper -->


<!-- ══════════════════════════════════════════════════════════
     MODAL 1 — ADD CUSTOMER  (matches customers.php exactly)
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-person-plus-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New Customer</h5>
                        <small class="text-white opacity-75">Fill in the customer details below</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
                        <input type="text" id="cust_name" class="form-control modal-input"
                            placeholder="e.g. John Smith">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Position</div>
                        <input type="text" id="cust_title" class="form-control modal-input"
                            placeholder="e.g. Procurement Manager">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email <span class="text-danger">*</span></div>
                        <input type="email" id="cust_email" class="form-control modal-input"
                            placeholder="e.g. john@company.com">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" id="cust_phone" class="form-control modal-input"
                            placeholder="e.g. +1 555 000 0000">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Address</div>
                        <textarea id="cust_address" rows="2"
                            class="form-control modal-input"
                            placeholder="Street, City, State, ZIP"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                    data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" onclick="saveCustomer()"
                    class="btn btn-sm px-4"
                    style="background:#0ea5e9;color:#fff;border-color:#0284c7;">
                    <i class="bi bi-person-plus me-1"></i>Save Customer
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL 2 — ADD BILL TO  (matches billTo.php exactly)
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addBillToModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#d97706,#b45309);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-receipt fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New Bill To</h5>
                        <small class="text-white opacity-75">Enter billing recipient details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="modal-label">Title <span class="text-danger">*</span></div>
                        <input type="text" id="bt_title" class="form-control modal-input"
                            placeholder="e.g. ABC Corporation">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Address <span class="text-danger">*</span></div>
                        <textarea id="bt_address" rows="4"
                            class="form-control modal-input"
                            placeholder="Street, City, State, ZIP, Country"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                    data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" onclick="saveBillTo()"
                    class="btn btn-sm px-4"
                    style="background:#d97706;color:#fff;border-color:#b45309;">
                    <i class="bi bi-plus-circle me-1"></i>Save Bill To
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL 3 — ADD SHIP TO  (matches shipTo.php exactly)
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addShipToModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-truck fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New Ship To</h5>
                        <small class="text-white opacity-75">Enter the shipping destination details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
                        <input type="text" id="st_name" class="form-control modal-input"
                            placeholder="e.g. John Smith">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Position</div>
                        <input type="text" id="st_title" class="form-control modal-input"
                            placeholder="e.g. Warehouse Manager">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Company <span class="text-danger">*</span></div>
                        <input type="text" id="st_company" class="form-control modal-input"
                            placeholder="e.g. Acme Logistics">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email</div>
                        <input type="email" id="st_email" class="form-control modal-input"
                            placeholder="e.g. john@company.com">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" id="st_phone" class="form-control modal-input"
                            placeholder="e.g. +1 555 000 0000">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Address <span class="text-danger">*</span></div>
                        <textarea id="st_address" rows="2"
                            class="form-control modal-input"
                            placeholder="Street, City, State, ZIP, Country"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                    data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="button" onclick="saveShipTo()"
                    class="btn btn-sm px-4"
                    style="background:#4f46e5;color:#fff;border-color:#4338ca;">
                    <i class="bi bi-plus-circle me-1"></i>Save Ship To
                </button>
            </div>
        </div>
    </div>
</div>


<script>
/* ══════════════════════════════════════════
   MODE TOGGLE
══════════════════════════════════════════ */
const toggle       = document.getElementById('useCustom');
const customMode   = document.getElementById('customMode');
const orderMode    = document.getElementById('orderMode');
const orderSelect  = document.getElementById('orderSelect');
const poNumberInput = document.getElementById('poNumberInput');
const totalField   = document.getElementById('total_amount');
const totalDisplay = document.getElementById('grandTotalDisplay');

function syncPoFromOrder() {
    const selected = orderSelect.options[orderSelect.selectedIndex];
    if (!selected) return;

    poNumberInput.value = selected.dataset.poNumber || '';
}

function updateMode() {
    if (toggle.checked) {
        customMode.style.display = 'block';
        orderMode.style.display  = 'none';
        orderSelect.required = false;
        orderSelect.value = '';
        recalcTotal();
    } else {
        customMode.style.display = 'none';
        orderMode.style.display  = 'block';
        orderSelect.required = true;
        totalField.value = '';
        syncPoFromOrder();
    }
}
toggle.addEventListener('change', updateMode);
orderSelect.addEventListener('change', syncPoFromOrder);
updateMode();

/* ══════════════════════════════════════════
   LINE ITEMS
══════════════════════════════════════════ */
function reIndex() {
    document.querySelectorAll('#lineItems tr.line-item').forEach((tr, i) => {
        const c = tr.querySelector('.row-num-cell');
        if (c) c.textContent = i + 1;
    });
}

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.line-item').forEach(row => {
        const qty   = parseFloat(row.querySelector('.qty')?.value)   || 0;
        const price = parseFloat(row.querySelector('.price')?.value) || 0;
        total += qty * price;
    });
    totalDisplay.textContent = total.toFixed(2);
    totalField.value = total.toFixed(2);
}

document.getElementById('addLine').addEventListener('click', () => {
    const tbody  = document.getElementById('lineItems');
    const newRow = document.createElement('tr');
    newRow.className = 'line-item';
    newRow.innerHTML = `
        <td class="row-num-cell"></td>
        <td><input type="text"   name="product[]"      class="line-input" placeholder="Product"></td>
        <td><input type="text"   name="part_no[]"      class="line-input" placeholder="Part #"></td>
        <td><input type="text"   name="manufacturer[]" class="line-input" placeholder="Manufacturer"></td>
        <td><input type="text"   name="description[]"  class="line-input" placeholder="Description"></td>
        <td><input type="number" name="qty[]"          class="line-input qty"   value="1" min="1"></td>
        <td><input type="number" name="price[]"        class="line-input price" value="0" min="0" step="0.01"></td>
        <td class="text-center">
            <button type="button" class="remove-row-btn remove-line" title="Remove">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;
    tbody.appendChild(newRow);
    reIndex();
});

document.addEventListener('click', e => {
    if (!e.target.closest('.remove-line')) return;
    const rows = document.querySelectorAll('#lineItems tr.line-item');
    if (rows.length > 1) {
        e.target.closest('tr').remove();
        reIndex();
        recalcTotal();
    } else {
        alert('At least one line item is required.');
    }
});

document.addEventListener('input', e => {
    if (e.target.classList.contains('qty') || e.target.classList.contains('price')) recalcTotal();
});

/* ══════════════════════════════════════════
   HELPER — clear fields
══════════════════════════════════════════ */
function clearFields(ids) {
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

function addOptionToSelect(selectId, id, text) {
    const select = document.getElementById(selectId);
    const opt    = new Option(text, id, true, true);
    select.add(opt);
}

/* ══════════════════════════════════════════
   SAVE CUSTOMER
══════════════════════════════════════════ */
function saveCustomer() {
    const name    = document.getElementById('cust_name').value.trim();
    const title   = document.getElementById('cust_title').value.trim();
    const email   = document.getElementById('cust_email').value.trim();
    const phone   = document.getElementById('cust_phone').value.trim();
    const address = document.getElementById('cust_address').value.trim();

    if (!name)  { alert('Full Name is required.'); return; }
    if (!email) { alert('Email is required.'); return; }

    fetch('ajax_add_dropdown.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ type: 'customer', name, title, email, phone, address })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }
        addOptionToSelect('customerSelect', data.id, data.text);
        clearFields(['cust_name','cust_title','cust_email','cust_phone','cust_address']);
        bootstrap.Modal.getInstance(document.getElementById('addCustomerModal')).hide();
    })
    .catch(() => alert('Failed to save customer. Please try again.'));
}

/* ══════════════════════════════════════════
   SAVE BILL TO
══════════════════════════════════════════ */
function saveBillTo() {
    const title   = document.getElementById('bt_title').value.trim();
    const address = document.getElementById('bt_address').value.trim();

    if (!title)   { alert('Title is required.'); return; }
    if (!address) { alert('Address is required.'); return; }

    fetch('ajax_add_dropdown.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ type: 'billto', title, address })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }
        addOptionToSelect('billtoSelect', data.id, data.text);
        clearFields(['bt_title','bt_address']);
        bootstrap.Modal.getInstance(document.getElementById('addBillToModal')).hide();
    })
    .catch(() => alert('Failed to save Bill To. Please try again.'));
}

/* ══════════════════════════════════════════
   SAVE SHIP TO
══════════════════════════════════════════ */
function saveShipTo() {
    const name    = document.getElementById('st_name').value.trim();
    const title   = document.getElementById('st_title').value.trim();
    const company = document.getElementById('st_company').value.trim();
    const email   = document.getElementById('st_email').value.trim();
    const phone   = document.getElementById('st_phone').value.trim();
    const address = document.getElementById('st_address').value.trim();

    if (!name)    { alert('Full Name is required.'); return; }
    if (!company) { alert('Company is required.'); return; }
    if (!address) { alert('Address is required.'); return; }

    fetch('ajax_add_dropdown.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ type: 'shipto', name, title, company, email, phone, address })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert(data.error); return; }
        addOptionToSelect('shiptoSelect', data.id, data.text);
        clearFields(['st_name','st_title','st_company','st_email','st_phone','st_address']);
        bootstrap.Modal.getInstance(document.getElementById('addShipToModal')).hide();
    })
    .catch(() => alert('Failed to save Ship To. Please try again.'));
}
</script>

<?php include("../templates/footer.php"); ?>
