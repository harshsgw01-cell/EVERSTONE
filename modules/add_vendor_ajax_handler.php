<?php
include("../config/database.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_vendor') {

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name == "") {
        echo json_encode(["status" => "error", "message" => "Vendor name required"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO vendors (name, email, phone, address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => $conn->error]);
        exit;
    }

    $stmt->bind_param("ssss", $name, $email, $phone, $address);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "id"     => $stmt->insert_id,
            "name"   => $name
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}
?>