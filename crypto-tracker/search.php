<?php
// search.php — filter the logged-in user's holdings by coin symbol/name
require 'config.php';
require_login();

$uid  = $_SESSION['user_id'];
$term = $_GET['q'] ?? '';
$rows = [];
$ran  = isset($_GET['q']);

if ($ran) {
    // VULN: SQLi — $_GET['q'] concatenated into a LIKE clause with no escaping.
    // This is the primary sqlmap / UNION-injection target.
    $q = "SELECT h.*, c.symbol, c.name AS coin_name, c.price AS current_price
          FROM holdings h
          JOIN coins c ON c.id = h.coin_id
          WHERE h.user_id = $uid
            AND (c.symbol LIKE '%$term%' OR c.name LIKE '%$term%')
          ORDER BY h.id";
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
  <title>Search — Crypto Tracker</title>
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
    <div class="card">
      <h1>Search holdings</h1>
      <form method="get" action="search.php">
        <label>Coin symbol or name</label>
        <input name="q" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q'], ENT_QUOTES) : ''; ?>" placeholder="BTC">
        <button type="submit">Search</button>
      </form>
    </div>

    <?php if ($ran): ?>
    <div class="card">
      <!-- VULN: Reflected XSS — raw $_GET['q'] echoed back into the heading -->
      <h2>Results for: <?php echo $_GET['q']; ?></h2>
      <?php if (!$rows): ?>
        <p class="muted">No matching holdings.</p>
      <?php else: ?>
      <table>
        <tr><th>Coin</th><th>Amount</th><th>Buy price</th><th>Current</th><th>Notes</th></tr>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo $r['symbol']; ?> <span class="muted"><?php echo $r['coin_name']; ?></span></td>
          <td><?php echo rtrim(rtrim($r['amount'], '0'), '.'); ?></td>
          <td>$<?php echo number_format($r['buy_price'], 2); ?></td>
          <td>$<?php echo number_format($r['current_price'], 2); ?></td>
          <!-- VULN: Stored XSS — notes echoed verbatim here too -->
          <td><?php echo $r['notes']; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
