<?php
session_start();
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

/* ---------- UPDATE FIRST (NO OUTPUT BEFORE THIS) ---------- */
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id     = $_POST['order_id'];
    $status       = $_POST['status'];
    $due_date     = $_POST['due_date'];
    $paid_date    = $_POST['paid_date'];
    $total_amount = $_POST['total_amount'];

    mysqli_query($conn, "
        UPDATE invoices SET
            order_id='$order_id',
            status='$status',
            due_date='$due_date',
            paid_date='$paid_date',
            total_amount='$total_amount'
        WHERE id=$id
    ");

    header("Location: invoices.php");
    exit;
}

/* ---------- FETCH DATA ---------- */
$invoice = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM invoices WHERE id = $id
"));

$orders = mysqli_query($conn, "
    SELECT o.id, o.code, c.name AS customer
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    ORDER BY o.id DESC
");

/* ---------- NOW OUTPUT ---------- */
include("../templates/header.php");
include("../templates/navbar.php");

/* Status helper */
$statusRaw   = strtolower($invoice['status'] ?? '');
$statusClass = match (true) {
    str_contains($statusRaw, 'paid')    => 'sb-paid',
    str_contains($statusRaw, 'sent')    => 'sb-sent',
    str_contains($statusRaw, 'overdue') => 'sb-overdue',
    default                             => 'sb-draft',
};
?>

<div class="page-wrapper">

    <!-- ══════════ PAGE HEADER ══════════ -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-receipt"></i>
                    Edit Invoice #<?= $id ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="status-badge <?= $statusClass ?>">
                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                    <?= htmlspecialchars($invoice['status'] ?? 'Draft') ?>
                </span>
                <div class="inv-num-badge">
                    <i class="bi bi-receipt"></i>
                    INV-<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?>
                </div>
                <a href="invoices.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>Back
                </a>
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

    <form method="post">

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

                    <!-- Linked Order -->
                    <div class="col-md-6">
                        <label class="form-label">Linked Order <span class="text-danger">*</span></label>
                        <select name="order_id" class="form-control form-input" required>
                            <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                                <option value="<?= $o['id'] ?>"
                                    <?= $o['id'] == $invoice['order_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['code']) ?>
                                    <?= $o['customer'] ? ' — ' . htmlspecialchars($o['customer']) : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-input" id="statusSelect">
                            <?php foreach (['Draft', 'Sent', 'Paid', 'Overdue'] as $s): ?>
                                <option <?= $invoice['status'] == $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Total Amount -->
                    <div class="col-md-3">
                        <label class="form-label">Total Amount</label>
                        <div class="input-prefix-group">
                            <span class="input-prefix">$</span>
                            <input type="text" class="line-input total" placeholder="0.00" style="background:#f8fafc;color:#64748b;">
                            </>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════ DATES ══════════ -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#eff6ff;color:#0369a1;">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <div class="form-card-header-title">Dates</div>
                </div>
                <div class="form-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control form-input"
                                value="<?= htmlspecialchars($invoice['due_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Paid Date
                                <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;">
                                    (leave blank if unpaid)
                                </span>
                            </label>
                            <input type="date" name="paid_date" class="form-control form-input" id="paidDateInput"
                                value="<?= htmlspecialchars($invoice['paid_date'] ?? '') ?>">
                            <div class="paid-note <?= !empty($invoice['paid_date']) ? 'visible' : '' ?>" id="paidNote">
                                <i class="bi bi-check-circle-fill"></i> Marked as paid
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <!-- ══════════ ACTION FOOTER ══════════ -->
            <div class="form-actions-bar d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4">
                <div class="action-footer-left">
                    <i class="bi bi-clock-history"></i>
                    Invoice #<?= $id ?> &nbsp;&bull;&nbsp;
                    <?php if (!empty($invoice['created_at'])): ?>
                        Created <?= date('d M Y', strtotime($invoice['created_at'])) ?>
                    <?php else: ?>
                        Last modified now
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="invoices.php" class="action-btn cancel">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                    <button type="submit" class="action-btn save">
                        <i class="bi bi-floppy-fill"></i> Save Changes
                    </button>
                </div>
            </div>                        
    </form>
</div><!-- /page-wrapper -->

<script>
    /* ── Show/hide paid note based on paid_date ── */
    const paidDateInput = document.getElementById('paidDateInput');
    const paidNote = document.getElementById('paidNote');

    paidDateInput.addEventListener('change', function() {
        paidNote.classList.toggle('visible', this.value !== '');
    });

    /* ── Status badge live update in header ── */
    const statusSelect = document.getElementById('statusSelect');
    const headerBadge = document.querySelector('.page-header .status-badge');

    const statusClasses = {
        'Paid': 'sb-paid',
        'Sent': 'sb-sent',
        'Overdue': 'sb-overdue',
        'Draft': 'sb-draft',
    };

    statusSelect.addEventListener('change', function() {
        headerBadge.className = 'status-badge ' + (statusClasses[this.value] || 'sb-draft');
        headerBadge.querySelector('span:last-child') ?
            headerBadge.lastChild.textContent = ' ' + this.value :
            null;
        headerBadge.innerHTML =
            `<i class="bi bi-circle-fill" style="font-size:.5rem;"></i> ${this.value}`;

        /* Auto-set paid date when status flipped to Paid */
        if (this.value === 'Paid' && !paidDateInput.value) {
            paidDateInput.value = new Date().toISOString().split('T')[0];
            paidNote.classList.add('visible');
        }
        if (this.value !== 'Paid') {
            paidNote.classList.remove('visible');
        }
    });
</script>

<?php include("../templates/footer.php"); ?>