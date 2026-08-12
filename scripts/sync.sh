#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")/.."

FTP_USER="if0_36247100"
FTP_PASS="VKz6FwnHjzX"
FTP_HOST="ftpupload.net"
REMOTE_DIR="/lmalp.10001mb.com/htdocs"
LOCAL_BUILD="deploy/lmalp.10001mb.com/htdocs"
DB_FILE="taskflow.db"
BACKUP_DIR="backups"

mkdir -p "$BACKUP_DIR"

ensure_schema() {
    if [[ ! -f "data/$DB_FILE" ]]; then
        php -r "require 'vendor/autoload.php'; \$pdo = TaskFlow\\Database::get('data/$DB_FILE'); new TaskFlow\\TaskRepository(\$pdo); new TaskFlow\\DisciplineRepository(\$pdo);"
    fi
}

run_lftp() {
    lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" \
        -e "set ssl:verify-certificate no; set ftp:ssl-allow no; set ftp:chmod-ignore yes; $1; bye"
}

backup_prod_db() {
    local stamp
    stamp=$(date +%Y%m%d-%H%M%S)
    local target="$BACKUP_DIR/prod-${DB_FILE}.${stamp}.bak"
    echo "Backup prod DB → $target"
    run_lftp "get $REMOTE_DIR/$DB_FILE -o $target"
    echo "Prod DB backed up."
}

pull_prod_db() {
    echo "Pull prod DB → data/$DB_FILE.tmp"
    run_lftp "get $REMOTE_DIR/$DB_FILE -o data/$DB_FILE.tmp"
}

build_code_only() {
    rm -rf "$LOCAL_BUILD"
    mkdir -p "$LOCAL_BUILD"
    cp -r public/. "$LOCAL_BUILD/"
    cp -r src "$LOCAL_BUILD/"
    cp -r vendor "$LOCAL_BUILD/"
    # La base reste en dehors du build code.
}

sync_code() {
    ensure_schema
    echo "=== Sync CODE only (prod DB untouched) ==="
    build_code_only
    echo "Upload code to $REMOTE_DIR (excludes $DB_FILE)"
    run_lftp "mirror -R -v --exclude-glob='$DB_FILE' $LOCAL_BUILD $REMOTE_DIR"
    echo "Code synced."
}

sync_db() {
    ensure_schema
    echo "=== Sync DB (interactive, prod backed up first) ==="
    backup_prod_db
    pull_prod_db

    local local_tasks prod_tasks local_habits prod_habits
    local_tasks=$(sqlite3 "data/$DB_FILE" 'SELECT COUNT(*) FROM tasks')
    prod_tasks=$(sqlite3 "data/$DB_FILE.tmp" 'SELECT COUNT(*) FROM tasks')
    local_habits=$(sqlite3 "data/$DB_FILE" 'SELECT COUNT(*) FROM discipline_habits')
    prod_habits=$(sqlite3 "data/$DB_FILE.tmp" 'SELECT COUNT(*) FROM discipline_habits')

    echo ""
    echo "  Local tasks:  $local_tasks"
    echo "  Prod tasks:   $prod_tasks"
    echo "  Local habits: $local_habits"
    echo "  Prod habits:  $prod_habits"
    echo ""
    read -rp "Overwrite prod DB with local data/$DB_FILE? Type YES to confirm: " confirm

    if [[ "$confirm" == "YES" ]]; then
        run_lftp "put data/$DB_FILE -o $REMOTE_DIR/$DB_FILE"
        echo "Prod DB overwritten."
    else
        echo "Aborted. Prod DB untouched."
    fi
    rm -f "data/$DB_FILE.tmp"
}

sync_full() {
    sync_code
    sync_db
}

usage() {
    cat <<'EOF'
Usage: scripts/sync.sh <command>

  code    Sync code only. The prod DB is never touched.
  db      Backup prod DB, compare counts, then ask before overwriting.
  backup  Pull a timestamped backup of the prod DB.
  full    code + db (interactive confirmation for DB).

Never run 'rm -rf' on the remote document root.
EOF
}

case "${1:-}" in
    code) sync_code ;;
    db) sync_db ;;
    backup) backup_prod_db ;;
    full) sync_full ;;
    *) usage; exit 1 ;;
esac
