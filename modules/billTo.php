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
if (isset($_POST['add_billTo'])) {
    $title   = mysqli_real_escape_string($conn, trim($_POST['title']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    mysqli_query($conn, "INSERT INTO billto (title, address) VALUES ('$title','$address')");
    header("Location: billTo.php");
    exit;
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit_billto_id'])) {
    $id      = (int)$_POST['edit_billto_id'];
    $title   = mysqli_real_escape_string($conn, trim($_POST['edit_title']));
    $address = mysqli_real_escape_string($conn, trim($_POST['edit_address']));
    mysqli_query($conn, "UPDATE billto SET title='$title', address='$address' WHERE id=$id");
    header("Location: billTo.php");
    exit;
}

/* ---------- DELETE ---------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM billto WHERE id=$id");
    header("Location: billTo.php?deleted=1");
    exit;
}

/* ---------- TABLE FUNCTION (supports pagination + search) ---------- */
function getBillToTable($conn, $search = "", $page = 1, $per_page = 10)
{
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $whereClause = "WHERE 1=1";
    if (!empty($search)) {
        $s = mysqli_real_escape_string($conn, trim($search));
        $whereClause .= " AND (title LIKE '%$s%' OR address LIKE '%$s%')";
    }

    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM billto $whereClause"));
    $total     = (int)($total_row['total'] ?? 0);
    $pages     = max(1, (int)ceil($total / $per_page));

    $result = mysqli_query($conn, "SELECT * FROM billto $whereClause ORDER BY id DESC LIMIT $offset, $per_page");
    $rows   = [];
    while ($r = mysqli_fetch_assoc($result)) $rows[] = $r;
    $count  = count($rows);

    ob_start();

    if ($count === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-receipt"></i></div>
            <div class="empty-state-title">No Bill To Records Found</div>
            <div class="empty-state-sub">
                <?= !empty($search) ? 'No records match your search criteria' : 'Add your first billing recipient to get started' ?>
            </div>
            <?php if (empty($search)): ?>
            <button class="page-action-btn primary mt-3"
                data-bs-toggle="modal" data-bs-target="#newBillToModal">
                <i class="bi bi-plus-circle-fill"></i>Add Bill To
            </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table bt-table mb-0">
                <thead>
                    <tr>
                        <th style="width:110px;">Bill To #</th>
                        <th>Title</th>
                        <th>Address</th>
                        <th class="text-center" style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="btBody">
                <?php foreach ($rows as $b): ?>
                <tr data-title="<?= strtolower(htmlspecialchars($b['title']   ?? '')) ?>"
                    data-address="<?= strtolower(htmlspecialchars($b['address'] ?? '')) ?>">
                    <td>
                        <span class="ref-badge ref-badge-amber">
                            <?= htmlspecialchars($b['code'] ?? '—') ?>
                        </span>
                    </td>
                    <td>
                        <div class="bt-cell">
                            <div class="bt-avatar">
                                <?= strtoupper(substr($b['title'] ?? '?', 0, 1)) ?>
                            </div>
                            <span class="bt-title-name"><?= htmlspecialchars($b['title'] ?? '—') ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="addr-text">
                            <?= !empty($b['address']) ? nl2br(htmlspecialchars($b['address'])) : '<span class="text-muted">—</span>' ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="row-actions">
                            <!-- EDIT -->
                            <button type="button"
                                class="row-action-btn edit editBtn"
                                title="Edit"
                                data-bs-toggle="modal"
                                data-bs-target="#editBillToModal"
                                data-id="<?= $b['id'] ?>"
                                data-code="<?= htmlspecialchars($b['code']    ?? '') ?>"
                                data-title="<?= htmlspecialchars($b['title']   ?? '') ?>"
                                data-address="<?= htmlspecialchars($b['address'] ?? '') ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- DELETE -->
                            <button type="button"
                                class="row-action-btn delete"
                                title="Delete Bill To"
                                onclick="openDeleteModal(
                                    <?= $b['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($b['title']   ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($b['address'] ?? '')) ?>',
                                    '<?= addslashes(htmlspecialchars($b['code']    ?? '—')) ?>'
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
            <nav aria-label="Bill To pagination">
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

            <span id="noMatchMsg" class="text-muted" style="font-size:.78rem;display:none;">
                <i class="bi bi-search me-1"></i>No records match your search
            </span>
        </div>
    <?php endif;

    return ob_get_clean();
}

/* ---------- AJAX ENTRY POINT ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getBillToTable($conn, $_GET['bt_search'] ?? '', $page, $per_page);
    exit;
}

/* ---------- STAT DATA ---------- */
$cnt_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM billto"))['c'];
$with_addr = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM billto WHERE address IS NOT NULL AND address != ''"))['c'];

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="page-wrapper">

    <!-- ── ALERTS ── -->
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3"
        style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill me-2 text-success"></i>Bill To record deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-receipt"></i>
                    Bill To
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="bt_search"
                        value="<?= htmlspecialchars($_GET['bt_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search title or address…">
                </div>
                <button class="page-action-btn primary"
                    data-bs-toggle="modal" data-bs-target="#newBillToModal">
                    <i class="bi bi-plus-circle-fill"></i>New Bill To
                </button>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="cust-stat-card" style="--card-color:#d97706;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Total Bill To</div>
                        <div class="cust-stat-num" style="color:#d97706;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#fef3c7;color:#d97706;">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="cust-stat-card" style="--card-color:#6366f1;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">Showing</div>
                        <div class="cust-stat-num" id="filteredCount" style="color:#6366f1;"><?= $cnt_total ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#e0e7ff;color:#6366f1;">
                        <i class="bi bi-eye"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="cust-stat-card" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="cust-stat-label">With Address</div>
                        <div class="cust-stat-num" style="color:#10b981;"><?= $with_addr ?></div>
                    </div>
                    <div class="cust-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="bt-table-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Bill To records
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
            <div id="btTable" style="overflow-x:auto;overflow-y:visible;min-height:300px;">
                <?= getBillToTable($conn, $_GET['bt_search'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ══════════════════════════════════════════
     ADD MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="newBillToModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <form method="post" class="modal-content">
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
                        <input type="text" name="title"
                            class="form-control modal-input"
                            style="height:40px;"
                            placeholder="e.g. ABC Corporation" required>
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Address <span class="text-danger">*</span></div>
                        <textarea name="address" rows="4"
                            class="form-control modal-input"
                            placeholder="Street, City, State, ZIP, Country"
                            required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4"
                    data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
                <button type="submit" name="add_billTo"
                    class="btn btn-sm px-4"
                    style="background:#d97706;color:#fff;border-color:#b45309;">
                    <i class="bi bi-plus-circle me-1"></i>Save Bill To
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     EDIT MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="editBillToModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_billto_id" id="edit_billto_id">
            <div class="modal-header border-0 rounded-top-3"
                style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit Bill To</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update billing details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded-3"
                    style="background:#fffbeb;border:1px solid #fde68a;">
                    <i class="bi bi-receipt" style="color:#b45309;font-size:.9rem;"></i>
                    <span style="font-size:.78rem;color:#92400e;font-weight:600;">
                        Bill To # &nbsp;<strong id="editModalCode">—</strong>
                    </span>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="modal-label">Title <span class="text-danger">*</span></div>
                        <input type="text" name="edit_title" id="edit_title"
                            class="form-control modal-input"
                            style="height:40px;"
                            placeholder="e.g. ABC Corporation" required>
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Address <span class="text-danger">*</span></div>
                        <textarea name="edit_address" id="edit_address" rows="4"
                            class="form-control modal-input"
                            placeholder="Street, City, State, ZIP, Country"
                            required></textarea>
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
                    style="background:#d97706;color:#fff;border-color:#b45309;">
                    <i class="bi bi-check-lg me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     DELETE BILL TO MODAL
══════════════════════════════════════════ -->
<div class="modal fade" id="deleteBillToModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                        <div>
                            <div class="delete-modal-title">Delete Bill To</div>
                            <div class="delete-modal-sub" id="deleteBtModalSub">Confirm record removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-bt-chip mb-3">
                    <div class="delete-bt-avatar" id="deleteBtAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-bt-title" id="deleteBtTitle">—</div>
                        <div class="delete-bt-meta" id="deleteBtAddress">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Bill To #</span>
                                <span class="chip-detail-value" id="deleteBtCode">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        This action is <strong>permanent and cannot be undone.</strong>
                        The billing record and its address will be permanently removed.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3"
                style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <a href="#" id="deleteBtConfirmBtn" class="btn-delete-confirm">
                    <i class="bi bi-trash-fill"></i> Yes, Delete Bill To
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
    document.getElementById('btTable').innerHTML =
        '<div class="text-center py-5">' +
            '<div class="spinner-border text-primary opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div>' +
            '<p class="text-muted small mt-2">Loading...</p>' +
        '</div>';

    const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;
    let url = 'billTo.php?ajax=1&page=' + page + '&per_page=' + perPage;
    if (search) url += '&bt_search=' + encodeURIComponent(search);

    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById('btTable').innerHTML = html;
            initEditButtons();
            updateFilteredCount(html);

            /* push state */
            let qs = [];
            if (search) qs.push('bt_search=' + encodeURIComponent(search));
            qs.push('page=' + page);
            qs.push('per_page=' + perPage);
            history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
        });
}

/* Extract visible count from rendered HTML and update stat card */
function updateFilteredCount(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const strongTags = tmp.querySelectorAll('.table-footer-bar strong');
    if (strongTags.length >= 2) {
        /* second <strong> is the "of N" total for current filter */
        document.getElementById('filteredCount').textContent = strongTags[1].textContent;
    }
}

/* ── Search ── */
document.getElementById('bt_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    searchTimer = setTimeout(() => loadTable(q, 1), 350);
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('#bt_search')) {
        e.preventDefault();
        clearTimeout(searchTimer);
        loadTable(e.target.value.trim(), 1);
    }
});

/* ── Pagination clicks (delegated – works after AJAX re-render) ── */
document.addEventListener('click', function (e) {
    const pg = e.target.closest('#btTable .pagination .page-link');
    if (!pg || !pg.dataset.page) return;
    e.preventDefault();
    const page   = parseInt(pg.dataset.page, 10) || 1;
    const search = document.getElementById('bt_search').value.trim();
    loadTable(search, page);
});

/* ── Per-page change ── */
document.getElementById('perPage').addEventListener('change', function () {
    const search = document.getElementById('bt_search').value.trim();
    loadTable(search, 1);
});

/* ══════════════════════════════════════════
   EDIT MODAL – re-init after AJAX re-render
══════════════════════════════════════════ */
function initEditButtons() {
    document.querySelectorAll('.editBtn').forEach(btn => {
        /* clone to remove stale listeners */
        const fresh = btn.cloneNode(true);
        btn.replaceWith(fresh);
        fresh.addEventListener('click', function () {
            document.getElementById('edit_billto_id').value      = this.dataset.id;
            document.getElementById('edit_title').value          = this.dataset.title;
            document.getElementById('edit_address').value        = this.dataset.address;
            document.getElementById('editModalSub').textContent  = 'Editing: ' + this.dataset.title;
            document.getElementById('editModalCode').textContent = this.dataset.code || '—';
        });
    });
}

/* Initial bind on page load */
document.addEventListener('DOMContentLoaded', initEditButtons);

/* ══════════════════════════════════════════
   DELETE MODAL
══════════════════════════════════════════ */
function openDeleteModal(id, title, address, code) {
    document.getElementById('deleteBtModalSub').textContent = 'Removing: ' + title;
    document.getElementById('deleteBtAvatar').textContent   = title ? title.charAt(0).toUpperCase() : '?';
    document.getElementById('deleteBtTitle').textContent    = title   || '—';
    document.getElementById('deleteBtCode').textContent     = code    || '—';
    const addrEl = document.getElementById('deleteBtAddress');
    addrEl.textContent   = address ? address.replace(/\n/g, ', ') : '—';
    addrEl.style.display = address ? '' : 'none';
    document.getElementById('deleteBtConfirmBtn').href = '?delete=' + id;
    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('deleteBillToModal')
    ).show();
}
</script>

<?php include("../templates/footer.php"); ?>