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

## Step 6: The admin panel, part 1: sign-in, roles and the audit log (phase 2d)

**What this is:** the base that every admin screen will stand on. Admins are
the staff who run Wiifarm. This step decides who they are, how they sign in,
what each one is allowed to do, and how every change they make is recorded.

- **Admins are separate from members:**
    - their own accounts, their own sign-in page at `/admin/login`, and their
      own session. A member's password never opens the admin panel, and an
      admin is never signed in as a member;
    - admins sign in with a username and password. Five wrong passwords lock
      that username, from that computer, for a minute;
    - each sign-in is recorded (time, and the audit log below).
- **There is no default admin and no default password.** The first
  super-admin is made on the server with `php artisan wiifarm:create-admin`,
  which asks for the name, username, email and a password of at least 12
  characters. Nobody can sign in with a guessed starting password.
- **Roles and permissions** (specification section 11):
    - every admin action has a named permission, such as "Ban and unban
      members" or "Approve or reject KYC submissions". The full list, in plain
      English, is in `config/admin_permissions.php`; later parts add theirs;
    - a role is a set of permissions, and an admin can have more than one
      role;
    - the super-admin role has every permission. Three are super-admin only
      and cannot be put in any other role: managing admins, managing roles,
      and changing system settings. The database refuses any attempt;
    - every admin page and action checks the permission on the server. The
      menu also hides what an admin may not use, but the server check is the
      real lock.
- **Deactivating an admin:** a deactivated admin cannot sign in, and one who
  is already signed in is signed out on their next click.
- **The audit log:**
    - every admin action that changes data records who did it, what they did,
      which record, the values before and after, the time, the IP address and
      the browser;
    - entries can only be added. The software refuses to change or delete
      one, so the log cannot be edited from the panel or by any code in it.
- **The admin dashboard:** for now, members, paid members, banned members and
  KYC waiting for review. Money, orders, stockists, pins and the pools join as
  those parts are built.
- **Tests:** 84 pass (18 new), including: a member cannot use the admin
  panel; a deactivated admin is signed out; a support role cannot be given
  super-admin powers; the audit log cannot be changed or deleted.
- **Checked in a browser:** a wrong password refused, signing in, the
  dashboard, the member area still closed to the admin, and signing out.

**Still to come for admins** (specification section 11): two-factor sign-in
and a password reset by email code for admins.

**Next:** the admin screens for members (search, details, ban, block
withdrawals, change sponsor), KYC review and the verification settings.

## Step 7: The admin panel, part 2: members, KYC review and settings (phase 2d)

**What this is:** the first screens admins use every day. Each screen and
each button checks the admin's role first; an admin without the permission
does not see the button, and the server refuses the action anyway.

- **Members list:**
    - search by username, name ("Amaka Okafor" works), email or phone
      number (with or without the leading 0);
    - filter by active or banned, KYC state, and paid or free. Each row shows
      the member's state at a glance;
    - 25 per page. ("With a balance" joins when wallets are built.)
- **A member's page:**
    - their details, with whether the email and phone are confirmed, their
      sponsor, how many members they referred, whether the package is paid,
      two-factor, and their latest KYC submission;
    - **ban / lift a ban:** needs a reason. The member is signed out at once
      and sees the reason when they try to sign in. The company account
      cannot be banned;
    - **block / open withdrawals:** needs a reason, which the member will see
      when they try to withdraw (withdrawals come with the ledger);
    - **change the sponsor:** type the new sponsor's username and a reason.
      The rules from Step 4 apply (no loops, no banned sponsor, and so on).
      The people this member referred stay with them;
    - **edit details:** name, email, phone, country and address, with a
      reason. The username never changes. A new email or phone number has to
      be confirmed again by the member. Only the fields that changed are
      recorded;
    - the page lists every sponsor change and every admin action on this
      member: when, what, by whom, and why.
- **KYC review:**
    - the waiting list shows the oldest first, so members are served in turn;
      approved, rejected and all can be viewed too;
    - a submission shows the answers and the photos, which open in full. The
      files are only reachable by signed-in admins with the KYC permission;
    - **approve** makes the member verified. **Reject** needs a reason, which
      the member sees, and they can submit again. Earlier submissions are
      listed beside it;
    - a submission can be decided once only; a second decision is refused.
- **Settings:** the two verification switches (email and phone confirmation).
  Admins with "See the settings" can look; only a super-admin can change
  them. Each change is in the audit log with the old and new value.
- **Every action above is written to the audit log** in the same step as the
  change itself, so a change can never happen without its log entry.
- **Tests:** 102 pass (18 new), including: an admin who may only view cannot
  ban, edit or change a sponsor; a banned member is signed out on their next
  click; a KYC file cannot be opened by a member through the admin address.
