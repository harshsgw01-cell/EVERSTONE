<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include("../includes/auth.php");

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = trim($_POST['password']);
    $token = trim($_POST['token']);

    $res = mysqli_query($conn, "SELECT * FROM password_resets WHERE token='$token'"); // AND expires_at > NOW()
    if (mysqli_num_rows($res) === 1) {
        $reset = mysqli_fetch_assoc($res);
        $user_id = $reset['user_id'];

        $hashed = md5($password);

        mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE id='$user_id'");
        mysqli_query($conn, "DELETE FROM password_resets WHERE user_id='$user_id'");

        $success = "Password updated successfully. <a href='login.php'>Login here</a>.";
    } else {
        $error = "Invalid or expired token.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Change Password | Everstone</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-9">
                <div class="card p-4 shadow-sm border-0">
                    <h3 class="mb-3 text-center">Set New Password</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                    <?php if (!$success): ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <input type="password" name="password" class="form-control mb-2" placeholder="New Password"
                                required>
                            <button class="btn btn-primary w-100">Update Password</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>