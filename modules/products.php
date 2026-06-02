<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
/* ── Pagination setup ── */
$default_per_page = 10;
$per_page = max(5, (int)($_GET['per_page'] ?? $default_per_page));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

require_role(['Admin', 'Sales']);

$error   = '';
$success = '';

/* ── Auto-generate next product code ── */
function nextProductCode($conn)
{
    $last = mysqli_fetch_assoc(mysqli_query($conn, "SELECT code FROM products WHERE code LIKE 'P%' ORDER BY id DESC LIMIT 1"));
    if ($last && preg_match('/P(\d+)/', $last['code'], $m)) {
        return 'P' . str_pad((int)$m[1] + 1, 5, '0', STR_PAD_LEFT);
    }
    return 'P00001';
}

/* ── CREATE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $code        = mysqli_real_escape_string($conn, trim($_POST['code']         ?? ''));
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']         ?? ''));
    $part_number = mysqli_real_escape_string($conn, trim($_POST['part_number']  ?? ''));
    $manufacturer = mysqli_real_escape_string($conn, trim($_POST['manufacturer'] ?? ''));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']  ?? ''));
    $sales_price = (float)($_POST['sales_price'] ?? 0);
    $cost_price  = (float)($_POST['cost_price']  ?? 0);
    $catalog_file = '';

    if (empty($name)) {
        $error = 'Product name is required.';
    } elseif (empty($code)) {
        $error = 'Product code is required.';
    } else {
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM products WHERE code='$code' LIMIT 1"));
        if ($check) {
            $error = 'A product with this code already exists.';
        } else {
            if (!empty($_FILES['catalog_file']['name'])) {
                $upload_dir = '../uploads/catalogs/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = time() . '_' . basename($_FILES['catalog_file']['name']);
                $dest     = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['catalog_file']['tmp_name'], $dest)) {
                    $catalog_file = mysqli_real_escape_string($conn, 'uploads/catalogs/' . $filename);
                }
            }

            mysqli_query($conn, "INSERT INTO products 
                (code, name, part_number, manufacturer, description, sales_price, cost_price, catalog_file)
                VALUES ('$code','$name','$part_number','$manufacturer','$description',
                        '$sales_price','$cost_price'," . ($catalog_file ? "'$catalog_file'" : "NULL") . ")");
            $success = "Product <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
        }
    }
}

/* ── UPDATE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id          = (int)$_POST['id'];
    $code        = mysqli_real_escape_string($conn, trim($_POST['code']         ?? ''));
    $name        = mysqli_real_escape_string($conn, trim($_POST['name']         ?? ''));
    $part_number = mysqli_real_escape_string($conn, trim($_POST['part_number']  ?? ''));
    $manufacturer = mysqli_real_escape_string($conn, trim($_POST['manufacturer'] ?? ''));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']  ?? ''));
    $sales_price = (float)($_POST['sales_price'] ?? 0);
    $cost_price  = (float)($_POST['cost_price']  ?? 0);

    if (empty($name)) {
        $error = 'Product name is required.';
    } elseif (empty($code)) {
        $error = 'Product code is required.';
    } else {
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM products WHERE code='$code' AND id != $id LIMIT 1"));
        if ($check) {
            $error = 'Another product with this code already exists.';
        } else {
            $set = "code='$code', name='$name', part_number='$part_number', 
                    manufacturer='$manufacturer', description='$description',
                    sales_price='$sales_price', cost_price='$cost_price'";

            if (!empty($_FILES['catalog_file']['name'])) {
                $upload_dir = '../uploads/catalogs/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = time() . '_' . basename($_FILES['catalog_file']['name']);
                $dest     = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['catalog_file']['tmp_name'], $dest)) {
                    $cf = mysqli_real_escape_string($conn, 'uploads/catalogs/' . $filename);
                    $set .= ", catalog_file='$cf'";
                }
            }

            mysqli_query($conn, "UPDATE products SET $set WHERE id=$id");
            $success = "Product updated successfully.";
        }
    }
}

/* ── DELETE ── */
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM products WHERE id=$del_id");
    header("Location: products.php?deleted=1");
    exit;
}
if (isset($_GET['deleted'])) $success = 'Product deleted successfully.';

