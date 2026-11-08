#!/usr/bin/env bash
# HEL.ink: one-command installer. Run it from a clone, or straight from GitHub:
#   curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install.sh | bash
# Add --yes to take every default. --help lists the options.
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
# Install HEL.ink (Laravel). Safe to run again: it never overwrites an existing
# .env without asking, and never deletes anything.
# ---------------------------------------------------------------------------
claim_tty "$@"
SELF="${BASH_SOURCE[0]:-$0}"
SELF_DIR="$(cd "$(dirname "$SELF")" 2>/dev/null && pwd || pwd)"
DIR=''; NAME=''; URL=''; PORT=''; RUN=''; HUB=''; HUB_URL=''; HUB_SECRET="${HOP_SSO_SECRET:-}"
DB=''; DB_PATH=''; DB_HOST=''; DB_PORT=''; DB_NAME=''; DB_USER=''; DB_PASS="${HOP_DB_PASSWORD:-}"
ADMIN=''; HUB_DIR=''; PUBLIC_URL=''; MAIL_HOST=''; MAIL_PORT=''; MAIL_USER=''; MAIL_FROM=''; MAIL_PASS="${HOP_MAIL_PASSWORD:-}"
RUN_MODES='serve (built-in server, default) | none (I will set up nginx or Apache myself)'
HUB_NOTE='  (the hub is the account server)'

usage() { say "Usage: ./install.sh [options]"; say ""; common_usage; cat <<U
      --db sqlite|mysql   Database (default sqlite)
      --db-path FILE      SQLite file (default database/database.sqlite)
      --db-host H --db-port N --db-name N --db-user U   MySQL/MariaDB (password: HOP_DB_PASSWORD)
      --admin EMAIL       Owner e-mail
      --hub-dir DIR       Where to install the hub (with --hub new)
      --mail-host H --mail-port N --mail-user U --mail-from ADDR   Outgoing e-mail (password: HOP_MAIL_PASSWORD)
U
}
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --star) STAR=yes ;; --no-star) STAR=no ;;
    --dir) DIR="$2"; shift ;; --name) NAME="$2"; shift ;; --url) URL="$2"; shift ;; --port) PORT="$2"; shift ;;
    --run) RUN="$2"; shift ;; --hub) HUB="$2"; shift ;; --hub-url) HUB_URL="$2"; shift ;; --hub-secret) HUB_SECRET="$2"; shift ;;
    --db) DB="$2"; shift ;; --db-path) DB_PATH="$2"; shift ;; --db-host) DB_HOST="$2"; shift ;; --db-port) DB_PORT="$2"; shift ;;
    --db-name) DB_NAME="$2"; shift ;; --db-user) DB_USER="$2"; shift ;; --admin) ADMIN="$2"; shift ;; --hub-dir) HUB_DIR="$2"; shift ;; --mail-host) MAIL_HOST="$2"; shift ;; --mail-port) MAIL_PORT="$2"; shift ;; --mail-user) MAIL_USER="$2"; shift ;; --mail-from) MAIL_FROM="$2"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) bad "Unknown option: $1"; usage; exit 2 ;;
  esac; shift
done

say "${B}HEL.ink${Z} installer"
say "${D}Free and open source. Nothing is sent anywhere; this script only runs on your machine.${Z}"
[ "$(id -u)" = 0 ] && warn "You are running as root. Not needed: the built-in server and the files work as a normal user."

# --- 1. Checks ---------------------------------------------------------------
hdr "Checking this computer"
PROBLEMS=0
need() { bad "$1"; say "     ${D}$2${Z}"; PROBLEMS=$((PROBLEMS + 1)); }
if have "$PHP_BIN"; then
  PHPV="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
  if version_ge "$PHPV" "8.3"; then ok "PHP $PHPV"; else need "PHP $PHPV is too old (need 8.3 or newer)" "macOS: brew install php   ·   Debian/Ubuntu: https://deb.sury.org   ·   Windows: use WSL"; fi
  MISSING=''
  MODS="$("$PHP_BIN" -m 2>/dev/null)"
  for e in mbstring openssl pdo tokenizer xml ctype fileinfo curl dom; do printf '%s\n' "$MODS" | grep -qi "^$e\$" || MISSING="$MISSING $e"; done
  [ -n "$MISSING" ] && need "PHP extensions missing:$MISSING" "Debian/Ubuntu: sudo apt install php-mbstring php-xml php-curl php-sqlite3 php-mysql   ·   macOS (brew) has them"
