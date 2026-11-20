#!/usr/bin/env bash
# =====================================================================================================================
#  installer.sh – richtet tcd-studio.de (Website + Impressumsschutz-System) auf dem Server ein.
#
#  Aufruf (im Webroot, per SSH, am besten als der Benutzer, unter dem PHP läuft – oder als root):
#       bash installer.sh              # geführt, mit Rückfragen
#       bash installer.sh --check      # nur prüfen, nichts verändern
#       bash installer.sh --help       # alle Optionen
#
#  Was der Installer macht (und nichts davon doppelt: er kann gefahrlos erneut gestartet werden):
#    1. Server prüfen (PHP-Version, Erweiterungen, E-Mail-Versand, Webserver)
#    2. Datenverzeichnis anlegen – am besten AUSSERHALB des Webroots – und Rechte setzen
#    3. Datenbank + Datenschlüssel anlegen, Einstellungen (Domain, Postfächer) schreiben
#    4. ersten Admin-Account anlegen
#    5. Ausweis-Tresor einrichten: öffentlicher Schlüssel, Archiv (Verzeichnis oder S3), Archiv-Test
#    6. täglichen Wartungs-Cronjob einrichten
#    7. Zugriffsschutz prüfen (nginx-Regeln anzeigen), Abschlussbericht und Checkliste
#
#  Was der Installer NICHT kann: Postfächer anlegen, DNS/HTTPS einrichten, Zwei-Faktor-Anmeldung aktivieren,
#  den privaten Tresor-Schlüssel für dich aufbewahren. Diese Punkte stehen am Ende in der Checkliste.
#
#  Geheimnisse (Passwörter, S3-Schlüssel) werden nie als Programmargument übergeben und nie ins Log geschrieben.
# =====================================================================================================================

if [ -z "${BASH_VERSION:-}" ] || [ "${BASH_VERSINFO[0]:-0}" -lt 4 ]; then
    echo "Dieser Installer braucht bash ab Version 4 (aufrufen mit: bash installer.sh)." >&2
    exit 1
fi

set -Eeuo pipefail
umask 027

INSTALLER_VERSION="1.0"

# ------------------------------------------------------------------------------------------------------------------
#  Ausgabe
# ------------------------------------------------------------------------------------------------------------------
C_RESET=''; C_RED=''; C_GREEN=''; C_YELLOW=''; C_BLUE=''; C_BOLD=''
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ] && [ "${TERM:-dumb}" != "dumb" ]; then
    C_RESET=$'\033[0m'; C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_BLUE=$'\033[36m'; C_BOLD=$'\033[1m'
fi

LOG_FILE=""
LOG_BUFFER=()
WARNINGS=()          # Hinweise für den Abschlussbericht
PROBLEMS=()          # echte Fehler, die der Installer nicht beheben konnte (Exit-Code 3)
TODOS=()             # was der Mensch noch tun muss
SECRET_NOTES=()      # einmalig anzuzeigende Dinge (z. B. erzeugtes Passwort) – nie ins Log
TMP_FILES=()

log_line() {
    local line
    line="$(date '+%Y-%m-%d %H:%M:%S') $*"
    if [ -n "$LOG_FILE" ]; then
        printf '%s\n' "$line" >>"$LOG_FILE" 2>/dev/null || true
    else
        LOG_BUFFER+=("$line")
    fi
}
say()   { printf '%s\n' "$*"; log_line "$*"; }
info()  { printf '    %s\n' "$*"; log_line "    $*"; }
ok()    { printf '  %s[ OK ]%s %s\n' "$C_GREEN" "$C_RESET" "$*"; log_line "[ OK ] $*"; }
warn()  { printf '  %s[WARN]%s %s\n' "$C_YELLOW" "$C_RESET" "$*" >&2; log_line "[WARN] $*"; WARNINGS+=("$*"); }
bad()   { printf '  %s[FEHLER]%s %s\n' "$C_RED" "$C_RESET" "$*" >&2; log_line "[FEHLER] $*"; PROBLEMS+=("$*"); }
step()  { printf '\n%s== %s ==%s\n' "$C_BOLD$C_BLUE" "$*" "$C_RESET"; log_line "== $* =="; }
die()   { printf '\n%s[ABBRUCH]%s %s\n' "$C_RED" "$C_RESET" "$*" >&2; log_line "[ABBRUCH] $*"; exit 1; }
todo()  { TODOS+=("$*"); }

on_error() {
    local code=$? line=$1
    printf '\n%s[ABBRUCH]%s Unerwarteter Fehler in Zeile %s (Exit-Code %s). Es wurde nichts Unfertiges „halb gelöscht“; der Installer kann erneut gestartet werden.\n' \
        "$C_RED" "$C_RESET" "$line" "$code" >&2
    log_line "[ABBRUCH] Unerwarteter Fehler in Zeile $line (Exit $code)"
    exit "$code"
}
trap 'on_error $LINENO' ERR

cleanup() {
    local f
    for f in ${TMP_FILES[@]+"${TMP_FILES[@]}"}; do
        [ -n "$f" ] && rm -rf -- "$f" 2>/dev/null || true
    done
}
trap cleanup EXIT

# ------------------------------------------------------------------------------------------------------------------
#  Optionen
# ------------------------------------------------------------------------------------------------------------------
usage() {
    cat <<'EOF'
installer.sh – richtet tcd-studio.de auf diesem Server ein.

Aufruf:   bash installer.sh [Optionen]

Allgemein
  --check                 nur prüfen (Server + vorhandene Installation), nichts verändern
  -y, --yes               keine Rückfragen: es gelten die Optionen unten bzw. sichere Standardwerte
  --php PFAD              PHP-Programm für die Kommandozeile (Standard: automatisch gefunden)
  -h, --help              diese Hilfe

Website
  --site-url URL          Adresse der Website, z. B. https://tcd-studio.de (Standard: bisheriger Wert)
  --mail-domain DOMAIN    Domain der Postfächer contact@/info@/imprint@/privacy-policy@/no-reply@ (Standard: tcd-studio.de)
  --data-dir PFAD         Datenverzeichnis (Datenbank, Schlüssel). Empfohlen: außerhalb des Webroots.
                          Mit "inside" bleibt es bei <Webroot>/data (nur .htaccess-Schutz, nur Apache).
  --web-user NAME         Benutzer, unter dem der Webserver/PHP läuft (nur als root; Standard: automatisch)
  --web-group NAME        Gruppe des Webservers (Standard: Hauptgruppe von --web-user; als normaler Benutzer: Gruppe,
                          die Schreibrechte auf data/, legal/ bekommt)
  --behind-proxy          Die Seite läuft hinter einem TLS-Proxy/CDN, der X-Forwarded-Proto setzt (trust_forwarded_proto)

Admin-Konto (nur wenn noch keins existiert)
  --admin-email ADRESSE   E-Mail-Adresse des Admins
  --admin-name NAME       Anzeigename (2–80 Zeichen)
  --admin-password-file DATEI   Passwort aus Datei (erste Zeile). Alternativ: Umgebungsvariable TCD_ADMIN_PASSWORD
  --generate-admin-password     zufälliges Passwort erzeugen und am Ende einmalig anzeigen

Ausweis-Tresor
  --vault-public-key DATEI      öffentlichen Schlüssel (PEM, RSA ≥ 3072 Bit) aus Datei speichern
  --vault-generate [VERZ]       Schlüsselpaar HIER erzeugen (privater Schlüssel landet in VERZ, Standard ~/tcd-vault-key).
                                Der private Schlüssel gehört offline gesichert und danach vom Server gelöscht!
  --vault-later                 Schlüssel später im Admin-Bereich erzeugen (empfohlen: der Schlüssel entsteht im Browser)
  --archive-dir PFAD            Archiv-Verzeichnis (außerhalb des Webroots, am besten eigenes Volume)
  --s3-endpoint URL --s3-bucket NAME [--s3-region R] [--s3-prefix P] [--s3-path-style true|false]
                                S3-kompatibles Archiv; die Schlüssel kommen aus den Umgebungsvariablen
                                TCD_S3_ACCESS_KEY und TCD_S3_SECRET_KEY (nie als Argument!)

Sonstiges
  --no-cron               keinen Cronjob einrichten
  --cron-schedule "M H * * *"   Zeitplan des Wartungs-Jobs (Standard: 17 3 * * *)
  --test-mail ADRESSE     Test-E-Mail senden
  --skip-http-check       Abschluss-Test über HTTP überspringen
  --remove-installer      installer.sh am Ende löschen

Beispiel (unbeaufsichtigt):
  TCD_ADMIN_PASSWORD='…' bash installer.sh --yes --admin-email me@example.org --admin-name "Max Muster" \
      --data-dir /var/www/tcd-data --archive-dir /mnt/vault/tcd --vault-later

Exit-Codes: 0 = alles erledigt · 1 = Abbruch/Fehler · 3 = fertig, aber es gibt offene Probleme (siehe Bericht).
EOF
}

MODE_CHECK=0
ASSUME_YES=0
OPT_PHP=""
OPT_SITE_URL=""
OPT_MAIL_DOMAIN=""
OPT_DATA_DIR=""
OPT_WEB_USER=""
OPT_WEB_GROUP=""
OPT_BEHIND_PROXY=0
OPT_ADMIN_EMAIL=""
OPT_ADMIN_NAME=""
OPT_ADMIN_PW_FILE=""
OPT_GEN_PW=0
OPT_VAULT_MODE=""            # file | generate | later
OPT_VAULT_PUBKEY=""
OPT_VAULT_KEYDIR=""
OPT_ARCHIVE_DIR=""
OPT_S3_ENDPOINT=""; OPT_S3_BUCKET=""; OPT_S3_REGION=""; OPT_S3_PREFIX=""; OPT_S3_PATH_STYLE=""
OPT_NO_CRON=0
OPT_CRON_SCHEDULE="17 3 * * *"
OPT_TEST_MAIL=""
OPT_SKIP_HTTP=0
OPT_REMOVE_INSTALLER=0

