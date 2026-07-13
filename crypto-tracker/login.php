<?php
// login.php — session-based auth
require 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // VULN: SQLi auth bypass — credentials concatenated into the query with no
    // escaping and no prepared statement. e.g. username: admin' --
    // VULN: plaintext password storage — password compared as raw text
    $q = "SELECT * FROM users WHERE username = '$user' AND password = '$pass'";
    $res = mysqli_query($conn, $q);

    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['username'] = $row['username'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials.';
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Login — Crypto Tracker</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="nav"><span class="brand">🪙 Crypto Tracker</span>
    <a href="register.php">Register</a>
  </div>
  <div class="wrap">
    <div class="card" style="max-width:420px;margin:0 auto;">
      <h1>Log in</h1>
      <?php if (isset($_GET['registered'])): ?>
        <div class="msg msg-ok">Account created — you can log in now.</div>
      <?php endif; ?>
      <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
      <form method="post" action="login.php">
        <label>Username</label>
        <input name="username" autofocus>
        <label>Password</label>
        <input name="password" type="password">
        <button type="submit">Log in</button>
      </form>
      <p class="muted" style="margin-top:16px;">No account? <a class="link" href="register.php">Register</a></p>
    </div>
  </div>
</body>
</html>
