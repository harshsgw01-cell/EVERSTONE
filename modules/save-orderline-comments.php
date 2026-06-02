<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['order_line_id']) || empty($_POST['comment'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$order_line_id = (int) $_POST['order_line_id'];
$user_id = $_SESSION['user_id'] ?? null;
$comment = mysqli_real_escape_string($conn, $_POST['comment']);

$sql = "INSERT INTO order_line_comments (order_line_id, user_id, comment) 
        VALUES ($order_line_id, " . ($user_id ? $user_id : "NULL") . ", '$comment')";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["success" => true]);
} else {
    http_response_code(500);
    echo json_encode(["error" => mysqli_error($conn)]);
}
