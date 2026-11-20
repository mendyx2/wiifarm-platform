# Wiifarm Platform Specification (Phase 1)

Nov 6, 2026 · @wendy

What the new Wiifarm platform must do, in plain words, so it can be built from scratch with no ViserLab code. Its two authorities are Wiifarm's **Proposed Business Compensation Plan** (the plan) and the **Compensation Engineering Blueprint v1.0** (the blueprint). Where the current system differs from them, the plan wins and the difference is noted. Nothing is taken from the current system's source code. Every rule here is Wiifarm's; where the board has not decided yet, the section says so.

## 1. Conventions and glossary

**Rules every module follows:**

- **Money** is in naira (₦), stored to 2 decimal places (kobo). Every amount is rounded to the kobo once, at the point it is paid.
- **The ledger is the truth.** No balance is ever typed in or edited. Every credit or debit is an entry with an amount, a wallet, a reason and a reference, and a balance is the sum of its entries.
- **Once only.** Every payout records what triggered it (a registration, a match run, an award, a store activation). The same trigger can never pay twice, even if a job runs again.
- **All or nothing.** A payout that touches several members either completes for all of them or for none.
- **Logged and permitted.** Every admin action that moves money needs a named permission and is written to the audit log.
- **Configurable, not hard-coded.** Rates, caps and amounts in this document are settings an authorised admin can change. Each change is logged and applies from that moment on, never backwards.

**Glossary:**

| Term | Meaning |
| --- | --- |
| PV (point value) | The plan's unit of volume. **1 PV = ₦600.** Packages, products and stores carry PV. |
| Sponsor | The member who referred someone. Sponsors form the **referral chain**, used for direct, indirect and generation bonuses. |
| Parent | The member someone sits directly under in the **binary tree**. It can differ from the sponsor (spillover). |
| Leg | The left or right side under a member in the binary tree. |
| Generation | A step up the referral chain: generation 1 is the sponsor, generation 2 the sponsor's sponsor, and so on. |
| Level | A step up or down the binary tree (used for PV and leadership). |
| Match | 50 PV on the left leg paired with 50 PV on the right leg. |
| Pairing PV | PV still waiting to be matched. Matches use it up. |
| Lifetime PV | All PV a leg has ever received. Used for awards; never used up. |
| Company account | Member #1, the cooperative's own member account. It sponsors anyone who signs up without a referral. |
| Manufacturing company | A company that makes the products. A separate company from the cooperative, with its own accounts. Today there is one manufacturing partner; more can be added later, each with its own products and account. |
| Cooperative | Wiifarm Cooperative, which runs the network and its pools. A separate company from the manufacturer, with its own accounts. |

## 2. Members and accounts

A person becomes a member when their package is paid. Until then they hold a pending account that earns nothing and appears nowhere in the tree.

**Ways to join:**

1. **Self sign-up.** The person fills in the form, chooses a package, and pays by Paystack or with an e-pin. Placement and bonuses happen at the moment payment is confirmed.
2. **Key-in by a member ("Add Distributor").** A member registers someone and pays the package from their own VISA wallet. The member doing the key-in earns the key-in bonus (section 5). The member fills in the person's details, including the password they will sign in with. Creating the account, taking the price from the VISA wallet, and placing and paying for the new member happen together, or not at all.

**Sign-up details:**

| Field | Rule |
| --- | --- |
| First and last name | Required |
| Username | Required, unique, at least 6 characters, letters, digits and underscore only |
| Email | Required, valid |
| Mobile | Required, with country dialling code |
| Country, state, city, address | Required for key-in; country required for self sign-up |
| Password | Required, typed twice; an admin setting can demand a strong password |
| Sponsor (referral username) | Optional for self sign-up (blank → company account); required for key-in |
| Parent and leg | Optional. If given, the parent must be in the sponsor's own tree and the leg must be free when chosen |
| Package | Required |
| Terms | Must be accepted (admin setting) |

**After joining:**

- **Login** by username or email and password. Two-factor sign-in with an authenticator app is optional per member.
- **Email and phone verification** can be switched on by the admin. When on, a member must verify before using the dashboard.
- **KYC.** A member submits the fields the admin's KYC form asks for (for example NIN and an ID card image). The states are unverified, pending and verified. An admin approves or rejects with a reason, and the member can resubmit. KYC files are private and only shown to signed-in admins and their owner.
- **Profile.** Members can change contact details, bank details and password. Changes to bank details are kept in a history.
- **Statuses.** Active or banned (with a reason). A member's withdrawals can be blocked separately, with a reason.
- **Sponsor change.** Only an admin can change a member's sponsor. Each change is logged with the old and new sponsor.

## 3. Binary network and PV

Every member has one place in a binary tree: one parent, and at most one child on the left and one on the right. The company account is the top of the tree.

&#91;embedded content: placement · 2 decisions, 3 outcomes\]

**Placement rules:**

- "Level by level, left first" means the search checks the members directly below, then the next level down, and takes the first free slot it finds, left before right.
- A chosen parent must sit inside the sponsor's own tree. Placing someone into another sponsor's tree is refused.
- **A slot is claimed in one step that succeeds only if the slot is still free.** Two members can never get the same slot. A member who loses that race is placed again by the same rules.
- Placement never fails silently. If no slot can be found, the registration stops with a clear error and nothing is paid.

**How PV climbs:**

- When a member gains PV, every upline in the tree receives it, all the way to the top, on the leg the member sits under.
- Each upline's leg gets the PV twice: once as **lifetime PV** (for awards) and once as **pairing PV** (for matching).
- The leg is taken from the parent's own record of its left and right child. If a parent does not name the member on either leg, that upline gets nothing, the problem is logged for an admin, and the climb goes on above it.
- A loop in the tree (a member above themselves) ends the climb and is logged.
- Every PV credit is recorded with the member, the leg, the amount and its source.

**Sources of PV:**

| Source | PV that climbs | Counts for |
| --- | --- | --- |
| Registration | The package's PV | Matching and awards |
| Package upgrade | The difference between the new and old package's PV | Matching and awards |
| Product purchase | The product's PV × quantity | Matching and awards |
| Stockist store activation | The store's PV (section 9) | **Awards only.** The plan says store PV "will only be counted for the award", so it goes to lifetime PV and never to pairing PV |

**Repurchase PV** is a separate counter, used for the unilevel bonus (5.10) and repurchase awards (5.11). It is the PV of products a member buys after joining, including their monthly purchase (10.5 PV, an admin setting; Revised Baseline Rules). Repurchase awards count the repurchase PV of a member's **1st to 10th generation** downline (the referral chain). The current system instead shares it with up to 50 tree uplines, and that is replaced.

## 4. Packages

Every member holds one package. Its price is its PV × ₦1,000, while bonuses are worked out on PV × ₦600 (its "PV value", 60% of the price). It comes with a number of product packs. The package decides the direct bonus, the matching rate, the daily matching cap and how many unilevel generations the member is paid on. To stay active, a member makes a **monthly purchase of 10.5 PV** (₦10,500; Revised Baseline Rules, 19 November 2026). The amount is an admin setting.

