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
- Phase 2, the foundation (accounts, admin roles, the ledger, the design system): **next**.

## Reference

The old system's state when this rebuild began is commit
[`a6314c6`](https://github.com/mendyx2/wiifarmvest/commit/a6314c6) on its `main` branch.