else
  need "PHP is not installed" "macOS: brew install php   ·   Debian/Ubuntu: sudo apt install php-cli php-mbstring php-xml php-curl php-sqlite3"
fi
have composer && ok "Composer $(composer_run --version 2>/dev/null | awk '{print $3}')" || need "Composer is not installed" "https://getcomposer.org/download/"
if have node && have npm; then
  NODEV="$(node -v | sed 's/^v//')"
  if version_ge "$NODEV" 20; then ok "Node $NODEV"; else need "Node $NODEV is too old (need 20+)" "https://nodejs.org or: nvm install 22"; fi
else need "Node.js and npm are not installed" "https://nodejs.org or: brew install node"; fi
have git && ok "git" || warn "git is not installed (only needed to download or update $APP)"
FREE="$(disk_free_mb "$SELF_DIR")"; [ "${FREE:-0}" -ge 1500 ] 2>/dev/null && ok "Disk: ${FREE} MB free" || warn "Less than 1.5 GB free (${FREE:-?} MB): the install needs about 1 GB"
[ "$PROBLEMS" -gt 0 ] && die "$PROBLEMS thing(s) to fix first (see above). Run this again when done; it is safe to repeat."

# --- 2. Questions ------------------------------------------------------------
hdr "A few questions (press Enter for the suggestion)"
if [ -f "$SELF_DIR/artisan" ] && [ -z "$DIR" ]; then DIR="$SELF_DIR"; fi
ask DIR "Install into which folder?" "$HOME/hel-ink"
case "$DIR" in "~"*) DIR="$HOME${DIR#\~}" ;; esac
ask NAME "What do you want to call your site?" "HEL.ink"
ask PORT "Port for the built-in server?" "8000"
case "$PORT" in ''|*[!0-9]*) die "Port must be a number" ;; esac
if [ -z "$URL" ] && [ -f "$DIR/.env" ]; then URL="$(get_env "$DIR/.env" APP_URL)"; [ -n "$URL" ] && say "  ${D}Keeping the address from the existing .env: $URL${Z}"; fi
ask URL "Public address of HEL.ink?" "http://localhost:$PORT"
URL="${URL%/}"
if [ -z "${NO_HUB_QUESTION:-}" ]; then

  HOSTONLY0="$(printf '%s' "$URL" | sed -E 's#^[a-z]+://##; s#[:/].*$##')"
  case "$HOSTONLY0" in localhost|127.0.0.1|*.localhost) : ;; *) ask PUBLIC_URL "Public address of the home page (the family front door; may equal the hub address)?" "$URL" ;; esac
  PUBLIC_URL="${PUBLIC_URL:-$URL}"; PUBLIC_URL="${PUBLIC_URL%/}"

fi
choose DB "Database?" sqlite sqlite mysql
if [ "$DB" = mysql ]; then
  ask DB_HOST "MySQL/MariaDB host?" 127.0.0.1
  ask DB_PORT "MySQL/MariaDB port?" 3306
  ask DB_NAME "Database name?" "helink"
  ask DB_USER "Database user?" "helink"
  [ -n "$DB_PASS" ] || ask_secret DB_PASS "Database password (hidden)?"
  if have "$PHP_BIN" && [ "$DRY" = 0 ]; then
    if P="$DB_PASS" "$PHP_BIN" -r 'new PDO("mysql:host=".$argv[1].";port=".$argv[2].";dbname=".$argv[3],$argv[4],getenv("P"));' "$DB_HOST" "$DB_PORT" "$DB_NAME" "$DB_USER" 2>/dev/null; then ok "Connected to the database"; else die "Cannot connect to MySQL with those details. Create the database and user first, then run this again."; fi
  fi
else
  ask DB_PATH "SQLite file?" "$DIR/database/database.sqlite"
fi

ask ADMIN "Your e-mail (you become the owner)?" ""
if [ -z "$MAIL_HOST" ] && confirm "Set up outgoing e-mail now? (sign-up confirmation and password reset need it; you can do it later in .env)" n; then
  ask MAIL_HOST "SMTP host?" ""
fi
if [ -n "$MAIL_HOST" ]; then
  ask MAIL_PORT "SMTP port?" 587
  ask MAIL_USER "SMTP user name?" ""
  [ -n "$MAIL_PASS" ] || ask_secret MAIL_PASS "SMTP password (hidden)?"
  ask MAIL_FROM "Send e-mail from which address?" "${ADMIN:-hello@${MAIL_HOST#*.}}"
fi
choose RUN "How will it run?" serve serve none

