# Taala Crystal — implementation and production launch plan

Updated: 29 October 2026. Branch: `taala-v2-development`.

## How we will work

Implement the numbered increments in order, preserving existing data and audit history. For each increment: inspect existing behavior → implement → add focused tests for consequential behavior → run focused and affected regression tests → owner browser verification → update README and this plan. Fix failures before moving on. Do not claim local tests were run when only static checks were possible. Deployment is a separate final gate.

Completed baseline: V2 inventory, production, sales, purchasing, delivery workflows and accounting are implemented. Production expense allocation was owner-verified with 154 tests / 1,216 assertions across its requested test groups plus `production cost allocation ok`. Historical inventory valuation was owner-verified with: 14 focused tests / 117 assertions and 72 regression tests / 576 assertions (86 / 693 total), followed by `historical inventory valuation ok`. Customer and supplier statements were owner-verified: 21 focused tests / 227 assertions plus 73 regressions / 495 assertions (94 / 722 total), followed by `customer supplier statements ok`. Outstanding balances and ageing are now the latest owner-verified milestone: migration succeeded, 25 focused tests / 220 assertions plus 147 regressions / 1,060 assertions (172 / 1,280 total) passed locally, followed by `customer supplier ageing ok`. These overlapping milestone groups are not cumulative unique-test totals. A current full-suite and staging run remain outstanding.

Status legend: **Owner-verified** means focused tests, affected regressions and owner browser checks passed; **In progress** means implementation underway; **Pending verification** means delivered but not yet owner-verified; **Planned** means not yet delivered. Existing functionality will be reviewed and reused rather than rebuilt unnecessarily.

## 1. Accounting completion

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 1.1 | Historical inventory valuation by date; opening values plus recorded quantity/value movements; reconciliation to posted account 1300 | Inclusive cutoff, reversals on their own dates, inactive items, no invented pre-activation values; report totals reconcile and differences remain visible | Owner-verified — 86 tests / 693 assertions and browser confirmation |
| 1.2 | Customer and supplier statements | Opening balance + dated activity = closing balance; only authorized records visible | Owner-verified — 94 tests / 722 assertions and browser confirmation |
| 1.3 | Outstanding balances and ageing with configurable party terms and transaction due-date overrides | Journal-dated settlements/reversals, saved due dates, unknown-date bucket, signed credits and control coverage | Owner-verified — 172 tests / 1,280 assertions, successful migration and browser confirmation |
| 1.4 | Financial report PDF/Excel exports | Export totals/date ranges match screen; authorized access and safe user text | Planned |
| 1.5 | Review missing payment/receipt correction workflows; implement agreed audited corrections | No destructive editing, duplicate stock effects, duplicate refunds or lost audit history | Planned |
| 1.6 | Period locks and controlled reopening; review closing-entry treatment | All relevant posting/reversal paths respect locks; reopen permission and audit verified | Planned |
| 1.7 | Accountant review of opening balances, invoices, taxes and required financial workflows | Business approves opening cash/bank, receivables, payables, inventory and equity; day-one requirements documented | Planned |

Increment 1.3 rules agreed: optional calendar-day terms per customer/supplier (0 means same-day; blank means unknown), snapshotted due dates on new sales drafts and individual goods receipts, optional transaction override, and no backfill of existing dates. Current/due-today, 1–30, 31–60, 61–90, 91+ and unknown-date buckets stay separate from signed credits. Payments/reversals follow posted journal dates and explicit document links. Historical date corrections/backfill require a separately agreed audited workflow.

Do not add automatic overhead rates or other accounting automation unless separately agreed. Existing allocation of recorded production expenses remains supported.

## 2. Roles, account access and sessions

First audit existing authentication and permissions. Password-reset scaffolding may already exist; finish and test the real email flow rather than duplicating it.

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 2.1 | Agree permission matrix and migrate existing role assignments safely | Existing admin/driver accounts remain usable; production and purchase approval responsibilities explicitly assigned | Planned |
| 2.2 | Enforce the role matrix in routes, controllers/services, menus and exports | Direct URLs and submissions cannot bypass permissions; customer/driver records isolated | Planned |
| 2.3 | Forgot password, expiring email reset links, signed-in password change and account deactivation | Real email delivery, invalid/expired/reused tokens and deactivated users tested | Planned |
| 2.4 | One-minute inactivity logout with short warning and Stay signed in action | Server rejects expired sessions; passive polling cannot keep users signed in; browser tabs and long forms tested; prevent accidental loss of sensitive data | Planned |

### Agreed role list

| Role | Intended scope, to be finalized in 2.1 |
| --- | --- |
| Super Admin | System configuration and role/account administration; no automatic payroll access |
| Admin | Operational oversight and explicitly assigned approvals |
| User / Customer | Own customer portal and own records only; ordering/self-registration scope to confirm |
| Stock Manager | Stock, receiving and authorized adjustments |
| Sales Manager | Sales, customers and sales reports |
| Accountant | Authorized financial reports, journals, payments and expenses; no automatic payroll access |
| Drivers | Their own assigned deliveries and delivery updates |
| Guest | Public website only; no internal business records |

Production recording/reversal, purchase requests and purchase approvals must be assigned explicitly to these roles. These assignments are not silently inferred from role names.

## 3. V2 dashboard, search and settings

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 3.1 | Replace legacy dashboard metrics with V2 sales, collections, stock, production, deliveries and permitted accounting figures | Cards and charts agree with underlying reports and distinguish sales from collections | Planned |
| 3.2 | Permission-aware search with clear results and filters | Search products, customers, suppliers and operational references without leaking restricted records or payroll | Planned |
| 3.3 | Personal settings: profile, light/dark/system theme, security and useful notifications; restricted company settings | Preferences persist; contrast and role restrictions verified | Planned |