- **Checked in a browser:** search, a member's page, blocking and opening
  withdrawals (both shown in the member's history), the KYC waiting list and
  a submission with its photos, and the settings page.

**For the board (added to the open decisions):** when a member's sponsor is
changed, should anything else follow? For example, should their place in the
binary tree move, and should bonuses already paid stay as they are? Today it
changes the sponsor only, for the future. This matters once bonuses exist
(phase 3).

**Still to come later:** a member's wallets and tree, adding or subtracting
from a wallet, signing in as the member to help them, and sending a member a
notification. Each needs a part that is not built yet.

**Next:** managing admins and roles, and a screen to read the audit log
(phase 2d, part 3).

## Step 8: The admin panel, part 3: admins, roles and the audit log (phase 2d)

**What this is:** the screens a super-admin uses to decide who can run the
platform and what each person may do, and the screen for reading the audit
log. Only super-admins see Admins and Roles.

- **Admins:**
    - the list shows each admin, their roles, whether they are active, and
      when they last signed in;
    - **add an admin:** name, username (permanent), email, a password of at
      least 12 characters, and one or more roles. The password is never
      written to the audit log, and the screen reminds you to hand it over in
      person, not by email;
    - **change an admin:** name, email, roles, or a new password;
    - **deactivate or reactivate** with a reason. A deactivated admin is
      signed out on their next click. Admins are never deleted, so the log
      always knows who did what.
- **Two safety rules:**
    - there is always at least one active super-admin. The last one cannot be
      deactivated or lose the super-admin role, so the panel can never be
      locked with nobody able to manage it. (A deactivated super-admin does
      not count.)
    - nobody can deactivate their own account.
- **Roles:**
    - create a role, name it, say what it is for, and tick its permissions,
      grouped as Members, KYC, Settings, Audit and Admins;
    - the super-admin-only permissions are shown but cannot be ticked;
    - the super-admin role itself always has everything and cannot be
      changed or deleted;
    - a role can be deleted only when no admin has it;
    - a changed role takes effect from each admin's next click.
- **The audit log screen:**
    - every entry: when, which admin, what they did, which record (with a
      link to it, if the viewer may open it), what changed (old value struck
      through, new value in green), the reason, and the IP address;
    - filter by admin, action, kind of record, record number and dates;
    - read only. There is no button or address that changes or deletes an
      entry; a test checks that.
- **A fault found and fixed while checking in the browser:** the page for
  editing an admin hid the signed-in admin's own details, because both were
  called "admin" behind the scenes. They now have different names, and a test
  guards it.
- **Also tidied:** "before" values in the log now read "yes/no" instead of
  "1/0".
- **Tests:** 118 pass (16 new), including: an admin with every ordinary
  permission still cannot manage admins or roles; the last super-admin is
  protected; a role cannot be given super-admin-only permissions.
- **Checked in a browser:** creating a "Support desk" role, adding an admin
  with it, the audit log showing both, and that admin signing in to see only
  Members, KYC review and Audit log (Admins is refused).

**Phase 2d is complete** apart from two items the specification asks for
that are best done together later: two-factor sign-in for admins and an
admin password reset by email code (both need email sending, which comes
with message templates).

**Next:** the ledger (phase 2e): wallets and entries, where every naira and
PV point is recorded and nothing is ever paid twice.

## Step 9: The ledger and wallets (phase 2e)

**What this is:** the record of every naira in the platform. Everything that
pays or charges a member later (deposits, withdrawals, transfers, bonuses,
shop orders, pools) will go through it, so it is built first and built
strictly.

- **How it works, simply:**
    - money is never typed into a balance. Each movement is a
      **transaction**, made of **entries**: money out of one account and the
      same money into another. The entries of a transaction always add up to
      zero, so money can only move, never appear or vanish;
    - a wallet's balance is the sum of its entries, worked out each time it
      is shown. There is no balance number anywhere that someone could edit;
    - entries and transactions are written once and can never be changed or
      deleted. A mistake is put right with a new, opposite transaction, which
      leaves a visible trail.
- **"Once only":** every transaction carries a key naming what caused it
  (for example "registration 123, direct bonus"). If the same cause is
  posted again, for instance because a job ran twice, nothing new is
  written. If the same cause arrives with a different amount, it is refused
  as a fault.
- **All or nothing:** a payout touching several members either completes for
  all of them or for none.
- **A member's wallet never goes below zero.** The platform's own accounts
  may: a pool line below zero is an overspend, and the specification says it
  must be shown, never hidden.
- **The accounts** (from specifications 6 and 7):
    - each member: Main balance, VISA, Product, Autoship and Savings wallets,
      and one wallet for each of the 13 bonuses. A wallet opens the first
      time it is used;
    - the platform: money coming in and going out, admin corrections, and
      every budget line of the revenue split (company 30%, leadership 10%,
      the four operations lines, and the 14 bonus lines of the Consumer
      Rewards Pool, Commerce Pool and Strategic Reserve).
- **Money arithmetic** is done in whole kobo, never with decimals that can
  drift. A tested helper splits an amount by percentages so that the parts
  add up to the amount exactly; it reproduces the specification's own
  example (a ₦15,000 pack: company ₦4,500, leadership ₦1,500, operations
  ₦750, members ₦8,250). It will be used when revenue starts flowing.
- **A nightly check** (`wiifarm:ledger-check`, 2:15 every morning) confirms
  that every transaction adds up to zero, the whole ledger adds up to zero,
  and no member wallet is below zero. Any finding is logged for the team.
  The test suite proves it catches a figure written behind the ledger's back.
- **Screens:**
    - **members:** a new Wallet page with every balance and a statement for
      each wallet (date, what it was, amount in or out, and the balance
      after). Bonus wallets appear once they hold something;
    - **admins:** a member's page now shows their wallets, each opening its
      full statement;
    - **add to or subtract from a wallet:** choose the wallet, the amount
      and a reason (the member sees it on their statement). It asks to
      confirm, cannot take a wallet below zero, and is written to both the
      ledger and the audit log. A double click or a resent form counts once.
      It needs a new permission: "Add to or subtract from a member's wallet".
- **Tests:** 145 pass (27 new), covering the rules above: double entry,
  "once only", nothing below zero, all or nothing, nothing editable, and the
  nightly check.
- **Checked in a browser:** an admin added ₦25,000 to a member's wallet, was
  refused when subtracting ₦30,000, then subtracted ₦5,000; the statement
  showed both with the right balances. A member's Wallet page showed their
  main balance, a bonus wallet and the statement.

**For the board (added to the open decisions, number 15):** who may add to
or subtract from a member's wallet? Today any role can be given that
permission. It could be limited to super-admins, or need a second admin's
approval above an amount.

**Not in this step (they use the ledger, and come in phase 4):** deposits,
withdrawals, transfers between members, bonus transfers and e-pins. PV
accounts for the binary tree come in phase 3.

**For the team:** the live site should run on MySQL or PostgreSQL, not the
SQLite file used for development, so that the wallet locks hold when many
members act at the same moment.

**Phase 2 (the foundation) is complete.** Next: phase 3, the binary network
and PV.

## Step 10: The binary network, part 1: packages, placement and PV (phase 3)

**What this is:** the engine of the network. Nothing here pays a bonus yet
(that is the next phase); it decides where each member sits and makes sure
every point of volume (PV) reaches the right people.

- **The seven packages** (specification section 4) are in the database with
  their price, PV, product packs, direct bonus share, pay per match, daily
  cap and unilevel depth, exactly as in the table. A package can be marked
  "no upgrades" or taken off offer. Prices follow the table (₦1,000 per PV);
  bonuses will be worked out on ₦600 per PV.
- **Joining:** a person becomes a member when their package is paid. In one
  step, all or nothing, they are placed in the tree, given the package, and
  the package's PV climbs the tree. A payment can activate once only: the
  same payment again changes nothing, and a second payment for an
  already-paid member is refused. (The payments themselves, Paystack and
  e-pins, come in the payments phase; they will call this step.)
- **Placement** (specification section 3):
    - the company account is the top of the tree;
    - with no choice, the member goes in the first free place below their
      sponsor, level by level, left before right;
    - a member may ask for a parent and a leg. The parent must be inside
      the sponsor's own tree, or the placement is refused. If the leg asked
      for is taken, the member goes to the next free place down that same
      leg;
    - a place is claimed in one step that only succeeds if it is still
      free; the database itself refuses a second member in the same place.
      A member who loses that race is placed again by the same rules (a
      test forces the race to prove it);
    - placement never fails silently: if it cannot be done, nothing happens,
      nothing is paid, and the reason is given;
    - a place, once given, can never be changed.
- **PV climbing:**
    - when a member gains PV, every upline up to the top receives it on the
      leg the member sits under, twice: as **lifetime PV** (for awards, only
      ever grows) and as **pairing PV** (for matching, used up by matches,
      the rest carried forward);
    - PV from a stockist store counts for awards only, so it is added to
      lifetime PV and never to pairing PV;
    - PV is kept in the same ledger as money, in its own unit, so it follows
      the same rules: every credit is recorded with the member it came from,
      the leg, the amount and the source; the same source can never climb
      twice; and the nightly check proves the PV books balance too;
    - the old system could disagree with itself about which leg a member was
      on. Here a member's leg is stored in one place only, so that cannot
      happen. A loop in the tree is impossible for the same reason, but the
      climb still checks for one, stops, and records it for an admin.
- **Upgrades:** a member can move to a higher package by paying the
  difference in price. The PV difference climbs the tree and the new
  package applies from then on. No downgrades; no upgrade from a package
  marked "no upgrades". One payment upgrades once only.
- **A sample network** for trying the screens: `php artisan
  wiifarm:demo-network` adds ten members with different packages under
  different sponsors. It refuses to run on the live site.
- **Tests:** 163 pass (18 new): placement level by level and left first,
  a taken leg, a parent outside the sponsor's tree, the race for a slot, PV
  on the right legs all the way up, store PV for awards only, once-only
  joining and upgrades, and no downgrades.

**For the board (added to the open decisions, number 16):** what happens
when someone signs up with the referral of a person who has not paid yet,
and so has no place in the tree? Today the new member's placement is
refused with a clear message, and nothing is paid.

**Next:** the network screens: the member's team tree and PV log, and the
admin's view of a member's place and PV.

## Step 11: The binary network, part 2: the screens (phase 3)

**What this is:** the pages where members and admins see the network built in
Step 10.

- **For members, "My team":**
    - the PV on each leg: what is waiting to be matched, and the lifetime
      total;
    - the tree below them, four levels at a time: each member's username,
      name, package, and the PV waiting on their left and right legs. Free
      places are shown. Clicking a member shows the team below them, with
      "One level up" and "Back to me";
    - a member can only look down their own team, never above themselves or
      into someone else's (the server refuses);
    - a member whose package is not paid yet is told they will get their place
      as soon as it is;
    - **PV log:** every PV credit, newest first: who it came from, why
      (joined, upgraded, product purchase, stockist store), which leg, and how
      much pairing and lifetime PV it added.
- **For admins:**
    - a member's page has a new **Network** card: their package, where they
      sit (parent, leg, how many levels below the top), their PV on both
      legs, and links to their tree, their PV log and their parent;
    - the tree and PV log of any member, moving up or down freely;
    - a **Packages** page: the seven packages with price, PV, PV value, packs,
      direct bonus, pay per match, daily cap, unilevel depth and how many
      members hold each. For now it can be read, not changed; changing a
      package is a plan setting and will be logged like every setting.
- **At sign-up:** a new "Choose your place in the team (optional)" section:
  a parent's username and a side. The parent must be in the sponsor's team,
  and the side must be free at that moment; otherwise the form says why. If
  the side is taken by the time the package is paid, the member goes down that
  same side, as the specification says.
- **Tests:** 171 pass (8 new), including that a member cannot see above
  themselves or into another team.
- **Checked in a browser** with the sample network: a Mega member's team
  showed left 120 PV and right 615 PV, which match the packages below her
  (60 + 30 + 30, and 105 + 60 + 450). A member who asked for a taken left
  side was placed further down the left. The admin tree of the company showed
  1,185 PV left and 420 PV right, and the packages page matched the plan's
  table. Looking above oneself was refused.
- **Fixed while checking:** on the admin tree, cards in the fourth row
  overlapped on a laptop screen; they now shrink to fit.

**Phase 3 is complete.** Next: the bonuses (direct, indirect, key-in,
upgrade, matching with daily caps and carry-forward, and the rest of
specification section 5), paid through the ledger, each once only.

## Step 12: Checking the board's Allocation Blueprint V2 (no code)

The board sent its final decisions on bonuses and allocations: the
Allocation Blueprint V2 and its Baseline Rules. Before building anything,
they were checked against the arithmetic and simulated on growing networks.
The full review, the questions for the board and the build plan are in
`docs/blueprint-v2-review.md`.

In short: the registration and upgrade bonuses, the key-in bonus and the
stockist activation bonus now fit their pools exactly. Binary matching,
unilevel, rank awards and repurchase awards would pay more than their pools
take in as the network grows, so the board needs to say what happens when a
pool is short. Fourteen questions are listed, and specification sections 5
and 7 will be updated to match V2 once they are answered.

## Step 13: The board's Allocation Blueprint V3 adopted (no code)

The board replaced the V2 blueprint with **Allocation Blueprint V3**, a
workbook now kept in `docs/board/`. A board meeting also settled how goods and
stockists work. Both are now in the specification:

- **Section 7 (pools)** follows V3:
    - ₦750 of every unit to the manufacturers, ₦750 to the cooperative;
    - separate ledgers for registration, repurchase, upgrade and stockist
      activation, each with its own lines and safety net;
    - the Strategic Reserve, Executive Leaders, Contingency Reserve and
      Sub-leaders pools.
- **Sections 8 and 9 (shop and stockists)** follow the meeting:
    - everyone pays the company, never a stockist;
    - goods move from the manufacturers to the Mega store, then the Sub
      store, then the buyer, with the company allocating;
    - pickup codes work at any stockist, never expire and release the whole
      order at once;
    - stockists have their own wallet and earn ₦120 (Mega) or ₦90 (Sub) per
      unit handed over;
    - the old 6% / 8% rebates, the 3% stockist-sponsor bonus, cash on pickup
      and the stockists' own till are removed.
- **Section 5:**
    - the leadership bonus qualifies at 480,000 PV on the repurchase PV log;
    - matching and leadership are funded from the registration ledger.
- **Open decisions:**
    - 1 and 2 are settled by V3;
    - eight new ones (17 to 24) are added. The most important are the 1%
      Strategic Reserve (rank awards now get ₦5.25 a unit instead of ₦52.50)
      and the rule for a line that runs short.

The check is in `docs/blueprint-v3-review.md`. In short, the registration
ledger now adds up to exactly 100%, and every fixed-formula bonus fits its
line to the kobo. Rank awards, and in some network shapes matching and
unilevel, can still cost more than their lines.

## Step 14: How a public shop sale is shared (no code)

The board set the split of a public sale. A public unit costs ₦2,000:

- ₦750 goes to the manufacturers;
- ₦750 goes to the cooperative pool;
- of the remaining ₦500, 20% (₦100) goes to the member whose link brought the
  buyer (the affiliate), and ₦400 is reserved in the manufacturers' account.

Specification section 8 now shows this. Decision 23 keeps four smaller
questions:

- which ledger takes the cooperative's ₦750;
- whether the stockist's rebate on a public pickup comes from it;
- where the affiliate's ₦100 goes when nobody referred the buyer;
- whether a public sale earns PV.

## Step 15: More board decisions: public sales, contingency, 10 generations

From the board, written into the specification:

- **Public shop sales stay outside the cooperative.** They are there to bring
  the products to the public. Of a ₦2,000 unit:
    - 20% of the ₦500 margin (₦100) goes to the affiliate, when one sent the
      buyer;
    - the usual ₦120 / ₦90 goes to the stockist who hands it over;
    - the rest goes to the manufacturers.

  A public sale earns no PV and pays no network bonus.
- **Affiliates get their own wallet,** as stockists do.
- **The stockist sponsor bonus is the Pool D bonus** to 10 sponsor
  generations; the old 3% of store sales is gone.
- **10 generations, never 15.**
    - Unilevel is now 10% · 7% · 5% · 4% · 4% · 3% · 3% · 3% · 2% · 2% (43%),
      and every package from Booster up reaches all 10.
    - The packages already in the database were corrected (a small database
      change). A test checks no package goes beyond 10.
- **Contingency fund.** Every reserve and leftover is placed in a contingency
  fund, kept per ledger and fully tracked. When a line runs short, it is
  covered from its own ledger's contingency fund. No ledger ever pays for
  another.

Still open: rank awards (₦5.25 a unit), what happens if a ledger's contingency
fund is also empty, whether unpaid shares go to the contingency fund or the
company, the affiliate's 20% base, and the Global Share Pool.

Tests: 171 pass.

## Step 16: The board's answers on pools, shortfalls and the two companies

- **The 1% Strategic Reserve is confirmed.** Rank awards receive ₦5.25 a
  unit. An award the pool cannot cover yet waits until it can; at that rate
  awards will often wait.
- **Shortfalls are held until the money arrives.** A payout that its line and
  its ledger's contingency fund cannot cover shows as pending. It is paid in
  full once money comes in, oldest first, and never cancelled.
- **Unpaid shares stay with the cooperative.** For example, a missing
  generation or a banned upline: that share goes into the ledger's contingency
  fund.
- **Two separate companies.** The manufacturing company and the cooperative
  have different accounts. The specification now names them apart
  everywhere. Each payment is split between them at once, and their books
  never mix.
- **Affiliates:** 20% of the ₦500 margin (₦100), and anyone can register as
  one, member or not.
- **No Global Share Pool.** It is removed from the specification, with its
  quarterly payout.
- **Store PV counts both ways:** 10 levels up the binary tree and 10
  generations up the referral chain, for awards only.

Open: decision 25, whether each company has its own Paystack and bank
account, and which company allocates stock.

The pool accounts in the code still follow the old allocation, including a
Global Share line. They are replaced in the next build step.

## Step 17: Manufacturing partners and stock allocation

- **One manufacturing partner for now, with more products later.** Each
  product will name its manufacturer, so if new partners join with their own
  products, each product's ₦750 a unit goes to the right partner's account.
- **The manufacturing company allocates stock to the stores.** The Mega store
  receives from the manufacturing company, and a Sub store collects from its
  Mega.

Still open (decision 25): whether each company has its own Paystack and bank
account, or one account collects and passes the other its share.

## Step 18: The pool ledgers (phase 4, part 1)

**What this is:** the money side of the board's Allocation Blueprint V3, built.
Every payment can now be split, to the kobo, between the manufacturing
company and the cooperative's separate pools, ready for the bonuses to be
paid from them.

- **Separate books:**
    - each manufacturing partner has its own account. There is one today, and
      more can be added;
    - each of the cooperative's pools is its own ledger, with one account per
      line and its own contingency fund: Registration (A), Strategic Reserve
      (B), Repurchase (C), Stockist activation (D), Upgrade (E) and Executive
      Leaders;
    - public-shop sales have a separate holding ledger that is not a
      cooperative pool. The affiliate's share and the stockist's rebate wait
      there until pickup.
- **The split** (one entry per payment, adding up to the payment exactly):
    - **registration:** half to the manufacturer. The cooperative's half goes:
      1% to the Strategic Reserve (then 70% awards / 30% UCB), 2.3% to the
      contingency fund, 10% to Executive Leaders, and 86.7% to the six bonus
      lines;
    - **upgrade:** half to the manufacturer; the cooperative's half goes 1%
      reserve, 10% leaders, then 52.5% to upgrade bonuses and the rest to the
      contingency fund;
    - **repurchase:** half to the manufacturer; the cooperative keeps the full
      half, split 49 / 10 / 10 / 16, with 15% to the contingency fund;
    - **public sale:** the affiliate's ₦100 a unit (only when an affiliate
      sent the buyer) and ₦120 a unit for the stockist are held until pickup.
      Everything else goes to the manufacturer. No cooperative pool receives
      anything.
