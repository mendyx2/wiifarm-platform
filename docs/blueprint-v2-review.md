# Allocation Blueprint V2: review and implementation plan

The board's final decisions on bonuses and allocations arrived as two
documents: **Allocation Blueprint V2** (the pool allocation schedule) and
**Baseline Rules & Formula for the Allocation Blueprint**. This page checks
whether they can work, lists what still needs an answer, and plans the build.
Nothing has been built from them yet.

## The short answer

**Most of it is feasible and can be built as written.** The new model fixes the
biggest problem in the old specification: the registration bonuses now fit
their pools exactly. Direct, indirect, key-in and upgrade bonuses each pay
exactly what their pool line receives, to the kobo.

**Four parts would pay out more than their pools take in** as the network
grows: the binary matching, the unilevel bonus, the rank (recognition)
awards and the repurchase awards. A fifth, the stockist rebate charged on
registrations, over-fills the Consumer Rewards Pool. The software can still
pay them, and will show every shortfall, but the board needs to decide what
happens when a pool runs short. That is the one decision that blocks paying
real money.

## How the money flows (as the board decided)

Every ₦1,500 product piece (1.5 PV; so ₦1,000 of price = 1 PV) splits 50/50:

| Revenue from | Company keeps | Pool (₦750 per piece) | Taken off the top | Left to pay members |
| --- | --- | --- | --- | --- |
| Registration | ₦750 | Pool A, Consumer Rewards | Strategic Reserve 10% (₦75), Executive Leaders 10% (₦75) | ₦600 |
| Upgrade | ₦750 | Pool E, Upgrade Reserve | Strategic Reserve 10%, Executive Leaders 10% | ₦600 |
| Repurchase | ₦750 | Pool C, Commerce | nothing | ₦750 |
| Stockist activation | | Pool D | | 8% of ₦3M or 6.5% of ₦10M |

Bonuses are paid at ₦600 per PV earned.

This replaces the old 30 / 10 / 5 / 55 split in section 7 of the
specification, and it answers open decision 2: each kind of revenue funds its
own pool.

## What was checked, and the result

### Exact fits (feasible as written)

| Bonus | Plan formula | Per piece | Pool line per piece | Result |
| --- | --- | --- | --- | --- |
| Direct referral | 25% × PV × ₦600 | ₦225 | 37.5% of ₦600 = ₦225 | Exact |
| Indirect, 10 generations | 3% + 2% + 8 × 1% = 13% | ₦117 | 19.5% of ₦600 = ₦117 | Exact |
| Key-in | 2% × PV × ₦600 | ₦18 | 3% of ₦600 = ₦18 | Exact |
| Upgrade, 10 generations | 25% + 2% + 8 × 1% of ΔPV | ₦315 | 52.5% of ₦600 = ₦315 | Exact |
| Stockist activation (Basic) | ₦50k + ₦30k + 8 × ₦20k | ₦240,000 | 8% of ₦3M = ₦240,000 | Exact |
| Stockist activation (Mega) | ₦150k + ₦100k + 8 × ₦50k | ₦650,000 | 6.5% of ₦10M = ₦650,000 | Exact |
| Stockist rebate on repurchases | ₦120 (Mega) or ₦90 (Sub) per piece | ₦90 to ₦120 | 20% of ₦750 = ₦150 | Fits, ₦30 to ₦60 left |
| UCB (5% + 2% + 1% of an award) | 8% of each award | | 30% of the Strategic Reserve | Fits easily |

Where a generation is missing, the member is the company account, or the
registration was keyed in by an admin, the board's rule is that the share
goes to company "breakage". So these lines never overspend.

### Do not fit as written

1. **Binary matching (18% of Pool A, ₦108 per piece).** The pool line is
   fixed per piece sold. But every PV climbs to *every* upline, and each upline
   can be paid a match on it, so the cost grows with the depth of the tree.
   A simulation of growing networks with a realistic package mix (no daily
   caps; caps lower it, repurchase and upgrade PV raise it):

   | Members | Matching paid ÷ matching pool |
   | --- | --- |
   | 100 | 0.4 to 0.6 |
   | 1,000 | 0.8 to 1.3 |
   | 10,000 | 1.1 to 1.8 |
   | 50,000 | 1.1 to 2.2 |

   So matching fits a young network and then outgrows its pool, by up to
   about twice. The real figure depends on the shape of Wiifarm's tree; it
   should be measured on the live data before go-live.

2. **Matching on repurchase and upgrade PV has no pool.** The specification
   says product and upgrade PV climb the tree and count for matching. But
   repurchase money goes to the Commerce Pool and upgrade money to Pool E,
   and neither has a matching line. Either that PV should not count for
   matching (awards only), or a matching share should be taken from those
   pools. Pool E has ₦285 per piece left over that could carry it.

3. **Unilevel (49% of Pool C, ₦367.50 per piece).** The plan's rates add up to
   49% *of PV value* (₦900 per piece), which is ₦441 per piece at full depth:
   ₦73.50, or 20%, more than the line. In practice, members whose package
   does not unlock deep generations, and inactive members, leave shares
   unpaid, so it may fit; at full depth it does not.

4. **Repurchase awards (10% of Pool C, ₦75 per piece).** The award table
   (₦50,000 at 750 PV up to ₦5M at 45,000 PV) pays a single earner 1.3 to 7
   times what their team's repurchases put into the pool, before counting the
   other uplines who earn on the same volume. Section 5.11 of the
   specification still has the old table; the board document refers to a
   milestone schedule that was not included in the PDF.

5. **Recognition (rank) awards (70% of the Strategic Reserve).** Even if one
   member got the whole pool from the volume under them, the cash ladder
   costs 0.8 to 1.9 times that pool, and every upline earns on the same
   volume. The board document says awards are paid "when triggered" from the
   pool; it does not say what happens if the pool is short.

