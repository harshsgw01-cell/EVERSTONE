<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();

/* ── Pagination setup ── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

require_role(['Admin']);

/* ---------- ADD ---------- */
if (isset($_POST['add_vendor'])) {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    mysqli_query($conn, "INSERT INTO vendors (name, email, phone, address)
                         VALUES ('$name','$email','$phone','$address')");
    header("Location: vendor_add.php");
    exit;
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit_vendor_id'])) {
    $id      = (int)$_POST['edit_vendor_id'];
    $name    = mysqli_real_escape_string($conn, trim($_POST['edit_name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['edit_email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['edit_phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['edit_address']));
    mysqli_query($conn, "UPDATE vendors
                         SET name='$name', email='$email', phone='$phone', address='$address'
                         WHERE id=$id");
    header("Location: vendor_add.php");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM vendors WHERE id=$id");
    header("Location: vendor_add.php");
    exit;
}

/* ---------- COUNTS ---------- */
$cnt_total      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM vendors"))['c'];
$cnt_with_email = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM vendors WHERE email   IS NOT NULL AND email   != ''"))['c'];
$cnt_with_phone = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM vendors WHERE phone   IS NOT NULL AND phone   != ''"))['c'];
$cnt_with_addr  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM vendors WHERE address IS NOT NULL AND address != ''"))['c'];

$vendors = mysqli_query($conn, "
    SELECT * FROM vendors 
    ORDER BY id DESC 
    LIMIT $offset, $per_page
");
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM vendors"))['c'];
$pages = max(1, ceil($total / $per_page));

$rows   = [];
while ($r = mysqli_fetch_assoc($vendors)) $rows[] = $r;

include("../templates/header.php");
include("../templates/navbar.php");

?>

<div class="page-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-building-fill"></i>Vendors
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="vd_search"
                        class="form-control page-search-input"
                        placeholder="Search name, email, phone…">
                </div>
                <button class="page-action-btn primary"
                    data-bs-toggle="modal" data-bs-target="#newVendorModal">
                    <i class="bi bi-plus-circle-fill"></i>New Vendor
                </button>
            </div>
        </div>
    </div>

    <!-- STAT CARDS — horizontal layout matching vendor_add.php -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#ea580c;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Total Vendors</div>
                        <div class="cust-stat-num" style="color:#ea580c;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#ffedd5;color:#ea580c;">
                        <i class="bi bi-building-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#6366f1;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Showing</div>
                        <div class="cust-stat-num" id="filteredCount" style="color:#6366f1;"><?= mysqli_num_rows($vendors) ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#e0e7ff;color:#6366f1;">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#0ea5e9;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Email</div>
                        <div class="cust-stat-num" style="color:#0ea5e9;"><?= $cnt_with_email ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#e0f2fe;color:#0ea5e9;">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Address</div>
                        <div class="cust-stat-num" style="color:#10b981;"><?= $cnt_with_addr ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="vd-table-card card border-0">
        <!-- TOP PAGINATION BAR -->
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
        style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                    style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Vendors, <?= $total ?> total
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Per Page -->
                <select onchange="changePerPage(this.value)" class="form-select form-select-sm" style="width:auto;">
                    <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-building"></i></div>
                <div class="empty-state-title">No Vendors Yet</div>
                <div class="empty-state-sub">Add your first vendor or supplier to get started</div>
                <button class="page-action-btn primary mt-3"
                    data-bs-toggle="modal" data-bs-target="#newVendorModal">
                    <i class="bi bi-plus-circle-fill"></i>Add Vendor
                </button>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="table vd-table mb-0">
                    <thead>
                        <tr>
                            <th>Vendor #</th>
                            <th>Name</th>
                            <th class="email-hide">Email</th>
                            <th>Phone</th>
                            <th class="addr-hide">Address</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="vdBody">
                    <?php foreach ($rows as $v): ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($v['name']    ?? '')) ?>"
                        data-email="<?= strtolower(htmlspecialchars($v['email']   ?? '')) ?>"
                        data-phone="<?= strtolower(htmlspecialchars($v['phone']   ?? '')) ?>"
                        data-address="<?= strtolower(htmlspecialchars($v['address'] ?? '')) ?>">
                        <td>
                            <span class="ref-badge ref-badge-orange">
                                <?= htmlspecialchars($v['code'] ?? '—') ?>
                            </span>
                        </td>
                        <td>
                            <div class="vd-cell">
                                <div class="vd-avatar"><?= strtoupper(substr($v['name'] ?? '?', 0, 1)) ?></div>
                                <span class="vd-name"><?= htmlspecialchars($v['name'] ?? '—') ?></span>
                            </div>
                        </td>
                        <td class="email-hide">
                            <?php if (!empty($v['email'])): ?>
                            <span class="contact-cell"><i class="bi bi-envelope"></i><?= htmlspecialchars($v['email']) ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($v['phone'])): ?>
                            <span class="contact-cell"><i class="bi bi-telephone"></i><?= htmlspecialchars($v['phone']) ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="addr-hide">
                            <span class="addr-text"
                                style="display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;"
                                title="<?= htmlspecialchars($v['address'] ?? '') ?>">
                                <?= !empty($v['address']) ? htmlspecialchars($v['address']) : '—' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="row-actions">
                                <button type="button" class="row-action-btn edit editBtn" title="Edit Vendor"
                                    data-bs-toggle="modal" data-bs-target="#editVendorModal"
                                    data-id="<?= $v['id'] ?>"
                                    data-code="<?= htmlspecialchars($v['code']    ?? '') ?>"
                                    data-name="<?= htmlspecialchars($v['name']    ?? '') ?>"
                                    data-email="<?= htmlspecialchars($v['email']   ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($v['phone']   ?? '') ?>"
                                    data-address="<?= htmlspecialchars($v['address'] ?? '') ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="row-action-btn delete" title="Delete Vendor"
                                    onclick="openDeleteModal(<?= $v['id'] ?>,'<?= addslashes(htmlspecialchars($v['name'] ?? '')) ?>','<?= addslashes(htmlspecialchars($v['email'] ?? '')) ?>','<?= addslashes(htmlspecialchars($v['code'] ?? '—')) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="table-footer-bar d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <span>
                        <i class="bi bi-list-ul me-1"></i>
                        Showing <strong><?= mysqli_num_rows($vendors) ?></strong> of
                        <strong><?= $total ?></strong> product<?= $total != 1 ? 's' : '' ?>
                    </span>

                    <div class="d-flex align-items-center gap-2">
                        <!-- Pagination -->
                        <?php if ($pages > 1): ?>
                            <ul class="pagination pagination-sm mb-0">

                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&per_page=<?= $per_page ?>">Prev</a>
                                </li>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($pages, $page + 2);
                                for ($p = $start; $p <= $end; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $p ?>&per_page=<?= $per_page ?>">
                                            <?= $p ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= min($pages, $page + 1) ?>&per_page=<?= $per_page ?>">Next</a>
                                </li>

                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ADD VENDOR MODAL -->
<div class="modal fade" id="newVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
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
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Vendor Name <span class="text-danger">*</span></div>
                        <input type="text" name="name" class="form-control modal-input" placeholder="e.g. Acme Supplies Inc." required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email</div>
                        <input type="email" name="email" class="form-control modal-input" placeholder="e.g. contact@vendor.com">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="phone" class="form-control modal-input" placeholder="e.g. +1 555 000 0000">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Address</div>
                        <input type="text" name="address" class="form-control modal-input" placeholder="Street, City, State, ZIP">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" name="add_vendor" class="btn btn-sm px-4"
                    style="background:#ea580c;color:#fff;border-color:#c2410c;">
                    <i class="bi bi-plus-circle me-1"></i>Save Vendor
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT VENDOR MODAL -->
<div class="modal fade" id="editVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_vendor_id" id="edit_vendor_id">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit Vendor</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update vendor details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-3"
                    style="background:#fff7ed;border:1px solid #fed7aa;">
                    <i class="bi bi-building" style="color:#c2410c;font-size:.9rem;"></i>
                    <span style="font-size:.78rem;color:#9a3412;font-weight:600;">
                        Vendor # &nbsp;<strong id="editModalCode">—</strong>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Vendor Name <span class="text-danger">*</span></div>
                        <input type="text" name="edit_name" id="edit_name" class="form-control modal-input" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email</div>
                        <input type="email" name="edit_email" id="edit_email" class="form-control modal-input">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="edit_phone" id="edit_phone" class="form-control modal-input">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Address</div>
                        <input type="text" name="edit_address" id="edit_address" class="form-control modal-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-sm px-4"
                    style="background:#ea580c;color:#fff;border-color:#c2410c;">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE VENDOR MODAL -->
<div class="modal fade" id="deleteVendorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-building-fill"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete Vendor</div>
                            <div class="delete-modal-sub" id="deleteVendorModalSub">Confirm vendor removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-cust-chip mb-3">
                    <div class="delete-cust-avatar" id="deleteVendorAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-cust-name" id="deleteVendorName">—</div>
                        <div class="delete-cust-meta" id="deleteVendorEmail">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Vendor #</span>
                                <span class="chip-detail-value" id="deleteVendorCode">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong> The vendor record will be permanently removed from your directory.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                <a href="#" id="deleteVendorConfirmBtn" class="btn-delete-confirm"><i class="bi bi-trash-fill"></i> Yes, Delete Vendor</a>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('edit_vendor_id').value      = this.dataset.id;
        document.getElementById('edit_name').value           = this.dataset.name;
        document.getElementById('edit_email').value          = this.dataset.email;
        document.getElementById('edit_phone').value          = this.dataset.phone;
        document.getElementById('edit_address').value        = this.dataset.address;
        document.getElementById('editModalSub').textContent  = 'Editing: ' + this.dataset.name;
        document.getElementById('editModalCode').textContent = this.dataset.code || '—';
    });
});

function openDeleteModal(id, name, email, code) {
    document.getElementById('deleteVendorModalSub').textContent = 'Removing: ' + name;
    document.getElementById('deleteVendorAvatar').textContent   = name ? name.charAt(0).toUpperCase() : '?';
    document.getElementById('deleteVendorName').textContent     = name  || '—';
    document.getElementById('deleteVendorEmail').textContent    = email || '—';
    document.getElementById('deleteVendorCode').textContent     = code  || '—';
    document.getElementById('deleteVendorEmail').style.display  = email ? '' : 'none';
    document.getElementById('deleteVendorConfirmBtn').href      = '?delete=' + id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteVendorModal')).show();
}

document.getElementById('vd_search').addEventListener('input', function () {
    const q    = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#vdBody tr');
    let visible = 0;
    rows.forEach(row => {
        const match = !q
            || (row.dataset.name    || '').includes(q)
            || (row.dataset.email   || '').includes(q)
            || (row.dataset.phone   || '').includes(q)
            || (row.dataset.address || '').includes(q);
        row.classList.toggle('search-hidden', !match);
        if (match) visible++;
    });
    document.getElementById('visibleCount').textContent  = visible;
    document.getElementById('filteredCount').textContent = visible;
    document.getElementById('noMatchMsg').style.display  = visible === 0 ? 'inline' : 'none';
});
</script>
<?php endif; ?>
<?php include("../templates/footer.php"); ?>