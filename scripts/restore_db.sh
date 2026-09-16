#!/usr/bin/env bash
# Database restore script for CloudStore.
#
# Usage:
#   ./scripts/restore_db.sh backups/cloudstore_20250915_120000.sql.gz
#   ./scripts/restore_db.sh --latest              # Restore from latest backup
#
# Environment variables:
#   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, MYSQL_BIN (same as backup_db.sh)

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-cloudstore}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

BACKUP_DIR="$(dirname "$0")/../backups"

# Auto-detect mysql
if [ -n "${MYSQL_BIN:-}" ]; then
  MYSQL="$MYSQL_BIN"
elif command -v mysql &>/dev/null; then
  MYSQL="mysql"
elif [ -f "/c/laragon1/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe" ]; then
  MYSQL="/c/laragon1/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe"
else
  echo "Error: mysql not found. Set MYSQL_BIN or add to PATH."
  exit 1
fi

# Resolve file path
BACKUP_FILE="${1:-}"
if [ "$BACKUP_FILE" = "--latest" ]; then
  if [ ! -f "${BACKUP_DIR}/latest" ]; then
    echo "Error: No latest backup found. Run backup_db.sh first."
    exit 1
  fi
  BACKUP_FILE=$(cat "${BACKUP_DIR}/latest")
fi

if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
  echo "Error: Backup file not found: ${BACKUP_FILE:-<none>}"
  echo "Usage: $0 <backup_file.sql.gz> | --latest"
  exit 1
fi

echo "WARNING: This will DROP and recreate the '${DB_NAME}' database!"
echo "Backup file: ${BACKUP_FILE}"
echo ""
read -p "Type 'yes' to continue: " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
  echo "Aborted."
  exit 0
fi

PASS_ARG=""
if [ -n "$DB_PASS" ]; then
  PASS_ARG="-p${DB_PASS}"
fi

echo "Dropping and recreating ${DB_NAME}..."
"$MYSQL" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_ARG \
  -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Restoring from ${BACKUP_FILE}..."
gunzip -c "$BACKUP_FILE" | "$MYSQL" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" $PASS_ARG "$DB_NAME"

echo "Restore complete."
