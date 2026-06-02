<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include("../includes/auth.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $email = mysqli_real_escape_string($conn, $email);

    $res = mysqli_query($conn, "SELECT id, username FROM users WHERE email='$email'");
    if (mysqli_num_rows($res) === 1) {
        $user = mysqli_fetch_assoc($res);

        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        mysqli_query($conn, "INSERT INTO password_resets (user_id, token, expires_at) 
                             VALUES ('{$user['id']}', '$token', '$expires')");
                             
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'support@vre.us';
            $mail->Password = 'lium kdrn lwty qkar';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('sales@everstonetech.ca', 'Everstone Support');
            $mail->addAddress($email, $user['username']);

            $mail->isHTML(true);
            $mail->Subject = "Password Reset Request";
            $resetLink = "https://everstonetech.ca/public/password-change.php?token=$token";

            $mail->Body = "
                            <div style='font-family: Arial, sans-serif; text-align: center; background-color: #f9f9f9; padding: 40px;'>
                                <div style='max-width: 500px; margin: auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);'>
                                    <img width='100px' style='margin-bottom: 12px' src='https://everstonetech.ca/assets/Everstone.png' alt='Logo'>
                                    <h2 style='color: #333;'>Password Reset</h2>
                                    <p style='color: #555; font-size: 15px;'>
                                        If you've lost your password or wish to reset it,<br>
                                        use the button below to get started.
                                    </p>
                                    <p>
                                        <a href='$resetLink' 
                                        style='display: inline-block; margin: 20px 0; background: #007bff; color: #ffffff; padding: 12px 20px; text-decoration: none; font-size: 16px; border-radius: 5px; font-weight: bold;'>
                                        Reset Your Password
                                        </a>
                                    </p>
                                    <p style='color: #777; font-size: 13px; line-height: 1.5;'>
                                        If you did not request a password reset, you can safely ignore this email.<br>
                                        Only a person with access to your email can reset your account password.
                                    </p>
                                    <hr style='border:none; border-top:1px solid #eee; margin:25px 0;'>
                                    <p style='color:#999; font-size:12px;'>Everstone Support Team</p>
                                </div>
                            </div>
                        ";

            $mail->send();

            $_SESSION['msg'] = "A reset link has been sent to your email.";
            header("Location: login.php");
            exit();
            // echo "<div class='alert alert-success'>A reset link has been sent to your email.</div>";
        } catch (Exception $e) {
            // echo "<div class='alert alert-danger'>Mailer Error: {$mail->ErrorInfo}</div>";
            $_SESSION['msg'] = "Mailer Error!";
            header("Location: login.php");
            exit();
        }
    } else {
        // echo "<div class='alert alert-danger'>Email not found.</div>";
        $_SESSION['msg'] = "Email not found.";
        header("Location: login.php");
        exit();
    }
}