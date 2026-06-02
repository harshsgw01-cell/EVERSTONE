<?php
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
if (!$order_id) { echo json_encode(["error" => "Missing order_id"]); exit; }

$allowed_fields = [
    'order_number'       => 'string',
    'customer_po_number' => 'string',
    'shipping'           => 'string',
    'note_to_buyer'      => 'string',
];

$sets = [];
foreach ($allowed_fields as $field => $type) {
    if (!array_key_exists($field, $_POST)) continue;
    $val = mysqli_real_escape_string($conn, trim($_POST[$field]));
    $sets[] = "`$field` = '$val'";
}

if (empty($sets)) {
    echo json_encode(["error" => "No valid fields to update"]);
    exit;
}

// Check order_number uniqueness if provided
if (isset($_POST['order_number']) && trim($_POST['order_number']) !== '') {
    $on  = mysqli_real_escape_string($conn, trim($_POST['order_number']));
    $chk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT id FROM orders WHERE order_number='$on' AND id!=$order_id LIMIT 1"));
    if ($chk) {
        echo json_encode(["error" => "Order number already exists"]);
        exit;
    }
}

$sql = "UPDATE orders SET " . implode(', ', $sets) . " WHERE id=$order_id";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(["error" => "DB error: " . mysqli_error($conn)]);
    exit;
}

echo json_encode(["success" => true, "order_id" => $order_id]);