<?php
session_start();
include("../config/database.php");
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';
$city = $_POST['city'] ?? '';
$state = $_POST['state'] ?? '';
$zip = $_POST['zip'] ?? '';

if (!$name || !$address) {
    echo json_encode(["status" => "error"]);
    exit;
}

mysqli_query($conn, "
    INSERT INTO shipto (name, address, city, state, zip)
    VALUES (
        '".mysqli_real_escape_string($conn,$name)."',
        '".mysqli_real_escape_string($conn,$address)."',
        '".mysqli_real_escape_string($conn,$city)."',
        '".mysqli_real_escape_string($conn,$state)."',
        '".mysqli_real_escape_string($conn,$zip)."'
    )
");

$id = mysqli_insert_id($conn);

echo json_encode([
    "status" => "success",
    "id" => $id,
    "name" => $name
]);