# --- 3. Summary --------------------------------------------------------------
hdr "Summary"
say "  Site name   $NAME"
say "  Folder      $DIR"
say "  Address     $URL   (built-in server on port $PORT)"
say "  Home page   $PUBLIC_URL"
say "  Database    $DB${DB_PATH:+ ($DB_PATH)}"
say "  Owner       ${ADMIN:-(not set)}"
say "  E-mail      ${MAIL_HOST:-not set up (confirmation links are written to storage/logs instead)}"
say "  Run         $RUN"
confirm "Install now?" y || { say "Nothing was changed."; exit 0; }
[ "$DRY" = 1 ] && say "${Y}Dry run: nothing below is executed.${Z}"

# --- 4. Get the code (when run from a download) ---------------------------------
if [ ! -f "$DIR/artisan" ]; then
  have git || die "git is needed to download HEL.ink"
  step "Downloading HEL.ink into $DIR" git clone --depth 1 https://github.com/navi-crwn/HEL.ink.git "$DIR"
fi
cd "$DIR" 2>/dev/null || { [ "$DRY" = 1 ] && exit 0; die "Cannot enter $DIR"; }


# --- 5. Install --------------------------------------------------------------
hdr "Installing"
step "PHP packages (composer)" composer_run install --no-dev --optimize-autoloader --no-interaction --no-progress
step "Front end (npm)" npm ci --no-audit --no-fund --loglevel=error
step "Building the front end" npm run build --silent

if [ -f .env ] && [ "$DRY" = 0 ]; then
  if confirm ".env already exists. Keep it and only add what is missing?" y; then KEEP_ENV=1; else ( umask 077; cp .env ".env.backup-$(date +%Y%m%d%H%M%S)" ); KEEP_ENV=0; ok "Old .env saved next to it"; fi
else KEEP_ENV=0; fi
if [ "$KEEP_ENV" = 0 ] && [ "$DRY" = 0 ]; then
  ( umask 077; cp .env.example .env )   # private from the first byte: secrets are written below
  set_env .env APP_NAME "$NAME"
  set_env .env APP_URL "$URL"
  # The apps force https in the "production" environment, so a plain http address (a trial on this computer) runs as "local".
  case "$URL" in https://*) set_env .env APP_ENV production ;; *) set_env .env APP_ENV local ;; esac
  set_env .env APP_DEBUG false
  case "$URL" in https://*) set_env .env SESSION_SECURE_COOKIE true ;; *) set_env .env SESSION_SECURE_COOKIE false ;; esac
  # Behind a reverse proxy on this machine (https address, or --run none) the proxy is 127.0.0.1; the built-in server has none.
  # Set TRUSTED_PROXIES in the environment for another front (for example "cloudflare").
  PROXIES="${TRUSTED_PROXIES:-}"
  if [ -z "$PROXIES" ]; then case "$URL" in https://*) PROXIES=127.0.0.1 ;; *) [ "$RUN" = none ] && PROXIES=127.0.0.1 ;; esac; fi
  set_env .env TRUSTED_PROXIES "$PROXIES"
  set_env .env LOG_CHANNEL daily
  set_env .env LOG_LEVEL warning   # debug logs request details and mail bodies: only for a trial on this computer (below)
  [ -n "$ADMIN" ] && set_env .env SUPERADMIN_EMAIL "$ADMIN"
  [ -n "$ADMIN" ] && set_env .env ADMIN_EMAIL "$ADMIN"
  if [ -n "$MAIL_HOST" ]; then
    set_env .env MAIL_MAILER smtp; set_env .env MAIL_HOST "$MAIL_HOST"; set_env .env MAIL_PORT "$MAIL_PORT"
    set_env .env MAIL_USERNAME "$MAIL_USER"; set_env .env MAIL_PASSWORD "$MAIL_PASS"; set_env .env MAIL_FROM_ADDRESS "$MAIL_FROM"
  else
    # No mail server yet: messages go to the log, which only shows them at debug level, so a local trial gets that level.
    set_env .env MAIL_MAILER log
    case "$URL" in https://*) warn "No mail server: sign-up and password-reset e-mails will not arrive. Set MAIL_* in .env." ;; *) set_env .env LOG_LEVEL debug ;; esac
  fi
  HOSTONLY="$(printf '%s' "$URL" | sed -E 's#^[a-z]+://##; s#[:/].*$##')"

  HUBHOST="$HOSTONLY"; PUBHOST="$(printf '%s' "$PUBLIC_URL" | sed -E 's#^[a-z]+://##; s#[:/].*$##')"
  set_env .env HUB_HOST "$HUBHOST"; set_env .env HUB_PUBLIC_HOST "$PUBHOST"; set_env .env HUB_PUBLIC_URL "$PUBLIC_URL"
  case "$HUBHOST" in localhost|127.0.0.1|*.localhost) set_env .env SESSION_DOMAIN null ;; *) set_env .env SESSION_DOMAIN "$HUBHOST" ;; esac
  set_env .env HOP_SSO_SECRET "${HUB_SECRET:-$(rand_hex 32)}"
  for A in LINKHOP BIOHOP PIXELHOP NOTEHOP FILEHOP; do set_env .env "${A}_URL" ""; set_env .env "${A}_ENABLED" false; done
  set_env .env SUPPORT_EMAIL "${ADMIN:-support@example.com}"; set_env .env SECURITY_EMAIL "${ADMIN:-security@example.com}"; set_env .env FEEDBACK_EMAIL "${ADMIN:-feedback@example.com}"
  [ -n "$MAIL_HOST" ] || set_env .env MAIL_FROM_ADDRESS "${ADMIN:-hello@example.com}"
  set_env .env WATCH_ALERT_EMAIL "${ADMIN:-}"
  set_env .env DB_MIGRATIONS_TABLE migrations_hub

  if [ "$DB" = sqlite ]; then
    [ -n "$DB_PATH" ] || DB_PATH="$DIR/database/database.sqlite"
    mkdir -p "$(dirname "$DB_PATH")"; [ -f "$DB_PATH" ] || : >"$DB_PATH"
    set_env .env DB_CONNECTION sqlite
    set_env .env DB_DATABASE "$DB_PATH"
  else
    set_env .env DB_CONNECTION mysql
    set_env .env DB_HOST "$DB_HOST"; set_env .env DB_PORT "$DB_PORT"; set_env .env DB_DATABASE "$DB_NAME"
    set_env .env DB_USERNAME "$DB_USER"; set_env .env DB_PASSWORD "$DB_PASS"
  fi
  chmod 600 .env
  ok "Wrote .env (private to you; never committed)"
