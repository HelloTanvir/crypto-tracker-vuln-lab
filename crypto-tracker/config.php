<?php
// config.php — DB connection + session bootstrap
// Shared by every page. Localhost lab only.

session_start();

// Lab credentials — localhost only, never public.
// Values come from environment variables when set (the Docker setup passes
// them in); otherwise they fall back to the stock Kali defaults, where root
// has no password and connects over the local socket. So this same file works
// both dockerized and installed natively on Kali, unchanged.
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';  // '' = empty pw
$DB_NAME = getenv('DB_NAME') ?: 'crypto_tracker';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$conn) {
    // Fail loudly in the lab so setup problems are obvious.
    die('DB connection failed: ' . mysqli_connect_error());
}

// Small helper so pages can gate on login without repeating themselves.
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
