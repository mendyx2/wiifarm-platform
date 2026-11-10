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