parse_args() {
    local arg val
    while [ $# -gt 0 ]; do
        arg=$1
        val=""
        case $arg in
            --*=*) val=${arg#*=}; arg=${arg%%=*}; set -- "$arg" "$val" "${@:2}" ;;
        esac
        need_val() { [ $# -ge 2 ] && [ -n "$2" ] || die "Die Option $1 braucht einen Wert (siehe --help)."; }
        case $1 in
            -h|--help) usage; exit 0 ;;
            --check) MODE_CHECK=1; shift ;;
            -y|--yes) ASSUME_YES=1; shift ;;
            --php) need_val "$@"; OPT_PHP=$2; shift 2 ;;
            --site-url) need_val "$@"; OPT_SITE_URL=$2; shift 2 ;;
            --mail-domain) need_val "$@"; OPT_MAIL_DOMAIN=$2; shift 2 ;;
            --data-dir) need_val "$@"; OPT_DATA_DIR=$2; shift 2 ;;
            --web-user) need_val "$@"; OPT_WEB_USER=$2; shift 2 ;;
            --web-group) need_val "$@"; OPT_WEB_GROUP=$2; shift 2 ;;
            --behind-proxy) OPT_BEHIND_PROXY=1; shift ;;
            --admin-email) need_val "$@"; OPT_ADMIN_EMAIL=$2; shift 2 ;;
            --admin-name) need_val "$@"; OPT_ADMIN_NAME=$2; shift 2 ;;
            --admin-password-file) need_val "$@"; OPT_ADMIN_PW_FILE=$2; shift 2 ;;
            --generate-admin-password) OPT_GEN_PW=1; shift ;;
            --vault-public-key) need_val "$@"; OPT_VAULT_MODE="file"; OPT_VAULT_PUBKEY=$2; shift 2 ;;
            --vault-generate)
                OPT_VAULT_MODE=generate
                if [ $# -ge 2 ] && [ "${2#-}" = "$2" ]; then OPT_VAULT_KEYDIR=$2; shift 2; else shift; fi ;;
            --vault-later) OPT_VAULT_MODE=later; shift ;;
            --archive-dir) need_val "$@"; OPT_ARCHIVE_DIR=$2; shift 2 ;;
            --s3-endpoint) need_val "$@"; OPT_S3_ENDPOINT=$2; shift 2 ;;
            --s3-bucket) need_val "$@"; OPT_S3_BUCKET=$2; shift 2 ;;
            --s3-region) need_val "$@"; OPT_S3_REGION=$2; shift 2 ;;
            --s3-prefix) need_val "$@"; OPT_S3_PREFIX=$2; shift 2 ;;
            --s3-path-style) need_val "$@"; OPT_S3_PATH_STYLE=$2; shift 2 ;;
            --no-cron) OPT_NO_CRON=1; shift ;;
            --cron-schedule) need_val "$@"; OPT_CRON_SCHEDULE=$2; shift 2 ;;
            --test-mail) need_val "$@"; OPT_TEST_MAIL=$2; shift 2 ;;
            --skip-http-check) OPT_SKIP_HTTP=1; shift ;;
            --remove-installer) OPT_REMOVE_INSTALLER=1; shift ;;
            *) die "Unbekannte Option: $1 (siehe --help)" ;;
        esac
    done
}

# ------------------------------------------------------------------------------------------------------------------
#  Eingabe
# ------------------------------------------------------------------------------------------------------------------
read_line() {      # read_line VAR – eine Zeile von stdin; Ende der Eingabe = Abbruch
    local __v
    if ! IFS= read -r __v; then
        printf '\n' >&2
        die "Die Eingabe wurde beendet (kein Terminal?). Für den unbeaufsichtigten Betrieb: bash installer.sh --yes … (siehe --help)."
    fi
    printf -v "$1" '%s' "$__v"
}

trim() {           # trim "text" -> Text ohne führende/abschließende Leerzeichen
    local s=$1
    s="${s#"${s%%[![:space:]]*}"}"
    s="${s%"${s##*[![:space:]]}"}"
    printf '%s' "$s"
}

ask() {            # ask VAR "Frage" "Standard"
    local __var=$1 __q=$2 __def=${3-} __a=""
    if [ "$ASSUME_YES" = 1 ]; then printf -v "$__var" '%s' "$__def"; return 0; fi
    if [ -n "$__def" ]; then printf '  %s [%s]: ' "$__q" "$__def" >&2; else printf '  %s: ' "$__q" >&2; fi
    read_line __a
    __a=$(trim "$__a")
    [ -z "$__a" ] && __a=$__def
    printf -v "$__var" '%s' "$__a"
}

ask_yn() {         # ask_yn "Frage" j|n  -> 0 = ja
    local q=$1 def=${2:-j} a=""
    if [ "$ASSUME_YES" = 1 ]; then [ "$def" = j ]; return; fi
    local hint="J/n"; [ "$def" = n ] && hint="j/N"
    while :; do
        printf '  %s [%s]: ' "$q" "$hint" >&2
        read_line a
        a=$(trim "$a")
        a=$(printf '%s' "$a" | tr '[:upper:]' '[:lower:]')
        [ -z "$a" ] && a=$def
        case $a in j|ja|y|yes) return 0 ;; n|nein|no) return 1 ;; esac
        printf '  Bitte mit j oder n antworten.\n' >&2
    done
}

ask_secret() {     # ask_secret VAR "Frage"
    local __var=$1 __q=$2 __a=""
    [ "$ASSUME_YES" = 1 ] && die "Internes Problem: ein Geheimnis kann im unbeaufsichtigten Modus nicht abgefragt werden."
    printf '  %s: ' "$__q" >&2
    if [ -t 0 ]; then
        IFS= read -rs __a || { printf '\n' >&2; die "Die Eingabe wurde beendet."; }
        printf '\n' >&2
    else
        IFS= read -r __a || { printf '\n' >&2; die "Die Eingabe wurde beendet."; }
    fi
    printf -v "$__var" '%s' "$__a"
}

# ------------------------------------------------------------------------------------------------------------------
#  Hilfsfunktionen: Pfade, Rechte, PHP
# ------------------------------------------------------------------------------------------------------------------
shq() {            # in einfache Anführungszeichen setzen (für Shell-Zeilen, die wir anzeigen oder in die crontab schreiben)
    local s=$1
    printf "'%s'" "${s//\'/\'\\\'\'}"
}

lexical_path() {   # absoluter Pfad ohne "." und ".." (ohne Symlinks aufzulösen)
    local p=$1 part
    local -a parts=() stack=()
    local tilde='~'
    case $p in
        "$tilde") p=${HOME:-/} ;;
        "$tilde"/*) p=${HOME:-/}/${p#"$tilde"/} ;;
    esac
    case $p in /*) ;; *) p=$PWD/$p ;; esac
    IFS=/ read -ra parts <<<"$p"
    for part in ${parts[@]+"${parts[@]}"}; do
        case $part in
            ''|.) ;;
            ..) if [ ${#stack[@]} -gt 0 ]; then unset 'stack[${#stack[@]}-1]'; fi ;;
            *) stack+=("$part") ;;
        esac
    done
    local out="" s
    for s in ${stack[@]+"${stack[@]}"}; do out+="/$s"; done
    printf '%s' "${out:-/}"
}

physical_path() {  # wie lexical_path, aber der bereits existierende Teil wird über Symlinks hinweg aufgelöst
    local lex d tail="" base
    lex=$(lexical_path "$1")
    d=$lex
    while [ ! -d "$d" ] && [ "$d" != "/" ]; do
        base=${d##*/}
        tail="/$base$tail"
        d=${d%/*}
        [ -z "$d" ] && d=/
    done
    d=$(cd -P -- "$d" 2>/dev/null && pwd -P) || d=$lex
    d=${d%/}
    printf '%s' "${d}${tail}"
}

path_inside() {    # path_inside CHILD PARENT -> 0, wenn CHILD gleich oder unterhalb PARENT liegt (physische Pfade)
    case "$1/" in "$2"/*) return 0 ;; esac
    return 1
}

has_cmd() { command -v "$1" >/dev/null 2>&1; }

is_root() { [ "$(id -u)" = "0" ]; }

# Als Webserver-Benutzer ausführen (nur als root); sonst: direkt.
as_web() {
    if is_root && [ -n "${WEB_USER:-}" ]; then
        if has_cmd runuser; then runuser -u "$WEB_USER" -- "$@"
        elif has_cmd sudo; then sudo -n -u "$WEB_USER" -- "$@"
        else su -s /bin/sh "$WEB_USER" -c 'exec "$@"' sh "$@"
        fi
    else
        "$@"
    fi
}

web_can_write() {  # web_can_write PFAD -> 0, wenn PHP (Webserver-Benutzer) dort schreiben kann
    if is_root && [ -n "${WEB_USER:-}" ]; then
        as_web test -w "$1" 2>/dev/null
    else
        [ -w "$1" ]
    fi
}

find_php() {
    local cands=() c v
    [ -n "$OPT_PHP" ] && cands+=("$OPT_PHP")
    [ -n "${PHP_BIN:-}" ] && cands+=("$PHP_BIN")
    cands+=(php php8.5 php8.4 php8.3 php8.2 php8.1 php8.0 php7.4)
    for c in /opt/plesk/php/*/bin/php /opt/alt/php*/usr/bin/php /usr/local/bin/php /usr/local/php*/bin/php; do
        [ -x "$c" ] && cands+=("$c")
    done
    for c in "${cands[@]}"; do
        command -v "$c" >/dev/null 2>&1 || continue
        c=$(command -v "$c")
        v=$("$c" -r 'echo PHP_SAPI === "cli" && PHP_VERSION_ID >= 70400 ? PHP_VERSION : "";' 2>/dev/null || true)
        if [ -n "$v" ]; then
            PHP=$c
            PHP_VER=$v
            return 0
        fi
    done
    return 1
}

HELPER=""
hp() {             # PHP-Hilfsprogramm (liest Geheimnisse nur über stdin); als root im Namen des Webserver-Benutzers
    local -a envv=()
    [ -n "${DATA_DIR:-}" ] && envv=("TCD_DATA_DIR=$DATA_DIR")
    as_web env ${envv[@]+"${envv[@]}"} "$PHP" "$HELPER" "$@"
}

hp_stateless() {   # für Befehle, die keine Daten brauchen (Passwortregeln): ein leeres Wegwerf-Verzeichnis, damit nichts angelegt wird
    if [ -z "$STATELESS_DIR" ]; then
        STATELESS_DIR=$(mktemp -d "${TMPDIR:-/tmp}/tcd-installer.XXXXXX")
        TMP_FILES+=("$STATELESS_DIR")
    fi
    TCD_DATA_DIR=$STATELESS_DIR "$PHP" "$HELPER" "$@"
}
STATELESS_DIR=""

hp_get() {         # hp_get "$ausgabe" schluessel -> Wert der INFO-Zeile
    printf '%s\n' "$1" | awk -F'\t' -v k="$2" '$1 == "INFO" && $2 == k { print $3; exit }'
}

show_results() {   # Ergebnis-Zeilen des Helfers hübsch anzeigen; Rückgabe: Anzahl FAIL in HP_FAILS
    local st label detail
    HP_FAILS=0
    while IFS=$'\t' read -r st label detail; do
        case $st in
            OK) ok "$label${detail:+ – $detail}" ;;
            WARN) warn "$label${detail:+ – $detail}" ;;
            FAIL) HP_FAILS=$((HP_FAILS + 1)); bad "$label${detail:+ – $detail}" ;;
            INFO) : ;;
        esac
    done <<<"$1"
}

