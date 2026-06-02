<?php
// Create this as: /public_html/public/genhash.php
$pass = "Admin@123";   // ← set your desired password here
echo password_hash($pass, PASSWORD_DEFAULT);
