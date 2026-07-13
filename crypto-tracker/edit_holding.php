<?php
// edit_holding.php — update a holding
require 'config.php';
require_login();

$error = '';

// VULN: CSRF — no token on this update action.
// VULN: IDOR — the holding is loaded/updated purely by its id, with no check
// that it belongs to the session user. Any id can be edited.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = $_POST['id'] ?? '';
    $amount    = $_POST['amount'] ?? '';
    $buy_price = $_POST['buy_price'] ?? '';
    $notes     = $_POST['notes'] ?? '';

    // VULN: SQLi — concatenated UPDATE, no escaping.
    // VULN: Stored XSS — notes stored verbatim.
    $q = "UPDATE holdings
          SET amount = '$amount', buy_price = '$buy_price', notes = '$notes'
          WHERE id = $id";
    if (mysqli_query($conn, $q)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Could not update: ' . mysqli_error($conn);
    }
}

// VULN: IDOR — fetch by id from the query string, no ownership check.
$id  = $_GET['id'] ?? '';
$res = mysqli_query($conn, "SELECT h.*, c.symbol FROM holdings h
                            JOIN coins c ON c.id = h.coin_id
                            WHERE h.id = $id");
$h = $res ? mysqli_fetch_assoc($res) : null;
if (!$h) { die('Holding not found.'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Edit holding — Crypto Tracker</title>
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
      <h1>Edit <?php echo $h['symbol']; ?> holding</h1>
      <?php if ($error): ?><div class="msg msg-err"><?php echo $error; ?></div><?php endif; ?>
      <form method="post" action="edit_holding.php">
        <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
        <label>Amount</label>
        <input name="amount" value="<?php echo $h['amount']; ?>">
        <label>Buy price (USD)</label>
        <input name="buy_price" value="<?php echo $h['buy_price']; ?>">
        <label>Notes</label>
        <textarea name="notes"><?php echo $h['notes']; ?></textarea>
        <button type="submit">Save changes</button>
      </form>
    </div>
  </div>
</body>
</html>