# ------------------------------------------------------------------------------------------------------------------
#  1. Umgebung und Voraussetzungen
# ------------------------------------------------------------------------------------------------------------------
ROOT=""; ROOT_REAL=""; PHP=""; PHP_VER=""; WEB_USER=""; WEB_GROUP=""; WEBSERVER="unbekannt"

detect_root() {
    local src=${BASH_SOURCE[0]}
    ROOT=$(cd -P -- "$(dirname -- "$src")" && pwd -P)
    ROOT_REAL=$ROOT
    [ -f "$ROOT/app/bootstrap.php" ] && [ -f "$ROOT/tools/install-helper.php" ] \
        || die "Der Installer muss im Hauptverzeichnis der Website liegen (dort, wo auch app/ und tools/ liegen). Gefunden: $ROOT"
    HELPER="$ROOT/tools/install-helper.php"
}

detect_webserver() {
    local found=""
    if has_cmd pgrep; then
        pgrep -x apache2 >/dev/null 2>&1 && found="Apache"
        [ -z "$found" ] && pgrep -x httpd >/dev/null 2>&1 && found="Apache"
        [ -z "$found" ] && pgrep -x nginx >/dev/null 2>&1 && found="nginx"
        [ -z "$found" ] && pgrep -x caddy >/dev/null 2>&1 && found="Caddy"
        [ -z "$found" ] && pgrep -x litespeed >/dev/null 2>&1 && found="LiteSpeed"
        [ -z "$found" ] && pgrep -x lswsctrl >/dev/null 2>&1 && found="LiteSpeed"
    fi
    if [ -z "$found" ]; then
        if has_cmd apache2ctl || has_cmd apachectl || has_cmd httpd; then found="Apache (installiert)"
        elif has_cmd nginx; then found="nginx (installiert)"
        fi
    fi
    WEBSERVER=${found:-unbekannt (Shared-Hosting?)}
}

detect_web_user() {
    WEB_USER=""; WEB_GROUP=""
    if ! is_root; then
        WEB_GROUP=$OPT_WEB_GROUP
        return 0
    fi
    local u
    if [ -n "$OPT_WEB_USER" ]; then
        id -u "$OPT_WEB_USER" >/dev/null 2>&1 || die "Der Benutzer --web-user $OPT_WEB_USER existiert nicht."
        WEB_USER=$OPT_WEB_USER
    else
        if has_cmd ps; then
            u=$(ps -eo user=,comm= 2>/dev/null | awk '$1 != "root" && ($2 ~ /^(apache2|httpd|nginx|php-fpm|php-fpm[0-9.]*|lshttpd|litespeed)$/) { print $1; exit }' || true)
            [ -n "$u" ] && id -u "$u" >/dev/null 2>&1 && WEB_USER=$u
        fi
        if [ -z "$WEB_USER" ]; then
            for u in www-data apache nginx www httpd; do
                if id -u "$u" >/dev/null 2>&1; then WEB_USER=$u; break; fi
            done
        fi
    fi
    if [ -n "$WEB_USER" ]; then
        WEB_GROUP=${OPT_WEB_GROUP:-$(id -gn "$WEB_USER")}
    fi
}

check_requirements() {
    step "1/8  Server prüfen"
    say "  Installationsordner : $ROOT"
    say "  Benutzer            : $(id -un) (uid $(id -u))"
    say "  Webserver           : $WEBSERVER"
    if is_root; then
        if [ -n "$WEB_USER" ]; then say "  Webserver-Benutzer  : $WEB_USER:$WEB_GROUP (Dateien werden diesem Benutzer zugeordnet)"
        else warn "Als root gestartet, aber der Webserver-Benutzer wurde nicht gefunden. Mit --web-user NAME angeben, sonst kann PHP die Daten nicht schreiben."
        fi
    else
        say "  (Nicht als root: es wird angenommen, dass PHP unter demselben Benutzer läuft – typisch bei Shared-Hosting.)"
    fi

    if ! find_php; then
        die "Kein PHP ab Version 7.4 für die Kommandozeile gefunden. Mit --php /pfad/zu/php angeben (z. B. /usr/bin/php8.2)."
    fi
    say "  PHP (Kommandozeile) : $PHP  (Version $PHP_VER)"

    local out
    out=$("$PHP" "$HELPER" check 2>&1) || true
    show_results "$out"
    if [ "$HP_FAILS" -gt 0 ]; then
        if [ "$MODE_CHECK" = 1 ]; then
            say ""
            say "  Es fehlen Voraussetzungen (siehe oben)."
        else
            die "Es fehlen Voraussetzungen (siehe oben). Beheben (z. B. fehlende PHP-Erweiterungen im Hosting-Panel aktivieren) und den Installer erneut starten."
        fi
    fi
    todo "Prüfen, dass die PHP-Version des WEBSERVERS ebenfalls ≥ 7.4 ist und dieselben Erweiterungen hat (die Kommandozeile kann abweichen): nach der Anmeldung im Admin-Bereich unter Settings → System checks."

    has_cmd openssl || warn "Das Programm \"openssl\" fehlt – ein Tresor-Schlüssel kann dann nicht hier erzeugt werden (aber im Admin-Bereich im Browser)."
    has_cmd curl    || warn "Das Programm \"curl\" fehlt – der Abschluss-Test über HTTP wird übersprungen."
}

# ------------------------------------------------------------------------------------------------------------------
#  2. Einstellungen sammeln
# ------------------------------------------------------------------------------------------------------------------
CUR_DATA_DIR=""; CUR_INSTALLED="no"; CUR_ADMIN="no"; CUR_VAULT_KEY="none"; CUR_SITE_URL=""; CUR_MAIL_DOMAIN=""
CUR_VAULT_DRIVER="directory"; CUR_VAULT_DIR=""; CUR_VAULT_DIR_SET="no"; CUR_VAULT_FP=""
DATA_DIR=""; DATA_INSIDE=0; MOVE_DATA=0
SITE_URL=""; MAIL_DOMAIN=""
ADMIN_EMAIL=""; ADMIN_NAME=""; ADMIN_PW=""; ADMIN_PW_GENERATED=0
VAULT_MODE=""; VAULT_PUBKEY=""; VAULT_KEYDIR=""
ARCH_MODE=""; ARCH_DIR=""; S3_ENDPOINT=""; S3_BUCKET=""; S3_REGION="eu-central-1"; S3_PREFIX="tcd-id/"; S3_PATH_STYLE="true"; S3_ACCESS=""; S3_SECRET=""
CRON_WANTED=1
TEST_MAIL_TO=""
BEHIND_PROXY=0

read_current_state() {
    local out
    out=$("$PHP" "$HELPER" status 2>&1) || { printf '%s\n' "$out" >&2; die "Der Zustand der Installation konnte nicht gelesen werden (siehe Meldung oben)."; }
    CUR_DATA_DIR=$(hp_get "$out" data_dir)
    CUR_INSTALLED=$(hp_get "$out" installed)
    CUR_ADMIN=$(hp_get "$out" admin)
    CUR_VAULT_KEY=$(hp_get "$out" vault_key)
    CUR_VAULT_FP=$(hp_get "$out" vault_fingerprint)
    CUR_SITE_URL=$(hp_get "$out" site_url)
    CUR_MAIL_DOMAIN=$(hp_get "$out" mail_domain)
    CUR_VAULT_DRIVER=$(hp_get "$out" vault_driver)
    CUR_VAULT_DIR=$(hp_get "$out" vault_dir)
    CUR_VAULT_DIR_SET=$(hp_get "$out" vault_dir_configured)
}

