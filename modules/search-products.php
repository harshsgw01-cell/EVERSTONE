<?php
include("../config/database.php");
$term = trim($_GET['term'] ?? '');

if ($term === '') {
    $result = mysqli_query($conn, "
        SELECT name, part_number, manufacturer, description, sales_price, cost_price 
        FROM products 
        ORDER BY name ASC
    ");
} else {
    $safe = mysqli_real_escape_string($conn, $term);
    $result = mysqli_query($conn, "
        SELECT name, part_number, manufacturer, description, sales_price, cost_price 
        FROM products 
        WHERE name LIKE '%$safe%' 
           OR part_number LIKE '%$safe%' 
           OR manufacturer LIKE '%$safe%'
        ORDER BY name ASC 
        LIMIT 20
    ");
}

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

header('Content-Type: application/json');
echo json_encode($products);