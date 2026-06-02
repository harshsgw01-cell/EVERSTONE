<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();
require_role(['Admin']);

if (!isset($_POST['ids'])) {
    http_response_code(400);
    echo json_encode(["error" => "No IDs provided"]);
    exit;
}

$ids = json_decode($_POST['ids'], true);
$ids = array_map('intval', $ids);

if (!$ids) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid IDs"]);
    exit;
}

// Get order_id from first line
$res = mysqli_query($conn, "SELECT order_id FROM order_lines WHERE id = {$ids[0]}");
$row = mysqli_fetch_assoc($res);
$order_id = (int)$row['order_id'];

// Mark selected lines completed
$idList = implode(',', $ids);
mysqli_query($conn, "UPDATE order_lines SET status = 'Completed' WHERE id IN ($idList)");

// Sync parent order status
$res = mysqli_query($conn, "
    SELECT COUNT(*) AS pending 
    FROM order_lines 
    WHERE order_id = $order_id AND status != 'Completed'
");
$row = mysqli_fetch_assoc($res);

if ($row['pending'] == 0) {
    mysqli_query($conn, "UPDATE orders SET status = 'Completed' WHERE id = $order_id");
}

echo json_encode(["success" => true]);