valid_url() { [[ $1 =~ ^https?://[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?(:[0-9]{1,5})?/?$ ]]; }
valid_domain() { [[ $1 =~ ^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$ ]]; }
valid_email() { [[ $1 =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$ ]]; }
no_ctrl() { [[ $1 != *[[:cntrl:]]* ]]; }

describe_fs() {    # kurze Beschreibung des Dateisystems eines Pfads (für den Hinweis "eigenes Volume")
    local p=$1
    while [ ! -e "$p" ] && [ "$p" != / ]; do p=${p%/*}; [ -z "$p" ] && p=/; done
    df -P -- "$p" 2>/dev/null | awk 'NR==2 { print $1 " (eingehängt unter " $6 ")" }' || true
}

fs_id() {          # Gerätenummer des Dateisystems eines Pfads (zum Vergleichen)
    local p=$1
    while [ ! -e "$p" ] && [ "$p" != / ]; do p=${p%/*}; [ -z "$p" ] && p=/; done
    stat -c '%d' -- "$p" 2>/dev/null || stat -f '%d' -- "$p" 2>/dev/null || echo "?"
}

gather_data_dir() {
    say ""
    say "${C_BOLD}Datenverzeichnis${C_RESET} – hier liegen Datenbank, Datenschlüssel und Sitzungen (private Kundendaten!)."
    say "  Empfohlen: ein Verzeichnis AUSSERHALB des Webroots, damit niemand es über den Browser abrufen kann."

    local parent suggestion=""
    parent=$(dirname -- "$ROOT_REAL")
    if [ -w "$parent" ] && [ "$parent" != "/" ]; then suggestion="$parent/tcd-studio-data"; fi

    local chosen=""
    if [ -n "$OPT_DATA_DIR" ]; then
        chosen=$OPT_DATA_DIR
    elif [ "$CUR_INSTALLED" = yes ] || [ -f "$CUR_DATA_DIR/config.local.php" ]; then
        chosen=$CUR_DATA_DIR
        say "  Vorhandene Installation gefunden – das Datenverzeichnis bleibt: $chosen"
    elif [ "$ASSUME_YES" = 1 ]; then
        chosen=${suggestion:-inside}
    else
        if [ -n "$suggestion" ]; then
            say "    1) außerhalb des Webroots (empfohlen)"
            say "    2) im Webroot unter data/ (nur durch .htaccess geschützt – funktioniert nur mit Apache)"
            local choice=""
            ask choice "Auswahl" "1"
            if [ "$choice" = 2 ]; then chosen=inside; else ask chosen "Pfad des Datenverzeichnisses" "$suggestion"; fi
        else
            warn "Das Verzeichnis über dem Webroot ist für diesen Benutzer nicht beschreibbar – ein Datenverzeichnis außerhalb kann nicht automatisch vorgeschlagen werden."
            if ask_yn "Eigenen Pfad außerhalb des Webroots angeben? (sonst bleibt es bei data/ im Webroot)" j; then
                ask chosen "Pfad des Datenverzeichnisses" ""
                [ -n "$chosen" ] || chosen=inside
            else
                chosen=inside
            fi
        fi
    fi

    if [ "$chosen" = inside ]; then
        DATA_DIR="$ROOT_REAL/data"
        DATA_INSIDE=1
    else
        no_ctrl "$chosen" || die "Der Pfad des Datenverzeichnisses enthält Steuerzeichen."
        DATA_DIR=$(physical_path "$chosen")
        if path_inside "$DATA_DIR" "$ROOT_REAL"; then
            if [ "$DATA_DIR" = "$ROOT_REAL/data" ]; then DATA_INSIDE=1
            else die "Das Datenverzeichnis darf nicht an anderer Stelle im Webroot liegen (nur $ROOT_REAL/data oder außerhalb). Angegeben: $DATA_DIR"
            fi
        fi
    fi

    # Wechsel eines bestehenden Datenverzeichnisses
    local cur_real=""
    [ -n "$CUR_DATA_DIR" ] && cur_real=$(physical_path "$CUR_DATA_DIR")
    if [ -n "$cur_real" ] && [ "$cur_real" != "$DATA_DIR" ]; then
        if [ -f "$cur_real/config.local.php" ]; then
            [ "$DATA_INSIDE" = 1 ] && die "Bestehende Daten werden nie in den Webroot verschoben (Sicherheitsrisiko). Aktuell: $cur_real"
            if [ -d "$DATA_DIR" ] && [ -n "$(ls -A "$DATA_DIR" 2>/dev/null)" ]; then
                die "Das Ziel $DATA_DIR ist nicht leer, die Daten in $cur_real würden vermischt. Bitte manuell auflösen (nichts wurde verändert)."
            fi
            MOVE_DATA=1
        fi
    fi
    say "  → Datenverzeichnis: $DATA_DIR$([ "$DATA_INSIDE" = 1 ] && printf ' (im Webroot – nur Apache/.htaccess-Schutz!)')"
    if [ "$MOVE_DATA" = 1 ]; then
        say "  → Die vorhandenen Daten werden von $cur_real hierher verschoben."
    fi
    if [ "$DATA_INSIDE" = 1 ]; then
        warn "Das Datenverzeichnis liegt im Webroot und ist nur durch data/.htaccess geschützt. Bei nginx o. Ä. wäre die Datenbank abrufbar – besser außerhalb ablegen (Installer mit --data-dir PFAD erneut starten)."
    fi
}

gather_site() {
    say ""
    say "${C_BOLD}Website und Postfächer${C_RESET}"
    local def_url=${OPT_SITE_URL:-${CUR_SITE_URL:-https://tcd-studio.de}}
    ask SITE_URL "Adresse der Website (mit https://)" "$def_url"
    SITE_URL=${SITE_URL%/}
    valid_url "$SITE_URL" || die "Ungültige Adresse der Website: $SITE_URL (erwartet: https://domain.tld)"
    case $SITE_URL in https://*) ;; *) warn "Die Website-Adresse beginnt nicht mit https:// – Ausweis-Uploads funktionieren nur über HTTPS." ;; esac

    local def_dom=${OPT_MAIL_DOMAIN:-${CUR_MAIL_DOMAIN:-tcd-studio.de}}
    ask MAIL_DOMAIN "Domain der Postfächer (contact@, info@, imprint@, privacy-policy@, no-reply@)" "$def_dom"
    MAIL_DOMAIN=$(printf '%s' "$MAIL_DOMAIN" | tr '[:upper:]' '[:lower:]')
    valid_domain "$MAIL_DOMAIN" || die "Ungültige Domain für die Postfächer: $MAIL_DOMAIN"

    BEHIND_PROXY=$OPT_BEHIND_PROXY
    if [ "$ASSUME_YES" != 1 ] && [ "$BEHIND_PROXY" = 0 ]; then
        if ask_yn "Läuft die Seite hinter einem Proxy/CDN, das HTTPS beendet (z. B. Cloudflare, Load-Balancer)?" n; then BEHIND_PROXY=1; fi
    fi
    local host=${SITE_URL#*://}; host=${host%%[:/]*}
    if [ "$host" != "tcd-studio.de" ] && [ "$host" != "www.tcd-studio.de" ]; then
        warn "Die Website-Adresse ist nicht tcd-studio.de: die statischen Seiten (index.html, privacy/, legal-notice/ …) enthalten weiterhin tcd-studio.de, und backend/config.php (Kontaktformular) muss von Hand angepasst werden."
    fi
    if [ "$MAIL_DOMAIN" != "tcd-studio.de" ]; then
        warn "Andere Postfach-Domain: backend/config.php (Empfänger/Absender des Kontaktformulars) und das Impressum müssen von Hand angepasst werden."
    fi
}

gen_password() {
    local p=""
    if has_cmd openssl; then p=$(openssl rand -base64 36 2>/dev/null | tr -dc 'A-Za-z0-9' | head -c 20 || true); fi
    if [ "${#p}" -lt 16 ]; then p=$(head -c 256 /dev/urandom | tr -dc 'A-Za-z0-9' | head -c 20 || true); fi
    [ "${#p}" -ge 16 ] || die "Es konnte kein Zufalls-Passwort erzeugt werden."
    printf '%s' "$p"
}

gather_admin() {
    [ "$CUR_ADMIN" = yes ] && return 0
    say ""
    say "${C_BOLD}Admin-Konto${C_RESET} – Zugang zum Admin-Bereich (${SITE_URL}/admin/). Dieses Konto prüft Anträge und sieht Ausweisfotos."
    local def_email=${OPT_ADMIN_EMAIL:-}
    ask ADMIN_EMAIL "E-Mail-Adresse des Admins" "$def_email"
    [ -n "$ADMIN_EMAIL" ] || die "Für das Admin-Konto wird eine E-Mail-Adresse gebraucht (--admin-email)."
    ADMIN_EMAIL=$(printf '%s' "$ADMIN_EMAIL" | tr '[:upper:]' '[:lower:]')
    valid_email "$ADMIN_EMAIL" || die "Ungültige E-Mail-Adresse: $ADMIN_EMAIL"
    ask ADMIN_NAME "Name des Admins" "${OPT_ADMIN_NAME:-}"
    no_ctrl "$ADMIN_NAME" || die "Der Name enthält Steuerzeichen."
    [ "${#ADMIN_NAME}" -ge 2 ] && [ "${#ADMIN_NAME}" -le 80 ] || die "Der Name muss 2 bis 80 Zeichen lang sein."

    if [ -n "$OPT_ADMIN_PW_FILE" ]; then
        [ -r "$OPT_ADMIN_PW_FILE" ] || die "Die Passwort-Datei ist nicht lesbar: $OPT_ADMIN_PW_FILE"
        IFS= read -r ADMIN_PW <"$OPT_ADMIN_PW_FILE" || [ -n "$ADMIN_PW" ] || die "Die Passwort-Datei ist leer."
        ADMIN_PW=${ADMIN_PW%$'\r'}
    elif [ -n "${TCD_ADMIN_PASSWORD:-}" ]; then
        ADMIN_PW=$TCD_ADMIN_PASSWORD
    elif [ "$OPT_GEN_PW" = 1 ]; then
        ADMIN_PW=$(gen_password); ADMIN_PW_GENERATED=1
    elif [ "$ASSUME_YES" = 1 ]; then
        die "Im unbeaufsichtigten Modus braucht das Admin-Konto ein Passwort: TCD_ADMIN_PASSWORD, --admin-password-file oder --generate-admin-password."
    else
        if ask_yn "Passwort zufällig erzeugen lassen (wird am Ende einmal angezeigt)?" n; then
            ADMIN_PW=$(gen_password); ADMIN_PW_GENERATED=1
        else
            local again="" tries=0
            while :; do
                tries=$((tries + 1))
                ask_secret ADMIN_PW "Passwort (mind. 10 Zeichen, Eingabe bleibt unsichtbar)"
                ask_secret again "Passwort wiederholen"
                if [ "$ADMIN_PW" != "$again" ]; then
                    printf '  Die Eingaben sind nicht gleich.\n' >&2
                elif ! check_password_quiet; then
                    :
                else
                    break
                fi
                [ "$tries" -lt 5 ] || die "Zu viele Fehlversuche."
            done
        fi
    fi
    check_password_quiet verbose || die "Das Passwort wird nicht akzeptiert."
}

check_password_quiet() {   # prüft $ADMIN_PW mit den Regeln der Anwendung; Meldung nur bei Fehler
    local out rc=0
    out=$(printf '%s\n' "email=$ADMIN_EMAIL" "password=$ADMIN_PW" | hp_stateless password-check 2>&1) || rc=$?
    if [ "$rc" -ne 0 ]; then
        local detail
        detail=$(printf '%s\n' "$out" | awk -F'\t' '$1 == "FAIL" { print $3; exit }')
        printf '  %s\n' "${detail:-Das Passwort wird nicht akzeptiert.}" >&2
        return 1
    fi
    return 0
}

gather_vault() {
    say ""
    say "${C_BOLD}Ausweis-Tresor${C_RESET} – Ausweisfotos werden mit einem ÖFFENTLICHEN Schlüssel verschlüsselt; der PRIVATE Schlüssel gehört nie auf einen Server."
    if [ "$CUR_VAULT_KEY" = ok ]; then
        say "  Es ist bereits ein Tresor-Schlüssel gespeichert (Fingerabdruck $CUR_VAULT_FP) – er bleibt unverändert."
        VAULT_MODE=keep
    else
        if [ -n "$OPT_VAULT_MODE" ]; then
            VAULT_MODE=$OPT_VAULT_MODE
            VAULT_PUBKEY=$OPT_VAULT_PUBKEY
            VAULT_KEYDIR=$OPT_VAULT_KEYDIR
        elif [ "$ASSUME_YES" = 1 ]; then
            VAULT_MODE=later
        else
            say "    1) Später im Admin-Bereich erzeugen (empfohlen – der Schlüssel entsteht in deinem Browser, nie auf dem Server)"
            say "    2) Ich habe schon einen öffentlichen Schlüssel (Datei) – jetzt speichern"
            say "    3) Schlüsselpaar HIER auf dem Server erzeugen (der private Schlüssel muss danach heruntergeladen und gelöscht werden)"
            local choice=""
            ask choice "Auswahl" "1"
            case $choice in
                2) VAULT_MODE="file"; ask VAULT_PUBKEY "Pfad der Datei mit dem öffentlichen Schlüssel (PEM)" "" ;;
                3) VAULT_MODE=generate ;;
                *) VAULT_MODE=later ;;
            esac
        fi
        case $VAULT_MODE in
            file)
                [ -n "$VAULT_PUBKEY" ] || die "Kein Pfad zur Datei mit dem öffentlichen Schlüssel angegeben."
                VAULT_PUBKEY=$(lexical_path "$VAULT_PUBKEY")
                [ -r "$VAULT_PUBKEY" ] || die "Die Datei mit dem öffentlichen Schlüssel ist nicht lesbar: $VAULT_PUBKEY"
                ;;
            generate)
                has_cmd openssl || die "Zum Erzeugen eines Schlüsselpaars wird das Programm \"openssl\" gebraucht."
                [ -n "$VAULT_KEYDIR" ] || { ask VAULT_KEYDIR "Verzeichnis für den privaten Schlüssel (außerhalb des Webroots)" "${HOME:-/root}/tcd-vault-key"; }
                VAULT_KEYDIR=$(physical_path "$VAULT_KEYDIR")
                path_inside "$VAULT_KEYDIR" "$ROOT_REAL" && die "Der private Schlüssel darf nicht im Webroot liegen: $VAULT_KEYDIR"
                [ -e "$VAULT_KEYDIR/id-vault-private-key.pem" ] && die "Dort liegt schon ein Schlüssel: $VAULT_KEYDIR/id-vault-private-key.pem (nichts überschrieben)."
                ;;
        esac
    fi
}

gather_archive() {
    say ""
    say "${C_BOLD}Archiv für Ausweisfotos${C_RESET} – nach der Prüfung liegen die (verschlüsselten) Fotos nur noch hier, bis der Kunde den Dienst beendet."
    say "  Versprochen wird ein getrenntes, geschütztes Speichersystem: am besten ein eigenes Volume/Mount oder ein S3-Bucket."

    if [ -n "$OPT_S3_ENDPOINT" ] || [ -n "$OPT_S3_BUCKET" ]; then
        ARCH_MODE=s3
    elif [ -n "$OPT_ARCHIVE_DIR" ]; then
        ARCH_MODE=dir
    elif [ "$CUR_VAULT_DRIVER" = s3 ] || { [ "$CUR_VAULT_DIR_SET" = yes ] && [ -n "$CUR_VAULT_DIR" ]; }; then
        ARCH_MODE=keep
        say "  Das Archiv ist bereits eingerichtet – es wird nur erneut getestet."
    elif [ "$ASSUME_YES" = 1 ]; then
        ARCH_MODE=dir
    else
        say "    1) Verzeichnis (außerhalb des Webroots, möglichst eigenes Volume)"
        say "    2) S3-kompatibler Speicher (AWS S3, Hetzner, Backblaze, Wasabi, MinIO …)"
        local choice=""
        ask choice "Auswahl" "1"
        if [ "$choice" = 2 ]; then ARCH_MODE=s3; else ARCH_MODE=dir; fi
    fi

    case $ARCH_MODE in
        dir)
            local def=${OPT_ARCHIVE_DIR:-}
            if [ -z "$def" ]; then
                local base
                base=$(dirname -- "$DATA_DIR")
                def="$base/tcd-vault-archive"
                [ "$DATA_INSIDE" = 1 ] && def="$(dirname -- "$ROOT_REAL")/tcd-vault-archive"
            fi
            ask ARCH_DIR "Archiv-Verzeichnis" "$def"
            no_ctrl "$ARCH_DIR" || die "Der Archiv-Pfad enthält Steuerzeichen."
            ARCH_DIR=$(physical_path "$ARCH_DIR")
            path_inside "$ARCH_DIR" "$ROOT_REAL" && die "Das Archiv darf nicht im Webroot liegen: $ARCH_DIR"
            [ "$ARCH_DIR" = "$DATA_DIR" ] && die "Das Archiv muss ein eigenes Verzeichnis sein."
            path_inside "$ARCH_DIR" "$DATA_DIR" && warn "Das Archiv liegt innerhalb des Datenverzeichnisses – das ist KEIN getrenntes Speichersystem und widerspricht der Datenschutzerklärung. Besser: eigenes Volume/Verzeichnis."
            [ -d "$(dirname -- "$ARCH_DIR")" ] || die "Das übergeordnete Verzeichnis von $ARCH_DIR existiert nicht (Volume nicht eingehängt?). Es wird bewusst nicht rekursiv angelegt."
            ;;
        s3)
            S3_ENDPOINT=${OPT_S3_ENDPOINT:-}
            S3_BUCKET=${OPT_S3_BUCKET:-}
            S3_REGION=${OPT_S3_REGION:-eu-central-1}
            S3_PREFIX=${OPT_S3_PREFIX:-tcd-id/}
            S3_PATH_STYLE=${OPT_S3_PATH_STYLE:-true}
            ask S3_ENDPOINT "S3-Endpunkt (https://…)" "$S3_ENDPOINT"
            ask S3_BUCKET "Bucket-Name" "$S3_BUCKET"
            ask S3_REGION "Region" "$S3_REGION"
            ask S3_PREFIX "Präfix im Bucket" "$S3_PREFIX"
            ask S3_PATH_STYLE "Path-Style-Adressierung (true/false)" "$S3_PATH_STYLE"
            [ -n "$S3_ENDPOINT" ] && [ -n "$S3_BUCKET" ] || die "S3 braucht mindestens Endpunkt und Bucket."
            valid_url "$S3_ENDPOINT" || die "Ungültiger S3-Endpunkt: $S3_ENDPOINT"
            case $S3_ENDPOINT in https://*) ;; *) warn "Der S3-Endpunkt nutzt kein https:// – Ausweisdaten (verschlüsselt) und Zugangsdaten würden unverschlüsselt übertragen." ;; esac
            case $S3_PATH_STYLE in true|false) ;; *) die "--s3-path-style muss true oder false sein." ;; esac
            S3_ACCESS=${TCD_S3_ACCESS_KEY:-}
            S3_SECRET=${TCD_S3_SECRET_KEY:-}
            if [ -z "$S3_ACCESS" ] || [ -z "$S3_SECRET" ]; then
                [ "$ASSUME_YES" = 1 ] && die "S3-Zugangsdaten fehlen: Umgebungsvariablen TCD_S3_ACCESS_KEY und TCD_S3_SECRET_KEY setzen."
                [ -n "$S3_ACCESS" ] || ask_secret S3_ACCESS "S3 Access Key (Eingabe bleibt unsichtbar)"
                [ -n "$S3_SECRET" ] || ask_secret S3_SECRET "S3 Secret Key (Eingabe bleibt unsichtbar)"
            fi
            [ -n "$S3_ACCESS" ] && [ -n "$S3_SECRET" ] || die "S3-Zugangsdaten fehlen."
            no_ctrl "$S3_ACCESS$S3_SECRET" || die "Die S3-Zugangsdaten enthalten Steuerzeichen."
            ;;
    esac
}

gather_misc() {
    say ""
    say "${C_BOLD}Sonstiges${C_RESET}"
    if [ "$OPT_NO_CRON" = 1 ]; then
        CRON_WANTED=0
    else
        if ! ask_yn "Täglichen Wartungs-Job einrichten (löscht nie geprüfte Ausweis-Uploads nach 14 Tagen)?" j; then CRON_WANTED=0; fi
    fi
    [[ $OPT_CRON_SCHEDULE =~ ^[0-9*/,-]+\ [0-9*/,-]+\ [0-9*/,-]+\ [0-9*/,A-Za-z-]+\ [0-9*/,A-Za-z-]+$ ]] || die "Ungültiger --cron-schedule (erwartet 5 Felder, z. B. \"17 3 * * *\")."
    TEST_MAIL_TO=$OPT_TEST_MAIL
    if [ -z "$TEST_MAIL_TO" ] && [ "$ASSUME_YES" != 1 ]; then
        if ask_yn "Eine Test-E-Mail senden, um den Mailversand zu prüfen?" n; then
            ask TEST_MAIL_TO "Empfänger der Test-E-Mail" "${ADMIN_EMAIL:-}"
        fi
    fi
    if [ -n "$TEST_MAIL_TO" ]; then valid_email "$TEST_MAIL_TO" || die "Ungültige Test-Mail-Adresse: $TEST_MAIL_TO"; fi
}

show_plan() {
    say ""
    say "${C_BOLD}Zusammenfassung – das wird jetzt eingerichtet${C_RESET}"
    say "  Website            : $SITE_URL"
    say "  Postfächer         : contact@ info@ imprint@ privacy-policy@ no-reply@ $MAIL_DOMAIN"
    say "  Datenverzeichnis   : $DATA_DIR$([ "$MOVE_DATA" = 1 ] && printf '  (bestehende Daten werden verschoben)')"
    if [ "$CUR_ADMIN" = yes ]; then say "  Admin-Konto        : vorhanden (bleibt)"; else say "  Admin-Konto        : $ADMIN_EMAIL ($ADMIN_NAME)"; fi
    case $VAULT_MODE in
        keep) say "  Tresor-Schlüssel   : vorhanden (bleibt)" ;;
        later) say "  Tresor-Schlüssel   : später im Admin-Bereich" ;;
        file) say "  Tresor-Schlüssel   : aus Datei $VAULT_PUBKEY" ;;
        generate) say "  Tresor-Schlüssel   : wird hier erzeugt, privater Schlüssel in $VAULT_KEYDIR" ;;
    esac
    case $ARCH_MODE in
        keep) say "  Archiv             : bleibt wie eingerichtet (wird getestet)" ;;
        dir) say "  Archiv             : Verzeichnis $ARCH_DIR" ;;
        s3) say "  Archiv             : S3 $S3_ENDPOINT / $S3_BUCKET / $S3_PREFIX" ;;
    esac
    say "  Wartungs-Cronjob   : $([ "$CRON_WANTED" = 1 ] && printf 'ja (%s)' "$OPT_CRON_SCHEDULE" || printf 'nein')"
    if [ -n "$WEB_USER" ]; then say "  Webserver-Benutzer : $WEB_USER:$WEB_GROUP"; else say "  Webserver-Benutzer : $(id -un) (aktueller Benutzer)"; fi
    say ""
    if [ "$ASSUME_YES" != 1 ]; then
        ask_yn "So einrichten?" j || die "Abgebrochen – es wurde nichts verändert."
    fi
}

