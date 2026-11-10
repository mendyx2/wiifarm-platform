# Notes: the Wiifarm Platform rebuild, step by step

Each piece of work is one numbered step, newest last, explained in plain
language. (The old system's own log, steps 1 to 129, is `docs/frontend-notes.md`
in `mendyx2/wiifarmvest`.)

## Step 1: The repository and its documents

**What:** started the new repository with the documents that carry the
project's context. There is no application code yet.

- `README.md`: what this repository is and where to start.
- `CLAUDE.md`: the context and rules for anyone (person or AI) working here.
- `docs/specification.md`: phase 1 of the build plan, exported from the
  online specification as it stood on 10 November 2026.
- `docs/build-plan.md`: the clean-room rules and the seven phases. Its two
  diagrams appear only in the online version.
- `docs/frontend-file-list.md`: the 286 front-end files of the old system,
  sorted into reuse (150), restyle (82) and rebuild (54).
- `docs/provenance.md`: the empty log for files brought over later.

**Reference point:** the old system's state when the rebuild began is commit
`a6314c6` on `mendyx2/wiifarmvest` `main` (the merge of its pull request #4).

**Next:** phase 2, the foundation: a fresh project skeleton, Wiifarm's design
system, accounts, admin roles and permissions, the audit log, and the ledger.

## Step 2: The project skeleton (phase 2a)

**What:** the empty application the platform is built on.

- **Starting point:** Laravel's official React starter kit (`laravel/react-starter-kit`,
  MIT licence): Laravel 12, Inertia 2, React 19, TypeScript, Tailwind CSS 4 and
  accessible UI parts (Radix). It brings sign-in, registration, password reset,
  email verification and profile/password settings, with 26 tests. Nothing in it
  comes from the old system or the licensed script.
- **Changes to the kit:**
    - the welcome page is replaced by a plain Wiifarm page (the real public site
      comes later, specification section 12);
    - three form types fixed so TypeScript passes with the current Inertia version;
    - a `typecheck` script added (`npm run typecheck`);
    - the app name set to Wiifarm;
    - the kit's PHP files tidied with Pint (formatting only).
- **Dependency register:** `docs/dependencies.md` lists all 52 direct packages with
  their licences. All are MIT, Apache-2.0 or ISC.
- **Checks:** 26 tests pass; `tsc`, ESLint, Pint and the production build are clean.
  The GitHub checks that came with the kit (tests and lint) run on every pull request
  into `main`.

**Next:** Wiifarm's design system (phase 2b).

## Step 3: Wiifarm's design system (phase 2b)

**What:** Wiifarm's own look, set in one place so every screen shares it.

- **Colours** (`resources/css/app.css`): a primary green, a harvest-gold accent,
  warm neutral greys, and named colours for status: success, warning, info and
  danger, each with a soft background. Light and dark versions of everything.
- **Type:** the free Inter typeface, from Bunny Fonts.
- **Logo:** a sprout mark drawn for this project, with the name "Wiifarm
  Cooperative" (`app-logo.tsx`, `app-logo-icon.tsx`).
- **Building blocks:**
    - status badges: paid, pending, rejected, under review and package
      (`ui/badge.tsx`);
    - coloured messages (`ui/alert.tsx`);
    - `wf/stat-card.tsx`: one headline figure with its label;
    - `wf/data-table.tsx`: plain tables with numbers aligned right, scrolling
      sideways on phones;
    - `wf/empty-state.tsx`: what an empty list shows;
    - `lib/money.ts`: shows kobo as naira, and PV.
- **Design reference page** (`/design`): every building block on one page for
  reviewers. It exists only on a developer's machine; on the live site the
  address does not exist (checked).
- **Removed:** the kit's links to Laravel's own website from the sidebar and
  header.
- **Checks:** 27 tests pass (one new, for the design page). TypeScript, ESLint,
  Prettier and the build are clean. Checked in a browser in light mode, dark
  mode and at phone width (no sideways scrolling).

**Next:** member accounts (phase 2c).

## Step 4: Member accounts, part 1 (phase 2c)

**What:** the member record and sign-up, sign-in and profile rules from
specification section 2.

- **The member record** (`users` table, `App\Models\User`):
    - username, first and last name, email, dialling code and mobile, country,
      state, city, address;
    - the sponsor;
    - status (active or banned, with a reason);
    - a separate block on withdrawals (with a reason);
    - `activated_at`, set when the package is paid. Until then the account is
      pending.

  Sponsor, status, bans and activation can never be set from a form.
- **The company account** (`CompanyAccountSeeder`): member #1, username
  `wiifarm` (a setting), with no sponsor, activated. It is created once, by
  `php artisan db:seed`. Its password is random; set a real one with "forgot
  password" before using it.
