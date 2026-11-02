#!/usr/bin/env bash
# HOP family: install the hub (HOP ID) and the apps you choose, connected, in one go. Run it from a clone, or straight from GitHub:
#   curl -fsSL https://raw.githubusercontent.com/navi-crwn/HEL.ink/main/install-family.sh | bash
# Add --yes to take every default. --help lists the options.
APP='the HOP family'; SLUG='hop-family'; REPO='HEL.ink'; PORT_DEFAULT=8000
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
# Install the HOP family on one computer: the HOP ID hub (HEL.ink) and the apps
# you pick, each in its own folder, already connected to the hub.
# ---------------------------------------------------------------------------
claim_tty "$@"
BASE=''; APPS=''; ADMIN=''; MAIL_HOST=''; MAIL_PORT=''; MAIL_USER=''; MAIL_FROM=''
GIT_BASE="${HOP_GIT_BASE:-https://github.com/navi-crwn}"
RUN_MODES='-'; HUB_NOTE=''
usage() {
  cat <<U
Usage: ./install-family.sh [options]

Installs the HOP ID hub (HEL.ink) and the apps you choose, each in its own folder under one
base folder, already connected to each other. Meant for a first try on one computer, or a small server.

Options:
  -y, --yes             No questions: use the defaults and the flags below
      --dir DIR         Base folder (default ~/hop-family)
      --apps LIST       Comma list of: linkhop,biohop,notehop,pixelhop (default: all four)
      --admin EMAIL     Owner e-mail (you become the owner when you sign up with it)
      --mail-host H --mail-port N --mail-user U --mail-from ADDR   Outgoing e-mail for the hub (password: HOP_MAIL_PASSWORD)
      --no-star         Do not offer the GitHub star page at the end
      --dry-run         Show the steps without running them
  -h, --help
U
}
# The SMTP password is kept in a plain shell variable, never exported: only the hub installer gets it (see below).
MAIL_SECRET="${HOP_MAIL_PASSWORD:-}"; unset HOP_MAIL_PASSWORD
while [ $# -gt 0 ]; do
  case "$1" in
    -y|--yes) YES=1 ;; --dry-run) DRY=1 ;; --no-star) STAR=no ;; --star) STAR=yes ;;
    --dir) BASE="$2"; shift ;; --apps) APPS="$2"; shift ;; --admin) ADMIN="$2"; shift ;;
    --mail-host) MAIL_HOST="$2"; shift ;; --mail-port) MAIL_PORT="$2"; shift ;; --mail-user) MAIL_USER="$2"; shift ;; --mail-from) MAIL_FROM="$2"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) bad "Unknown option: $1"; usage; exit 2 ;;
  esac; shift
done

say "${B}HOP family${Z} installer"
say "${D}The hub (HOP ID) plus the apps you choose. Free and open source; nothing is sent anywhere.${Z}"

hdr "A few questions (press Enter for the suggestion)"
ask BASE "Base folder for the whole family?" "$HOME/hop-family"
case "$BASE" in "~"*) BASE="$HOME${BASE#\~}" ;; esac
ask APPS "Which apps besides the hub? (linkhop,biohop,notehop,pixelhop)" "linkhop,biohop,notehop,pixelhop"
APPS="$(printf '%s' "$APPS" | tr 'A-Z' 'a-z' | tr -d ' ')"
WANT_GO=0; WANT_PHP=1
IFS=',' read -r -a APPLIST <<<"$APPS"
for a in "${APPLIST[@]}"; do
  case "$a" in linkhop|biohop) ;; notehop|pixelhop) WANT_GO=1 ;; '') ;; *) die "Unknown app '$a'. Choose from: linkhop, biohop, notehop, pixelhop" ;; esac
done
ask ADMIN "Your e-mail (you become the owner of the family)?" ""
if [ -z "$MAIL_HOST" ] && confirm "Set up outgoing e-mail for the hub now? (sign-up confirmation needs it; you can do it later)" n; then ask MAIL_HOST "SMTP host?" ""; fi
if [ -n "$MAIL_HOST" ]; then
  ask MAIL_PORT "SMTP port?" 587; ask MAIL_USER "SMTP user name?" ""
  [ -n "$MAIL_SECRET" ] || ask_secret MAIL_SECRET "SMTP password (hidden)?"
  ask MAIL_FROM "Send e-mail from which address?" "${ADMIN:-hello@${MAIL_HOST#*.}}"
fi

hdr "Checking this computer"
PROBLEMS=0
need() { bad "$1"; say "     ${D}$2${Z}"; PROBLEMS=$((PROBLEMS + 1)); }
have git && ok "git" || need "git is not installed" "macOS: xcode-select --install   ·   Debian/Ubuntu: sudo apt install git"
if have "$PHP_BIN" && version_ge "$("$PHP_BIN" -r 'echo PHP_VERSION;')" 8.3; then ok "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;')"; else need "PHP 8.3 or newer is needed (the hub and the Laravel apps)" "macOS: brew install php   ·   Debian/Ubuntu: https://deb.sury.org"; fi
have composer && ok "Composer" || need "Composer is not installed" "https://getcomposer.org/download/"
if have node && version_ge "$(node -v | sed 's/^v//')" 20; then ok "Node $(node -v)"; else need "Node 20 or newer is needed" "https://nodejs.org or: brew install node"; fi
if [ "$WANT_GO" = 1 ]; then have go && ok "Go $(go env GOVERSION | sed 's/^go//')" || need "Go is needed for NoteHop and PixelHop" "https://go.dev/dl (macOS: brew install go)"; fi
FREE="$(disk_free_mb "$HOME")"; [ "${FREE:-0}" -ge 4000 ] 2>/dev/null && ok "Disk: ${FREE} MB free" || warn "Less than 4 GB free (${FREE:-?} MB): the family needs about 3 GB"
[ "$PROBLEMS" -gt 0 ] && die "$PROBLEMS thing(s) to fix first (see above). Run this again when done; it is safe to repeat."