# ------------------------------------------------------------------------------------------------------------------
#  3. Datenverzeichnis, Rechte
# ------------------------------------------------------------------------------------------------------------------
POINTER_FILE=""

own() {            # own PFAD [-R]  – dem Webserver-Benutzer zuordnen (nur als root)
    local rec=""
    [ "${2:-}" = "-R" ] && rec="-R"
    if is_root && [ -n "$WEB_USER" ]; then
        # shellcheck disable=SC2086
        chown $rec "$WEB_USER:$WEB_GROUP" -- "$1"
    elif [ -n "$WEB_GROUP" ] && ! is_root; then
        # shellcheck disable=SC2086
        chgrp $rec "$WEB_GROUP" -- "$1" 2>/dev/null || warn "Die Gruppe von $1 konnte nicht auf $WEB_GROUP gesetzt werden."
    fi
}

setup_data_dir() {
    step "3/8  Datenverzeichnis"
    POINTER_FILE="$ROOT/app/data-dir.local"

    if [ "$MOVE_DATA" = 1 ]; then
        local cur_real e moved=0
        cur_real=$(physical_path "$CUR_DATA_DIR")
        [ -d "$(dirname -- "$DATA_DIR")" ] || die "Das übergeordnete Verzeichnis von $DATA_DIR existiert nicht."
        [ -d "$DATA_DIR" ] || mkdir -m 0750 -- "$DATA_DIR"
        say "  Verschiebe vorhandene Daten von $cur_real nach $DATA_DIR …"
        for e in "$cur_real"/* "$cur_real"/.[!.]* "$cur_real"/..?*; do
            [ -e "$e" ] || [ -L "$e" ] || continue
            case ${e##*/} in .htaccess|.gitkeep) continue ;; esac
            mv -n -- "$e" "$DATA_DIR/" || die "Verschieben von $e nach $DATA_DIR fehlgeschlagen. Der Zustand ist prüfbar: ursprüngliche Dateien liegen in $cur_real."
            moved=$((moved + 1))
        done
        [ -f "$DATA_DIR/config.local.php" ] || die "Nach dem Verschieben fehlt $DATA_DIR/config.local.php – bitte manuell prüfen (Daten liegen in $cur_real bzw. $DATA_DIR)."
        ok "$moved Einträge verschoben."
    fi

    if [ ! -d "$DATA_DIR" ]; then
        local parent
        parent=$(dirname -- "$DATA_DIR")
        [ -d "$parent" ] || die "Das übergeordnete Verzeichnis $parent existiert nicht (Volume nicht eingehängt?). Es wird bewusst nicht rekursiv angelegt."
        mkdir -m 0750 -- "$DATA_DIR" || die "Das Verzeichnis $DATA_DIR konnte nicht angelegt werden (Rechte?)."
        ok "Verzeichnis angelegt: $DATA_DIR"
    else
        ok "Verzeichnis vorhanden: $DATA_DIR"
    fi
    chmod 0750 -- "$DATA_DIR"
    own "$DATA_DIR"

    # Schutzdatei auch außerhalb des Webroots (schadet nicht, falls das Verzeichnis später doch eingebunden wird)
    if [ ! -f "$DATA_DIR/.htaccess" ] && [ -f "$ROOT/data/.htaccess" ]; then
        cp -- "$ROOT/data/.htaccess" "$DATA_DIR/.htaccess" 2>/dev/null || true
    fi

    LOG_FILE="$DATA_DIR/install.log"
    ( umask 077; : >>"$LOG_FILE" ) 2>/dev/null || LOG_FILE=""
    local line
    for line in ${LOG_BUFFER[@]+"${LOG_BUFFER[@]}"}; do
        [ -n "$LOG_FILE" ] && printf '%s\n' "$line" >>"$LOG_FILE"
    done
    LOG_BUFFER=()

    # Zeiger auf das Datenverzeichnis: wirkt für Webserver UND Kommandozeile/Cron, ohne Serverkonfiguration.
    if [ "$DATA_INSIDE" = 1 ]; then
        if [ -f "$POINTER_FILE" ]; then rm -f -- "$POINTER_FILE"; ok "Alter Zeiger auf ein anderes Datenverzeichnis entfernt."; fi
    else
        if [ ! -f "$POINTER_FILE" ] || [ "$(cat -- "$POINTER_FILE" 2>/dev/null || true)" != "$DATA_DIR" ]; then
            printf '%s\n' "$DATA_DIR" >"$POINTER_FILE" || die "Der Zeiger $POINTER_FILE konnte nicht geschrieben werden (Rechte auf app/?)."
            chmod 0644 -- "$POINTER_FILE"
            ok "Zeiger geschrieben: $POINTER_FILE → $DATA_DIR"
        else
            ok "Zeiger auf das Datenverzeichnis ist aktuell."
        fi
        if [ -n "${TCD_DATA_DIR:-}" ] && [ "$(physical_path "$TCD_DATA_DIR")" != "$DATA_DIR" ]; then
            warn "Die Umgebungsvariable TCD_DATA_DIR ($TCD_DATA_DIR) zeigt woanders hin und hat Vorrang vor dem Zeiger – für den Webserver prüfen!"
        fi
    fi
}

