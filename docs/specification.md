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
| Company account | Member #1. It sponsors anyone who signs up without a referral. |

## 2. Members and accounts

A person becomes a member when their package is paid. Until then they hold a pending account that earns nothing and appears nowhere in the tree.

**Ways to join:**

1. **Self sign-up.** The person fills in the form, chooses a package, and pays by Paystack or with an e-pin. Placement and bonuses happen at the moment payment is confirmed.
2. **Key-in by a member ("Add Distributor").** A member registers someone and pays the package from their own VISA wallet. The member doing the key-in earns the key-in bonus (section 5).

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

**Repurchase PV** is a separate counter, used for the unilevel bonus (5.10) and repurchase awards (5.11). It is the PV of products a member buys after joining, including their monthly 10 PV authorship. Repurchase awards count the repurchase PV of a member's **1st to 10th generation** downline (the referral chain). The current system instead shares it with up to 50 tree uplines, and that is replaced.

## 4. Packages

Every member holds one package. Its price is its PV × ₦1,000, while bonuses are worked out on PV × ₦600 (its "PV value", 60% of the price). It comes with a number of product packs. The package decides the direct bonus, the matching rate, the daily matching cap and how many unilevel generations the member is paid on. To stay active for the unilevel bonus, a member makes a **monthly authorship purchase of 10 PV**.

| Package | Price | PV | Product packs | Direct bonus (25% of PV value) | Paid per match | Daily cap (matches) | Daily cap (₦) | Unilevel generations |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Pre Starter | ₦15,000 | 15 | 1 | ₦2,250 | ₦900 (3%) | 5 | ₦4,500 | 1–4 |
| Starter | ₦30,000 | 30 | 2 | ₦4,500 | ₦1,500 (5%) | 7 | ₦10,500 | 1–6 |
| Builder | ₦60,000 | 60 | 4 | ₦9,000 | ₦2,100 (7%) | 10 | ₦21,000 | 1–8 |
| Booster | ₦105,000 | 105 | 7 | ₦15,750 | ₦2,400 (8%) | 30 | ₦72,000 | 1–10 |
| Executive | ₦150,000 | 150 | 10 | ₦22,500 | ₦2,700 (9%) | 60 | ₦162,000 | 1–12 |
| Advance | ₦255,000 | 255 | 17 | ₦38,250 | ₦3,000 (10%) | 90 | ₦270,000 | 1–13 |
| Mega | ₦450,000 | 450 | 30 | ₦67,500 | ₦3,600 (12%) | 120 | ₦432,000 | 1–15 |

"Paid per match" is a percentage of one match's value: 50 PV × ₦600 = ₦30,000.

**Upgrades.** A member can move to a higher package by paying the difference in price. The PV difference climbs the tree (section 3) and pays the upgrade bonuses (section 5). From then on, the new package's rates and caps apply. Downgrades are not allowed, and an admin can mark a package as "no upgrades".

## 5. Compensation

The plan lists thirteen ways of earning. They are retail profit, the key-in, direct, indirect, matching and upgrade bonuses, binary awards, UCB, the leadership bonus, the unilevel bonus, repurchase awards, the stockist bonus and the stockist rebate. Autoship and the Global Share Pool come from the blueprint and the current system. Each payout goes into its own bonus wallet (section 6) and is charged to its budget line (section 7). **Every bonus is only paid to active members.** A banned member's share is not paid, it does not pass to anyone else, and the generation still counts.

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
- **Amount:** the matches paid × the package's "paid per match".
- **PV used:** 50 PV per match is taken from each leg. **Everything left over carries forward** to the next days. Nothing is ever wiped.
- **No sponsor is needed** on either leg. Only PV counts.
- **Banned members:** they are not paid. Their pairing PV is left as it is.
- **Autoship split:** if the member has not met this month's autoship (5.6), 80% goes to the matching wallet and 20% to the autoship wallet.
- **Example:** a Mega member with 1,000 left and 400 right has 8 possible matches, under the cap of 120. They earn 8 × ₦3,600 = ₦28,800: ₦23,040 for matching and ₦5,760 to autoship. 600 PV carries forward on the left and 0 on the right.

