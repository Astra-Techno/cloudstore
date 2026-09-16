#!/usr/bin/env bash
# Deployment script for CloudStore with automatic backup and rollback.
#
# Usage:
#   ./scripts/deploy.sh                 # Deploy latest code
#   ./scripts/deploy.sh --rollback      # Rollback to previous release
#
# This script:
#   1. Creates a database backup before deploying
#   2. Pulls latest code from git
#   3. Runs database migrations
#   4. Builds admin frontend
#   5. Clears caches
#   6. On failure, offers rollback to the backup
#
# Environment variables:
#   DEPLOY_BRANCH  (default: master)
#   SKIP_BACKUP    (default: false)
#   SKIP_BUILD     (default: false)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
SKIP_BACKUP="${SKIP_BACKUP:-false}"
SKIP_BUILD="${SKIP_BUILD:-false}"

cd "$PROJECT_DIR"

# --- Rollback mode ---
if [ "${1:-}" = "--rollback" ]; then
  echo "=== ROLLBACK MODE ==="

  # Rollback git
  PREV_COMMIT=$(git rev-parse HEAD~1 2>/dev/null || echo "")
  if [ -z "$PREV_COMMIT" ]; then
    echo "Error: No previous commit to rollback to."
    exit 1
  fi

  echo "Rolling back to commit: $PREV_COMMIT"
  git checkout "$PREV_COMMIT" -- .

  # Restore database
  if [ -f "backups/latest" ]; then
    echo ""
    read -p "Also restore database from latest backup? (yes/no): " RESTORE_DB
    if [ "$RESTORE_DB" = "yes" ]; then
      "$SCRIPT_DIR/restore_db.sh" --latest
    fi
  fi

  # Rebuild admin if needed
  if [ "$SKIP_BUILD" != "true" ] && [ -d "admin" ]; then
    echo "Rebuilding admin frontend..."
    cd admin && npm run build 2>/dev/null && cd ..
  fi

  echo "Rollback complete."
  exit 0
fi

# --- Deploy mode ---
echo "=== CloudStore Deployment ==="
echo "Branch: ${DEPLOY_BRANCH}"
echo "Project: ${PROJECT_DIR}"
echo ""

# Step 1: Backup
if [ "$SKIP_BACKUP" != "true" ]; then
  echo "--- Step 1: Database backup ---"
  "$SCRIPT_DIR/backup_db.sh" || {
    echo "WARNING: Backup failed. Continuing anyway..."
  }
  echo ""
fi

# Step 2: Git pull
echo "--- Step 2: Pull latest code ---"
CURRENT_COMMIT=$(git rev-parse HEAD)
git fetch origin "$DEPLOY_BRANCH"
git merge "origin/$DEPLOY_BRANCH" --ff-only || {
  echo "Error: Fast-forward merge failed. Manual intervention needed."
  exit 1
}
NEW_COMMIT=$(git rev-parse HEAD)

if [ "$CURRENT_COMMIT" = "$NEW_COMMIT" ]; then
  echo "Already up to date. No deployment needed."
  exit 0
fi

echo "Updated: ${CURRENT_COMMIT:0:8} -> ${NEW_COMMIT:0:8}"
echo ""

# Step 3: PHP dependencies
echo "--- Step 3: PHP dependencies ---"
if [ -f "api/composer.json" ]; then
  cd api
  composer install --no-dev --optimize-autoloader 2>/dev/null || echo "composer install skipped"
  cd ..
fi
echo ""

# Step 4: Database migrations
echo "--- Step 4: Database migrations ---"
if [ -f "api/bin/console" ]; then
  export PATH="/c/laragon1/bin/php/php-8.3.16-Win32-vs16-x64:$PATH"
  php api/bin/console migrate 2>/dev/null || {
    echo "WARNING: Migration failed."
    read -p "Rollback? (yes/no): " DO_ROLLBACK
    if [ "$DO_ROLLBACK" = "yes" ]; then
      git checkout "$CURRENT_COMMIT" -- .
      "$SCRIPT_DIR/restore_db.sh" --latest
      echo "Rolled back to ${CURRENT_COMMIT:0:8}."
      exit 1
    fi
  }
fi
echo ""

# Step 5: Build admin frontend
if [ "$SKIP_BUILD" != "true" ] && [ -d "admin" ]; then
  echo "--- Step 5: Build admin frontend ---"
  cd admin
  npm ci --production=false 2>/dev/null || npm install 2>/dev/null
  npm run build || {
    echo "ERROR: Admin build failed."
    exit 1
  }
  cd ..
  echo ""
fi

# Step 6: Post-deploy
echo "--- Step 6: Post-deploy checks ---"
if command -v curl &>/dev/null; then
  HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health 2>/dev/null || echo "000")
  if [ "$HTTP_CODE" = "200" ]; then
    echo "Health check: OK"
  else
    echo "Health check: HTTP $HTTP_CODE (non-critical)"
  fi
fi

echo ""
echo "=== Deployment complete ==="
echo "Commit: ${NEW_COMMIT:0:8}"
echo "To rollback: ./scripts/deploy.sh --rollback"