| Package | Price | PV | Product packs | Direct bonus (25% of PV value) | Paid per match | Daily cap (matches) | Daily cap (₦) | Unilevel generations |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Pre Starter | ₦15,000 | 15 | 1 | ₦2,250 | ₦900 (3%) | 5 | ₦4,500 | 1–4 |
| Starter | ₦30,000 | 30 | 2 | ₦4,500 | ₦1,500 (5%) | 7 | ₦10,500 | 1–6 |
| Builder | ₦60,000 | 60 | 4 | ₦9,000 | ₦2,100 (7%) | 10 | ₦21,000 | 1–10 |
| Booster | ₦105,000 | 105 | 7 | ₦15,750 | ₦2,400 (8%) | 30 | ₦72,000 | 1–10 |
| Executive | ₦150,000 | 150 | 10 | ₦22,500 | ₦2,700 (9%) | 60 | ₦162,000 | 1–12 |
| Advance | ₦255,000 | 255 | 17 | ₦38,250 | ₦3,000 (10%) | 90 | ₦270,000 | 1–13 |
| Mega | ₦450,000 | 450 | 30 | ₦67,500 | ₦3,600 (12%) | 120 | ₦432,000 | 1–15 |

The unilevel generations follow the plan's unilevel table, up to the 15th (owner, 19 November 2026, from the plan document "Wiifarm Coop Binary System"). The indirect referral bonus, the upgrade bonus, store PV and the stockist sponsor bonus stay at 10 generations.

"Paid per match" is a percentage of one match's value: 50 PV × ₦600 = ₦30,000.

**Upgrades.** A member can move to a higher package by paying the difference in price. The PV difference climbs the tree (section 3) and pays the upgrade bonuses (section 5). From then on, the new package's rates and caps apply. Downgrades are not allowed, and an admin can mark a package as "no upgrades".

## 5. Compensation

The plan lists thirteen ways of earning. They are retail profit, the key-in, direct, indirect, matching and upgrade bonuses, binary awards, UCB, the leadership bonus, the unilevel bonus, repurchase awards, the stockist bonus and the stockist rebate. Autoship comes from the blueprint and the current system. There is no Global Share Pool (board, November 2026). Each payout goes into its own bonus wallet (section 6) and is charged to its budget line (section 7). **Every bonus is only paid to active members.** A banned member's share is not paid, it does not pass to anyone else, and the generation still counts. Every share that is not paid, for whatever reason, goes to that ledger's **Unclaimed Bonus Reserve** with a record of where it came from (section 7).

### 5.1 Direct bonus

- **When:** a member's registration is paid.
- **Who:** their sponsor.
- **Amount:** the package's direct bonus (25% of its PV value, section 4).
- **Example:** a Mega sign-up pays the sponsor ₦67,500.

### 5.2 Indirect bonus (10 generations)

- **When:** a registration is paid.
- **Who:** the 10 generations of sponsors above the direct sponsor. Generation 1 here is the sponsor's sponsor.
- **Amount:** a share of the package's PV value. Generation 1 gets 3%, generation 2 gets 2%, and generations 3 to 10 get 1% each: 13% in total.
- **Example:** a Mega sign-up (450 PV = ₦270,000) pays ₦8,100, then ₦5,400, then ₦2,700 to each of the next eight. That is ₦35,100 in all.

### 5.3 Key-in bonus

- **When:** a member registers someone through "Add Distributor".
- **Who:** the member who keyed them in.
- **Amount:** 2% of the package's PV value.
- **Example:** keying in a Mega member pays ₦5,400.

### 5.4 Upgrade bonuses

- **When:** a member upgrades their package.
- **Base:** the PV difference × ₦600.
- **Who and how much:** the sponsor gets 25% of the base. Generation 2 gets 2%, and generations 3 to 10 get 1% each.
- **Example:** Starter to Builder is 30 PV, or ₦18,000. The sponsor gets ₦4,500, generation 2 gets ₦360, and each of the next eight gets ₦180.

### 5.5 Matching bonus (binary)

- **When:** once a day, at the scheduled run (section 12).
- **Matches:** one match needs 50 pairing PV on **both** legs. The number of matches is the smaller leg's pairing PV ÷ 50, rounded down.
- **Daily cap:** the package's daily cap, minus any matches already paid that day.
- **Funded by:** the registration ledger's matching line, 16.61% of the net (₦108.01 a unit, section 7).
- **Amount:** the matches paid × the package's "paid per match".
- **PV used:** 50 PV per match is taken from each leg. **Everything left over carries forward** to the next days. Nothing is ever wiped.
- **No sponsor is needed** on either leg. Only PV counts.
- **Banned members:** they are not paid. Their pairing PV is left as it is.
- **Autoship hold:** if the member has not yet made this month's autoship (5.6), 80% goes to the matching wallet and 20% is held until they do.
- **The run:** at 01:30 Lagos time it closes the day just ended, using the pairing PV each member holds at that moment (PV that arrives between midnight and the run counts that night). If a night is missed, the next run first closes the missed days one by one, each with its own cap, so nobody loses a day. A member is matched once per day, so a run started again pays nobody twice.
- **When the line is short:** covered from Pool A's contingency fund; if that is short too, the payout is held and paid in full when money arrives, oldest first (section 7). The PV is used on the night either way: the payout is owed.
- **The company account** earns no matching bonus; its PV stays where it is.
- **Example:** a Mega member with 1,000 left and 400 right has 8 possible matches, under the cap of 120. They earn 8 × ₦3,600 = ₦28,800: ₦23,040 for matching and ₦5,760 to autoship. 600 PV carries forward on the left and 0 on the right.

### 5.6 Autoship

The owner settled this after speaking with a leader (19 November 2026): the monthly purchase keeps members active on product purchases, and it is a check for fulfilment, never a freeze. Every member is matched as usual.

- **Rule:** a member makes the month's autoship by buying the monthly purchase (10.5 PV, section 4) of products for that calendar month.
- **Until then:** 20% of each matching bonus they earn that month is held in their autoship wallet ("Matching held until autoship"). It cannot be spent or withdrawn.
- **Released:** as soon as the month's autoship is made, the month's held amount moves to their matching bonus wallet. It is checked when they buy, and every night.
- **The first week:** "the system calls for the previous month's autoship in the first week of the current month". A month's autoship can still be made in the first 7 days of the next month. A purchase in those days goes first to the previous month, if it was short and the member already belonged that month; only the rest counts for the new month. One purchase never covers two months.
- **The month a member joins counts** (owner, 20 November 2026): a new member must make the autoship for the month they joined, by the 7th of the next month. Their registration is not an autoship.
- **Missed:** a month whose first week has passed without its autoship is missed. Its held amount **stays held** (owner, 20 November 2026). It is released when the member makes a later month's autoship (decision 36).
- **Flushed after 3 missed months:** when a member misses 3 months in a row, the held amount of each of those months is moved to the registration ledger's (Pool A's) **Unclaimed Bonus Reserve** (section 7), where the matching money came from. A further month missed in the same run is flushed too, once its first week has passed. Each flush is recorded in the reserve's report against the member and the month.
- **Reminders** (owner, 20 November 2026): from the 4th week of a month (the 22nd) to the end of the next month's first week (the 7th), a member who has not made that month's autoship is reminded:
  - on the dashboard, every day of that window, with what they have bought and the deadline;
  - by email, three times, once each: on the 22nd, on the 1st of the next month and on the 7th (decision 36).
  - The dashboard also warns a member who has missed months in a row.
