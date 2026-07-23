# Using the App

A walkthrough of the Crypto Portfolio Tracker: first as a normal user, then how
to trigger each intentional vulnerability from a browser. Take a screenshot at
each ✅ step for the report.

> Base URL (Docker): **http://localhost/crypto-tracker/**
> Seed logins: `admin/admin123`, `alice/password1`, `bob/qwerty`

---

## Part A — Normal usage (the "honest" app)

### 1. Register
1. Go to **http://localhost/crypto-tracker/register.php**.
2. Enter a username, password, and display name → **Register**.
3. You're redirected to the login page.

### 2. Log in
1. Go to **login.php**, enter your credentials → **Log in**.
2. You land on the **Dashboard**.

### 3. Dashboard
Shows your holdings in a table — coin, amount, buy price, current price, current
value, and profit/loss — plus your **total portfolio value**. Your display name
is shown in the heading.

### 4. Add a holding
1. **Add holding** in the nav.
2. Pick a coin, enter an amount and buy price, add a note (e.g. "long term hold").
3. **Add holding** → back to the dashboard, now showing the new row and updated
   total.

### 5. Edit / delete a holding
- **Edit** on a row → change amount / buy price / notes → **Save changes**.
- **Del** on a row → removes it.

### 6. Search
1. **Search** in the nav.
2. Type a coin symbol or name (e.g. `BTC`) → **Search**.
3. Matching holdings are listed; the heading echoes what you searched for.

### 7. Profile
- Change your **display name**.
- Change your **password**.

That's the whole app. Everything below abuses these same features.

---

## Part B — Exploiting each vulnerability

### 1. SQL Injection — login auth bypass
The login query concatenates your input straight into SQL, so you can comment
out the password check.

1. Go to **login.php**.
2. **Username:** `admin' -- ` &nbsp;*(note the trailing space after `--`)*
3. **Password:** anything (e.g. `x`).
4. **Log in.**

✅ You're logged in as **admin** without knowing the password. The query became:
```sql
SELECT * FROM users WHERE username = 'admin' -- ' AND password = 'x'
```
The `-- ` turns the rest into a comment. Try `' OR '1'='1' -- ` too.

> Automating SQLi to dump the whole database → see **USING_SQLMAP.md**.

---

### 2. Stored XSS — malicious note fires on the dashboard
Notes are saved verbatim and rendered on the dashboard with no encoding.

1. Log in (any user). Go to **Add holding**.
2. In **Notes**, enter:
   ```html
   <script>alert(document.cookie)</script>
   ```
3. Fill the other fields with anything valid → **Add holding**.

✅ Every time the **dashboard** loads, the script runs and pops an alert with the
session cookie. It fires for anyone who views that portfolio — combine with IDOR
(#5) for a cross-user demo.

The **display name** on the Profile page is the same kind of sink: set it to
`<script>alert('xss-name')</script>` and it fires on the dashboard heading.

---

### 3. Reflected XSS — malicious search link
The search page echoes your raw query back into the results heading.

1. Log in.
2. Visit this URL (or type the payload into the search box):
   ```
   http://localhost/crypto-tracker/search.php?q=<script>alert('XSS')</script>
   ```

✅ The script executes on page load. The payload appears verbatim in the page
source inside `Results for: ...`. A crafted link like this, sent to a
logged-in victim, runs attacker JavaScript in their session.

---

### 4. CSRF — attacker page acts as the victim
State-changing actions have no CSRF token, and delete even accepts GET. The
ready-made proof-of-concept pages live in `crypto-tracker/attacker/`.

**Setup:** log in as a victim (e.g. `bob`) in your browser and keep the tab
open. Note their current holdings on the dashboard.

**a) Silent delete via GET** — browse to
`http://localhost/crypto-tracker/attacker/csrf_delete.html` in the same browser.
The page fetches the victim's own dashboard, scrapes the id of the **first**
holding, and fires `delete_holding.php?id=<that id>` — no hardcoded id, so it
always kills the top holding whatever it is.

✅ Reload the dashboard — the first holding is gone. The victim never clicked
anything.

> Serve it under the app's host (`http://localhost/...`) so the dashboard read
> is same-origin; opening it as a bare `file://` blocks that read step.

**b) Account takeover + stored XSS** — open
`crypto-tracker/attacker/csrf_addpw.html`. Two hidden auto-submitting forms fire:
one changes the victim's password to `hacked123`, the other injects a
stored-XSS holding.

✅ The victim's password is now `hacked123` (log out and prove it), and a
malicious holding appears in their portfolio.

**c) Both at once** — open `crypto-tracker/attacker/csrf_all.html` to run the
dynamic delete **and** the password change **and** the XSS injection from a
single page.

> Why it works: no CSRF token is validated and the session cookie has no
> `SameSite` restriction, so the browser attaches it to the cross-site request.

---

### 5. IDOR — view/modify another user's portfolio
The dashboard trusts a `user_id` from the URL with no ownership check.

1. Log in as **bob** (`bob/qwerty`).
2. Visit:
   ```
   http://localhost/crypto-tracker/dashboard.php?user_id=2
   ```

✅ You're viewing **alice's** portfolio (`user_id=2`) while logged in as bob.
Change the number to browse other users. The Edit/Del links on those rows also
work, because `edit_holding.php` and `delete_holding.php` operate purely on the
holding `id` — so you can modify or delete another user's holdings too.

---

### 6. Plaintext password storage
Passwords are stored and compared as raw text — no hashing anywhere.

**Prove it directly (Docker Desktop → db container → Exec tab, or a terminal):**
```bash
docker compose exec db mariadb -ucrypto -pcryptopass crypto_tracker \
  -e "SELECT username, password FROM users;"
```
✅ You see readable passwords (`admin123`, `password1`, `qwerty`).

**Chaining:** dumping this table via SQLi (see USING_SQLMAP.md) hands an attacker
working credentials for every account — one vulnerability (SQLi) amplified by
another (plaintext storage) into full account takeover.

---

## Suggested screenshot checklist (for the report)
- [ ] SQLi login bypass — payload in the box + resulting admin session
- [ ] sqlmap dumping `users` (see USING_SQLMAP.md)
- [ ] Plaintext passwords visible in the dump / DB
- [ ] Stored XSS alert firing on the dashboard
- [ ] Reflected XSS alert + payload in page source
- [ ] CSRF — holding count before/after, and password-change success
- [ ] IDOR — another user's portfolio while logged in as someone else
- [ ] One paragraph: SQLi → plaintext creds → account takeover (chaining)