- **Rounding:** each part rounds down to the kobo, and the few kobo left go to
  the receiving pool's contingency fund, so nothing is lost or invented.
- **Once only:** the same payment can never be split twice.
- **Versioned rates:** the rates are the board's schedule, stored as
  "version 1". Every split records the version it used, so a future change
  applies from then on, never backwards.
- **Paying from a pool** (used by the bonuses when they are built):
    1. the bonus line pays what it holds;
    2. that pool's own contingency fund covers the rest; another pool's money
       is never used;
    3. if both together cannot cover it, the payout is **held**, in full,
       never in part. When money arrives it is paid automatically, oldest
       first; nobody jumps the queue. An hourly run also catches anything
       missed;
    4. a share with nobody to receive it moves into that pool's contingency
       fund and stays with the cooperative.
- **Pool ledgers can never go below zero:** the ledger itself refuses.
- **The admin Pools page** (new permission "See the pool ledgers,
  contingency funds and held payouts") shows:
    - the manufacturing partners' accounts;
    - every pool, line by line: what came in and went out today, this month,
      this quarter or all time, and what it holds now;
    - each contingency fund;
    - every held payout and how long it has waited.
- **Removed:** the old pool accounts from the earlier 30 / 10 / 5 / 55 split,
  including the Global Share line. The affiliate and stockist rebate wallets
  leave the member's wallets; affiliates and stockists get their own wallets
  in a later step.
- **Tests:** 187 pass (16 new). They check the split to the kobo:
    - a ₦15,000 Pre Starter registration: ₦7,500 to the manufacturer, ₦2,251.16
      in the direct line against a ₦2,250 bonus, ₦172.53 in the contingency
      fund;
    - a repurchase, an upgrade and a public sale with and without an
      affiliate.

  They also check: no double split, odd amounts still adding up, the
  contingency fund covering a shortfall, another pool's money never used,
  held payouts paid in full and in order, and the permission.
- **Checked in a browser:** the ten sample registrations (₦1,605,000) put
  ₦802,500, exactly half, with the manufacturing partner. The pools showed
  each line, and a ₦2,500 unilevel payout showed as held, because the
  repurchase pool is still empty. The nightly ledger check passed.

**Next:** orders and pickup codes, then stockists (Mega and Sub stores, their
wallets, stock allocated by the manufacturer, and rebates paid on pickup).

## Step 19: Products, orders and pickup codes (phase 4, part 2)

**What this is:** how members get their goods. Everyone pays through the
platform, never a stockist. Each paid order gets a pickup code, which the
buyer shows at a stockist to collect the goods.

- **Products:**
    - each product has a name, a code, its **manufacturer**, a member price,
      a public price, PV a unit and units in a pack;
    - the first product, "Wiifarm product" (₦1,500 / ₦2,000 a unit, 1.5 PV,
      10 a pack), is set up automatically, because every package's packs are
      made of it. It can be renamed;
    - admins add and change products on a new Products page (new permission
      "Add and change products, prices and PV"), and every change is in the
      audit log. A new price applies to orders from then on.
- **Three kinds of order:**
    - **registration packs:** when a package is paid, an order for its packs
      is created and marked paid, with its own pickup code. Pre Starter is 1
      pack (10 units); Mega is 30 packs (300 units);
    - **repurchase:** a member chooses products in the new **Shop** and pays
      from their main balance or product wallet. In one step the wallet pays,
      the money is split (half to the product's manufacturer, half to the
      repurchase pool, Pool C), the PV climbs the tree, and the member's own
      repurchase PV is recorded, which unilevel and leadership will need. If
      the wallet is short, nothing happens. A resent form buys once;
    - **public:** the order is created waiting for online payment. When the
      payment is confirmed, it is split the public way (affiliate and stockist
      shares held until pickup, the rest to the manufacturer). The online
      payment itself comes with Paystack.
- **Pickup codes:**
    - twelve letters and digits in three groups, such as `6DSA-E22Y-UYJM`.
      Look-alike characters (0 / O, 1 / I / L) are left out, so they can be
      read aloud; there are about 10¹⁸ possible codes, so they cannot be
      guessed;
    - **only the buyer sees the code**, on "My orders", with a Copy button.
      The database keeps it encrypted, plus a keyed fingerprint used to find
      the order. Admins see only its last four characters;
    - it works at any stockist, never expires, releases the whole order, and
      works once. Typing it in lower case or with spaces still works.
- **Screens:**
    - members: Shop and My orders;
    - admins: Orders (search, filter by status and kind, and each order's
      details) and Products.
- **Fixed while checking:** member pages did not show the one-line
  confirmation after an action ("Order WF0000012 is paid…"). Admin pages
  already did; both now share the same message bar.
- **Tests:** 198 pass (11 new):
    - a package's pack order;
    - codes found however they are typed, and never stored in plain text;
    - a repurchase paid, split and climbing;
    - a resent form buying once; a short wallet buying nothing;
    - an unpaid member refused;
    - a public order paid online, even if the gateway reports it twice;
    - members seeing only their own codes, and admins never seeing them;
    - buying through the Shop page;
    - products with permission and audit.
- **Checked in a browser:** the ten sample members' pack orders (10 to 300
  units), a member buying 3 units from her product wallet and getting her
  code, and the admin Orders list. The ledger check passed.

