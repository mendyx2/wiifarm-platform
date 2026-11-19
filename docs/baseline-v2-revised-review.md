# Revised Baseline Rules (V2 Revised): review and plan

The owner shared **"Baseline Rules & Functional Specifications (V2 Revised)"**
on 19 November 2026, together with the plan document **"Wiifarm Coop Binary
System"** (the compensation plan PDF it refers to for the matching rates,
recognition awards and the unilevel table). This page compares them with
what is built, lists the changes, and asks what the rules leave open.
Nothing below is built yet except the 15-generation unilevel (notes step 31).

## Already the same as what is built

| Rule | Baseline | Built |
| --- | --- | --- |
| Product and PV | ₦1,500 a piece = 1.5 PV; a pack is 10 pieces (₦15,000, 15 PV); payout at ₦600 a PV | Same |
| Packages | Pre Starter 15 PV (1 pack) to Mega 450 PV (30 packs) | Same |
| Split of every piece | 50% (₦750) to the company, 50% to Pool A, C or E | Same (₦750 to the manufacturing company) |
| Pool A top | Strategic Reserve 1%, Contingency 2.3%, Executive Leaders 10%, net 86.7% (₦650.25) | Same |
| Direct bonus | 25% of PV × ₦600, at once, 34.62% of the net | Same |
| Key-in bonus | 2% of PV × ₦600 to the account that processed it; both direct and key-in if that is the sponsor | Same |
| Matching | 50 PV + 50 PV, ₦30,000 a match, the package's rate and daily cap, 16.61% of the net | Same |
| Leadership line | 9.5% of the net, 5% of the team's monthly matching | Same line (who qualifies differs, below) |
| Stockist rebate | ₦120 a piece (Mega), ₦90 (Sub); 18.5% of the net on registrations, 16% of Pool C on repurchases | Same |
| UCB | 5%, 2%, 1% of an award to the first three uplines; 30% of Pool B | Same line (the awards themselves are not built yet) |
| Pool C | Unilevel 49%, repurchase awards 10%, Sub-leaders 10% (held until batch payouts), stockist rebate 16%, 15% buffer | Same |
| Pool D | ₦3M store: ₦240,000 (₦50k, ₦30k, ₦20k × 8); ₦10M store: ₦650,000 (₦150k, ₦100k, ₦50k × 8); nothing past the 10th generation | Same |
| Store PV | 3,000 / 10,000 PV for recognition awards only, generations 1 to 10 | Same (also 10 levels up the tree, as the board decided) |
| Pool E | Strategic Reserve 1%, Executive Leaders 10%, 52.5% for upgrade bonuses (25%, 2%, 1% × 8 of the PV difference), the rest kept as a buffer | Same; the example (Pre Starter to Mega: ₦65,250, ₦5,220, ₦2,610) matches |
| Unilevel depth | Up to 15 generations by package | Done on 19 November (step 31) |

## Changes the baseline asks for

1. **Indirect bonus line: 17.99% of the net** (₦117.00 a piece), not 18.00%.
   The 0.01% goes to Pool A's contingency fund. This is a new version (2) of
   the allocation schedule, from the day it is made; past splits keep
   version 1.
2. **Monthly requirement: 10.5 PV** (₦10,500) of personal repurchases each
   calendar month, as a setting. Today it is fixed at 10 PV.
3. **Inactive members' matching is frozen.** A member who has not met the
   monthly requirement is not matched; their volume waits, untouched, until
   they qualify again. Today every member is matched, and the autoship hold
   takes 20% instead.
4. **Leadership bonus: Executive Ambassador rank (480,000 PV).** Today it
   qualifies on 480,000 PV of repurchases (Blueprint V3). Under the baseline
   it follows the recognition rank, which needs the awards built first.
5. **Unilevel paid in real time on every repurchase,** not once a month.
6. **The repurchase award schedule** (this answers decision 21), on the
   cumulative repurchase PV of a member's 1st to 10th generations. Each
   tier pays only the difference from the last one paid:

   | PV | 5,000 | 10,000 | 20,000 | 35,000 | 50,000 | 65,000 | 80,000 | 90,000 | 95,000 | 100,000 |
   | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
   | Award (cumulative) | ₦250k | ₦500k | ₦1M | ₦1.75M | ₦2.5M | ₦3.25M | ₦4M | ₦4.5M | ₦4.75M | ₦5M |
   | Paid at this tier | ₦250k | ₦250k | ₦500k | ₦750k | ₦750k | ₦750k | ₦750k | ₦500k | ₦250k | ₦250k |

7. **Recognition award thresholds** from the plan PDF. Five differ from
   specification 5.8: Diamond 28,000 (was 25,000), Executive Diamond 40,000
   (35,000), Platinum 85,000 (80,000), Ambassador 350,000 (300,000),
   Executive Ambassador 480,000 (450,000). The cash and gifts, and the
   package and left/right rank conditions, are unchanged.
8. **Stores: "100% product value provided to service the center."** A
   ₦3M store would receive ₦3M of products (2,000 pieces), a ₦10M store
   ₦10M (6,667 pieces). Today the deposit is split (8% or 6.5% to Pool D,
   the rest to the manufacturing company) and the manufacturing company
   allocates stock by hand.

## Kept as the board decided, though the baseline words it differently

- **"Company breakage."** The baseline sends a missing sponsor's share, and
  the key-in share of an admin registration, to "company breakage". The
  board decided in November that unpaid shares stay with the cooperative in
  each ledger's contingency fund. That is kept.
- **Key-in on a self sign-up.** The baseline pays the account that processed
  the registration. On a self sign-up that is the new member. The system
  pays no key-in then (it goes to the contingency fund), as before.

## Questions

1. **Which month counts for the 10.5 PV rule?** Checked against the current
   calendar month, every member is frozen from the 1st until they buy.
   Options:
   - (a) the current month only;
   - (b) a purchase keeps a member active for the month it is made and the
     next.

   And does a member's registration count for the month they join (otherwise
   new members are frozen until they also buy 10.5 PV)?
2. **The autoship 20% hold** is not in the baseline. Is it dropped now that
   inactive volume is frozen instead (decision 7)?
3. **Unilevel in real time:** must the upline themselves have met the 10.5 PV
   rule that month to receive a share at the moment of the purchase
   (decision 4)?
4. **Store stock:** should activating a store give it products worth the
   whole deposit automatically? If so, the 8% / 6.5% sponsor bonus is paid
   on top of the full product value. Who funds it: the manufacturing company
   out of its share, or the cooperative (decision 27)?
5. **Leadership:** must a leader have actually achieved the Executive
   Ambassador rank (Mega package, an Ambassador on each side, 480,000 PV on
   the lesser leg), or is 480,000 PV on the lesser leg enough? The team is
   still decision 3.

## Plan

| Step | What | Waits for |
| --- | --- | --- |
| 1 | Schedule version 2 (indirect 17.99%); the 10.5 PV setting | Done (notes step 33) |
| 2 | Frozen matching for inactive members; the autoship hold switched off or removed | questions 1 and 2 |
| 3 | Unilevel in real time on each repurchase | question 3 |
| 4 | Awards: rank detection with the PDF's thresholds and conditions, admin approval, cash or gift, UCB; repurchase awards with the incremental rule; both held when their pools are short | nothing |
| 5 | Leadership qualified by rank | step 4, question 5 |
| 6 | Store opening stock | question 4 |
