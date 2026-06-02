<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $title = $_POST['title'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $q = "INSERT INTO customers (name, title, email, phone, address) 
          VALUES ('$name','$title','$email','$phone','$address')";
    if (mysqli_query($conn, $q)) {
        $id = mysqli_insert_id($conn);
        echo json_encode(["success" => true, "id" => $id, "name" => $name]);
    } else {
        echo json_encode(["success" => false]);
    }
}
