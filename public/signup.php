<?php
include("../includes/auth.php");

$error = "";
$success = "";

if (is_logged_in()) {
    header("Location: {$base_url}dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $username = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($email) && !empty($role) && !empty($password)) {

        $username = mysqli_real_escape_string($conn, $username);
        $email = mysqli_real_escape_string($conn, $email);
        $role = mysqli_real_escape_string($conn, $role);
        $password = mysqli_real_escape_string($conn, $password);

        $hashed_password = md5($password);

        $q = "INSERT INTO users (username, email, password, role) 
              VALUES ('$username', '$email', '$hashed_password', '$role')";

        if (mysqli_query($conn, $q)) {
            $success = "Account created successfully. <a href='login.php'>Login here</a>.";
        } else {
            $error = "Signup failed: " . mysqli_error($conn);
        }

    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Signup - Everstone</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 col-sm-8">
                <div class="card p-4 shadow-sm border-0">
                    <h3 class="mb-3 text-center">Everstone Signup</h3>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="text" name="name" class="form-control mb-2" placeholder="Username" required>
                        <input type="email" name="email" class="form-control mb-2" placeholder="Email" required>
                        <input type="password" name="password" class="form-control mb-2" placeholder="Password"
                            required>
                        <select name="role" id="role" class="form-control mb-2">
                            <option value="Admin">Admin</option>
                            <option value="Sales">Sales</option>
                            <option value="Finance">Finance</option>
                            <option value="Auditor">Auditor</option>
                        </select>
                        <button class="btn btn-primary w-100">Signup</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>