fi

grep -qE '^APP_KEY=.+' .env 2>/dev/null || step "Creating the app key" "$PHP_BIN" artisan key:generate --force
step "Creating the database tables" "$PHP_BIN" artisan migrate --force
step "Linking the storage folder" "$PHP_BIN" artisan storage:link --force
step "Warming caches" "$PHP_BIN" artisan optimize

if [ -n "$ADMIN" ] && [ "$DRY" = 0 ]; then "$PHP_BIN" artisan hop:owner "$ADMIN" >/dev/null 2>&1 && ok "Owner set: $ADMIN (create your account with this e-mail)" || warn "The owner is taken from SUPERADMIN_EMAIL once you sign up with $ADMIN."; fi


# --- 6. How to keep it running -------------------------------------------------
[ "$DRY" = 1 ] || cat >start.sh <<START
#!/usr/bin/env bash
# Start HEL.ink: the web server, the queue worker and the scheduler. Stop with Ctrl+C.
cd "\$(dirname "\$0")"
$PHP_BIN artisan serve --host=127.0.0.1 --port=$PORT &
$PHP_BIN artisan queue:work --tries=3 --sleep=3 &
$PHP_BIN artisan schedule:work &
trap 'kill 0' INT TERM
wait
START
[ "$DRY" = 1 ] || chmod +x start.sh

hdr "Done"
if [ "$DRY" = 1 ]; then ok "Dry run finished: nothing was installed"; else ok "HEL.ink is installed in $DIR"; fi
say ""
say "  ${B}Start it:${Z}   cd $DIR && ./start.sh"
say "  ${B}Open it:${Z}    $URL"
say "  ${B}First time:${Z} open the address, press Sign up with ${ADMIN:-your e-mail}: the owner e-mail gets the admin role."
say "  ${B}Next:${Z}      install the apps (LinkHop, BioHop, PixelHop, NoteHop, FileHop); each installer asks for this hub's address ($URL)"
say "              and the secret, which is HOP_SSO_SECRET in $DIR/.env (the apps' installers can also install this hub for you)."

say ""
say "  ${B}Behind nginx (your own domain):${Z} point the web root at $DIR/public, use PHP-FPM, and run these two"
say "  in the background (a systemd service or supervisor):"
say "    $PHP_BIN artisan queue:work --tries=3        and        $PHP_BIN artisan schedule:work"
say "  or the single cron line:  * * * * * cd $DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1"
say "  Update later with ./update.sh · remove with ./uninstall.sh"
say ""
if [ "$DRY" = 0 ]; then star_prompt; fi

}
main "$@"; exit $?
