# Allocation Blueprint V3: review

The board's bonus allocation blueprint is now **Allocation Blueprint V3**
(`docs/board/allocation-blueprint-v3.xlsx`). Together with the November 2026
meeting notes, it is written into specification sections 7, 8 and 9. Where a
note in the workbook disagrees with its percentage column (several notes still
describe V2), the percentage column is followed.

## What V3 settles

- **Every member purchase splits 50/50:** ₦750 a unit to the manufacturers
  and ₦750 to the cooperative.
- **Each kind of purchase has its own ledger:**
  - registration (Pool A);
  - repurchase (Pool C, which keeps the full ₦750);
  - upgrade (Pool E);
  - stockist activation (Pool D).
- **The registration ledger now balances.** Its lines add up to exactly 100%
  of ₦650.25, because the Strategic Reserve went from 10% to 1% and a 2.3%
  Contingency Reserve was added. Under V2 the same lines needed 108.3%.
- **The stockist rebate is funded in both ledgers that pay it:**
  - registration ledger, 18.5%: ₦120.30 a unit;
  - repurchase ledger, 16%: ₦120 a unit.

  That covers ₦120 to a Mega store or ₦90 to a Sub store.
- **The fixed-formula bonuses fit to the kobo,** with a little to spare:

  | Bonus | Line holds | Plan pays |
  | --- | --- | --- |
  | Direct | ₦225.12 | ₦225 |
  | Indirect | ₦117.05 | ₦117 |
  | Key-in | ₦18.01 | ₦18 |
  | Upgrade | ₦350.44 | ₦315 |
- **Safety nets:**
  - repurchase ledger: ₦112.50 a unit;
  - upgrade ledger: ₦317.06 a unit;
  - anything a line does not pay out also stays in its ledger.

## Decisions from the November 2026 meeting

- **Public shop sales stay outside the cooperative.** Of a ₦2,000 unit:
  - 20% of the ₦500 margin (₦100) to the affiliate's wallet, when an
    affiliate's link brought the buyer;
  - the usual ₦120 / ₦90 to the stockist who hands it over;
  - the rest to the manufacturers: ₦1,780 to ₦1,910 a unit.

  No PV, no network bonus. Sales straight from the landing page have no
  affiliate share.
- **Affiliates and stockists each have their own wallet.**
- **The stockist sponsor bonus is Pool D** (8% / 6.5% to 10 sponsor
  generations). There is no ongoing share of store sales.
- **10 generations, never 15:**
  - unilevel is now 43% over 10 generations;
  - every package from Booster up reaches all 10;
  - store PV also stops at 10.
- **Contingency fund.** Every reserve and leftover goes into a contingency
  fund, kept per ledger. Each ledger is self-sufficient: its shortfalls are
  covered only from its own contingency fund, never by another ledger.

## What still does not fit

| Line | Holds per unit | Can cost per unit | Note |
| --- | --- | --- | --- |
| Recognition (rank) awards | ₦5.25 | 8 to 19 × the pool | Was ₦52.50 under V2; the 1% Strategic Reserve cut it by 90% |
| Binary matching | ₦108.01 | 0.8 to 1.1 × typically, up to 2.1 × in very even growth | Daily caps mostly delay payment |
| Unilevel at full depth | ₦367.50 | ₦387 (10 generations) | ₦19.50 short only when all 10 uplines qualify; covered by the repurchase ledger's own contingency fund (₦112.50 a unit goes in) |
| Repurchase awards | ₦75 | Section 5.11's table costs 1.3 to 7 × for one earner | The board's schedule has not arrived |

These are open decisions 17 to 24 in specification section 13. Two things
cannot be built until the board decides them:

- **the rule for a line that runs short** (decision 18), which blocks real
  matching and award payouts;
- **the awards** (decisions 17 and 21).

## Build order (unchanged)

1. Separate ledgers and the split of every payment: manufacturers, the four
   ledgers, Strategic Reserve, Executive Leaders, Contingency, Sub-leaders and
   safety nets. Rates are admin settings, applying forward only.
2. Orders and pickup codes: any stockist, no expiry, whole orders.
3. Stockists:
   - Mega and Sub stores;
   - stockist wallets;
   - company allocation of stock;
   - stock books balanced on each code;
   - rebates from the right ledger.
4. Registration and upgrade bonuses.
5. Daily matching with caps, then leadership, unilevel and awards, once the
   shortfall rule is set.
6. The public shop, affiliates and their wallets (split settled).