### 5.6 Autoship

- **Rule:** a member meets autoship for a calendar month by buying at least **10 PV** of products in that month.
- **Until then:** 20% of each matching bonus is held in their autoship wallet.
- **Using it:** the autoship balance can only be spent on products.
- **Month end:** an autoship balance not used by the end of the month goes to the company, and the sweep is logged.

### 5.7 Leadership bonus

- **Who qualifies:** a member who holds the **Executive Ambassador** rank (450,000 PV, 5.8).
- **Amount:** **5%** of what their team earns from the matching bonus each month.
- **Paid:** monthly, for the month just ended. Each month is paid once.
- **Open:** whether "team" means the whole downline or a set number of levels (section 13).
- **Replaced:** the current system pays 4% to anyone with 1,000 PV on each leg, up to 5 levels above each matching payout. That is replaced by the plan's rule.

### 5.8 Binary awards (recognition ranks)

- **Rule:** a one-time award when the **lesser leg's lifetime PV** reaches the rank's level. Stockist store PV counts here (section 3).
- **Package conditions:** Emerald needs the Builder package or higher, Silver needs Booster, and Jade needs Executive.
- **From Gold upwards:** the member must hold the Mega package **and** have produced the rank just below on both their left and right genealogy. For example, Gold needs a Jade on the left and a Jade on the right.
- **Paying it:** an admin approves each award. The member takes the cash or, where offered, the gift.

| Rank | PV on lesser leg | Cash award | Or gift |
| --- | --- | --- | --- |
| Emerald | 1,500 | ₦80,000 | Cash only |
| Silver | 3,000 | ₦150,000 | Cash only |
| Jade | 6,000 | ₦300,000 | Cash only |
| Gold | 12,500 | ₦550,000 | Laptop |
| Diamond | 25,000 | ₦1,200,000 | Smart TV |
| Executive Diamond | 35,000 | ₦1,500,000 | Solar freezer and washing machine |
| Platinum | 80,000 | ₦4,000,000 | Complete solar system |
| Premium Platinum | 150,000 | ₦10,000,000 | Small car |
| Ambassador | 300,000 | ₦16,000,000 | International trip |
| Executive Ambassador | 450,000 | ₦25,000,000 | Big car |
| Sapphire | 800,000 | ₦40,000,000 | Land |
| Executive Sapphire | 2,500,000 | ₦100,000,000 | Small bungalow |
| Global Mega | 5,000,000 | ₦180,000,000 | Duplex |

This is the ladder the current system already holds, so the other ladder in Wiifarm's remediation notes (Sapphire Leader to Global Presidential) is dropped.

### 5.9 Achievers Celebration Bonus (ACB)

- **When:** a member receives a binary award or a repurchase award.
- **Who and how much:** their sponsor gets 5% of the award's value, generation 2 gets 2%, and generation 3 gets 1%.

### 5.10 Unilevel bonus (15 generations)

- **When:** each month, on the repurchase PV of a member's downline, including each downline member's 10 PV monthly authorship.
- **Who:** up the referral chain. Each member is paid only on the generations their package unlocks (section 4).
- **Rates (share of the PV value):**

| Generation | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Rate | 10% | 7% | 5% | 4% | 4% | 3% | 3% | 3% | 2% | 2% | 2% | 1% | 1% | 1% | 1% |

- **Example:** one 1st-generation member's 10 PV authorship (₦6,000) pays ₦600.
- **Replaced:** the current system uses different rates (6%, 5%, 4%, 3%, 2%, then 1% and 0.5%) and different package depths. Both are replaced by the plan's.

### 5.11 Repurchase awards

