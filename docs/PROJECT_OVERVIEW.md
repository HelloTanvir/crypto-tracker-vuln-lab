# Project Overview & Architecture

A reference for **what this project is, how it works, and what every file does.**
For using/attacking it, see [USING_THE_APP.md](USING_THE_APP.md) and
[USING_SQLMAP.md](USING_SQLMAP.md).

---

## 1. What this project is

The **Crypto Portfolio Tracker** is a small web application themed as a personal
cryptocurrency portfolio manager. A user registers, logs in, and records their
coin holdings (which coin, how much, and the price they bought at). The app then
shows a table of those holdings with each one's current value and profit/loss,
plus the total portfolio value.

Coin prices are **stored in the database and set manually** — the app does *not*
call any live price API. This is deliberate: it keeps the app fully functional
offline with zero external dependencies.

**Its real purpose is teaching.** It is an intentionally insecure app built for a
university cybersecurity course, so students can practise exploiting real
vulnerabilities (SQL injection, XSS, CSRF, IDOR, plaintext passwords) in a safe,
offline lab. The "money app" theme makes the impact of each flaw easy to explain.
Every insecure line in the source is tagged with a `// VULN:` comment.

---

## 2. How it works (architecture)

### Request flow
It's a classic multi-page PHP application — no framework, no router, no
JavaScript framework. Each `.php` file is either a page you view or an action
handler that processes a form and redirects. State between requests is kept in a
**PHP session** (cookie `PHPSESSID`).

```
Browser ──HTTP──> Apache (mod_php) ──mysqli──> MariaDB
                        │
                   PHP session (cookie: PHPSESSID)
```

In the Docker setup this is two containers:

```
   host:80
      │
      ▼
┌───────────────┐        Docker network        ┌───────────────┐
│  web          │  ─────────────────────────▶  │  db           │
│ PHP 8.2 +     │   mysqli to host "db":3306    │ MariaDB 11    │
│ Apache        │                               │ crypto_tracker│
└───────────────┘                               └───────────────┘
```

### How a typical request is served
1. Browser requests e.g. `dashboard.php` with its `PHPSESSID` cookie.
2. Apache runs the PHP file via `mod_php`.
3. The file `require 'config.php'`, which calls `session_start()` and opens the
   MariaDB connection `$conn`.
4. Pages that need a login call `require_login()`, which redirects to
   `login.php` if `$_SESSION['user_id']` isn't set.
5. The page runs SQL through `$conn`, builds HTML, and returns it. Action
   handlers instead do their `INSERT/UPDATE/DELETE` and `header('Location: ...')`
   redirect.

### How login/session works
- `login.php` looks up the user row; on success it stores
  `$_SESSION['user_id']` and `$_SESSION['username']`.
- Every protected page reads `$_SESSION['user_id']` to know who's logged in.
- `logout.php` destroys the session.

> ⚠️ Note for the report: the app trusts a `user_id` supplied in the URL on some
> pages instead of always using the session's user id — that's the IDOR flaw,
> covered per-page below and in USING_THE_APP.md.

---

## 3. Data model

Database: **`crypto_tracker`** (schema + seed data in `crypto-tracker/db.sql`).

### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK, auto-increment | |
| `username` | VARCHAR(50), unique | login name |
| `password` | VARCHAR(255) | **plaintext, on purpose** |
| `display_name` | VARCHAR(100) | shown on the dashboard — **stored-XSS sink** |
| `created_at` | TIMESTAMP | defaults to now |

### `coins`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `symbol` | VARCHAR(10) | e.g. `BTC` |
| `name` | VARCHAR(50) | e.g. `Bitcoin` |
| `price` | DECIMAL(18,2) | manual current USD price |

Seeded coins: BTC 65000, ETH 3200, SOL 150, ADA 0.45, XRP 0.60.

### `holdings`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `user_id` | INT FK → users.id | owner of the holding |
| `coin_id` | INT FK → coins.id | which coin |
| `amount` | DECIMAL(18,8) | how many coins |
| `buy_price` | DECIMAL(18,2) | price paid per coin |
| `notes` | TEXT | free text — **stored-XSS sink** |

**Relationships:** one `user` has many `holdings`; each `holding` references one
`coin`. Current value = `amount × coins.price`; profit/loss =
`(coins.price − buy_price) × amount`. These are computed in PHP at render time,
not stored.

### Seed accounts
| id | username | password | display_name |
|----|----------|----------|--------------|
| 1 | admin | admin123 | Administrator |
| 2 | alice | password1 | Alice |
| 3 | bob | qwerty | Bob |

Alice (id 2) and Bob (id 3) start with a few holdings so dashboards aren't empty.

---

## 4. Pages & files — what each one does

All app files live in `crypto-tracker/`.

### Infrastructure