**For the board (decision 26):** when a member upgrades, do they also receive
the extra product packs of the higher package?

**Next:** stockists: Mega and Sub stores, their own wallets, stock allocated
by the manufacturer, and handing goods over by pickup code with the rebate
paid on the spot.

## Step 20: Stockists (phase 4, part 3)

**What this is:** the stores that hold the goods and hand them over. A store
never takes a buyer's money: it checks the pickup code, hands the goods over,
and is paid its rebate on the spot.

- **Stores:**
    - a stockist is a member in good standing with a paid package, and runs one
      store: **Mega** (₦10M deposit) or **Sub** (₦3M deposit). A Sub store is
      attached to the Mega store it collects from;
    - each store has its own **stockist wallet**, separate from the owner's
      member wallets;
    - admins set up a store (new permissions: see stores; set up, activate and
      deactivate them; allocate stock), and every step is in the audit log.
- **Activation,** once the deposit is received. The admin types the payment
  reference. In one step, once only:
    - **the deposit is split:** 8% (Sub) or 6.5% (Mega) to Pool D, and the
      rest to the manufacturing company for the products the store receives
      (to be confirmed, decision 27);
    - **the stockist sponsor bonus** goes to 10 generations, starting with the
      store's sponsor: ₦50,000 / ₦30,000 / ₦20,000 × 8 for a Sub store, and
      ₦150,000 / ₦100,000 / ₦50,000 × 8 for a Mega store. A share with nobody
      to receive it (the company account, a missing or banned member) stays in
      Pool D's contingency fund;
    - **the store PV** (3,000 or 10,000) goes 10 levels up the stockist's tree
      as lifetime PV, and 10 generations up their referral chain. It counts
      for awards only, never for matching.
