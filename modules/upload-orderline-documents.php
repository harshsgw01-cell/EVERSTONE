<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['order_line_id']) || empty($_FILES['document'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$order_line_id = (int) $_POST['order_line_id'];

$uploadDir = "../uploads/orderline_docs/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$file = $_FILES['document'];
// , 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg'
$allowed = ['pdf'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid file type"]);
    exit;
}

$filename = uniqid("doc_") . "." . $ext;
$targetPath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $dbPath = "uploads/orderline_docs/" . $filename;
    $sql = "INSERT INTO order_line_documents (order_line_id, file_path) 
            VALUES ($order_line_id, '$dbPath')";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(["success" => true, "file" => $dbPath]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => mysqli_error($conn)]);
    }
} else {
    http_response_code(500);
    echo json_encode(["error" => "File upload failed"]);
}
