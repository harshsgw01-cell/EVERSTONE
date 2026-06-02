<?php
$role = $_SESSION['role'] ?? '';
$user = $_SESSION['user'] ?? 'User';

$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($pages) {
    global $currentPage;
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($currentPage, $pages) ? 'active-link' : '';
}
?>
<script src="<?= htmlspecialchars(asset_url('js/tcs-theme.js')) ?>"></script>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/tcs-unified.css')) ?>">

<nav class="navbar navbar-expand-lg tcs-navbar">
    <div class="container-fluid px-3">

        <!-- ── BRAND ── -->
        <a class="navbar-brand d-flex align-items-center gap-2 flex-shrink-0" href="<?= htmlspecialchars(app_url('public/dashboard.php')) ?>">
            <div class="nav-logo">
                <img src="<?= htmlspecialchars(asset_url('Everstone.png')) ?>" alt="Everstone" width="36" height="36">
            </div>
            <div class="d-none d-sm-block">
                <div class="brand-title">EVERSTONE TECHNOLOGY SYSTEMS</div>
            </div>
        </a>

        <!-- ── MOBILE RIGHT ── -->
        <div class="d-flex align-items-center gap-2 d-lg-none ms-auto">

            <!-- Theme toggle uses shared .tcs-theme-toggle — auto-wired by tcs-theme.js -->
            <button class="tcs-theme-toggle compact" aria-label="Toggle theme">
                <i class="bi bi-sun-fill"></i>
            </button>

            <div class="dropdown">
                <button class="nav-avatar-btn border-0 bg-transparent p-0"
                    data-bs-toggle="dropdown" aria-expanded="false" type="button">
                    <div class="nav-avatar"><?= strtoupper(substr($user, 0, 1)) ?></div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end nav-dropdown-menu mt-2" style="min-width:210px;">
                    <li>
                        <div class="nav-user-header px-3 py-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="nav-avatar nav-avatar-sm"><?= strtoupper(substr($user, 0, 1)) ?></div>
                                <div>
                                    <div class="fw-semibold" style="font-size:.83rem;color:var(--nb-user-name);"><?= htmlspecialchars($user) ?></div>
                                    <div class="d-flex align-items-center gap-1 mt-1">
                                        <span class="role-dot"></span>
                                        <span style="font-size:.68rem;color:var(--nb-user-hdr-sub);"><?= htmlspecialchars($role ?: 'Guest') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('public/dashboard.php')) ?>"><span class="nav-dd-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-house"></i></span>Dashboard</a></li>
                    <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('public/profile.php')) ?>"><span class="nav-dd-icon" style="background:#f3f4f6;color:#374151;"><i class="bi bi-person"></i></span>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item nav-dd-item nav-dd-danger" href="<?= htmlspecialchars(app_url('public/logout.php')) ?>"><span class="nav-dd-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-box-arrow-right"></i></span>Sign Out</a></li>
                </ul>
            </div>

            <button class="navbar-toggler nav-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-5"></i>
            </button>
        </div>

        <!-- ── MAIN NAV ── -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">

                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('dashboard.php') ?>" href="<?= htmlspecialchars(app_url('public/dashboard.php')) ?>">
                        <i class="bi bi-house-door"></i><span>Dashboard</span>
                    </a>
                </li>

                <?php if (in_array($role, ['Admin', 'Sales'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link nav-link-custom dropdown-toggle <?= isActive(['customers.php','salesPerson.php','billTo.php','buyer.php','shipTo.php','vendor_add.php','products.php']) ?>"
                        href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-plus-circle"></i><span>Add</span>
                    </a>
                    <ul class="dropdown-menu nav-dropdown-menu mt-2" style="min-width:220px;">
                        <li class="nav-dd-section-label">Master Data</li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/customers.php')) ?>"><span class="nav-dd-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-people"></i></span>Customers</a></li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/salesPerson.php')) ?>"><span class="nav-dd-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-person-check"></i></span>Sales Person</a></li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/billTo.php')) ?>"><span class="nav-dd-icon" style="background:#fef9c3;color:#ca8a04;"><i class="bi bi-credit-card"></i></span>Bill To</a></li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/buyer.php')) ?>"><span class="nav-dd-icon" style="background:#fce7f3;color:#db2777;"><i class="bi bi-person-badge"></i></span>Buyer</a></li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/shipTo.php')) ?>"><span class="nav-dd-icon" style="background:#e0f2fe;color:#0284c7;"><i class="bi bi-truck"></i></span>Ship To</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="nav-dd-section-label">Inventory</li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/products.php')) ?>"><span class="nav-dd-icon" style="background:#fef3c7;color:#d97706;"><i class="bi bi-box-seam"></i></span>Products</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="nav-dd-section-label">Procurement</li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('modules/vendor_add.php')) ?>"><span class="nav-dd-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bi bi-building"></i></span>Vendor</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Sales'])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('rfqs.php') ?>" href="<?= htmlspecialchars(app_url('modules/rfqs.php')) ?>">
                        <i class="bi bi-journal-check"></i><span>RFQs</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Sales'])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('lost_rfqs.php') ?>" href="<?= htmlspecialchars(app_url('modules/lost_rfqs.php')) ?>">
                        <i class="bi bi-file-earmark-x"></i><span>Lost RFQs</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Sales', 'Account'])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('order.php') ?>" href="<?= htmlspecialchars(app_url('modules/order.php')) ?>">
                        <i class="bi bi-bag-check"></i><span>Orders</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Sales', 'Account'])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('purchase_order.php') ?>" href="<?= htmlspecialchars(app_url('modules/purchase_order.php')) ?>">
                        <i class="bi bi-cart-check"></i><span>PO</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Sales'])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('profit-loss.php') ?>" href="<?= htmlspecialchars(app_url('modules/profit-loss.php')) ?>">
                        <i class="bi bi-graph-up-arrow"></i><span>Margins</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($role, ['Admin', 'Account'])): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('invoices.php') ?>" href="<?= htmlspecialchars(app_url('modules/invoices.php')) ?>">
                        <i class="bi bi-file-earmark-text"></i><span>Invoices</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($role === 'Admin'): ?>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?= isActive('manage-users.php') ?>" href="<?= htmlspecialchars(app_url('modules/manage-users.php')) ?>">
                        <i class="bi bi-people-fill"></i><span>Users</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Desktop: divider + theme toggle + user pill -->
                <li class="nav-item d-none d-lg-flex align-items-center">
                    <div class="nav-divider-v"></div>
                </li>

                <li class="nav-item d-none d-lg-block">
                    <!-- Auto-wired by tcs-theme.js -->
                    <button class="tcs-theme-toggle compact" aria-label="Toggle theme">
                        <i class="bi bi-sun-fill"></i>
                    </button>
                </li>

                <li class="nav-item dropdown d-none d-lg-block">
                    <button class="nav-user-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" type="button">
                        <div class="nav-avatar nav-avatar-sm"><?= strtoupper(substr($user, 0, 1)) ?></div>
                        <div class="nav-user-info">
                            <span class="nav-user-name"><?= htmlspecialchars($user) ?></span>
                        </div>
                        <i class="bi bi-chevron-down nav-chevron"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end nav-dropdown-menu mt-2" style="min-width:220px;">
                        <li>
                            <div class="nav-user-header px-3 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="nav-avatar" style="width:38px;height:38px;font-size:1rem;border-radius:10px;"><?= strtoupper(substr($user, 0, 1)) ?></div>
                                    <div>
                                        <div class="fw-semibold" style="font-size:.84rem;color:var(--nb-user-name);"><?= htmlspecialchars($user) ?></div>
                                        <div class="d-flex align-items-center gap-1 mt-1">
                                            <span class="role-dot"></span>
                                            <span style="font-size:.68rem;color:var(--nb-user-hdr-sub);"><?= htmlspecialchars($role ?: 'Guest') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('public/dashboard.php')) ?>"><span class="nav-dd-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-house"></i></span>Dashboard</a></li>
                        <li><a class="dropdown-item nav-dd-item" href="<?= htmlspecialchars(app_url('public/profile.php')) ?>"><span class="nav-dd-icon" style="background:#f3f4f6;color:#374151;"><i class="bi bi-person"></i></span>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item nav-dd-item nav-dd-danger" href="<?= htmlspecialchars(app_url('public/logout.php')) ?>"><span class="nav-dd-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-box-arrow-right"></i></span>Sign Out</a></li>
                    </ul>
                </li>

            </ul>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Active link highlight
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-link-custom').forEach(link => {
        const href = link.getAttribute('href') || '';
        const linkPage = href.split('/').pop().split('?')[0];
        if (linkPage && linkPage === currentPage && !link.classList.contains('active-link'))
            link.classList.add('active-link');
    });

    // Auto-close mobile menu on nav click
    document.querySelectorAll('#navbarNav .nav-link:not(.dropdown-toggle)').forEach(link => {
        link.addEventListener('click', () => {
            const collapse = document.getElementById('navbarNav');
            if (collapse?.classList.contains('show'))
                bootstrap.Collapse.getInstance(collapse)?.hide();
        });
    });
});
</script>
