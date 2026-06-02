<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
include("../config/database.php");
include("../includes/auth.php");
check_auth();

$user_id  = $_SESSION['user_id'] ?? 0;
$role     = $_SESSION['role'] ?? 'User';
$username = $_SESSION['username'] ?? 'User';

// ── DETECT EXISTING COLUMNS IN users TABLE ─────────────────────────
$uid = (int)$user_id;
$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM users");
$user_cols = [];
while ($c = mysqli_fetch_assoc($cols_res)) $user_cols[] = $c['Field'];

$has_full_name  = in_array('full_name',  $user_cols);
$has_email      = in_array('email',      $user_cols);
$has_phone      = in_array('phone',      $user_cols);
$has_created_at = in_array('created_at', $user_cols);
$has_last_login = in_array('last_login', $user_cols);

if (!$has_full_name) { mysqli_query($conn, "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) DEFAULT NULL"); $has_full_name = true; }
if (!$has_email)     { mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL");    $has_email = true; }
if (!$has_phone)     { mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL");     $has_phone = true; }

// ── FETCH USER ─────────────────────────────────────────────────────
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $uid"));

// Always read username from DB directly
$db_username = $user['username'] ?? $username;

// ── SAFE VARIABLE DEFAULTS (prevent undefined variable warnings) ───
$full_name = $user['full_name'] ?? '';
$email     = $user['email']     ?? '';
$phone     = $user['phone']     ?? '';

// ── HANDLE FORM SUBMISSIONS ────────────────────────────────────────
$success_msg = '';
$error_msg   = '';
$full_name   = $user['full_name'] ?? '';
$email       = $user['email']     ?? '';
$phone       = $user['phone']     ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $email     = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone     = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $current   = $_POST['current_password'] ?? '';
    $new_pass  = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    // Validate profile fields
    if (empty($full_name)) {
        $error_msg = 'Full name is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } else {
        if (!empty($email) && $has_email) {
            $email_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != $uid"));
            if ($email_check) $error_msg = 'This email is already used by another account.';
        }
    }

    // Validate password only if any password field is filled
    if (empty($error_msg) && (!empty($current) || !empty($new_pass) || !empty($confirm))) {
        if (empty($current) || empty($new_pass) || empty($confirm)) {
            $error_msg = 'Fill all three password fields to change your password.';
        } elseif ($new_pass !== $confirm) {
            $error_msg = 'New passwords do not match.';
        } elseif (strlen($new_pass) < 6) {
            $error_msg = 'New password must be at least 6 characters.';
        } else {
            $fresh  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id=$uid"));
            $stored = $fresh['password'] ?? '';
            $valid  = password_verify($current, $stored) || md5($current) === $stored || $current === $stored;
            if (!$valid) $error_msg = 'Current password is incorrect.';
        }
    }

    if (empty($error_msg)) {
        $set_parts = ["full_name='$full_name'"];
        if ($has_email && !empty($email)) $set_parts[] = "email='$email'";
        if ($has_phone) $set_parts[] = "phone='$phone'";
        // Change password if provided
        if (!empty($new_pass) && !empty($current)) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $set_parts[] = "password='$hashed'";
        }
        mysqli_query($conn, "UPDATE users SET " . implode(', ', $set_parts) . " WHERE id=$uid");
        $_SESSION['username'] = $full_name ?: $username;
        $success_msg = (!empty($new_pass) && !empty($current)) ? 'Profile and password updated successfully.' : 'Profile updated successfully.';
        $user        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $uid"));
        $db_username = $user['username'] ?? $username;
        $full_name   = $user['full_name'] ?? '';
        $email       = $user['email']     ?? '';
        $phone       = $user['phone']     ?? '';
        $full_name   = $user['full_name'] ?? '';
        $email       = $user['email']     ?? '';
        $phone       = $user['phone']     ?? '';
    }
}

// ── ACTIVITY STATS ─────────────────────────────────────────────────
$stat_rfqs     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM rfqs"))['cnt']     ?? 0;
$stat_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders"))['cnt']   ?? 0;
$stat_invoices = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM invoices"))['cnt'] ?? 0;

