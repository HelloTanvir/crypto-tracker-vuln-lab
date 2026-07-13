<?php
// profile.php — edit display name + change password
require 'config.php';
require_login();

$uid = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VULN: CSRF — neither action below validates a token, so an off-site
    // auto-submitting form can change a logged-in victim's display name or
    // password (account takeover for the password case).
    $action = $_POST['action'] ?? '';

    if ($action === 'display_name') {
        $name = $_POST['display_name'] ?? '';
        // VULN: SQLi + Stored XSS — value concatenated in and later rendered
        // unescaped on the dashboard.
        mysqli_query($conn, "UPDATE users SET display_name = '$name' WHERE id = $uid");
        $msg = 'Display name updated.';
    } elseif ($action === 'password') {
        $newpass = $_POST['password'] ?? '';
        // VULN: plaintext password storage — stored as raw text, no hashing.
        // VULN: SQLi — concatenated UPDATE.
        mysqli_query($conn, "UPDATE users SET password = '$newpass' WHERE id = $uid");
        $msg = 'Password changed.';
    }
}

$res  = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid");
$user = mysqli_fetch_assoc($res);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Profile — Crypto Tracker</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="nav">
    <span class="brand">🪙 Crypto Tracker</span>
    <a href="dashboard.php">Dashboard</a>
    <a href="add_holding.php">Add holding</a>
    <a href="search.php">Search</a>
    <a href="profile.php">Profile</a>
    <a href="logout.php">Logout</a>
  </div>
  <div class="wrap">
    <?php if ($msg): ?><div class="msg msg-ok"><?php echo $msg; ?></div><?php endif; ?>

    <div class="card" style="max-width:480px;">
      <h1>Profile</h1>
      <p class="muted">Logged in as <strong><?php echo $user['username']; ?></strong></p>
      <form method="post" action="profile.php">
        <input type="hidden" name="action" value="display_name">
        <label>Display name</label>
        <!-- value echoed unescaped so a stored payload is visible here too -->
        <input name="display_name" value="<?php echo $user['display_name']; ?>">
        <button type="submit">Update display name</button>
      </form>
    </div>

    <div class="card" style="max-width:480px;">
      <h2>Change password</h2>
      <form method="post" action="profile.php">
        <input type="hidden" name="action" value="password">
        <label>New password</label>
        <input name="password" type="password">
        <button type="submit">Change password</button>
      </form>
    </div>
  </div>
</body>
</html>
