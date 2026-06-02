<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

include("../includes/auth.php");

$error = "";

if (is_logged_in()) {
    header("Location: " . app_url('public/dashboard.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['name-email'])) {
    $name_email = trim($_POST['name-email']);
    $password   = trim($_POST['password']);

    if (login($name_email, $password)) {
        header("Location: " . app_url('public/dashboard.php'));
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <title>Login - Everstone</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="https://everstonetech.ca/assets/Everstone.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Original login.css -->
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/tcs-unified.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('css/tcs-theme.css')) ?>">
<script src="<?= htmlspecialchars(asset_url('js/tcs-theme.js')) ?>"></script>

</head>
<body class="login-page">

<div class="bg-layer"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- Standalone theme toggle (login page) -->
<button class="tcs-theme-toggle page-theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <i class="bi bi-sun-fill" id="themeIcon"></i>
</button>

<div class="login-container">

    <!-- Left Panel -->
    <div class="login-left">
        <div class="left-deco left-deco-1"></div>
        <div class="left-deco left-deco-2"></div>
        <div class="left-deco left-deco-3"></div>
        <div class="left-inner">
            <div class="brand-logo">
                <img src="https://everstonetech.ca/assets/Everstone.png" alt="Everstone Logo">
            </div>
            <div class="brand-title">Everstone<br>Portal</div>
            <div class="brand-tagline">Customer Relationship Management</div>
            <div class="brand-sep"></div>
            <div class="brand-feature">
                <div class="brand-feature-icon"><i class="bi bi-shield-check-fill"></i></div>
                <div class="brand-feature-text">
                    <strong>Secure Access</strong><span>End-to-end encrypted sessions</span>
                </div>
            </div>
            <div class="brand-feature">
                <div class="brand-feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="brand-feature-text">
                    <strong>Live Analytics</strong><span>Real-time insights &amp; reports</span>
                </div>
            </div>
            <div class="brand-feature">
                <div class="brand-feature-icon"><i class="bi bi-people-fill"></i></div>
                <div class="brand-feature-text">
                    <strong>Client Management</strong><span>Centralised relationship tracking</span>
                </div>
            </div>
            <div class="brand-footer">&copy; <?= date('Y') ?> Everstone &middot; All rights reserved</div>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="login-right">
        <div class="login-eyebrow"><i class="bi bi-lock-fill"></i> Secure Login</div>
        <div class="login-heading">Welcome back</div>
        <div class="login-subheading">Sign in to your account to continue</div>

        <?php if ($error): ?>
        <div class="tcs-alert danger">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['msg'])): ?>
        <div class="tcs-alert info">
            <i class="bi bi-info-circle-fill"></i>
            <?= htmlspecialchars($_SESSION['msg']) ?>
        </div>
        <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

        <form method="post" novalidate>

            <div class="field-group">
                <label class="field-label">Username or Email</label>
                <div class="tcs-input-wrap">
                    <i class="bi bi-person tcs-input-icon-left"></i>
                    <input type="text" name="name-email"
                        class="tcs-input no-toggle <?= $error ? 'is-invalid' : '' ?>"
                        placeholder="Enter your username or email"
                        autocomplete="username" required>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label">Password</label>
                <div class="tcs-input-wrap">
                    <i class="bi bi-lock tcs-input-icon-left"></i>
                    <input type="password" name="password" id="passwordInput"
                        class="tcs-input <?= $error ? 'is-invalid' : '' ?>"
                        placeholder="Enter your password"
                        autocomplete="current-password" required>
                    <button type="button" class="tcs-pw-toggle" id="pwToggle" tabindex="-1">
                        <i class="bi bi-eye" id="pwToggleIcon"></i>
                    </button>
                </div>
            </div>

            <div class="form-row-meta">
                <a href="#" class="forgot-link"
                    data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="login-btn">
                <i class="bi bi-box-arrow-in-right"></i>Sign In
            </button>

        </form>

        <div class="login-page-footer">
            &copy; <?= date('Y') ?> Everstone &nbsp;&middot;&nbsp; All rights reserved
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <form method="post" action="forgot_password.php" class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#4f46e5,#6366f1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-key-fill text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title">Reset Password</h5>
                        <small style="color:var(--color-text-muted);font-size:.72rem;">
                            We'll send a reset link to your email
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close" id="modalClose" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Enter your registered email address and we'll send you a password reset link.</p>
                <div class="tcs-input-wrap">
                    <i class="bi bi-envelope tcs-input-icon-left"></i>
                    <input type="email" name="email"
                        class="tcs-input no-toggle"
                        placeholder="your@email.com" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="modal-submit">
                    <i class="bi bi-send-fill"></i>Send Reset Link
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- ✅ Shared theme JS — include tcs-theme.js on every page -->
<script src="<?= htmlspecialchars(asset_url('js/tcs-theme.js')) ?>"></script>

<script>
/* ── Page-specific: sync standalone toggle icon on load ── */
(function () {
    const icon = document.getElementById('themeIcon');
    const modalClose = document.getElementById('modalClose');

    function syncLoginIcons(theme) {
        if (icon) icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        if (modalClose) modalClose.style.filter = theme === 'dark'
            ? 'invert(1) grayscale(1) brightness(2)' : 'none';
    }

    syncLoginIcons(document.documentElement.getAttribute('data-theme') || 'dark');

    document.getElementById('themeToggle')?.addEventListener('click', function () {
        window.EverstoneTheme.toggle();
        syncLoginIcons(document.documentElement.getAttribute('data-theme'));
    });
})();

/* ── Password toggle ── */
document.getElementById('pwToggle').addEventListener('click', function () {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('pwToggleIcon');
    const isPass = input.type === 'password';
    input.type     = isPass ? 'text' : 'password';
    icon.className = isPass ? 'bi bi-eye-slash' : 'bi bi-eye';
});

/* ── Dismiss error on typing ── */
document.querySelectorAll('.tcs-input').forEach(el => {
    el.addEventListener('input', function () {
        const alert = document.querySelector('.tcs-alert.danger');
        if (alert) {
            alert.style.transition = 'opacity .3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }
        document.querySelectorAll('.tcs-input').forEach(i => i.classList.remove('is-invalid'));
    });
});
</script>
</body>
</html>
