<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header('Content-Type: application/json');

$order_id = (int) ($_GET['order_id'] ?? 0);
$order_number = trim(mysqli_real_escape_string($conn, $_GET['order_number'] ?? ''));

if ($order_number === '') {
    echo json_encode(["valid" => false, "msg" => "Order number is required"]);
    exit;
}

$check = mysqli_query(
    $conn,
    "SELECT id FROM orders WHERE order_number = '$order_number' AND id <> $order_id LIMIT 1"
);

if (mysqli_num_rows($check) > 0) {
    echo json_encode(["valid" => false, "msg" => "Order number already exists"]);
} else {
    echo json_encode(["valid" => true]);
}