## 4. Guidance, Help and responsive interface

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 4.1 | Short role-aware menu tour for new users; dismiss on a key press or click; replay from Help | Dismissal does not submit underlying forms; keyboard/mobile usable; critical confirmations remain separate | Planned |
| 4.2 | Searchable Help with workflows, screenshots, permissions and troubleshooting | Instructions match delivered features; restricted content is not exposed publicly | Planned |
| 4.3 | Responsive and accessibility review of all public/internal screens | Phone/tablet/desktop, keyboard, form errors, table overflow, themes and driver flows tested | Planned |

Responsive implementation is required throughout; 4.3 is the cross-system review, not the first time mobile support is considered.

## 5. Public website and customer contact

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 5.1 | Home, About Us and Contact Us pages; approved business content, images and login link | Public visitors can navigate without authentication; no internal records exposed | Planned |
| 5.2 | Contact details and location map | Business confirms address, map pin, hours and contact channels; works on mobile | Planned |
| 5.3 | FAQ chatbot before human handoff | Answers only approved business information; visitor can request an agent; unknown answers and unavailable agents handled clearly | Planned |

Choose the human handoff channel, hours, notification owner and transcript retention before implementation. Do not imply a human is online when none is available. External provider choices and paid subscriptions are separate decisions.

## 6. Restricted payroll payments

A payments-focused payroll module will live inside the same application. Detailed rules will be supplied by the owner before implementation. Do not assume statutory payroll, tax calculation, bank transfers or automatic salary disbursement is included.

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 6.1 | Gather the designated email, payee records, payment periods, amounts, approval/correction rules and accounting treatment | Written scope approved; distinguish recording a payment from actually sending money | Planned — needs owner details |
| 6.2 | Restrict access to one designated verified account | Account identity and verified designated email enforced on every page/action/export; no automatic Admin/Super Admin access; controlled authorized-person replacement | Planned |
| 6.3 | Implement agreed payment records, corrections, reports and accounting linkage | No duplicate payments or expense posting; restricted audit trail; payroll excluded from general search, dashboards, Help and notifications for unauthorized users | Planned |

The application restriction cannot prevent access by someone who independently controls the server or database. Infrastructure access must be limited separately. Strong account protection and a controlled recovery procedure are part of this module.

## 7. Operational readiness, meter and data cutover

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 7.1 | Full daily workflow review with staff, including returns, refunds, shortages, damage and corrections | Each day-one scenario has a tested feature or documented approved procedure | Planned |
| 7.2 | Real meter integration and outage handling | Device protocol/units/baseline confirmed; physical readings, duplicates, outages and reconnects tested; approved alternative required if launch proceeds without device integration | Planned — needs device details |
| 7.3 | Clean master data and rehearse migration/opening balances | Products, recipes, prices, parties and staff approved; quantities and balances reconciled; no guessed historical costs | Planned |
| 7.4 | Retire conflicting legacy entry points and prepare cutover | One source of truth; history retained read-only as agreed; migration rehearsed and reversible where possible | Planned |

Gather hardware and migration information early while software increments proceed. Never blindly seed test data or delete historical data on the live database.

## 8. Release engineering and staging verification

| Increment | Work | Completion check | Status |
| --- | --- | --- | --- |
| 8.1 | Full current test suite and integration testing on MySQL staging | All required tests pass; migrations work from a clean install and representative prior version | Planned |
| 8.2 | Security and dependency review | Authentication, role isolation, exports/uploads, secrets, meter endpoints, payroll boundary and session expiry verified | Planned |
| 8.3 | Realistic volume and concurrency tests | Stock/payment races, duplicate submissions and slow pages resolved; acceptable performance agreed | Planned |
| 8.4 | Hosting, domain/HTTPS, email, production configuration, required workers/schedules | Staging resembles production; secrets excluded from source; debug disabled; services restart reliably | Planned |
| 8.5 | Monitoring, backups, recovery and deployment runbooks | Off-server backup policy agreed; restore successfully demonstrated; alerts routed; rollback/recovery rehearsed | Planned |

## 9. Pilot and launch

1. Train each role using role-specific Help and practice data.
2. Run a controlled pilot; reconcile cash, stock, receivables and payables daily.
3. Resolve defects and obtain owner/accountant approval of required workflows and balances.
4. Approve cutover date; take a final backup, apply the rehearsed migration and verify opening data.
5. Deploy a tagged release and run role-specific smoke tests.
6. Monitor closely during launch and assign responsibility for support, backups and incident response.

**Launch gate:** required scope verified; current full suite and MySQL staging checks passed; no unresolved critical defects; real data reconciled; backups restored successfully; meter operation or agreed fallback ready; staff trained; owner approves deployment.

## Immediate next action

**1.1 Historical inventory valuation** and **1.2 Customer and supplier statements** are complete and owner-verified. **1.3 Outstanding balances and ageing** is also complete and owner-verified: migration and all 172 tests / 1,280 assertions passed locally, followed by `customer supplier ageing ok`. Next: **1.4 Financial report PDF/Excel exports**, reusing report services so exported filters, totals and warnings match the screen, with authorization and safe spreadsheet text verified. Do not invent contractual due dates or label unknown terms overdue. Keep this document and README synchronized as each milestone completes. No delivery dates are promised before hardware, payroll, external-service and business-policy dependencies are known.
