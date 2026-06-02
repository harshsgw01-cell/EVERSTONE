<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin', 'Sales']);

/* ─────────────────── Pagination setup ─────────────────── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM `rfqs` WHERE `id`='$id'");
    header("Location: lost_rfqs.php?deleted=1");
    exit;
}

if (isset($_POST['update_status'])) {
    $rfq_id = (int)$_POST['rfq_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $valid_statuses = ['Lost', 'Open', 'Ready for Review', 'Approved'];
    if (in_array($status, $valid_statuses)) {
        $result = mysqli_query($conn, "UPDATE rfqs SET status = '$status' WHERE id = $rfq_id");
        echo json_encode($result ? ['success' => true, 'status' => $status] : ['success' => false, 'error' => 'Database update failed']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
    }
    exit;
}

if (!isset($_GET['ajax'])) {
    include("../templates/header.php");
    include("../templates/navbar.php");
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
    echo getLostRfqsTable($conn, $_GET['rfq_search'] ?? "", $page, $per_page);
    exit;
}

function getLostRfqsTable($conn, $search = "", $page = 1, $per_page = 20)
{
    $page     = max(1, (int)$page);
    $per_page = max(5, (int)$per_page);
    $offset   = ($page - 1) * $per_page;

    $whereClause = "WHERE r.status = 'Lost'";
    if (!empty($search)) {
        $s = mysqli_real_escape_string($conn, trim($search));
        $whereClause .= " AND (r.rfq_number LIKE '%$s%' OR r.rfq_title LIKE '%$s%' OR c.name LIKE '%$s%')";
    }

    /* total count for pagination */
    $total_row = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS total
        FROM rfqs AS r
        JOIN customers c ON r.customer_id = c.id
        JOIN salesperson sp ON r.salesPerson_id = sp.id
        LEFT JOIN billto bt ON r.billTo_id = bt.id
        LEFT JOIN buyer b ON r.buyer_id = b.id
        LEFT JOIN shipto st ON r.shipTo_id = st.id
        $whereClause
    "));
    $total = (int)($total_row['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $per_page));

    $rfqs = mysqli_query($conn, "
        SELECT r.*, c.name AS customer, sp.name AS salesPerson,
               bt.title AS billTo, b.name AS buyer, st.name AS shipTo
        FROM rfqs AS r
        JOIN customers c ON r.customer_id = c.id
        JOIN salesperson sp ON r.salesPerson_id = sp.id
        LEFT JOIN billto bt ON r.billTo_id = bt.id
        LEFT JOIN buyer b ON r.buyer_id = b.id
        LEFT JOIN shipto st ON r.shipTo_id = st.id
        $whereClause ORDER BY r.lost_date DESC, r.id DESC
        LIMIT $offset, $per_page
    ");
    $count = mysqli_num_rows($rfqs);
    ob_start();

    if ($total === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-x-octagon"></i></div>
            <div class="empty-state-title">No Lost RFQs Found</div>
            <div class="empty-state-sub">No RFQs are currently marked as Lost.</div>
        </div>
    <?php else: ?>
        <table class="table lost-rfqs-table mb-0">
            <thead>
                <tr>
                    <th>RFQ #</th>
                    <th class="d-none d-md-table-cell">Title</th>
                    <th>Customer</th>
                    <th class="d-none d-sm-table-cell">Our Price</th>
                    <th class="d-none d-sm-table-cell">Awarded</th>
                    <th class="d-none d-lg-table-cell">Awarded To</th>
                    <th class="d-none d-md-table-cell">Difference</th>
                    <th class="d-none d-xl-table-cell">Lost Note</th>
                    <th class="d-none d-md-table-cell">Lost Date</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_assoc($rfqs)):
                    $diff      = (!empty($r['awarded_price']) && !empty($r['our_price'])) ? ($r['awarded_price'] - $r['our_price']) : null;
                    $diffClass = ($diff !== null && $diff < 0) ? 'text-danger' : 'text-success';
                    $diffIcon  = ($diff !== null && $diff < 0) ? 'arrow-down' : 'arrow-up';
                    $rfqNumber   = htmlspecialchars($r['rfq_number'] ?? '—');
                    $rfqTitle    = htmlspecialchars($r['rfq_title'] ?? '—');
                    $rfqCustomer = htmlspecialchars($r['customer'] ?? '—');
                    $rfqStatus   = htmlspecialchars($r['status'] ?? 'Lost');
                    $rfqOurPrice = '$' . number_format($r['our_price'] ?? 0, 2);
                    $rfqAwarded  = '$' . number_format($r['awarded_price'] ?? 0, 2);
                    $rfqLostDate = !empty($r['lost_date']) ? date('M j, Y', strtotime($r['lost_date'])) : '—';
                ?>
                    <tr class="rfq-row status-row-<?= $r['id'] ?>">
                        <td class="fw-semibold text-nowrap">
                            <span class="ref-badge ref-badge-red"><?= $rfqNumber ?></span>
                        </td>
                        <td class="d-none d-md-table-cell lost-title-cell text-truncate" title="<?= $rfqTitle ?>">
                            <span style="font-size:.83rem;"><?= $rfqTitle ?></span>
                        </td>
                        <td class="fw-semibold" style="font-size:.83rem;">
                            <span class="cell-truncate" title="<?= $rfqCustomer ?>"><?= $rfqCustomer ?></span>
                        </td>
                        <td class="d-none d-sm-table-cell">
                            <span class="price-badge price-blue"><?= $rfqOurPrice ?></span>
                        </td>
                        <td class="d-none d-sm-table-cell">
                            <span class="price-badge price-green"><?= $rfqAwarded ?></span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php if (!empty($r['awarded_to'])): ?>
                                <span class="awarded-badge"><i class="bi bi-building"></i><?= htmlspecialchars($r['awarded_to']) ?></span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell fw-semibold <?= $diffClass ?>" style="font-size:.82rem;">
                            <?php if ($diff !== null): ?><i class="bi bi-<?= $diffIcon ?> me-1"></i>$<?= number_format(abs($diff), 2) ?><?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="d-none d-xl-table-cell" style="max-width:200px;">
                            <?php if (!empty($r['lost_note'])): ?>
                                <span class="text-muted" style="font-size:.78rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?= htmlspecialchars($r['lost_note']) ?></span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.78rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell" style="white-space:nowrap;font-size:.78rem;">
                            <?php if (!empty($r['lost_date'])): ?>
                                <i class="bi bi-calendar-x text-danger me-1" style="font-size:.72rem;"></i><?= $rfqLostDate ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="status-cell p-2">
                            <span class="status-display editable-status"
                                data-status="<?= $rfqStatus ?>"
                                data-rfq-id="<?= $r['id'] ?>"
                                tabindex="0">
                                <span class="status-dot"></span>
                                <?= $rfqStatus ?>
                                <i class="bi bi-chevron-down ms-1" style="font-size:.55em;opacity:.6;"></i>
                            </span>
                        </td>
                        <td class="text-center p-2">
                            <div class="row-actions">
                                <button type="button" class="row-action-btn delete" title="Delete"
                                    onclick="event.preventDefault(); closeOpenMenu(); openDeleteModal(<?= $r['id'] ?>, '<?= addslashes($rfqNumber) ?>', '<?= addslashes($rfqCustomer) ?>', '<?= addslashes($rfqTitle) ?>', '<?= addslashes($rfqOurPrice) ?>', '<?= addslashes($rfqLostDate) ?>');">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="table-footer-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-muted" style="font-size:.78rem;">
                <i class="bi bi-list-ul me-1"></i>
                Showing <strong><?= $count ?></strong> of <strong><?= $total ?></strong> lost RFQ<?= $total !== 1 ? 's' : '' ?>
            </span>

            <?php if ($pages > 1): ?>
            <nav aria-label="Lost RFQ pagination">
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
?>


<div class="page-wrapper">

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" style="border-radius:10px;font-size:.875rem;">
            <i class="bi bi-check-circle-fill me-2 text-success"></i>Lost RFQ deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-file-earmark-x-fill"></i>Lost RFQs
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="rfq_search"
                        value="<?= htmlspecialchars($_GET['rfq_search'] ?? '') ?>"
                        class="form-control page-search-input"
                        placeholder="Search RFQ #, title, customer…">
                </div>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <?php
    $stats = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS total,
            COALESCE(SUM(our_price),0)                AS total_our,
            COALESCE(SUM(awarded_price),0)             AS total_awarded,
            COALESCE(SUM(awarded_price - our_price),0) AS total_diff
        FROM rfqs WHERE status = 'Lost'
    "));
    $cnt_this_month = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS c FROM rfqs
        WHERE status='Lost'
          AND MONTH(lost_date)=MONTH(NOW())
          AND YEAR(lost_date)=YEAR(NOW())
    "))['c'];
    $diffTotal = $stats['total_diff'];
    $diffColor = $diffTotal < 0 ? '#ef4444' : '#10b981';
    $diffBg    = $diffTotal < 0 ? '#fee2e2' : '#d1fae5';
    $diffIcon  = $diffTotal < 0 ? 'arrow-down-circle-fill' : 'arrow-up-circle-fill';
    ?>
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-sm-3">
            <div class="rfq-stat-card" style="--card-color:#ef4444;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="lost-stat-label">Total Lost</div>
                        <div class="lost-stat-num" style="color:#ef4444;"><?= number_format($stats['total']) ?></div>
                    </div>
                    <div class="lost-stat-icon" style="background:#fee2e2;color:#ef4444;">
                        <i class="bi bi-x-octagon-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-3">
            <div class="rfq-stat-card" style="--card-color:#3b82f6;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="lost-stat-label">Our Total</div>
                        <div class="lost-stat-num" style="color:#3b82f6;font-size:clamp(.9rem,2vw,1.4rem);">$<?= number_format($stats['total_our'], 0) ?></div>
                    </div>
                    <div class="lost-stat-icon" style="background:#dbeafe;color:#3b82f6;">
                        <i class="bi bi-tag-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-3">
            <div class="rfq-stat-card" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="lost-stat-label">Awarded Total</div>
                        <div class="lost-stat-num" style="color:#10b981;font-size:clamp(.9rem,2vw,1.4rem);">$<?= number_format($stats['total_awarded'], 0) ?></div>
                    </div>
                    <div class="lost-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-3">
            <div class="rfq-stat-card" style="--card-color:<?= $diffColor ?>;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="lost-stat-label">Difference</div>
                        <div class="lost-stat-num" style="color:<?= $diffColor ?>;font-size:clamp(.9rem,2vw,1.4rem);">$<?= number_format(abs($diffTotal), 0) ?></div>
                    </div>
                    <div class="lost-stat-icon" style="background:<?= $diffBg ?>;color:<?= $diffColor ?>;">
                        <i class="bi bi-<?= $diffIcon ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="lost-page-card card border-0">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
             style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                      style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Lost RFQs
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
            <div id="rfqTable" style="overflow-x:auto;overflow-y:visible;min-height:400px;">
                <?= getLostRfqsTable($conn, $_GET['rfq_search'] ?? '', $page, $per_page) ?>
            </div>
        </div>
    </div>

</div>

<!-- DELETE RFQ MODAL -->
<div class="modal fade" id="deleteRfqModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-file-earmark-x"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete Lost RFQ</div>
                            <div class="delete-modal-sub" id="deleteRfqModalSub">Confirm RFQ removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-rfq-chip mb-3">
                    <div class="delete-rfq-avatar" id="deleteRfqAvatar">?</div>
                    <div style="flex:1;min-width:0;">
                        <div class="delete-rfq-code" id="deleteRfqNumber">—</div>
                        <div class="delete-rfq-meta" id="deleteRfqCustomer">—</div>
                        <div class="delete-rfq-meta" id="deleteRfqTitle">—</div>
                        <div class="chip-detail">
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Our Price</span>
                                <span class="chip-detail-value" id="deleteRfqPrice">—</span>
                            </div>
                            <div style="width:1px;height:28px;background:#fecaca;"></div>
                            <div class="chip-detail-item">
                                <span class="chip-detail-label">Lost Date</span>
                                <span class="chip-detail-value" id="deleteRfqDate">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong> The RFQ and all associated data will be permanently removed.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                <a href="#" id="deleteRfqConfirmBtn" class="btn-delete-confirm"><i class="bi bi-trash-fill"></i> Yes, Delete RFQ</a>
            </div>
        </div>
    </div>
</div>

<script>
    function openDeleteModal(id, rfqNumber, customer, title, ourPrice, lostDate) {
        document.getElementById('deleteRfqModalSub').textContent = 'Removing: ' + rfqNumber;
        document.getElementById('deleteRfqNumber').textContent = rfqNumber;
        document.getElementById('deleteRfqCustomer').textContent = customer;
        document.getElementById('deleteRfqTitle').textContent = title;
        document.getElementById('deleteRfqPrice').textContent = ourPrice;
        document.getElementById('deleteRfqDate').textContent = lostDate;
        document.getElementById('deleteRfqConfirmBtn').href = '?delete=' + id;
        document.getElementById('deleteRfqAvatar').textContent = rfqNumber ? rfqNumber.charAt(0).toUpperCase() : '?';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteRfqModal')).show();
    }

    /* ── Load table via AJAX ── */
    function loadTable(search, page = 1) {
        document.getElementById("rfqTable").innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-danger opacity-50" style="width:1.5rem;height:1.5rem;border-width:2px;"></div><p class="text-muted small mt-2">Loading...</p></div>';

        const perPage = document.getElementById('perPage')?.value || <?= (int)$per_page ?>;
        let url = `lost_rfqs.php?ajax=1&page=${page}&per_page=${perPage}`;
        if (search) url += `&rfq_search=${encodeURIComponent(search)}`;

        fetch(url)
            .then(r => r.text())
            .then(html => {
                document.getElementById("rfqTable").innerHTML = html;
                initAll();
                /* sync browser URL */
                let qs = [];
                if (search) qs.push('rfq_search=' + encodeURIComponent(search));
                qs.push('page=' + page);
                qs.push('per_page=' + perPage);
                history.pushState(null, '', qs.length ? '?' + qs.join('&') : '?');
            });
    }

    /* ── Search ── */
    let searchTimer;
    document.getElementById("rfq_search").addEventListener("input", function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        searchTimer = setTimeout(() => loadTable(q, 1), 350);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.matches('#rfq_search')) {
            e.preventDefault();
            clearTimeout(searchTimer);
            loadTable(e.target.value.trim(), 1);
        }
    });

    /* ── Pagination click (delegated) ── */
    document.addEventListener('click', function (e) {
        const pg = e.target.closest('.pagination .page-link');
        if (!pg || !pg.dataset.page) return;
        e.preventDefault();
        const page   = parseInt(pg.dataset.page, 10) || 1;
        const search = document.getElementById('rfq_search').value;
        loadTable(search, page);
    });

    /* ── Per-page change ── */
    document.getElementById('perPage').addEventListener('change', function () {
        const search = document.getElementById('rfq_search').value;
        loadTable(search, 1);
    });

    /* ── Action menus ── */
    let openMenu = null, openBtn = null;

    function positionMenu(btn, menu) {
        const r = btn.getBoundingClientRect();
        menu.style.top   = (r.bottom + 4) + 'px';
        menu.style.right = (window.innerWidth - r.right) + 'px';
        menu.style.left  = 'auto';
    }
    function closeOpenMenu() {
        if (openMenu) {
            openMenu.style.display = 'none';
            openBtn?.classList.remove('active');
            openMenu = null;
            openBtn  = null;
        }
    }
    function initActionMenus() {
        document.querySelectorAll('.action-toggle').forEach(btn => {
            const fresh = btn.cloneNode(true);
            btn.replaceWith(fresh);
            fresh.addEventListener('click', function (e) {
                e.stopPropagation();
                const menu = document.getElementById('actionMenu' + this.dataset.rfqId);
                if (!menu) return;
                if (openMenu === menu) { closeOpenMenu(); return; }
                closeOpenMenu();
                menu.style.display = 'block';
                positionMenu(this, menu);
                this.classList.add('active');
                openMenu = menu;
                openBtn  = this;
            });
        });
    }
    window.addEventListener('scroll', () => { if (openMenu && openBtn) positionMenu(openBtn, openMenu); }, true);
    window.addEventListener('resize', () => { if (openMenu && openBtn) positionMenu(openBtn, openMenu); });
    document.addEventListener('click', e => {
        if (openMenu && !e.target.closest('.action-toggle') && !e.target.closest('.action-menu')) closeOpenMenu();
    });

    /* ── Status dropdown ── */
    let currentDropdown = null, currentRfqId = null, currentStatusEl = null;

    function initStatusDropdowns() {
        document.querySelectorAll('.editable-status').forEach(el => {
            const fresh = el.cloneNode(true);
            el.replaceWith(fresh);
            fresh.addEventListener('click', e => {
                e.stopPropagation();
                if (currentDropdown && currentStatusEl === fresh) { hideStatusDropdown(); return; }
                showStatusDropdown(fresh);
            });
            fresh.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (currentDropdown && currentStatusEl === fresh) { hideStatusDropdown(); return; }
                    showStatusDropdown(fresh);
                }
            });
        });
    }

    function showStatusDropdown(el) {
        closeOpenMenu();
        if (currentDropdown) hideStatusDropdown();
        const rfqId = el.dataset.rfqId, cur = el.dataset.status;
        currentRfqId    = rfqId;
        currentStatusEl = el;
        document.querySelector(`.status-row-${rfqId}`)?.classList.add('status-dropdown-open');

        const colors = {
            'Lost': '#ef4444', 'Open': '#0ea5e9',
            'Ready for Review': '#f59e0b', 'Approved': '#10b981'
        };
        const dd = document.createElement('div');
        dd.className = 'status-dropdown';
        dd.innerHTML = Object.entries(colors).map(([s, c]) =>
            `<div class="status-dropdown-option ${cur === s ? 'active' : ''}" data-status="${s}">
                <span style="width:8px;height:8px;border-radius:50%;background:${c};display:inline-block;flex-shrink:0;"></span>${s}
            </div>`
        ).join('');
        const rect = el.getBoundingClientRect();
        dd.style.cssText = `position:fixed;z-index:9999;left:${rect.left}px;top:${rect.bottom + 4}px;min-width:${Math.max(rect.width, 175)}px;`;
        document.body.appendChild(dd);
        currentDropdown = dd;
        dd.querySelectorAll('.status-dropdown-option').forEach(o =>
            o.addEventListener('click', e => { e.stopPropagation(); updateStatus(rfqId, o.dataset.status, el); })
        );
    }

    function hideStatusDropdown() {
        currentDropdown?.remove();
        currentDropdown = null;
        currentStatusEl = null;
        document.querySelector(`.status-row-${currentRfqId}`)?.classList.remove('status-dropdown-open');
        currentRfqId = null;
    }
    document.addEventListener('click', e => {
        if (currentDropdown && !e.target.closest('.status-display') && !e.target.closest('.status-dropdown')) hideStatusDropdown();
    });

    function updateStatus(rfqId, newStatus, el) {
        el.style.opacity = '.6';
        el.innerHTML = '<i class="bi bi-arrow-repeat"></i> Saving…';
        const fd = new FormData();
        fd.append('update_status', '1');
        fd.append('rfq_id', rfqId);
        fd.append('status', newStatus);
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                el.style.opacity = '1';
                if (data.success) {
                    if (newStatus !== 'Lost') {
                        const row = document.querySelector(`.status-row-${rfqId}`);
                        if (row) {
                            row.style.transition = 'opacity .3s,transform .3s';
                            row.style.opacity    = '0';
                            row.style.transform  = 'translateX(-20px)';
                            setTimeout(() => row.remove(), 300);
                        }
                    } else {
                        el.dataset.status = newStatus;
                        el.innerHTML = `<span class="status-dot"></span>${newStatus}<i class="bi bi-chevron-down ms-1" style="font-size:.55em;opacity:.6;"></i>`;
                        el.addEventListener('click', e => {
                            e.stopPropagation();
                            if (currentDropdown && currentStatusEl === el) { hideStatusDropdown(); return; }
                            showStatusDropdown(el);
                        });
                    }
                    hideStatusDropdown();
                } else {
                    el.innerHTML = `<span class="status-dot"></span>${el.dataset.status}<i class="bi bi-chevron-down ms-1" style="font-size:.55em;opacity:.6;"></i>`;
                    alert('Update failed: ' + (data.error || 'Unknown'));
                }
            }).catch(() => {
                el.style.opacity = '1';
                el.innerHTML = `<span class="status-dot"></span>${el.dataset.status}<i class="bi bi-chevron-down ms-1" style="font-size:.55em;opacity:.6;"></i>`;
            });
    }

    function initAll() {
        initActionMenus();
        initStatusDropdowns();
    }
    document.addEventListener('DOMContentLoaded', initAll);
</script>

<?php if (!isset($_GET['ajax'])) include("../templates/footer.php"); ?>