<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $title = $_POST['title'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    $signaturePath = null;

    if (!empty($_FILES['signature']['name'])) {
        $uploadDir = "../uploads/signatures/";
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['signature']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['signature']['tmp_name'], $targetFile)) {
            $signaturePath = "uploads/signatures/" . $fileName;
        }
    }

    $q = "INSERT INTO salesperson (name, title, email, phone, signature) 
          VALUES ('$name','$title','$email','$phone','$signaturePath')";

    if (mysqli_query($conn, $q)) {
        $id = mysqli_insert_id($conn);
        echo json_encode([
            "success" => true,
            "id" => $id,
            "name" => $name
        ]);
    } else {
        echo json_encode(["success" => false]);
    }
}