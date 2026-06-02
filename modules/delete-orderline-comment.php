<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(["error" => "Missing comment ID"]);
    exit;
}

$id = (int) $_POST['id'];

if (mysqli_query($conn, "DELETE FROM order_line_comments WHERE id=$id")) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to delete comment"]);
}