- **Rule:** one-time cash awards on the repurchase PV accumulated from a member's **1st to 10th generation** downline (section 3).

| Repurchase PV | 750 | 1,500 | 3,000 | 6,000 | 10,000 | 15,000 | 20,000 | 25,000 | 30,000 | 45,000 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Cash award (₦) | 50,000 | 100,000 | 250,000 | 350,000 | 1.25M | 1.5M | 2M | 2.5M | 3M | 5M |

- **Replaced:** the current system's amounts differ from the 3,000 PV level upwards, and it has a 35,000 PV level instead of 30,000. The plan's table replaces it.

### 5.12 Retail profit and the earnings described elsewhere

- **Retail profit** is not a payout. Members buy products at member prices and can resell them for over 50% profit; the plan's example is ₦225,000 on a ₦450,000 Mega repurchase. The system only needs a member price and a retail price per product (section 8).
- The stockist bonus, stockist rebate and 3% stockist sponsor bonus are in section 9.
- The affiliate bonus is in section 8.
- The Global Share Pool is in section 7.
- Cash back as a Welcome Back Package is in section 8. It is currently ₦0 on every package.

## 6. Wallets, ledger and payments

Each member has a main balance, four special-purpose wallets, and one wallet per kind of bonus. Every movement in any of them is a ledger entry (section 1).

| Wallet | What goes in | What it can be used for |
| --- | --- | --- |
| Main balance | Deposits, bonus transfers, transfers from other members | Withdrawals, transfers, purchases |
| VISA wallet | Top-ups (see section 13) | Paying registrations (key-in) and e-pins |
| Product wallet | Product credit | Shop purchases |
| Autoship | The 20% autoship hold (5.6) | Product purchases only; swept at month end |
| Savings wallet | Savings deposits | Savings products (section 10) |
| Bonus wallets | One per bonus: direct, indirect, upgrade, matching, leadership, unilevel, key-in, awards, repurchase award, ACB, affiliate, stockist rebate, stockist sponsor | Moved to the main balance ("Bonus Transfer") |

**Deposits:**

- **Paystack:** the member pays online, and the deposit is credited only when Paystack confirms it. The confirmation comes server-to-server and is checked; a returning browser is never trusted on its own.
- **USDT (TRC20):** Wiifarm uses one wallet address. The member sends the money and enters the transaction ID. The system checks it on the Tron network (amount, address, confirmations) and credits once. A scheduled scan also finds transfers that nobody reported. A transaction ID can never be credited twice.
- **Manual methods** (for example a bank transfer): the member fills in the method's form and uploads proof. An admin approves or rejects with a reason. Proof files are private.
- Every confirmed deposit is split across the four pools (section 7).

**Withdrawals:**

- Each method has a minimum, a maximum, a fixed charge, a percentage charge, a rate and its own form (for example bank name, account number, account name).
- On request, the amount leaves the main balance at once and the request waits for an admin.
- An admin approves it (paid) or rejects it with a reason. On rejection the full amount, including the charge, goes back to the main balance.
- Members whose withdrawals are blocked cannot request one, and see the reason.

**Transfers:**

- **Balance transfer** moves money between members, with a fixed and a percentage charge set by the admin.
- **Bonus transfer** moves money from a bonus wallet to the member's own main balance.

**E-pins:** a pin is a code worth a fixed amount, bought from the VISA wallet or created by an admin. A pin pays one registration, once. Pins show who created them, who used them, and when.

## 7. Pools and governance

The blueprint's guiding principle governs this section: **every payout must be traceable to the transaction that produced its margin.** Every incoming naira is tagged with the engine that earned it, split into budget pools, and every bonus is charged to its own budget line.

**Two financing engines:**

| Engine | Earned from | Funds |
| --- | --- | --- |
| Registration engine | New registrations and package upgrades | The Consumer Rewards Pool (CRP): acquisition incentives |
| Commerce engine | Repurchases, retail sales and stockist purchases | The Commerce Pool (CP): recurring commercial incentives |

