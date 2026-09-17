#!/usr/bin/env bash
# Build a white-label driver APK for a specific tenant.
#
# Usage:
#   ./scripts/build_driver.sh \
#     --app-token "tok_abc123" \
#     --api-url "https://store.example.com/api/v1" \
#     --app-name "Jeyam Mutton Driver" \
#     --app-id "com.jeyammutton.driver" \
#     --primary-color "#E23744"
#
# Optional:
#   --sentry-dsn "https://xxx@sentry.io/yyy"

set -euo pipefail

APP_TOKEN=""
API_URL=""
APP_NAME="CloudMarket Driver"
APP_ID=""
PRIMARY_COLOR="#E23744"
SENTRY_DSN=""
SENTRY_ENV="production"

while [[ $# -gt 0 ]]; do
  case $1 in
    --app-token) APP_TOKEN="$2"; shift 2;;
    --api-url) API_URL="$2"; shift 2;;
    --app-name) APP_NAME="$2"; shift 2;;
    --app-id) APP_ID="$2"; shift 2;;
    --primary-color) PRIMARY_COLOR="$2"; shift 2;;
    --sentry-dsn) SENTRY_DSN="$2"; shift 2;;
    --sentry-env) SENTRY_ENV="$2"; shift 2;;
    *) echo "Unknown option: $1"; exit 1;;
  esac
done

if [ -z "$APP_TOKEN" ] || [ -z "$API_URL" ]; then
  echo "Error: --app-token and --api-url are required"
  exit 1
fi

DART_DEFINES=(
  "--dart-define=APP_MODE=driver"
  "--dart-define=APP_TOKEN=$APP_TOKEN"
  "--dart-define=API_BASE_URL=$API_URL"
  "--dart-define=APP_NAME=$APP_NAME"
  "--dart-define=PRIMARY_COLOR=$PRIMARY_COLOR"
)

if [ -n "$SENTRY_DSN" ]; then
  DART_DEFINES+=("--dart-define=SENTRY_DSN=$SENTRY_DSN")
  DART_DEFINES+=("--dart-define=SENTRY_ENV=$SENTRY_ENV")
fi

echo "Building DRIVER APK for: $APP_NAME"
echo "  API URL: $API_URL"
echo "  App Mode: driver"

# Override Android applicationId so customer and driver APKs can coexist
GRADLE_FILE="$(dirname "$0")/../android/app/build.gradle.kts"
ORIGINAL_APP_ID="com.cloudmarket.cloudstore"
if [ -n "$APP_ID" ]; then
  sed -i "s/applicationId = \"$ORIGINAL_APP_ID\"/applicationId = \"$APP_ID\"/" "$GRADLE_FILE"
  echo "  Application ID: $APP_ID"
fi

flutter build apk --release "${DART_DEFINES[@]}"

# Restore original applicationId so the working copy stays clean
if [ -n "$APP_ID" ]; then
  sed -i "s/applicationId = \"$APP_ID\"/applicationId = \"$ORIGINAL_APP_ID\"/" "$GRADLE_FILE"
fi

echo ""
echo "Driver APK built: build/app/outputs/flutter-apk/app-release.apk"

# Rename with tenant identifier if app-id provided
if [ -n "$APP_ID" ]; then
  SAFE_NAME=$(echo "$APP_ID" | tr '.' '-')
  cp build/app/outputs/flutter-apk/app-release.apk \
     "build/app/outputs/flutter-apk/${SAFE_NAME}-driver.apk"
  echo "Copied to: build/app/outputs/flutter-apk/${SAFE_NAME}-driver.apk"
fi