- **Sign-up:**
    - the specification's fields, with clear messages;
    - usernames are 6 to 40 letters, digits or underscores, and unique;
    - the mobile is digits only, and a local leading zero is dropped
      (0801... with +234 is stored as 801...);
    - the terms must be ticked;
    - the sponsor is optional. Blank means the company account; an unknown or
      banned sponsor is refused. A referral link (`/register?ref=username`)
      fills it in.

  Placement in the network and the package come later (phases 3 and 4).
- **Sign-in:** by username or email. A banned member cannot sign in and is told
  why. A member banned while signed in is signed out on their next page
  (`SignOutBannedMembers`).
- **Profile settings:** first and last name, email, mobile, state, city,
  address. The username cannot be changed. Changing the email or mobile
  clears its verification.
- **Removed:** the kit's "delete my account" button and route. A cooperative
  that holds members' money and ledger records should not let accounts vanish,
  and the specification has no such feature. Closing an account can become an
  admin action if the board wants one.
- **Sponsor changes** (`App\Services\Members\ChangeSponsor`):
    - every change is logged in `sponsor_changes` with the old sponsor, the new
      sponsor, the admin and a reason;
    - refused: no reason, a member sponsoring themselves, the same sponsor, a
      banned sponsor, the company account, and any change that would make a
      loop (the new sponsor sitting in the member's own downline).

  The admin screen for it comes with phase 2d.
- **Tests:** 46 pass (19 new or rewritten), including every refusal above.
  Checked in a browser: a real sign-up with mistakes first, then success,
  landing on the dashboard as a pending account under the company account.

**Still to come in accounts (part 2):** KYC (the form, private uploads, admin
review), two-factor sign-in, and the email and phone verification switches.

## Step 5: Member accounts, part 2 (phase 2c)

**What:** the rest of specification section 2: the verification switches,
KYC and two-factor sign-in, plus a store for admin settings.

- **Admin settings** (`settings` table, `App\Support\Settings`):
    - every setting an admin may change, with its default in
      `config/wiifarm.php`;
    - a setting nobody has changed uses its default, so every setting has a
      known value from day one;
    - the screen to change them comes with the admin panel (phase 2d).
- **Email and phone verification switches** (both off by default):
    - when a switch is on, a member who has not confirmed is sent to confirm
      before using the member area (`RequireVerification`). Profile settings
      stay open, so a wrong email or number can be corrected;
    - the email confirmation link is sent at sign-up when the switch is on;
    - phone codes are 6 digits, sent by SMS, and only their hash is stored;
    - a code lasts 10 minutes, and 5 wrong tries void it. A changed number
      voids the code, and changing the number clears its verification;
    - SMS sending sits behind one interface (`SmsSender`). Until Wiifarm picks
      a provider, messages are written to the log.
- **KYC:**
    - the form's fields are a setting (`kyc.fields`). By default: NIN
      (11 digits), ID document type, a photo of the ID, and a photo of the
      member's face;
    - files (JPG, PNG or PDF, up to 5 MB) are stored outside the public
      folder and only shown to their owner (admins get access in phase 2d);
    - states are unverified, pending and verified;
    - the review service (`App\Services\Members\Kyc`): approve, or reject
      with a reason. After a rejection the member sees the reason and submits
      again, and earlier submissions stay as a record;
    - each submission keeps a copy of the form as it stood, so a later change
      to the form cannot change its meaning;
    - the "already under review" check is made on a locked database row, so
      two submissions at the same moment cannot both pass; a refused
      submission leaves no files behind.
- **Two-factor sign-in** (optional per member):
    - works with any authenticator app (Google Authenticator, Microsoft
      Authenticator, Authy);
    - to turn it on: type the password, scan the QR code (or type the key),
      then type one code from the app;
    - eight backup codes are shown once. Each works one time only, and new
      ones can be made (the old ones then stop working);
    - with it on, a correct password alone does not sign in: the next step
      asks for a code from the app, or a backup code;
    - the secret is stored encrypted; the backup codes are encrypted and
      hashed. Neither is ever sent to the browser except the setup key, once;
    - turning it on or off and making new codes each need the password;
    - every step is rate-limited.
- **New libraries:** `pragmarx/google2fa` (MIT) and `bacon/bacon-qr-code`
  (BSD-2-Clause), both added to `docs/dependencies.md`.
- **Tests:** 66 pass (20 new).
- **Checked in a browser:**
    - a KYC submission with real files, shown as "under review", with the
      member's own files opening;
    - turning two-factor on (with a code computed the way an authenticator
      app does), the backup codes shown, then signing out and back in through
      the code step.

**Next:** admins, roles and permissions, and the audit log (phase 2d). The
admin screens for KYC review, sponsor changes and settings come with it.
