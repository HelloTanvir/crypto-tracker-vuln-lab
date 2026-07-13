<?php
// dashboard.php — portfolio table + total value
require 'config.php';
require_login();

// VULN: IDOR — any user_id from the query string is honored with no check that
// it belongs to the logged-in session user. dashboard.php?user_id=2 shows
// another user's portfolio.
$uid = $_GET['user_id'] ?? $_SESSION['user_id'];

// Fetch the profile of whichever user we're viewing (for the display name).
$ures = mysqli_query($conn, "SELECT * FROM users WHERE id = $uid");
$viewed = $ures ? mysqli_fetch_assoc($ures) : null;

// Holdings joined to coins so we can compute current value.
$q = "SELECT h.*, c.symbol, c.name AS coin_name, c.price AS current_price
      FROM holdings h
      JOIN coins c ON c.id = h.coin_id
      WHERE h.user_id = $uid
      ORDER BY h.id";
$res = mysqli_query($conn, $q);

$total = 0.0;
$rows  = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $r['value'] = $r['amount'] * $r['current_price'];
        $r['pl']    = ($r['current_price'] - $r['buy_price']) * $r['amount'];
        $total += $r['value'];
        $rows[] = $r;
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard — Crypto Tracker</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="nav">
    <span class="brand">🪙 Crypto Tracker</span>
    <a href="dashboard.php">Dashboard</a>
    <a href="add_holding.php">Add holding</a>
    <a href="search.php">Search</a>
    <a href="profile.php">Profile</a>
    <a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a>
  </div>
  <div class="wrap">
    <!-- VULN: Stored XSS — display_name rendered without htmlspecialchars -->
    <h1>Portfolio — <?php echo $viewed ? $viewed['display_name'] : 'Unknown'; ?></h1>

    <div class="card">
      <?php if (!$rows): ?>
        <p class="muted">No holdings yet. <a class="link" href="add_holding.php">Add one</a>.</p>
      <?php else: ?>
      <table>
        <tr>
          <th>Coin</th><th>Amount</th><th>Buy price</th><th>Current</th>
          <th>Value</th><th>P/L</th><th>Notes</th><th></th>
        </tr>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo $r['symbol']; ?> <span class="muted"><?php echo $r['coin_name']; ?></span></td>
          <td><?php echo rtrim(rtrim($r['amount'], '0'), '.'); ?></td>
          <td>$<?php echo number_format($r['buy_price'], 2); ?></td>
          <td>$<?php echo number_format($r['current_price'], 2); ?></td>
          <td>$<?php echo number_format($r['value'], 2); ?></td>
          <td class="<?php echo $r['pl'] >= 0 ? 'profit' : 'loss'; ?>">
            <?php echo ($r['pl'] >= 0 ? '+' : '') . '$' . number_format($r['pl'], 2); ?>
          </td>
          <!-- VULN: Stored XSS — notes rendered verbatim, no output encoding -->
          <td><?php echo $r['notes']; ?></td>
          <td>
            <a class="btn btn-sm" href="edit_holding.php?id=<?php echo $r['id']; ?>">Edit</a>
            <!-- VULN: CSRF via GET + IDOR — delete is a plain GET link, no token -->
            <a class="btn btn-sm btn-danger" href="delete_holding.php?id=<?php echo $r['id']; ?>">Del</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <div class="total">Total value: $<?php echo number_format($total, 2); ?></div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
