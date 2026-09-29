# Controlled paid pilot readiness

This is the release gate for one merchant pilot. A release is ready only when every required row has evidence from the same deployed version.

## Scope

- One tenant, one catalog, one configured store location, and one active delivery zone.
- Customer delivery and pickup, merchant dashboard order handling, and merchant driver delivery.
- Cash on delivery plus Razorpay test mode before any live online payment.
- QR dine-in is tested separately when enabled for the pilot tenant.

## Automated gates

| Gate | Current evidence | Required result |
| --- | --- | --- |
| Admin production build | `npm run build` | Pass |
| Admin responsive workflow | `npx playwright test e2e/workspace-navigation.spec.ts` | Pass at desktop, tablet, and phone widths |
| API unit suite | `php vendor/bin/phpunit --testsuite Unit` | Pass |
| Mobile static analysis | `dart analyze` | No errors or warnings |
| Android build | `flutter build apk --debug` during development; signed release workflow for pilot | Pass |
| Database migration rehearsal | Fresh staging database plus upgrade copy of staging database | Migrations apply once and rollback/restore is demonstrated |

## Server-backed merchant journey

Record the timestamp, order number, device/build version, and tester for each run.

1. Configure merchant branding, business hours, payment methods, store GPS point, delivery zone, minimum order, tax, and charges.
2. Create a limited-stock product with a variant and an unlimited-stock product. Add images, option groups, an offer, and a coupon.
3. Customer chooses and saves a GPS address. Restart the app and confirm the same default address and serviceability remain selected.
4. Add products and variants, change quantities, apply and remove a coupon, and verify subtotal, discount, tax, delivery charge, and total against a manual calculation.
5. Submit checkout twice using the same retry request. Confirm one order, one stock decrement, and one admin alert.
6. Accept the order, prepare it, assign an active driver, mark it ready, pick it up, and confirm that customer status changes without reopening the app.
7. Update driver location and confirm map location, freshness, and ETA. Complete delivery using the delivery handoff rule.
8. Place a pickup order and complete the pickup status path without showing driver controls.
9. Cancel a limited-stock order and reject another. Confirm inventory is restored exactly once in both cases.
10. Complete one marketplace-plan order and confirm the ledger records `max(500 paise, round(total / 100))` once.

## Provider acceptance

### OTP

- Production has `APP_ENV=production` and test OTP mode disabled.
- SMS provider credentials are stored in Platform Settings, not returned to clients or logs.
- Test valid, invalid, expired, throttled, provider failure, and resend flows on a real phone.

### Razorpay

- Use Razorpay test keys and a unique webhook secret in Platform Settings.
- Configure `/api/v1/webhooks/payment` in the Razorpay test dashboard.
- Test success, customer cancellation, payment failure, retry, duplicate callback, invalid signature, delayed webhook, and app restart.
- Confirm checkout signatures use the API key secret and webhook signatures use the raw request body plus webhook secret.
- Test a completed refund and a gateway-pending refund. A pending refund must remain pending for reconciliation.
- Switch to live credentials only after matching the Razorpay dashboard, local payment record, order status, refund record, and merchant report.

## Operational pilot gate

- Train the merchant owner and at least one staff member using the same build.
- Verify audible new-order alerts on the merchant's real browser/tablet and push notifications on customer and driver devices.
- Keep a second contact method available during the pilot and record every failed or manually corrected order.
- Take a database backup and perform a staging restore before launch.
- Define support hours, cancellation/refund responsibility, provider fees, taxes, subscription/setup scope, and marketplace fee base in writing.
- Start with a limited ordering window and order volume. Review the first day before expanding hours.

## Stop conditions

Pause new paid orders if duplicate orders occur, stock becomes negative, payments and orders disagree, tenant data crosses boundaries, driver handoff cannot be verified, or backups cannot be restored. Fix the cause, repeat the entire affected journey, and attach new evidence before resuming.

## Pilot evidence log

| Date/time | Version/commit | Tenant | Journey | Order/payment reference | Result | Tester | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| | | | | | | | |