- **Records:** every release and flush is recorded, once, with the month it belongs to, and every reminder sent. A held share paid later, after its pool refilled, is settled the same way.

### 5.7 Leadership bonus

- **Who qualifies:** a member with **480,000 PV of lifetime PV on their lesser leg** by the end of the month, the Executive Ambassador level (the owner, after speaking with a leader, 19 November 2026). The other leg can hold any amount: 1,000,000 PV on the right and 479,999 on the left does not qualify.
- **Funded by:** the registration ledger's leadership line, 9.5% of the net (₦61.77 a unit, section 7).
- **Amount:** **5%** of what their team earns from the matching bonus each month.
- **Paid:** monthly, for the month just ended. Each month is paid once.
- **Open:** whether "team" means the whole downline or a set number of levels (section 13).
- **Replaced:** the current system pays 4% to anyone with 1,000 PV on each leg, up to 5 levels above each matching payout. That is replaced by the plan's rule.
- **How it is run** (built November 2026):
  - On the first of each month (02:30 Lagos time), every member with 480,000 PV on their repurchase PV log by the end of the month just ended earns 5% of what their team earned from matching on days in that month. A missed month is caught up by the next run, and a month is never paid twice.
  - Until the board decides (decision 3), who the "team" is, is an admin setting: everyone below the leader in the binary tree (the default), or everyone down their sponsor chain, to a set number of levels or generations, or with no limit. Each month's run records the rule it used.
  - A leader inside another leader's team still counts in that team, so both leaders earn on the same matching.
  - When the leadership line and Pool A's contingency fund are short, the payout is held until they refill (section 7).

### 5.8 Binary awards (recognition ranks)

- **Rule:** a one-time award when the **lesser leg's lifetime PV** reaches the rank's level. Stockist store PV counts here (section 3).
- **Package conditions:** Emerald needs the Builder package or higher, Silver needs Booster, and Jade needs Executive.
- **From Gold upwards:** the member must hold the Mega package **and** have produced the rank just below on both their left and right genealogy. For example, Gold needs a Jade on the left and a Jade on the right.
- **Paying it:** an admin approves each award. The member takes the cash or, where offered, the gift.
- **Thresholds** are those of the plan document "Wiifarm Coop Binary System" (owner, 19 November 2026); five were raised from the earlier table (Diamond, Executive Diamond, Platinum, Ambassador, Executive Ambassador).
- **How it is run** (built November 2026):
  - A check every night (02:00 Lagos time) records each rank a member has newly qualified for, as waiting for approval. "Produced the rank below" means someone on that side holds that rank or a higher one, from an award not refused. A member who qualifies for several ranks at once gets each of them.
  - While it waits, the member chooses the cash or the gift on their Awards page. Approving pays the cash into their awards wallet from the Strategic Reserve's recognition line, or, for a gift, moves the cash value from that line to the cooperative's "award gifts to buy" account (decision 34). An admin marks the gift delivered.
  - What the line and Pool B's contingency fund cannot cover is held until they refill, oldest first (section 7). An admin can refuse an award with a reason the member sees.

| Rank | PV on lesser leg | Cash award | Or gift |
| --- | --- | --- | --- |
| Emerald | 1,500 | ₦80,000 | Cash only |
| Silver | 3,000 | ₦150,000 | Cash only |
| Jade | 6,000 | ₦300,000 | Cash only |
| Gold | 12,500 | ₦550,000 | Laptop |
| Diamond | 28,000 | ₦1,200,000 | Smart TV |
| Executive Diamond | 40,000 | ₦1,500,000 | Solar freezer and washing machine |
| Platinum | 85,000 | ₦4,000,000 | Complete solar system |
| Premium Platinum | 150,000 | ₦10,000,000 | Small car |
| Ambassador | 350,000 | ₦16,000,000 | International trip |
| Executive Ambassador | 480,000 | ₦25,000,000 | Big car |
| Sapphire | 800,000 | ₦40,000,000 | Land |
| Executive Sapphire | 2,500,000 | ₦100,000,000 | Small bungalow |
| Global Mega | 5,000,000 | ₦180,000,000 | Duplex |

This is the ladder the current system already holds, so the other ladder in Wiifarm's remediation notes (Sapphire Leader to Global Presidential) is dropped.

### 5.9 Upline Celebrated Bonus (UCB)

- **When:** a member's recognition award (5.8) is approved. The Revised Baseline Rules tie it to the recognition award only, not to repurchase awards.
- **Who and how much:** the first upline (sponsor) gets 5% of the award's cash value, the second 2%, and the third 1%, from the Strategic Reserve's UCB line (30% of Pool B). It is paid whether the member takes the cash or the gift.
- **Not paid** to a banned member, a member whose package is not paid or the company account; their share goes to Pool B's Unclaimed Bonus Reserve (section 7).

### 5.10 Unilevel bonus (15 generations)

- **When:** at once, on every product repurchase by a member of the downline, including each one's monthly purchase (Revised Baseline Rules: "calculated in real-time on every member repurchase").
- **Who:** up the referral chain. Each member is paid only on the generations their package unlocks (section 4).
- **Rates (share of the PV value):**

| Generation | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Rate | 7% | 5% | 4% | 4% | 3% | 3% | 3% | 2% | 2% | 2% | 1% | 1% | 1% | 1% | 1% |

40% in all, from the plan's unilevel table (owner, 19 November 2026). Each package reaches the generations in section 4: Pre Starter 4, Starter 6, Builder and Booster 10, Executive 12, Advance 13, Mega 15.
- **Funded by:** the repurchase ledger's unilevel line (₦367.50 a unit, section 7). A unit pays at most ₦360 when all 15 uplines qualify, so the line covers it.

- **Example:** one 1st-generation member's 10 PV authorship (₦6,000) pays ₦420 (7%); the plan's own table shows 3 members' 10 PV paying ₦1,260.
- **How it is run** (built November 2026):
  - The moment a member's repurchase is paid, each of the 15 generations above them is paid their share of its PV value into their unilevel wallet, once per order.
  - A share is not paid to a banned member, a member whose package is not paid, the company account or a member whose package does not reach that generation. Such a share is not passed on, and the generation still counts; it goes to Pool C's Unclaimed Bonus Reserve (section 7).
  - An upline does not need to have made their own monthly purchase to earn unilevel: the monthly purchase decides the autoship hold on matching (5.6), and the baseline bases unilevel on "the upline's active package" (decision 4).
  - When the line and the contingency fund are short, a share is held until they refill (section 7).
