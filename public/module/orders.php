<?php 
include("../../includes/auth.php");  // ✅ Goes up to crm/, then includes/
include("../../config/database.php");// ✅ CRITICAL: Loads $conn
include("../../templates/header.php"); 
include("../../templates/footer.php");
check_auth(); 

// Static ID 29 as requested - securely casted
$id = 29; 

$query = "SELECT o.*, 
    c.name AS customer_name, c.address AS customer_address,
    sp.name AS salesperson_name, sp.title AS salesperson_title, 
    sp.email AS salesperson_email, sp.phone AS salesperson_phone, 
    sp.signature AS salesperson_signature,
    bt.title AS billto_title, bt.address AS billto_address,
    b.name AS buyer_name, b.email AS buyer_email, b.phone AS buyer_phone, 
    b.address AS buyer_address,
    st.name AS shipto_name, st.company AS shipto_company, 
    st.email AS shipto_email, st.phone AS shipto_phone, 
    st.address AS shipto_address 
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    LEFT JOIN salesperson sp ON o.salesPerson_id = sp.id 
    LEFT JOIN billto bt ON o.billTo_id = bt.id 
    LEFT JOIN buyer b ON o.buyer_id = b.id 
    LEFT JOIN shipto st ON o.shipTo_id = st.id 
    WHERE o.id = $id";

$order_result = mysqli_query($conn, $query);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    echo "<div class='alert alert-danger'>Order #29 not found in database.</div>";
    include("../templates/footer.php");
    exit;
}

$lines_result = mysqli_query($conn, "SELECT * FROM order_lines WHERE order_id = $id");
?>

<style>
table { font-size: 12px; }
.table-responsive { min-width: 850px; }
.badge { font-size: 0.75em; }
</style>

