#!/usr/bin/env bash
# HEL.ink: update. Run it in the folder where HEL.ink is installed. --help lists the options.
APP='HEL.ink'; SLUG='hel-ink'; REPO='HEL.ink'; PORT_DEFAULT=8000
# ---------------------------------------------------------------------------
# Shared helpers (the same in every HOP installer). Plain bash 3.2+, no extras.
# ---------------------------------------------------------------------------
set -eu
set -o pipefail

if [ -t 1 ]; then
  B=$'\033[1m'; D=$'\033[2m'; R=$'\033[31m'; G=$'\033[32m'; Y=$'\033[33m'; C=$'\033[36m'; Z=$'\033[0m'
else
  B=''; D=''; R=''; G=''; Y=''; C=''; Z=''
fi
say()  { printf '%s\n' "$*"; }
hdr()  { printf '\n%s== %s ==%s\n' "$B" "$*" "$Z"; }
ok()   { printf ' %s✓%s %s\n' "$G" "$Z" "$*"; }
warn() { printf ' %s!%s %s\n' "$Y" "$Z" "$*" >&2; }
bad()  { printf ' %s✗%s %s\n' "$R" "$Z" "$*" >&2; }
die()  { bad "$*"; exit 1; }
have() { command -v "$1" >/dev/null 2>&1; }
# PHP_BIN: the interpreter for php, artisan and composer. Set it in the environment to override;
# otherwise php8.3 when it is installed (the version production runs), else php.
if [ -z "${PHP_BIN:-}" ]; then if have php8.3; then PHP_BIN=php8.3; else PHP_BIN=php; fi; fi
# composer_run ARGS...: composer under PHP_BIN (a phar or a script with a php shebang); other launchers run as they are.
composer_run() {
  local c; c="$(command -v composer)" || return 127
  if head -n 1 "$c" 2>/dev/null | grep -q php; then "$PHP_BIN" "$c" "$@"; else "$c" "$@"; fi
}

YES=0; DRY=0; STAR=ask; INTERACTIVE=0
[ -t 0 ] && INTERACTIVE=1
# Run through "curl ... | bash": stdin is the script, so ask on the terminal instead.
claim_tty() {
  [ "$INTERACTIVE" = 1 ] && return 0
  case " $* " in *" --yes "*|*" -y "*) return 0 ;; esac
  if (: </dev/tty) 2>/dev/null; then exec </dev/tty; INTERACTIVE=1; fi
}

# ask VAR "Question" "default": skips the question when VAR is already set
# (by a flag or the environment), and takes the default with --yes.
ask() {
  local var="$1" q="$2" def="${3-}" cur reply
  eval "cur=\${$var-}"
  [ -n "$cur" ] && return 0
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then eval "$var=\$def"; return 0; fi
  if [ -n "$def" ]; then printf ' %s?%s %s %s[%s]%s ' "$C" "$Z" "$q" "$D" "$def" "$Z"; else printf ' %s?%s %s ' "$C" "$Z" "$q"; fi
  IFS= read -r reply || reply=''
  eval "$var=\${reply:-\$def}"
}
# ask_secret VAR "Question": no echo; empty means "generate" or "skip" for the caller.
ask_secret() {
  local var="$1" q="$2" cur reply
  eval "cur=\${$var-}"
  [ -n "$cur" ] && return 0
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then eval "$var=''"; return 0; fi
  printf ' %s?%s %s ' "$C" "$Z" "$q"
  IFS= read -rs reply || reply=''; printf '\n'
  eval "$var=\$reply"
}
# choose VAR "Question" default opt...: the answer is one of the options.
choose() {
  local var="$1" q="$2" def="$3" cur a ok_=0; shift 3
  eval "cur=\${$var-}"
  if [ -n "$cur" ]; then for a in "$@"; do [ "$a" = "$cur" ] && ok_=1; done; [ $ok_ = 1 ] && return 0; die "--$var: '$cur' is not one of: $*"; fi
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then eval "$var=\$def"; return 0; fi
  while :; do
    ask _pick "$q ($*)" "$def"; a="$_pick"; _pick=''
    for ok_ in "$@"; do [ "$ok_" = "$a" ] && { eval "$var=\$a"; return 0; }; done
    warn "Please type one of: $*"
  done
}
# confirm "Question" y|n: true when the answer is yes.
confirm() {
  local q="$1" def="${2:-y}" reply hint='[Y/n]'
  [ "$def" = n ] && hint='[y/N]'
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then [ "$def" = y ]; return; fi
  printf ' %s?%s %s %s%s%s ' "$C" "$Z" "$q" "$D" "$hint" "$Z"
  IFS= read -r reply || reply=''
  case "${reply:-$def}" in y|Y|yes|YES|Yes) return 0 ;; *) return 1 ;; esac
}
# proceed "Question": like confirm (default no), but --yes means yes. For steps the person asked for by name.
proceed() { [ "$YES" = 1 ] && return 0; confirm "$1" n; }
rand_hex() { if have openssl; then openssl rand -hex "$1"; else LC_ALL=C tr -dc 'a-f0-9' </dev/urandom | head -c $(( $1 * 2 )); fi; }

