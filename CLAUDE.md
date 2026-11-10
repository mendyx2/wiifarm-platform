# Working on the Wiifarm Platform

Read this first. It carries the context from earlier work, so a new session
does not start from nothing.

## What this repository is

A clean rebuild of the Wiifarm Cooperative platform. The current live system
is `mendyx2/wiifarmvest`; it is built on a licensed commercial script
(ViserLab), and this repository must contain none of that script's code,
templates, styles, wording or table designs.

## The documents that hold the context

- `docs/specification.md`: what to build. The source of truth for every rule.
  Its authorities are Wiifarm's Proposed Business Compensation Plan and the
  Compensation Engineering Blueprint v1.0. Section 13 lists open board decisions:
  do not guess an answer to any of them; build around them or ask.
- `docs/build-plan.md`: the clean-room rules and the seven phases.
- `docs/frontend-file-list.md`: which files from the old system may come
  across (Reuse / Restyle) and which may not (Rebuild).
- `docs/provenance.md`: the log of every file brought over. Update it in the
  same change that brings a file in.
- `docs/notes.md`: the step-by-step log of work in this repository (start it
  with the first change; one numbered step per piece of work).

## Clean-room rules (must follow)

1. Write code from the specification, never by copying or translating old files.
2. You may read the old repository to see how something behaves and to compare
   results in testing. Never paste its code here.
3. A file from the old system comes across only if the file list allows it,
   after review, and with a line in `docs/provenance.md`.
4. Only open-source dependencies that allow commercial use (MIT, BSD, Apache).
   Never add `laramin/utility` or any package from the licensed script.

## How the owner likes to work

- Explain every change step by step in `docs/notes.md`, in plain language.
- Use simple language in anything meant for the board.
- Flag business decisions instead of assuming; record the answer in the
  specification when it comes.
- Verify a claim before stating it.
- Do not open a pull request unless asked.

## Key facts (details in the specification)

- 1 PV = ₦600 for bonus calculations. Package prices in the plan are ₦1,000
  per PV; which one applies is an open board decision.
- Seven packages, Pre Starter (15 PV) to Mega (450 PV); 50 PV per match;
  daily caps carry forward; no sponsor needed per leg; banned members earn no
  bonuses.
- Every naira and PV point is a ledger entry; balances are never edited.
- Nothing is ever paid twice: every payout records what triggered it.
