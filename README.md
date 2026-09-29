# CloudMarket

### Branded ordering, local discovery, and merchant-operated fulfilment

CloudMarket helps local restaurants, hotels, cloud kitchens, meat shops, and other retailers accept digital orders under their own brand or through a shared marketplace. Merchants manage products, orders, pickup, and their own delivery teams. An optional QR Table Ordering add-on extends the same platform into restaurant dine-in service.

**The core proposition:** give local businesses a practical digital sales and operations system without requiring CloudMarket to operate a delivery fleet.

> **Document date: 22 September 2026.** This README combines the founder's stated business model with a review of the repository. “Code present” means implementation exists; it does not mean the feature has passed production acceptance testing. Commercial illustrations are assumptions, not actual revenue, forecasts, or market research. The repository is named `cloudstore`; the business/product name used here is **CloudMarket**.

## Contents

- [Business concept and target customers](#business-concept-and-target-customers)
- [Commercial models](#commercial-models)
- [Product experiences and workflows](#product-experiences-and-workflows)
- [Feature inventory and readiness](#feature-inventory-and-readiness)
- [QR Table Ordering add-on](#qr-table-ordering-add-on)
- [Architecture and configuration](#architecture-and-configuration)
- [Revenue scope and illustrative economics](#revenue-scope-and-illustrative-economics)
- [Release gaps and future enhancements](#release-gaps-and-future-enhancements)
- [Go-to-market and success measures](#go-to-market-and-success-measures)
- [Meeting and webinar material](#meeting-and-webinar-material)
- [Repository and engineering handover](#repository-and-engineering-handover)

## Business concept and target customers

Local merchants need a way to publish their catalog, accept orders, manage preparation, and maintain customer relationships. CloudMarket brings these tasks into a shared software platform with merchant-specific branding and access boundaries.

The intended customers include:

| Merchant | Primary use | Relevant capabilities |
| --- | --- | --- |
| Restaurants and hotels | Delivery, takeaway, and dine-in | Menu options, kitchen order board, tables, QR ordering |
| Cloud and home kitchens | Direct repeat ordering | Branded app, delivery zones, offers, pickup |
| Meat and fish shops | Portion and weight-based ordering | Priced weight variants, stock, merchant drivers |
| Bakeries, sweet shops, and juice shops | Catalog and local fulfilment | Categories, bundles, availability, promotions |
| Other local retailers | Direct digital sales | Products, customer records, checkout, local delivery |

These are target segments, not evidence of current paying customers or proven product-market fit in every segment. Specialized workflows such as variable final-weight billing and scheduled catering still need separate validation.

### What CloudMarket provides—and who fulfils the order

CloudMarket provides the ordering software, merchant administration, customer experience, and driver tools. The merchant prepares goods, handles pickup, and supplies its own drivers for delivery.

**CloudMarket does not currently promise an on-demand delivery workforce.** A future logistics partnership or delivery service would be a separate business expansion with additional costs and obligations.

“An app only for your shop” means a dedicated branded customer experience and tenant-scoped operations. The present architecture is a shared multi-tenant platform; it is not evidence of a separate server, database, or transferred source-code ownership for each client.

## Commercial models

The prices below reflect the founder's proposed packages. Support periods, taxes, hosting, app-store publication, renewal terms, and optional services must be defined in the customer agreement.

| | Branded merchant package | Marketplace merchant package |
| --- | --- | --- |
| Initial charge | ₹50,000 | ₹2,000 registration |
| Customer discovery | Merchant's dedicated branded app | Shared CloudMarket marketplace app |
| Administration | Tenant admin panel | Tenant admin panel |
| Driver tooling | Separate merchant driver app | Separate merchant driver app |
| Fulfilment | Merchant delivery and/or pickup | Merchant delivery and/or pickup |
| Intended platform order fee | No marketplace fee under the stated branded offer | ₹5 or 1% of eligible order value, whichever is higher |
| Optional expansion | QR tables and other separately priced services | QR tables and other separately priced services |

### Marketplace transaction fee

The intended marketplace rule is:

```text
fee = max(₹5, eligible_order_value × 1%)
```

Completed marketplace orders now use this formula through [MarketplaceFee.php](api/src/Modules/Order/Domain/MarketplaceFee.php). A forward migration changes the default ledger metadata to `one_percent_minimum_500_paise`; historical rows retain the fee rule stored when they were created.

| Order value | Fee: higher of ₹5 / 1% |
| --- | ---: |
| ₹200 | ₹5 |
| ₹500 | ₹5 |
| ₹1,000 | ₹10 |

Contract terms must define whether the fee base includes tax, delivery fees, discounts, and refunds. Existing code records fees on delivery/pickup completion; do not assume dine-in fees follow the same rule.

Plan selection and the fee ledger exist in code. Automated collection of the ₹50,000/₹2,000 onboarding charges, recurring subscriptions, and complete merchant settlement are not established by this review.

## Product experiences and workflows

### 1. Platform operator

The platform administrator creates tenants, selects branded or marketplace plans, enables capabilities, manages tenant administrators, configures integration credentials, initiates app builds, and reviews the marketplace fee ledger.

Capabilities act as **add-on switches**, not separately installed third-party plugins. This allows a hotel to enable QR ordering while a meat shop uses catalog, pickup, and merchant delivery.

### 2. Merchant operations

The merchant configures branding, business hours, store location, delivery zones, products, variants, stock, and offers. Staff receive orders, progress preparation, assign drivers, and manage customers and support requests.

The dashboard is being refined toward a tablet/POS experience: an icon rail, an app-tile launcher, grouped tools, touch-friendly actions, and mobile navigation. These UI changes are in progress; demonstrate a tested build rather than implying all screens already have the final design.

### 3. Branded customer app

The customer opens the merchant-specific app, chooses a location/address, browses products and offers, customizes items, and orders for pickup or delivery. The app includes order history, status tracking, profile/address management, favourites, reviews, and support screens.

Address selection and serviceability are especially important for delivery. Store coordinates and configured service zones are operational prerequisites, not just optional profile details.

### 4. Shared marketplace

Customers discover participating marketplace stores and enter a store's catalog. The discovery API can calculate distance and filter stores when coordinates are provided.

Current discovery uses geographic distance and delivery-zone logic. The API also has a no-coordinate path, so “every result is always location-verified” would overstate the implementation. Location requirements and pickup-only discovery need consistent end-to-end validation.

### 5. Merchant driver app

Drivers sign in, view assigned deliveries, update delivery stages and availability, submit location updates, and use delivery handoff/earnings functionality. The customer app can display driver location and an estimated arrival time.

The ETA presently uses a distance/speed estimate, not verified live traffic routing. Fresh location updates depend on permissions, device connectivity, and app lifecycle behavior.

### 6. Counter and dine-in

Counter staff select products, adjust quantities, record a payment method, and create an order. The current POS endpoint supports paid pickup sales; it rejects counter delivery without the customer address flow. Selecting cash/card/UPI here records the operator's payment choice—it is not proof of an integrated card terminal or confirmed online payment.

Dine-in uses the table/QR workflow described below. A combined “add items to this table from the counter” workflow should not be advertised as complete merely because both screens exist.

## Feature inventory and readiness

The following is a source-code inventory, not a production certification.

| Area | Code present | Qualification |
| --- | --- | --- |
| Tenant administration | Tenant lifecycle, commercial plans, admins, capability switches | Validate role and tenant isolation across every endpoint |
| Branding | Logo, primary color, slogan/settings, bootstrap data | Existing apps must contain compatible runtime branding code; native app identity changes need rebuilds |
| Catalog | Categories, products, images, variants, add-on groups | Validate variant/extra pricing and stock under concurrent orders |
| Inventory | Stock modes, quantities, stock alerts | Not a full purchasing, supplier, recipe/BOM, or warehouse system |
| Cart and ordering | Cart edits, checkout, order details, lifecycle/history | Test duplicate requests, retries, stale prices, and unavailable items |
| Fulfilment | Merchant delivery, pickup, GPS addresses, zones | Requires real store coordinates and verified service areas |
| Offers | Coupons, promotions, bundles | Test combinations, expiry, usage limits, and refund effects |
| Customer tools | Favourites, reviews, search suggestions, cancellation, reorder, invoice endpoint | Validate eligibility, access rules, and financial document requirements |
| Support | Customer tickets/messages and admin handling | Ticketing is not a demonstrated live driver-customer chat system |
| Notifications | In-app records, admin alerts, streaming/polling, mobile FCM hooks | Sound needs browser interaction; push needs Firebase setup and device testing |
| Delivery operations | Assignment, status changes, location, OTP handoff, earnings-related code | Not proof of production background tracking or payroll settlement |
| Payments/refunds | Razorpay-related backend initiation, webhook/refund code | Customer checkout integration remains incomplete; credentials and full sandbox testing are required |
| Analytics | Dashboard/report APIs, audit-log views, fee ledger | Validate figures against source orders; not a complete accounting suite |
| QR tables | Tables, tokens, visits, table codes, orders, receipts, printable QR UI | New module; broader acceptance and abuse-resistance testing remain necessary |
| App builds | Tenant build records, Android/iOS workflows, artifact handling | Build automation is not app-store approval or verified iOS release readiness |
| Configuration | Platform defaults and tenant overrides, encrypted stored values | Infrastructure bootstrap secrets still belong outside the database |
| Privacy/operations | Policy endpoints, account-deletion path, health checks, release workflow | Presence of these features does not establish compliance or an uptime guarantee |

### OTP, payment, maps, and monitoring dependencies

- **OTP:** generation, expiry, cooldown, and verification code exists. Production requires a delivery provider and disabling test-code behavior.
- **Payments:** the mobile checkout contains payment-information/redirect messaging, while the reviewed Flutter dependencies do not establish a complete gateway checkout integration. Demonstrate actual authorized payment, failure, cancellation, webhook, and refund paths before offering online payments as ready.
- **Maps:** Mappls-related settings and OpenStreetMap-based map components are present. Keys, allowed origins, service limits, and provider terms must be validated for the deployment. Do not promise unlimited free map usage.
- **Push/crash monitoring:** Firebase messaging/Crashlytics and Sentry dependencies exist. Configuration, event receipt, release association, and alert ownership require verification.

## QR Table Ordering add-on

### Merchant benefit

Guests browse and order from a table QR in their phone browser, with no app installation. The merchant maintains one product catalog while extending ordering to dine-in.

### Current flow

1. Platform admin enables `qr_table_ordering` for the tenant.
2. Merchant creates named tables and downloads/prints their QR stickers.
3. Staff opens a table visit when guests arrive.
4. Guests scan the persistent QR, browse items, and use the visit code where required by the configured flow.
5. The backend validates products/options, calculates totals, and creates a `dine_in` order.
6. Orders progress through confirmation, acceptance, preparation, ready, and served.
7. Further orders belong to the current table visit and contribute to its bill.
8. Staff completes outstanding orders, collects payment, and closes the visit.

QR links use opaque tokens; tables can be disabled or have their QR replaced. Visit codes reduce misuse from previously photographed QR links. Tokens/codes are not a substitute for rate limits and abuse monitoring.

### Boundaries and next steps

- Pay-at-counter is the initial workflow; integrated pay-at-table is a future extension.
- Bill closure and payment recording are operator actions, not automatic bank reconciliation.
- Split bills, table transfers/merges, reservations, waiter permissions, and kitchen-printer integration are expansion opportunities.
- Guest identification and any phone-based customer lookup need a privacy/security review before using real customer history in public QR flows.
- Pricing for this add-on is **not yet fixed**. Sell it as a clearly scoped optional feature rather than implying it is included in every plan.

## Architecture and configuration

```text
Platform admin / Merchant admin / QR browser menu (Vue + TypeScript)
                           │
Customer / Driver / Marketplace apps (Flutter)
                           │
                    PHP REST API
                           │
              MySQL tenant-scoped data
                           │
       Optional providers: OTP, payments, maps, push
```

- **Backend:** custom PHP 8.2+ application, modules/controllers/services/repositories, JWT authentication, MySQL, migrations, and PHPUnit tests.
- **Admin and browser ordering:** Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios, and QR generation.
- **Mobile:** shared Flutter codebase with `customer`, `driver`, and `marketplace` modes; location, mapping, notification, and error-reporting dependencies.
- **Release automation:** GitHub Actions build workflows and a release/QA workflow; deployment scripts and health endpoints.
- **Operational configuration:** encrypted database records with tenant-specific values taking precedence over platform defaults, then environment fallback. Platform scope is `tenant_id = 0`.

Database configuration does not eliminate server bootstrap configuration: database access, encryption roots, and signing/deployment infrastructure still need securely managed external values. Browser map keys and mobile app identification tokens must not be mistaken for privileged server credentials.

### Identity and isolation

A tenant is a merchant boundary for catalog, orders, customers, branding, and settings. Customer and driver authorization is distinct from the app's tenant identification token. Driver package IDs are differentiated from customer IDs in the Android build flow so both can coexist on one device.

Separate branded apps share platform code and infrastructure. Dedicated infrastructure, data export commitments, source ownership, support SLAs, and exit arrangements are commercial decisions requiring explicit agreements.

## Revenue scope and illustrative economics

### Established proposal versus optional future income

| Revenue stream | Commercial status | Considerations |
| --- | --- | --- |
| ₹50,000 branded setup | Founder-proposed core offer | One-time revenue; includes onboarding/build/support costs |
| ₹2,000 marketplace registration | Founder-proposed core offer | One-time revenue; not recurring subscription income |
| Marketplace order fee | Founder-proposed transaction revenue | Correct the minimum/cap discrepancy first |
| Hosting/support maintenance | Optional future package | Define renewal, service scope, and support limits |
| QR Table Ordering | Optional add-on | Setup/annual pricing to validate with hotel clients |
| Staff training/catalog setup | Optional service | Labor-intensive; price against delivery cost |
| Advanced reporting, multi-branch tools | Future premium modules | Only sell as available after implementation/acceptance |
| Integrations and custom work | Optional project revenue | Quote separately; avoid unlimited customization promises |

### Illustrative revenue scenarios—not forecasts

These examples use a 30-day month and the **intended** minimum-₹5 rule. All merchants/order volumes are hypothetical; there is no claim that these customers or orders exist.

| Scenario | Assumptions | Arithmetic | Gross revenue illustration |
| --- | --- | --- | ---: |
| Branded onboarding cohort | 10 newly sold packages | 10 × ₹50,000 | ₹5,00,000 one-time |
| Marketplace registration cohort | 100 new merchants | 100 × ₹2,000 | ₹2,00,000 one-time |
| Smaller active marketplace | 100 merchants, 10 completed eligible orders/day each, ₹300/order | 100 × 10 × 30 × ₹5 | ₹1,50,000/month |
| Larger active marketplace | 250 merchants, 20 completed eligible orders/day each, ₹300/order | 250 × 20 × 30 × ₹5 | ₹7,50,000/month |
| Higher basket example | 100 merchants, 10 completed eligible orders/day each, ₹800/order | 100 × 10 × 30 × ₹8 | ₹2,40,000/month |

For the smaller marketplace example, merchant GMV would be ₹90,00,000/month, but platform transaction revenue would be ₹1,50,000—not ₹90,00,000. GMV is the value of goods sold, not CloudMarket revenue. The same scenario under the current capped code would generate ₹90,000 in fee records, illustrating why the pricing discrepancy matters.

Do not add these scenarios together as if they were a forecast. Do not call setup collections ARR. Order-fee revenue is variable; it is not contracted subscription ARR.

### Cost and sustainability model

Budget for hosting/database/backups, images and bandwidth, messaging, provider usage, build infrastructure, app publication/support, merchant onboarding, sales commissions, customer service, refunds/disputes, and engineering maintenance. Payment processing costs must have an explicit payer.

```text
Contribution per merchant = recurring fees + transaction fees
                          − attributable hosting/provider/support costs

Setup contribution = setup charge − onboarding/build/training/acquisition costs
```

The ₹50,000 package should not silently imply unlimited lifetime hosting, support, and upgrades. The ₹5 floor also needs a real support-cost model: small ticket fees can be consumed by manual support work.

## Release gaps and future enhancements

### Priority 0: earn a reliable paid pilot

1. Finalize marketplace fee contract terms and validate the corrected fee ledger on staging.
2. Complete provider-backed OTP and online payment acceptance tests; keep development OTP out of production.
3. Test stock, options, taxes, offers, retries, cancellations, and refunds together—not just screen rendering.
4. Verify saved addresses, chosen-location continuity, serviceability, pickup behavior, and marketplace no-location behavior.
5. Verify driver assignment, pickup, handoff, location freshness, stale-location messaging, and completion on physical devices.
6. Audit public QR identification, cross-tenant access, token handling, and rate limits.
7. Validate database migrations and restore backups in staging; verify production rollout/rollback procedures.
8. Finish and stabilize the POS/navigation redesign; perform tablet, desktop, mobile, keyboard, and long-list testing.

### Priority 1: merchant operating depth

- Staff roles, shift reconciliation, cash drawer handling, and permission-aware workflows.
- Unified staff-assisted table ordering, split/merged bills, table transfers, and kitchen display/printing.
- Complete receipts/invoices, refunds, reconciliation, settlement reporting, and appropriate accounting integrations.
- Better stock reservations/restoration, product availability schedules, and operational reports.
- Production release monitoring, actionable alerts, support runbooks, and measurable onboarding time.

### Priority 2: repeat business and expansion

- Loyalty implementation beyond capability switches; customer segmentation and consent-based campaigns.
- Multi-branch management, centralized catalogs, and franchise reporting.
- Scheduled/preorders, subscriptions, repeat baskets, and merchant-specific vertical workflows.
- Traffic-aware routing, richer service areas, route planning, and reliable background tracking.
- Supplier/purchasing, recipe costing, wastage, and inventory accounting where customer demand justifies it.
- Better low-connectivity support; do not promise offline order acceptance without stock/payment synchronization design.

### Longer-term options

Demand forecasting, assisted catalog creation, merchant business insights, partner APIs, and optional logistics partnerships are potential directions. These are roadmap ideas, not current AI capabilities or operational services.

## Go-to-market and success measures

A focused first rollout is easier to validate than simultaneous entry into every merchant category. Start with a small local cohort of restaurants and shops that already handle pickup or delivery. Use QR tables as a hotel/restaurant entry point, then offer branded ordering where repeat customers justify it.

Suggested sequence: merchant interview → catalog/location setup → controlled test orders → staff training → paid pilot → weekly review → case study with permission → local referrals.

Measure:

- Time from signup to first successful order.
- Active merchants and retained merchants, not just registered tenants.
- Successful checkout/completion rates and reasons for failure.
- Orders and repeat customers per active merchant.
- Merchant acquisition cost and onboarding/support hours.
- Average order value, collected fees, and contribution after service costs.
- Cancellation/refund rate and payment reconciliation differences.
- App crash-free sessions, API errors/latency, and notification delivery.
- QR scan-to-order conversion and repeat orders per table visit.

No actual traction, market size, customer savings, valuation, or audited revenue is established in this README. Add verified figures with dates and sources before using them in an investor deck.

## Meeting and webinar material

### Thirty-second introduction

> CloudMarket helps local businesses take digital orders through their own branded app or our shared marketplace. Merchants manage their catalog, customers, pickup, and their own delivery staff from one system. For restaurants, QR table ordering also lets guests scan, browse, and order without installing an app. We aim to combine affordable digital ordering with practical day-to-day merchant operations.

### Client meeting: lead with the merchant's workflow

Ask how orders arrive today, who delivers, what causes missed orders, whether diners reorder, and who updates the menu. Then demonstrate only the relevant journey: branded ordering for repeat customers, marketplace participation for shared discovery, or QR tables for dine-in.

Discuss package scope, merchant responsibilities, add-on charges, support, app publication, and provider costs before discussing advanced roadmap items.

### Suggested 25-minute webinar

| Time | Segment | Demonstration |
| --- | --- | --- |
| 0–3 min | The local merchant problem | Fragmented ordering and operational work |
| 3–6 min | Two commercial choices | Branded app versus marketplace |
| 6–11 min | Customer journey | Location, catalog, customization, checkout, status |
| 11–16 min | Merchant operations | Order acceptance, preparation, pickup/driver flow |
| 16–20 min | QR tables | Scan, order, repeat items, serve, close bill |
| 20–23 min | Packaging and onboarding | Responsibilities, optional add-ons, pilot scope |
| 23–25 min | Questions and next step | Offer a scoped pilot/demo |

Use a test tenant and test orders. Configure products, coordinates, zones, table visit, and staff access in advance. If payment/driver tracking is not verified for the demo build, show the supported cash/pickup flow and clearly identify the planned integration.

### Suggested investor deck structure

1. Customer problem and initial merchant segment.
2. Product and one complete verified order journey.
3. Branded/marketplace distribution choices.
4. Merchant-owned fulfilment and operating responsibilities.
5. Pricing, fee rule, and cost assumptions.
6. Actual pilot results and retention, when available.
7. Acquisition channel and onboarding capacity.
8. Alternatives and differentiation supported by customer interviews.
9. Engineering readiness, dependencies, and release risks.
10. Funding use, milestones, and a specific ask grounded in a budget.

Potential differentiation is merchant branding, multiple ordering channels, and merchant-operated fulfilment in one platform. Do not claim this is unique or cheaper than every competitor without a dated competitor study. The strongest future evidence will be merchant retention and operational results, not the number of features listed.

### Questions to be ready to answer

| Question | Honest answer |
| --- | --- |
| Who delivers? | The merchant and its own drivers; CloudMarket supplies tools. |
| Is the branded app exclusive to my shop? | Its customer experience is shop-specific; the underlying platform is shared. |
| Can guests order without an app? | Yes, via the QR table browser flow when enabled and set up. |
| Is this a complete accounting/POS replacement? | Not yet demonstrated; counter ordering exists, while deeper accounting/hardware workflows require scope. |
| Are payments and push ready automatically? | No. Provider setup and end-to-end verification are required. |
| Does the platform own a delivery fleet? | No current fleet service is included. |
| Is the ₹50,000 charge recurring? | The stated proposal is an initial package charge; recurring terms must be agreed separately. |
| Is the marketplace fee finalized in software? | No—the current cap conflicts with the intended minimum and must be corrected. |
| Is the app bug-free or fully production-certified? | No such claim is supported by this source review. |

## Repository and engineering handover

| Path | Purpose |
| --- | --- |
| [`admin/`](admin/) | Vue admin dashboard and public QR browser UI |
| [`api/`](api/) | PHP application, routes, modules, tests, migrations |
| [`mobile/`](mobile/) | Flutter customer, driver, marketplace experiences |
| [`public_html/`](public_html/) | Deployment frontend assets |
| [`api/public/`](api/public/) | API public entry point and served assets |
| [`.github/workflows/`](.github/workflows/) | Android/iOS builds and release automation |
| [`scripts/`](scripts/) | Deployment/backup utilities |
| [`docs/release-qa-agent.md`](docs/release-qa-agent.md) | Release workflow setup and device-QA boundaries |
| [`docs/pilot-readiness.md`](docs/pilot-readiness.md) | Paid-pilot release gates, provider acceptance, and evidence log |

### Primary evidence used for this overview

- [API routes](api/routes/api.php): exposed platform, admin, customer, driver, and dining interfaces.
- [Tenant domain](api/src/Modules/Tenant/Domain/Tenant.php) and [capability repository](api/src/Modules/Tenant/Repository/CapabilityRepository.php): tenant model and feature flags.
- [Marketplace controller](api/src/Modules/Platform/Controller/MarketplaceController.php): discovery and location filtering.
- [Order management](api/src/Modules/Order/Service/OrderManagementService.php): lifecycle and fee calculation.
- [Dining controller](api/src/Modules/Dining/Controller/DiningController.php): table visits and guest ordering.
- [Counter checkout](api/src/Modules/Order/Controller/AdminPosController.php): POS scope and payment recording.
- [Payment service](api/src/Modules/Payment/Service/PaymentService.php) and [mobile checkout](mobile/lib/screens/checkout/checkout_screen.dart): payment implementation and remaining client integration.
- [Order controller](api/src/Modules/Order/Controller/OrderController.php): driver location/ETA information.
- [Operational configuration](api/src/Modules/Platform/Service/OperationalConfig.php): configuration precedence and decryption.
- [Mobile configuration](mobile/lib/config/app_config.dart), [Flutter dependencies](mobile/pubspec.yaml), and [Android workflow](.github/workflows/build-android.yml): modes, dependencies, and build settings.

### Development verification

Use a development/staging environment with non-production configuration. Common project commands:

```sh
# Backend, from api/
composer install
composer test

# Admin, from admin/
npm ci
npm run build
npx playwright test

# Mobile, from mobile/
flutter pub get
flutter analyze
flutter test
```

The backend console entry point is `api/bin/console`; inspect its available commands before applying migrations. Match the mobile build environment to the workflow rather than assuming a developer's newer Flutter SDK is compatible. The checked-in Android workflow currently pins Flutter 3.27.4, while `pubspec.yaml` declares a broader minimum.

The test directories provide useful coverage, but tests must be run against the intended version/environment. Browser tests with mocked APIs do not prove database integrity, real payment completion, notification delivery, or physical-device tracking.

**Documentation review scope:** local source and workflow inspection. This task did not deploy code, audit live customer data, run an independent penetration test, certify compliance, or validate actual revenue. Keep this document updated after each tested release and replace assumptions with measured pilot evidence.
