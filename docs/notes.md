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