- **Stock:**
    - stock is counted in the ledger, unit by unit, exactly like money. A store
      can never hand over or pass on what it does not hold;
    - **the manufacturing company allocates** stock to Mega stores (admin
      page, "Allocate stock");
    - **a Sub store collects from its Mega store**, and the Mega stockist
      records it on their store page (to be confirmed, decision 28).
- **Handover by code:**
    - on the new **My store** page, the stockist types the buyer's code. They
      see the order (number, what is in it, the buyer's first name, and their
      own rebate) and confirm;
    - in one step: the whole order is marked collected, the store's stock goes
      down, and the rebate is paid to the stockist wallet: ₦120 a unit for a
      Mega store, ₦90 for a Sub store;
    - the rebate comes from the ledger that funded the order: registration
      packs from Pool A, repurchases from Pool C, upgrade packs from Pool E's
      contingency fund, and public orders from what the sale held back. What a
      Sub store does not take (₦30 a unit) goes to the pool's contingency fund,
      or, for a public order, back to the manufacturer;
    - a code works once; a second try says when the order was collected. A
      store that is short of stock hands over nothing, and a deactivated store
      cannot hand over.
- **Upgrade packs** (board decision): an upgrade now creates an order for the
  higher package's extra packs. For example, Starter to Builder adds 2 packs
  (20 units).
- **Tests:** 210 pass (12 new):
    - the deposit split and the sponsor bonus to the kobo;
    - store PV for awards only;
    - activation once only, and a Sub store needing an active Mega;
    - allocation and collection;
    - handovers by a Sub store and a Mega store, paid from the right pool;
    - a public pickup, and an upgrade's packs;
    - short stock and deactivated stores refused;
    - the stockist's page and the admin permissions.
- **Checked in a browser** with the sample network:
    - Adaeze got the Ikeja Mega Store and Chika the Yaba Sub Store under it;
    - the manufacturer allocated 1,000 units to Ikeja, and Yaba collected 200;
    - Chika typed Gbenga's code in lower case, saw his 10-unit pack order,
      and handed it over. Her wallet showed ₦900 and her stock 190.

  The ledger check passed.
- **Tidied while checking:** the code box now clears after a handover, and
  statements say "Stockist rebate" instead of a system name.

