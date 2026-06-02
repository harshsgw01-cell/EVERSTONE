<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

/* ---------- PAGINATION SETUP ---------- */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page']     ?? 1));

/* ---------- ADD ---------- */
if (isset($_POST['add_salesPerson'])) {
    $name  = mysqli_real_escape_string($conn, trim($_POST['name']));
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));

    $signaturePath = null;
    if (!empty($_FILES['signature']['name'])) {
        $uploadDir = "../uploads/signatures/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName   = time() . "_" . basename($_FILES['signature']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['signature']['tmp_name'], $targetFile)) {
            $signaturePath = "uploads/signatures/" . $fileName;
        }
    }

    $sig = $signaturePath ? "'" . mysqli_real_escape_string($conn, $signaturePath) . "'" : "NULL";
    mysqli_query($conn, "INSERT INTO salesperson (name, title, email, phone, signature)
                         VALUES ('$name','$title','$email','$phone',$sig)");
    header("Location: salesPerson.php");
    exit;
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit_sp_id'])) {
    $id    = (int)$_POST['edit_sp_id'];
    $name  = mysqli_real_escape_string($conn, trim($_POST['edit_name']));
    $title = mysqli_real_escape_string($conn, trim($_POST['edit_title']));
    $email = mysqli_real_escape_string($conn, trim($_POST['edit_email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['edit_phone']));

    if (!empty($_FILES['edit_signature']['name'])) {
        $uploadDir = "../uploads/signatures/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT signature FROM salesperson WHERE id=$id"));
        if ($old && !empty($old['signature']) && file_exists("../" . $old['signature'])) {
            unlink("../" . $old['signature']);
        }

        $fileName   = time() . "_" . basename($_FILES['edit_signature']['name']);
        $targetFile = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['edit_signature']['tmp_name'], $targetFile)) {
            $newSig = mysqli_real_escape_string($conn, "uploads/signatures/" . $fileName);
            mysqli_query($conn, "UPDATE salesperson
                                 SET name='$name', title='$title', email='$email', phone='$phone',
                                     signature='$newSig'
                                 WHERE id=$id");
        }
    } else {
        mysqli_query($conn, "UPDATE salesperson
                             SET name='$name', title='$title', email='$email', phone='$phone'
                             WHERE id=$id");
    }

    header("Location: salesPerson.php");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $sig = mysqli_fetch_assoc(mysqli_query($conn, "SELECT signature FROM salesperson WHERE id=$id"));
    if ($sig && !empty($sig['signature']) && file_exists("../" . $sig['signature'])) {
        unlink("../" . $sig['signature']);
    }
    mysqli_query($conn, "DELETE FROM salesperson WHERE id=$id");
    header("Location: salesPerson.php?deleted=1");
    exit;
}

/* ---------- SALES PERSONS TABLE FUNCTION ---------- */
function getSalesPersonsTable($conn, $search = "", $page = 1, $per_page = 20) {
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $where = "WHERE 1=1";
    if (trim($search) !== '') {
        $s = mysqli_real_escape_string($conn, trim($search));
        $where .= " AND (name LIKE '%$s%' OR email LIKE '%$s%' OR title LIKE '%$s%')";
    }

    /* -- total count for pagination -- */
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM salesperson $where"));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $result = mysqli_query($conn, "SELECT * FROM salesperson $where ORDER BY id DESC LIMIT $offset, $per_page");
    $count  = mysqli_num_rows($result);

    ob_start();

    if ($count === 0): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-person-badge"></i></div>
        <div class="empty-state-title">No Sales Persons Found</div>
        <div class="empty-state-sub">Try adjusting your search criteria</div>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table sp-table mb-0">
            <thead>
                <tr>
                    <th>SP #</th>
                    <th>Name</th>
                    <th>Title</th>
                    <th class="email-hide">Email</th>
                    <th>Phone</th>
                    <th>Signature</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="spBody">
            <?php while ($sp = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td>
                    <span class="ref-badge ref-badge-rose">
                        <?= htmlspecialchars($sp['code'] ?? '—') ?>
                    </span>
                </td>
                <td>
                    <div class="sp-cell">
                        <div class="sp-avatar">
                            <?= strtoupper(substr($sp['name'] ?? '?', 0, 1)) ?>
                        </div>
                        <span class="sp-name"><?= htmlspecialchars($sp['name'] ?? '—') ?></span>
                    </div>
                </td>
                <td>
                    <?php if (!empty($sp['title'])): ?>
                    <span class="title-tag"><?= htmlspecialchars($sp['title']) ?></span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="email-hide">
                    <?php if (!empty($sp['email'])): ?>
                    <span class="contact-cell">
                        <i class="bi bi-envelope"></i>
                        <?= htmlspecialchars($sp['email']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($sp['phone'])): ?>
                    <span class="contact-cell">
                        <i class="bi bi-telephone"></i>
                        <?= htmlspecialchars($sp['phone']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($sp['signature'])): ?>
                    <img src="../<?= htmlspecialchars($sp['signature']) ?>"
                        class="sig-preview sig-lightbox-trigger"
                        alt="Signature"
                        data-src="../<?= htmlspecialchars($sp['signature']) ?>"
                        title="Click to enlarge">
                    <?php else: ?>
                    <span class="sig-none">
                        <i class="bi bi-slash-circle"></i>None
                    </span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <div class="row-actions">
                        <!-- EDIT -->
                        <button type="button"
                            class="row-action-btn edit editBtn"
                            title="Edit"
                            data-bs-toggle="modal"
                            data-bs-target="#editSalesPersonModal"
                            data-id="<?= $sp['id'] ?>"
                            data-code="<?= htmlspecialchars($sp['code']  ?? '') ?>"
                            data-name="<?= htmlspecialchars($sp['name']  ?? '') ?>"
                            data-title="<?= htmlspecialchars($sp['title'] ?? '') ?>"
                            data-email="<?= htmlspecialchars($sp['email'] ?? '') ?>"
                            data-phone="<?= htmlspecialchars($sp['phone'] ?? '') ?>"
                            data-sig="<?= !empty($sp['signature']) ? htmlspecialchars('../' . $sp['signature']) : '' ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <!-- DELETE -->
                        <button type="button"
                            class="row-action-btn delete"
                            title="Delete Sales Person"
                            onclick="openDeleteModal(
                                <?= $sp['id'] ?>,
                                '<?= addslashes(htmlspecialchars($sp['name']  ?? '')) ?>',
                                '<?= addslashes(htmlspecialchars($sp['title'] ?? '')) ?>',
                                '<?= addslashes(htmlspecialchars($sp['email'] ?? '')) ?>',
                                '<?= addslashes(htmlspecialchars($sp['code']  ?? '—')) ?>'
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
            sales person<?= $total !== 1 ? 's' : '' ?>
        </span>

        <?php if ($pages > 1): ?>
        <nav aria-label="Sales person pagination">
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
    echo getSalesPersonsTable($conn, $_GET['sp_search'] ?? '', $page, $per_page);
    exit;
}

/* ---------- COUNTS ---------- */
$cnt_total      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM salesperson"))['c'];
$cnt_with_sig   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM salesperson WHERE signature IS NOT NULL AND signature != ''"))['c'];
$cnt_with_email = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM salesperson WHERE email IS NOT NULL AND email != ''"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper">

    <!-- ── ALERTS ── -->
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Sales person deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-person-badge-fill"></i>
                    Sales Persons
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="sp_search"
                        value="<?= htmlspecialchars($_GET['sp_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search name, email, title…">
                </div>
                <button class="page-action-btn primary"
                    data-bs-toggle="modal" data-bs-target="#newSalesPersonModal">
                    <i class="bi bi-person-plus-fill"></i>New Sales Person
                </button>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="cust-stat-card" style="--card-color:#e11d48;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Total Sales Persons</div>
                        <div class="cust-stat-num" style="color:#e11d48;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#ffe4e6;color:#e11d48;">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="cust-stat-card" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Email</div>
                        <div class="cust-stat-num" style="color:#10b981;"><?= $cnt_with_email ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="cust-stat-card" style="--card-color:#7c3aed;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Signature</div>
                        <div class="cust-stat-num" style="color:#7c3aed;"><?= $cnt_with_sig ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#ede9fe;color:#7c3aed;">
                        <i class="bi bi-pen-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="sp-table-card card border-0">
        <!-- Card header with per-page selector -->
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Sales Persons
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
            <div id="spTable" style="overflow-x:auto;min-height:300px;">
                <?= getSalesPersonsTable($conn, $_GET['sp_search'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ══════════════════════════════════════════
     SIGNATURE LIGHTBOX
══════════════════════════════════════════ -->
<div id="sigLightbox" onclick="closeLightbox()">
    <span class="close-lb">&times;</span>
    <img id="lightboxImg" src="" alt="Signature">
</div>

<!-- ══════════════════════════════════════════
     ADD MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="newSalesPersonModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#e11d48,#be123c);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-person-plus-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New Sales Person</h5>
                        <small class="text-white opacity-75">Fill in the sales person details below</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
                        <input type="text" name="name" class="form-control modal-input"
                            placeholder="e.g. Jane Doe" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Role</div>
                        <input type="text" name="title" class="form-control modal-input"
                            placeholder="e.g. Senior Sales Rep">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email <span class="text-danger">*</span></div>
                        <input type="email" name="email" class="form-control modal-input"
                            placeholder="e.g. jane@company.com" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="phone" class="form-control modal-input"
                            placeholder="e.g. +1 555 000 0000">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Signature Image</div>
                        <div class="file-input-wrap">
                            <input type="file" name="signature" accept="image/*" id="addSigInput">
                        </div>
                        <div id="addSigPreviewWrap" style="display:none;margin-top:10px;">
                            <div class="modal-label mb-1">Preview</div>
                            <img id="addSigPreview"
                                style="height:48px;border-radius:8px;background:#f8fafc;
                                       border:1px solid #e2e8f0;padding:4px;object-fit:contain;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                    data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" name="add_salesPerson"
                    class="btn btn-sm px-4"
                    style="background:#e11d48;color:#fff;border-color:#be123c;">
                    <i class="bi bi-person-plus me-1"></i>Save Sales Person
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="editSalesPersonModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="edit_sp_id" id="edit_sp_id">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit Sales Person</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <!-- SP # badge -->
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-3"
                    style="background:#fff1f2;border:1px solid #fecdd3;">
                    <i class="bi bi-person-badge" style="color:#be123c;font-size:.9rem;"></i>
                    <span style="font-size:.78rem;color:#9f1239;font-weight:600;">
                        Sales Person # &nbsp;<strong id="editModalCode">—</strong>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
                        <input type="text" name="edit_name" id="edit_name"
                            class="form-control modal-input"
                            placeholder="e.g. Jane Doe" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Title / Role</div>
                        <input type="text" name="edit_title" id="edit_title"
                            class="form-control modal-input"
                            placeholder="e.g. Senior Sales Rep">
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email <span class="text-danger">*</span></div>
                        <input type="email" name="edit_email" id="edit_email"
                            class="form-control modal-input"
                            placeholder="e.g. jane@company.com" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Phone</div>
                        <input type="tel" name="edit_phone" id="edit_phone"
                            class="form-control modal-input"
                            placeholder="e.g. +1 555 000 0000">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Replace Signature</div>
                        <!-- Current signature preview -->
                        <div id="currentSigWrap" class="current-sig-wrap" style="display:none;">
                            <img id="currentSigImg" src="" alt="Current Signature">
                            <div>
                                <div class="current-sig-label">Current Signature</div>
                                <div class="current-sig-hint">Upload a new file below to replace it</div>
                            </div>
                        </div>
                        <div class="file-input-wrap">
                            <input type="file" name="edit_signature" accept="image/*" id="editSigInput">
                        </div>
                        <div id="editSigPreviewWrap" style="display:none;margin-top:10px;">
                            <div class="modal-label mb-1">New Signature Preview</div>
                            <img id="editSigPreview"
                                style="height:48px;border-radius:8px;background:#f8fafc;
                                       border:1px solid #e2e8f0;padding:4px;object-fit:contain;">
                        </div>
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
                    style="background:#e11d48;color:#fff;border-color:#be123c;">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DELETE SALES PERSON MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="deleteSpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap">
                            <i class="bi bi-person-dash-fill"></i>
                        </div>
                        <div>
                            <div class="delete-modal-title">Delete Sales Person</div>
                            <div class="delete-modal-sub" id="deleteSpModalSub">Confirm removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-sp-chip mb-3">
                    <div class="delete-sp-avatar" id="deleteSpAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-sp-name"  id="deleteSpName">—</div>
                        <div class="delete-sp-meta"  id="deleteSpTitle">—</div>
                        <div class="delete-sp-meta"  id="deleteSpEmail">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">SP #</span>
                                <span class="chip-detail-value" id="deleteSpCode">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        This action is <strong>permanent and cannot be undone.</strong>
                        The sales person record and their signature will be permanently removed.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteSpConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Sales Person
                </a>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════
   INIT — re-run after every AJAX render
══════════════════════════════════════════ */
function initAll() {
    initEditButtons();
    initLightboxTriggers();
}

/* ── Edit modal — populate & re-attach after AJAX ── */
function initEditButtons() {
    document.querySelectorAll('.editBtn').forEach(btn => {
        const fresh = btn.cloneNode(true);
        btn.replaceWith(fresh);
        fresh.addEventListener('click', function () {
            document.getElementById('edit_sp_id').value         = this.dataset.id;
            document.getElementById('edit_name').value          = this.dataset.name;
            document.getElementById('edit_title').value         = this.dataset.title;
            document.getElementById('edit_email').value         = this.dataset.email;
            document.getElementById('edit_phone').value         = this.dataset.phone;
            document.getElementById('editModalSub').textContent  = 'Editing: '       + this.dataset.name;
            document.getElementById('editModalCode').textContent = this.dataset.code || '—';

            // reset new-sig upload + preview
            document.getElementById('editSigInput').value          = '';
            document.getElementById('editSigPreviewWrap').style.display = 'none';

            // show or hide current sig
            const sigWrap = document.getElementById('currentSigWrap');
            const sigImg  = document.getElementById('currentSigImg');
            if (this.dataset.sig) {
                sigImg.src              = this.dataset.sig;
                sigWrap.style.display   = 'flex';
            } else {
                sigWrap.style.display   = 'none';
            }
        });
    });
}

/* ── Signature lightbox triggers — re-attach after AJAX ── */
function initLightboxTriggers() {
    document.querySelectorAll('.sig-lightbox-trigger').forEach(img => {
        const fresh = img.cloneNode(true);
        img.replaceWith(fresh);
        fresh.addEventListener('click', function () {
            openLightbox(this.dataset.src || this.src);
        });
        fresh.style.cursor = 'pointer';
    });
}

/* ── Add modal — signature file preview ── */
document.getElementById('addSigInput').addEventListener('change', function () {
    previewFile(this, 'addSigPreview', 'addSigPreviewWrap');
});

/* ── Edit modal — new signature file preview ── */
document.getElementById('editSigInput').addEventListener('change', function () {
    previewFile(this, 'editSigPreview', 'editSigPreviewWrap');
});

function previewFile(input, imgId, wrapId) {
    const wrap = document.getElementById(wrapId);
    const img  = document.getElementById(imgId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    } else {
        wrap.style.display = 'none';
    }
}

/* ── Signature lightbox ── */
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('sigLightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('sigLightbox').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

/* ══════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════ */
function openDeleteModal(id, name, title, email, code) {
    document.getElementById('deleteSpModalSub').textContent  = 'Removing: ' + name;
    document.getElementById('deleteSpAvatar').textContent    = name ? name.charAt(0).toUpperCase() : '?';
    document.getElementById('deleteSpName').textContent      = name  || '—';
    document.getElementById('deleteSpTitle').textContent     = title || '—';
    document.getElementById('deleteSpEmail').textContent     = email || '—';
    document.getElementById('deleteSpCode').textContent      = code  || '—';
    document.getElementById('deleteSpTitle').style.display   = title ? '' : 'none';
    document.getElementById('deleteSpEmail').style.display   = email ? '' : 'none';
    document.getElementById('deleteSpConfirmBtn').href        = '?delete=' + id;
    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('deleteSpModal')
    ).show();
}

/* ══════════════════════════════════════════
   TABLE AJAX + PAGINATION
══════════════════════════════════════════ */
let searchTimer;

function loadTable(search, page = 1) {
    document.getElementById('spTable').innerHTML =
        '<div class="text-center py-5">' +
        '<div class="spinner-border text-secondary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
        '<p class="text-muted small mt-2">Loading...</p></div>';

    const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;

    let url = 'salesPerson.php?ajax=1&page=' + page + '&per_page=' + perPage;
    if (search) url += '&sp_search=' + encodeURIComponent(search);

    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById('spTable').innerHTML = html;
            initAll(); // re-bind edit buttons + lightbox triggers

            // push state
            let qs = [];
            if (search) qs.push('sp_search=' + encodeURIComponent(search));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
}

/* Pagination click — delegated so it works after re-renders */
document.addEventListener('click', function (e) {
    const pg = e.target.closest('#spTable .pagination .page-link');
    if (!pg || !pg.dataset.page) return;
    e.preventDefault();
    const page   = parseInt(pg.dataset.page, 10) || 1;
    const search = document.getElementById('sp_search').value.trim();
    loadTable(search, page);
});

/* Per-page selector */
document.getElementById('perPage').addEventListener('change', function () {
    const search = document.getElementById('sp_search').value.trim();
    loadTable(search, 1);
});

/* Search */
document.getElementById('sp_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value;
    searchTimer = setTimeout(() => loadTable(q.trim(), 1), 350);
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('#sp_search')) {
        e.preventDefault();
        clearTimeout(searchTimer);
        loadTable(e.target.value.trim(), 1);
    }
});

/* Back/forward */
window.addEventListener('popstate', function () {
    const params = new URLSearchParams(window.location.search);
    const q  = params.get('sp_search') || '';
    const pg = parseInt(params.get('page') || '1', 10);
    document.getElementById('sp_search').value = q;
    loadTable(q, pg);
});

/* Init on first load */
document.addEventListener('DOMContentLoaded', initAll);
</script>

<?php include("../templates/footer.php"); ?>