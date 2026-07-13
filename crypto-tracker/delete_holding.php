<?php
// delete_holding.php — remove a holding
require 'config.php';
require_login();

// VULN: CSRF via GET — this destructive action accepts a plain GET request with
// no CSRF token, so <img src="delete_holding.php?id=1"> on an attacker page
// deletes a logged-in victim's holding.
// VULN: IDOR — the id is deleted with no check that it belongs to the session
// user, so any user's holding can be removed by id.
// VULN: SQLi — id concatenated straight into the DELETE.
$id = $_GET['id'] ?? '';
mysqli_query($conn, "DELETE FROM holdings WHERE id = $id");

header('Location: dashboard.php');
exit;
