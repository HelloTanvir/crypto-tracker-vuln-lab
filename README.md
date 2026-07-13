# Crypto Portfolio Tracker — Deliberately Vulnerable Lab

⚠️ **Intentionally insecure teaching app** for a university cybersecurity course.
It contains real, exploitable vulnerabilities **on purpose** so students can
practise attacking them in a safe, offline lab. **Run it on your own machine
only. Never expose it to a network or the public internet.**

Everything runs in Docker — **nothing is installed on your host machine.**

## What's in here

```
cybersecurity/
├── docker-compose.yml        # web + db (+ on-demand sqlmap) containers
├── Dockerfile                # PHP 8.2 + Apache image for the app
├── Dockerfile.sqlmap         # sqlmap image (exploit tool)
├── crypto-tracker/           # the PHP app source + db.sql seed + attacker/ PoCs
│   └── README.md             # deployment reference (Docker + native Kali)
├── docs/
│   ├── USING_THE_APP.md      # how to use the app + trigger each vuln in a browser
│   └── USING_SQLMAP.md       # how to automate SQL injection with sqlmap
└── sqlmap-output/            # sqlmap writes evidence here (created on first run)
```

## Quick start (command line)

From this folder (`cybersecurity/`):

```bash
docker compose up --build -d      # build + start the app and database
```

Then open **http://localhost/crypto-tracker/** in your browser.

Stop it when you're done:
```bash
docker compose down               # stop (keeps the database)
docker compose down -v            # stop AND wipe the database (fresh reseed next time)
```

Seed logins (passwords stored in plaintext, on purpose):

| username | password  |
|----------|-----------|
| admin    | admin123  |
| alice    | password1 |
| bob      | qwerty    |

## Quick start (Docker Desktop app)

Since you have **Docker Desktop** installed, you can drive most of this from the
GUI instead of the terminal.

### 1. Start the stack
1. Open **Docker Desktop**.
2. Click the **Terminal** tab at the bottom of the Docker Desktop window (the
   built-in terminal — it's a normal shell running on your Mac).
3. Change into this project folder and start it:
   ```bash
   cd /Users/tanvir/projects/bubt/cybersecurity
   docker compose up --build -d
   ```
4. Go to the **Containers** view in the left sidebar. You'll see a
   **cybersecurity** group with `web` and `db` running (green).
5. Click the **`web`** container's port link (`80:80`) — or just browse to
   **http://localhost/crypto-tracker/**.

### 2. Start / stop without the terminal
In **Containers**, use the ▶ / ⏹ buttons next to the **cybersecurity** group to
start and stop the whole stack. (The very first start still needs the
`docker compose up --build` command above so Docker can build the images.)

### 3. Run commands inside a container (Exec)
Click a container → the **Exec** tab opens a shell *inside* it. Useful for
poking at the database:
```bash
# inside the db container's Exec tab:
mariadb -ucrypto -pcryptopass crypto_tracker -e "SELECT id,username,password FROM users;"
```

### 4. View logs
Click a container → the **Logs** tab shows live output (Apache/PHP errors,
MariaDB startup, etc.).

> The Docker Desktop **Terminal** tab and each container's **Exec** tab both
> accept the same commands shown throughout these docs. Anywhere a guide says
> "run this in a terminal," the Docker Desktop Terminal tab works too.

## Documentation

- **[docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md)** — what the project is,
  how it works, the data model, and what every page/file does. **Start here.**
- **[docs/USING_THE_APP.md](docs/USING_THE_APP.md)** — use the app as a normal
  user, and step-by-step browser walkthroughs for every vulnerability
  (SQLi, stored/reflected XSS, CSRF, IDOR, plaintext passwords).
- **[docs/USING_SQLMAP.md](docs/USING_SQLMAP.md)** — automate SQL injection and
  dump the database with the containerized `sqlmap`.
- **[crypto-tracker/README.md](crypto-tracker/README.md)** — deployment
  reference, including running natively on Kali and the remediation summary for
  the report.

## Vulnerability map (quick reference)

| # | Vuln | Where | See |
|---|------|-------|-----|
| 1 | SQL Injection | `login.php`, `search.php`, register/add/edit | app + sqlmap docs |
| 2 | Stored XSS | notes, display name → rendered on `dashboard.php` | app doc |
| 3 | Reflected XSS | `search.php` | app doc |
| 4 | CSRF | add / delete (GET) / password change | app doc + `attacker/` |
| 5 | IDOR | `dashboard.php?user_id=`, edit, delete | app doc |
| 6 | Plaintext passwords | `db.sql`, register, profile | app + sqlmap docs |
