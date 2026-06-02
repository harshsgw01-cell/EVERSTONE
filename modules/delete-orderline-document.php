<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header('Content-Type: application/json');

if (!isset($_POST['id'])) {
    echo json_encode(["error" => "Missing document ID"]);
    exit;
}

$id = (int) $_POST['id'];

$res = mysqli_query($conn, "SELECT file_path FROM order_line_documents WHERE id=$id");
$doc = mysqli_fetch_assoc($res);

if (!$doc) {
    echo json_encode(["error" => "Document not found"]);
    exit;
}

if (mysqli_query($conn, "DELETE FROM order_line_documents WHERE id=$id")) {
    $path = "../" . $doc['file_path'];
    if (file_exists($path)) {
        unlink($path);
    }
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to delete document"]);
}
