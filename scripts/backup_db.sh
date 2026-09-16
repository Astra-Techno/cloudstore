#!/usr/bin/env bash
# Database backup script for CloudStore.
#
# Usage:
#   ./scripts/backup_db.sh                    # Backs up to ./backups/
#   ./scripts/backup_db.sh /path/to/dest      # Backs up to specified directory
#   MYSQL_BIN=/usr/bin/mysql ./scripts/backup_db.sh  # Override mysql path
#
# Environment variables:
#   DB_HOST     (default: 127.0.0.1)
#   DB_PORT     (default: 3306)
#   DB_NAME     (default: cloudstore)
#   DB_USER     (default: root)
#   DB_PASS     (default: empty)
#   MYSQL_BIN   (default: auto-detect)
#   KEEP_DAYS   (default: 30, backups older than this are pruned)

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-cloudstore}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
KEEP_DAYS="${KEEP_DAYS:-30}"

BACKUP_DIR="${1:-$(dirname "$0")/../backups}"
mkdir -p "$BACKUP_DIR"

# Auto-detect mysqldump
if [ -n "${MYSQL_BIN:-}" ]; then
  MYSQLDUMP="${MYSQL_BIN%/*}/mysqldump"
elif command -v mysqldump &>/dev/null; then
  MYSQLDUMP="mysqldump"
elif [ -f "/c/laragon1/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe" ]; then
  MYSQLDUMP="/c/laragon1/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe"
else
  echo "Error: mysqldump not found. Set MYSQL_BIN or add to PATH."
  exit 1
fi

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FILENAME="${DB_NAME}_${TIMESTAMP}.sql.gz"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

echo "Backing up ${DB_NAME} to ${FILEPATH}..."

PASS_ARG=""
if [ -n "$DB_PASS" ]; then
  PASS_ARG="-p${DB_PASS}"
fi

"$MYSQLDUMP" \
  -h "$DB_HOST" \
  -P "$DB_PORT" \
  -u "$DB_USER" \
  $PASS_ARG \
  --single-transaction \
  --routines \
  --triggers \
  --quick \
  "$DB_NAME" | gzip > "$FILEPATH"

SIZE=$(du -h "$FILEPATH" | cut -f1)
echo "Backup complete: ${FILEPATH} (${SIZE})"

# Prune old backups
if [ "$KEEP_DAYS" -gt 0 ]; then
  PRUNED=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime +${KEEP_DAYS} -delete -print | wc -l)
  if [ "$PRUNED" -gt 0 ]; then
    echo "Pruned ${PRUNED} backup(s) older than ${KEEP_DAYS} days."
  fi
fi

# Write latest backup path for other scripts to reference
echo "$FILEPATH" > "${BACKUP_DIR}/latest"