setup_permissions() {
    step "4/8  Rechte"
    local d
    mkdir -p -- "$ROOT/legal"
    for d in "$DATA_DIR" "$ROOT/legal"; do
        if is_root && [ -n "$WEB_USER" ]; then own "$d" -R
        elif [ -n "$WEB_GROUP" ] && ! is_root; then own "$d" -R; chmod -R g+rwX -- "$d"
        fi
    done
    chmod 0750 -- "$DATA_DIR"
    [ -f "$DATA_DIR/config.local.php" ] && chmod 0640 -- "$DATA_DIR/config.local.php"
    [ -d "$DATA_DIR/vault-inbox" ] && chmod 0700 -- "$DATA_DIR/vault-inbox"
    if [ -f "$ROOT/sitemap.xml" ]; then
        own "$ROOT/sitemap.xml"
        [ -n "$WEB_GROUP" ] && ! is_root && chmod g+w -- "$ROOT/sitemap.xml"
    fi

    # PHP legt sitemap.xml und die Weiterleitungsseiten über temporäre Dateien im Webroot an: dafür braucht es Schreibrecht auf das Verzeichnis.
    if web_can_write "$ROOT"; then
        ok "Der Webserver kann im Webroot schreiben (sitemap.xml, Weiterleitungsseiten)."
    elif is_root && [ -n "$WEB_USER" ]; then
        chown "$WEB_USER:$WEB_GROUP" -- "$ROOT"
        ok "Das Webroot-Verzeichnis selbst gehört nun $WEB_USER (nur das Verzeichnis, nicht die Dateien darin) – nötig für sitemap.xml."
    elif [ -n "$WEB_GROUP" ] && ! is_root; then
        chgrp "$WEB_GROUP" -- "$ROOT" 2>/dev/null && chmod g+w -- "$ROOT" 2>/dev/null \
            && ok "Das Webroot-Verzeichnis ist für die Gruppe $WEB_GROUP beschreibbar." \
            || warn "Das Webroot-Verzeichnis konnte nicht für $WEB_GROUP beschreibbar gemacht werden (sitemap.xml)."
    else
        warn "Der Webserver kann im Webroot ($ROOT) vermutlich nicht schreiben – die Datei sitemap.xml wird dann nicht aktualisiert. Läuft PHP unter einem anderen Benutzer? Dann --web-group GRUPPE (oder als root --web-user) verwenden."
    fi
    web_can_write "$DATA_DIR" && ok "Der Webserver kann im Datenverzeichnis schreiben." || bad "Der Webserver kann im Datenverzeichnis NICHT schreiben: $DATA_DIR"
    web_can_write "$ROOT/legal" && ok "Der Webserver kann Impressums-Seiten in legal/ schreiben." || warn "Der Webserver kann in $ROOT/legal nicht schreiben – freigegebene Impressen könnten nicht veröffentlicht werden."
}

# ------------------------------------------------------------------------------------------------------------------
#  5. Anwendung initialisieren
# ------------------------------------------------------------------------------------------------------------------
setup_app() {
    step "5/8  Datenbank, Schlüssel und Einstellungen"
    local out
    out=$(hp init 2>&1) || { printf '%s\n' "$out" >&2; die "Die Initialisierung ist fehlgeschlagen (siehe oben)."; }
    show_results "$out"

    local cfg="site_url=$SITE_URL"
    if [ "$MAIL_DOMAIN" != "$CUR_MAIL_DOMAIN" ] || [ "$CUR_INSTALLED" != yes ]; then
        cfg+=$'\n'"mail.from=no-reply@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.contact=contact@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.info=info@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.imprint=imprint@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.privacy=privacy-policy@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.admin_notify=imprint@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.requests=contact@$MAIL_DOMAIN"
    fi
    if [ "$BEHIND_PROXY" = 1 ]; then cfg+=$'\n'"trust_forwarded_proto=true"; fi
    out=$(printf '%s\n' "$cfg" | hp config-set 2>&1) || { printf '%s\n' "$out" >&2; die "Die Einstellungen konnten nicht geschrieben werden."; }
    if [ "$BEHIND_PROXY" = 1 ]; then ok "Einstellungen gespeichert (site_url, Postfächer, Proxy-Modus)."; else ok "Einstellungen gespeichert (site_url, Postfächer)."; fi
    if [ -f "$DATA_DIR/config.local.php" ]; then chmod 0640 -- "$DATA_DIR/config.local.php"; fi
    own "$DATA_DIR" -R 2>/dev/null || true

    todo "Postfächer beim Mail-Anbieter anlegen: contact@$MAIL_DOMAIN, info@$MAIL_DOMAIN, imprint@$MAIL_DOMAIN, privacy-policy@$MAIL_DOMAIN, no-reply@$MAIL_DOMAIN (SPF/DKIM für $MAIL_DOMAIN einrichten, sonst landen Mails im Spam)."
    todo "Datenbank und $DATA_DIR/config.local.php IMMER zusammen sichern (darin liegt der Datenschlüssel; ohne ihn sind die verschlüsselten Adressdaten für immer unlesbar). Backups verschlüsseln."
}

# ------------------------------------------------------------------------------------------------------------------
#  6. Admin
# ------------------------------------------------------------------------------------------------------------------
setup_admin() {
    step "6/8  Admin-Konto"
    if [ "$CUR_ADMIN" = yes ]; then
        ok "Es gibt schon ein Admin-Konto – nichts geändert."
        return 0
    fi
    local out rc=0
    out=$(printf '%s\n' "email=$ADMIN_EMAIL" "name=$ADMIN_NAME" "password=$ADMIN_PW" | hp admin-create 2>&1) || rc=$?
    if [ "$rc" -eq 0 ]; then
        ok "Admin-Konto angelegt: $ADMIN_EMAIL"
        say "  Anmelden unter: $SITE_URL/admin/login.php"
        todo "Als Admin anmelden und unter Account die Zwei-Faktor-Anmeldung aktivieren (für alle, die Ausweisfotos sehen dürfen, dringend empfohlen)."
        if [ "$ADMIN_PW_GENERATED" = 1 ]; then
            SECRET_NOTES+=("Zufälliges Admin-Passwort für $ADMIN_EMAIL: $ADMIN_PW   (jetzt im Passwort-Manager ablegen und nach dem ersten Login ändern)")
        fi
    else
        printf '%s\n' "$out" | awk -F'\t' '$1 == "FAIL" || $1 == "WARN" { print "    " $3 }' >&2
        bad "Das Admin-Konto konnte nicht angelegt werden. Alternativ: php tools/create-admin.php oder $SITE_URL/admin/setup.php (Token steht in $DATA_DIR/setup-token-….txt)."
    fi
    ADMIN_PW=""
}