# set_env FILE KEY VALUE: replace "KEY=" or "#KEY=", or append. The new content is written to a
# temporary file next to FILE (same owner and mode, never wider than FILE) and renamed over it, so
# a full disk or an interrupted run cannot leave a cut-off .env behind.
set_env() {
  local f="$1" k="$2" v="$3" tmp
  case "$v" in *[[:space:]\#\"\$\\]*|'') v="\"$(printf '%s' "$v" | sed 's/[\\"$]/\\&/g')\"" ;; esac
  tmp="$(umask 077; mktemp "$f.XXXXXX")" || return 1
  trap 'rm -f "$tmp"; trap - RETURN' RETURN
  [ ! -e "$f" ] || cp -p "$f" "$tmp" || return 1
  if grep -qE "^#?[[:space:]]*${k}=" "$f" 2>/dev/null; then
    K="$k" V="$v" awk 'BEGIN{d=0; k=ENVIRON["K"]; v=ENVIRON["V"]} { if (!d && $0 ~ "^#?[ \t]*" k "=") { print k "=" v; d=1 } else print }' "$f" >"$tmp" || return 1
  else
    { cat "$f" 2>/dev/null || true; printf '%s=%s\n' "$k" "$v"; } >"$tmp" || return 1
  fi
  mv -f "$tmp" "$f"
}
# get_env FILE KEY: the value, without the quotes and escapes set_env adds.
get_env() {
  local raw; raw="$(sed -n "s/^$2=//p" "$1" 2>/dev/null | head -1)"
  case "$raw" in \"*\") raw="${raw#\"}"; raw="${raw%\"}"; raw="$(printf '%s' "$raw" | sed 's/\\\([\\"$]\)/\1/g')" ;; esac
  printf '%s\n' "$raw"
}

port_free() {
  if have lsof; then ! lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
  elif have ss; then ! ss -ltn "sport = :$1" 2>/dev/null | grep -q LISTEN
  else ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; fi
}
free_port() { local p="$1"; while ! port_free "$p"; do p=$((p + 1)); done; echo "$p"; }
disk_free_mb() { df -Pm "${1:-.}" 2>/dev/null | awk 'NR==2{print $4}'; }
version_ge() { [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -1)" = "$2" ]; }  # version_ge HAVE NEED
open_url() { if have open; then open "$1" >/dev/null 2>&1 || true; elif have xdg-open; then xdg-open "$1" >/dev/null 2>&1 || true; else say "   $1"; fi; }

# After a good install: one question, never silent, never repeated, nothing sent anywhere.
star_prompt() {
  local url="https://github.com/navi-crwn/$REPO"
  [ "$STAR" = no ] && { say "   ${D}If you like $APP: $url${Z}"; return 0; }
  if [ "$STAR" = yes ] || confirm "Open the GitHub page of $APP so you can star it?" y; then
    if have gh && gh auth status >/dev/null 2>&1 && confirm "Star it right now with your GitHub login (gh)?" n; then
      gh api -X PUT "user/starred/navi-crwn/$REPO" >/dev/null 2>&1 && ok "Starred. Thank you!" || { warn "Could not star it; opening the page instead."; open_url "$url"; }
    else
      open_url "$url"
    fi
  fi
  say "   ${D}GitHub: $url${Z}"
}

# Run a step, show it, stop with a clear message when it fails.
step() { local msg="$1"; shift; printf ' %s…%s %s\n' "$D" "$Z" "$msg"; if [ "$DRY" = 1 ]; then say "   (dry run) $*"; return 0; fi; "$@" || die "Step failed: $msg"; }

common_usage() {
cat <<USAGE
Options:
  -y, --yes             No questions: use the defaults and any flags below
      --dir DIR         Install into DIR (default: this folder, or ~/$SLUG when run from a download)
      --name NAME       Name of your site, shown in the pages
      --url URL         Public address (default http://localhost:PORT)
      --port N          Port for the built-in server (default $PORT_DEFAULT)
      --run MODE        $RUN_MODES
      --hub MODE        existing | new | standalone$HUB_NOTE
      --hub-url URL     Address of the HOP ID hub (with --hub existing)
      --hub-secret S    Shared app secret (or set HOP_SSO_SECRET in the environment)
      --star / --no-star
      --dry-run         Show the steps without running them
  -h, --help
USAGE
}
main() {
# ---------------------------------------------------------------------------
# Update HEL.ink (Laravel): backup, new code, packages, front end, migrations.
# The site is in maintenance mode while this runs. If a step fails before the
# migrations ran, the previous version is put back and the site comes up again.
# From the migrations on nothing is guessed: the site stays down and the exact
# recovery steps are printed.
# ---------------------------------------------------------------------------
FORCE=0; NOBACKUP=0
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --force) FORCE=1 ;; --no-backup) NOBACKUP=1 ;;
    -h|--help) say "Usage: ./update.sh [--yes] [--force] [--no-backup] [--dry-run]"; say "  --force      update even if you changed tracked files (your changes may conflict)"; say "  --no-backup  go on when the database cannot be copied first (with --yes the update stops instead)"; exit 0 ;;
    *) bad "Unknown option: $1"; exit 2 ;;
  esac; shift
done
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
cd "$SELF_DIR"
[ -f artisan ] || die "Run this from the HEL.ink folder (artisan not found here)."
[ -f .env ] || die "No .env here: HEL.ink is not installed in this folder. Run ./install.sh first."
have composer && have npm && have "$PHP_BIN" || die "php ($PHP_BIN), composer and npm are needed (run ./install.sh to see what is missing)."
have git && [ -d .git ] || die "This folder is not a git clone, so it cannot update itself. Download the new version and run ./install.sh again."
git symbolic-ref -q HEAD >/dev/null || die "This checkout is not on a branch. Run:  git checkout main  and try again."

hdr "Updating HEL.ink"
BEFORE="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
BEFORE_SHA="$(git rev-parse HEAD)"
DIRTY=0
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  if [ "$FORCE" = 0 ]; then
    git status --short --untracked-files=no | head -10
    die "You changed files here (listed above). Commit or stash them, or run ./update.sh --force."
  fi
  DIRTY=1
fi
confirm "Update now? (a copy of the database is saved first)" y || { say "Nothing was changed."; exit 0; }

# --- 1. Database copy, taken before anything changes ---------------------------------------
BACKUP_FILE=''; CNF=''
cleanup_tmp() { [ -z "$CNF" ] || rm -f "$CNF"; }   # the temporary mysqldump option file holds the DB password
trap cleanup_tmp EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# No copy possible: stop, unless --no-backup was given or a person on the terminal says go on.
no_backup() {
  warn "$1"
  [ "$NOBACKUP" = 1 ] && { warn "Going on without a database copy (--no-backup)."; return 0; }
  if [ "$YES" = 1 ] || [ "$INTERACTIVE" = 0 ]; then die "No database copy could be made, so nothing was changed. Fix the cause above, or run again with --no-backup."; fi
  confirm "Continue without a copy?" n || { say "Nothing was changed."; exit 0; }
}
# A value for a my.cnf-style option file: double-quoted, with \ and " escaped.
cnf_q() { printf '"%s"' "$(printf '%s' "$1" | sed 's/[\\"]/\\&/g')"; }
backup_db() {
  local ts dbc dbf out dbn dbh dbp dbu dbw
  ts="$(date +%Y%m%d-%H%M%S)"; dbc="$(get_env .env DB_CONNECTION)"
  [ "$NOBACKUP" = 1 ] && { warn "Skipping the database copy (--no-backup)."; return 0; }
  mkdir -p backups && chmod 700 backups
  case "$dbc" in
    sqlite)
      dbf="$(get_env .env DB_DATABASE)"; [ -n "$dbf" ] || dbf="$SELF_DIR/database/database.sqlite"
      [ -f "$dbf" ] || { ok "No SQLite file yet ($dbf): nothing to copy"; return 0; }
      out="backups/database-$ts.sqlite"
      if have sqlite3 && ( umask 077; sqlite3 "$dbf" ".timeout 10000" ".backup '$out'" ) 2>/dev/null; then :
      elif ( umask 077; "$PHP_BIN" -r '$p = new PDO("sqlite:".$argv[1]); $p->exec("VACUUM INTO ".$p->quote($argv[2]));' "$dbf" "$out" ) 2>/dev/null; then :
      else
        rm -f "$out"
        warn "Could not take a consistent copy (sqlite3 command missing or too old); copying the files instead. Stop the queue workers if they write to it."
        ( umask 077; cp "$dbf" "$out" && { [ ! -f "$dbf-wal" ] || cp "$dbf-wal" "$out-wal"; } && { [ ! -f "$dbf-shm" ] || cp "$dbf-shm" "$out-shm"; } ) || { rm -f "$out" "$out-wal" "$out-shm"; no_backup "Copying the SQLite file failed."; return 0; }
      fi
      chmod 600 "$out" "$out-wal" "$out-shm" 2>/dev/null || true
      BACKUP_FILE="$out"; ok "Database copied to $out" ;;
    mysql|mariadb)
      dbn="$(get_env .env DB_DATABASE)"; dbh="$(get_env .env DB_HOST)"; dbp="$(get_env .env DB_PORT)"
      dbu="$(get_env .env DB_USERNAME)"; dbw="$(get_env .env DB_PASSWORD)"
      case "$dbn" in ''|*[!A-Za-z0-9_]*) die "DB_DATABASE '$dbn' has characters other than letters, digits and _: make the dump yourself, then run ./update.sh --no-backup" ;; esac
      have mysqldump || { no_backup "mysqldump is not installed, so the MySQL/MariaDB database cannot be copied first."; return 0; }
      CNF="$(umask 077; mktemp)"
      printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$(cnf_q "$dbu")" "$(cnf_q "$dbw")" "$(cnf_q "${dbh:-127.0.0.1}")" "${dbp:-3306}" >"$CNF"
      out="backups/$dbn-$ts.sql"
      if ( umask 077; mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines "$dbn" >"$out" ) 2>/dev/null && [ -s "$out" ]; then
        chmod 600 "$out"; BACKUP_FILE="$out"; ok "Database dumped to $out"
      else
        rm -f "$out"; no_backup "mysqldump failed (check DB_HOST, DB_USERNAME and DB_PASSWORD in .env)."
      fi
      rm -f "$CNF"; CNF='' ;;
    *) warn "Database type '${dbc:-unknown}': no automatic copy. Make your own backup first."
       no_backup "No automatic database copy for this database type." ;;
  esac
}
if [ "$DRY" = 1 ]; then say " … Database copy: (dry run) skipped"; else backup_db; fi