$member_since = ($has_created_at && !empty($user['created_at'])) ? date('M Y', strtotime($user['created_at'])) : 'N/A';
$last_login   = ($has_last_login && !empty($user['last_login']))  ? date('d M Y, H:i', strtotime($user['last_login'])) : null;

$display_name = !empty($user['full_name']) ? $user['full_name'] : $db_username;
$words    = array_filter(explode(' ', trim($display_name)));
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
$initials = substr($initials, 0, 2) ?: '?';

$role_colors = [
    'Admin'      => ['bg' => '#dc3545', 'label' => 'Administrator'],
    'Sales'      => ['bg' => '#0d6efd', 'label' => 'Sales'],
    'Accounting' => ['bg' => '#198754', 'label' => 'Account'],
    'Account'    => ['bg' => '#198754', 'label' => 'Account'],
];
$role_info = $role_colors[$role] ?? ['bg' => '#6c757d', 'label' => $role];

include("../templates/header.php");
include("../templates/navbar.php");
?>

<div class="profile-page">

    <div class="profile-hero">
        <div class="profile-hero-inner">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-ring"><?= htmlspecialchars($initials ?: '?') ?></div>
                    <div>
                        <div class="hero-name"><span><?= htmlspecialchars($display_name) ?></span></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 hero-stats-row">
                    <?php if (in_array($role, ['Admin','Sales'])): ?>
                    <div class="hero-stat">
                        <div class="hero-stat-num"><?= number_format($stat_rfqs) ?></div>
                        <div class="hero-stat-label">RFQs</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-num"><?= number_format($stat_orders) ?></div>
                        <div class="hero-stat-label">Orders</div>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Admin','Account','Accounting'])): ?>
                    <div class="hero-stat">
                        <div class="hero-stat-num"><?= number_format($stat_invoices) ?></div>
                        <div class="hero-stat-label">Invoices</div>
                    </div>
                    <?php endif; ?>
                    <div class="hero-stat">
                        <div class="hero-stat-num hero-stat-num--large"><?= $member_since ?></div>
                        <div class="hero-stat-label">Member Since</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="profile-body">

            <?php if ($success_msg): ?>
            <div class="prof-alert success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
            <div class="prof-alert error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <div class="row g-3">

                <!-- LEFT COLUMN -->
                <div class="col-12 col-lg-3 d-flex">
                    <div class="prof-card w-100">
                        <div class="prof-card-header">
                            <div class="prof-card-header-icon profile-main"><i class="bi bi-person-vcard"></i></div>
                            <p class="prof-card-title">Account Info</p>
                        </div>
                        <div class="prof-card-body prof-card-body--compact">
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-person"></i></div>
                                <div>
                                    <div class="info-row-label">Username</div>
                                    <div class="info-row-value">@<?= htmlspecialchars($user['username'] ?? $username) ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-person"></i></div>
                                <div>
                                    <div class="info-row-label">Full Name</div>
                                    <div class="info-row-value"><?= htmlspecialchars($user['full_name'] ?? $full_name) ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-envelope"></i></div>
                                <div>
                                    <div class="info-row-label">Email</div>
                                    <div class="info-row-value"><?= htmlspecialchars(!empty($user['email']) ? $user['email'] : $email) ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-telephone"></i></div>
                                <div>
                                    <div class="info-row-label">Phone</div>
                                    <div class="info-row-value"><?= htmlspecialchars(!empty($user['phone']) ? $user['phone'] : $phone) ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-shield-check"></i></div>
                                <div>
                                    <div class="info-row-label">Role</div>
                                    <div class="info-row-value">
                                        <span class="role-pill" style="background:<?= $role_info['bg'] ?>;font-size:.7rem;padding:2px 10px;">
                                            <?= htmlspecialchars($role_info['label']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-calendar3"></i></div>
                                <div>
                                    <div class="info-row-label">Member Since</div>
                                    <div class="info-row-value"><?= $member_since ?></div>
                                </div>
                            </div>
                            <?php if ($last_login): ?>
                            <div class="info-row">
                                <div class="info-row-icon"><i class="bi bi-clock-history"></i></div>
                                <div>
                                    <div class="info-row-label">Last Login</div>
                                    <div class="info-row-value"><?= $last_login ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="col-12 col-lg-9 d-flex flex-column">

                    <!-- Edit Profile Card -->
                    <div class="prof-card mb-3">
                        <div class="prof-card-header">
                            <div class="prof-card-header-icon profile-edit"><i class="bi bi-pencil-square"></i></div>
                            <p class="prof-card-title">Edit Profile</p>
                        </div>
                        <div class="prof-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row g-3">
                                    <div class="col-12 col-sm-4">
                                        <label class="prof-label">Full Name</label>
                                        <input type="text" name="full_name" class="prof-input"
                                            value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                                            placeholder="Your full name" required>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="prof-label">Email Address</label>
                                        <input type="email" name="email" class="prof-input"
                                            value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                            placeholder="your@email.com">
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="prof-label">Phone Number</label>
                                        <input type="text" name="phone" class="prof-input"
                                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                            placeholder="+1 234 567 8900">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn-dark-solid">
                                        <i class="bi bi-check2 me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="prof-card flex-grow-1">
                        <div class="prof-card-header">
                            <div class="prof-card-header-icon profile-pass"><i class="bi bi-lock"></i></div>
                            <p class="prof-card-title">Change Password</p>
                        </div>
                        <div class="prof-card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                <input type="hidden" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                <div class="row g-3">
                                    <div class="col-12 col-sm-4">
                                        <label class="prof-label">Current Password</label>
                                        <div class="input-with-toggle">
                                            <input type="password" name="current_password" id="cur_pass" class="prof-input"
                                                placeholder="Current password">
                                            <button type="button" class="toggle-pass" data-target="cur_pass">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="prof-label">New Password</label>
                                        <div class="input-with-toggle">
                                            <input type="password" name="new_password" id="new_pass" class="prof-input"
                                                placeholder="Min. 6 characters"
                                                oninput="checkStrength(this.value)">
                                            <button type="button" class="toggle-pass" data-target="new_pass">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                                        <small id="strengthLabel" class="strength-label"></small>
                                    </div>
                                    <div class="col-12 col-sm-4">
                                        <label class="prof-label">Confirm New Password</label>
                                        <div class="input-with-toggle">
                                            <input type="password" name="confirm_password" id="conf_pass" class="prof-input"
                                                placeholder="Repeat new password"
                                                oninput="checkMatch()">
                                            <button type="button" class="toggle-pass" data-target="conf_pass">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <small id="matchLabel" class="strength-label"></small>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn-dark-solid btn-gold">
                                        <i class="bi bi-lock-fill me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = document.getElementById(this.dataset.target);
        const icon  = this.querySelector('i');
        if (input.type === 'password') { input.type = 'text'; icon.className = 'bi bi-eye-slash'; }
        else { input.type = 'password'; icon.className = 'bi bi-eye'; }
    });
});

function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if (!val) { fill.style.width = '0%'; label.textContent = ''; return; }
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { w:'20%', c:'#ef4444', t:'Very Weak' }, { w:'40%', c:'#f97316', t:'Weak' },
        { w:'60%', c:'#eab308', t:'Fair' },       { w:'80%', c:'#22c55e', t:'Strong' },
        { w:'100%',c:'#16a34a', t:'Very Strong' },
    ];
    const lvl = levels[score - 1] || levels[0];
    fill.style.width = lvl.w; fill.style.background = lvl.c;
    label.textContent = lvl.t; label.style.color = lvl.c;
}

function checkMatch() {
    const np = document.getElementById('new_pass').value;
    const cp = document.getElementById('conf_pass').value;
    const label = document.getElementById('matchLabel');
    if (!cp) { label.textContent = ''; return; }
    if (np === cp) { label.textContent = '✓ Passwords match'; label.style.color = '#16a34a'; }
    else { label.textContent = '✗ Passwords do not match'; label.style.color = '#ef4444'; }
}

setTimeout(() => {
    document.querySelectorAll('.prof-alert').forEach(el => {
        el.style.transition = 'opacity .5s'; el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>

<?php include("../templates/footer.php"); ?>