/* ── SEARCH & FETCH ── */
$search   = mysqli_real_escape_string($conn, trim($_GET['q'] ?? ''));
$where    = $search ? "WHERE name LIKE '%$search%' OR code LIKE '%$search%'" : '';
$products = mysqli_query($conn, "
    SELECT * FROM products 
    $where 
    ORDER BY id DESC 
    LIMIT $offset, $per_page
");
$total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products $where"))['c'];
$pages = max(1, ceil($total / $per_page));
$next_code = nextProductCode($conn);

/* ── STATS ── */
$total_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products"))['c'];
$avg_sale  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(AVG(sales_price),0) AS a FROM products"))['a'];
$margins_r = mysqli_query($conn, "SELECT sales_price, cost_price FROM products WHERE sales_price > 0");
$margins   = [];
while ($r = mysqli_fetch_assoc($margins_r)) {
    if ($r['sales_price'] > 0) $margins[] = (($r['sales_price'] - $r['cost_price']) / $r['sales_price']) * 100;
}
$avg_margin = count($margins) ? array_sum($margins) / count($margins) : 0;

include("../templates/header.php");
include("../templates/navbar.php");
?>
<div class="page-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-box-seam-fill"></i>Products
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form method="GET" action="products.php" class="d-flex" id="searchForm">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" id="searchInput"
                            value="<?= htmlspecialchars($search) ?>"
                            class="form-control search-input" placeholder="Search name or code…"
                            autocomplete="off">
                    </div>
                </form>
                <button class="page-action-btn primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-lg"></i>Add Product
                </button>
            </div>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="page-alert danger">
            <i class="bi bi-exclamation-circle-fill"></i><?= $error ?>
            <button type="button" class="btn-close ms-auto btn-close-sm" onclick="this.closest('.page-alert').remove()"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="page-alert success">
            <i class="bi bi-check-circle-fill"></i><?= $success ?>
            <button type="button" class="btn-close ms-auto btn-close-sm" onclick="this.closest('.page-alert').remove()"></button>
        </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--accent:#d97706;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Products</div>
                        <div class="stat-num" style="color:#d97706;"><?= number_format($total_all) ?></div>
                    </div>
                    <div class="stat-icon" style="background:#fef3c7;color:#d97706;">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--accent:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Avg. Sales Price</div>
                        <div class="stat-num" style="color:#10b981;">€<?= number_format($avg_sale, 2) ?></div>
                    </div>
                    <div class="stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-tag-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--accent:#3b82f6;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Avg. Margin</div>
                        <div class="stat-num" style="color:#3b82f6;"><?= number_format($avg_margin, 1) ?>%</div>
                    </div>
                    <div class="stat-icon" style="background:#dbeafe;color:#3b82f6;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cust-stat-card" style="--accent:#8b5cf6;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label"><?= $search ? 'Search Results' : 'Showing' ?></div>
                        <div class="stat-num" style="color:#8b5cf6;"><?= number_format($total) ?></div>
                    </div>
                    <div class="stat-icon" style="background:#ede9fe;color:#8b5cf6;">
                        <i class="bi bi-<?= $search ? 'search' : 'list-ul' ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-card card border-0">
        <!-- TOP PAGINATION BAR -->
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"
        style="background:var(--color-surface-2);border-bottom:1px solid var(--color-border);">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill"
                    style="background:var(--color-accent-bg);color:var(--color-accent-dark);font-size:.7rem;">
                    <i class="bi bi-funnel me-1"></i>List
                </span>
                <span style="font-size:.8rem;color:var(--color-text-secondary);">
                    paginate Products
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
        <div style="overflow-x:auto;">
            <?php if (mysqli_num_rows($products) === 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="empty-state-title"><?= $search ? 'No products match your search' : 'No products yet' ?></div>
                    <div class="empty-state-sub"><?= $search ? 'Try a different search term' : 'Click Add Product to get started' ?></div>
                </div>
            <?php else: ?>
                <table class="table prod-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Part Number</th>
                            <th>Manufacturer</th>
                            <th>Description</th>
                            <th>Sales Price</th>
                            <th>Cost Price</th>
                            <th>Margin</th>
                            <th>Catalog</th>
                            <th>Added</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = $offset + 1;
                        while ($p = mysqli_fetch_assoc($products)):
                            $margin = (($p['sales_price'] ?? 0) > 0)
                                ? (($p['sales_price'] - ($p['cost_price'] ?? 0)) / $p['sales_price']) * 100
                                : 0;
                            $margin_color = $margin >= 20 ? '#10b981' : ($margin >= 10 ? '#d97706' : ($p['sales_price'] > 0 ? '#ef4444' : '#94a3b8'));
                        ?>
                            <tr>
                                <td style="color:#94a3b8;font-size:.75rem;font-weight:600;"><?= $i++ ?></td>

                                <td><span class="prod-code"><?= htmlspecialchars($p['code'] ?? '') ?></span></td>
                                <td><span class="prod-name"><?= htmlspecialchars($p['name'] ?? '') ?></span></td>

                                <td style="font-size:.8rem;color:#475569;">
                                    <?= htmlspecialchars($p['part_number'] ?? '—') ?>
                                </td>

                                <td style="font-size:.8rem;color:#475569;">
                                    <?= htmlspecialchars($p['manufacturer'] ?? '—') ?>
                                </td>

                                <td style="font-size:.8rem;color:#64748b;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= !empty($p['description']) ? htmlspecialchars($p['description']) : '<span style="color:#d1d5db;">—</span>' ?>
                                </td>

                                <td>
                                    <span class="price-cell" style="color:#0f172a;">
                                        €<?= number_format($p['sales_price'] ?? 0, 2) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="price-cell" style="color:#64748b;">
                                        €<?= number_format($p['cost_price'] ?? 0, 2) ?>
                                    </span>
                                </td>

                                <td>
                                    <span style="font-weight:700;font-size:.82rem;color:<?= $margin_color ?>;">
                                        <?= ($p['sales_price'] ?? 0) > 0 ? number_format($margin, 1) . '%' : '—' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($p['catalog_file'])): ?>
                                        <a href="/<?= htmlspecialchars($p['catalog_file']) ?>" target="_blank" class="catalog-link">
                                            <i class="bi bi-file-earmark-pdf"></i>View PDF
                                        </a>
                                    <?php else: ?>
                                        <span class="no-catalog"><i class="bi bi-dash"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:.75rem;color:#94a3b8;white-space:nowrap;">
                                    <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <button class="row-action-btn edit editBtn" title="Edit"
                                            data-id="<?= $p['id'] ?>"
                                            data-code="<?= htmlspecialchars($p['code']) ?>"
                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-part="<?= htmlspecialchars($p['part_number'] ?? '') ?>"
                                            data-manufacturer="<?= htmlspecialchars($p['manufacturer'] ?? '') ?>"
                                            data-description="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                            data-sales="<?= $p['sales_price'] ?>"
                                            data-cost="<?= $p['cost_price'] ?>"
                                            data-catalog="<?= htmlspecialchars($p['catalog_file'] ?? '') ?>"
                                            data-bs-toggle="modal" data-bs-target="#editProductModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="row-action-btn delete deleteBtn" title="Delete"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteProductModal">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="table-footer-bar d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <span>
                        <i class="bi bi-list-ul me-1"></i>
                        Showing <strong><?= mysqli_num_rows($products) ?></strong> of
                        <strong><?= $total ?></strong> product<?= $total != 1 ? 's' : '' ?>
                    </span>

                    <div class="d-flex align-items-center gap-2">
                        <!-- Pagination -->
                        <?php if ($pages > 1): ?>
                            <ul class="pagination pagination-sm mb-0">

                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&per_page=<?= $per_page ?>&q=<?= urlencode($search) ?>">Prev</a>
                                </li>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($pages, $page + 2);
                                for ($p = $start; $p <= $end; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $p ?>&per_page=<?= $per_page ?>&q=<?= urlencode($search) ?>">
                                            <?= $p ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= min($pages, $page + 1) ?>&per_page=<?= $per_page ?>&q=<?= urlencode($search) ?>">Next</a>
                                </li>

                            </ul>
                        <?php endif; ?>

                    </div>
                </div>
                <?php if ($search): ?>
                    <a href="products.php" class="text-muted text-decoration-none" style="font-size:.78rem;"><i class="bi bi-x-circle me-1"></i>Clear search</a>
                <?php endif; ?>
        </div>
    <?php endif; ?>
    </div>
</div>

</div>

<!-- ══ ADD PRODUCT MODAL ══ -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header-custom">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="modal-header-icon"><i class="bi bi-box-seam-fill"></i></div>
                        <div>
                            <h5 class="mb-0 fw-bold text-white" style="font-size:.95rem;">Add New Product</h5>
                            <small class="text-white opacity-50">Fill in product details below</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-5">
                        <div class="modal-label">Product Code <span class="text-danger">*</span></div>
                        <input type="text" name="code" class="form-control modal-input"
                            value="<?= htmlspecialchars($next_code) ?>" required>
                    </div>
                    <div class="col-7">
                        <div class="modal-label">Product Name <span class="text-danger">*</span></div>
                        <input type="text" name="name" class="form-control modal-input" placeholder="e.g. Tactical Vest" required>
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Part Number</div>
                        <input type="text" name="part_number" class="form-control modal-input" placeholder="e.g. Everstone-4521">
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Manufacturer</div>
                        <input type="text" name="manufacturer" class="form-control modal-input" placeholder="e.g. Safariland">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Description</div>
                        <textarea name="description" class="form-control modal-input" rows="2"
                            style="height:auto;" placeholder="Short product description"></textarea>
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Sales Price (€)</div>
                        <input type="number" name="sales_price" step="0.01" min="0" value="0.00" class="form-control modal-input">
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Cost Price (€)</div>
                        <input type="number" name="cost_price" step="0.01" min="0" value="0.00" class="form-control modal-input">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Catalog File <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;">(PDF, optional)</span></div>
                        <input type="file" name="catalog_file" accept=".pdf,.doc,.docx"
                            class="form-control modal-input" style="height:auto;padding:6px 10px;">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i>Cancel</button>
                <button type="submit" class="btn-save-modal"><i class="bi bi-check2"></i>Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ EDIT PRODUCT MODAL ══ -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header-custom">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="modal-header-icon"><i class="bi bi-pencil-square"></i></div>
                        <div>
                            <h5 class="mb-0 fw-bold text-white" style="font-size:.95rem;">Edit Product</h5>
                            <small class="text-white opacity-50" id="edit_modal_sub">Update product details</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-5">
                        <div class="modal-label">Product Code <span class="text-danger">*</span></div>
                        <input type="text" name="code" id="edit_code" class="form-control modal-input" required>
                    </div>
                    <div class="col-7">
                        <div class="modal-label">Product Name <span class="text-danger">*</span></div>
                        <input type="text" name="name" id="edit_name" class="form-control modal-input" required>
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Part Number</div>
                        <input type="text" name="part_number" id="edit_part" class="form-control modal-input" placeholder="e.g. Everstone-4521">
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Manufacturer</div>
                        <input type="text" name="manufacturer" id="edit_manufacturer" class="form-control modal-input" placeholder="e.g. Safariland">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Description</div>
                        <textarea name="description" id="edit_description" class="form-control modal-input" rows="2"
                            style="height:auto;" placeholder="Short product description"></textarea>
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Sales Price (€)</div>
                        <input type="number" name="sales_price" id="edit_sales" step="0.01" min="0" class="form-control modal-input">
                    </div>
                    <div class="col-6">
                        <div class="modal-label">Cost Price (€)</div>
                        <input type="number" name="cost_price" id="edit_cost" step="0.01" min="0" class="form-control modal-input">
                    </div>
                    <div class="col-12">
                        <div class="modal-label">Replace Catalog File <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;">(leave blank to keep existing)</span></div>
                        <input type="file" name="catalog_file" accept=".pdf,.doc,.docx"
                            class="form-control modal-input" style="height:auto;padding:6px 10px;">
                        <div id="edit_current_catalog" class="mt-1" style="font-size:.75rem;color:#64748b;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i>Cancel</button>
                <button type="submit" class="btn-save-modal"><i class="bi bi-check2"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ DELETE PRODUCT MODAL ══ -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-trash-fill"></i></div>
                        <div>
                            <div style="font-size:.95rem;font-weight:700;color:#fff;">Delete Product</div>
                            <div style="font-size:.72rem;color:rgba(255,255,255,.4);" id="delete_modal_sub">Confirm removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="d-flex align-items-center gap-3 p-3 mb-3" style="background:#fef3c7;border:1.5px solid #fde68a;border-radius:12px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:#fde68a;display:flex;align-items:center;justify-content:center;color:#b45309;font-size:1rem;flex-shrink:0;">
                        <i class="bi bi-box-seam-fill"></i>
                    </div>
                    <div>
                        <div id="delete_product_name" style="font-weight:700;color:#92400e;font-size:.88rem;"></div>
                        <div style="font-size:.72rem;color:#b45309;margin-top:1px;">Product</div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent</strong>. The product will be removed from the catalogue but existing order lines will not be affected.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i>Cancel</button>
                <a href="#" id="delete_confirm_btn" class="btn-delete-confirm"><i class="bi bi-trash-fill"></i>Yes, Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_code').value = this.dataset.code;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_part').value = this.dataset.part;
            document.getElementById('edit_manufacturer').value = this.dataset.manufacturer;
            document.getElementById('edit_description').value = this.dataset.description;
            document.getElementById('edit_sales').value = this.dataset.sales;
            document.getElementById('edit_cost').value = this.dataset.cost;
            document.getElementById('edit_modal_sub').textContent = 'Editing: ' + this.dataset.name;
            const cat = this.dataset.catalog;
            const catDiv = document.getElementById('edit_current_catalog');
            catDiv.innerHTML = cat ?
                '<i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Current: <a href="/' + cat + '" target="_blank" class="text-primary">' + cat.split('/').pop() + '</a>' :
                '<span class="text-muted">No catalog file uploaded</span>';
        });
    });

    document.querySelectorAll('.deleteBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_product_name').textContent = this.dataset.name;
            document.getElementById('delete_modal_sub').textContent = 'Removing: ' + this.dataset.name;
            document.getElementById('delete_confirm_btn').href = 'products.php?delete=' + this.dataset.id;
        });
    });

    document.querySelectorAll('.page-alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 4000);
    });

    /* ── Live client-side search ── */
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('.prod-table tbody tr');
    const footerCount = document.getElementById('footerCount');

    if (searchInput && tableRows.length) {
        searchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            let visible = 0;
            tableRows.forEach(row => {
                const code = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const name = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const match = !q || code.includes(q) || name.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (footerCount) footerCount.textContent = visible;
        });

        /* Submit form on Enter for server-side search (e.g. after page reload) */
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('searchForm').submit();
            }
        });
    }

    function changePerPage(val) {
        const params = new URLSearchParams(window.location.search);
        params.set('per_page', val);
        params.set('page', 1);
        window.location.search = params.toString();
    }
</script>

<?php include("../templates/footer.php"); ?>