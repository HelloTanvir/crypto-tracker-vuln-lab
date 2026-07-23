# Deploy on Kali Linux with Apache (native, no Docker)

How to run the Crypto Portfolio Tracker lab directly on a Kali PC using
Apache2 + `mod_php` + MariaDB — no Docker involved.

> ⚠️ **This app is intentionally vulnerable.** Run it on an isolated lab
> machine / LAN only. Never expose it to a shared or public network.

The app is served at **`http://localhost/crypto-tracker/`** (port 80), which
keeps every URL and the `attacker/*.html` CSRF PoCs working verbatim.

---

## 1. Get the files onto the Kali PC

Pick whichever is easiest:

**Via git** (if you've pushed this repo somewhere):
```bash
git clone <your-repo-url> cybersecurity
cd cybersecurity
```

**Via SCP** (from another machine that has the repo):
```bash
# run on the Kali PC — replace with the source host's IP and user
scp -r <user>@<source-ip>:/path/to/cybersecurity ~/cybersecurity
```

**Via USB stick** — just copy the whole `cybersecurity` folder over.

---

## 2. Install the LAMP stack

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php php-mysqli libapache2-mod-php
sudo systemctl start apache2 mariadb
sudo systemctl enable apache2 mariadb   # optional: auto-start on boot
```

---

## 3. Deploy the app into Apache's web root

```bash
# from inside the cybersecurity/ folder you copied over
sudo cp -r crypto-tracker /var/www/html/
sudo chown -R www-data:www-data /var/www/html/crypto-tracker
```

---

## 4. Load the database

```bash
sudo mariadb < /var/www/html/crypto-tracker/db.sql
```

This creates the `crypto_tracker` database, its tables, and the seed data
(coins, users, holdings).

---

## 5. Fix the DB auth (the #1 native gotcha)

`config.php` connects as **`root` with an empty password**. On modern Kali,
MariaDB's `root` uses *socket* auth, so Apache (running as `www-data`) can't
log in that way — you'd get **"DB connection failed"**. Run this once so local
processes can connect as `root` with an empty password, matching what
`config.php` expects:

```bash
sudo mariadb -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD(''); FLUSH PRIVILEGES;"
```

*(Fine for a deliberately-vulnerable localhost lab. Never do this on a real
server.)*

> **Prefer a dedicated DB user instead?** Create one and point `config.php` at
> it (edit `$DB_USER` / `$DB_PASS` near the top of the file):
> ```bash
> sudo mariadb -e "CREATE USER IF NOT EXISTS 'crypto'@'localhost' IDENTIFIED BY 'cryptopass'; \
>   GRANT ALL PRIVILEGES ON crypto_tracker.* TO 'crypto'@'localhost'; FLUSH PRIVILEGES;"
> ```

---

## 5b. Stop the browser caching the attacker PoCs (recommended)

The Docker build ships an Apache rule that sends `Cache-Control: no-store` for
the `attacker/` directory. Without it, Chrome caches the PoC pages and can
silently re-run a **stale** `csrf_delete.html` — which looks like "the CSRF did
nothing". Reproduce that rule natively:

```bash
sudo a2enmod headers
sudo cp apache-attacker.conf /etc/apache2/conf-enabled/attacker.conf
sudo systemctl reload apache2
```

(`apache-attacker.conf` is in the repo root and targets
`/var/www/html/crypto-tracker/attacker`, which matches this native layout.)

## 5c. Where the stolen cookies land

The stored-XSS payload exfiltrates `document.cookie` to
`attacker/collect.php`, which appends it to a `loot/cookie.txt` file. With no
Docker bind-mount here, that path is on disk directly:

```bash
cat /var/www/html/crypto-tracker/attacker/loot/cookie.txt
```

`collect.php` creates the `loot/` dir on first hit; the step-3 `chown www-data`
is what makes it writable. If nothing appears, re-run that `chown`.

---

## 6. Browse to it

On the Kali PC itself:
```
http://localhost/crypto-tracker/
```

Seed accounts (plaintext, on purpose):

| username | password  |
|----------|-----------|
| admin    | admin123  |
| alice    | password1 |
| bob      | qwerty    |

---

## Accessing it from another PC (optional)

To reach the lab from a different machine, find the Kali IP (`ip a`) and browse
to:
```
http://<kali-ip>/crypto-tracker/
```

Two caveats:
- The `attacker/*.html` CSRF PoC pages — and the stored-XSS cookie-stealer
  payload that points at `attacker/collect.php` — have `http://localhost/...`
  hardcoded. To fire them from another machine, replace `localhost` with the
  Kali IP (in the files, and in any payload you inject by hand).
- ⚠️ Only do this on an **isolated lab LAN** — this app is intentionally
  exploitable.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `DB connection failed` | Run the step 5 command; check `sudo systemctl status mariadb` |
| Browser **downloads** the `.php` file instead of running it | `sudo a2enmod php8.* && sudo systemctl restart apache2` |
| `Call to undefined function mysqli_connect()` | `sudo apt install -y php-mysqli && sudo systemctl restart apache2` |
| Socket error `/run/mysqld/mysqld.sock` | `sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld && sudo systemctl restart mariadb` |
| Adding/editing holdings silently fails | Re-run the `chown www-data` step (step 3) |

---

## Resetting the database

To wipe and reseed (e.g. after students have modified data):
```bash
sudo mariadb < /var/www/html/crypto-tracker/db.sql
```
The seed script drops and recreates the tables, so this restores a clean state.
