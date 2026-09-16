#!/usr/bin/env bash
# Build customer + driver APKs for all tenants defined in tenants.json.
#
# tenants.json format:
# [
#   {
#     "slug": "jeyam-mutton",
#     "app_token": "tok_abc123",
#     "api_url": "https://market.cloudkart24.com/api/v1",
#     "app_name": "Jeyam Mutton",
#     "app_id": "com.jeyammutton",
#     "primary_color": "#E23744",
#     "sentry_dsn": "",
#     "build_driver": true
#   }
# ]
#
# Usage: ./scripts/build_all_tenants.sh tenants.json

set -euo pipefail

CONFIG_FILE="${1:-tenants.json}"

if [ ! -f "$CONFIG_FILE" ]; then
  echo "Error: Config file not found: $CONFIG_FILE"
  echo "Usage: $0 tenants.json"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
COUNT=$(python3 -c "import json; print(len(json.load(open('$CONFIG_FILE'))))" 2>/dev/null || \
        python -c "import json; print(len(json.load(open('$CONFIG_FILE'))))")

echo "Building APKs for $COUNT tenant(s)..."
echo ""

for i in $(seq 0 $((COUNT - 1))); do
  SLUG=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['slug'])" 2>/dev/null || \
         python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['slug'])")
  TOKEN=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['app_token'])" 2>/dev/null || \
          python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['app_token'])")
  API_URL=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['api_url'])" 2>/dev/null || \
            python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['api_url'])")
  APP_NAME=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['app_name'])" 2>/dev/null || \
             python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t['app_name'])")
  APP_ID=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t.get('app_id',''))" 2>/dev/null || \
           python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t.get('app_id',''))")
  COLOR=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t.get('primary_color','#E23744'))" 2>/dev/null || \
          python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t.get('primary_color','#E23744'))")
  SENTRY=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t.get('sentry_dsn',''))" 2>/dev/null || \
           python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print(t.get('sentry_dsn',''))")
  BUILD_DRIVER=$(python3 -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print('true' if t.get('build_driver',True) else 'false')" 2>/dev/null || \
                 python -c "import json; t=json.load(open('$CONFIG_FILE'))[$i]; print('true' if t.get('build_driver',True) else 'false')")

  echo "=== Building for tenant: $SLUG ==="

  EXTRA_ARGS=()
  [ -n "$APP_ID" ] && EXTRA_ARGS+=("--app-id" "$APP_ID.customer")
  [ -n "$SENTRY" ] && EXTRA_ARGS+=("--sentry-dsn" "$SENTRY")

  "$SCRIPT_DIR/build_customer.sh" \
    --app-token "$TOKEN" \
    --api-url "$API_URL" \
    --app-name "$APP_NAME" \
    --primary-color "$COLOR" \
    "${EXTRA_ARGS[@]}"

  if [ "$BUILD_DRIVER" = "true" ]; then
    DRIVER_ARGS=()
    [ -n "$APP_ID" ] && DRIVER_ARGS+=("--app-id" "$APP_ID.driver")
    [ -n "$SENTRY" ] && DRIVER_ARGS+=("--sentry-dsn" "$SENTRY")

    "$SCRIPT_DIR/build_driver.sh" \
      --app-token "$TOKEN" \
      --api-url "$API_URL" \
      --app-name "$APP_NAME Driver" \
      --primary-color "$COLOR" \
      "${DRIVER_ARGS[@]}"
  fi

  echo ""
done

echo "All tenant builds complete!"