| File | What it does |
|---|---|
| **`config.php`** | Shared bootstrap: `session_start()`, opens the `mysqli` connection `$conn`, and defines `require_login()`. DB settings come from environment variables (set by Docker) with Kali defaults as fallback. Every page starts with `require 'config.php'`. |
| **`db.sql`** | Creates the database, the three tables, and the seed data. Loaded once at DB startup. |
| **`style.css`** | The single shared stylesheet (dark theme, tables, forms). No JavaScript. |

### Pages a user sees

| File | Type | Functionality |
|---|---|---|
| **`index.php`** | redirect | Landing page. If logged in → `dashboard.php`, else → `login.php`. |
| **`register.php`** | page + handler | Account signup form (username, password, display name). On POST, inserts a new `users` row, then redirects to login. |
| **`login.php`** | page + handler | Login form. On POST, looks up the user by username+password; on success stores `user_id`/`username` in the session and redirects to the dashboard. |
| **`logout.php`** | handler | Destroys the session and redirects to login. |
| **`dashboard.php`** | page | The main screen. Loads the viewed user's `holdings` joined to `coins`, computes each holding's current value and P/L and the portfolio total, and renders the table plus the user's display name. Each row has Edit/Del links. |
| **`add_holding.php`** | page + handler | Form to create a holding (pick coin, amount, buy price, notes). On POST, inserts a `holdings` row for the logged-in user and redirects to the dashboard. |
| **`edit_holding.php`** | page + handler | Loads one holding by `id` into an edit form; on POST updates its amount, buy price, and notes. |
| **`delete_holding.php`** | handler | Deletes the holding whose `id` is in the query string, then redirects to the dashboard. (No page UI — it's a link target.) |
| **`search.php`** | page | Filters the logged-in user's holdings by coin symbol/name (a `LIKE` query on `?q=`) and lists matches. Echoes the search term back in the results heading. |
| **`profile.php`** | page + handler | Two forms: change **display name**, and change **password**. Each posts back to this page and updates the `users` row for the logged-in user. |

### Attacker proof-of-concept files (`crypto-tracker/attacker/`)

These are **not part of the app** — they're standalone HTML pages an "attacker"
hosts elsewhere, used to demonstrate CSRF against a logged-in victim.

| File | What it demonstrates |
|---|---|
| **`csrf_delete.html`** | A hidden `<img>` pointing at `delete_holding.php?id=1`. Opening it while logged in silently deletes that holding (CSRF via GET). |
| **`csrf_addpw.html`** | Two hidden auto-submitting forms: one changes the victim's password to `hacked123` (account takeover), one injects a stored-XSS holding — both without the victim's consent (CSRF via POST). |

---

## 5. Feature → file map

| Feature | Files involved |
|---|---|
| Register | `register.php` → `users` table |
| Login / logout | `login.php`, `logout.php`, `config.php` (session) |
| View portfolio + totals | `dashboard.php` (joins `holdings`+`coins`) |
| Add a holding | `add_holding.php` |
| Edit a holding | `edit_holding.php` |
| Delete a holding | `delete_holding.php` |
| Search holdings | `search.php` |
| Change display name / password | `profile.php` |

---

## 6. Where each vulnerability lives (map to the code)

Every insecure path is marked `// VULN: <type>` in the source. Summary:

| Vulnerability | File(s) | Why it's there |
|---|---|---|
| **SQL Injection** | `login.php`, `search.php`, `register.php`, `add_holding.php`, `edit_holding.php`, `delete_holding.php` | User input concatenated directly into SQL strings — no prepared statements, no escaping. |
| **Stored XSS** | sink: `add_holding.php`/`edit_holding.php` (notes), `profile.php` (display name); render: `dashboard.php`, `search.php` | Stored values echoed to HTML with no `htmlspecialchars`. |
| **Reflected XSS** | `search.php` | `$_GET['q']` echoed into the results heading unencoded. |
| **CSRF** | `add_holding.php`, `delete_holding.php` (GET), `profile.php` | State-changing actions accept requests with no CSRF token; delete accepts GET; cookies have no `SameSite`. |
| **IDOR** | `dashboard.php`, `edit_holding.php`, `delete_holding.php` | Operates on a `user_id`/`id` from the request with no check that it belongs to the logged-in user. |
| **Plaintext passwords** | `db.sql`, `register.php`, `profile.php`, `login.php` | Passwords stored and compared as raw text — no `password_hash()`. |

Detailed exploit steps for each are in [USING_THE_APP.md](USING_THE_APP.md);
remediation (the secure versions) is summarised in
[../crypto-tracker/README.md](../crypto-tracker/README.md).

---

## 7. Deliberately-omitted "partial mitigation"

To show awareness of defence-in-depth, one spot is *inconsistently* hardened:
`search.php` escapes the search term inside the input **field value**
(`htmlspecialchars`) but still reflects it **raw in the results heading**. So the
reflected-XSS still fires via the heading — a talking point about how partial
mitigation gives a false sense of safety. Note this in the report.