**How each naira of revenue is allocated:**

| Allocation | Share | Inside it |
| --- | --- | --- |
| Company return | 30.00% |  |
| Executive and frontline leadership | 10.00% |  |
| Operations | 5.00% | Platform maintenance 0.50%, marketing and business development 1.00%, business expansion and retained earnings 3.00%, miscellaneous and contingency 0.50% |
| Member compensation budget | 55.00% | Consumer Rewards Pool 22.00%, Commerce Pool 24.75%, Strategic Reserve 8.25% |

The Strategic Reserve is a reserve inside the 55% compensation budget, not a third engine.

**The allocation is applied per product pack.** Every package is made of packs of 15 PV at ₦15,000 each (Mega = 30 packs), and a carton is 15 packs. The split above applies to each pack's price, so one pack carries: company ₦4,500, executive and frontline leadership ₦1,500, operations ₦750, and members ₦8,250 (Consumer Rewards ₦3,300, Commerce ₦3,712.50, Strategic Reserve ₦1,237.50). A package or carton's budget is the pack budget × its number of packs. Each bonus line's budget per pack is its share × ₦15,000 (for example, direct referral 6% = ₦900 per pack).

**Budget lines inside the compensation budget (share of revenue):**

| Pool | Budget line | Share |
| --- | --- | --- |
| Consumer Rewards Pool (22%) | Key-in bonus | 1.00% |
|  | Direct referral bonus | 6.00% |
|  | Indirect referral bonus | 5.00% |
|  | Binary matching bonus | 8.00% |
|  | Upgrade bonus | 2.00% |
| Commerce Pool (24.75%) | Unilevel bonus | 9.00% |
|  | Leadership bonus | 4.00% |
|  | Repurchase bonus | 5.00% |
|  | Stockist sponsorship rebate | 3.00% |
|  | Stockist product rebate | 3.75% |
| Strategic Reserve (8.25%) | Contingency reserve | 1.00% |
|  | Rank achievement awards | 4.50% |
|  | Achievers / Upline Celebrated Bonus | 1.50% |
|  | Global Share | 1.25% |

**Rules:**

- **Splitting:** each confirmed payment is split into these allocations as ledger entries that add up to the payment exactly. The rounding remainder goes to the compensation budget.
- **Charging:** every bonus payout is a debit from its own budget line, naming the member and the transaction that funded it.
- **Reporting:** the admin dashboard and the financial report show, for each pool and each line, what came in, what was paid and what is left, by day, month and quarter.
- **Plan versus budget:** the plan pays some bonuses as a share of PV value, and those can be larger than their budget line. For example, the direct bonus is 25% of a package's value, while its budget line is 6% of revenue. What happens when a payout would overrun its line is a board decision (section 13). Until it is made, payouts follow the plan and overruns are reported, never hidden.

**Global Share Pool:**

- **What it collects:** its 1.25% budget line, per calendar quarter.
- **When it pays:** on the 1st of January, April, July and October, for the quarter just ended. Each quarter is paid once.
- **Who gets it:** shared equally among the qualifying members. The plan does not say who qualifies. The current system uses the Diamond rank and above (section 13).

## 8. Shop and affiliate

The shop sells Wiifarm's products to members and to the public. Public buyers collect from a stockist in their state, and the member who sent them earns an affiliate bonus.

**Products:**

- **Details:** name, category, SKU, description, specifications, images, PV, stock quantity, and minimum and maximum order quantities.
- **Price per state:** each state can have its own price, with optional per-state bonuses for Mega and Basic stockists.
- **Wholesale:** cartons of 15 packs by default, with a carton price.
- **Affiliate bonus:** a flat amount or a percentage of the price.
- **Display:** a product can be featured or hidden.

**Prices:** each product has a member price and a suggested retail price, so members can see their retail profit (5.12).