# --- 2. Code, packages, front end, migrations ----------------------------------------------
# STAGE says how far the update got: 0 not started · 1 pull · 2 composer · 3 npm ci · 4 build · 5 migrations and after · 9 done.
STAGE=0; STAGE_NAME=''
begin() { STAGE="$1"; STAGE_NAME="$2"; }
# Put the previous version back (code, PHP packages, front end) and bring the site up.
restore_previous() {
  if [ "$(git rev-parse HEAD)" != "$BEFORE_SHA" ]; then
    # With --force and local edits, --keep leaves those edits alone (and refuses if they are in the way).
    if [ "$DIRTY" = 1 ]; then git reset --keep "$BEFORE_SHA"; else git reset --hard "$BEFORE_SHA"; fi || return 1
  fi
  if [ "$STAGE" -ge 2 ]; then composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress || return 1; fi
  if [ "$STAGE" -ge 3 ]; then npm ci --no-audit --no-fund --loglevel=error && npm run build --silent || return 1; fi
  "$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true
  "$PHP_BIN" artisan up
}
on_exit() {
  local rc=$?
  trap - EXIT INT TERM
  cleanup_tmp
  if [ "$STAGE" -ge 1 ] && [ "$STAGE" -le 4 ] && [ "$rc" -ne 0 ]; then
    bad "The update stopped while: $STAGE_NAME. The migrations have not run, so the database is untouched."
    say "  Putting the previous version ($BEFORE) back…"
    if restore_previous; then
      ok "The previous version is back and the site is up. Nothing changed; fix the problem above and run ./update.sh again."
    else
      bad "Could not restore the previous version by itself. The site stays in maintenance mode. By hand:"
      say "    cd $SELF_DIR"
      say "    git reset --hard $BEFORE_SHA && composer install --no-dev --optimize-autoloader && npm ci && npm run build"
      say "    $PHP_BIN artisan optimize:clear && $PHP_BIN artisan up"
    fi
  elif [ "$STAGE" -ge 5 ] && [ "$STAGE" -le 8 ] && [ "$rc" -ne 0 ]; then
    bad "The update stopped while: $STAGE_NAME. The migrations may have run, so the site stays in maintenance mode."
    say "  If only a late step failed (caches, queue restart) and the error is fixed, bring the site up with:"
    say "    cd $SELF_DIR && $PHP_BIN artisan up"
    say "  To go back to the previous version ($BEFORE):"
    if [ -n "$BACKUP_FILE" ]; then
      say "    1. Restore the database from $SELF_DIR/$BACKUP_FILE (taken before this update)"
    else
      say "    1. Restore your own database backup (no copy was made by this run)"
    fi
    say "    2. cd $SELF_DIR && git reset --hard $BEFORE_SHA"
    say "    3. composer install --no-dev --optimize-autoloader && npm ci && npm run build"
    say "    4. $PHP_BIN artisan optimize:clear && $PHP_BIN artisan up"
  fi
  exit "$rc"
}
if [ "$DRY" = 0 ]; then
  trap on_exit EXIT
  begin 1 "switching on maintenance mode"
  "$PHP_BIN" artisan down --retry=30 >/dev/null 2>&1 || warn "Could not switch on maintenance mode; visitors may see the site while it updates."
