# Wiifarm Platform Rebuild: Build Plan

Nov 6, 2026 · @wendy

A new platform that does everything Wiifarm uses today, written from scratch on free, open-source tools, with no ViserLab code.

## Ground rules

"Clone" here means the **same features and workflows** Wiifarm uses today. It does not mean a copy of ViserLab's code, screens, wording or database. Copying any of those, even with names changed, would still be using their work. Features and business rules are not owned by ViserLab, so rebuilding them from a written specification is fine.

- **Build from the specification, not the old code.** Developers write new code from the plain-language specification (step 1). Nobody copies, retypes or "translates" old files.
- **Treat the old system as a black box.** It is used only to watch what happens (inputs and results) and to compare totals in testing.
- **No ViserLab parts at all.** That means no `laramin` licence package, no "basic" template, no admin theme, images, icons, help text or email templates, and none of their database table designs.
- **Wiifarm's own design.** The admin and member screens get a new look, new layouts and new wording.
- **Only open-source building blocks**, under licences that allow commercial use (MIT, BSD, Apache). Each one is listed in a register.
- **The same rule applies to code from this project.** The React screens and bonus services written so far grew inside ViserLab's structure, so they are rewritten from the specification too. Only Wiifarm's business rules carry over.
- **A similarity check before go-live.** A reviewer compares the new code with the old and confirms nothing was copied.

## What it must do

The new platform needs ten modules, taken from the current system's menus and the compensation plan. Every rule below goes into the specification in full, with worked examples.

| Module | What it covers | Key rules to carry over |
| --- | --- | --- |
| 1. Members and accounts | Sign-up with sponsor, login, two-factor, profile, KYC (NIN, ID card), bank details, password reset, ban/unban | No referral → company account (member #1); usernames ≥ 6 characters |
| 2. Binary network | Placement (chosen parent and leg, or spillover), genealogy tree, binary list and summary, PV logs, stages | Taken slot → next free slot down the same leg; PV climbs to every upline on the correct leg |
| 3. Compensation | Direct and indirect bonus, matching, awards, leadership, unilevel, ACB, repurchase awards, autoship, key-in bonus | 1 PV = ₦600; 7 packages (₦15,000 to ₦450,000); 50 PV per match; daily cap with carry-forward; no sponsor needed per leg; banned members not paid; 80/20 autoship split |
| 4. Wallets and payments | Main balance and bonus wallets, deposits (Paystack, USDT TRC20, manual), withdrawals, transfers, e-pins | Every movement is a ledger entry; nothing is paid twice |
| 5. Pools and governance | Company reserve, executive, operational and compensation pools, Global Share Pool (quarterly) | Each payment is split across the four pools; bonuses are paid from the compensation reserve |
| 6. Shop | Products, categories, state prices, cart, checkout, orders, affiliate links and bonuses, welcome packs | Product PV feeds the tree; affiliate orders expire |
| 7. Stockists | Basic (₦3M) and Mega (₦10M) stores, inventory, point of sale, stock requests, transfers, redemptions, rebates | Enrollment bonus to 10 generations, once per stockist |
| 8. Farm investments and savings | Investment plans, dividends (batches, reversal), farm cycles (FFO), savings products, loans with guarantors | Dividends are previewed before paying; loans follow their repayment schedules |
| 9. Admin | Dashboard, member management, approvals, settings, admins with roles and permissions, support tickets, notices, audit log | Every money action needs a permission and is logged |
| 10. Website and messages | Public pages, shop front, email, SMS and push templates, languages, scheduled jobs | Payouts run from one daily schedule |

Some rules are still open with the board (see Risks), and the specification records each answer as it comes.

## Architecture

&#91;embedded content: architecture · front end, eight back-end modules, data and outside services\]

One web application, with no licence server and no outside code owner. The modules talk to each other only through the ledger and their own records, so each can be built and tested on its own. Payouts and the USDT scan run from the scheduler, not by hand.

## Data

The database is designed new, around one rule: **balances are never edited directly**. Every naira and every PV point is a ledger entry, and a balance is the sum of its entries. That makes every payout traceable and stops double payments.

| Record | What it holds |
| --- | --- |
| Member | Identity, sponsor, package, status, KYC state |
| Tree position | Parent, leg (left/right), stage |
| PV entry | Member, leg, points, source (registration, purchase, store) |
| Wallet entry | Member, wallet, amount (+/−), reason, reference; one row per movement |
| Bonus run | Each matching, award or pool payout, with what it paid and why |
| Pool entry | Pool, amount, source payment |
| Payment | Deposit or withdrawal, gateway, status, proof |
| Product, order, stock | Shop items, state prices, orders, stockist inventory and movements |
| Investment, loan | Plan, holding, dividend batch, savings product, loan and repayment schedule |
| Admin, role, audit entry | Who did what, when, with which permission |

The old database is used only as the **source for the data move**: a migration script reads it and writes Wiifarm's new records.

## Build phases

&#91;embedded content: build roadmap · 7 phases, a gate after each\]

The phases follow the money: the ledger comes first, because everything else writes to it. Phases 4 to 6 can overlap once phase 3 has passed its gate. Dates and cost are set at the end of phase 1, once the specification shows the full size of the work.

## Testing and switch-over

The new system goes live only when it pays the same members the same amounts as the old one, on the same events.

1. **Automated tests for every rule.** Each worked example in the specification becomes a test, for example "a Mega member with 1,000 / 400 PV and a cap of 2 is paid 2 matches and carries 900 / 300".
2. **Parity runs.** The same test network (sign-ups, purchases, a store) goes through both systems. Every balance, bonus and PV total must match to the kobo. Any difference is either fixed or recorded as an approved rule change.
3. **Data move rehearsals.** The migration script copies a recent backup of the live data into the new system at least twice before go-live. Member counts, balances and tree positions are checked against the old totals.
4. **Similarity review.** A reviewer who did not write the code confirms nothing was copied from ViserLab.
5. **Go-live.** Freeze the old system for a few hours, run the final data move and check the totals. Then point the domain at the new system. The old system stays read-only for 90 days as a fallback and a record.
6. **Members are told in advance.** Same logins, same balances, a new look.

## Risks and open questions

| Risk | What we do about it |
| --- | --- |
| Copied code slips in | Clean-room rules, a dependency register, and the similarity review before go-live |
| A bonus rule is misunderstood | Worked examples signed off by the board, and parity runs against the old system |
| The old data has errors (for example, registrations that failed with the bug fixed in Step 126) | Data-move rehearsals list every record that does not add up, for a decision before go-live |
| The work grows during the build | Only what the signed specification lists is built; new ideas wait for version 2 |
| The old system stops during the build (licence check) | Decide now whether to buy a licence for the months until go-live |

**Open questions for the board:**

- [ ] Which award ladder is correct: the code's (Emerald to Global Mega) or the remediation notes' (Sapphire Leader to Global Presidential)?
- [ ] Should a ban pause bonuses (paid on unban, as now) or cancel them for good?
- [ ] Who builds it: an in-house developer, a contracted team, or both? Phase 1 needs one person who knows the business rules.
- [ ] Which modules can wait until after go-live? Fewer modules at launch means an earlier switch-over.
