<?php
// add_holding.php — create a holding
require 'config.php';
require_login();

$error = '';
// VULN: CSRF — this state-changing POST has no CSRF token and no SameSite
// cookie protection, so an off-site auto-submitting form can add holdings.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid       = $_SESSION['user_id'];
    $coin_id   = $_POST['coin_id'] ?? '';
    $amount    = $_POST['amount'] ?? '';
    $buy_price = $_POST['buy_price'] ?? '';
    $notes     = $_POST['notes'] ?? '';

    // VULN: SQLi — every value concatenated into the INSERT with no escaping.
    // VULN: Stored XSS — notes stored verbatim, later echoed on the dashboard.
    $q = "INSERT INTO holdings (user_id, coin_id, amount, buy_price, notes)
          VALUES ($uid, '$coin_id', '$amount', '$buy_price', '$notes')";
    if (mysqli_query($conn, $q)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Could not add holding: ' . mysqli_error($conn);
    }
}

$coins = mysqli_query($conn, "SELECT * FROM coins ORDER BY symbol");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add holding — Crypto Tracker</title>
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
    <div class="card" style="max-width:480px;">
      <h1>Add holding</h1>
      <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
      <form method="post" action="add_holding.php">
        <label>Coin</label>
        <select name="coin_id">
          <?php while ($c = mysqli_fetch_assoc($coins)): ?>
            <option value="<?php echo $c['id']; ?>"><?php echo $c['symbol'] . ' — ' . $c['name']; ?></option>
          <?php endwhile; ?>
        </select>
        <label>Amount</label>
        <input name="amount" placeholder="0.5">
        <label>Buy price (USD)</label>
        <input name="buy_price" placeholder="60000">
        <label>Notes</label>
        <textarea name="notes" placeholder="e.g. long term hold"></textarea>
        <button type="submit">Add holding</button>
      </form>
    </div>
  </div>
</body>
</html>