<div class="container mt-4">
    <h4>
        <i class="bi bi-arrow-left-circle me-2" title="Back" onclick="history.go(-1)" style="cursor: pointer;" role="button"></i>
        Order #<?= htmlspecialchars($order['order_number']) ?>
    </h4>

    <div class="card">
        <div class="card-body">
            <!-- Customer Info Row -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <strong>Bill To:</strong><br>
                    <?= htmlspecialchars($order['billto_title'] ?? '') ?><br>
                    <?= nl2br(htmlspecialchars($order['billto_address'] ?? '')) ?>
                </div>
                <div class="col-md-4">
                    <strong>Buyer:</strong><br>
                    <?= htmlspecialchars($order['buyer_name'] ?? '') ?><br>
                    <?= htmlspecialchars($order['buyer_email'] ?? '') ?><br>
                    <?= htmlspecialchars($order['buyer_phone'] ?? '') ?>
                </div>
                <div class="col-md-4">
                    <strong>Ship To:</strong><br>
                    <?= htmlspecialchars($order['shipto_name'] ?? '') ?>
                    <?= ($order['shipto_company'] ? ' (' . htmlspecialchars($order['shipto_company']) . ')' : '') ?><br>
                    <?= htmlspecialchars($order['shipto_email'] ?? '') ?><br>
                    <?= htmlspecialchars($order['shipto_phone'] ?? '') ?><br>
                    <?= nl2br(htmlspecialchars($order['shipto_address'] ?? '')) ?>
                </div>
            </div>

            <!-- Order Details -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <p class="mb-0">
                        <strong>RFQ #:</strong> <?= htmlspecialchars($order['rfq_number'] ?? 'N/A') ?> &nbsp;
                        <strong>RFQ Title:</strong> <?= htmlspecialchars($order['rfq_title'] ?? 'N/A') ?> &nbsp;
                        <strong>Quote Date:</strong> <?= htmlspecialchars($order['quote_date'] ?? 'N/A') ?> &nbsp;
                        <strong>Lead Time:</strong> <?= htmlspecialchars($order['lead_time'] ?? 0) ?> days
                    </p>
                </div>
            </div>

            <!-- Order Lines Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th width="2%" class="text-center">
                                <input type="checkbox" id="selectAll" title="Select All">
                            </th>
                            <th width="5%">Line #</th>
                            <th>Product</th>
                            <th width="5%">Qty</th>
                            <th width="40%">Description</th>
                            <th width="8%">Status</th>
                            <th width="8%">Delivered</th>
                            <th width="8%">Invoiced</th>
                            <th width="8%">Unit Price</th>
                            <th width="10%">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1; 
                        $total = 0;
                        while ($line = mysqli_fetch_assoc($lines_result)) {
                            $qty = $line['qty'] ?? 0;
                            $unit_price = $line['unit_price'] ?? 0;
                            $amount = $qty * $unit_price;
                            $total += $amount;
                            
                            $is_completed = ($line['status'] === 'Completed');
                        ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" 
                                       class="form-check-input line-checkbox" 
                                       data-ol-id="<?= $line['id'] ?>"
                                       <?= $is_completed ? 'disabled checked' : '' ?> 
                                       title="<?= $is_completed ? 'Completed - Cannot select' : 'Select for actions' ?>">
                            </td>
                            <td class="text-center"><?= $i++ ?></td>
                            <td>
                                <a href="handle-order-line.php?ol_id=<?= $line['id'] ?>&i=<?= $i-1 ?>" 
                                   class="text-decoration-none" 
                                   title="Edit Line #<?= $i-1 ?>">
                                    <?= htmlspecialchars($line['product'] ?? $line['description'] ?? 'N/A') ?>
                                </a>
                            </td>
                            <td class="text-end"><?= number_format($qty, 0) ?></td>
                            <td>
                                <div class="small">
                                    <b>PART:</b> <?= htmlspecialchars($line['part'] ?? '') ?><br>
                                    <b>MFG:</b> <?= htmlspecialchars($line['mfg'] ?? '') ?><br>
                                    <b>CUST:</b> <?= htmlspecialchars($line['cust'] ?? '') ?><br>
                                    <b>COO:</b> <?= htmlspecialchars($line['coo'] ?? '') ?><br>
                                    <b>ECCN:</b> <?= htmlspecialchars($line['eccn'] ?? '') ?><br>
                                    <b>HTSUS:</b> <?= htmlspecialchars($line['htsus'] ?? '') ?><br>
                                    <b>DESC:</b> <?= htmlspecialchars($line['description'] ?? '') ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <?php if ($is_completed): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= htmlspecialchars($line['status'] ?? 'Pending') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format($line['delivered'] ?? 0, 2) ?></td>
                            <td class="text-end"><?= number_format($line['invoiced'] ?? 0, 2) ?></td>
                            <td class="text-end">$<?= number_format($unit_price, 2) ?></td>
                            <td class="text-end fw-bold">$<?= number_format($amount, 2) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="1">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button id="btnPackagingSlip" class="btn btn-outline-primary" disabled>
                                        <i class="bi bi-filetype-pdf"></i> Packaging Slip
                                    </button>
                                    <button id="btnInvoice" class="btn btn-outline-info" disabled>
                                        <i class="bi bi-filetype-pdf"></i> Invoice
                                    </button>
                                </div>
                            </td>
                            <td colspan="8" class="text-end fw-bold fs-5">TOTAL</td>
                            <td class="text-end fw-bold fs-5">$<?= number_format($total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.line-checkbox');
    const btnPackagingSlip = document.getElementById('btnPackagingSlip');
    const btnInvoice = document.getElementById('btnInvoice');

    function updateHeaderCheckbox() {
        const enabledCheckboxes = Array.from(checkboxes).filter(cb => !cb.disabled);
        const checkedEnabled = enabledCheckboxes.filter(cb => cb.checked).length;
        const totalEnabled = enabledCheckboxes.length;

        if (checkedEnabled === 0) {
            selectAll.indeterminate = false;
            selectAll.checked = false;
        } else if (checkedEnabled === totalEnabled) {
            selectAll.indeterminate = false;
            selectAll.checked = true;
        } else {
            selectAll.indeterminate = true;
            selectAll.checked = false;
        }
    }

    function updateButtons() {
        const selectedCount = Array.from(checkboxes)
            .filter(cb => cb.checked && !cb.disabled).length;
        const hasSelection = selectedCount > 0;
        
        btnPackagingSlip.disabled = !hasSelection;
        btnInvoice.disabled = !hasSelection;
    }

    // Header checkbox toggle
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => {
            if (!cb.disabled) {
                cb.checked = selectAll.checked;
            }
        });
        updateButtons();
    });

    // Individual checkbox changes
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateHeaderCheckbox();
            updateButtons();
        });
    });

    // Get selected order line IDs
    function getSelectedIds() {
        return Array.from(checkboxes)
            .filter(cb => cb.checked && !cb.disabled && cb.dataset.olId)
            .map(cb => cb.dataset.olId);
    }

    // Packaging Slip
    btnPackagingSlip.addEventListener('click', function() {
        const ids = getSelectedIds();
        if (ids.length === 0) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'order-line-package-slip.php';
        form.style.display = 'none';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids';
        input.value = JSON.stringify(ids);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    });

    // Invoice
    btnInvoice.addEventListener('click', function() {
        const ids = getSelectedIds();
        if (ids.length === 0) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'order-line-invoice.php';
        form.style.display = 'none';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids';
        input.value = JSON.stringify(ids);
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    });

    // Initial state
    updateHeaderCheckbox();
    updateButtons();
});
</script>

<?php include("../templates/footer.php"); ?>