**Registration packs:** each package comes with its number of product packs (section 4). A new member collects them from a stockist with a code, in the same way as a Welcome Back Package.

**Member orders:**

- A member pays from their main balance, product wallet or autoship wallet.
- The product's PV climbs the tree (section 3), and the purchase pays the unilevel bonus (5.10).
- It also counts towards autoship (5.6) and repurchase PV.

**Public (affiliate) orders:**

1. A member shares their personal link (for example `?ref=WII-XXXXXX`) or an advert from the Ad Center. A click is remembered for a number of days set by the admin.
2. The buyer chooses their state and pays by Paystack or chooses **cash on pickup**. They receive a pickup code.
3. A stockist checks the code in person and hands over the goods. The order is then fulfilled.
4. On fulfilment, the referring member's affiliate bonus is paid. A percentage bonus is worked out on the item's actual price: 10% of a ₦40,000 item is ₦4,000. The stockist earns their rebate and pickup fee, and the stockist's sponsor their 3% (section 9).
5. An order not paid or collected within the admin's set number of days expires, and its stock is released.

**Order statuses:** pending, paid, awaiting pickup, fulfilled, cancelled, expired.

**Ad Center:** admins publish adverts (image, caption, link). Members share them on WhatsApp, Facebook, X, Telegram, TikTok and Instagram with their own link attached. Clicks and orders are reported per advert.

**Welcome Back Package (cash back):** when a package has a cash-back amount, it is not paid as money. It becomes a package code for products of that value, which a stockist redeems in person.

## 9. Stockists

A stockist is a member who runs a store that holds Wiifarm stock, sells to members, and hands over public orders. There are two kinds.

|  | Basic store | Mega store |
| --- | --- | --- |
| Setup payment | ₦3,000,000 | ₦10,000,000 |
| Products received to service the store | 100% of the setup value | 100% of the setup value |
| Store PV (for awards only) | 3,000 PV | 10,000 PV |
| Stockist bonus, total over 10 generations | ₦240,000 | ₦650,000 |
| Rebate on products that go out of the store (share of PV value) | 6% | 8% |
| Buys stock from | Wiifarm or a Mega store | Wiifarm |

**Becoming a stockist:**

1. A member applies with their business details and state. A stockist sponsor can be named.
2. An admin reviews the application, takes payment and activates the store. The stockist receives products worth 100% of the setup payment.
3. On activation, in one step that is paid once only:
   - the stockist bonus goes to 10 generations of the stockist's referral chain (generation 1 is the stockist's sponsor, or their referrer if none was named);
   - the store PV goes up the stockist's uplines as lifetime PV, which counts for awards and never for matching. The plan's table shows the PV reaching 15 generations, while its text says 10 (section 13);
   - the payment is split into the pools on the commerce engine (section 7).
4. A banned upline is skipped, and the generation still counts.

**Enrollment bonus by generation:**

| Generation | 1 | 2 | 3–10, each |
| --- | --- | --- | --- |
| Basic | ₦50,000 | ₦30,000 | ₦20,000 |
| Mega | ₦150,000 | ₦100,000 | ₦50,000 |

These figures match the current system. The other split in Wiifarm's remediation notes (Basic 1,000 PV, ₦120,000 to generation 1) is not in the plan and is dropped.

**Running the store:**

- **Inventory:** stock in and out, with a record of every movement. Stock arrives through approved stock requests, transfers between stockists, and supplies from Wiifarm.
- **Point of sale:** the stockist sells to members and records the invoice. The member's PV, repurchase PV and unilevel bonuses follow.
- **Redemptions:** the stockist redeems member invoices, Welcome Back Packages and public pickup orders in person, by code.
- **Rebate:** the stockist earns 6% (Basic) or 8% (Mega) of the PV value of stock they hand over. A state's own rebate rate, when set, replaces it.
- **Stockist sponsor bonus:** the member who sponsored the store earns 3% of the PV value of the store's sales. For example, 500 PV sold = ₦300,000, so the sponsor earns ₦9,000.
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
| Plan | Packages, awards (approve and pay), unilevel levels, repurchase awards, leadership settings, the Global Share Pool |
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
| Daily 02:00 | Check members for new binary awards |
| Daily 03:00 | Full database backup, kept 30 days |
| Daily | Expire unpaid or uncollected public orders (8). This is not scheduled in the current system, and should be |
| Last day of the month, 23:00 | Autoship sweep (5.6) |
| 1st of Jan, Apr, Jul, Oct, 01:17 | Global Share Pool payout for the quarter just ended (7) |

