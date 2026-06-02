<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

$error   = "";
$success = "";

/* ---------- CREATE ---------- */
if ($_SERVER['REQUEST_METHOD'] === "POST" && isset($_POST['name'])) {
    $username = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $role     = trim($_POST['role']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($email) && !empty($role) && !empty($password)) {
        $username = mysqli_real_escape_string($conn, $username);
        $email    = mysqli_real_escape_string($conn, $email);
        $role     = mysqli_real_escape_string($conn, $role);
        $hashed   = md5($password);

        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Username or Email already exists.";
        } else {
            if (mysqli_query($conn, "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed', '$role')")) {
                $success = "User account created successfully.";
            } else {
                $error = "Signup failed: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "All fields are required.";
    }
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit_user_id'])) {
    $editId       = (int)$_POST['edit_user_id'];
    $editUsername = mysqli_real_escape_string($conn, trim($_POST['edit_username']));
    $editRole     = mysqli_real_escape_string($conn, trim($_POST['edit_role']));

    if (!empty($editUsername) && !empty($editRole)) {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$editUsername' AND id != '$editId'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Username already taken.";
        } else {
            if (mysqli_query($conn, "UPDATE users SET username='$editUsername', role='$editRole' WHERE id='$editId'")) {
                $success = "User updated successfully.";
            } else {
                $error = "Update failed: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Both fields are required for update.";
    }
}

/* ---------- STAT COUNTS ---------- */
$cnt_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$cnt_admin   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='Admin'"))['c'];
$cnt_sales   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='Sales'"))['c'];
$cnt_Account = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='Account'"))['c'];

$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");

include("../templates/header.php");
include("../templates/navbar.php");

function roleClass($role)
{
    return match ($role) {
        'Admin'   => 'role-admin',
        'Sales'   => 'role-sales',
        'Account' => 'role-account',
        default   => 'role-default',
    };
}
function roleIcon($role)
{
    return match ($role) {
        'Admin'   => 'bi-shield-fill-check',
        'Sales'   => 'bi-bag-fill',
        'Account' => 'bi-calculator-fill',
        default   => 'bi-person-fill',
    };
}
?>

<div class="page-wrapper">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="page-header-title">
                    <i class="bi bi-people-fill"></i>Manage Users
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="page-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" id="usr_search" class="form-control page-search-input" placeholder="Search username, email, role…">
                </div>
                <button class="page-action-btn primary" data-bs-toggle="modal" data-bs-target="#newUserModal">
                    <i class="bi bi-person-plus-fill"></i>Add User
                </button>
            </div>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="page-alert danger">
            <i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close ms-auto btn-close-sm" onclick="this.closest('.page-alert').remove()"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="page-alert success">
            <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close ms-auto btn-close-sm" onclick="this.closest('.page-alert').remove()"></button>
        </div>
    <?php endif; ?>

    <!-- STAT CARDS — matching purchase_order.php style -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="usr-stat-card <?= (($_GET['filter']??'')==='total')?'active-filter':'' ?>"
                 data-filter="total" style="--card-color:#4f46e5;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="usr-stat-label">Total Users</div>
                        <div class="usr-stat-num" style="color:#4f46e5;"><?= $cnt_total ?></div>
                    </div>
                    <div class="usr-stat-icon" style="background:#e0e7ff;color:#4f46e5;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="usr-stat-card <?= (($_GET['filter']??'')==='Admin')?'active-filter':'' ?>"
                 data-filter="Admin" style="--card-color:#3730a3;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="usr-stat-label">Admins</div>
                        <div class="usr-stat-num" style="color:#3730a3;"><?= $cnt_admin ?></div>
                    </div>
                    <div class="usr-stat-icon" style="background:#e0e7ff;color:#3730a3;">
                        <i class="bi bi-shield-fill-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="usr-stat-card <?= (($_GET['filter']??'')==='Sales')?'active-filter':'' ?>"
                 data-filter="Sales" style="--card-color:#10b981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="usr-stat-label">Sales</div>
                        <div class="usr-stat-num" style="color:#10b981;"><?= $cnt_sales ?></div>
                    </div>
                    <div class="usr-stat-icon" style="background:#d1fae5;color:#10b981;">
                        <i class="bi bi-bag-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="usr-stat-card <?= (($_GET['filter']??'')==='Account')?'active-filter':'' ?>"
                 data-filter="Account" style="--card-color:#f59e0b;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="usr-stat-label">Account</div>
                        <div class="usr-stat-num" style="color:#f59e0b;"><?= $cnt_Account ?></div>
                    </div>
                    <div class="usr-stat-icon" style="background:#fef9c3;color:#f59e0b;">
                        <i class="bi bi-calculator-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div class="usr-table-card card border-0">
        <div class="card-body p-0">
            <?php
            $userRows = [];
            while ($u = mysqli_fetch_assoc($users)) $userRows[] = $u;
            ?>
            <?php if (empty($userRows)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                    <div class="empty-state-title">No Users Found</div>
                    <div class="empty-state-sub">Create your first user account to get started</div>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table usr-table mb-0" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th class="email-hide">Email</th>
                                <th>Role</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersBody">
                            <?php foreach ($userRows as $u): ?>
                                <tr data-username="<?= strtolower(htmlspecialchars($u['username'])) ?>"
                                    data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>"
                                    data-role="<?= strtolower(htmlspecialchars($u['role'])) ?>">
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                                            <div>
                                                <div class="user-name"><?= htmlspecialchars($u['username']) ?></div>
                                                <div class="user-id">#<?= $u['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="email-hide">
                                        <span class="email-cell">
                                            <i class="bi bi-envelope"></i><?= htmlspecialchars($u['email']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="role-pill <?= roleClass($u['role']) ?>">
                                            <i class="bi <?= roleIcon($u['role']) ?>" style="font-size:.7rem;"></i>
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="row-actions justify-content-center">
                                            <button title="Edit" class="row-action-btn edit editBtn"
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#editUserModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button title="Delete" class="row-action-btn delete deleteBtn"
                                                data-id="<?= $u['id'] ?>"
                                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                                data-email="<?= htmlspecialchars($u['email']) ?>"
                                                data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer-bar d-flex align-items-center justify-content-between">
                    <span class="text-muted" style="font-size:.78rem;">
                        <i class="bi bi-list-ul me-1"></i>
                        Showing <strong id="visibleCount"><?= count($userRows) ?></strong>
                        of <strong><?= count($userRows) ?></strong> user<?= count($userRows) !== 1 ? 's' : '' ?>
                    </span>
                    <span id="noMatchMsg" class="text-muted" style="font-size:.78rem;display:none;">
                        <i class="bi bi-search me-1"></i>No users match your search
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ADD USER MODAL -->
<div class="modal fade" id="newUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <div class="modal-header border-0 rounded-top-3" style="background:linear-gradient(135deg,#4f46e5,#6366f1);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle">
                        <i class="bi bi-person-plus-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Add New User</h5>
                        <small class="text-white opacity-75">Create a system user account</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Username <span class="text-danger">*</span></div>
                        <input type="text" name="name" class="form-control modal-input" placeholder="e.g. john_doe" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Email <span class="text-danger">*</span></div>
                        <input type="email" name="email" class="form-control modal-input" placeholder="e.g. john@company.com" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Password <span class="text-danger">*</span></div>
                        <input type="password" name="password" class="form-control modal-input" placeholder="••••••••" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Role <span class="text-danger">*</span></div>
                        <select name="role" class="form-select modal-input" required>
                            <option value="" disabled selected>Select role…</option>
                            <option value="Admin">Admin</option>
                            <option value="Sales">Sales</option>
                            <option value="Account">Account</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-sm px-4" style="background:#4f46e5;color:#fff;border-color:#4338ca;"><i class="bi bi-person-plus me-1"></i>Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_user_id" id="edit_user_id">
            <div class="modal-header border-0 rounded-top-3" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-10 p-2 rounded-circle">
                        <i class="bi bi-pencil-square fs-5 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-white">Edit User</h5>
                        <small class="text-white opacity-50" id="editModalSub">Update user details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="modal-label">Username <span class="text-danger">*</span></div>
                        <input type="text" name="edit_username" id="edit_username" class="form-control modal-input" required>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-label">Role <span class="text-danger">*</span></div>
                        <select name="edit_role" id="edit_role" class="form-select modal-input" required>
                            <option value="Admin">Admin</option>
                            <option value="Sales">Sales</option>
                            <option value="Account">Account</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-3 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                <button type="submit" class="btn btn-sm px-4" style="background:#4f46e5;color:#fff;border-color:#4338ca;"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE USER MODAL -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="delete-modal-header">
                <div class="d-flex align-items-center justify-content-between" style="position:relative;z-index:1;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="delete-icon-wrap"><i class="bi bi-person-x-fill"></i></div>
                        <div>
                            <div class="delete-modal-title">Delete User</div>
                            <div class="delete-modal-sub" id="deleteModalSub">Confirm account removal</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="delete-user-chip mb-3">
                    <div class="delete-user-avatar" id="deleteUserAvatar">?</div>
                    <div>
                        <div class="delete-user-name" id="deleteUserName">—</div>
                        <div class="delete-user-meta" id="deleteUserMeta">—</div>
                    </div>
                </div>
                <div class="delete-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>This action is <strong>permanent and cannot be undone.</strong> All data associated with this user account will be removed from the system.</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i> Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn-delete-confirm"><i class="bi bi-trash-fill"></i> Yes, Delete User</a>
            </div>
        </div>
    </div>
</div>

<script>
let activeFilter = '', searchTimer;

function applyFilters() {
    const q = document.getElementById('usr_search').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#usersBody tr');
    let visible = 0;
    rows.forEach(row => {
        const roleMatch = !activeFilter || activeFilter === 'total' ||
            (row.dataset.role || '') === activeFilter.toLowerCase();
        const searchMatch = !q ||
            (row.dataset.username || '').includes(q) ||
            (row.dataset.email || '').includes(q) ||
            (row.dataset.role || '').includes(q);
        const show = roleMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const vc = document.getElementById('visibleCount');
    if (vc) vc.textContent = visible;
    const nm = document.getElementById('noMatchMsg');
    if (nm) nm.style.display = visible === 0 ? 'inline' : 'none';
}

document.querySelectorAll('.usr-stat-card').forEach(card => {
    card.addEventListener('click', function () {
        const f = this.dataset.filter;
        activeFilter = activeFilter === f ? '' : f;
        document.querySelectorAll('.usr-stat-card')
            .forEach(c => c.classList.toggle('active-filter', c.dataset.filter === activeFilter));
        document.getElementById('usr_search').value = '';
        applyFilters();
    });
});

document.getElementById('usr_search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (this.value) {
            activeFilter = '';
            document.querySelectorAll('.usr-stat-card').forEach(c => c.classList.remove('active-filter'));
        }
        applyFilters();
    }, 300);
});

document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('edit_user_id').value  = this.dataset.id;
        document.getElementById('edit_username').value = this.dataset.username;
        document.getElementById('edit_role').value     = this.dataset.role;
        document.getElementById('editModalSub').textContent = 'Editing: ' + this.dataset.username;
    });
});

document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('deleteModalSub').textContent   = 'Removing: ' + this.dataset.username;
        document.getElementById('deleteUserAvatar').textContent = this.dataset.username.charAt(0).toUpperCase();
        document.getElementById('deleteUserName').textContent   = this.dataset.username;
        document.getElementById('deleteUserMeta').textContent   = this.dataset.role + '  •  ' + this.dataset.email;
        document.getElementById('deleteConfirmBtn').href        = 'delete_user.php?id=' + this.dataset.id;
    });
});

document.querySelectorAll('.page-alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 4000);
});
</script>

<?php include("../templates/footer.php"); ?>