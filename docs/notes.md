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