- **Replaced:** the current system uses different rates (6%, 5%, 4%, 3%, 2%, then 1% and 0.5%) and different package depths. Both are replaced by the plan's.

### 5.11 Repurchase awards

- **Rule:** cash awards on the cumulative repurchase PV of a member's **1st to 10th generation** downline (section 3), from the repurchase ledger's award line (10% of Pool C).
- **Schedule** (Revised Baseline Rules and the plan document, 19 November 2026; this settles decision 21):

| Repurchase PV | 5,000 | 10,000 | 20,000 | 35,000 | 50,000 | 65,000 | 80,000 | 90,000 | 95,000 | 100,000 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Cumulative award (₦) | 250,000 | 500,000 | 1M | 1.75M | 2.5M | 3.25M | 4M | 4.5M | 4.75M | 5M |
| Paid at this tier (₦) | 250,000 | 250,000 | 500,000 | 750,000 | 750,000 | 750,000 | 750,000 | 500,000 | 250,000 | 250,000 |

- **Incremental payout:** reaching a higher tier pays the difference between that tier's cumulative award and the cumulative award of the last tier already awarded. A member who passes two tiers at once gets one award for the difference.
- **How it is run:** the same nightly check records it as waiting; an admin approves it (decision 34), and what the line and Pool C's contingency fund cannot cover is held until they refill.
- **Replaced:** the earlier table (750 PV for ₦50,000 up to 45,000 PV for ₦5M).

### 5.12 Retail profit and the earnings described elsewhere

- **Retail profit** is not a payout. Members buy products at member prices and can resell them for over 50% profit; the plan's example is ₦225,000 on a ₦450,000 Mega repurchase. The system only needs a member price and a retail price per product (section 8).
- The stockist rebate and the stockist sponsor bonus (Pool D) are in section 9.
- The affiliate bonus is in section 8.
- Cash back as a Welcome Back Package is in section 8. It is currently ₦0 on every package.

## 6. Wallets, ledger and payments

Each member has a main balance, four special-purpose wallets, and one wallet per kind of bonus. Stockists and affiliates each have **their own wallet**, separate from these (sections 8 and 9). Every movement in any of them is a ledger entry (section 1).

| Wallet | What goes in | What it can be used for |
| --- | --- | --- |
| Main balance | Deposits, bonus transfers, transfers from other members | Withdrawals, transfers, purchases |
| VISA wallet | Top-ups (see section 13) | Paying registrations (key-in) and e-pins |
| Product wallet | Product credit | Shop purchases |
| Autoship | The 20% autoship hold (5.6) | Product purchases only; swept at month end |
| Savings wallet | Savings deposits | Savings products (section 10) |
| Bonus wallets | One per bonus: direct, indirect, upgrade, matching, leadership, unilevel, key-in, awards, repurchase award, Upline Celebrated Bonus (UCB), stockist sponsor | Moved to the main balance ("Bonus Transfer") |
| Stockist wallet | Rebates for units handed over | Withdrawals |
| Affiliate wallet | Commissions on public-shop sales | Withdrawals |

**Deposits:**

- **Paystack:** the member pays online, and the deposit is credited only when Paystack confirms it. The confirmation comes server-to-server and is checked; a returning browser is never trusted on its own.
  - Paystack pays for three things: a new member's package, an upgrade (the difference in price) and a deposit to the main balance (₦1,000 to ₦5,000,000).
  - Paystack's signed message (its webhook) confirms a payment; so does our server asking Paystack, when the member comes back and every five minutes for payments still waiting, for 48 hours. Only exactly the amount asked for, in naira, is credited; anything else is held for an admin.
  - If what was paid for can no longer happen (the member was activated some other way, or their package changed before the upgrade was confirmed), the money goes to their main balance and the payment says why.
  - The Paystack secret key is kept in the server's settings, not on an admin screen, so it never passes through the admin panel.
- **USDT (TRC20):** Wiifarm uses one wallet address. The member sends the money and enters the transaction ID. The system checks it on the Tron network (amount, address, confirmations) and credits once. A scheduled scan also finds transfers that nobody reported. A transaction ID can never be credited twice.
- **Manual methods** (for example a bank transfer): the member fills in the method's form and uploads proof. An admin approves or rejects with a reason. Proof files are private.
- A deposit is not a sale: it goes to the main balance and is split only when it is spent on a package, an upgrade or products, by the ledger of that purchase (section 7).

**Withdrawals:**

- Each method has a minimum, a maximum, a fixed charge, a percentage charge, a rate and its own form (for example bank name, account number, account name).
- On request, the amount leaves the main balance at once and the request waits for an admin.
- An admin approves it (paid) or rejects it with a reason. On rejection the full amount, including the charge, goes back to the main balance.
- Members whose withdrawals are blocked cannot request one, and see the reason.

**Transfers:**

- **Balance transfer** moves money between members, with a fixed and a percentage charge set by the admin.
- **Bonus transfer** moves money from a bonus wallet to the member's own main balance.

**E-pins:** a pin is a code worth a fixed amount, bought from the VISA wallet or created by an admin. A pin pays one registration, once. Pins show who created them, who used them, and when.

- A member buys pins from their VISA wallet, up to 50 at a time, each worth one package's price. The money waits in an "e-pins issued" account until a pin is used.
- An admin creates pins with a reason, recorded in the audit log.
- A person who has signed up enters the code on their "My package" page. A pin pays a package costing up to its value; anything over goes to the new member's main balance (decision 32).
- An admin can cancel an unused pin. A bought pin's value goes back to the buyer's VISA wallet.
- Codes look like pickup codes (for example K7QM-3XPA-R9TD) and are stored encrypted. Only the buyer, and admins allowed to manage pins, see an unused code.

## 7. Pools and governance

The board's **Allocation Blueprint V3** (`docs/board/allocation-blueprint-v3.xlsx`, November 2026) governs this section. Where a note in the workbook disagrees with its percentage column, the percentage column is followed. **Every payout must be traceable to the purchase that funded it**: each naira is tagged with the kind of purchase that brought it in, kept in that purchase's own ledger, and every bonus is charged to its own line.

**Two separate companies.** The manufacturing company and the cooperative are different companies with **different accounts**, and the platform never mixes them. Today there is one manufacturing partner. Each product names its manufacturer, so when more partners join with their own products, each product's manufacturer share goes to that partner's own account. Each payment is split between them the moment it is confirmed, and each has its own books: what it received, what it paid and what it holds.

**The split of every member purchase** (₦1,500 a unit; 10 units = one ₦15,000 pack = 15 PV, so ₦1,000 of price = 1 PV):

| Share | Per unit | Where it goes |
| --- | --- | --- |
| Manufacturing company | ₦750 (50%) | The manufacturing company's account |
| Cooperative | ₦750 (50%) | The cooperative's ledger for that kind of purchase, below |

**The ledgers.** Each has its own balance and statement. Money moves between ledgers only as recorded transfers, never by editing a balance.

