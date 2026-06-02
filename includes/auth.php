<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('app_base_path')) {
    function app_base_path()
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        foreach (['/public/', '/modules/'] as $segment) {
            $pos = strpos($script, $segment);
            if ($pos !== false) {
                return rtrim(substr($script, 0, $pos), '/');
            }
        }

        return '';
    }
}

if (!function_exists('app_url')) {
    function app_url($path = '')
    {
        $base = app_base_path();
        $path = '/' . ltrim($path, '/');
        $url = ($base === '' ? '' : $base) . $path;

        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . $url;
        }

        return $url;
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path = '')
    {
        return app_url('assets/' . ltrim($path, '/'));
    }
}

$base_url = app_url('public/');

include_once(__DIR__ . "/../config/database.php");  

/**
 * Login using DB users table
 */
function login($name_email, $password)
{
    global $conn;

    $name_email = mysqli_real_escape_string($conn, $name_email);

    $q = mysqli_query($conn, "SELECT * FROM users WHERE username='$name_email' OR email='$name_email' LIMIT 1");

    if ($user = mysqli_fetch_assoc($q)) {
        $stored = $user['password'];
        $valid  = false;

        if (password_verify($password, $stored))  $valid = true; // bcrypt
        elseif (md5($password) === $stored)       $valid = true; // MD5
        elseif ($password === $stored)            $valid = true; // plain text

        if (!$valid) return false;

        $_SESSION['user']     = $user['username'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['role']     = $user['role'];
        return true;
    }

    return false;
}

/**
 * Check login
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

/**
 * Protect page
 */
function check_auth()
{
    global $base_url;

    if (!is_logged_in()) {
        header("Location: " . app_url('public/login.php'));
        exit;
    }
}
function require_role(array $roles)
{
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        echo "<div style='padding:40px;font-family:sans-serif'>
                <h3>⛔ Access Denied</h3>
                <p>You do not have permission to access this page.</p>
              </div>";
        exit;
    }
}


/**
 * Role guard
 */
function check_role($roles = [])
{
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        header("Location: " . app_url('public/unauthorized.php'));
        exit;
    }
}

/**
 * Logout
 */
function logout()
{
    session_unset();
    session_destroy();
    header("Location: " . app_url('public/login.php'));
    exit;
}
