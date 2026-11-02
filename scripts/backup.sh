#!/usr/bin/env bash
# HEL.ink hub: copy the database to backups/ (or HOP_BACKUP_DIR) and prune old copies.
#
#   scripts/backup.sh               run it by hand or from a timer (deploy/systemd/hub-backup.timer)
#
# SQLite: a consistent copy (sqlite3 .backup, else PDO VACUUM INTO), checked with integrity_check.
# MySQL/MariaDB: mysqldump --single-transaction; the password goes through a temporary 0600 option
# file, never onto the command line. Files and the folder are private to the user running this.
# Environment: HOP_BACKUP_DIR (default backups/), HOP_BACKUP_KEEP_DAYS (default 14), PHP_BIN.
# Copies stay on this machine: also copy them somewhere else, because a lost disk loses both.
set -euo pipefail
umask 077

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")/.." && pwd)"
cd "$APP_DIR"
[ -f .env ] || { echo "backup: no .env in $APP_DIR" >&2; exit 1; }
if [ -z "${PHP_BIN:-}" ]; then if command -v php8.3 >/dev/null 2>&1; then PHP_BIN=php8.3; else PHP_BIN=php; fi; fi
DIR="${HOP_BACKUP_DIR:-$APP_DIR/backups}"
KEEP_DAYS="${HOP_BACKUP_KEEP_DAYS:-14}"
case "$KEEP_DAYS" in ''|*[!0-9]*) echo "backup: HOP_BACKUP_KEEP_DAYS must be a number" >&2; exit 2 ;; esac

get_env() {
  local raw; raw="$(sed -n "s/^$1=//p" .env | head -1)"
  case "$raw" in \"*\") raw="${raw#\"}"; raw="${raw%\"}"; raw="$(printf '%s' "$raw" | sed 's/\\\([\\"$]\)/\1/g')" ;; esac
  printf '%s\n' "$raw"
}
cnf_q() { printf '"%s"' "$(printf '%s' "$1" | sed 's/[\\"]/\\&/g')"; }

mkdir -p "$DIR"; chmod 700 "$DIR"
TS="$(date +%Y%m%d-%H%M%S)"
CNF=''; OUT=''
trap '[ -z "$CNF" ] || rm -f "$CNF"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

case "$(get_env DB_CONNECTION)" in
  sqlite)
    SRC="$(get_env DB_DATABASE)"; [ -n "$SRC" ] || SRC="$APP_DIR/database/database.sqlite"
    [ -f "$SRC" ] || { echo "backup: SQLite file not found: $SRC" >&2; exit 1; }
    OUT="$DIR/hub-db-$TS.sqlite"
    if command -v sqlite3 >/dev/null 2>&1; then
      sqlite3 "$SRC" ".timeout 10000" ".backup '$OUT'" || { rm -f "$OUT"; echo "backup: sqlite3 .backup failed" >&2; exit 1; }
    else
      "$PHP_BIN" -r '$p = new PDO("sqlite:".$argv[1]); $p->exec("VACUUM INTO ".$p->quote($argv[2]));' "$SRC" "$OUT" || { rm -f "$OUT"; echo "backup: VACUUM INTO failed" >&2; exit 1; }
    fi
    chmod 600 "$OUT"
    if command -v sqlite3 >/dev/null 2>&1; then CHECK="$(sqlite3 "$OUT" 'PRAGMA integrity_check' 2>/dev/null || true)"
    else CHECK="$("$PHP_BIN" -r 'echo (new PDO("sqlite:".$argv[1]))->query("PRAGMA integrity_check")->fetchColumn();' "$OUT" 2>/dev/null || true)"; fi
    [ "$CHECK" = ok ] || { rm -f "$OUT"; echo "backup: the copy failed integrity_check ($CHECK)" >&2; exit 1; }
    ;;
  mysql|mariadb)
    DBN="$(get_env DB_DATABASE)"
    case "$DBN" in ''|*[!A-Za-z0-9_]*) echo "backup: DB_DATABASE has characters other than letters, digits and _" >&2; exit 1 ;; esac
    command -v mysqldump >/dev/null 2>&1 || { echo "backup: mysqldump is not installed" >&2; exit 1; }
    CNF="$(mktemp)"
    printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$(cnf_q "$(get_env DB_USERNAME)")" "$(cnf_q "$(get_env DB_PASSWORD)")" "$(cnf_q "$(get_env DB_HOST)")" "$(get_env DB_PORT)" >"$CNF"
    OUT="$DIR/hub-db-$TS.sql"
    mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines "$DBN" >"$OUT" || { rm -f "$OUT"; echo "backup: mysqldump failed" >&2; exit 1; }
    [ -s "$OUT" ] && tail -n 1 "$OUT" | grep -q 'Dump completed' || { rm -f "$OUT"; echo "backup: the dump is empty or incomplete" >&2; exit 1; }
    chmod 600 "$OUT"
    ;;
  *) echo "backup: unsupported DB_CONNECTION '$(get_env DB_CONNECTION)'" >&2; exit 1 ;;
esac

echo "backup: wrote $OUT"
# Prune only after a good copy, and only our own files, so a failing job never empties the folder.
find "$DIR" -maxdepth 1 -type f -name 'hub-db-*' -mtime +"$KEEP_DAYS" -delete
