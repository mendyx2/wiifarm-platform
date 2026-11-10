# Front-End Files: Reuse, Restyle or Rebuild

Nov 10, 2026 · @wendy

Every file of the React front end built in this project (286 files in core/resources/js), sorted by what happens to it in the new platform. **Rebuild** = the screen belongs to a feature that came from ViserLab's script; it is written again from the specification. **Restyle** = Wiifarm's own logic, but it uses ViserLab's theme style names, so it gets Wiifarm's new look. **Reuse** = our own code with no ViserLab styles; it carries over after a quick review. This is a first pass, made by checking each file's location and the style names it uses. A reviewer should confirm each "Reuse" file before it moves across, and the line between reuse and rebuild should be confirmed by a lawyer.

## Summary

**About 81% of the front-end work carries its logic into the new platform:**

- **150 files (52%) can be reused** after a quick review;
- **82 files (29%) keep their logic** but get Wiifarm's new look;
- **54 files (19%) are rebuilt**, because they belong to features that came from ViserLab's script.

| Area | Rebuild | Restyle | Reuse | Total |
| --- | --- | --- | --- | --- |
| Admin pages | 37 | 60 | 31 | 128 |
| Member pages | 5 | 5 | 43 | 53 |
| Stockist pages | 0 | 0 | 17 | 17 |
| Sign-in pages | 0 | 1 | 7 | 8 |
| Public shop and public pages | 0 | 0 | 8 | 8 |
| Shared components | 11 | 15 | 27 | 53 |
| Page layouts | 0 | 1 | 8 | 9 |
| Helpers, types, app start-up, data | 1 | 0 | 9 | 10 |
| **Total** | **54** | **82** | **150** | **286** |

**What this means:**

