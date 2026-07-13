<?php
// index.php — landing. Send logged-in users to the dashboard, others to login.
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
