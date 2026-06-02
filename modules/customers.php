<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Sales']);

/* ---------- PAGINATION SETUP ---------- */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page']     ?? 1));

/* ---------- ADD ---------- */
if (isset($_POST['add_customer'])) {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $title   = mysqli_real_escape_string($conn, trim($_POST['title']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    mysqli_query($conn, "INSERT INTO customers (name,title,email,phone,address)
                         VALUES ('$name','$title','$email','$phone','$address')");
    header("Location: customers.php");
    exit;
}

/* ---------- UPDATE ---------- */
if (isset($_POST['edit_customer_id'])) {
    $id      = (int)$_POST['edit_customer_id'];
    $name    = mysqli_real_escape_string($conn, trim($_POST['edit_name']));
    $title   = mysqli_real_escape_string($conn, trim($_POST['edit_title']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['edit_email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['edit_phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['edit_address']));
    mysqli_query($conn, "UPDATE customers
                         SET name='$name', title='$title', email='$email',
                             phone='$phone', address='$address'
                         WHERE id=$id");
    header("Location: customers.php");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM customers WHERE id=$id");
    header("Location: customers.php?deleted=1");
    exit;
}

/* ---------- CUSTOMERS TABLE FUNCTION ---------- */
function getCustomersTable($conn, $search = "", $page = 1, $per_page = 20) {
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE 1=1";
    if (trim($search) !== '') {
        $s = mysqli_real_escape_string($conn, trim($search));
        $where .= " AND (name LIKE '%$s%' OR email LIKE '%$s%'
                     OR phone LIKE '%$s%' OR title LIKE '%$s%')";
    }

    /* -- total count for pagination -- */
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers $where"));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $result = mysqli_query($conn, "SELECT * FROM customers $where ORDER BY id DESC LIMIT $offset, $per_page");
    $count  = mysqli_num_rows($result);

    ob_start();

    if ($count === 0): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-people"></i></div>
        <div class="empty-state-title">No Customers Found</div>
        <div class="empty-state-sub">Try adjusting your search criteria</div>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table cust-table mb-0">
            <thead>
                <tr>
                    <th>Customer #</th>
                    <th>Name</th>
                    <th>Title</th>
                    <th class="email-hide">Email</th>
                    <th>Phone</th>
                    <th class="addr-hide">Address</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="custBody">
            <?php while ($c = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td>
                    <span class="ref-badge ref-badge-sky">
                        <?= htmlspecialchars($c['code'] ?? '—') ?>
                    </span>
                </td>
                <td>
                    <div class="cust-cell">
                        <div class="cust-avatar">
                            <?= strtoupper(substr($c['name'] ?? '?', 0, 1)) ?>
                        </div>
                        <span class="cust-name"><?= htmlspecialchars($c['name'] ?? '—') ?></span>
                    </div>
                </td>
                <td>
                    <?php if (!empty($c['title'])): ?>
                    <span class="title-tag"><?= htmlspecialchars($c['title']) ?></span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="email-hide">
                    <?php if (!empty($c['email'])): ?>
                    <span class="contact-cell">
                        <i class="bi bi-envelope"></i>
                        <?= htmlspecialchars($c['email']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($c['phone'])): ?>
                    <span class="contact-cell">
                        <i class="bi bi-telephone"></i>
                        <?= htmlspecialchars($c['phone']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="addr-hide" style="font-size:.8rem;color:#64748b;max-width:160px;">
                    <span style="display:inline-block;white-space:nowrap;overflow:hidden;
                                 text-overflow:ellipsis;max-width:150px;"
                        title="<?= htmlspecialchars($c['address'] ?? '') ?>">
                        <?= !empty($c['address']) ? htmlspecialchars($c['address']) : '—' ?>
                    </span>
                </td>
                <td class="text-center">
                    <div class="row-actions">
                        <!-- EDIT -->
                        <button type="button"
                            class="row-action-btn edit editBtn"
                            title="Edit Customer"
                            data-bs-toggle="modal"
                            data-bs-target="#editCustomerModal"
                            data-id="<?= $c['id'] ?>"
                            data-name="<?= htmlspecialchars($c['name']    ?? '') ?>"
                            data-title="<?= htmlspecialchars($c['title']   ?? '') ?>"
                            data-email="<?= htmlspecialchars($c['email']   ?? '') ?>"
                            data-phone="<?= htmlspecialchars($c['phone']   ?? '') ?>"
                            data-address="<?= htmlspecialchars($c['address'] ?? '') ?>"
                            data-code="<?= htmlspecialchars($c['code']    ?? '') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <!-- DELETE -->
                        <button type="button"
                            class="row-action-btn delete"
                            title="Delete Customer"
                            onclick="openDeleteModal(
                                <?= $c['id'] ?>,
                                '<?= addslashes(htmlspecialchars($c['name']    ?? '')) ?>',
                                '<?= addslashes(htmlspecialchars($c['title']   ?? '')) ?>',
                                '<?= addslashes(htmlspecialchars($c['email']   ?? '')) ?>',
                                '<?= addslashes(htmlspecialchars($c['code']    ?? '—')) ?>'
                            )">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- ── PAGINATION FOOTER ── -->
    <div class="table-footer-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="text-muted" style="font-size:.78rem;">
            <i class="bi bi-list-ul me-1"></i>
            Showing <strong><?= $count ?></strong> of <strong><?= $total ?></strong>
            customer<?= $total !== 1 ? 's' : '' ?>
        </span>

        <?php if ($pages > 1): ?>
        <nav aria-label="Customer pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="javascript:void(0)" data-page="<?= max(1, $page - 1) ?>">Prev</a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="javascript:void(0)" data-page="<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="javascript:void(0)" data-page="<?= min($pages, $page + 1) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <?php endif;
    return ob_get_clean();
}

/* ---------- AJAX ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page']     ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getCustomersTable($conn, $_GET['cust_search'] ?? '', $page, $per_page);
    exit;
}

/* ---------- COUNTS ---------- */
$cnt_total  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM customers"))['c'];
$with_email = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM customers WHERE email IS NOT NULL AND email != ''"))['c'];
$with_phone = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM customers WHERE phone IS NOT NULL AND phone != ''"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>


<div class="page-wrapper">

    <!-- ── ALERTS ── -->
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Customer deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-people-fill"></i>
                    Customers
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="cust_search"
                        value="<?= htmlspecialchars($_GET['cust_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search name, email, phone…">
                </div>
                <button class="page-action-btn primary"
                    data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                    <i class="bi bi-person-plus-fill"></i>New Customer
                </button>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#0ea5e9;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Total Customers</div>
                        <div class="cust-stat-num" style="color:#0ea5e9;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#e0f2fe;color:#0ea5e9;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#6366f1;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Showing</div>
                        <div class="cust-stat-num" id="filteredCount" style="color:#6366f1;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#e0e7ff;color:#6366f1;">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Email</div>
                        <div class="cust-stat-num" style="color:#10b981;"><?= $with_email ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#f59e0b;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Phone</div>
                        <div class="cust-stat-num" style="color:#f59e0b;"><?= $with_phone ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#fef9c3;color:#f59e0b;">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="cust-table-card card border-0">
        <!-- Card header with per-page selector -->
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Customers
                </span>
            </div>
            <div class="d-flex gap-2">
                <select id="perPage" class="form-select form-select-sm"
                        style="min-width:90px;font-size:.8rem;">
                    <option value="10"  <?= $per_page == 10  ? 'selected' : '' ?>>10 / page</option>
                    <option value="20"  <?= $per_page == 20  ? 'selected' : '' ?>>20 / page</option>
                    <option value="50"  <?= $per_page == 50  ? 'selected' : '' ?>>50 / page</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="custTable" style="overflow-x:auto;min-height:300px;">
                <?= getCustomersTable($conn, $_GET['cust_search'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ══════════════════════════════════════════
     ADD CUSTOMER MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="newCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
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
                        <input type="text" name="name" class="form-control modal-input"
                            placeholder="e.g. John Smith" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Position</div>
                        <input type="text" name="title" class="form-control modal-input"
                            placeholder="e.g. Procurement Manager">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email <span class="text-danger">*</span></div>
                        <input type="email" name="email" class="form-control modal-input"
                            placeholder="e.g. john@company.com" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="phone" class="form-control modal-input"
                            placeholder="e.g. +1 555 000 0000"
                            pattern="[0-9+\-\s]{3,20}">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Address</div>
                        <textarea name="address" rows="2"
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
                <button type="submit" name="add_customer"
                    class="btn btn-sm px-4"
                    style="background:#0ea5e9;color:#fff;border-color:#0284c7;">
                    <i class="bi bi-person-plus me-1"></i>Save Customer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     EDIT CUSTOMER MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_customer_id" id="edit_customer_id">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit Customer</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update customer details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-3"
                    style="background:#f0f9ff;border:1px solid #bae6fd;">
                    <i class="bi bi-person-badge" style="color:#0284c7;font-size:.9rem;"></i>
                    <span style="font-size:.78rem;color:#0369a1;font-weight:600;">
                        Customer # &nbsp;<strong id="editModalCode">—</strong>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
                        <input type="text" name="edit_name" id="edit_name"
                            class="form-control modal-input"
                            placeholder="e.g. John Smith" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Position</div>
                        <input type="text" name="edit_title" id="edit_title"
                            class="form-control modal-input"
                            placeholder="e.g. Procurement Manager">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email <span class="text-danger">*</span></div>
                        <input type="email" name="edit_email" id="edit_email"
                            class="form-control modal-input"
                            placeholder="e.g. john@company.com" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="edit_phone" id="edit_phone"
                            class="form-control modal-input"
                            placeholder="e.g. +1 555 000 0000"
                            pattern="[0-9+\-\s]{3,20}">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Address</div>
                        <textarea name="edit_address" id="edit_address" rows="2"
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
                <button type="submit"
                    class="btn btn-sm px-4"
                    style="background:#0ea5e9;color:#fff;border-color:#0284c7;">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DELETE CUSTOMER MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap">
                            <i class="bi bi-person-x-fill"></i>
                        </div>
                        <div>
                            <div class="delete-modal-title">Delete Customer</div>
                            <div class="delete-modal-sub" id="deleteCustModalSub">Confirm customer removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-cust-chip mb-3">
                    <div class="delete-cust-avatar" id="deleteCustAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-cust-name"  id="deleteCustName">—</div>
                        <div class="delete-cust-meta"  id="deleteCustTitle">—</div>
                        <div class="delete-cust-meta"  id="deleteCustEmail">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Customer #</span>
                                <span class="chip-detail-value" id="deleteCustCode">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        This action is <strong>permanent and cannot be undone.</strong>
                        The customer record will be permanently removed from your directory.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteCustConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Customer
                </a>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════
   EDIT MODAL — re-init after every AJAX render
══════════════════════════════════════════ */
function initEditButtons() {
    document.querySelectorAll('.editBtn').forEach(btn => {
        // clone to remove old listeners before re-attaching
        const fresh = btn.cloneNode(true);
        btn.replaceWith(fresh);
        fresh.addEventListener('click', function () {
            document.getElementById('edit_customer_id').value  = this.dataset.id;
            document.getElementById('edit_name').value         = this.dataset.name;
            document.getElementById('edit_title').value        = this.dataset.title;
            document.getElementById('edit_email').value        = this.dataset.email;
            document.getElementById('edit_phone').value        = this.dataset.phone;
            document.getElementById('edit_address').value      = this.dataset.address;
            document.getElementById('editModalSub').textContent  = 'Editing: '  + this.dataset.name;
            document.getElementById('editModalCode').textContent = this.dataset.code || '—';
        });
    });
}

/* ══════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════ */
function openDeleteModal(id, name, title, email, code) {
    document.getElementById('deleteCustModalSub').textContent  = 'Removing: ' + name;
    document.getElementById('deleteCustAvatar').textContent    = name ? name.charAt(0).toUpperCase() : '?';
    document.getElementById('deleteCustName').textContent      = name  || '—';
    document.getElementById('deleteCustTitle').textContent     = title || '—';
    document.getElementById('deleteCustEmail').textContent     = email || '—';
    document.getElementById('deleteCustCode').textContent      = code  || '—';
    document.getElementById('deleteCustTitle').style.display   = title ? '' : 'none';
    document.getElementById('deleteCustEmail').style.display   = email ? '' : 'none';
    document.getElementById('deleteCustConfirmBtn').href        = '?delete=' + id;
    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('deleteCustomerModal')
    ).show();
}

/* ══════════════════════════════════════════
   TABLE AJAX + PAGINATION
══════════════════════════════════════════ */
let searchTimer;

function loadTable(search, page = 1) {
    document.getElementById('custTable').innerHTML =
        '<div class="text-center py-5">' +
        '<div class="spinner-border text-secondary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
        '<p class="text-muted small mt-2">Loading...</p></div>';

    const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;

    let url = 'customers.php?ajax=1&page=' + page + '&per_page=' + perPage;
    if (search) url += '&cust_search=' + encodeURIComponent(search);

    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById('custTable').innerHTML = html;
            initEditButtons(); // re-bind edit buttons on fresh HTML

            // update the "Showing" stat card from the footer text
            const strong = document.querySelector('#custTable .table-footer-bar strong');
            const filteredCountEl = document.getElementById('filteredCount');
            if (strong && filteredCountEl) filteredCountEl.textContent = strong.textContent;

            // push state
            let qs = [];
            if (search) qs.push('cust_search=' + encodeURIComponent(search));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
}

/* Pagination click — delegated so it works after re-renders */
document.addEventListener('click', function (e) {
    const pg = e.target.closest('#custTable .pagination .page-link');
    if (!pg || !pg.dataset.page) return;
    e.preventDefault();
    const page   = parseInt(pg.dataset.page, 10) || 1;
    const search = document.getElementById('cust_search').value.trim();
    loadTable(search, page);
});

/* Per-page selector */
document.getElementById('perPage').addEventListener('change', function () {
    const search = document.getElementById('cust_search').value.trim();
    loadTable(search, 1);
});

/* Search */
document.getElementById('cust_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value;
    searchTimer = setTimeout(() => loadTable(q.trim(), 1), 350);
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('#cust_search')) {
        e.preventDefault();
        clearTimeout(searchTimer);
        loadTable(e.target.value.trim(), 1);
    }
});

/* Back/forward */
window.addEventListener('popstate', function () {
    const params = new URLSearchParams(window.location.search);
    const q  = params.get('cust_search') || '';
    const pg = parseInt(params.get('page') || '1', 10);
    document.getElementById('cust_search').value = q;
    loadTable(q, pg);
});

/* Init on first load */
document.addEventListener('DOMContentLoaded', initEditButtons);
</script>

<?php include("../templates/footer.php"); ?>