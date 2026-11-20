# Wiifarm Platform

The new Wiifarm Cooperative platform, built from scratch on code Wiifarm owns.

It replaces the current system (`mendyx2/wiifarmvest`), which is built on a
licensed commercial script. This repository contains **no code from that
script**: everything here is written new from the specification, on free,
open-source tools.

## Where to start

| Document | What it is |
| --- | --- |
| [`docs/specification.md`](docs/specification.md) | **What the platform must do.** Members, the binary tree, packages, every bonus, wallets, pools, shop, stockists, savings and loans, admin, schedules. The source of truth for every feature. Section 13 lists the decisions the board still has to make. |
| [`docs/build-plan.md`](docs/build-plan.md) | **How it is built:** the clean-room rules, the modules, the data design, the seven phases and the gate at the end of each. The two diagrams are in the [online version](https://claude.ai/artifact/5JTPHtPX2RQxvh8ped5bLc). |
| [`docs/frontend-file-list.md`](docs/frontend-file-list.md) | **Which front-end files from the old system may come across**, and how: reuse, restyle or rebuild. |
| [`docs/dependencies.md`](docs/dependencies.md) | **Every outside package and its licence.** |
| [`docs/provenance.md`](docs/provenance.md) | **Where things came from.** Every file brought over from the old system is recorded here, with who reviewed it. |

## Ground rules (short version; the full rules are in the build plan)

1. Build from the specification, never from the old code.
2. The old system is a black box: watch what it does, compare totals, never copy.
3. No part of the licensed script: no licence package, theme, templates, images, wording or table designs.
4. Only open-source building blocks that allow commercial use (MIT, BSD, Apache).
5. A file from the old system comes across only after review, and only if
   `docs/frontend-file-list.md` allows it. Each one is logged in `docs/provenance.md`.

## Status

- Phase 1, the specification: **done**, waiting for the board's decisions (specification section 13).
- Phase 2, the foundation: **done**. The project skeleton, the design system, member accounts, the admin panel (sign-in, roles, audit log, members, KYC review, settings, admins and roles) and the ledger with wallets.
- Phase 3, the binary network and PV: **done**. Packages, placement in the tree, PV climbing on the right legs, joining and upgrades (once per payment), the team tree and PV log for members, and the admin's network views.
- Phase 4, compensation: **in progress**. Done: the pool ledgers (Allocation Blueprint V3), the split of every payment between the manufacturing company and the cooperative, contingency funds, held payouts, products, orders and pickup codes, stockists (stores, stock, handover by code, rebates), the direct, indirect, key-in and upgrade bonuses, affiliate accounts, withdrawals and bonus transfer, the daily matching bonus, and online payment by Paystack (packages, upgrades, deposits), e-pins, Add Distributor from the VISA wallet, the public shop, the member dashboard, the monthly leadership bonus, and the monthly unilevel bonus (15 generations), and the awards (recognition ranks, UCB, repurchase awards). Next: the remaining changes from the Revised Baseline Rules, once the owner answers its questions.

## Running it

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm run build      # or: npm run dev
php artisan wiifarm:create-admin   # the first super-admin (asks for the details)
php artisan wiifarm:demo-network   # optional: ten sample members (password123); never on the live site
php artisan serve
php artisan test   # tests
npm run typecheck  # TypeScript
```

## Going live: email (Zoho ZeptoMail)

Wiifarm sends member emails through Zoho ZeptoMail (owner, 20 November
2026). Bonuses are never emailed; they show on the member's dashboard.

1. Sign up at zoho.com/zeptomail and add Wiifarm's domain. ZeptoMail shows
   a few DNS records (SPF, DKIM); add them where the domain is managed and
   wait until ZeptoMail marks the domain verified.
2. Create a Mail Agent, then open **Setup Info > SMTP** and copy the
   **Send Mail token**.
3. In the live server's settings (its `.env`, or the hosting panel's
   variables), set:

   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.zeptomail.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=emailapikey
   MAIL_PASSWORD=<the Send Mail token>
   MAIL_FROM_ADDRESS=<for example noreply@ the verified domain>
   MAIL_FROM_NAME="Wiifarm Cooperative"
   ```

   If ZeptoMail's SMTP page shows a different host for your region (for
   example `smtp.zeptomail.eu`), use that one.
4. Make sure the server runs the scheduler every minute
   (`* * * * * php artisan schedule:run`). It also sends the emails waiting
   in the background; no other program is needed.
5. Check: sign up a test member, then look at **Admin > Messages**. The
   welcome message should show **Sent**. A **Failed** one means the token or
   the address is wrong.

## Reference

The old system's state when this rebuild began is commit
[`a6314c6`](https://github.com/mendyx2/wiifarmvest/commit/a6314c6) on its `main` branch.