Every job can run again safely: a second run never pays twice. Each run is logged with what it did.

## 13. Open decisions

The specification can be signed off once the board settles the points below.

**Settled by the plan:**

- **The award ladder** is Emerald to Global Mega, with the plan's conditions (5.8).
- **The key-in bonus** is 2%.
- **Stockist stores** are 3,000 PV (Basic) and 10,000 PV (Mega), and the stockist bonus is ₦50,000 / ₦30,000 / ₦20,000 × 8 for Basic and ₦150,000 / ₦100,000 / ₦50,000 × 8 for Mega.

**From the plan and the blueprint:**

- [ ] **1. Plan versus budget.** Several plan payouts are larger than their blueprint budget lines. Direct is 25% of package value against a 6% line, and unilevel can reach 49% across 15 generations against 9%. When a payout would overrun its line, should it be paid in full and reported, capped at the line, or should the plan or the budget be revised?
- [ ] **2. Funding between engines.** Does revenue from registrations fund only the Consumer Rewards Pool and commerce revenue only the Commerce Pool? Or is every payment split across all three pools in the 22 / 24.75 / 8.25 ratio?
- [ ] **3. Leadership bonus team.** Does "5% of what your team members earn from matching" cover the whole downline, or a set number of levels or generations? Is it counted through the tree or the referral chain?
- [ ] **4. Unilevel activity.** Must the earner make their own 10 PV authorship in a month to receive that month's unilevel bonus?
- [ ] **5. Store PV reach.** Does store PV go up 10 or 15 generations? Is it counted through the referral chain or the binary tree?
- [ ] **6. Global Share Pool.** Who qualifies (the current system uses Diamond and above)? And if nobody qualifies in a quarter, is the pool carried forward or returned?
- [ ] **7. Autoship hold.** The plan mentions the 10 PV authorship but not the 20% hold on matching bonuses (5.6). Should the hold stay?

**From the current system:**

- [ ] **8. Bans:** should a ban pause bonuses (paid on unban, as now) or cancel them for good?
- [ ] **9. Matrix stages:** is the current "stage two" matrix and its stage-out report still part of the plan? If not, it is left out.
- [ ] **10. VISA wallet:** how do members top it up: by deposit, by bonus transfer, or only by an admin?
- [ ] **11. Empty compensation budget:** if a pool runs out, do bonuses stop until it refills, or are they paid and the pool shown below zero?
- [ ] **12. Dividend reversal:** may it take a member's balance below zero, or stop at zero?
- [ ] **13. Loans:** when does a missed instalment make a loan "in default", and what happens then (fees, guarantors)?
- [ ] **14. Sponsor changes:** when an admin changes a member's sponsor, should their place in the binary tree also move, and do bonuses already paid stay as they are? (Today only the sponsor changes, for the future.)
- [ ] **15. Wallet corrections:** who may add to or subtract from a member's wallet? Today it is a permission any role can be given ("Add to or subtract from a member's wallet"). Should it be super-admin only, or need a second admin to approve above an amount?
- [ ] **16. Sponsor not yet paid:** someone signs up with the referral of a person whose own package is not paid yet (so that person has no place in the tree). When the new member pays, should they wait, be placed under the sponsor's nearest paid upline, or under the company account? Today the placement is refused with a clear message and nothing is paid.
