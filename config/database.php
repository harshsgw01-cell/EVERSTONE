<?php
$host    = "127.0.0.1";
$db_name = "everstone_crm";
$db_user = "root";
$db_pass = "";
$port    = 3306;
$conn = new mysqli($host, $db_user, $db_pass, $db_name, $port);

if ($conn->connect_error) {
    die("Connection failed (" . $conn->connect_errno . "): " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