# ------------------------------------------------------------------------------------------------------------------
#  7. Tresor und Archiv
# ------------------------------------------------------------------------------------------------------------------
secure_delete() {  # Datei überschreiben und löschen (soweit das Dateisystem es zulässt)
    if has_cmd shred; then shred -u -n 3 -- "$1" 2>/dev/null && return 0; fi
    local size
    size=$(wc -c <"$1" 2>/dev/null || echo 0)
    if [ "${size:-0}" -gt 0 ]; then head -c "$size" /dev/urandom >"$1" 2>/dev/null || true; fi
    rm -f -- "$1"
}

setup_vault_key() {
    step "7/8  Ausweis-Tresor"
    local out rc=0 fp=""
    case $VAULT_MODE in
        keep)
            ok "Tresor-Schlüssel vorhanden (Fingerabdruck $CUR_VAULT_FP)."
            ;;
        later)
            warn "Es ist noch kein Tresor-Schlüssel gespeichert – Kunden können erst Ausweisfotos hochladen, wenn er unter Admin → Settings → Identity vault gesetzt ist."
            todo "Admin → Settings → Identity vault: Schlüsselpaar im Browser erzeugen, den PRIVATEN Schlüssel offline sichern (am besten zweimal, nie auf einen Server), den öffentlichen speichern."
            ;;
        file)
            out=$(hp vault-key set <"$VAULT_PUBKEY" 2>&1) || rc=$?
            if [ "$rc" -eq 0 ]; then
                show_results "$out"
                todo "Den zum öffentlichen Schlüssel gehörenden PRIVATEN Schlüssel offline aufbewahren (Sicherungskopie anlegen). Geht er verloren, sind alle Ausweisfotos für immer unlesbar."
            else
                printf '%s\n' "$out" | awk -F'\t' '$1 == "FAIL" { print "    " $3 }' >&2
                bad "Der öffentliche Schlüssel aus $VAULT_PUBKEY wurde nicht übernommen."
            fi
            ;;
        generate)
            generate_vault_key
            ;;
    esac
}

generate_vault_key() {
    local priv="$VAULT_KEYDIR/id-vault-private-key.pem" pub="$VAULT_KEYDIR/id-vault-public-key.pem" out fp rc=0
    ( umask 077; mkdir -p -- "$VAULT_KEYDIR" ) || { bad "Das Verzeichnis für den Schlüssel konnte nicht angelegt werden: $VAULT_KEYDIR"; return 0; }
    chmod 0700 -- "$VAULT_KEYDIR" 2>/dev/null || true
    say "  Erzeuge ein RSA-Schlüsselpaar mit 4096 Bit (das dauert einige Sekunden) …"
    if ! ( umask 077; openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$priv" 2>/dev/null ); then
        bad "openssl konnte kein Schlüsselpaar erzeugen."
        rm -f -- "$priv"
        return 0
    fi
    chmod 0600 -- "$priv"
    openssl pkey -in "$priv" -pubout -out "$pub" 2>/dev/null || { bad "Der öffentliche Schlüssel konnte nicht abgeleitet werden."; return 0; }
    chmod 0644 -- "$pub"
    out=$(hp vault-key set <"$pub" 2>&1) || rc=$?
    if [ "$rc" -ne 0 ]; then
        printf '%s\n' "$out" | awk -F'\t' '$1 == "FAIL" { print "    " $3 }' >&2
        bad "Der öffentliche Schlüssel wurde nicht gespeichert; der private liegt in $priv."
        return 0
    fi
    show_results "$out"
    fp=$(hp vault-key fingerprint <"$pub" 2>/dev/null | awk -F'\t' '$1 == "OK" { print $3 }')

    local host user
    host=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo "SERVER")
    user=$(id -un)
    say ""
    say "  ${C_BOLD}${C_YELLOW}WICHTIG – privater Schlüssel${C_RESET}"
    say "    Datei        : $priv"
    say "    Fingerabdruck: $fp"
    say "    1. JETZT auf deinen eigenen Rechner herunterladen, z. B. von dort aus:"
    say "         scp $user@$host:$(shq "$priv") ."
    say "    2. Mindestens eine Sicherungskopie an einem anderen Ort anlegen (verschlüsselter USB-Stick, Tresor)."
    say "    3. Danach vom Server löschen. Wer diese Datei hat, kann ALLE Ausweisfotos lesen;"
    say "       geht sie verloren, sind alle Fotos für immer unlesbar."
    if [ "$ASSUME_YES" = 1 ]; then
        warn "Der private Tresor-Schlüssel liegt noch auf dem Server: $priv – herunterladen und dann löschen (shred -u $(shq "$priv"))."
        todo "Privaten Tresor-Schlüssel herunterladen, sichern und vom Server löschen: shred -u $(shq "$priv")"
        return 0
    fi
    local confirm=""
    ask confirm "Tippe GESICHERT, wenn du die Datei heruntergeladen und gesichert hast (sonst nur Enter)" ""
    if [ "$confirm" = "GESICHERT" ]; then
        if ask_yn "Den privaten Schlüssel jetzt unwiderruflich vom Server löschen?" j; then
            secure_delete "$priv"
            [ -e "$priv" ] && bad "Die Datei $priv konnte nicht gelöscht werden." || ok "Der private Schlüssel wurde vom Server gelöscht."
        else
            warn "Der private Tresor-Schlüssel liegt noch auf dem Server: $priv"
            todo "Privaten Tresor-Schlüssel vom Server löschen: shred -u $(shq "$priv")"
        fi
    else
        warn "Der private Tresor-Schlüssel liegt noch auf dem Server: $priv"
        todo "Privaten Tresor-Schlüssel herunterladen, sichern und vom Server löschen: shred -u $(shq "$priv")"
    fi
}

setup_archive() {
    local out rc=0 cfg
    case $ARCH_MODE in
        dir)
            if [ ! -d "$ARCH_DIR" ]; then
                ( umask 077; mkdir -m 0700 -- "$ARCH_DIR" ) || { bad "Das Archiv-Verzeichnis konnte nicht angelegt werden: $ARCH_DIR"; return 0; }
                ok "Archiv-Verzeichnis angelegt: $ARCH_DIR"
            else
                ok "Archiv-Verzeichnis vorhanden: $ARCH_DIR"
            fi
            chmod 0700 -- "$ARCH_DIR" 2>/dev/null || true
            own "$ARCH_DIR" -R
            if [ "$(fs_id "$ARCH_DIR")" = "$(fs_id "$DATA_DIR")" ] || [ "$(fs_id "$ARCH_DIR")" = "$(fs_id "$ROOT_REAL")" ]; then
                warn "Das Archiv liegt auf demselben Dateisystem wie die Website ($(describe_fs "$ARCH_DIR")). Das ist technisch in Ordnung, aber KEIN getrenntes Speichersystem – die Datenschutzerklärung verspricht ein getrenntes. Ein eigenes Volume/Mount oder S3 verwenden, bevor Kunden Ausweise hochladen."
                todo "Archiv auf ein getrenntes Speichersystem legen (eigenes Volume oder S3) oder die Datenschutzerklärung anpassen."
            fi
            cfg=$'vault.driver=directory\n'"vault.dir=$ARCH_DIR"
            out=$(printf '%s\n' "$cfg" | hp config-set 2>&1) || rc=$?
            ;;
        s3)
            cfg=$'vault.driver=s3\n'"vault.s3.endpoint=$S3_ENDPOINT"$'\n'"vault.s3.bucket=$S3_BUCKET"$'\n'"vault.s3.region=$S3_REGION"$'\n'"vault.s3.prefix=$S3_PREFIX"$'\n'"vault.s3.path_style=$S3_PATH_STYLE"$'\n'"vault.s3.access_key=$S3_ACCESS"$'\n'"vault.s3.secret_key=$S3_SECRET"
            out=$(printf '%s\n' "$cfg" | hp config-set 2>&1) || rc=$?
            cfg=""
            S3_ACCESS=""; S3_SECRET=""
            ;;
        keep)
            out=""
            ;;
        *)
            return 0
            ;;
    esac
    if [ "$rc" -ne 0 ]; then
        printf '%s\n' "$out" | awk -F'\t' '$1 == "FAIL" { print "    " $3 }' >&2
        bad "Die Archiv-Einstellungen konnten nicht gespeichert werden."
        return 0
    fi
    own "$DATA_DIR" -R 2>/dev/null || true

    say "  Teste das Archiv (schreiben, zurücklesen, löschen eines Probeobjekts) …"
    rc=0
    out=$(hp vault-probe 2>&1) || rc=$?
    show_results "$out"
    if [ "$rc" -ne 0 ]; then
        todo "Archiv reparieren und unter Admin → Settings → Identity vault → Test archive erneut testen (Kunden-Uploads erst danach freigeben)."
    fi
    todo "Das Archiv getrennt von $DATA_DIR sichern bzw. die Aufbewahrungsdauer der Backups festlegen (und in der Datenschutzerklärung nennen). Bei S3: keine Versionierung/Object-Lock, sonst ist die Löschung nicht unwiderruflich."
}

# ------------------------------------------------------------------------------------------------------------------
#  8. Cronjob, Schutz, Tests
# ------------------------------------------------------------------------------------------------------------------
CRON_TAG="# tcd-studio-maintenance"

setup_cron() {
    step "8/8  Wartungs-Job, Zugriffsschutz, Abschlusstest"
    local line
    line="$OPT_CRON_SCHEDULE $(shq "$PHP") $(shq "$ROOT/tools/maintenance.php") >/dev/null 2>&1 $CRON_TAG"

    # Der Job muss einmal laufen können.
    local out rc=0
    out=$(hp_maintenance 2>&1) || rc=$?
    if [ "$rc" -eq 0 ]; then ok "Wartungsskript läuft: $(printf '%s' "$out" | head -n1)"
    else bad "Das Wartungsskript (tools/maintenance.php) lief nicht fehlerfrei: $out"
    fi

    if [ "$CRON_WANTED" != 1 ]; then
        todo "Täglichen Wartungs-Job selbst einrichten:  $line"
        return 0
    fi
    if ! has_cmd crontab; then
        warn "Das Programm \"crontab\" fehlt. Wartungs-Job im Hosting-Panel (Cronjobs) einrichten."
        todo "Täglichen Wartungs-Job im Hosting-Panel anlegen:  $line"
        return 0
    fi
    local -a cron_cmd=(crontab)
    if is_root && [ -n "$WEB_USER" ]; then cron_cmd=(crontab -u "$WEB_USER"); fi
    local current="" new rc2=0
    current=$("${cron_cmd[@]}" -l 2>/dev/null || true)
    if printf '%s\n' "$current" | grep -qxF -- "$line"; then
        ok "Der Wartungs-Job ist bereits eingetragen."
        return 0
    fi
    new=$(printf '%s\n' "$current" | grep -vF -- "$CRON_TAG" || true)
    new=$(printf '%s\n%s\n' "$new" "$line" | sed '/^$/N;/^\n$/D')
    if printf '%s\n' "$new" | "${cron_cmd[@]}" - 2>/dev/null; then
        ok "Wartungs-Job eingetragen ($OPT_CRON_SCHEDULE)${WEB_USER:+ für den Benutzer $WEB_USER}."
    else
        rc2=$?
        warn "Der Wartungs-Job konnte nicht in die crontab geschrieben werden (Exit $rc2)."
        todo "Täglichen Wartungs-Job selbst einrichten:  $line"
    fi
}

