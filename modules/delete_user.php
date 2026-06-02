<?php
include("../includes/auth.php");
include("../config/database.php"); // ✅ THIS WAS MISSING
check_auth();
require_role(['Admin']);

if (!isset($_GET['id'])) {
    header("Location: manage-users.php");
    exit;
}

$userId = (int) $_GET['id'];

if ($userId == ($_SESSION['user_id'] ?? 0)) {
    echo "<script>alert('You cannot delete your own account!'); window.location.href='manage-users.php';</script>";
    exit;
}

$q = "DELETE FROM users WHERE id = $userId";
if (mysqli_query($conn, $q)) {
    header("Location: manage-users.php");
    exit;
} else {
    echo "<script>alert('Failed to delete user: " . mysqli_error($conn) . "'); window.location.href='manage-users.php';</script>";
}
?>