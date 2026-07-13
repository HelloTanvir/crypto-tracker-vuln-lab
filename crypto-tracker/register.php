<?php
// register.php — create an account
require 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $name = $_POST['display_name'] ?? '';

    if ($user === '' || $pass === '') {
        $error = 'Username and password are required.';
    } else {
        // VULN: SQLi — user input concatenated straight into the INSERT
        // VULN: plaintext password storage — intentional for coursework
        $q = "INSERT INTO users (username, password, display_name)
              VALUES ('$user', '$pass', '$name')";
        if (mysqli_query($conn, $q)) {
            header('Location: login.php?registered=1');
            exit;
        } else {
            $error = 'Could not register: ' . mysqli_error($conn);
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register — Crypto Tracker</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="nav"><span class="brand">🪙 Crypto Tracker</span>
    <a href="login.php">Login</a>
  </div>
  <div class="wrap">
    <div class="card" style="max-width:420px;margin:0 auto;">
      <h1>Create account</h1>
      <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
      <form method="post" action="register.php">
        <label>Username</label>
        <input name="username" autofocus>
        <label>Password</label>
        <input name="password" type="password">
        <label>Display name</label>
        <input name="display_name">
        <button type="submit">Register</button>
      </form>
      <p class="muted" style="margin-top:16px;">Already have an account? <a class="link" href="login.php">Log in</a></p>
    </div>
  </div>
</body>
</html>
