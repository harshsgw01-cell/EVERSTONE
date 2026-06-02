<?php
include("../includes/auth.php");
check_auth();
require_role(['Admin']);

header("Content-Type: application/json");

$response = ["success" => false];

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $name = trim($_POST['name']);
    $title = trim($_POST['title']);
    $company = trim($_POST['company']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    if (!empty($name) && !empty($title) && !empty($company) && !empty($email) && !empty($phone) && !empty($address)) {
        $q = "INSERT INTO `shipto` (`name`, `title`, `company`, `email`, `phone`, `address`) 
              VALUES ('$name', '$title', '$company', '$email', '$phone', '$address')";

        if (mysqli_query($conn, $q)) {
            $id = mysqli_insert_id($conn);
            $response = [
                "success" => true,
                "id" => $id,
                "name" => $name
            ];
        }
    }
}

echo json_encode($response);
?>