<?php
include("../config/database.php");

$order_id = intval($_GET['order_id']);
$result = mysqli_query($conn, "SELECT SUM(qty * unit_price) AS total FROM order_lines WHERE order_id=$order_id");
$row = mysqli_fetch_assoc($result);
echo json_encode(["total" => $row['total'] ?? 0]);
