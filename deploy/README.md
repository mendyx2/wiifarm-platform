# The preview site

A copy of the platform you can open in a browser to see and test it, with
sample members. It runs on Railway from this repository. It is for testing
only: no real members, no real money.

## What it is

- One small server: the website, the nightly jobs (matching, autoship,
  awards) and the background emails, from the `Dockerfile` and
  `deploy/start.sh`.
- Its data (database, uploaded photos, its secret key) is kept on a Railway
  **volume** mounted at `/data`, so it survives restarts and redeploys.
- Each start prepares the database, adds the admin from the settings below
  (once), and, with `APP_DEMO=true`, ten sample members (password
  `password123`). Starting again never doubles anything.

## Setting it up on Railway (about 10 minutes)

1. Sign in at railway.com with the GitHub account that owns this repository.
2. **New Project > Deploy from GitHub repo**, and choose
   `mendyx2/wiifarm-platform`. In the service's **Settings > Source**, set
   the branch to `claude/inspiring-davinci-lsw3ue` (or `main` once the work
   is merged there). Railway finds the `Dockerfile` by itself.
3. **Add a volume** to the service (right-click the service, or **+ New >
   Volume**) with the mount path `/data`.
4. In the service's **Variables**, add:

   | Variable | Value |
   | --- | --- |
   | `APP_DEMO` | `true` (adds the sample members) |
   | `APP_URL` | the site's address from step 5, for example `https://wiifarm-preview.up.railway.app` |
   | `ADMIN_USERNAME` | the admin's username, for example `wiifarm_admin` |
   | `ADMIN_EMAIL` | the admin's email |
   | `ADMIN_PASSWORD` | a password of at least 12 characters (kept only in Railway) |

5. **Settings > Networking > Generate Domain** (port `8080`). That address
   is the link to the site. Put it in `APP_URL` and let it redeploy.
6. Open the address. The first start takes a minute or two.

## Signing in

- **Admin:** `https://<the address>/admin/login`, with `ADMIN_USERNAME`
  and `ADMIN_PASSWORD`.
- **Members:** `https://<the address>/login`, for example `adaeze_n`
  (Mega, top of a team) or `chika_o`, password `password123`. All ten:
  adaeze_n, bayo_ade, chika_o, dapo_k, emeka_u, funmi_a, gbenga_t,
  halima_b, ikenna_m, jumoke_s.
- **Public shop:** `https://<the address>/products`.

## Optional

- **Paystack:** to try paying online, add Paystack's **test** secret key as
  `PAYSTACK_SECRET_KEY` (from the Paystack dashboard, test mode). Without
  it, online payment shows an error; e-pins and Add Distributor still work.
- **USDT:** to try USDT deposits, set `USDT_TRON_NETWORK=nile` (Tron's
  test network) and a free TronGrid key as `TRONGRID_API_KEY`, then enter a
  Nile address and a rate on **Admin > Deposits > USDT deposits**.
- **Email:** without mail settings, emails are written to the server's log
  (Railway's **Deploy Logs**); members still see everything on their
  dashboard. The ZeptoMail settings are in the main README.
- **Starting over:** delete the volume's contents (or the volume) and
  redeploy to get a fresh site with only the sample data.

## Before the live site

This preview uses SQLite and PHP's built-in server, which is fine for
testing with a few people. The live site should use a proper database
(MySQL or PostgreSQL) and web server; that is set up before go-live.