6. **Stockist rebate on registrations.** Pool A lists "Stockist Rebate" at 20%
   (₦150, marked "Pool C share"). Added to the other Pool A lines (88.3%,
   ₦529.80), that is ₦679.80 out of ₦600: 113%. It only fits if this rebate
   is paid from somewhere else.

7. **The sheet's own subtotals do not add up.** Pool A shows 70.80% (₦455)
   but its lines add to 88.30% (₦529.80) without the stockist rebate. Pool C
   adds to 89%, so ₦82.50 per piece has no named use; Pool E leaves 47.5%
   (₦285). The board should say where leftovers go (stay in the pool as a
   buffer, or company breakage).

## Questions for the board

Each is needed before the part it affects is built:

1. **When a pool is short** (matching, unilevel, awards): pay in full and
   show the deficit; scale everyone's payout down to what the pool holds that
   day or month; or hold payouts until the pool refills? (This replaces
   open decisions 1 and 11.)
2. **Repurchase and upgrade PV:** do they count for binary matching? If yes,
   which pool pays for it?
3. **Stockist rebate on registrations:** which pool pays it, given Pool A is
   already full?
4. **Leftovers:** what happens to the unallocated parts of Pools A, C and E?
5. **The repurchase award schedule:** the board document refers to a
   schedule that is missing from the PDF. Is it the table in section 5.11, or
   a new one?
6. **Leadership bonus:** the qualification is "Executive Ambassador
   (480,000 PV)" in one document and "480,000 PV on the repurchase PV log" in
   the other; section 5.8 puts Executive Ambassador at 450,000 PV of binary
   lifetime PV. Which is it? And is the "team" the whole downline, or a set
   number of levels (open decision 3)?
7. **The PV rate typo:** the baseline rules say "₦1,500 of product = 1 PV",
   but the example says ₦1,500 = 1.5 PV. The example matches the packages
   (₦15,000 = 15 PV), so the rule is read as ₦1,000 = 1 PV. Please confirm.
8. **Monthly activity:** the new rule is 10.5 PV (₦10,500) of personal
   purchases per calendar month; the plan said 10 PV. Does the registration
   itself count for the month a member joins? Does the rule apply only to
   matching (as written: volume is frozen, not lost), or to every bonus?
9. **Autoship:** the 20% autoship hold on matching (open decision 7) is not in
   the new documents. Is it dropped?
10. **Key-in on self sign-up:** the key-in bonus goes to "the account used to
    submit the registration". When people sign themselves up, that is their
    own account. Do they get 2% on their own registration, or does it go to
    breakage?
11. **Indirect generation 1:** the old specification says generation 1 is the
    sponsor's sponsor (the sponsor already gets 25%). The upgrade rule counts
    the sponsor as generation 1. Please confirm the indirect bonus starts at
    the sponsor's sponsor.
12. **Global Share Pool:** it had a 1.25% line before and has none now. Is it
    dropped?
13. **Missing details:** the package tier table on page 1 of the baseline
    rules is cut off (assumed unchanged from section 4); the Sub-leader ranks
    and targets and the Executive Leaders Bonus payout rules are "to be
    decided". Their money can be collected in their pools now and paid out
    once the rules come.
14. **Daily matching cut-off:** proposed midnight Lagos time.

## Implementation plan

The work follows the money and reuses what is built: the ledger (every pool
line is a ledger account, and a pool below zero shows as a deficit) and the
joining and upgrade steps (which already run once per payment).

| Step | What | Depends on | Can start |
| --- | --- | --- | --- |
| 4a | **The revenue waterfall.** Every confirmed payment splits into company 50%, the pool, the Strategic Reserve, Executive Leaders and each bonus line, as ledger entries that add to the payment exactly. A breakage account. All rates become admin settings, logged, applying from their change onwards, never backwards. A pools report: in, paid, left, per line, by day, month and quarter. | nothing | Now |
| 4b | **Registration bonuses:** direct, indirect (10 generations) and key-in, paid the moment a package is paid, each charged to its line, with breakage for missing, company and banned uplines. Records who submitted each registration. | 4a | Now |
| 4c | **Upgrade bonuses:** 25% / 2% / 1% × 8 from Pool E. | 4a | Now |
| 4d | **Daily binary matching:** the run at the cut-off, daily caps, carry-forward, frozen volume for inactive members, paid from the matching line, with the shortfall rule (question 1). | 4a, question 1 | After the answer |
| 4e | **Monthly activity** (10.5 PV) and its effect on matching. | the shop (personal purchases) | With the shop |
| 4f | **Monthly runs:** leadership 5% override; Sub-leaders and Executive Leaders pools collected and held until their rules come. | 4d, question 6 | After 4d |
| 4g | **Awards:** rank detection, admin approval, cash or gift, UCB to 3 generations; repurchase awards. | questions 1 and 5 | After the answers |
| 4h | **Commerce pool payouts:** unilevel and stockist rebates per piece fulfilled. | the shop and stockists | With those modules |
| 4i | **Stockist activation bonus** to 10 generations. | stockists | With stockists |

Every bonus is "once only": each payout records the payment, match run, award
or order that caused it, so a re-run never pays twice. Each step gets tests
built from the worked examples in these documents. One example: a Pre
Starter to Mega upgrade (ΔPV 435) pays the sponsor ₦65,250.

**Before go-live:** run the matching and award rules over a copy of the
live network to measure the real cost against the pools. The simulation
above gives the range; the real tree decides it.

**Paperwork that goes with it:** sections 5 and 7 of the specification will be
rewritten to match Blueprint V2, and the open decisions it settles (1 for
the fixed bonuses, and 2) closed, with the new questions above added.