| Ledger | Funded by | Taken off the top (of ₦750) | Shared out (of what is left) |
| --- | --- | --- | --- |
| **Registration (Pool A)** | First purchase: a new member's package | Strategic Reserve 1% (₦7.50), Contingency Reserve 2.3% (₦17.25), Executive Leaders 10% (₦75) | ₦650.25 (86.7%): direct 34.62% (₦225.12), indirect 17.99% (₦116.98; allocation schedule version 2, Revised Baseline Rules, 19 November 2026: the 0.01% left goes to the contingency fund, and the contingency fund covers the 2 kobo a piece the line is short of the ₦117 the bonus can pay), key-in 2.77% (₦18.01), binary matching 16.61% (₦108.01), leadership 9.5% (₦61.77), stockist rebate 18.5% (₦120.30). Adds up to 100%. |
| **Repurchase (Pool C, Commerce)** | Members' repurchases | Nothing: the full ₦750 | Unilevel 49% (₦367.50), repurchase awards 10% (₦75), Sub-leaders 10% (₦75), stockist rebate 16% (₦120). The other 15% (₦112.50) goes to its contingency fund. |
| **Upgrade (Pool E)** | The price difference of an upgrade | Strategic Reserve 1% (₦7.50), Executive Leaders 10% (₦75) | ₦667.50: upgrade bonuses 52.5% (₦350.44). The other ₦317.06 goes to its contingency fund. |
| **Stockist activation (Pool D)** | A store's activation deposit | | Sub store (₦3M): 8% = ₦240,000. Mega store (₦10M): 6.5% = ₦650,000. Paid to 10 sponsor generations (section 9). |
| **Strategic Reserve (Pool B)** | 1% of Pools A and E | | Recognition (rank) awards 70% (₦5.25 a unit), Upline Celebrated Bonus 30% (₦2.25 a unit) |
| **Executive Leaders** | 10% of Pools A and E | | Held until the board sets its payout rules |
| **Contingency fund** | 2.3% of Pool A, plus every reserve and designed leftover of each ledger | | Kept per ledger; covers only that ledger's shortfalls (rules below) |
| **Unclaimed Bonus Reserve** | Every bonus share that could not be paid | | Kept per ledger, apart from the contingency fund, with the origin of every inflow (rules below) |
| **Sub-leaders** | 10% of Pool C | | Held and paid in batches to qualified Sub-leaders; ranks and targets to be set |

**Rules:**

- **Splitting:** each confirmed payment is split into these lines as ledger entries that add up to the payment exactly. Per-unit amounts such as ₦225.11655 are not whole kobo, so the split is worked out on the whole payment and the rounding remainder goes to that ledger's safety net.
- **Charging:** every bonus is a debit from its own line, naming the member and the purchase that funded it. The plan's formulas decide what is paid (for example, the direct bonus is 25% × PV × ₦600 = ₦225 a unit). The line holds slightly more, and the difference stays in the ledger.
- **Contingency fund** (board, November 2026): every reserve and every leftover goes into a contingency fund, which is the safety net. That covers the 2.3% Contingency Reserve, each ledger's unassigned share (repurchase 15%, upgrade ₦317.06 a unit), what a line holds above what the plan pays (for example ₦0.12 a unit on the direct line), and a Sub store's ₦30 below the ₦120 rebate line. It is kept and reported **per ledger**, so it is always clear where each naira came from.
- **Self-sufficient ledgers:** no ledger ever pays for another ledger's bonuses. When a line in a ledger runs short, it is covered first from **that ledger's own** contingency fund.
- **Shortfalls: hold until it refills** (board, November 2026). A shortfall is never hidden. Each use of a contingency fund is recorded against the line it covered. If a line and its ledger's contingency fund cannot cover a payout, the payout is **held**: the member sees it as pending, nothing is paid in part, and it is paid as soon as the money arrives. Held payouts are paid oldest first. A payout is never cancelled because a line ran short.
- **Unpaid shares** (board, November 2026): a share with no one to receive it stays with **the cooperative**, never the manufacturing company.
- **Unclaimed Bonus Reserve** (owner, 20 November 2026): unpaid shares are **not** mixed with the contingency fund. Each ledger has its own Unclaimed Bonus Reserve, and every share a bonus could not pay goes there. For example, when a sign-up's sponsor chain is only 5 generations deep, the indirect bonus for generations 6 to 10 (5% of the package's PV value) goes to Pool A's reserve. It covers:
  - a generation beyond the top of the tree (no upline);
  - the company account, a banned member or a member whose package is not paid;
  - a generation the upline's package does not reach (unilevel);
  - a key-in with no one to pay (a self sign-up or an admin registration).

  It applies to the direct, indirect, key-in, upgrade, unilevel, stockist sponsor and UCB bonuses, and to the autoship hold that is flushed (5.6). Every inflow is recorded with the bonus, the generation, the reason, the member who would have received it and the member whose sign-up, upgrade, purchase, store or award it came from. The admin report "Unclaimed bonuses" shows the totals by ledger, bonus and reason, and every inflow. The reserve does not cover shortfalls: only the line and the contingency fund do (decision 35).
- **Reporting:** the admin dashboard and the financial report show each ledger and line (what came in, what was paid, what is left) by day, month and quarter.

**What the blueprint replaces:** the earlier 30 / 10 / 5 / 55 split and its 22% / 24.75% / 8.25% pools are replaced by the separate ledgers above. **There is no Global Share Pool** (board, November 2026).

## 8. Shop and affiliate

The shop sells Wiifarm's products to members and to the public. **Everyone pays through the platform; nobody pays a stockist.** Each payment is split at once between the manufacturing company and the cooperative (section 7). Every order gets a pickup code, and the buyer collects the goods from a stockist with it.

**Products:**

- **Details:** name, manufacturer, category, SKU, description, specifications, images, PV, stock quantity, and minimum and maximum order quantities.
- **Price per state:** each state can have its own price, with optional per-state bonuses for Mega and Basic stockists.
- **Wholesale:** cartons of 15 packs by default, with a carton price.
- **Affiliate bonus:** 20% of the margin between the public price and the member price (₦100 on a ₦2,000 unit), paid to the affiliate wallet (below).
- **Display:** a product can be featured or hidden.

**Prices:** each product has a member price and a suggested retail price, so members can see their retail profit (5.12).

**Registration packs:** each package comes with its number of product packs (section 4). A new member collects them from a stockist with the pickup code of their registration.

**Pickup codes** (board meeting, November 2026):

- One code per order, issued when the payment is confirmed.
- It can be used at **any** stockist, and it **never expires**.
- The whole order is collected at once; an order cannot be collected in parts.
- The stockist enters the code, sees the order details, and confirms the handover. The store's stock is then balanced automatically, and the stockist's rebate is paid (section 9). A code works once only.

**Member orders:**

- A member pays through the platform: from their main balance or product wallet, or online. They receive a pickup code.
- The product's PV climbs the tree (section 3), and the purchase pays the unilevel bonus (5.10).
- It also counts towards autoship (5.6) and repurchase PV.

**Public (affiliate) orders:**

