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

## Public shop (board, November 2026)

Per ₦2,000 unit:

| Share | Per unit |
| --- | --- |
| Manufacturers | ₦750 |
| Cooperative pool | ₦750 |
| Affiliate (20% of the ₦500 margin) | ₦100 |
| Manufacturers' account (rest of the margin) | ₦400 |

It adds up to ₦2,000 exactly. Still open (decision 23):

- which ledger takes the cooperative's ₦750;
- whether the stockist's rebate on a public pickup comes from it;
- where the ₦100 goes when nobody referred the buyer;
- whether a public sale earns PV.

## What still does not fit

| Line | Holds per unit | Can cost per unit | Note |
| --- | --- | --- | --- |
| Recognition (rank) awards | ₦5.25 | 8 to 19 × the pool | Was ₦52.50 under V2; the 1% Strategic Reserve cut it by 90% |
| Binary matching | ₦108.01 | 0.8 to 1.1 × typically, up to 2.1 × in very even growth | Daily caps mostly delay payment |
| Unilevel at full depth | ₦367.50 | ₦387 (10 generations) or ₦441 (15) | Real payouts are lower: package depth and inactive members |
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
6. The public shop and affiliates. The split is set; which ledger takes the cooperative's ₦750 is still open.
