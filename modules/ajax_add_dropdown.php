<?php
include("../includes/auth.php");
include("../config/database.php");
check_auth();

header('Content-Type: application/json');

$type = $_POST['type'] ?? '';

if ($type === 'customer') {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']    ?? ''));
    $title   = mysqli_real_escape_string($conn, trim($_POST['title']   ?? ''));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']   ?? ''));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']   ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));

    if (!$name || !$email) {
        echo json_encode(['error' => 'Name and email are required.']);
        exit;
    }

    mysqli_query($conn, "INSERT INTO customers (name, title, email, phone, address)
                         VALUES ('$name','$title','$email','$phone','$address')");
    $id = mysqli_insert_id($conn);
    echo json_encode(['id' => $id, 'text' => $name]);
    exit;
}

if ($type === 'billto') {
    $title   = mysqli_real_escape_string($conn, trim($_POST['title']   ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));

    if (!$title || !$address) {
        echo json_encode(['error' => 'Title and address are required.']);
        exit;
    }

    mysqli_query($conn, "INSERT INTO billto (title, address) VALUES ('$title','$address')");
    $id = mysqli_insert_id($conn);
    echo json_encode(['id' => $id, 'text' => $title]);
    exit;
}

if ($type === 'shipto') {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name']    ?? ''));
    $title   = mysqli_real_escape_string($conn, trim($_POST['title']   ?? ''));
    $company = mysqli_real_escape_string($conn, trim($_POST['company'] ?? ''));
    $email   = mysqli_real_escape_string($conn, trim($_POST['email']   ?? ''));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone']   ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));

    if (!$name || !$company || !$address) {
        echo json_encode(['error' => 'Name, company and address are required.']);
        exit;
    }

    mysqli_query($conn, "INSERT INTO shipto (name, title, company, email, phone, address)
                         VALUES ('$name','$title','$company','$email','$phone','$address')");
    $id = mysqli_insert_id($conn);
    echo json_encode(['id' => $id, 'text' => $name]);
    exit;
}

echo json_encode(['error' => 'Unknown type.']);