1. An affiliate shares their personal link (for example `?ref=WII-XXXXXX`) or an advert from the Ad Center. **Anyone can register as an affiliate, member or not**; an affiliate has their own account and affiliate wallet. A click is remembered for a number of days set by the admin.
2. The buyer pays online at the public price of **₦2,000 a unit**, and receives a pickup code.
3. A stockist checks the code in person and hands over the goods. The order is then fulfilled.
4. On fulfilment, the referring member's affiliate bonus is paid, and the stockist earns their rebate.

**How a public sale is shared** (board, November 2026). The public shop exists to bring the products to the public, so its sales do not feed the cooperative's pools. Per ₦2,000 unit:

| Share | Through an affiliate's link | Straight from the landing page |
| --- | --- | --- |
| Affiliate wallet: 20% of the ₦500 above the member price | ₦100 | none |
| Stockist wallet: the usual rebate for the store that hands it over | ₦120 (Mega) / ₦90 (Sub) | ₦120 (Mega) / ₦90 (Sub) |
| Manufacturing company: everything else | ₦1,780 / ₦1,810 | ₦1,880 / ₦1,910 |

A public sale earns no PV for anyone and pays no network bonus.


5. An order not paid within the admin's set number of days is cancelled. A paid order's code never expires.

**How the public shop works now** (built November 2026):

- The shop is at `/products`, with no account needed. An affiliate's link (`/r/WII-XXXXXX`, or `/products?ref=WII-XXXXXX`) is remembered for 30 days, and the page says who recommended it.
- The buyer gives their name, phone and email (Paystack needs an email for its receipt), chooses quantities, and pays with Paystack.
- After paying, the buyer lands on their own private page for the order (a signed link nobody can guess). It shows the pickup code and the list of active stockists, with their addresses and phone numbers.
- An unpaid order is cancelled after 3 days, unless Paystack is still confirming its payment. A payment that arrives after that is still honoured.

**Order statuses:** pending, paid (awaiting pickup), fulfilled, cancelled.

**Ad Center:** admins publish adverts (image, caption, link). Members share them on WhatsApp, Facebook, X, Telegram, TikTok and Instagram with their own link attached. Clicks and orders are reported per advert.

**Welcome Back Package (cash back):** when a package has a cash-back amount, it is not paid as money. It becomes a package code for products of that value, which a stockist redeems in person.

## 9. Stockists

A stockist is a member who runs a store that holds Wiifarm stock and hands over orders by pickup code. Stockists never take buyers' money. Each stockist has a **stockist wallet**, separate from their member wallets. There are two kinds.

|  | Basic store | Mega store |
| --- | --- | --- |
| Setup payment | ₦3,000,000 | ₦10,000,000 |
| Products received to service the store | 100% of the setup value | 100% of the setup value |
| Store PV (for awards only) | 3,000 PV | 10,000 PV |
| Stockist bonus, total over 10 generations | ₦240,000 | ₦650,000 |
| Rebate per unit handed over | ₦90 | ₦120 |
| Receives stock from | Collects from its Mega store | The manufacturing company |

**Becoming a stockist:**

1. A member applies with their business details and state. A stockist sponsor can be named.
2. An admin reviews the application, takes payment and activates the store. The stockist receives products worth 100% of the setup payment.
3. On activation, in one step that is paid once only:
   - the **stockist sponsor bonus** (Pool D) goes to 10 generations of the stockist's referral chain (generation 1 is the stockist's sponsor, or their referrer if none was named). This is the only stockist sponsor bonus; there is no ongoing share of the store's sales;
   - the store PV counts for awards only, never for matching, and reaches 10 steps, never more (board, November 2026). It counts **both ways**: up 10 levels of the stockist's binary tree as lifetime PV (binary awards), and up 10 generations of the referral chain;
   - the deposit buys the store's products at their face value: like any sale, half goes to the manufacturing company (₦750 of every ₦1,500). The stockist sponsor bonus, 8% (Sub) or 6.5% (Mega) of the deposit, is paid from the other half, and the rest of that half stays with the cooperative in Pool D's contingency fund (the owner, after speaking with a leader, 19 November 2026).
4. A banned upline is skipped, and the generation still counts.

**Enrollment bonus by generation:**

| Generation | 1 | 2 | 3–10, each |
| --- | --- | --- | --- |
| Basic | ₦50,000 | ₦30,000 | ₦20,000 |
| Mega | ₦150,000 | ₦100,000 | ₦50,000 |

These figures match the current system. The other split in Wiifarm's remediation notes (Basic 1,000 PV, ₦120,000 to generation 1) is not in the plan and is dropped.

**Running the store:**

- **Stock flow:** manufacturers → Mega store → Sub store → buyer. **The manufacturing company allocates** the stock to the stores (board, November 2026); a Sub store collects from its Mega store. Every movement is recorded, and each store's stock is balanced automatically as codes are entered.
- **Handovers:** the stockist hands over registration packs, repurchases, public orders and Welcome Back Packages, in person, by pickup code (section 8).
- **Rebate:** on each unit handed over, a Mega store earns ₦120 and a Sub store ₦90, paid to the stockist wallet. Registration packs are paid from the registration ledger's stockist line (₦120.30 a unit); repurchases from the repurchase ledger's (₦120); upgrade packs from the upgrade pool's contingency fund; public orders from what the sale held back. Only the store that hands the unit over is paid. When a Sub store hands over a member's order, the ₦30 a unit it does not take moves to that pool's contingency fund; on a public order it goes back to the manufacturer.
- **Stock is counted in the ledger**, unit by unit, like money: a store can never hand over or pass on stock it does not have, and a handover that is short of stock changes nothing.
- **Stockist sponsor bonus:** paid once, at activation, from Pool D (above). The earlier 3% of the store's sales to its sponsor is replaced by it.
- **Status:** an admin can deactivate a store. A deactivated store cannot sell or redeem.

## 10. Investments, farm cycles, savings and loans

These are the cooperative's financial products. They sit beside the network and do not pay network bonuses.

**Investment plans and dividends:**

- An admin sets up plans, each with a unit price. Members buy units with their main balance, and each purchase is a holding.
- **Dividends are paid in batches.** An admin chooses an amount per unit, the holdings it covers (all of them, one plan, or holdings bought between two dates) and previews the total before paying.
- A batch pays every covered holding once, in the background, and records how many were paid and how many failed.
- A pending batch can be cancelled. A paid batch can be reversed, which takes each dividend back. Reversal can push a member below zero (see section 13).

**Farm cycles (FFO):**

- A farm cycle is a funding period with a start date, a maturity date and a return.
- Members put savings into a cycle. At maturity the principal and return are paid out automatically. A cycle that was not paid is reopened for the next run.

**Savings:**

- **Products:** the admin defines savings types. Each has a duration, an interest rate and minimum and maximum amounts.
- **A member's savings plan** records the principal, balance, interest earned, target, contribution frequency and amount, start date, next due date and maturity date.
- **Movements** between savings and the main balance are ledger entries, processed in the background.

**Loans:**

