<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

/* ─────────────────── Pagination setup ─────────────────── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ---------- ADD ---------- */
if (isset($_POST['add_shipTo'])) {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $title   = mysqli_real_escape_string($conn, trim($_POST['title']));
    $company = mysqli_real_escape_string($conn, trim($_POST['company']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    mysqli_query($conn, "INSERT INTO shipto (name, title, company, email, phone, address)
                         VALUES ('$name','$title','$company','$email','$phone','$address')");
    header("Location: shipTo.php?added=1");
    exit;
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit_shipto_id'])) {
    $id      = (int)$_POST['edit_shipto_id'];
    $name    = mysqli_real_escape_string($conn, trim($_POST['edit_name']));
    $title   = mysqli_real_escape_string($conn, trim($_POST['edit_title']));
    $company = mysqli_real_escape_string($conn, trim($_POST['edit_company']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['edit_email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['edit_phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['edit_address']));
    mysqli_query($conn, "UPDATE shipto
                         SET name='$name', title='$title', company='$company',
                             email='$email', phone='$phone', address='$address'
                         WHERE id=$id");
    header("Location: shipTo.php");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM shipto WHERE id=$id");
    header("Location: shipTo.php?deleted=1");
    exit;
}

/* ---------- TABLE FUNCTION (supports pagination + search) ---------- */
function getShipToTable($conn, $search = "", $page = 1, $per_page = 10)
{
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $whereClause = "WHERE 1=1";
    if (!empty($search)) {
        $s = mysqli_real_escape_string($conn, trim($search));
        $whereClause .= " AND (name LIKE '%$s%' OR title LIKE '%$s%' OR company LIKE '%$s%'
                              OR email LIKE '%$s%' OR phone LIKE '%$s%' OR address LIKE '%$s%')";
    }

    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM shipto $whereClause"));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $result = mysqli_query($conn, "SELECT * FROM shipto $whereClause ORDER BY id DESC LIMIT $offset, $per_page");
    $rows   = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    $count  = count($rows);

    ob_start();

    if ($count === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-truck"></i></div>
            <div class="empty-state-title">No Ship To Records Found</div>
            <div class="empty-state-sub">
                <?= !empty($search) ? 'No records match your search criteria' : 'Add your first shipping destination to get started' ?>
            </div>
            <?php if (empty($search)): ?>
            <button class="page-action-btn primary mt-3"
                data-bs-toggle="modal" data-bs-target="#newShipToModal">
                <i class="bi bi-plus-circle-fill"></i>Add Ship To
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table st-table mb-0">
                <thead>
                    <tr>
                        <th>Ship To #</th>
                        <th>Name</th>
                        <th>Title</th>
                        <th class="company-hide">Company</th>
                        <th class="email-hide">Email</th>
                        <th>Phone</th>
                        <th class="addr-hide">Address</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="stBody">
                <?php foreach ($rows as $st): ?>
                <tr data-name="<?= strtolower(htmlspecialchars($st['name']    ?? '')) ?>"
                    data-title="<?= strtolower(htmlspecialchars($st['title']   ?? '')) ?>"
                    data-company="<?= strtolower(htmlspecialchars($st['company'] ?? '')) ?>"
                    data-email="<?= strtolower(htmlspecialchars($st['email']   ?? '')) ?>"
                    data-phone="<?= strtolower(htmlspecialchars($st['phone']   ?? '')) ?>"
                    data-address="<?= strtolower(htmlspecialchars($st['address'] ?? '')) ?>">
                    <td><span class="ref-badge ref-badge-indigo"><?= htmlspecialchars($st['code'] ?? '—') ?></span></td>
                    <td>
                        <div class="st-cell">
                            <div class="st-avatar"><?= strtoupper(substr($st['name'] ?? '?', 0, 1)) ?></div>
                            <span class="st-name"><?= htmlspecialchars($st['name'] ?? '—') ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($st['title'])): ?>
                        <span class="title-tag"><?= htmlspecialchars($st['title']) ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="company-hide">
                        <?php if (!empty($st['company'])): ?>
                        <span class="company-tag"><i class="bi bi-building"></i><?= htmlspecialchars($st['company']) ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="email-hide">
                        <?php if (!empty($st['email'])): ?>
                        <span class="contact-cell"><i class="bi bi-envelope"></i><?= htmlspecialchars($st['email']) ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($st['phone'])): ?>
                        <span class="contact-cell"><i class="bi bi-telephone"></i><?= htmlspecialchars($st['phone']) ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="addr-hide">
                        <span class="addr-text"
                            style="display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;"
                            title="<?= htmlspecialchars($st['address'] ?? '') ?>">
                            <?= !empty($st['address']) ? htmlspecialchars($st['address']) : '—' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="row-actions">
                            <button type="button" class="row-action-btn edit editBtn" title="Edit"
                                data-bs-toggle="modal" data-bs-target="#editShipToModal"
                                data-id="<?= $st['id'] ?>"
                                data-code="<?= htmlspecialchars($st['code']    ?? '') ?>"
                                data-name="<?= htmlspecialchars($st['name']    ?? '') ?>"
                                data-title="<?= htmlspecialchars($st['title']   ?? '') ?>"
                                data-company="<?= htmlspecialchars($st['company'] ?? '') ?>"
                                data-email="<?= htmlspecialchars($st['email']   ?? '') ?>"
                                data-phone="<?= htmlspecialchars($st['phone']   ?? '') ?>"
                                data-address="<?= htmlspecialchars($st['address'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="row-action-btn delete" title="Delete"
                                onclick="openDeleteModal(
                                    <?= $st['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($st['name']  ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($st['title'] ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($st['email'] ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($st['code']  ?? '—')) ?>'
                                )">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-muted" style="font-size:.78rem;">
                <i class="bi bi-list-ul me-1"></i>
                Showing <strong><?= $count ?></strong> of <strong><?= $total ?></strong>
                record<?= $total !== 1 ? 's' : '' ?>
            </span>

            <?php if ($pages > 1): ?>
            <nav aria-label="Ship To pagination">
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

/* ---------- AJAX ENTRY POINT ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getShipToTable($conn, $_GET['st_search'] ?? '', $page, $per_page);
    exit;
}

/* ---------- STAT COUNTS ---------- */
$cnt_total        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM shipto"))['c'];
$cnt_with_email   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM shipto WHERE email   IS NOT NULL AND email   != ''"))['c'];
$cnt_with_phone   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM shipto WHERE phone   IS NOT NULL AND phone   != ''"))['c'];
$cnt_with_company = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM shipto WHERE company IS NOT NULL AND company != ''"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper">

    <!-- ALERTS -->
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3"
        style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Ship To record deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3"
        style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Ship To record added successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-truck"></i>Ship To
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="st_search"
                        value="<?= htmlspecialchars($_GET['st_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search name, company, email…">
                </div>
                <button class="page-action-btn primary"
                    data-bs-toggle="modal" data-bs-target="#newShipToModal">
                    <i class="bi bi-plus-circle-fill"></i>New Ship To
                </button>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#4f46e5;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Total Ship To</div>
                        <div class="cust-stat-num" style="color:#4f46e5;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#e0e7ff;color:#4f46e5;">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#7c3aed;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Company</div>
                        <div class="cust-stat-num" style="color:#7c3aed;"><?= $cnt_with_company ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#ede9fe;color:#7c3aed;">
                        <i class="bi bi-building-fill"></i>
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
            <div class="cust-stat-card" style="--card-color:#f59e0b;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Phone</div>
                        <div class="cust-stat-num" style="color:#f59e0b;"><?= $cnt_with_phone ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#fef3c7;color:#f59e0b;">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="st-table-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Ship To records
                </span>
            </div>
            <div class="d-flex gap-2">
                <select id="perPage" class="form-select form-select-sm"
                        style="min-width:90px;font-size:.8rem;">
                    <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10 / page</option>
                    <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20 / page</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50 / page</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="stTable" style="overflow-x:auto;overflow-y:visible;min-height:300px;">
                <?= getShipToTable($conn, $_GET['st_search'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ADD MODAL -->
<div class="modal fade" id="newShipToModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
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
                        <input type="text" name="name" class="form-control modal-input" placeholder="e.g. John Smith" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Position</div>
                        <input type="text" name="title" class="form-control modal-input" placeholder="e.g. Warehouse Manager">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Company <span class="text-danger">*</span></div>
                        <input type="text" name="company" class="form-control modal-input" placeholder="e.g. Acme Logistics" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email</div>
                        <input type="email" name="email" class="form-control modal-input" placeholder="e.g. john@company.com">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="phone" class="form-control modal-input" placeholder="e.g. +1 555 000 0000">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Address <span class="text-danger">*</span></div>
                        <textarea name="address" rows="2" class="form-control modal-input"
                            placeholder="Street, City, State, ZIP, Country" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" name="add_shipTo" class="btn btn-sm px-4"
                    style="background:#4f46e5;color:#fff;border-color:#4338ca;">
                    <i class="bi bi-plus-circle me-1"></i>Save Ship To
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editShipToModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_shipto_id" id="edit_shipto_id">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit Ship To</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update shipping details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-3"
                    style="background:#eef2ff;border:1px solid #c7d2fe;">
                    <i class="bi bi-truck" style="color:#4338ca;font-size:.9rem;"></i>
                    <span style="font-size:.78rem;color:#3730a3;font-weight:600;">
                        Ship To # &nbsp;<strong id="editModalCode">—</strong>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
                        <input type="text" name="edit_name" id="edit_name" class="form-control modal-input" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Position</div>
                        <input type="text" name="edit_title" id="edit_title" class="form-control modal-input">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Company <span class="text-danger">*</span></div>
                        <input type="text" name="edit_company" id="edit_company" class="form-control modal-input" required>
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
                        <div class="modal-label">Address <span class="text-danger">*</span></div>
                        <textarea name="edit_address" id="edit_address" rows="2"
                            class="form-control modal-input" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-sm px-4"
                    style="background:#4f46e5;color:#fff;border-color:#4338ca;">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteShipToModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-truck"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete Ship To</div>
                            <div class="delete-modal-sub" id="deleteCustModalSub">Confirm removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-cust-chip mb-3">
                    <div class="delete-cust-avatar" id="deleteCustAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-cust-name" id="deleteCustName">—</div>
                        <div class="delete-cust-meta" id="deleteCustTitle">—</div>
                        <div class="delete-cust-meta" id="deleteCustEmail">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Ship To #</span>
                                <span class="chip-detail-value" id="deleteCustCode">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong>
                    The ship-to record will be permanently removed from your directory.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3"
                style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteCustConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Ship To
                </a>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════
   AJAX PAGINATION & SEARCH
══════════════════════════════════════════ */
let searchTimer;

function loadTable(search, page) {
    document.getElementById('stTable').innerHTML =
        '<div class="text-center py-5">' +
            '<div class="spinner-border text-primary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
            '<p class="text-muted small mt-2">Loading...</p>' +
        '</div>';

    const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;
    let url = 'shipTo.php?ajax=1&page=' + page + '&per_page=' + perPage;
    if (search) url += '&st_search=' + encodeURIComponent(search);

    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById('stTable').innerHTML = html;
            initEditButtons();
            updateFilteredCount(html);

            /* push state */
            let qs = [];
            if (search) qs.push('st_search=' + encodeURIComponent(search));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
}

/* Extract total from "Showing X of Y" and sync stat card (if present) */
function updateFilteredCount(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const strongTags = tmp.querySelectorAll('.table-footer-bar strong');
    if (strongTags.length >= 2) {
        const el = document.getElementById('filteredCount');
        if (el) el.textContent = strongTags[1].textContent;
    }
}

/* ── Search ── */
document.getElementById('st_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    searchTimer = setTimeout(() => loadTable(q, 1), 350);
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('#st_search')) {
        e.preventDefault();
        clearTimeout(searchTimer);
        loadTable(e.target.value.trim(), 1);
    }
});

/* ── Pagination clicks (delegated – works after AJAX re-render) ── */
document.addEventListener('click', function (e) {
    const pg = e.target.closest('#stTable .pagination .page-link');
    if (!pg || !pg.dataset.page) return;
    e.preventDefault();
    const page   = parseInt(pg.dataset.page, 10) || 1;
    const search = document.getElementById('st_search').value.trim();
    loadTable(search, page);
});

/* ── Per-page change ── */
document.getElementById('perPage').addEventListener('change', function () {
    const search = document.getElementById('st_search').value.trim();
    loadTable(search, 1);
});

/* ══════════════════════════════════════════
   EDIT MODAL – re-init after AJAX re-render
══════════════════════════════════════════ */
function initEditButtons() {
    document.querySelectorAll('.editBtn').forEach(btn => {
        const fresh = btn.cloneNode(true);
        btn.replaceWith(fresh);
        fresh.addEventListener('click', function () {
            document.getElementById('edit_shipto_id').value     = this.dataset.id;
            document.getElementById('edit_name').value          = this.dataset.name;
            document.getElementById('edit_title').value         = this.dataset.title;
            document.getElementById('edit_company').value       = this.dataset.company;
            document.getElementById('edit_email').value         = this.dataset.email;
            document.getElementById('edit_phone').value         = this.dataset.phone;
            document.getElementById('edit_address').value       = this.dataset.address;
            document.getElementById('editModalSub').textContent = 'Editing: ' + this.dataset.name;
            document.getElementById('editModalCode').textContent = this.dataset.code || '—';
        });
    });
}

/* Initial bind on page load */
document.addEventListener('DOMContentLoaded', initEditButtons);

/* ══════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════ */
function openDeleteModal(id, name, title, email, code) {
    document.getElementById('deleteCustModalSub').textContent = 'Removing: ' + name;
    document.getElementById('deleteCustAvatar').textContent   = name ? name.charAt(0).toUpperCase() : '?';
    document.getElementById('deleteCustName').textContent     = name  || '—';
    document.getElementById('deleteCustTitle').textContent    = title || '—';
    document.getElementById('deleteCustEmail').textContent    = email || '—';
    document.getElementById('deleteCustCode').textContent     = code  || '—';
    document.getElementById('deleteCustTitle').style.display  = title ? '' : 'none';
    document.getElementById('deleteCustEmail').style.display  = email ? '' : 'none';
    document.getElementById('deleteCustConfirmBtn').href      = '?delete=' + id;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteShipToModal')).show();
}
</script>

<?php include("../templates/footer.php"); ?>