- **Wiifarm's own areas carry over almost completely:** the stockist pages, public shop, member pages, helpers and layouts.
- **The rebuild work is concentrated in the admin panel's ViserLab features:** settings, payment gateways, messaging, languages, the website content manager, support tickets and the KYC form builder.
- **Restyling mostly means swapping style names** (for example ViserLab's `btn--primary` and `badge--success`) for Wiifarm's own. The page logic stays the same.

## Rebuild (54 files)

These screens serve features that came with ViserLab's script. They are written again from the specification, with Wiifarm's own design and wording. One of them, the vendor report page, is dropped entirely.

| Area | File | Why |
| --- | --- | --- |
| Admin page | `Pages/Admin/Cron/Index.tsx` | Cron job manager: ViserLab feature |
| Admin page | `Pages/Admin/Cron/Logs.tsx` | Cron job manager: ViserLab feature |
| Admin page | `Pages/Admin/Cron/Schedules.tsx` | Cron job manager: ViserLab feature |
| Admin page | `Pages/Admin/Extensions.tsx` | Extensions (reCAPTCHA, chat): ViserLab feature |
| Admin page | `Pages/Admin/Frontend/Element.tsx` | Website content manager: ViserLab's frontend manager |
| Admin page | `Pages/Admin/Frontend/Index.tsx` | Website content manager: ViserLab's frontend manager |
| Admin page | `Pages/Admin/Frontend/PageSections.tsx` | Website content manager: ViserLab's frontend manager |
| Admin page | `Pages/Admin/Frontend/Pages.tsx` | Website content manager: ViserLab's frontend manager |
| Admin page | `Pages/Admin/Frontend/Section.tsx` | Website content manager: ViserLab's frontend manager |
| Admin page | `Pages/Admin/Frontend/Seo.tsx` | SEO settings: ViserLab's frontend manager |
| Admin page | `Pages/Admin/Gateways/Automatic.tsx` | Payment gateway setup: ViserLab's gateway system |
| Admin page | `Pages/Admin/Gateways/AutomaticEdit.tsx` | Payment gateway setup: ViserLab's gateway system |
| Admin page | `Pages/Admin/Gateways/Manual.tsx` | Payment gateway setup: ViserLab's gateway system |
| Admin page | `Pages/Admin/Kyc/Setting.tsx` | KYC form builder: ViserLab's form system |
| Admin page | `Pages/Admin/Language/Index.tsx` | Languages: ViserLab's translation manager |
| Admin page | `Pages/Admin/Language/Keywords.tsx` | Translation keywords: ViserLab's translation manager |
| Admin page | `Pages/Admin/Methods/Form.tsx` | Gateway / withdraw method form: ViserLab's gateway system |
| Admin page | `Pages/Admin/Notification/EmailSetting.tsx` | Email settings: ViserLab's messaging system |
| Admin page | `Pages/Admin/Notification/Global.tsx` | Message settings: ViserLab's messaging system |
| Admin page | `Pages/Admin/Notification/PushSetting.tsx` | Push settings: ViserLab's messaging system |
| Admin page | `Pages/Admin/Notification/SmsSetting.tsx` | SMS settings: ViserLab's messaging system |
| Admin page | `Pages/Admin/Notification/TemplateEdit.tsx` | Message templates: ViserLab's messaging system |
| Admin page | `Pages/Admin/Notification/Templates.tsx` | Message templates: ViserLab's messaging system |
| Admin page | `Pages/Admin/Reports/BvLog.tsx` | BV log: from ViserLab's original MLM script (Wiifarm uses PV) |
| Admin page | `Pages/Admin/Settings/Configuration.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/Cookie.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/General.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/Hub.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/LogoIcon.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/Maintenance.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/Notice.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Settings/TextFile.tsx` | Site settings: a feature of ViserLab's admin panel |
| Admin page | `Pages/Admin/Support/Ticket.tsx` | Support tickets: ViserLab feature |
| Admin page | `Pages/Admin/Support/Tickets.tsx` | Support tickets: ViserLab feature |
| Admin page | `Pages/Admin/System/Info.tsx` | System info: ViserLab feature |
| Admin page | `Pages/Admin/VendorReports.tsx` | Report to ViserLab: dropped in the new platform |
| Admin page | `Pages/Admin/Withdraw/Methods.tsx` | Withdraw methods: ViserLab's gateway system |
| Data file | `data/lineIcons.json` | Icon list for the content manager's icon picker |
| Member page | `Pages/User/DepositManual.tsx` | Built on ViserLab's dynamic form format |
| Member page | `Pages/User/KycForm.tsx` | Built on ViserLab's dynamic form format |
| Member page | `Pages/User/TicketCreate.tsx` | Support tickets: ViserLab feature |
| Member page | `Pages/User/TicketView.tsx` | Support tickets: ViserLab feature |
| Member page | `Pages/User/Tickets.tsx` | Support tickets: ViserLab feature |
| Shared component | `Components/Admin/ChannelPicker.tsx` | Part of the messaging settings |
| Shared component | `Components/Admin/ContentFields.tsx` | Part of the website content manager |
| Shared component | `Components/Admin/FormAnswers.tsx` | Shows answers in ViserLab's form format |
| Shared component | `Components/Admin/FormBuilder.tsx` | ViserLab's form builder (KYC, gateways) |
| Shared component | `Components/Admin/GatewayTabs.tsx` | Part of the gateway setup |
| Shared component | `Components/Admin/IconPicker.tsx` | Part of the website content manager |
| Shared component | `Components/Admin/MethodTable.tsx` | Part of the gateway setup |
| Shared component | `Components/Admin/NotificationFields.tsx` | Part of the messaging settings |
| Shared component | `Components/Admin/NotificationNav.tsx` | Part of the messaging settings |
| Shared component | `Components/Captcha.tsx` | Reads ViserLab's captcha format |
| Shared component | `Components/DynamicForm.tsx` | Reads ViserLab's form format |

## Restyle (82 files)

These are Wiifarm's own screens and logic, but they use ViserLab's theme style names for buttons, badges, colours and tables. The logic carries over; the style names are swapped for Wiifarm's new design. The last column shows how many places in each file need changing. Most need fewer than 5.

| Area | File | Places to restyle |
| --- | --- | --- |
| Admin page | `Pages/Admin/Acb.tsx` | 1 |
| Admin page | `Pages/Admin/Ads/Index.tsx` | 1 |
| Admin page | `Pages/Admin/Affiliate/Audit.tsx` | 2 |
| Admin page | `Pages/Admin/Affiliate/Dashboard.tsx` | 2 |
| Admin page | `Pages/Admin/Affiliate/OrderDetail.tsx` | 1 |
| Admin page | `Pages/Admin/Affiliate/Settings.tsx` | 2 |
| Admin page | `Pages/Admin/Awards/Form.tsx` | 1 |
| Admin page | `Pages/Admin/Awards/Index.tsx` | 5 |
| Admin page | `Pages/Admin/Awards/Payments.tsx` | 5 |
| Admin page | `Pages/Admin/BonusWallet.tsx` | 1 |
| Admin page | `Pages/Admin/Dashboard.tsx` | 3 |
| Admin page | `Pages/Admin/Dividend/Batches.tsx` | 5 |
| Admin page | `Pages/Admin/Dividend/Create.tsx` | 3 |
| Admin page | `Pages/Admin/Dividend/Detail.tsx` | 5 |
| Admin page | `Pages/Admin/Dividend/Member.tsx` | 2 |
| Admin page | `Pages/Admin/Flashcards.tsx` | 3 |
| Admin page | `Pages/Admin/Loans/Index.tsx` | 2 |
| Admin page | `Pages/Admin/Loans/Products.tsx` | 9 |
| Admin page | `Pages/Admin/Loans/Show.tsx` | 9 |
| Admin page | `Pages/Admin/Money/DepositDetail.tsx` | 1 |
| Admin page | `Pages/Admin/Money/Deposits.tsx` | 2 |
| Admin page | `Pages/Admin/Money/UsdtPayments.tsx` | 2 |
| Admin page | `Pages/Admin/Money/WithdrawalDetail.tsx` | 2 |
| Admin page | `Pages/Admin/Money/Withdrawals.tsx` | 1 |
| Admin page | `Pages/Admin/Notices.tsx` | 5 |
| Admin page | `Pages/Admin/Pins/Index.tsx` | 3 |
| Admin page | `Pages/Admin/Plans/Holders.tsx` | 2 |
| Admin page | `Pages/Admin/Plans/Index.tsx` | 3 |
| Admin page | `Pages/Admin/Profile.tsx` | 5 |
| Admin page | `Pages/Admin/Projects.tsx` | 6 |
| Admin page | `Pages/Admin/Reports/Financial.tsx` | 1 |
| Admin page | `Pages/Admin/Reports/Investments.tsx` | 1 |
| Admin page | `Pages/Admin/Reports/StageTwice.tsx` | 6 |
| Admin page | `Pages/Admin/Reports/Transactions.tsx` | 2 |
| Admin page | `Pages/Admin/Savings/Cycles.tsx` | 12 |
| Admin page | `Pages/Admin/Savings/Index.tsx` | 1 |
| Admin page | `Pages/Admin/Savings/Settings.tsx` | 14 |
| Admin page | `Pages/Admin/Savings/Show.tsx` | 13 |
| Admin page | `Pages/Admin/Shop/Categories.tsx` | 3 |
| Admin page | `Pages/Admin/Shop/OrderDetail.tsx` | 19 |
| Admin page | `Pages/Admin/Shop/ProductForm.tsx` | 1 |
| Admin page | `Pages/Admin/Staff/Admins.tsx` | 8 |
| Admin page | `Pages/Admin/Staff/Permissions.tsx` | 6 |
| Admin page | `Pages/Admin/Staff/Roles.tsx` | 3 |
| Admin page | `Pages/Admin/Stockist/Activate.tsx` | 7 |
| Admin page | `Pages/Admin/Stockist/Dashboard.tsx` | 6 |
| Admin page | `Pages/Admin/Stockist/Details.tsx` | 5 |
| Admin page | `Pages/Admin/Stockist/OrderDetail.tsx` | 7 |
| Admin page | `Pages/Admin/Stockist/Orders.tsx` | 1 |
| Admin page | `Pages/Admin/Stockist/TransferDetail.tsx` | 2 |
| Admin page | `Pages/Admin/Stockist/Transfers.tsx` | 2 |
| Admin page | `Pages/Admin/Unilevel/Allocate.tsx` | 2 |
| Admin page | `Pages/Admin/Unilevel/Generations.tsx` | 7 |
| Admin page | `Pages/Admin/Users/Detail.tsx` | 45 |
| Admin page | `Pages/Admin/Users/KycDetail.tsx` | 2 |
| Admin page | `Pages/Admin/Users/NotifyAll.tsx` | 4 |
| Admin page | `Pages/Admin/Users/NotifyOne.tsx` | 1 |
| Admin page | `Pages/Admin/Users/SponsorHistory.tsx` | 2 |
| Admin page | `Pages/Admin/Users/Stages.tsx` | 1 |
| Admin page | `Pages/Admin/WelcomePack/Index.tsx` | 1 |
| Member page | `Pages/User/Dashboard.tsx` | 1 |
| Member page | `Pages/User/DepositHistory.tsx` | 1 |
| Member page | `Pages/User/Distributor.tsx` | 10 |
| Member page | `Pages/User/Shop/ProductDetail.tsx` | 7 |
| Member page | `Pages/User/TwoFactor.tsx` | 2 |
| Page layout | `Layouts/AdminLayout.tsx` | 8 |
| Shared component | `Components/Admin/BoxWidget.tsx` | 1 |
| Shared component | `Components/Admin/DataTable.tsx` | 2 |
| Shared component | `Components/Admin/DateRangeField.tsx` | 3 |
| Shared component | `Components/Admin/FilterCard.tsx` | 1 |
| Shared component | `Components/Admin/Form.tsx` | 1 |
| Shared component | `Components/Admin/ImageUploader.tsx` | 1 |
| Shared component | `Components/Admin/LinkWidget.tsx` | 1 |
| Shared component | `Components/Admin/LoanReject.tsx` | 2 |
| Shared component | `Components/Admin/SearchForm.tsx` | 2 |
| Shared component | `Components/Admin/StatWidget.tsx` | 10 |
| Shared component | `Components/Admin/StockistTopUp.tsx` | 1 |
| Shared component | `Components/Admin/TagInput.tsx` | 1 |
| Shared component | `Components/AttachmentPicker.tsx` | 3 |
| Shared component | `Components/ConfirmDialog.tsx` | 3 |
| Shared component | `Components/Shop/ProductCard.tsx` | 3 |
| Sign-in page | `Pages/Auth/Register.tsx` | 9 |

## Reuse (150 files)

These are our own code and use none of ViserLab's theme style names. They move to the new platform after a reviewer confirms each one, and adjusts it where the new back end works differently. They are grouped by area.

| Area | Files | List |
| --- | --- | --- |
| Stockist pages | 17 | `Pages/User/Stockist/`: AffiliateRedeem, Dashboard, Find (+ .css), History, Inventory/Catalog, Inventory/Checkout, Inventory/Dashboard, Inventory/OrderDetails, Inventory/Orders, Pos, Profile, Redemptions, Transfers/Create, Transfers/Index, Transfers/Show, WelcomePack |
| Member pages | 43 | `Pages/User/`: Acb (+ .css), Affiliate/Ads, Affiliate/Dashboard, Affiliate/Orders, Affiliate/types, Awards (+ .css), BinaryList, BinarySummary (+ .css), BonusTransfer (+ .css), ChangePassword, Deposit, Epin, GuarantorRequests, InvestmentDetails (+ .css), InvestmentPortfolio (+ .css), KycInfo, MyProject, MyStages, MyTree, Notifications, ProfileSetting, PvLog (+ .css), Referrals, RepurchaseAward, Shop/Cart, Shop/Checkout, Shop/OrderShow, Shop/Orders, Shop/Products, Transactions, UsdtPay (+ .css), Withdraw (+ .css), WithdrawHistory, WithdrawPreview |
| Admin pages | 31 | `Pages/Admin/`: ActionLog, Ads/Form, Affiliate/Affiliates, Affiliate/Orders, Auth/Code, Auth/Forgot, Auth/Login, Auth/Reset, Dividend/Investors, Notifications, PvLog (+ .css), Reports/Logins, Reports/NotificationHistory, Reports/StageTwice.css, Shop/OrderDetail.css, Shop/Orders, Shop/ProductForm.css, Shop/Products, Shop/RepurchaseAwards, Shop/RepurchaseQualified, Shop/States, Stockist/OrderDetail.css, Stockist/Report, Stockist/ReportShow, Users/Detail.css, Users/List, Users/NotifyAll.css, Users/Stages.css, Users/Tree, WelcomePack/Index.css |
| Sign-in pages | 7 | `Pages/Auth/`: Banned (+ .css), ForgotPassword, Login, Register.css, ResetPassword, VerifyCode |
| Public shop and public pages | 8 | `Pages/Shop/`: Cart, Checkout, Index, OrderSuccess, Pay, Product; `Pages/Public/`: Agreement (+ .css) |
| Shared components | 27 | `Components/`: Admin/Cells, Admin/Drawer, Admin/LineChart, Admin/PageHeader, Admin/PermissionOpens, Admin/RichTextEditor, DividendStatus, EmptyState, GenealogyTree (+ .css), Lightbox, Modal, PageTitleBar, Pagination, PasswordInput, Shop/CopyNotice, Shop/ProductPrice, Shop/ShopCard, Shop/Sku, Shop/types, Shop/useAddToCart, Stockist/OrderStatus, Stockist/SlField, TextField, TreeSearch, VerificationCode (+ .css) |
| Page layouts | 8 | `Layouts/`: AdminAuthLayout (+ .css), AdminLayout.css, AuthLayout (+ .css), DashboardLayout, PublicLayout (+ .css) |
| Helpers, types, app start-up | 9 | `lib/`: clipboard, geo, i18n, initials, money, notify, search; `types/index.d.ts`; `app.tsx` |

**What the review checks for each file:**

- no wording, layout or images copied from ViserLab's original pages;
- it still works with the new back end, where data names may change;
- the Wiifarm rules it shows match the signed-off specification.
