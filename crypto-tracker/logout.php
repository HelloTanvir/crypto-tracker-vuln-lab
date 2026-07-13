<?php
// logout.php — destroy the session
require 'config.php';
session_unset();
session_destroy();
header('Location: login.php');
exit;