**For the board:** decision 27 (the rest of a store's deposit goes to the
manufacturer) and decision 28 (who records a Sub store's collection).

**Next:** the bonuses: direct, indirect and key-in on registration, upgrade
bonuses, then daily matching.

## Step 21: Joining and upgrade bonuses (phase 4, part 4)

**What this is:** the first bonuses paid with real effect. When a package is
paid, its bonuses are paid at once from the registration pool. When a member
upgrades, the upgrade bonuses are paid from the upgrade pool.

- **One step when a package is paid.** Everything now happens together, all
  or nothing:
    1. the member is placed in the tree;
    2. their PV climbs;
    3. their packs' order is created;
    4. the payment is split between the manufacturer and Pool A;
    5. the bonuses are paid.

  Because the split comes first in the same step, every bonus line already
  holds the money it pays out. The same applies to an upgrade.
- **The bonuses** (on the package's PV value, PV × ₦600):
    - **direct**, 25% to the sponsor. A Mega sign-up pays ₦67,500, and a Pre
      Starter ₦2,250;
    - **indirect**, to the 10 generations above the sponsor: 3%, 2%, then 1%
      each. A Mega sign-up pays ₦8,100, ₦5,400, then ₦2,700 to each of the next
      eight;
    - **key-in**, 2% to the member who keyed the new member in (₦5,400 on a
      Mega). A sponsor who keys in their own recruit gets both the direct and
      the key-in bonus. A self sign-up has no key-in; the share stays with the
      cooperative;
    - **upgrade**, on the PV difference: 25% to the sponsor, 2% to generation
      2, and 1% each to generations 3 to 10. Starter to Builder (30 PV, ₦18,000)
      pays ₦4,500, ₦360, then ₦180 each.
- **Unpaid shares:** a share with nobody to receive it is not passed to anyone
  else, and the generation still counts. It stays with the cooperative in the
  pool's contingency fund. This covers a missing generation, a banned member,
  a member whose package is not paid, the company account, and a self
  sign-up's key-in.
- **Once only:** the same payment can never pay its bonuses twice.
- **Every line covers its bonus:** checked for every package size, each bonus
  line receives a little more than the bonus it pays (for example ₦2,251.16
  against a ₦2,250 direct bonus), so these bonuses never wait for money.
- **Tests:** 216 pass (6 new). Each checks the plan's own worked examples to
  the kobo, including that a banned generation's share is not passed up.

**Not yet:** the "Add Distributor" screen where a member keys someone in and
pays from their VISA wallet comes with payments. How the VISA wallet is topped
up is still open decision 10. The bonus service is ready for it.

**Next:** affiliate accounts, then withdrawals.

## Step 22: Affiliate accounts (phase 4, part 5)

**What this is:** the people who bring buyers to the public shop. As the board
decided, anyone can register as an affiliate, member or not, and every
affiliate has their own account and wallet.

- **Registering:** a new page at `/affiliate/register` asks for name, email,
  phone and a password. The affiliate gets a referral code such as
  `WII-TDAKD4`. A member can register too: it is a separate account with its
  own sign-in, and one affiliate account per email address.
- **Signing in:** at `/affiliate/login`, separate from member and admin
  sign-in. Five wrong passwords lock it for a minute. A banned affiliate cannot
  sign in and is told why.
- **Their link:** `/r/WII-TDAKD4` remembers the affiliate for 30 days on the
  visitor's device, then shows the site. The public storefront will read it
  when it is built with online payment, so the sale is credited to them.
- **Their dashboard:** the link with a Copy button, their affiliate wallet,
  what is still waiting for buyers to collect, the sales through their link,
  and the wallet statement.
- **Commission:**
    - a public order now records the affiliate who brought the buyer;
    - when the buyer collects the order from a stockist, the ₦100 a unit held
      since the sale (20% of the ₦500 margin) goes to the affiliate's wallet;
    - if the affiliate has been banned in the meantime, it goes to the
      manufacturer.
- **Admins:** a new Affiliates page (permissions "See affiliates and what they
  earned" and "Ban and unban affiliates") lists every affiliate with their
  code, contacts, sales and wallet. Admins can search, and ban or unban with a
  reason; it is audited.
- **Tests:** 223 pass (7 new):
    - registering and getting a code;
    - a member registering as an affiliate;
    - separate sign-in, and banned affiliates kept out;
    - the link being remembered;
    - the commission paid on pickup, or to the manufacturer when banned;
    - the admin page with its permissions.
- **Checked in a browser:** signing up as an affiliate, the welcome message
  with the code, and the dashboard on a laptop and a phone.

**Not yet:** an affiliate's password reset by email comes with email
messages. The public storefront comes with online payment.

**Next:** withdrawals, from the main balance, stockist wallets and affiliate
wallets, and moving bonuses to the main balance (bonus transfer).

## Step 23: Withdrawals and bonus transfer (phase 4, part 6)

**What this is:** how people take money out. Members, stockists and affiliates
can now withdraw what they have earned.

- **Bonus transfer:** on the Wallet page, a member moves money from any bonus
  wallet to their main balance ("All of it" fills in the full amount).
  Bonuses are withdrawn from the main balance.
- **Withdrawal methods:**
    - each has a minimum, a maximum, a fixed charge, a percentage charge and
      the details to ask for;
    - "Bank transfer" is set up from ₦1,000 to ₦5,000,000 with no charge, for
      an admin to change (decision 29). Its form asks for the bank name,
      account number and account name;
    - admins add and change methods on a new page (permission "Set up
      withdrawal methods, limits and charges"), and every change is audited.
- **Requesting:**
    - members withdraw from their main balance, stockists from their stockist
      wallet (on the same page), and affiliates from their affiliate wallet
      (on their own pages);
    - the form shows the charge and what the person will receive before they
      send it;
    - the amount leaves the wallet at once and waits in a holding account; a
      short wallet changes nothing; a resent form counts once.
- **Who may not withdraw:** a member whose withdrawals are blocked (they see
  the reason), and, while the new switch "Identity check (KYC) before
  withdrawing" is on (it is by default), a member whose identity is not
  verified.
- **The admin's decision:**
    - **mark as paid** after sending the money, with the payment reference.
      The person receives the amount less the charge, and the charge goes to
      the cooperative's own "withdrawal charges" account;
    - **refuse** with a reason the person sees. The full amount, charge
      included, goes back to the wallet;
    - a request can be decided once only; everything is audited;
    - a new Withdrawals page lists what is waiting (oldest first), paid and
      refused (permissions: see withdrawals; approve and reject them).
- **Tests:** 231 pass (8 new):
    - a request leaving the wallet and being paid less its charge;
    - a refusal returning everything;
    - blocks and KYC;
    - limits, details and balance, and a resent form;
    - stockist and affiliate withdrawals;
    - admin permissions and methods;
    - bonus transfer.

  One test caught a fault before it shipped: the withdraw page failed to open.
- **Checked in a browser:** a member asked for ₦5,000 by bank transfer and saw
  she would receive ₦4,850 (₦100 + 1% charge). The admin saw her bank details
  and "To send ₦4,850", marked it paid with a reference, and the ledger
  check passed.

**For the board (decision 29):** what each withdrawal method should charge,
whether charges go to the cooperative, and the KYC rule for withdrawals.

**Next:** daily binary matching with caps, then payments (Paystack and
e-pins), which bring real money in.

## Step 24: The daily matching bonus (phase 4, part 7)

**What this is:** the binary matching bonus (specification 5.5), paid every
night.

- **How a match works:**
    - 50 PV waiting on the left leg plus 50 PV waiting on the right leg make
      one match;
    - a member gets as many matches as their smaller leg allows, up to their
      package's daily cap (Pre Starter 5 a day up to Mega 120 a day);
    - each match pays the package's rate (₦900 for Pre Starter up to ₦3,600
      for Mega);
    - the PV a match uses is taken from both legs, and everything left over
      waits for the next night. Nothing is wiped. Lifetime PV, used for
      awards, never goes down.
- **When it runs:** every night at 01:30 Lagos time, for the day just ended.
  If a night is missed (the server was down), the next run first does the
  missed days one by one, each with its own daily cap, so nobody loses a
  day. Running a day again pays nobody twice.
- **Where the money comes from:** Pool A's matching line. If the line is
  short, Pool A's contingency fund covers it. If both are short, the payout
  is held and paid in full when money arrives, oldest first, as the board
  decided. The PV is used on the night either way, because the payout is
  owed.
- **Who is not paid:** banned members, members whose package is not paid,
  and the company account. Their PV stays where it is.
- **The autoship 20%:** until a member buys 10 PV of products in the month,
  20% of the bonus goes to their autoship wallet (5.6). This is a new admin
  switch, on by default, because the board has not yet said whether the hold
  stays (decision 7).
- **What people see:**
    - members: a "Matching bonus" table on their PV log, night by night
      (pairing PV before the run, matches, earnings, the autoship part, and
      "Daily cap" when the cap stopped them);
    - the PV log now also shows the PV each match used, once for each leg;
    - admins: a new Matching page (permission "See the nightly matching
      runs"), with each night's totals, what is still held, and every
      member's line.
- **Tests:** 238 pass (7 new). They cover:
    - the plan's own example (Mega, 1,000 left and 400 right: 8 matches,
      ₦28,800, of which ₦5,760 to autoship; 600 PV carries forward);
    - the daily cap and carrying forward, and a day run twice paying once;
    - banned members and the company account;
    - the autoship rule and its switch;
    - a payout the line cannot cover being held and paid once money
      arrived;
    - catching up missed nights;
    - the admin and member screens.
- **Checked in a browser:**
    - a demo Mega member with 320 PV left and 515 PV right got 6 matches,
      ₦21,600, of which ₦4,320 went to autoship;
    - the admin page showed the same night;
    - the PV log showed 300 PV used from each leg.

  The browser check caught a fault in the PV log: it showed "+-600" on one
  leg for a match. It now shows "−300" against "Each leg".

**For the board:**

- **Decision 7:** should the 20% autoship hold stay? It is a switch, so
  either answer is a click.
- **Decision 30 (new):** where should unused autoship money go at month
  end, and what may it buy? Spending it and the month-end sweep are not
  built yet.
- **Before go-live:** the earlier review estimated that matching could cost
  0.8 to 2.2 times its line as the network grows. The hold rule protects the
  pool, but members would wait. It should be measured on the live network.

**Next:** payments (Paystack and e-pins), which bring real money in.

## Step 25: Paying online with Paystack (phase 5, part 1)

**What this is:** the first real money coming in. Until now nobody could
pay for a package: the demo network was built by a command. Members can now
pay online through Paystack.

- **What can be paid:**
    - **a package:** a person who has signed up chooses a package on the new
      "My package" page and pays its price. When Paystack confirms, they
      become a member: their place in the tree, their packs' pickup code,
      the payment split, the joining bonuses, and the PV climbing the tree,
      all at once. The dashboard shows "One step left" until then;
    - **an upgrade:** a member picks a higher package on the same page and
      pays the difference in price;
    - **a deposit:** "Deposit" on the Wallet page adds ₦1,000 to ₦5,000,000
      to the main balance.
- **How a payment is confirmed:**
    - the member pays on Paystack's own page, so card details never touch
      our site;
    - nothing happens until Paystack itself confirms, by either:
        - its signed message to us (a "webhook"; an unsigned or forged one
          is refused), or
        - our server asking Paystack when the member comes back, and every
          five minutes for anything still waiting, for 48 hours.

      A browser saying "I paid" is never believed on its own;
    - only exactly the amount asked for, in naira, is credited. A different
      amount is marked "Wrong amount" and left for an admin;
    - the same payment is never credited twice, however many messages
      arrive.
- **Money never gets lost:** if what was paid for can no longer happen, the
  money goes to the member's main balance and the payment says why. That
  covers two cases:
    - the person became a member some other way;
    - the package changed before an upgrade payment was confirmed.

  Someone whose sponsor has not paid yet is stopped before paying, not
  after (decision 16).
- **Admins:** a new "Online payments" page lists every payment by status,
  searchable by reference or username (permission "See online payments").
  "Ask Paystack" checks a waiting payment now, and is recorded in the audit
  log. Admins cannot mark a payment paid themselves: only Paystack's answer
  counts.
- **Paystack's key:** it stays in the server's settings (`PAYSTACK_SECRET_KEY`),
  not on an admin screen, so it never passes through the admin panel. Without
  it, the pages say online payment is not set up yet.
- **A correction to the specification:** it said every deposit is split
  across the pools. Under Blueprint V3 each purchase funds its own ledger, so
  a deposit is the member's own money until it is spent. The text now says
  so, and the board is asked to confirm (decision 31).
- **Tests:** 248 pass (10 new). Paystack's replies are imitated, so the tests
  never touch real money. They cover:
    - paying a package and becoming a member;
    - a returning browser not being trusted, and a forged message being
      refused;
    - the wrong amount;
    - a deposit credited once;
    - an upgrade, and an out-of-date upgrade going to the main balance;
    - a sponsor who has not paid;
    - the five-minute check, and giving up after 48 hours;
    - Paystack refusing;
    - no key set;
    - the admin page.
- **Checked in a browser:**
    - the package and upgrade pages, the deposit page, the dashboard
      reminder on a phone, and the admin page;
    - this machine cannot reach Paystack, which showed two faults, now fixed:
        - Paystack refusing a payment crashed the page instead of showing a
          message;
        - the unfinished payment was left as "waiting";
    - "Ask Paystack" is now shown only for payments Paystack has seen.

**For the board (decision 31):**

- Members pay exactly the price, and Paystack's fee comes out of what it
  sends on. Should members pay the fee on top instead?
- Are deposits of ₦1,000 to ₦5,000,000 right?
- Is a deposit not split until it is spent?

**Still open (decision 25):** whether each company has its own Paystack
account. For now one account collects, and the ledger shows each company's
share.

**To go live:** put the live secret key in the server's settings, and in
Paystack's dashboard set the webhook address to `https://<site>/webhooks/paystack`.

**Next:** e-pins and keying in a new member from the VISA wallet ("Add
Distributor").

## Step 26: E-pins and Add Distributor (phase 5, part 2)

**What this is:** the two other ways to pay a package (specification section
2), besides paying online.

- **Add Distributor (key-in):** on the new "Add distributor" page a member
  registers someone and pays their package from their own VISA wallet.
    - The form asks for the person's details (including state, city and
      address, which key-in needs), the sponsor (the member, unless
      someone else invited the person), an optional place in the tree,
      the package, and the password the person will sign in with.
    - The account is created, the price leaves the VISA wallet, and the
      person is placed and paid for in one step. If anything fails (not
      enough in the VISA wallet, a wrong place in the tree), nothing
      happens at all.
    - The member earns the key-in bonus (2%), and the direct bonus too
      when they are the sponsor.
    - A form sent twice registers once.
- **E-pins:** a code that pays one person's package, once.
    - **Buying:** a member buys pins on the new "E-pins" page, from the VISA
      wallet, up to 50 at a time, each worth one package's price. They see
      each unused code with a Copy button, and later who used it and when.
    - **Using:** the person signs up, chooses their package on "My package"
      and types the code under "Or pay with an e-pin". Spaces, dashes and
      small letters do not matter. A used or cancelled pin, or a wrong
      code, is refused. If the person cannot be placed (for example their
      sponsor has not paid), the pin stays unused.
    - A pin pays a package costing up to its value; anything over goes to
      the new member's main balance (decision 32).
    - **Admins:** the new admin "E-pins" page lists every pin and who
      created and used it:
        - "See e-pins" lets an admin view the list;
        - "Create and cancel e-pins" lets them create pins (with a reason,
          for the audit log), see unused codes and cancel an unused pin. A
          bought pin's value goes back to the buyer's VISA wallet.
    - **The money:** a bought pin's value waits in an "E-pins issued and not
      yet used" account. When the pin is used, it is split like any
      registration. Codes are stored encrypted, never in plain text.
- **VISA wallet top-ups (decision 10):** the board has not said how members
  top it up. For now an admin does it with the existing "Add to or subtract
  from a member's wallet" on the member's page, which is audited.
- **Tidying:** the checks on a sign-up form (username, email, mobile,
  sponsor, place in the tree) now live in one place, so self sign-up and Add
  Distributor apply exactly the same rules.
- **Tests:** 256 pass (8 new). They cover:
    - buying pins once per form, with limits and a short wallet;
    - using a pin once, typed loosely;
    - the rest of a pin's value going to the main balance, and a pin
      too small for the package;
    - a pin that cannot place its user staying unused;
    - admin permissions, creating and cancelling with the refund;
    - key-in with both bonuses;
    - nothing created when the wallet is short or the form is incomplete;
    - a member without a package not being able to key in.
- **Checked in a browser:**
    - an admin topped up a member's VISA wallet;
    - the member bought two Starter pins;
    - the demo member who had not paid used one, typed in small letters,
      and became a member on Starter;
    - the first member keyed in a new Pre Starter member;
    - the admin page showed one pin used (by whom, and when) and one unused
      with its code;
    - the ledger check passed (110 transactions).

**For the board (decision 32):**

- When a pin is worth more than the package, where should the rest go?
- Admin-created pins are paid for by the cooperative. Who may create them,
  and up to what limit?
- May members cancel their own unused pins?

Decision 10 (how members top up the VISA wallet) is still open.

**Next:** the public storefront, where the public buy at ₦2,000 through an
affiliate's link and pay online.

## Step 27: The public shop (phase 5, part 3)

**What this is:** the shop for the public (specification section 8). Anyone
can buy Wiifarm products at ₦2,000 a unit, without an account, and collect
them from any stockist.

- **The shop page** (`/products`):
    - lists the products at the public price, with quantity buttons, and
      asks for the buyer's name, phone and email;
    - the buyer pays the total with Paystack;
    - the home page now has a "Shop" button.
- **Affiliates:** someone who arrives through an affiliate's link
  (`/r/WII-XXXXXX`, or `/products?ref=WII-XXXXXX`) is remembered for 30
  days, and the page says "Recommended by …". Their order records the
  affiliate, who earns ₦100 a unit when the goods are collected, as built
  in step 21. A banned affiliate's link earns nothing.
- **After paying:**
    - the buyer lands on their own page for the order. Its link is signed,
      so nobody can open another buyer's order by changing the number;
    - once Paystack confirms, the page shows the pickup code and the
      stockists where it can be collected (name, address, phone);
    - while Paystack is still confirming, the page says so and refreshes by
      itself.
- **The money** is split as decided for public sales. The affiliate's and
  stockist's shares are held until pickup, the rest belongs to the
  manufacturing company, and none goes to the cooperative's pools.
  Paystack confirms the payment exactly as for members (step 25).
- **Unpaid orders** are cancelled after 3 days by a nightly job, unless
  Paystack is still confirming their payment. A payment that arrives late
  is still honoured: paid is paid.
- **Admins** see public payments on the Online payments page, with the
  buyer's name and phone, and can search by order number or buyer.
- **Tests:** 262 pass (6 new). They cover:
    - the shop page;
    - an affiliate's link being remembered and the order being theirs,
      with the ₦100 a unit held;
    - the buyer's private page with the code, and refused without the
      signed link;
    - incomplete orders;
    - cancelling unpaid orders, and honouring a late payment;
    - the admin view.
- **Checked in a browser:** the shop through an affiliate's link
  ("Recommended by Kemi Ade"), on a computer and a phone, and the page of a
  paid order with its code and the stockists. Two faults found and fixed:
    - the order page's link was signed together with the server's address,
      so it failed when opened at a different address. Only the page's own
      path is signed now;
    - the product card stretched to the height of the details form.

**For the board (decision 33):**

- Are 30 days for the affiliate link and 3 days for an unpaid order right?
- Buyers must give an email, because Paystack needs one. Is that
  acceptable?
- Are prices per state still wanted? For now the public price is the same
  everywhere.

**Next:** a real member dashboard (balances, PV, team, orders at a glance),
then the leadership bonus and the monthly runs.

## Step 28: The member dashboard (phase 5, part 4)

**What this is:** the first page a member sees after signing in. Until now
it was an empty placeholder; it now shows their position at a glance.

- **For someone who has not paid yet:** a reminder to choose their package,
  pay online or with an e-pin, and a button to the package page.
- **For a member:**
    - **who they are:** their package and since when, and their own sign-up
      link (`/register?ref=their username`) with a Copy button;
    - **what needs doing,** shown only when it applies:
        - orders waiting to be collected with a pickup code;
        - identity (KYC) not yet verified, so they cannot withdraw;
        - this month's product purchases below 10 PV, so 20% of their
          matching bonus goes to autoship (shown only while that switch
          is on);
    - **money:**
        - the main balance;
        - what is waiting in the bonus wallets;
        - **earned so far** (every bonus ever paid to them, even after
          moving it to the main balance);
        - the VISA wallet, and the autoship wallet when it holds money;
    - **PV:** what is waiting to match and the lifetime PV on each leg;
    - **matching:** what a match pays on their package, the daily cap, and
      last night's matches (marked when the cap stopped them or the payout
      is held);
    - **team:** how many members they sponsored, how many sign-ups have not
      paid yet, and how many members sit in their left and right teams;
    - **latest earnings:** the last six bonuses, with what each was for.
- **Team counts** are worked out from the tree in one database query, for
  each leg, however deep it goes.
- **Tests:** 264 pass (2 new): the page for someone not yet paid, and every
  figure for a member, including that moving a bonus to the main balance
  does not change "earned so far".
- **Checked in a browser** on a computer and a phone with a demo Mega
  member. One fault found and fixed: on a laptop-sized screen the four money
  cards were too narrow and cut the amounts off ("₦75,810...."). They now
  sit two to a row until the screen is wide enough.

**Next:** the leadership bonus and the monthly runs (decisions 3 and 6
affect who qualifies and who counts as the team).

## Step 29: The leadership bonus (phase 5, part 5)

**What this is:** the monthly leadership bonus (specification 5.7).

- **Who qualifies:** a member with 480,000 PV on their repurchase PV log by
  the end of the month (Blueprint V3). Banned members, members whose
  package is not paid and the company account are not paid.
- **How much:** 5% of what the leader's team earned from the matching bonus
  in the month just ended, from Pool A's leadership line. If the line and
  Pool A's contingency fund are short, the payout is held until money
  arrives, as for every bonus.
- **Who the "team" is (decision 3, not yet decided):** an admin setting,
  under Settings, "Leadership bonus: who is the team":
    - **the binary tree:** everyone below the leader (the default, as you
      asked), or
    - **the sponsor chain:** everyone the leader brought in, and the people
      they brought in;
    - to a set number of levels or generations, or with no limit (0).

  Changing it is recorded in the audit log and applies from the next run.
  Each month's run records the rule it used, so past months stay
  explainable.
- **When:** early on the first of each month (02:30 Lagos time), for the
  month just ended. A missed month is caught up by the next run; a month is
  never paid twice.
- **A leader inside another leader's team** still counts there, so both
  earn on the same matching. The plan does not say otherwise; this is added
  to decision 3 for the board.
- **What people see:**
    - admins: a new Leadership page with each month's run, the rule it
      used, what was earned and what is still held, and every leader's
      line (repurchase PV, team size, team matching, 5%);
    - members: a "Leadership bonus" card on their dashboard with their
      repurchase PV against the 480,000 PV needed, and last month's
      result.
- **Tests:** 270 pass (6 new). They cover:
    - 5% of the whole tree team's matching, paid once, with other months
      and outsiders left out;
    - the setting: a level limit, and the sponsor chain instead of the
      tree;
    - who qualifies (479,999 PV does not, banned leaders do not, a team
      with no matching pays nothing);
    - a payout too big for the line being held;
    - the monthly command;
    - admin permissions and the settings form, and the member's dashboard
      card.

  One fault from the last step was found here: a doubled line in the
  dashboard test stopped the test suite from starting. It is fixed.
- **Checked in a browser:**
    - the settings card, the admin run page and the member's dashboard card,
      with a demo member given 480,000 repurchase PV;
    - the demo team had no matching in November, so the page showed
      "Nothing to pay". The dashboard now says why ("your team earned no
      matching"), not just ₦0.00.

**For the board (decision 3):**

- Who is the team (tree or sponsor chain, how deep)?
- When one leader is in another's team, should both earn on the same
  matching?

**Next:** the unilevel bonus (10 generations, from the repurchase pool).

## Step 30: The unilevel bonus (phase 5, part 6)

**What this is:** the monthly unilevel bonus (specification 5.10), paid on
what members buy after joining.

- **How it works:** for every member who bought products in the month, the
  10 generations above them in the referral chain share in the PV value
  (PV × ₦600) of what they bought: 10%, 7%, 5%, 4%, 4%, 3%, 3%, 3%, 2%
  and 2%, or 43% in all. For example, a member's 10.5 PV purchase (₦6,300
  of PV value) pays their sponsor ₦630.
- **Who is not paid a share:**
    - a member whose package does not reach that generation (Pre Starter 4,
      Starter 6, Builder 8, Booster and up 10);
    - banned members, members whose package is not paid, and the company
      account;
    - while the new switch is on, a member who did not buy 10 PV of
      products themselves that month (decision 4). The switch is on by
      default, because section 4 says the 10 PV purchase keeps a member
      active for unilevel.

  Such a share is not passed on, and the generation still counts.
- **The money:** each member's shares for the month are paid together, from
  the repurchase ledger's (Pool C's) unilevel line, into their unilevel
  wallet. What is left in the line afterwards goes to Pool C's contingency
  fund. If the line and the fund are short, the payout is held until they
  refill.
- **A point worth knowing:**
    - the unilevel line receives 49% of the cooperative's half of the
      price, which is a little less than 43% of the PV value. On 7 packs,
      for example, the line receives ₦2,572.50, while paying all 10
      generations costs ₦2,709;
    - so when every generation is paid, the contingency fund covers the
      difference, as the specification says. A test caught my first
      version assuming the opposite;
    - the run records what actually moved to the contingency fund, not the
      value of the unpaid shares.
- **When:** early on the first of each month (02:20 Lagos time), for the
  month just ended. A missed month is caught up; a month is never paid
  twice.
- **What people see:**
    - admins: a new Unilevel page with each month's repurchase PV, members
      paid, earnings, what went to the contingency fund and what is held,
      and each member's earnings by generation;
    - members: the dashboard's 10 PV reminder now also says that without it
      they do not earn the month's unilevel bonus.
- **Tests:** 276 pass (6 new), using real member purchases. They cover:
    - all 10 shares and nothing for an 11th generation, paid once;
    - a package that does not reach a generation, with the next generation
      still paid its own rate;
    - the 10 PV rule;
    - banned and company uplines;
    - a payout held when the line is empty;
    - the monthly command and the admin pages.
- **Checked in a browser:** the admin Unilevel page, the new switch on the
  Settings page, and the dashboard reminder. The local demo month shows a
  very large "to contingency" figure because of PV I had put in by hand for
  the last step's demo, not through a purchase. That is local test data
  only, and is also why the run now records what actually moved.

**For the board (decision 4):** must a member buy 10 PV themselves in a
month to earn that month's unilevel bonus? It is a switch, so either answer
is a click.

**Next:** the awards (specification 5.8, 5.9 and 5.11): the binary rank
awards, the Upline Celebrated Bonus, and the repurchase awards. Decision 21
(the repurchase award schedule) is still open.
