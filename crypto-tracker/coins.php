<?php
// coins.php — PUBLIC coin price lookup (no login required).
//
// VULN: SQLi — $_GET['q'] is concatenated straight into the query with no
// escaping and no prepared statement. Unlike search.php this endpoint has NO
// require_login(), so it is the easiest sqlmap target: point sqlmap here with
// no session cookie at all and it dumps the whole database (users + plaintext
// passwords included), because the injection runs on the same DB connection.
//
//   sqlmap -u "http://localhost/crypto-tracker/coins.php?q=BTC" --batch --dbs
//   sqlmap -u "http://localhost/crypto-tracker/coins.php?q=BTC" --batch \
//          -D crypto_tracker -T users --dump
require 'config.php';   // NOTE: no require_login() — intentionally public.

$term = $_GET['q'] ?? '';
$rows = [];
$ran  = isset($_GET['q']);

if ($ran) {
    // VULN: SQLi sink — classic string-in-LIKE injection, UNION-friendly
    // (the SELECT below exposes 3 columns: symbol, name, price).
    $q = "SELECT symbol, name, price
          FROM coins
          WHERE symbol LIKE '%$term%' OR name LIKE '%$term%'
          ORDER BY symbol";
    $res = mysqli_query($conn, $q);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Coin prices — Crypto Tracker</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="nav">
    <span class="brand">🪙 Crypto Tracker</span>
    <a href="login.php">Log in</a>
    <a href="register.php">Register</a>
  </div>
  <div class="wrap">
    <div class="card">
      <h1>Public coin prices</h1>
      <p class="muted">Look up a coin — no account needed.</p>
      <form method="get" action="coins.php">
        <label>Coin symbol or name</label>
        <input name="q" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES) : ''; ?>" placeholder="BTC">
        <button type="submit">Look up</button>
      </form>
    </div>

    <?php if ($ran): ?>
    <div class="card">
      <h2>Results</h2>
      <?php if (!$rows): ?>
        <p class="muted">No matching coins.</p>
      <?php else: ?>
      <table>
        <tr><th>Symbol</th><th>Name</th><th>Price (USD)</th></tr>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo $r['symbol']; ?></td>
          <td><?php echo $r['name']; ?></td>
          <td>$<?php echo number_format($r['price'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
