<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Sales']);

$rfq_id = (int)$_GET['id'];
$rfq    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM rfqs WHERE id=$rfq_id"));

if (!$rfq) {
    header("Location: rfqs.php");
    exit;
}

/* ---------- UPDATE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rfq'])) {
    $rfq_number     = mysqli_real_escape_string($conn, $_POST['rfq_number']);
    $rfq_title      = mysqli_real_escape_string($conn, $_POST['rfq_title']);
    $quote_date     = mysqli_real_escape_string($conn, $_POST['quote_date']);
    $validity       = mysqli_real_escape_string($conn, $_POST['validity']);
    $lead_time      = (int)$_POST['lead_time'];
    $shipping       = (float)$_POST['shipping'];
    $customer_id    = (int)$_POST['customer_id'];
    $salesPerson_id = (int)$_POST['salesPerson_id'];
    $billTo_id      = (int)$_POST['billTo_id'];
    $buyer_id       = (int)$_POST['buyer_id'];
    $shipTo_id      = (int)$_POST['shipTo_id'];
    $currency       = mysqli_real_escape_string($conn, $_POST['currency'] ?? 'USD');
    $price_source   = mysqli_real_escape_string($conn, $_POST['price_source'] ?? '');

    mysqli_query($conn, "UPDATE rfqs SET
        rfq_number='$rfq_number', rfq_title='$rfq_title',
        quote_date='$quote_date', validity='$validity',
        lead_time='$lead_time', shipping='$shipping',
        currency='$currency',
        customer_id='$customer_id', salesPerson_id='$salesPerson_id',
        billTo_id='$billTo_id', buyer_id='$buyer_id', shipTo_id='$shipTo_id',
        price_source='$price_source'
        WHERE id=$rfq_id")
    or die("RFQ Update Error: " . mysqli_error($conn));

    mysqli_query($conn, "DELETE FROM rfq_lines WHERE rfq_id=$rfq_id");

    if (!empty($_POST['lines'])) {
        foreach ($_POST['lines'] as $line) {
            if (empty($line['qty']) && empty($line['part']) && empty($line['desc'])) continue;
            $qty            = (int)$line['qty'];
            $unit           = mysqli_real_escape_string($conn, $line['unit']);
            $part           = mysqli_real_escape_string($conn, $line['part']);
            $mfg            = mysqli_real_escape_string($conn, $line['mfg']);
            $desc           = mysqli_real_escape_string($conn, $line['desc']);
            $unit_price     = (float)$line['unit_price'];
            $internal_price = (float)($line['internal_price'] ?? 0);
            $total          = (float)$line['total'];
            $line_source    = mysqli_real_escape_string($conn, $line['source'] ?? '');

            mysqli_query($conn, "INSERT INTO rfq_lines
                (rfq_id,qty,unit,part,mfg,description,
                 unit_price,internal_price,total_price,source)
                VALUES
                ('$rfq_id','$qty','$unit','$part','$mfg','$desc',
                 '$unit_price','$internal_price','$total','$line_source')")
            or die("RFQ Line Insert Error: " . mysqli_error($conn));
        }
    }

    header("Location: rfqs.php");
    exit;
}

/* ---------- DATA ---------- */
$customers    = mysqli_query($conn, "SELECT * FROM customers");
$shipTos      = mysqli_query($conn, "SELECT * FROM shipto");
$buyers       = mysqli_query($conn, "SELECT * FROM buyer");
$billToList   = mysqli_query($conn, "SELECT * FROM billto");
$salesPersons = mysqli_query($conn, "SELECT * FROM salesperson");
$lines_result = mysqli_query($conn, "SELECT * FROM rfq_lines WHERE rfq_id=$rfq_id ORDER BY id ASC");
$existingLines = [];
while ($l = mysqli_fetch_assoc($lines_result)) $existingLines[] = $l;

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper rfq-edit-page">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-pencil-square"></i>
                    Edit RFQ
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php
                $statusClass = match(strtolower($rfq['status'] ?? '')) {
                    'open'   => 'status-open',
                    'closed' => 'status-closed',
                    default  => 'status-draft',
                };
                ?>
                <span class="status-badge <?= $statusClass ?>">
                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                    <?= htmlspecialchars($rfq['status'] ?? 'Draft') ?>
                </span>
                <div class="rfq-num-badge">
                    <i class="bi bi-hash"></i>
                    <?= htmlspecialchars($rfq['rfq_number']) ?>
                </div>
                <button type="button" class="back-btn" onclick="history.go(-1);">
                    <i class="bi bi-arrow-left"></i>Back
                </button>
            </div>
        </div>
    </div>

    <form method="post" id="rfqForm">

        <!-- ── LETTERHEAD ── -->
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

        <div class="row g-3 mb-3">

            <!-- ── LEFT: RFQ DETAILS ── -->
            <div class="col-lg-6">
                <div class="form-card h-100">
                    <div class="form-card-header">
                        <div class="form-card-header-icon"
                            style="background:#fef3c7;color:#d97706;">
                            <i class="bi bi-info-circle-fill"></i>
                        </div>
                        <div class="form-card-header-title">RFQ Details</div>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">RFQ Number <span class="text-danger">*</span></label>
                                <input type="text" name="rfq_number" class="form-control form-input"
                                    value="<?= htmlspecialchars($rfq['rfq_number']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">RFQ Title <span class="text-danger">*</span></label>
                                <input type="text" name="rfq_title" class="form-control form-input"
                                    value="<?= htmlspecialchars($rfq['rfq_title']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Quote Date <span class="text-danger">*</span></label>
                                <input type="date" name="quote_date" id="quote_date"
                                    class="form-control form-input"
                                    value="<?= htmlspecialchars($rfq['quote_date']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Quote Validity <span class="text-danger">*</span></label>
                                <input type="text" name="validity" class="form-control form-input"
                                    value="<?= htmlspecialchars($rfq['validity']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Lead Time (Days) <span class="text-danger">*</span></label>
                                <input type="number" name="lead_time" min="0"
                                    class="form-control form-input"
                                    value="<?= (int)$rfq['lead_time'] ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Currency <span class="text-danger">*</span></label>
                                <select name="currency" id="currencySelect"
                                    class="form-control form-input">
                                    <?php
                                    $currencies = ['EUR' => 'EUR (€)', 'USD' => 'USD ($)', 'GBP' => 'GBP (£)'];
                                    foreach ($currencies as $val => $label):
                                    ?>
                                    <option value="<?= $val ?>"
                                        <?= ($rfq['currency'] ?? 'USD') === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Shipping Charge</label>
                                <input type="number" name="shipping" step="0.01" min="0"
                                    class="form-control form-input"
                                    value="<?= number_format((float)$rfq['shipping'], 2, '.', '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: PARTIES ── -->
            <div class="col-lg-6">
                <div class="form-card h-100">
                    <div class="form-card-header">
                        <div class="form-card-header-icon"
                            style="background:#f0fdf4;color:#16a34a;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="form-card-header-title">Parties & Contacts</div>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <!-- Customer -->
                            <div class="col-6">
                                <label class="form-label">Customer <span class="text-danger">*</span></label>
                                <select name="customer_id" class="form-control form-input" required>
                                    <option value="">Select Customer</option>
                                    <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= $rfq['customer_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                        <?= !empty($c['title']) ? '– '.htmlspecialchars($c['title']) : '' ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Sales Person -->
                            <div class="col-6">
                                <label class="form-label">Sales Person <span class="text-danger">*</span></label>
                                <select name="salesPerson_id" class="form-control form-input" required>
                                    <option value="">Select Sales Person</option>
                                    <?php while ($s = mysqli_fetch_assoc($salesPersons)): ?>
                                    <option value="<?= $s['id'] ?>"
                                        <?= $rfq['salesPerson_id'] == $s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['name']) ?>
                                        <?= !empty($s['title']) ? '– '.htmlspecialchars($s['title']) : '' ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Bill To -->
                            <div class="col-6">
                                <label class="form-label">Bill To <span class="text-danger">*</span></label>
                                <select name="billTo_id" class="form-control form-input" required>
                                    <option value="">Select Bill To</option>
                                    <?php while ($b = mysqli_fetch_assoc($billToList)): ?>
                                    <option value="<?= $b['id'] ?>"
                                        <?= $rfq['billTo_id'] == $b['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['code']) ?> – <?= htmlspecialchars($b['title']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Buyer -->
                            <div class="col-6">
                                <label class="form-label">Buyer <span class="text-danger">*</span></label>
                                <select name="buyer_id" class="form-control form-input" required>
                                    <option value="">Select Buyer</option>
                                    <?php while ($b = mysqli_fetch_assoc($buyers)): ?>
                                    <option value="<?= $b['id'] ?>"
                                        <?= $rfq['buyer_id'] == $b['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['code']) ?> – <?= htmlspecialchars($b['name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <!-- Ship To -->
                            <div class="col-12">
                                <label class="form-label">Ship To <span class="text-danger">*</span></label>
                                <select name="shipTo_id" class="form-control form-input" required>
                                    <option value="">Select Ship To</option>
                                    <?php while ($st = mysqli_fetch_assoc($shipTos)): ?>
                                    <option value="<?= $st['id'] ?>"
                                        <?= $rfq['shipTo_id'] == $st['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st['name']) ?>
                                        <?= !empty($st['title']) ? '– '.htmlspecialchars($st['title']) : '' ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             LINE ITEMS
        ══════════════════════════════════════════ -->
        <?php
        $savedCurrency = $rfq['currency'] ?? 'USD';
        $currencySymMap = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];
        $currSym = $currencySymMap[$savedCurrency] ?? '$';
        ?>
        <div class="rfq-lines-card">
            <div class="rfq-lines-header">
                <div class="rfq-lines-title">
                    <i class="bi bi-list-ul"></i>
                    RFQ Line Items
                    <span style="font-size:.72rem;color:rgba(255,255,255,.4);font-weight:400;">
                        (<?= count($existingLines) ?> item<?= count($existingLines) !== 1 ? 's' : '' ?> loaded)
                    </span>
                </div>
                <button type="button" class="add-row-btn" id="addRow">
                    <i class="bi bi-plus-lg"></i>Add Line
                </button>
            </div>

            <div class="rfq-table-wrap">
                <table class="rfq-line-table" id="lineItemsTable" style="min-height: 150px;">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th style="min-width:70px;">Qty</th>
                            <th style="min-width:70px;">Unit</th>
                            <th style="min-width:280px;">Description / Part Details</th>
                            <th style="min-width:160px;">
                                Source&nbsp;<span style="font-size:.6rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;padding:1px 5px;border-radius:10px;font-weight:700;letter-spacing:.03em;vertical-align:middle;">🔒 Internal</span>
                            </th>
                            <th style="min-width:110px;">
                                Unit Price <span class="currency-symbol"><?= $currSym ?></span>
                            </th>
                            <th style="min-width:110px;">
                                Internal Cost <span class="currency-symbol"><?= $currSym ?></span>
                            </th>
                            <th style="min-width:110px;">
                                Total <span class="currency-symbol"><?= $currSym ?></span>
                            </th>
                            <th class="text-center" style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $fallback = [[
                        'qty'=>1,'unit'=>'Each','part'=>'','mfg'=>'','description'=>'',
                        'unit_price'=>0,'internal_price'=>0,'total_price'=>0
                    ]];
                    $displayLines = !empty($existingLines) ? $existingLines : $fallback;
                    foreach ($displayLines as $i => $line):
                    ?>
                    <tr>
                        <td class="row-num-cell row-index"><?= $i + 1 ?></td>
                        <td>
                            <input type="number" name="lines[<?= $i ?>][qty]" min="0"
                                class="line-input qty"
                                value="<?= (int)$line['qty'] ?>" required>
                        </td>
                        <td>
                            <input type="text" name="lines[<?= $i ?>][unit]"
                                class="line-input"
                                value="<?= htmlspecialchars($line['unit']) ?>" required>
                        </td>
                        <td>
                            <div class="desc-grid">
                                <div class="desc-field-wrap">
                                    <span class="desc-label">PART</span>
                                    <input type="text" name="lines[<?= $i ?>][part]"
                                        class="desc-input"
                                        value="<?= htmlspecialchars($line['part'] ?? '') ?>"
                                        placeholder="—">
                                </div>
                                <div class="desc-field-wrap">
                                    <span class="desc-label">MFG</span>
                                    <input type="text" name="lines[<?= $i ?>][mfg]"
                                        class="desc-input"
                                        value="<?= htmlspecialchars($line['mfg'] ?? '') ?>"
                                        placeholder="—">
                                </div>
                                <div class="desc-field-wrap full">
                                    <span class="desc-label">DESC</span>
                                    <input type="text" name="lines[<?= $i ?>][desc]"
                                        class="desc-input"
                                        value="<?= htmlspecialchars($line['description'] ?? '') ?>"
                                        placeholder="Item description…">
                                </div>
                            </div>
                        </td>
                        <td>
                            <textarea name="lines[<?= $i ?>][source]"
                                class="line-input line-source"
                                placeholder="URL or note…"
                                rows="2"
                                style="resize:vertical;min-height:54px;background:#fffbeb;border-color:#fde68a;font-size:.75rem;"><?= htmlspecialchars($line['source'] ?? '') ?></textarea>
                        </td>
                        <td>
                            <input type="number" step="0.01" name="lines[<?= $i ?>][unit_price]"
                                class="line-input price"
                                value="<?= number_format((float)$line['unit_price'], 2, '.', '') ?>"
                                placeholder="0.00" required>
                        </td>
                        <td>
                            <input type="number" step="0.01" name="lines[<?= $i ?>][internal_price]"
                                class="line-input internal-price"
                                value="<?= number_format((float)($line['internal_price'] ?? 0), 2, '.', '') ?>"
                                placeholder="0.00">
                        </td>
                        <td>
                            <input type="number" step="0.01" name="lines[<?= $i ?>][total]"
                                class="line-input total"
                                value="<?= number_format((float)$line['total_price'], 2, '.', '') ?>"
                                placeholder="0.00" readonly>
                        </td>
                        <td class="text-center">
                            <button type="button" class="remove-row-btn removeRow" title="Remove row">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grand Total Bar -->
            <div class="grand-total-bar">
                <span class="grand-total-label">Grand Total</span>
                <span class="grand-total-currency currency-symbol" style="font-size: 20px; font-weight: 700;"><?= $currSym ?></span>
                <span class="grand-total-value" id="grandTotal">0.00</span>
            </div>
        </div>

        <!-- ── FORM ACTIONS ── -->
        <div class="form-actions-bar">
            <div class="form-actions-left">
                <i class="bi bi-clock-history"></i>
                Last updated: <?= !empty($rfq['updated_at'])
                    ? date('d M Y, h:i A', strtotime($rfq['updated_at']))
                    : date('d M Y', strtotime($rfq['created_at'] ?? 'now')) ?>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="action-btn cancel" onclick="history.go(-1);">
                    <i class="bi bi-x-lg"></i>Cancel
                </button>
                <button type="submit" name="update_rfq" class="action-btn save">
                    <i class="bi bi-floppy-fill"></i>Update RFQ
                </button>
            </div>
        </div>

    </form>
</div><!-- /page-wrapper -->

<script>
/* ── Currency: initialise symbol from saved value ── */
(function () {
    const map   = { USD: '$', EUR: '€', GBP: '£' };
    const saved = <?= json_encode($savedCurrency) ?>;
    const sym   = map[saved] || '$';
    document.querySelectorAll('.currency-symbol').forEach(el => el.textContent = sym);

    document.getElementById('currencySelect').addEventListener('change', function () {
        const s = map[this.value] || '$';
        document.querySelectorAll('.currency-symbol').forEach(el => el.textContent = s);
    });
})();

/* ── Line Items Logic ── */
document.addEventListener("DOMContentLoaded", function () {
    const tbody = document.querySelector("#lineItemsTable tbody");
    const addRowBtn = document.getElementById("addRow");

    function calcRow(row) {
        const qty   = parseFloat(row.querySelector(".qty").value)   || 0;
        const price = parseFloat(row.querySelector(".price").value) || 0;
        row.querySelector(".total").value = (qty * price).toFixed(2);
    }

    function calcGrandTotal() {
        let sum = 0;
        tbody.querySelectorAll(".total").forEach(t => sum += parseFloat(t.value) || 0);
        document.getElementById("grandTotal").textContent =
            (Math.round(sum * 100) / 100).toFixed(2);
    }

    function reIndex() {
        Array.from(tbody.rows).forEach((row, idx) => {
            row.querySelector(".row-index").textContent = idx + 1;
            row.querySelectorAll("[name]").forEach(inp => {
                inp.name = inp.name.replace(/\[\d+\]/, `[${idx}]`);
            });
        });
    }

    function attachEvents(row) {
        row.querySelector(".qty").addEventListener("input",   () => { calcRow(row); calcGrandTotal(); });
        row.querySelector(".price").addEventListener("input", () => { calcRow(row); calcGrandTotal(); });
        calcRow(row);
        calcGrandTotal();
    }

    Array.from(tbody.rows).forEach(attachEvents);

    /* ADD ROW */
    addRowBtn.addEventListener("click", function () {
        const idx    = tbody.rows.length;
        const newRow = tbody.rows[0].cloneNode(true);

        newRow.querySelector(".row-index").textContent = idx + 1;
        newRow.querySelectorAll("[name]").forEach(inp => {
            inp.name  = inp.name.replace(/\[\d+\]/, `[${idx}]`);
            inp.value = '';
        });
        newRow.querySelector(".qty").value = "1";
        newRow.querySelector('[name*="[unit]"]').value = "Each";
        const srcTA = newRow.querySelector('.line-source');
        if (srcTA) srcTA.value = '';

        tbody.appendChild(newRow);
        attachEvents(newRow);
        newRow.querySelector(".qty").focus();
    });

    /* REMOVE ROW */
    tbody.addEventListener("click", function (e) {
        const btn = e.target.closest(".removeRow");
        if (!btn) return;
        if (tbody.rows.length > 1) {
            btn.closest("tr").remove();
            reIndex();
            calcGrandTotal();
        } else {
            alert("At least one line item is required!");
        }
    });

    calcGrandTotal();
});
</script>

<?php include("add-customer-model.php"); ?>
<?php include("add-salesPerson-model.php"); ?>
<?php include("add-billTo-model.php"); ?>
<?php include("add-buyer-model.php"); ?>
<?php include("add-shipTo-model.php"); ?>
<?php include("../templates/footer.php"); ?>
