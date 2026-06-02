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

$ol_id = (int)($_POST['ol_id'] ?? 0);
if (!$ol_id) { echo json_encode(["error" => "Missing ol_id"]); exit; }

// Verify line exists
$lineCheck = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, order_id FROM order_lines WHERE id=$ol_id"));
if (!$lineCheck) { echo json_encode(["error" => "Order line not found"]); exit; }
$order_id = (int)$lineCheck['order_id'];

// Build SET clause from allowed fields
$allowed_fields = [
    'description'       => 'string',
    'part_number'       => 'string',
    'manufacturer'      => 'string',
    'note_to_buyer'     => 'string',
    'qty'               => 'float',
    'unit_price'        => 'float',
    'cost_price'        => 'float',
    'delivered'         => 'float',
    'invoiced'          => 'float',
    'status'            => 'string',
    // sourcing fields
    'fulfillment_method'  => 'string',
    'supplier_name'       => 'string',
    'supplier_po_number'  => 'string',
    'shipping_cost'       => 'float',
    'tracking_number'     => 'string',
    'tracking_url'        => 'string',
];

$allowed_statuses = ['Open','In Progress','In Review','To be Tested','Delayed','Cancelled','Closed','On Hold','Completed'];
$allowed_fulfillment = ['Stock','Drop Ship','Warehouse Delivery'];

$sets = [];
foreach ($allowed_fields as $field => $type) {
    if (!array_key_exists($field, $_POST)) continue;
    $val = $_POST[$field];

    if ($field === 'status' && !in_array($val, $allowed_statuses)) {
        echo json_encode(["error" => "Invalid status value"]); exit;
    }
    if ($field === 'fulfillment_method' && !in_array($val, $allowed_fulfillment)) {
        echo json_encode(["error" => "Invalid fulfillment_method value"]); exit;
    }

    if ($type === 'float') {
        $val = (float)$val;
        $sets[] = "`$field` = $val";
    } else {
        $val = mysqli_real_escape_string($conn, trim($val));
        $sets[] = "`$field` = '$val'";
    }
}

if (empty($sets)) {
    echo json_encode(["error" => "No valid fields to update"]);
    exit;
}

$sql = "UPDATE order_lines SET " . implode(', ', $sets) . " WHERE id=$ol_id";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(["error" => "DB error: " . mysqli_error($conn)]);
    exit;
}

// Sync parent order status
$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS pending FROM order_lines WHERE order_id=$order_id AND status!='Completed'"
));
$new_order_status = ($row['pending'] == 0) ? 'Completed' : 'Pending';
mysqli_query($conn, "UPDATE orders SET status='$new_order_status' WHERE id=$order_id");

echo json_encode(["success" => true, "ol_id" => $ol_id]);