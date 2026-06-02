<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    $q = "INSERT INTO buyer (name, email, phone, address) 
          VALUES ('$name','$email','$phone','$address')";

    if (mysqli_query($conn, $q)) {
        $id = mysqli_insert_id($conn);
        echo json_encode(["success" => true, "id" => $id, "name" => $name]);
    } else {
        echo json_encode(["success" => false]);
    }
}