- **Loan products** set the interest rate, minimum and maximum amount, the borrowing limit as a multiple of savings, the minimum savings and minimum membership age, the allowed terms, and whether approval is automatic.
- **Applying:** a member applies for an amount, a term and a purpose. They can ask other members to act as guarantors, and each guarantor accepts or declines.
- **Approval:** on approval the loan is disbursed and a repayment schedule is created, with each instalment's principal, interest, total and due date.
- **Repayments** reduce the outstanding balance, and the loan is cleared when it reaches zero. Missed instalments can mark a loan as in default; when exactly is settled at sign-off (section 13).

## 11. Admin, roles and audit

Admins run the platform from one panel. What each admin can see and do is set by their role.

**Roles and permissions:**

- Every admin page and every admin action has a named permission. A role is a set of permissions, and each admin has one or more roles.
- The **super-admin** role has every permission. A few areas are super-admin only: admins and roles, system settings, and payment gateway keys.
- An admin account can be deactivated. A deactivated admin is signed out on their next request.
- Admin sign-in uses a username and password, with optional two-factor sign-in and a password reset by email code. Repeated failed attempts are slowed down.

**What admins do:**

| Area | Actions |
| --- | --- |
| Dashboard | Members, deposits, withdrawals, orders, stockists and pins at a glance; the four pool balances |
| Members | Search and filter (active, banned, KYC state, with balance, paid/free); view a member's details, tree and wallets; edit details; add or subtract from a wallet with a reason; ban or unban; block withdrawals; change a sponsor; review KYC; sign in as the member to help them; send a notification |
| Money | Approve or reject deposits and withdrawals; manage gateways and withdrawal methods; create e-pins |
| Plan | Packages, awards (approve and pay), unilevel levels, repurchase awards, leadership settings, pool ledgers and their contingency funds, held payouts |
| Commerce | Products, categories, state prices, orders, stockists and their requests, the Ad Center, affiliate settings, Welcome Back Packages |
| Finance | Investment plans and dividend batches, farm cycles, savings and loan products, loan approvals |
| Content | Website pages and sections, SEO, cookie notice, languages, notices and announcements to members |
| Support | Support tickets with private attachments; reply, close |
| Reports | Transactions, PV log, login history, financial report (income, payouts and pools by period), admin action log |
| Settings | Site name and currency, registration on/off, verification switches, charges, maintenance mode, message templates |

**Audit log:** every admin action that changes data records the admin, the action, the record it changed, the old and new values, the time and the IP address. The log cannot be edited or deleted from the panel.

## 12. Website, messages and scheduled jobs

**Public website:**

- **Pages:** a home page with a banner and calls to action, the shop front (section 8), a contact page, policy pages (privacy, terms), a cookie notice and a maintenance page.
- **Content:** admins edit the text, images and SEO details (title, description, sharing image) of each page, without a developer.
- **Languages:** English first. More languages can be added, with every on-screen word translatable.
- **Maintenance mode:** only Wiifarm's own admin can switch it on. No outside party can.

**Messages:**

- Each event that tells a member something has its own template for email, SMS and push. Examples: a deposit or withdrawal approved or rejected, a bonus paid, KYC reviewed, a password reset code, a new sign-up in your team.
- Each channel can be switched on or off for the whole site and per template. Templates use placeholders such as `{{amount}}`.
- The email and SMS providers are settings, not code.

**Scheduled jobs (times are Lagos time):**

| When | Job |
| --- | --- |
| Every minute | Work the background queues; scan for unreported USDT payments |
| Hourly | Pay any ACB and leadership bonuses still due; qualify new leaders |
| Daily 00:00 | Savings contributions and loan repayments due today |
| Daily 01:30 | Matching bonus run (5.5) |
| Daily 02:30, acts once a month | Leadership bonus for the month just ended (5.7) |
| Daily 02:00 | Record members newly qualified for a recognition or repurchase award (5.8, 5.11) |
| Daily 03:00 | Full database backup, kept 30 days |
| Daily | Expire unpaid or uncollected public orders (8). This is not scheduled in the current system, and should be |
| Daily 03:10 | Autoship: release held matching where an autoship is made; flush it after 3 missed months in a row (5.6) |
| Daily 09:20, from the 22nd to the 7th | Autoship reminder emails, once per stage (5.6) |

Every job can run again safely: a second run never pays twice. Each run is logged with what it did.

## 13. Open decisions

The specification can be signed off once the board settles the points below.

**Settled by the plan:**

- **The award ladder** is Emerald to Global Mega, with the plan's conditions (5.8).
- **The key-in bonus** is 2%.
- **Stockist stores** are 3,000 PV (Basic) and 10,000 PV (Mega), and the stockist bonus is ₦50,000 / ₦30,000 / ₦20,000 × 8 for Basic and ₦150,000 / ₦100,000 / ₦50,000 × 8 for Mega.

**From the plan and the blueprint:**

- [x] **1. Plan versus budget.** Settled for the direct, indirect, key-in, upgrade and stockist lines by Blueprint V3 (section 7): each line now holds what the plan pays. Still open for matching, unilevel and awards (17 and 18 below). *Earlier question:* Several plan payouts are larger than their blueprint budget lines. Direct is 25% of package value against a 6% line, and unilevel can reach 49% across 15 generations against 9%. When a payout would overrun its line, should it be paid in full and reported, capped at the line, or should the plan or the budget be revised?
- [x] **2. Funding between engines.** Settled by Blueprint V3: each kind of purchase funds its own ledger (section 7). *Earlier question:* Does revenue from registrations fund only the Consumer Rewards Pool and commerce revenue only the Commerce Pool? Or is every payment split across all three pools in the 22 / 24.75 / 8.25 ratio?
- [ ] **3. Leadership bonus team.** Does "5% of what your team members earn from matching" cover the whole downline, or a set number of levels or generations? Is it counted through the tree or the referral chain? *Built as an admin setting, starting with the whole binary-tree downline (5.7). Also: when one leader is in another leader's team, should both earn on the same matching (as built), or should the upper leader skip the lower leader's team?*
- [x] **4. Unilevel activity.** Settled from the Revised Baseline Rules and the leader's answers (19 November 2026): unilevel is paid at each repurchase on the upline's active package; the monthly purchase decides the autoship hold on matching, not unilevel. Please confirm.
- [x] **5. Store PV reach.** Settled: 10 steps, counted both up the binary tree and up the referral chain (section 9).
- [x] **6. Global Share Pool.** Settled: there is no Global Share Pool (board, November 2026).
- [x] **7. Autoship hold.** Settled (19 and 20 November 2026): the 20% hold stays, released when the month's autoship is made; a missed month stays held, and after 3 missed months in a row their holds go to Pool A's Unclaimed Bonus Reserve (5.6). It remains an admin switch, on.

**From the current system:**