fi
begin 1 "fetching the new version";   step "Fetching the new version" git pull --ff-only
begin 2 "installing PHP packages";    step "PHP packages (composer)" composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress
begin 3 "installing front-end packages"; step "Front end (npm)" npm ci --no-audit --no-fund --loglevel=error
begin 4 "building the front end";     step "Building the front end" npm run build --silent
begin 5 "running the migrations";     step "Updating the database tables" "$PHP_BIN" artisan migrate --force
begin 6 "linking the storage folder"; step "Linking the storage folder" "$PHP_BIN" artisan storage:link --force
begin 7 "refreshing caches";          step "Clearing and warming caches" "$PHP_BIN" artisan optimize:clear
                                      step "Warming caches" "$PHP_BIN" artisan optimize
begin 8 "restarting queue workers";   step "Telling queue workers to restart" "$PHP_BIN" artisan queue:restart
begin 8 "bringing the site up"
[ "$DRY" = 1 ] || "$PHP_BIN" artisan up || die "The update itself worked, but the site could not leave maintenance mode. Run:  $PHP_BIN artisan up"
STAGE=9
AFTER="$(git describe --tags --always 2>/dev/null || git rev-parse --short HEAD)"
hdr "Done"
ok "HEL.ink updated: $BEFORE → $AFTER"
say "  If it runs as a service (systemd, supervisor), restart it. With ./start.sh, stop it (Ctrl+C) and start it again."

}
main "$@"; exit $?
