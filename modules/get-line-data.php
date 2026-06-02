<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header('Content-Type: application/json');

if (empty($_GET['ol_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing order line id"]);
    exit;
}

$ol_id = (int) $_GET['ol_id'];

$commentsRes = mysqli_query($conn, "SELECT c.id, c.comment, c.created_at, u.username 
    FROM order_line_comments c 
    LEFT JOIN users u ON c.user_id=u.id 
    WHERE c.order_line_id=$ol_id ORDER BY c.created_at DESC");

$comments = [];
while ($row = mysqli_fetch_assoc($commentsRes)) {
    $comments[] = $row;
}

$docsRes = mysqli_query($conn, "SELECT id, file_path, uploaded_at 
    FROM order_line_documents WHERE order_line_id=$ol_id ORDER BY uploaded_at DESC");

$documents = [];
while ($row = mysqli_fetch_assoc($docsRes)) {
    $documents[] = $row;
}

echo json_encode([
    "comments" => $comments,
    "documents" => $documents
]);