- [ ] **8. Bans:** should a ban pause bonuses (paid on unban, as now) or cancel them for good?
- [ ] **9. Matrix stages:** is the current "stage two" matrix and its stage-out report still part of the plan? If not, it is left out.
- [ ] **10. VISA wallet:** how do members top it up: by deposit, by bonus transfer, or only by an admin? *For now only an admin can, through "Add to or subtract from a member's wallet" on the member's page.*
- [x] **11. Empty compensation budget:** Settled: payouts are held until the line refills (section 7).
- [ ] **12. Dividend reversal:** may it take a member's balance below zero, or stop at zero?
- [ ] **13. Loans:** when does a missed instalment make a loan "in default", and what happens then (fees, guarantors)?
- [ ] **14. Sponsor changes:** when an admin changes a member's sponsor, should their place in the binary tree also move, and do bonuses already paid stay as they are? (Today only the sponsor changes, for the future.)
- [ ] **15. Wallet corrections:** who may add to or subtract from a member's wallet? Today it is a permission any role can be given ("Add to or subtract from a member's wallet"). Should it be super-admin only, or need a second admin to approve above an amount?
- [ ] **16. Sponsor not yet paid:** someone signs up with the referral of a person whose own package is not paid yet (so that person has no place in the tree). When the new member pays, should they wait, be placed under the sponsor's nearest paid upline, or under the company account? Today the placement is refused with a clear message and nothing is paid.

**From Blueprint V3 (November 2026):**

- [x] **17. Recognition awards.** Settled: the Strategic Reserve is 1%, so recognition awards receive ₦5.25 a unit (section 7). Awards the pool cannot cover yet are held until it refills, oldest first. At that rate an ₦80,000 Emerald award needs the reserve from about 15,200 registration units, so awards will often wait.
- [x] **18. When a line runs short.** Settled: covered from the same ledger's contingency fund; if that is empty too, held until it refills, oldest first (section 7).
- [x] **19. Contingency Reserve and safety nets.** Settled: every reserve and leftover goes into a contingency fund kept per ledger, used only for that ledger's shortfalls; unpaid shares stay with the cooperative, in that ledger's Unclaimed Bonus Reserve, kept apart from the contingency fund (owner, 20 November 2026; section 7).
- [x] **20. Unilevel depth.** Settled: 15 generations at the plan's rates (owner, 19 November 2026; sections 4 and 5.10). This replaces the earlier "10 generations, never 15"; the indirect referral bonus stays at 10 generations.
- [x] **21. Repurchase award schedule.** Settled by the Revised Baseline Rules (19 November 2026): 5,000 PV for ₦250,000 up to 100,000 PV for ₦5,000,000, paid incrementally (5.11).
- [ ] **22. Upgrade ledger.** Pool A has a 2.3% Contingency Reserve, Pool E does not. Intended?
- [x] **23. Public shop split.** Settled: affiliate ₦100 (20% of the ₦500 margin), the stockist's usual ₦120 / ₦90, the rest to the manufacturing company; nothing to the cooperative, no PV. Anyone can be an affiliate, member or not (section 8).
- [x] **24. Global Share Pool and stockist sponsor bonus.** Settled: no Global Share Pool; the stockist sponsor bonus is Pool D.
- [ ] **25. Collecting payments for two companies.** Settled in part: the manufacturing company allocates stock to the stores (section 9); there is one manufacturing partner for now, with more possible later (section 7). Still open: does each company have its own Paystack and bank account, so a payment is split at the gateway, or does one account collect and pass the other company its share, and if so how often?
- [x] **26. Packs on upgrade.** Settled: an upgrade brings the higher package's extra packs, collected with a pickup code. The stockist's rebate for them comes from the upgrade pool's (Pool E's) own contingency fund, since that pool has no rebate share of its own.
- [x] **27. The rest of a store's deposit.** Settled (19 November 2026): half to the manufacturing company for the products; the sponsor bonus from the other half; the rest of that half to Pool D's contingency fund (section 9).
- [ ] **28. Recording a Sub store's collection.** The system has the Mega stockist record what each of their Sub stores collects, moving the units from the Mega's stock to the Sub's. Should the manufacturing company record it instead, or should the Sub store confirm it too?
- [ ] **29. Withdrawal charges and limits.** The system sets up "Bank transfer" from ₦1,000 to ₦5,000,000 with no charge, for an admin to change. What should each method's charge be (fixed and percentage), and is it right that charges go to the cooperative? Member withdrawals also need verified KYC (an admin switch, on by default); stockist withdrawals follow the stockist's own member account; affiliates have no KYC. Is that right?
- [x] **30. Unused autoship money.** Settled (19 and 20 November 2026): the held amount is not spent on products; it is released to the member when an autoship is made, or flushed to Pool A's Unclaimed Bonus Reserve after 3 missed months in a row (5.6).
- [ ] **31. Paystack's fee and deposit limits.** Members pay exactly the price; Paystack takes its fee from what it sends on, so the company receiving the money bears it. Should members pay the fee on top instead (for deposits, packages, or both)? Deposits are set at ₦1,000 to ₦5,000,000: right? And a deposit is treated as the member's own money, not a sale, so nothing is split until it is spent (section 6): please confirm.
- [ ] **32. E-pin rules.** (a) A pin worth more than the package chosen pays it, and the rest goes to the new member's main balance. Should the rest go back to the buyer instead, or should a pin only pay the package it was bought for? (b) A pin an admin creates is paid for by the cooperative: its value comes from the platform's adjustment account, and once used, it is split like any registration and pays the usual bonuses. Who may create them, and is there a limit? (c) Should members be able to cancel their own unused pins?
- [ ] **33. Public shop details.** (a) The affiliate link is remembered for 30 days and an unpaid order waits 3 days: right? (Both are settings for now, not yet on an admin screen.) (b) Buyers must give an email, because Paystack needs one: is that acceptable, or should a placeholder be used for buyers without email? (c) Prices per state (section 8) are not built: the public price is the same everywhere. Are per-state prices still wanted?
- [ ] **34. Paying awards.** (a) When a member takes the gift instead of the cash, the system sets the cash value aside from the recognition line to buy the gift. Is that right, or does the gift cost something else? (b) Repurchase awards wait for an admin's approval like recognition awards. Should they be paid automatically instead? (c) Recognition awards are far larger than Pool B receives (₦5.25 a piece for recognition), so most will be held for a long time (decision 17).
- [ ] **35. Using the Unclaimed Bonus Reserve.** Unclaimed shares are kept per ledger and reported (section 7), but nothing spends them yet. (a) Should the reserve also help cover a shortfall in its own ledger after the contingency fund, before a payout is held? (b) Or is it kept for another use the board decides, for example a transfer to the company? Until the board says, it is only kept and reported.
- [ ] **36. Autoship details** (built as below until the board says otherwise). (a) A missed month's hold is released when the member makes **any later** month's autoship, if 3 in a row have not been missed yet. Or should a missed month's hold only be flushed or kept, never released? (b) After 3 missed months, a 4th missed month in the same run is flushed as soon as its first week passes (the count does not start again at 0). Right? (c) Reminder emails go three times: the 22nd, the 1st and the 7th. Should they go every day of the window instead? (d) "The company reserves" is read as Pool A's Unclaimed Bonus Reserve, not the contingency fund. Right?