hp_maintenance() {
    as_web env "TCD_DATA_DIR=$DATA_DIR" "$PHP" "$ROOT/tools/maintenance.php"
}

HTACCESS_BEGIN="# BEGIN tcd-installer"
HTACCESS_END="# END tcd-installer"

setup_guard() {
    local f="$ROOT/.htaccess" block
    block="$HTACCESS_BEGIN
<FilesMatch \"^(installer\\.sh|install\\.log)\$\">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>
$HTACCESS_END"
    if [ -f "$f" ] && grep -qF -- "$HTACCESS_BEGIN" "$f"; then
        ok "Schutz für installer.sh in .htaccess vorhanden."
    elif [ -f "$f" ]; then
        printf '\n%s\n' "$block" >>"$f" && ok "Schutzregel für installer.sh an die bestehende .htaccess angehängt." || warn "Die .htaccess konnte nicht ergänzt werden."
    else
        printf '%s\n' "$block" >"$f" && chmod 0644 -- "$f" && ok "Schutzregel für installer.sh in neuer .htaccess angelegt." || warn "Die .htaccess konnte nicht angelegt werden."
    fi
    if [[ $WEBSERVER == nginx* || $WEBSERVER == Caddy* || $WEBSERVER == LiteSpeed* ]]; then
        warn "Webserver: $WEBSERVER. Die .htaccess-Dateien der Website gelten hier NICHT (bei LiteSpeed nur teilweise). Die Verzeichnisse app/, data/, tools/, backend/, docs/ und die Datei installer.sh MÜSSEN vom Webserver gesperrt werden."
        say ""
        say "  Beispiel für nginx (in den server{}-Block der Website):"
        say "      location ~ ^/(app|data|tools|backend|docs)(/|\$) { deny all; return 404; }"
        say "      location = /installer.sh { deny all; return 404; }"
        say "      location ~ /\\.(?!well-known) { deny all; }"
        say "      # PHP-Upload-Limits für Ausweisfotos: upload_max_filesize ≥ 9M, post_max_size ≥ 20M, client_max_body_size 20m;"
        todo "Zugriffsregeln für nginx/Caddy einbauen (siehe oben) – ohne sie sind app/ und ggf. data/ abrufbar."
    fi
}

http_code() {
    local code
    code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 12 --connect-timeout 6 "$1" 2>/dev/null) || code=000
    printf '%s' "${code:-000}"
}

http_checks() {
    if [ "$OPT_SKIP_HTTP" = 1 ] || ! has_cmd curl; then
        say "  Abschluss-Test über HTTP übersprungen."
        todo "Von außen prüfen: $SITE_URL/ , /portal/ , /admin/login.php laden; und dass $SITE_URL/data/ , /app/config.php und /installer.sh NICHT abrufbar sind."
        return 0
    fi
    say "  Teste $SITE_URL von diesem Server aus …"
    local code reachable=0
    code=$(http_code "$SITE_URL/")
    if [ "$code" = 000 ]; then
        warn "$SITE_URL ist von diesem Server aus (noch) nicht erreichbar (DNS/HTTPS/Zertifikat?). Der Test wird übersprungen – nach der Domain-Einrichtung wiederholen: bash installer.sh --check"
        todo "Wenn die Domain/HTTPS steht: $SITE_URL/ , /portal/ , /admin/login.php im Browser prüfen und mit  bash installer.sh --check  den Zugriffsschutz testen."
        return 0
    fi
    reachable=1
    case $code in 2*|3*) ok "Startseite antwortet (HTTP $code)." ;; *) warn "Die Startseite antwortet mit HTTP $code." ;; esac
    for path in /portal/login.php /admin/login.php; do
        code=$(http_code "$SITE_URL$path")
        case $code in
            200|302|303) ok "$path antwortet (HTTP $code) – PHP und Datenbank arbeiten im Webserver." ;;
            *) bad "$path antwortet mit HTTP $code – PHP läuft im Webserver nicht fehlerfrei (Server-Log prüfen; Rechte auf das Datenverzeichnis? open_basedir?)." ;;
        esac
    done

    # Schutz: Testdatei in die zu schützenden Verzeichnisse legen und abrufen.
    local token dir rel probe_ok=1
    token="probe-$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
    for rel in app tools backend docs data; do
        dir="$ROOT/$rel"
        [ -d "$dir" ] || continue
        if ! ( umask 022; printf 'installer-probe\n' >"$dir/$token.txt" ) 2>/dev/null; then
            continue
        fi
        code=$(http_code "$SITE_URL/$rel/$token.txt")
        rm -f -- "$dir/$token.txt"
        if [ "$code" = 200 ]; then
            probe_ok=0
            bad "GEFAHR: /$rel/ ist über das Internet abrufbar! Zugriff im Webserver sperren (siehe Hinweise oben)."
        fi
    done
    [ "$probe_ok" = 1 ] && ok "app/, tools/, backend/, docs/ und data/ sind von außen nicht abrufbar."
    code=$(http_code "$SITE_URL/installer.sh")
    if [ "$code" = 200 ]; then
        bad "/installer.sh ist über das Internet abrufbar – löschen (bash installer.sh --remove-installer) oder sperren."
    else
        ok "installer.sh ist von außen nicht abrufbar (HTTP $code)."
    fi
    [ "$reachable" = 1 ] && case $SITE_URL in http://*) warn "Die Website wird ohne HTTPS ausgeliefert – Ausweis-Uploads werden abgelehnt, bis HTTPS aktiv ist." ;; esac
}

final_report() {
    local out rc=0
    out=$(hp report 2>&1) || rc=$?
    say "  Prüfungen der Anwendung (wie im Admin-Bereich unter Settings → System checks):"
    show_results "$out"
}

maybe_test_mail() {
    [ -n "$TEST_MAIL_TO" ] || return 0
    local out rc=0
    out=$(hp mail-test "$TEST_MAIL_TO" 2>&1) || rc=$?
    show_results "$out"
}

# ------------------------------------------------------------------------------------------------------------------
#  Abschluss
# ------------------------------------------------------------------------------------------------------------------
summary() {
    say ""
    say "${C_BOLD}==================================================================${C_RESET}"
    if [ ${#PROBLEMS[@]} -eq 0 ]; then
        say "${C_BOLD}${C_GREEN} Fertig.${C_RESET} Die Installation ist abgeschlossen."
    else
        say "${C_BOLD}${C_YELLOW} Fertig – aber $((${#PROBLEMS[@]})) Problem(e) brauchen deine Aufmerksamkeit:${C_RESET}"
        local p
        for p in "${PROBLEMS[@]}"; do say "   ✘ $p"; done
    fi
    say "${C_BOLD}==================================================================${C_RESET}"
    say "  Admin-Bereich : $SITE_URL/admin/login.php"
    say "  Kundenbereich : $SITE_URL/portal/"
    say "  Datenordner   : $DATA_DIR"
    [ -n "$LOG_FILE" ] && say "  Protokoll     : $LOG_FILE (ohne Passwörter)"
    if [ ${#SECRET_NOTES[@]} -gt 0 ]; then
        say ""
        say "${C_BOLD}${C_YELLOW}  Einmalige Angaben – wird nicht gespeichert, nicht ins Protokoll geschrieben:${C_RESET}"
        local s
        for s in "${SECRET_NOTES[@]}"; do printf '    %s\n' "$s"; done
    fi
    if [ ${#WARNINGS[@]} -gt 0 ]; then
        say ""
        say "${C_BOLD}  Hinweise (${#WARNINGS[@]}):${C_RESET}"
        local w
        for w in "${WARNINGS[@]}"; do say "   ! $w"; done
    fi
    if [ ${#TODOS[@]} -gt 0 ]; then
        say ""
        say "${C_BOLD}  Das musst du noch selbst erledigen:${C_RESET}"
        local t i=1
        for t in "${TODOS[@]}"; do say "   $i. $t"; i=$((i + 1)); done
    fi
    say ""
    say "  Rechtlicher Hinweis: AGB (/agb/), Datenschutzerklärung (/privacy/) und der Umgang mit Ausweisfotos wurden von einer KI"
    say "  entworfen und müssen vor dem Livegang anwaltlich geprüft werden (siehe docs/BETRIEB.md)."
}

remove_installer() {
    [ "$OPT_REMOVE_INSTALLER" = 1 ] || { [ "$ASSUME_YES" != 1 ] && ask_yn "installer.sh jetzt löschen? (bei Bedarf liegt er im Repository weiterhin vor)" n; } || return 0
    rm -f -- "$ROOT/installer.sh" && say "  installer.sh wurde gelöscht." || warn "installer.sh konnte nicht gelöscht werden."
}

# ------------------------------------------------------------------------------------------------------------------
#  Ablauf
# ------------------------------------------------------------------------------------------------------------------
main() {
    parse_args "$@"
    say "${C_BOLD}TCD-Studio – Installer $INSTALLER_VERSION${C_RESET}"
    detect_root
    detect_webserver
    detect_web_user
    check_requirements

    step "2/8  Vorhandene Installation"
    read_current_state
    if [ "$CUR_INSTALLED" = yes ]; then
        say "  Eine Installation wurde gefunden (Datenverzeichnis $CUR_DATA_DIR). Der Installer ergänzt nur, was fehlt, und ändert nichts Bestehendes."
        say "  Admin-Konto: $CUR_ADMIN · Tresor-Schlüssel: $CUR_VAULT_KEY · Archiv: $CUR_VAULT_DRIVER"
    else
        say "  Noch keine Installation – es wird neu eingerichtet."
    fi

    if [ "$MODE_CHECK" = 1 ]; then
        DATA_DIR=$CUR_DATA_DIR
        SITE_URL=$CUR_SITE_URL
        if [ "$CUR_INSTALLED" = yes ]; then
            step "Zustand der Anwendung"
            final_report
        fi
        http_checks
        summary
        [ ${#PROBLEMS[@]} -eq 0 ] || exit 3
        exit 0
    fi

    gather_data_dir
    gather_site
    gather_admin
    gather_vault
    gather_archive
    gather_misc
    show_plan

    setup_data_dir
    setup_permissions
    setup_app
    setup_admin
    setup_vault_key
    setup_archive
    setup_cron
    setup_guard
    maybe_test_mail
    own "$DATA_DIR" -R 2>/dev/null || true
    http_checks
    final_report
    summary
    remove_installer
    [ ${#PROBLEMS[@]} -eq 0 ] || exit 3
    exit 0
}

main "$@"
