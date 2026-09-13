# CloudMarket Release & QA Agent

This is the first CloudMarket agent: a deliberately narrow, production-safe release operator. It is a GitHub Actions workflow at `.github/workflows/cloudmarket-release-qa.yml`, started manually from the Actions tab.

It performs these actions in order:

1. Requires the operator to type `DEPLOY` and pass the GitHub `production` environment approval gate.
2. Calls the existing one-click deployment endpoint without printing its secret URL.
3. Checks the public live and readiness API endpoints, which also confirms database migrations completed.
4. Logs in with a platform-admin account stored only as GitHub secrets.
5. Confirms the selected tenant exists, creates the Android build through the platform API, and waits for the existing build callback.
6. Writes a release report with the completed artifact URL to the GitHub Actions job summary.

It **cannot** rotate a tenant app token, edit tenants, create orders, change prices, or send a phone's GPS location. It also does not install an APK on a device: a hosted GitHub runner has no access to your USB or wireless-debugging phone. That keeps real customer data and physical device permissions under human control.

## One-time GitHub setup

1. Push the workflow to the repository used by the server's `GITHUB_REPO` setting.
2. In GitHub, open **Settings → Environments → New environment**, create `production`, and add yourself as a required reviewer. This is the approval gate before the deploy call can use any production secret.
3. Add these **environment secrets** to `production` (not repository variables):

   | Secret | Value |
   | --- | --- |
   | `CLOUDMARKET_DEPLOY_URL` | The complete private HTTPS URL of your deployment endpoint, including its deploy key. |
   | `CLOUDMARKET_API_BASE_URL` | The public base API URL ending in `/api/v1`. |
   | `CLOUDMARKET_PLATFORM_EMAIL` | A dedicated platform-admin login email. |
   | `CLOUDMARKET_PLATFORM_PASSWORD` | That dedicated account's password. |

4. On the server, ensure `GITHUB_TOKEN`, `GITHUB_REPO`, `APP_URL`, and `BUILD_WEBHOOK_SECRET` are configured. Those are consumed by the existing backend build dispatch and callback; do not place them in the workflow.
5. Prefer a dedicated platform-admin account for automation. If credentials or a deploy URL were previously shared in chat or committed anywhere, rotate them before enabling this workflow.

## Running a release

Open **Actions → CloudMarket Release & QA Agent → Run workflow** and enter:

- `confirmation`: `DEPLOY`
- `tenant_uuid`: the tenant UUID shown in Platform Admin
- `app_name`: branded app display name
- `app_id`: unique lower-case Android identifier, for example `com.cloudmarket.hotelabc`
- `app_mode`: `customer` or `driver`
- `build_type`: normally `apk`
- `primary_color`: the tenant brand colour, for example `#2E7D32`

Read the job summary after it finishes. Download its artifact only from the reported authenticated build/share URL.

## Human device QA checklist

After installing the APK on a non-production test device, verify the full branded-app journey:

- Bootstraps on both Wi-Fi and mobile data.
- OTP fallback works when SMS is not configured.
- Categories, products, offers, promotions, and bundles load with correct images and prices.
- Product variant/add-on selection and cart quantity changes update totals correctly.
- GPS-based address selection checks delivery serviceability before checkout; do not use a real customer's address.
- Pickup and delivery checkout validation behaves correctly. Do not submit a real order during routine release QA.
- Profile, saved addresses, notifications, and prior orders load without errors.

## What makes it an agent, and what comes next

This first version is workflow automation with explicit guardrails. That is the right foundation: it has narrow authority, repeatable tools, an approval checkpoint, and a written result. A later **AI QA reviewer** can consume the release report, sanitized server logs, screenshots, and test results to explain failures and suggest fixes. OpenAI's Agents SDK is designed around instructions, tools, guardrails, and tracing, which fits that second phase well. See the [official Agents SDK overview](https://openai.github.io/openai-agents-python/).

Do not give a future AI reviewer direct production database, deployment-key, customer-order, token-rotation, or device-GPS authority. Keep those actions as explicitly approved tools.
