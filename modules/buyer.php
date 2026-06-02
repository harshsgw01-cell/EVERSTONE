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
if (isset($_POST['add_buyer'])) {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    mysqli_query($conn, "INSERT INTO buyer (name, email, phone, address)
                         VALUES ('$name','$email','$phone','$address')");
    header("Location: buyer.php?added=1");
    exit;
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit_buyer_id'])) {
    $id      = (int)$_POST['edit_buyer_id'];
    $name    = mysqli_real_escape_string($conn, trim($_POST['edit_name']));
    $email   = mysqli_real_escape_string($conn, trim($_POST['edit_email']));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['edit_phone']));
    $address = mysqli_real_escape_string($conn, trim($_POST['edit_address']));
    mysqli_query($conn, "UPDATE buyer
                         SET name='$name', email='$email', phone='$phone', address='$address'
                         WHERE id=$id");
    header("Location: buyer.php");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM buyer WHERE id=$id");
    header("Location: buyer.php?deleted=1");
    exit;
}

/* ---------- TABLE FUNCTION (supports pagination + search) ---------- */
function getBuyerTable($conn, $search = "", $page = 1, $per_page = 10)
{
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $whereClause = "WHERE 1=1";
    if (!empty($search)) {
        $s = mysqli_real_escape_string($conn, trim($search));
        $whereClause .= " AND (name LIKE '%$s%' OR email LIKE '%$s%' OR phone LIKE '%$s%' OR address LIKE '%$s%')";
    }

    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM buyer $whereClause"));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $result = mysqli_query($conn, "SELECT * FROM buyer $whereClause ORDER BY id DESC LIMIT $offset, $per_page");
    $rows   = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    $count  = count($rows);

    ob_start();

    if ($count === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-bag-heart"></i></div>
            <div class="empty-state-title">No Buyers Found</div>
            <div class="empty-state-sub">
                <?= !empty($search) ? 'No buyers match your search criteria' : 'Add your first buyer to get started' ?>
            </div>
            <?php if (empty($search)): ?>
            <button class="page-action-btn primary mt-3"
                data-bs-toggle="modal" data-bs-target="#newBuyerModal">
                <i class="bi bi-person-plus-fill"></i>Add Buyer
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table by-table mb-0">
                <thead>
                    <tr>
                        <th>Buyer #</th>
                        <th>Name</th>
                        <th class="email-hide">Email</th>
                        <th>Phone</th>
                        <th class="addr-hide">Address</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="byBody">
                <?php foreach ($rows as $b): ?>
                <tr data-name="<?= strtolower(htmlspecialchars($b['name']    ?? '')) ?>"
                    data-email="<?= strtolower(htmlspecialchars($b['email']   ?? '')) ?>"
                    data-phone="<?= strtolower(htmlspecialchars($b['phone']   ?? '')) ?>"
                    data-address="<?= strtolower(htmlspecialchars($b['address'] ?? '')) ?>">
                    <td><span class="ref-badge ref-badge-teal"><?= htmlspecialchars($b['code'] ?? '—') ?></span></td>
                    <td>
                        <div class="by-cell">
                            <div class="by-avatar"><?= strtoupper(substr($b['name'] ?? '?', 0, 1)) ?></div>
                            <span class="by-name"><?= htmlspecialchars($b['name'] ?? '—') ?></span>
                        </div>
                    </td>
                    <td class="email-hide">
                        <?php if (!empty($b['email'])): ?>
                        <span class="contact-cell"><i class="bi bi-envelope"></i><?= htmlspecialchars($b['email']) ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($b['phone'])): ?>
                        <span class="contact-cell"><i class="bi bi-telephone"></i><?= htmlspecialchars($b['phone']) ?></span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="addr-hide">
                        <div class="addr-text">
                            <?php if (!empty($b['address'])): ?>
                            <span style="display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;"
                                title="<?= htmlspecialchars($b['address']) ?>">
                                <?= htmlspecialchars($b['address']) ?>
                            </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="row-actions">
                            <button type="button" class="row-action-btn edit editBtn" title="Edit Buyer"
                                data-bs-toggle="modal" data-bs-target="#editBuyerModal"
                                data-id="<?= $b['id'] ?>"
                                data-code="<?= htmlspecialchars($b['code']    ?? '') ?>"
                                data-name="<?= htmlspecialchars($b['name']    ?? '') ?>"
                                data-email="<?= htmlspecialchars($b['email']   ?? '') ?>"
                                data-phone="<?= htmlspecialchars($b['phone']   ?? '') ?>"
                                data-address="<?= htmlspecialchars($b['address'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="row-action-btn delete" title="Delete Buyer"
                                onclick="openDeleteModal(
                                    <?= $b['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($b['name']  ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($b['title'] ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($b['email'] ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($b['code']  ?? '—')) ?>'
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
                buyer<?= $total !== 1 ? 's' : '' ?>
            </span>

            <?php if ($pages > 1): ?>
            <nav aria-label="Buyer pagination">
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
    echo getBuyerTable($conn, $_GET['by_search'] ?? '', $page, $per_page);
    exit;
}

/* ---------- STAT COUNTS ---------- */
$cnt_total      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM buyer"))['c'];
$cnt_with_email = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM buyer WHERE email IS NOT NULL AND email != ''"))['c'];
$cnt_with_phone = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM buyer WHERE phone IS NOT NULL AND phone != ''"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>


<div class="page-wrapper">

    <!-- ALERTS -->
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3"
        style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Buyer deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3"
        style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Buyer added successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-bag-heart-fill"></i>Buyers
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="by_search"
                        value="<?= htmlspecialchars($_GET['by_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search name, email, phone…">
                </div>
                <button class="page-action-btn primary"
                    data-bs-toggle="modal" data-bs-target="#newBuyerModal">
                    <i class="bi bi-person-plus-fill"></i>New Buyer
                </button>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#0d9488;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Total Buyers</div>
                        <div class="cust-stat-num" style="color:#0d9488;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#ccfbf1;color:#0d9488;">
                        <i class="bi bi-bag-heart-fill"></i>
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
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#f59e0b;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Email</div>
                        <div class="cust-stat-num" style="color:#f59e0b;"><?= $cnt_with_email ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#fef3c7;color:#f59e0b;">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--card-color:#8b5cf6;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Phone</div>
                        <div class="cust-stat-num" style="color:#8b5cf6;"><?= $cnt_with_phone ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#ede9fe;color:#8b5cf6;">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="by-table-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Buyer records
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
            <div id="byTable" style="overflow-x:auto;overflow-y:visible;min-height:300px;">
                <?= getBuyerTable($conn, $_GET['by_search'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ADD BUYER MODAL -->
<div class="modal fade" id="newBuyerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0d9488,#0f766e);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-person-plus-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New Buyer</h5>
                        <small class="text-white opacity-75">Fill in the buyer details below</small>
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
                        <div class="modal-label">Email</div>
                        <input type="email" name="email" class="form-control modal-input" placeholder="e.g. john@company.com">
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
                <button type="submit" name="add_buyer" class="btn btn-sm px-4"
                    style="background:#0d9488;color:#fff;border-color:#0f766e;">
                    <i class="bi bi-person-plus me-1"></i>Save Buyer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT BUYER MODAL -->
<div class="modal fade" id="editBuyerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_buyer_id" id="edit_buyer_id">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit Buyer</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update buyer details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-3"
                    style="background:#f0fdfa;border:1px solid #99f6e4;">
                    <i class="bi bi-bag-heart" style="color:#0f766e;font-size:.9rem;"></i>
                    <span style="font-size:.78rem;color:#134e4a;font-weight:600;">
                        Buyer # &nbsp;<strong id="editModalCode">—</strong>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Full Name <span class="text-danger">*</span></div>
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
                    style="background:#0d9488;color:#fff;border-color:#0f766e;">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE BUYER MODAL -->
<div class="modal fade" id="deleteBuyerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-person-x-fill"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete Buyer</div>
                            <div class="delete-modal-sub" id="deleteCustModalSub">Confirm buyer removal</div>
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
                                <span class="chip-detail-label">Buyer #</span>
                                <span class="chip-detail-value" id="deleteCustCode">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong> The buyer record will be permanently removed from your directory.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3"
                style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteCustConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Buyer
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
    document.getElementById('byTable').innerHTML =
        '<div class="text-center py-5">' +
            '<div class="spinner-border text-primary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
            '<p class="text-muted small mt-2">Loading...</p>' +
        '</div>';

    const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;
    let url = 'buyer.php?ajax=1&page=' + page + '&per_page=' + perPage;
    if (search) url += '&by_search=' + encodeURIComponent(search);

    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById('byTable').innerHTML = html;
            initEditButtons();
            updateFilteredCount(html);

            /* push state */
            let qs = [];
            if (search) qs.push('by_search=' + encodeURIComponent(search));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
}

/* Extract total from "Showing X of Y" and sync stat card */
function updateFilteredCount(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const strongTags = tmp.querySelectorAll('.table-footer-bar strong');
    if (strongTags.length >= 2) {
        document.getElementById('filteredCount').textContent = strongTags[1].textContent;
    }
}

/* ── Search ── */
document.getElementById('by_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    searchTimer = setTimeout(() => loadTable(q, 1), 350);
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('#by_search')) {
        e.preventDefault();
        clearTimeout(searchTimer);
        loadTable(e.target.value.trim(), 1);
    }
});

/* ── Pagination clicks (delegated – works after AJAX re-render) ── */
document.addEventListener('click', function (e) {
    const pg = e.target.closest('#byTable .pagination .page-link');
    if (!pg || !pg.dataset.page) return;
    e.preventDefault();
    const page   = parseInt(pg.dataset.page, 10) || 1;
    const search = document.getElementById('by_search').value.trim();
    loadTable(search, page);
});

/* ── Per-page change ── */
document.getElementById('perPage').addEventListener('change', function () {
    const search = document.getElementById('by_search').value.trim();
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
            document.getElementById('edit_buyer_id').value       = this.dataset.id;
            document.getElementById('edit_name').value           = this.dataset.name;
            document.getElementById('edit_email').value          = this.dataset.email;
            document.getElementById('edit_phone').value          = this.dataset.phone;
            document.getElementById('edit_address').value        = this.dataset.address;
            document.getElementById('editModalSub').textContent  = 'Editing: ' + this.dataset.name;
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
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteBuyerModal')).show();
}
</script>

<?php include("../templates/footer.php"); ?>