USED=''
pick_port() { local p="$1"; while ! port_free "$p" || case " $USED " in *" $p "*) true ;; *) false ;; esac; do p=$((p + 1)); done; USED="$USED $p"; echo "$p"; }
HUB_PORT="$(pick_port 8000)"
hdr "Summary"
say "  Folder      $BASE"
say "  Hub         $BASE/hub  (http://localhost:$HUB_PORT)"
for a in "${APPLIST[@]}"; do [ -n "$a" ] && say "  App         $BASE/$a"; done
say "  Owner       ${ADMIN:-(not set)}"
confirm "Install the family now? (a few minutes)" y || { say "Nothing was changed."; exit 0; }
[ "$DRY" = 1 ] && say "${Y}Dry run: nothing below is executed.${Z}"

mkdir -p "$BASE" 2>/dev/null || true
clone() { [ -f "$BASE/$2/.git/HEAD" ] || [ -d "$BASE/$2/.git" ] || step "Downloading $1" git clone --depth 1 "$GIT_BASE/$1.git" "$BASE/$2"; }
mailflags=()
[ -n "$MAIL_HOST" ] && mailflags=(--mail-host "$MAIL_HOST" --mail-port "$MAIL_PORT" --mail-user "$MAIL_USER" --mail-from "$MAIL_FROM")

hdr "1/$(( ${#APPLIST[@]} + 1 )): the hub (HOP ID)"
clone HEL.ink hub
HOP_MAIL_PASSWORD="$MAIL_SECRET" step "Installing the hub" bash "$BASE/hub/install.sh" --yes --no-star --dir "$BASE/hub" --name HOP --url "http://localhost:$HUB_PORT" --port "$HUB_PORT" --db sqlite ${ADMIN:+--admin "$ADMIN"} ${mailflags[@]+"${mailflags[@]}"}
unset MAIL_SECRET

N=1; STARTS="hub:$HUB_PORT"; URLS="Hub (sign in and account)  http://localhost:$HUB_PORT"
for a in "${APPLIST[@]}"; do
  [ -n "$a" ] || continue
  N=$((N + 1))
  case "$a" in linkhop) REPO=LinkHop; P0=8001 ;; biohop) REPO=BioHop; P0=8002 ;; notehop) REPO=NoteHop; P0=8090 ;; pixelhop) REPO=PixelHop; P0=8080 ;; esac
  hdr "$N/$(( ${#APPLIST[@]} + 1 )): $REPO"
  clone "$REPO" "$a"
  P="$(pick_port "$P0")"
  OWNER=''; case "$a" in linkhop|biohop) [ -n "$ADMIN" ] && OWNER="$ADMIN" ;; esac   # the Go apps get their owner through the hub
  step "Installing $REPO" bash "$BASE/$a/install.sh" --yes --no-star --dir "$BASE/$a" --url "http://localhost:$P" --port "$P" --hub new --hub-dir "$BASE/hub" ${OWNER:+--admin "$OWNER"}
  STARTS="$STARTS $a:$P"; URLS="$URLS
$REPO  http://localhost:$P"
done

if [ "$DRY" = 0 ]; then
  {
    echo '#!/usr/bin/env bash'
    echo '# Start the whole HOP family. Stop everything with Ctrl+C.'
    echo 'cd "$(dirname "$0")"'
    echo 'trap '"'"'kill 0'"'"' INT TERM'
    for s in $STARTS; do d="${s%%:*}"; echo "(cd $d && ./start.sh) &"; done
    echo 'wait'
  } >"$BASE/start-all.sh"
  chmod +x "$BASE/start-all.sh"
fi

hdr "Done"
if [ "$DRY" = 1 ]; then ok "Dry run finished: nothing was installed"; else ok "The HOP family is installed in $BASE"; fi
say ""
say "  ${B}Start everything:${Z}  $BASE/start-all.sh"
say "  ${B}Then open:${Z}"
printf '%s\n' "$URLS" | sed 's/^/    /'
say ""
say "  Sign up at the hub with ${ADMIN:-your e-mail}: the owner e-mail gets the admin role. Every app then signs you in through it."
[ -z "$MAIL_HOST" ] && say "  ${D}No mail server: confirmation links are written to $BASE/hub/storage/logs/.${Z}"
say "  Update an app later with its own ./update.sh · remove with ./uninstall.sh"
say ""
if [ "$DRY" = 0 ]; then REPO=HEL.ink; APP='the HOP family'; star_prompt; fi

}
main "$@"; exit $?
