#!/usr/bin/env bash
# =====================================================================================================================
#  installer.sh – richtet tcd-studio.de (Website + Impressumsschutz-System) auf dem Server ein.
#
#  Aufruf (im Webroot, per SSH – als der Benutzer, unter dem PHP läuft, oder als root):
#       bash installer.sh              # geführt, mit Rückfragen
#       bash installer.sh --check      # nur prüfen, nichts verändern
#       bash installer.sh --help       # alle Optionen
#
#  Was der Installer macht (er kann gefahrlos erneut gestartet werden und ergänzt nur, was fehlt):
#    1. Server prüfen (PHP-Version, Erweiterungen, E-Mail-Versand, Webserver)
#    2. Datenverzeichnis anlegen – am besten AUSSERHALB des Webroots – bzw. eine bestehende Installation dorthin verschieben
#    3. Rechte für den Webserver-Benutzer setzen
#    4. Datenbank + Datenschlüssel anlegen, Einstellungen (Adresse, Postfächer) schreiben
#    5. ersten Admin-Account anlegen
#    6. Ausweis-Tresor: Archiv (Verzeichnis oder S3) mit Test, öffentlicher Schlüssel
#    7. täglichen Wartungs-Cronjob, Zugriffsschutz, Abschlusstest und Checkliste
#
#  Was der Installer NICHT kann: Postfächer anlegen, DNS/HTTPS einrichten, Zwei-Faktor-Anmeldung aktivieren,
#  den privaten Tresor-Schlüssel für dich aufbewahren. Diese Punkte stehen am Ende in der Checkliste.
#
#  Sicherheit: Als root läuft jeder PHP-Aufruf als Webserver-Benutzer (nie als root). Geheimnisse (Passwörter, S3-Schlüssel)
#  werden nie als Programmargument übergeben, nie vererbt und nie ins Protokoll geschrieben.
# =====================================================================================================================

if [ -z "${BASH_VERSION:-}" ] || [ "${BASH_VERSINFO[0]:-0}" -lt 4 ]; then
    echo "Dieser Installer braucht bash ab Version 4 (aufrufen mit: bash installer.sh)." >&2
    exit 1
fi

set -Eeuo pipefail
umask 027

INSTALLER_VERSION="1.1"

# ------------------------------------------------------------------------------------------------------------------
#  Ausgabe und Protokoll
# ------------------------------------------------------------------------------------------------------------------
C_RESET=''; C_RED=''; C_GREEN=''; C_YELLOW=''; C_BLUE=''; C_BOLD=''
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ] && [ "${TERM:-dumb}" != "dumb" ]; then
    C_RESET=$'\033[0m'; C_RED=$'\033[31m'; C_GREEN=$'\033[32m'; C_YELLOW=$'\033[33m'; C_BLUE=$'\033[36m'; C_BOLD=$'\033[1m'
fi

LOG_FILE=""
WARNINGS=()          # Hinweise für den Abschlussbericht
PROBLEMS=()          # echte Fehler, die der Installer nicht beheben konnte (Exit-Code 3)
TODOS=()             # was der Mensch noch tun muss
SECRET_NOTES=()      # einmalig anzuzeigende Dinge (z. B. erzeugtes Passwort) – nie ins Protokoll
TMP_FILES=()
MOVE_LOCKED_DIR=""   # während eines Umzugs schreibgeschütztes altes Datenverzeichnis (wird bei Abbruch wieder freigegeben)
MOVE_LOCKED_MODE=""

log_line() {
    [ -n "$LOG_FILE" ] || return 0
    printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | sed $'s/\033\\[[0-9;]*m//g' >>"$LOG_FILE" 2>/dev/null || true
}
say()   { printf '%s\n' "$*"; log_line "$*"; }
ok()    { printf '  %s[ OK ]%s %s\n' "$C_GREEN" "$C_RESET" "$*"; log_line "[ OK ] $*"; }
note()  { printf '  %s[INFO]%s %s\n' "$C_BLUE" "$C_RESET" "$*"; log_line "[INFO] $*"; }
warn()  { printf '  %s[WARN]%s %s\n' "$C_YELLOW" "$C_RESET" "$*" >&2; log_line "[WARN] $*"; WARNINGS+=("$*"); }
bad()   { printf '  %s[FEHLER]%s %s\n' "$C_RED" "$C_RESET" "$*" >&2; log_line "[FEHLER] $*"; PROBLEMS+=("$*"); }
step()  { printf '\n%s== %s ==%s\n' "$C_BOLD$C_BLUE" "$*" "$C_RESET"; log_line "== $* =="; }
die()   { printf '\n%s[ABBRUCH]%s %s\n' "$C_RED" "$C_RESET" "$*" >&2; log_line "[ABBRUCH] $*"; exit 1; }
todo()  { TODOS+=("$*"); }

on_error() {
    local code=$? line=$1
    trap - ERR
    printf '\n%s[ABBRUCH]%s Unerwarteter Fehler in Zeile %s (Exit-Code %s). Der Installer kann erneut gestartet werden.\n' \
        "$C_RED" "$C_RESET" "$line" "$code" >&2
    log_line "[ABBRUCH] Unerwarteter Fehler in Zeile $line (Exit $code)"
    exit 1
}
trap 'on_error $LINENO' ERR

on_interrupt() {
    trap - INT TERM
    if [ -t 0 ]; then stty echo 2>/dev/null || true; fi
    printf '\n%s[ABBRUCH]%s Vom Benutzer abgebrochen. Der Installer kann erneut gestartet werden.\n' "$C_RED" "$C_RESET" >&2
    log_line "[ABBRUCH] vom Benutzer abgebrochen"
    exit 130
}
trap on_interrupt INT TERM

cleanup() {
    local f
    if [ -n "$MOVE_LOCKED_DIR" ] && [ -n "$MOVE_LOCKED_MODE" ]; then
        chmod "$MOVE_LOCKED_MODE" -- "$MOVE_LOCKED_DIR" 2>/dev/null || true
    fi
    for f in ${TMP_FILES[@]+"${TMP_FILES[@]}"}; do
        if [ -n "$f" ]; then rm -rf -- "$f" 2>/dev/null || true; fi
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
  --check                 nur prüfen (Server, vorhandene Installation, Zugriffsschutz), nichts verändern
  -y, --yes               keine Rückfragen: es gelten die Optionen unten bzw. sichere Standardwerte
  --php PFAD              PHP-Programm für die Kommandozeile (Standard: automatisch gefunden)
  -h, --help              diese Hilfe

Website
  --site-url URL          Adresse der Website, z. B. https://tcd-studio.de (Standard: bisheriger Wert)
  --mail-domain DOMAIN    Domain der Postfächer contact@/info@/imprint@/privacy-policy@/no-reply@ (Standard: tcd-studio.de)
  --data-dir PFAD         Datenverzeichnis (Datenbank, Schlüssel). Empfohlen: außerhalb des Webroots; ein leeres oder
                          neues Verzeichnis. Liegt eine bestehende Installation in <Webroot>/data, wird sie dorthin
                          VERSCHOBEN (die Website ist dabei kurz nur lesbar). Mit "inside" bleibt es bei <Webroot>/data.
  --web-user NAME         (nur als root) Benutzer, unter dem PHP läuft; Standard: automatisch (www-data, apache, nginx …)
  --web-group NAME        als root: Gruppe des Webservers. Als normaler Benutzer: Gruppe, die Schreibrechte auf
                          Daten, legal/ und sitemap.xml bekommt (wenn PHP unter einem anderen Benutzer läuft)
  --behind-proxy          Die Seite läuft hinter einem TLS-Proxy/CDN, der X-Forwarded-Proto setzt
  --no-proxy              … nicht (setzt eine frühere Proxy-Einstellung zurück)

Admin-Konto (nur wenn noch keins existiert)
  --admin-email ADRESSE   E-Mail-Adresse des Admins
  --admin-name NAME       Anzeigename (2–80 Zeichen)
  --admin-password-file DATEI   Passwort aus Datei (erste Zeile). Alternativ: Umgebungsvariable TCD_ADMIN_PASSWORD
  --generate-admin-password     zufälliges Passwort erzeugen und sofort einmalig anzeigen

Ausweis-Tresor
  --vault-later                 Schlüssel später im Admin-Bereich erzeugen (Standard; empfohlen, weil der Schlüssel dann
                                im Browser entsteht und nie auf dem Server liegt)
  --vault-public-key DATEI      öffentlichen Schlüssel (PEM "BEGIN PUBLIC KEY", RSA ≥ 3072 Bit) aus Datei speichern
  --vault-generate [VERZ]       Schlüsselpaar HIER erzeugen (privater Schlüssel in VERZ, Standard ~/tcd-vault-key).
                                Der private Schlüssel gehört offline gesichert und danach vom Server gelöscht!
  --archive-dir PFAD            Archiv-Verzeichnis (außerhalb des Webroots, am besten eigenes Volume; neu oder leer)
  --s3-endpoint URL --s3-bucket NAME [--s3-region R] [--s3-prefix P] [--s3-path-style true|false]
                                S3-kompatibles Archiv (nur https://). Zugangsdaten aus den Umgebungsvariablen
                                TCD_S3_ACCESS_KEY und TCD_S3_SECRET_KEY (nie als Argument!) – oder verdeckte Eingabe.
  --no-archive                  Archiv jetzt nicht einrichten

Server (nur als root auf Debian/Ubuntu)
  --no-packages           fehlende Pakete (PHP, Apache, certbot) NICHT automatisch per apt installieren
  --no-webserver          die Apache-Seite (VirtualHost) NICHT anlegen/aktivieren
  --no-https              kein Let's-Encrypt-Zertifikat holen

Sonstiges
  --no-cron               keinen Cronjob einrichten
  --cron-schedule "M H * * *"   Zeitplan des Wartungs-Jobs (Standard: 17 3 * * *)
  --test-mail ADRESSE     Test-E-Mail senden
  --skip-http-check       Abschluss-Test über HTTP überspringen
  --remove-installer      installer.sh am Ende löschen

Beispiel (unbeaufsichtigt; das Passwort steht in einer Datei, nicht in der Befehlszeile/History):
  bash installer.sh --yes --admin-email me@example.org --admin-name "Max Muster" \
      --admin-password-file ~/admin-pw.txt --data-dir /var/www/tcd-data --archive-dir /mnt/vault/tcd

Protokoll (ohne Passwörter): ~/tcd-installer.log
Exit-Codes: 0 = alles erledigt · 1 = Abbruch/Fehler · 3 = fertig, aber es gibt offene Probleme (siehe Bericht) · 130 = abgebrochen.
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
OPT_PROXY=""                 # "" = nicht angegeben, 1 = ja, 0 = nein
OPT_ADMIN_EMAIL=""
OPT_ADMIN_NAME=""
OPT_ADMIN_PW_FILE=""
OPT_GEN_PW=0
OPT_VAULT_MODE=""            # file | generate | later
OPT_VAULT_PUBKEY=""
OPT_VAULT_KEYDIR=""
OPT_ARCHIVE_DIR=""
OPT_NO_ARCHIVE=0
OPT_S3_ENDPOINT=""; OPT_S3_BUCKET=""; OPT_S3_REGION=""; OPT_S3_PREFIX=""; OPT_S3_PATH_STYLE=""
OPT_NO_CRON=0
OPT_CRON_SCHEDULE="17 3 * * *"
OPT_TEST_MAIL=""
OPT_SKIP_HTTP=0
OPT_REMOVE_INSTALLER=0
OPT_NO_PACKAGES=0
OPT_NO_WEBSERVER=0
OPT_NO_HTTPS=0

need_val() {       # need_val OPTION [WERT]
    if [ $# -lt 2 ] || [ -z "$2" ]; then die "Die Option $1 braucht einen Wert (siehe --help)."; fi
    case $2 in --*) die "Die Option $1 braucht einen Wert, gefunden wurde aber die Option $2 (siehe --help)." ;; esac
}

parse_args() {
    local arg
    while [ $# -gt 0 ]; do
        arg=$1
        case $arg in
            --check=*|--yes=*|--behind-proxy=*|--no-proxy=*|--generate-admin-password=*|--vault-later=*|--no-archive=*|--no-cron=*|--skip-http-check=*|--remove-installer=*|--no-packages=*|--no-webserver=*|--no-https=*|--help=*)
                die "Die Option ${arg%%=*} erwartet keinen Wert (siehe --help)." ;;
            --*=*) shift; set -- "${arg%%=*}" "${arg#*=}" "$@" ;;
        esac
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
            --behind-proxy) OPT_PROXY=1; shift ;;
            --no-proxy) OPT_PROXY=0; shift ;;
            --admin-email) need_val "$@"; OPT_ADMIN_EMAIL=$2; shift 2 ;;
            --admin-name) need_val "$@"; OPT_ADMIN_NAME=$2; shift 2 ;;
            --admin-password-file) need_val "$@"; OPT_ADMIN_PW_FILE=$2; shift 2 ;;
            --generate-admin-password) OPT_GEN_PW=1; shift ;;
            --vault-public-key) need_val "$@"; OPT_VAULT_MODE="file"; OPT_VAULT_PUBKEY=$2; shift 2 ;;
            --vault-generate)
                OPT_VAULT_MODE="generate"
                if [ $# -ge 2 ] && [ -n "$2" ] && [ "${2#-}" = "$2" ]; then OPT_VAULT_KEYDIR=$2; shift 2; else shift; fi ;;
            --vault-later) OPT_VAULT_MODE="later"; shift ;;
            --archive-dir) need_val "$@"; OPT_ARCHIVE_DIR=$2; shift 2 ;;
            --no-archive) OPT_NO_ARCHIVE=1; shift ;;
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
            --no-packages) OPT_NO_PACKAGES=1; shift ;;
            --no-webserver) OPT_NO_WEBSERVER=1; shift ;;
            --no-https) OPT_NO_HTTPS=1; shift ;;
            *) die "Unbekannte Option: $1 (siehe --help)" ;;
        esac
    done
    if [ -n "$OPT_ARCHIVE_DIR" ] && { [ -n "$OPT_S3_ENDPOINT" ] || [ -n "$OPT_S3_BUCKET" ]; }; then
        die "--archive-dir und --s3-* schließen sich aus."
    fi
}

# ------------------------------------------------------------------------------------------------------------------
#  Eingabe
# ------------------------------------------------------------------------------------------------------------------
eof_die() {
    printf '\n' >&2
    if [ -t 0 ]; then die "Die Eingabe wurde beendet."; fi
    die "Die Eingabe wurde beendet (keine weiteren Antworten). Für den unbeaufsichtigten Betrieb: bash installer.sh --yes … (siehe --help)."
}

read_line() {      # read_line VAR – eine Zeile von stdin (auch die letzte ohne Zeilenende); Ende der Eingabe = Abbruch
    local __rl_v=""
    if ! IFS= read -r __rl_v && [ -z "$__rl_v" ]; then eof_die; fi
    printf -v "$1" '%s' "${__rl_v%$'\r'}"
}

trim() {
    local s=$1
    s="${s#"${s%%[![:space:]]*}"}"
    s="${s%"${s##*[![:space:]]}"}"
    printf '%s' "$s"
}

lower() { printf '%s' "$1" | tr '[:upper:]' '[:lower:]'; }

ask() {            # ask VAR "Frage" "Standard"
    local __ask_var=$1 __ask_q=$2 __ask_def=${3-} __ask_ans=""
    if [ "$ASSUME_YES" = 1 ]; then printf -v "$__ask_var" '%s' "$__ask_def"; return 0; fi
    if [ -n "$__ask_def" ]; then printf '  %s [%s]: ' "$__ask_q" "$__ask_def" >&2; else printf '  %s: ' "$__ask_q" >&2; fi
    read_line __ask_ans
    __ask_ans=$(trim "$__ask_ans")
    if [ -z "$__ask_ans" ]; then __ask_ans=$__ask_def; fi
    printf -v "$__ask_var" '%s' "$__ask_ans"
}

ask_choice() {     # ask_choice VAR "Frage" Standard Erlaubt…
    local __ch_var=$1 __ch_q=$2 __ch_def=$3 __ch_ans="" __ch_c
    shift 3
    if [ "$ASSUME_YES" = 1 ]; then printf -v "$__ch_var" '%s' "$__ch_def"; return 0; fi
    while :; do
        ask __ch_ans "$__ch_q" "$__ch_def"
        for __ch_c in "$@"; do
            if [ "$__ch_ans" = "$__ch_c" ]; then printf -v "$__ch_var" '%s' "$__ch_ans"; return 0; fi
        done
        printf '  Bitte eine der Möglichkeiten angeben: %s\n' "$*" >&2
    done
}

ask_yn() {         # ask_yn "Frage" j|n  -> 0 = ja
    local q=$1 def=${2:-j} a=""
    if [ "$ASSUME_YES" = 1 ]; then [ "$def" = j ]; return; fi
    local hint="J/n"
    if [ "$def" = n ]; then hint="j/N"; fi
    while :; do
        printf '  %s [%s]: ' "$q" "$hint" >&2
        read_line a
        a=$(lower "$(trim "$a")")
        if [ -z "$a" ]; then a=$def; fi
        case $a in j|ja|y|yes) return 0 ;; n|nein|no) return 1 ;; esac
        printf '  Bitte mit j oder n antworten.\n' >&2
    done
}

ask_secret() {     # ask_secret VAR "Frage"
    local __as_var=$1 __as_q=$2 __as_ans=""
    if [ "$ASSUME_YES" = 1 ]; then die "Internes Problem: ein Geheimnis kann im unbeaufsichtigten Modus nicht abgefragt werden."; fi
    printf '  %s: ' "$__as_q" >&2
    if [ -t 0 ]; then
        if ! IFS= read -rs __as_ans && [ -z "$__as_ans" ]; then eof_die; fi
        printf '\n' >&2
    else
        if ! IFS= read -r __as_ans && [ -z "$__as_ans" ]; then eof_die; fi
    fi
    printf -v "$__as_var" '%s' "${__as_ans%$'\r'}"
}

# ------------------------------------------------------------------------------------------------------------------
#  Hilfsfunktionen: Pfade, Rechte, PHP
# ------------------------------------------------------------------------------------------------------------------
shq() {            # in einfache Anführungszeichen setzen (portabel, auch bash 4.2)
    printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"
}

lexical_path() {   # absoluter Pfad ohne "." und ".." (Symlinks bleiben unaufgelöst)
    local p=$1 part tilde='~'
    local -a parts=() stack=()
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

physical_path() {  # wie lexical_path, der existierende Teil wird über Symlinks hinweg aufgelöst
    local lex d tail="" base
    lex=$(lexical_path "$1")
    d=$lex
    while [ ! -d "$d" ] && [ "$d" != "/" ]; do
        base=${d##*/}
        tail="/$base$tail"
        d=${d%/*}
        if [ -z "$d" ]; then d=/; fi
    done
    d=$(cd -P -- "$d" 2>/dev/null && pwd -P) || d=$lex
    d=${d%/}
    printf '%s' "${d}${tail}"
}

path_inside() {    # path_inside KIND ELTERN -> 0, wenn KIND gleich ELTERN ist oder darunter liegt
    case "$1/" in "${2%/}"/*) return 0 ;; esac
    return 1
}

check_path_text() { # Pfad ohne Steuerzeichen und ohne führende/abschließende Leerzeichen
    if [[ $1 == *[[:cntrl:]]* ]]; then die "Der Pfad enthält Steuerzeichen: $2"; fi
    if [ "$(trim "$1")" != "$1" ]; then die "Der Pfad beginnt oder endet mit Leerzeichen: \"$1\" ($2)"; fi
}

dir_is_empty() { [ -d "$1" ] && [ -z "$(ls -A -- "$1" 2>/dev/null)" ]; }

has_cmd() { command -v "$1" >/dev/null 2>&1; }

is_root() { [ "$(id -u)" = "0" ]; }

# Als Webserver-Benutzer ausführen (als root); sonst direkt.
as_web() {
    if is_root; then
        if has_cmd runuser; then runuser -u "$WEB_USER" -- "$@"
        else sudo -n -u "$WEB_USER" -- "$@"
        fi
    else
        "$@"
    fi
}

web_can_write() {  # 0, wenn PHP (Webserver-Benutzer) PFAD beschreiben kann; 2 = nicht prüfbar
    if is_root; then
        as_web test -w "$1" 2>/dev/null
    elif [ -n "$WEB_GROUP" ]; then
        return 2
    else
        [ -w "$1" ]
    fi
}

web_can_enter() { # 0, wenn der Webserver-Benutzer das Verzeichnis betreten und lesen kann
    if is_root; then as_web test -x "$1" 2>/dev/null && as_web test -r "$1" 2>/dev/null; else [ -x "$1" ] && [ -r "$1" ]; fi
}

own() {            # own PFAD [-R]  – als root dem Webserver-Benutzer zuordnen (Symlinks werden nie verfolgt)
    local rec=""
    if [ "${2:-}" = "-R" ]; then rec="-R"; fi
    if is_root; then
        # shellcheck disable=SC2086
        chown -h $rec "$WEB_USER:$WEB_GROUP" -- "$1"
    elif [ -n "$WEB_GROUP" ]; then
        # shellcheck disable=SC2086
        if ! chgrp -h $rec "$WEB_GROUP" -- "$1" 2>/dev/null; then warn "Die Gruppe von $1 konnte nicht auf $WEB_GROUP gesetzt werden."; fi
    fi
}

web_chmod() {      # chmod als Webserver-Benutzer (als root: verhindert, dass ein untergeschobener Symlink root-Dateien ändert)
    if is_root; then as_web chmod "$1" -- "$2" 2>/dev/null || true; else chmod "$1" -- "$2" 2>/dev/null || true; fi
}

find_php() {
    local -a cands=()
    local c v
    if [ -n "$OPT_PHP" ]; then
        if ! has_cmd "$OPT_PHP"; then die "--php $OPT_PHP: dieses Programm gibt es nicht."; fi
        cands=("$OPT_PHP")
    else
        if [ -n "${PHP_BIN:-}" ]; then cands+=("$PHP_BIN"); fi
        cands+=(php php8.5 php8.4 php8.3 php8.2 php8.1 php8.0 php7.4)
        for c in /opt/plesk/php/*/bin/php /opt/alt/php*/usr/bin/php /usr/local/bin/php /usr/local/php*/bin/php; do
            if [ -x "$c" ]; then cands+=("$c"); fi
        done
    fi
    for c in "${cands[@]}"; do
        has_cmd "$c" || continue
        c=$(command -v "$c")
        case $c in /*) ;; *) c="$(cd -P -- "$(dirname -- "$c")" && pwd -P)/${c##*/}" ;; esac
        v=$(as_web "$c" -r 'echo PHP_SAPI === "cli" && PHP_VERSION_ID >= 70400 ? PHP_VERSION : "";' 2>/dev/null || true)
        if [ -n "$v" ]; then
            PHP=$c
            PHP_VER=$v
            return 0
        fi
    done
    if [ -n "$OPT_PHP" ]; then die "--php $OPT_PHP ist kein PHP ab Version 7.4 für die Kommandozeile (php-cli)."; fi
    return 1
}

HELPER=""
STATELESS_DIR=""
hp() {             # PHP-Hilfsprogramm; als root immer im Namen des Webserver-Benutzers
    local -a envv=()
    if [ -n "${DATA_DIR:-}" ]; then envv=("TCD_DATA_DIR=$DATA_DIR"); fi
    as_web env ${envv[@]+"${envv[@]}"} "$PHP" "$HELPER" "$@"
}

hp_noenv() {       # wie hp, aber die Anwendung sucht ihr Datenverzeichnis selbst (Zeiger/Standard) – für den Zustand
    as_web env -u TCD_DATA_DIR "$PHP" "$HELPER" "$@"
}

hp_stateless() {   # für Prüfungen ohne Daten (Passwortregeln, Schlüssel-Fingerabdruck): leeres Wegwerf-Verzeichnis
    as_web env "TCD_DATA_DIR=$STATELESS_DIR" "$PHP" "$HELPER" "$@"
}

hp_get() {         # hp_get "$ausgabe" schluessel -> Wert der INFO-Zeile
    printf '%s\n' "$1" | awk -F'\t' -v k="$2" '$1 == "INFO" && $2 == k { print $3; exit }' | tr -d '\000-\037\177'
}

hp_fail_text() {   # Text der ersten FAIL/WARN-Zeile
    printf '%s\n' "$1" | awk -F'\t' '($1 == "FAIL" || $1 == "WARN") { print $3; exit }'
}

HP_FAILS=0
show_results() {   # Ergebnis-Zeilen des Helfers anzeigen; Anzahl FAIL in HP_FAILS
    local st label detail
    HP_FAILS=0
    while IFS=$'\t' read -r st label detail; do
        case $st in
            OK) ok "$label${detail:+ – $detail}" ;;
            WARN) warn "$label${detail:+ – $detail}" ;;
            FAIL) HP_FAILS=$((HP_FAILS + 1)); bad "$label${detail:+ – $detail}" ;;
            *) : ;;
        esac
    done <<<"$1"
}

# ------------------------------------------------------------------------------------------------------------------
#  1. Umgebung und Voraussetzungen
# ------------------------------------------------------------------------------------------------------------------
ROOT=""; ROOT_REAL=""; PHP=""; PHP_VER=""; WEB_USER=""; WEB_GROUP=""; WEBSERVER="unbekannt"; SITE_TAG=""

detect_root() {
    local src=${BASH_SOURCE[0]}
    if has_cmd readlink && readlink -f -- "$src" >/dev/null 2>&1; then src=$(readlink -f -- "$src"); fi
    ROOT=$(cd -P -- "$(dirname -- "$src")" && pwd -P)
    ROOT_REAL=$ROOT
    if [ ! -f "$ROOT/app/bootstrap.php" ] || [ ! -f "$ROOT/tools/install-helper.php" ]; then
        die "Der Installer muss im Hauptverzeichnis der Website liegen (dort, wo auch app/ und tools/ liegen). Gefunden: $ROOT"
    fi
    if [[ $ROOT == *[[:cntrl:]]* ]]; then die "Der Pfad der Website enthält Steuerzeichen."; fi
    HELPER="$ROOT/tools/install-helper.php"
    SITE_TAG="# tcd-studio-maintenance site=$(printf '%s' "$ROOT" | cksum | awk '{print $1}')"
    local h=${HOME:-}
    if [ -n "$h" ] && [ -d "$h" ] && [ -w "$h" ] && ! path_inside "$(physical_path "$h")" "$ROOT_REAL"; then
        LOG_FILE="$h/tcd-installer.log"
        if [ -L "$LOG_FILE" ] || ! ( umask 077; : >>"$LOG_FILE" ) 2>/dev/null; then LOG_FILE=""; fi
    fi
}

detect_webserver() {
    local found="" p
    if has_cmd pgrep; then
        for p in apache2:Apache httpd:Apache nginx:nginx caddy:Caddy litespeed:LiteSpeed lshttpd:LiteSpeed openlitespeed:LiteSpeed; do
            if pgrep -x "${p%%:*}" >/dev/null 2>&1; then found=${p#*:}; break; fi
        done
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
        if [ -n "$WEB_GROUP" ] && ! getent group "$WEB_GROUP" >/dev/null 2>&1 && ! id -Gn 2>/dev/null | tr ' ' '\n' | grep -qxF -- "$WEB_GROUP"; then
            die "Die Gruppe --web-group $WEB_GROUP gibt es nicht."
        fi
        return 0
    fi
    local uid u
    if [ -n "$OPT_WEB_USER" ]; then
        id -u "$OPT_WEB_USER" >/dev/null 2>&1 || die "Der Benutzer --web-user $OPT_WEB_USER existiert nicht."
        WEB_USER=$OPT_WEB_USER
    else
        if has_cmd ps; then
            uid=$(ps -eo uid=,comm= 2>/dev/null | awk '$1 != 0 && ($2 ~ /^(apache2|httpd|nginx|php-fpm[0-9.]*|lshttpd|litespeed|caddy)$/) { print $1; exit }' || true)
            if [ -n "$uid" ]; then u=$(id -nu "$uid" 2>/dev/null || true); if [ -n "$u" ]; then WEB_USER=$u; fi; fi
        fi
        if [ -z "$WEB_USER" ]; then
            for u in www-data apache nginx www httpd; do
                if id -u "$u" >/dev/null 2>&1; then WEB_USER=$u; break; fi
            done
        fi
    fi
    if [ -z "$WEB_USER" ]; then
        die "Als root gestartet, aber der Benutzer des Webservers wurde nicht gefunden. Bitte mit --web-user NAME angeben (PHP läuft dann nie als root)."
    fi
    if [ "$WEB_USER" = root ] || [ "$(id -u "$WEB_USER")" = 0 ]; then die "Der Webserver-Benutzer darf nicht root sein."; fi
    WEB_GROUP=${OPT_WEB_GROUP:-$(id -gn "$WEB_USER")}
    getent group "$WEB_GROUP" >/dev/null 2>&1 || die "Die Gruppe --web-group $WEB_GROUP gibt es nicht."
    if ! has_cmd runuser && ! has_cmd sudo; then
        die "Als root werden \"runuser\" oder \"sudo\" gebraucht, um PHP als $WEB_USER auszuführen."
    fi
}

REQ_FAILED=0
check_requirements() {
    step "1/7  Server prüfen"
    say "  Installationsordner : $ROOT"
    say "  Benutzer            : $(id -un) (uid $(id -u))"
    say "  Webserver           : $WEBSERVER"
    if is_root; then
        say "  Webserver-Benutzer  : $WEB_USER:$WEB_GROUP (PHP läuft als dieser Benutzer, Daten gehören ihm)"
    elif [ -n "$WEB_GROUP" ]; then
        say "  Webserver-Gruppe    : $WEB_GROUP (bekommt Schreibrechte auf Daten, legal/ und sitemap.xml)"
    else
        say "  (Nicht als root: es wird angenommen, dass PHP unter demselben Benutzer läuft – typisch bei Shared-Hosting.)"
    fi

    install_packages
    if ! find_php; then
        die "Kein PHP ab Version 7.4 für die Kommandozeile gefunden. Mit --php /pfad/zu/php angeben (z. B. /usr/bin/php8.2)."
    fi
    say "  PHP (Kommandozeile) : $PHP  (Version $PHP_VER)"
    if [ -n "$LOG_FILE" ]; then say "  Protokoll           : $LOG_FILE"; fi

    STATELESS_DIR=$(mktemp -d "${TMPDIR:-/tmp}/tcd-installer.XXXXXX")
    TMP_FILES+=("$STATELESS_DIR")
    chmod 0711 -- "$STATELESS_DIR"

    local out
    out=$(as_web "$PHP" "$HELPER" check 2>&1) || true
    show_results "$out"
    if [ "$HP_FAILS" -gt 0 ]; then
        REQ_FAILED=1
        if [ "$MODE_CHECK" != 1 ]; then
            die "Es fehlen Voraussetzungen (siehe oben). Beheben (z. B. fehlende PHP-Erweiterungen im Hosting-Panel aktivieren) und den Installer erneut starten. Es wurde nichts verändert."
        fi
    fi
    todo "Prüfen, dass das PHP des WEBSERVERS ebenfalls ≥ 7.4 ist und dieselben Erweiterungen hat (die Kommandozeile kann abweichen): nach der Anmeldung im Admin-Bereich unter Settings → System checks."
    if ! has_cmd openssl; then note "Das Programm \"openssl\" fehlt – ein Tresor-Schlüssel kann nicht hier erzeugt werden (aber im Admin-Bereich im Browser)."; fi
    if ! has_cmd curl; then note "Das Programm \"curl\" fehlt – der Abschluss-Test über HTTP entfällt."; fi
}

# ------------------------------------------------------------------------------------------------------------------
#  2. Zustand und Einstellungen
# ------------------------------------------------------------------------------------------------------------------
CUR_DATA_DIR=""; CUR_SOURCE=""; CUR_PROBLEM=""; CUR_INSTALLED="no"; CUR_HAS_CONFIG="no"; CUR_ADMIN="no"; CUR_VAULT_KEY="none"
CUR_VAULT_FP=""; CUR_SITE_URL=""; CUR_MAIL_DOMAIN=""; CUR_PROXY="false"; CUR_ARCHIVE_FILES=0
CUR_VAULT_DRIVER="directory"; CUR_VAULT_DIR=""; CUR_VAULT_DIR_SET="no"
CUR_S3_ENDPOINT=""; CUR_S3_BUCKET=""; CUR_S3_REGION=""; CUR_S3_PREFIX=""; CUR_S3_PATH_STYLE=""; CUR_S3_KEYS="no"

read_current_state() {
    local out
    if ! out=$(hp_noenv status 2>&1); then
        printf '%s\n' "$out" >&2
        die "Der Zustand der Installation konnte nicht gelesen werden (siehe Meldung oben). Es wurde nichts verändert."
    fi
    CUR_DATA_DIR=$(hp_get "$out" data_dir)
    CUR_SOURCE=$(hp_get "$out" data_dir_source)
    CUR_PROBLEM=$(hp_get "$out" problem)
    CUR_INSTALLED=$(hp_get "$out" installed)
    CUR_HAS_CONFIG=$(hp_get "$out" has_config)
    CUR_ADMIN=$(hp_get "$out" admin); CUR_ADMIN=${CUR_ADMIN:-no}
    CUR_VAULT_KEY=$(hp_get "$out" vault_key); CUR_VAULT_KEY=${CUR_VAULT_KEY:-none}
    CUR_VAULT_FP=$(hp_get "$out" vault_fingerprint)
    CUR_SITE_URL=$(hp_get "$out" site_url)
    CUR_MAIL_DOMAIN=$(hp_get "$out" mail_domain)
    CUR_PROXY=$(hp_get "$out" trust_forwarded_proto); CUR_PROXY=${CUR_PROXY:-false}
    CUR_ARCHIVE_FILES=$(hp_get "$out" archive_files); CUR_ARCHIVE_FILES=${CUR_ARCHIVE_FILES:-0}
    CUR_VAULT_DRIVER=$(hp_get "$out" vault_driver); CUR_VAULT_DRIVER=${CUR_VAULT_DRIVER:-directory}
    CUR_VAULT_DIR=$(hp_get "$out" vault_dir)
    CUR_VAULT_DIR_SET=$(hp_get "$out" vault_dir_configured); CUR_VAULT_DIR_SET=${CUR_VAULT_DIR_SET:-no}
    CUR_S3_ENDPOINT=$(hp_get "$out" s3_endpoint)
    CUR_S3_BUCKET=$(hp_get "$out" s3_bucket)
    CUR_S3_REGION=$(hp_get "$out" s3_region)
    CUR_S3_PREFIX=$(hp_get "$out" s3_prefix)
    CUR_S3_PATH_STYLE=$(hp_get "$out" s3_path_style)
    CUR_S3_KEYS=$(hp_get "$out" s3_keys); CUR_S3_KEYS=${CUR_S3_KEYS:-no}
}

explain_state_problem() {  # bricht bei einem kaputten Zeiger ab (nichts wird neu angelegt)
    case $CUR_PROBLEM in
        pointer_target_missing)
            die "app/data-dir.local verweist auf $CUR_DATA_DIR, aber dieses Verzeichnis gibt es nicht (Speichermedium nicht eingehängt? umbenannt?). Es wurde bewusst NICHTS neu angelegt – sonst entstünde eine leere Installation mit neuem Schlüssel. Verzeichnis wieder verfügbar machen und erneut starten. Nur wenn die alten Daten wirklich weg sein sollen: app/data-dir.local und data/.tcd-moved löschen." ;;
        pointer_invalid)
            die "app/data-dir.local ist unlesbar oder enthält keinen absoluten Pfad. Bitte prüfen (eine Zeile mit dem Pfad des Datenverzeichnisses). Es wurde nichts verändert." ;;
        moved_marker_without_pointer)
            die "data/.tcd-moved sagt, dass die Daten an einen anderen Ort verschoben wurden ($(head -n1 "$ROOT/data/.tcd-moved" 2>/dev/null | tr -d '\000-\037\177' || true)), aber app/data-dir.local fehlt (bei einem Update gelöscht?). Zeiger wiederherstellen: den Pfad in app/data-dir.local schreiben. Es wurde nichts verändert." ;;
    esac
}

valid_url() { [[ $1 =~ ^https?://[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?(:[0-9]{1,5})?/?$ ]]; }
valid_domain() { [[ $1 =~ ^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$ ]]; }
valid_email() { [[ $1 =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$ ]]; }

describe_fs() {
    local p=$1
    while [ ! -e "$p" ] && [ "$p" != / ]; do p=${p%/*}; if [ -z "$p" ]; then p=/; fi; done
    df -P -- "$p" 2>/dev/null | awk 'NR==2 { print $1 " (eingehängt unter " $6 ")" }' || true
}

fs_id() {
    local p=$1
    while [ ! -e "$p" ] && [ "$p" != / ]; do p=${p%/*}; if [ -z "$p" ]; then p=/; fi; done
    stat -c '%d' -- "$p" 2>/dev/null || stat -f '%d' -- "$p" 2>/dev/null || echo "?"
}

DATA_DIR=""; DATA_INSIDE=0; MOVE_FROM=""; RESUME_MOVE=0
SITE_URL=""; MAIL_DOMAIN=""; PROXY="false"
ADMIN_EMAIL=""; ADMIN_NAME=""; ADMIN_PW=""; ADMIN_PW_GENERATED=0
VAULT_MODE=""; VAULT_PUBKEY=""; VAULT_KEYDIR=""
ARCH_MODE=""; ARCH_DIR=""; S3_ENDPOINT=""; S3_BUCKET=""; S3_REGION=""; S3_PREFIX=""; S3_PATH_STYLE=""; S3_ACCESS=""; S3_SECRET=""; S3_KEEP_KEYS=0
CRON_WANTED=1
TEST_MAIL_TO=""

# Ist PFAD als (neues) Verzeichnis zulässig? Bricht sonst ab.
validate_new_dir() {   # validate_new_dir PFAD "Bezeichnung" ERLAUBTE_MARKIERUNG
    local p=$1 what=$2 marker=${3:-}
    if [ "$p" = / ]; then die "$what darf nicht / sein."; fi
    if path_inside "$ROOT_REAL" "$p"; then die "$what darf kein übergeordnetes Verzeichnis der Website sein: $p"; fi
    if [ -n "$DATA_DIR" ] && [ "$p" != "$DATA_DIR" ] && path_inside "$DATA_DIR" "$p"; then die "$what darf das Datenverzeichnis nicht enthalten: $p"; fi
    if [ -L "$p" ]; then die "$what darf kein symbolischer Link sein: $p"; fi
    if [ -e "$p" ] && [ ! -d "$p" ]; then die "$what ist eine Datei, kein Verzeichnis: $p"; fi
    if [ -d "$p" ] && ! dir_is_empty "$p"; then
        if [ -n "$marker" ] && [ -e "$p/$marker" ]; then return 0; fi
        die "$what ist nicht leer: $p – aus Sicherheitsgründen werden nur neue oder leere Verzeichnisse übernommen (sonst würden fremde Dateien dem Webserver übergeben). Nichts wurde verändert."
    fi
    if [ ! -d "$p" ] && [ ! -d "$(dirname -- "$p")" ]; then
        die "Das übergeordnete Verzeichnis von $p existiert nicht (Speichermedium nicht eingehängt?). Es wird bewusst nicht rekursiv angelegt."
    fi
}

gather_data_dir() {
    say ""
    say "${C_BOLD}Datenverzeichnis${C_RESET} – hier liegen Datenbank, Datenschlüssel und Sitzungen (private Kundendaten!)."
    say "  Empfohlen: ein Verzeichnis AUSSERHALB des Webroots, damit niemand es über den Browser abrufen kann."

    local parent suggestion="" cur_real="" chosen=""
    parent=$(dirname -- "$ROOT_REAL")
    if [ "$parent" != "/" ] && [ -w "$parent" ]; then suggestion="$parent/tcd-studio-data"; fi
    if [ -n "$CUR_DATA_DIR" ]; then cur_real=$(physical_path "$CUR_DATA_DIR"); fi
    local cur_inside=0
    if [ "$cur_real" = "$ROOT_REAL/data" ]; then cur_inside=1; fi

    if [ -n "$OPT_DATA_DIR" ]; then
        chosen=$OPT_DATA_DIR
    elif [ "$CUR_SOURCE" = pointer ] && [ "$CUR_HAS_CONFIG" != yes ] && dir_is_empty "$cur_real"; then
        chosen=$cur_real
        say "  app/data-dir.local nennt $cur_real (noch leer) – dort wird eingerichtet."
    elif [ "$CUR_HAS_CONFIG" = yes ]; then
        chosen=$cur_real
        say "  Vorhandene Installation gefunden: $cur_real"
        if [ "$cur_inside" = 1 ] && [ "$ASSUME_YES" != 1 ] && [ -n "$suggestion" ]; then
            if ask_yn "Die Daten liegen im Webroot. Jetzt nach außerhalb verschieben? Die Website ist dabei kurz nur lesbar." j; then
                ask chosen "Ziel-Verzeichnis (neu oder leer)" "$suggestion"
            fi
        fi
    elif [ "$ASSUME_YES" = 1 ]; then
        chosen=${suggestion:-inside}
    else
        say "  Hinweis: Wurde die Website bisher über die Server-Variable TCD_DATA_DIR (z. B. SetEnv) betrieben, hier GENAU diesen Pfad angeben."
        if [ -n "$suggestion" ]; then
            say "    1) außerhalb des Webroots (empfohlen)"
            say "    2) im Webroot unter data/ (nur durch .htaccess geschützt – funktioniert nur mit Apache)"
            local choice=""
            ask_choice choice "Auswahl" 1 1 2
            if [ "$choice" = 2 ]; then chosen=inside; else ask chosen "Pfad des Datenverzeichnisses (neu oder leer)" "$suggestion"; fi
        else
            note "Das Verzeichnis über dem Webroot ist für diesen Benutzer nicht beschreibbar – ein Ort außerhalb kann nicht vorgeschlagen werden."
            ask chosen "Pfad außerhalb des Webroots (leer = im Webroot unter data/)" ""
            if [ -z "$chosen" ]; then chosen=inside; fi
        fi
    fi

    if [ "$chosen" = inside ]; then
        DATA_DIR="$ROOT_REAL/data"
    else
        check_path_text "$chosen" "Datenverzeichnis"
        DATA_DIR=$(physical_path "$chosen")
    fi
    DATA_INSIDE=0
    if path_inside "$DATA_DIR" "$ROOT_REAL"; then
        if [ "$DATA_DIR" != "$ROOT_REAL/data" ]; then
            die "Das Datenverzeichnis darf nicht an anderer Stelle im Webroot liegen (nur $ROOT_REAL/data oder außerhalb). Angegeben: $DATA_DIR"
        fi
        DATA_INSIDE=1
    fi

    if [ "$DATA_DIR" != "$cur_real" ] && [ "$CUR_HAS_CONFIG" = yes ]; then
        # Umzug einer bestehenden Installation
        if [ "$DATA_INSIDE" = 1 ]; then die "Bestehende Daten werden nie in den Webroot verschoben (Sicherheitsrisiko). Aktuell: $cur_real"; fi
        if path_inside "$DATA_DIR" "$cur_real"; then die "Das neue Datenverzeichnis darf nicht im bisherigen liegen ($cur_real)."; fi
        if [ "$CUR_VAULT_DRIVER" = directory ] && [ -n "$CUR_VAULT_DIR" ] && path_inside "$(physical_path "$CUR_VAULT_DIR")" "$cur_real" && [ "$CUR_ARCHIVE_FILES" -gt 0 ]; then
            die "Das Archiv ($CUR_VAULT_DIR) liegt im bisherigen Datenverzeichnis und enthält $CUR_ARCHIVE_FILES Ausweisdatei(en) – ein automatischer Umzug ist dann nicht möglich. Bitte von Hand umziehen. Nichts wurde verändert."
        fi
        validate_new_dir "$DATA_DIR" "Das Ziel-Datenverzeichnis" ".tcd-move-incomplete"
        if [ -e "$DATA_DIR/.tcd-move-incomplete" ]; then RESUME_MOVE=1; fi
        MOVE_FROM=$cur_real
    elif [ "$CUR_HAS_CONFIG" != yes ]; then
        # neue Installation: nur ein neues/leeres Verzeichnis – oder ein bestehendes Datenverzeichnis dieser Anwendung (z. B. bisher per TCD_DATA_DIR)
        if [ "$DATA_INSIDE" = 1 ]; then
            : # <Webroot>/data enthält nur .htaccess/.gitkeep
        elif [ -d "$DATA_DIR" ] && [ ! -L "$DATA_DIR" ] && [ -f "$DATA_DIR/config.local.php" ]; then
            local owner allowed
            owner=$(stat -c '%u' -- "$DATA_DIR/config.local.php" 2>/dev/null || echo x)
            allowed=" 0 $(id -u) "
            if is_root; then allowed+="$(id -u "$WEB_USER") "; fi
            case $allowed in *" $owner "*) ;; *) die "In $DATA_DIR liegt eine config.local.php, die einem anderen Benutzer (uid $owner) gehört – sie wird aus Sicherheitsgründen nicht übernommen." ;; esac
            say "  In $DATA_DIR liegt bereits ein Datenverzeichnis dieser Anwendung – es wird übernommen."
        else
            validate_new_dir "$DATA_DIR" "Das Datenverzeichnis" ""
        fi
    fi
    if [ "$DATA_INSIDE" = 0 ] && [ ! -w "$ROOT/app" ]; then
        die "Der Zeiger auf das Datenverzeichnis kann nicht geschrieben werden: $ROOT/app ist für $(id -un) nicht beschreibbar."
    fi

    say "  → Datenverzeichnis: $DATA_DIR$([ "$DATA_INSIDE" = 1 ] && printf ' (im Webroot – nur Apache/.htaccess-Schutz!)')"
    if [ -n "$MOVE_FROM" ]; then say "  → Die vorhandenen Daten werden von $MOVE_FROM hierher verschoben$([ "$RESUME_MOVE" = 1 ] && printf ' (ein abgebrochener Umzug wird fortgesetzt)')."; fi
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
    MAIL_DOMAIN=$(lower "$MAIL_DOMAIN")
    valid_domain "$MAIL_DOMAIN" || die "Ungültige Domain für die Postfächer: $MAIL_DOMAIN"

    PROXY=$CUR_PROXY
    if [ "$OPT_PROXY" = 1 ]; then PROXY=true
    elif [ "$OPT_PROXY" = 0 ]; then PROXY=false
    elif [ "$ASSUME_YES" != 1 ]; then
        local d=n
        if [ "$CUR_PROXY" = true ]; then d=j; fi
        if ask_yn "Läuft die Seite hinter einem Proxy/CDN, das HTTPS beendet (z. B. Cloudflare, Load-Balancer)? Nur dann j." "$d"; then PROXY=true; else PROXY=false; fi
    fi

    local host=${SITE_URL#*://}; host=${host%%[:/]*}
    if [ "$host" != "tcd-studio.de" ] && [ "$host" != "www.tcd-studio.de" ]; then
        note "Die Website-Adresse ist nicht tcd-studio.de: die statischen Seiten (index.html, privacy/, legal-notice/ …) enthalten weiterhin tcd-studio.de."
        todo "Statische Seiten und backend/config.php (Kontaktformular) an die Domain $host anpassen."
    fi
    if [ "$MAIL_DOMAIN" != "tcd-studio.de" ]; then
        todo "backend/config.php (Empfänger/Absender des Kontaktformulars) und das Impressum auf Adressen @$MAIL_DOMAIN umstellen."
    fi
}

gen_password() {
    local p=""
    if has_cmd openssl; then p=$(openssl rand -base64 48 2>/dev/null | tr -dc 'A-Za-z0-9' | cut -c1-20 || true); fi
    if [ "${#p}" -lt 16 ]; then p=$(od -An -N64 -tx1 /dev/urandom | tr -dc 'a-f0-9' | cut -c1-24 || true); fi
    [ "${#p}" -ge 16 ] || die "Es konnte kein Zufalls-Passwort erzeugt werden."
    printf '%s' "$p"
}

check_password_quiet() {   # prüft $ADMIN_PW mit den Regeln der Anwendung; Meldung nur bei Fehler
    local out rc=0
    out=$(printf '%s\n' "email=$ADMIN_EMAIL" "password=$ADMIN_PW" | hp_stateless password-check 2>&1) || rc=$?
    if [ "$rc" -ne 0 ]; then
        local detail
        detail=$(hp_fail_text "$out")
        printf '  %s\n' "${detail:-Das Passwort wird nicht akzeptiert.}" >&2
        return 1
    fi
    return 0
}

gather_admin() {
    # Geheimnisse aus der Umgebung übernehmen und sofort aus der Umgebung entfernen (nicht an Kindprozesse vererben)
    local env_pw=$ENV_ADMIN_PW
    ENV_ADMIN_PW=""
    if [ "$CUR_ADMIN" = yes ]; then return 0; fi
    say ""
    say "${C_BOLD}Admin-Konto${C_RESET} – Zugang zum Admin-Bereich (${SITE_URL}/admin/). Dieses Konto prüft Anträge und sieht Ausweisfotos."
    ask ADMIN_EMAIL "E-Mail-Adresse des Admins" "${OPT_ADMIN_EMAIL:-}"
    if [ -z "$ADMIN_EMAIL" ]; then die "Für das Admin-Konto wird eine E-Mail-Adresse gebraucht (--admin-email)."; fi
    ADMIN_EMAIL=$(lower "$ADMIN_EMAIL")
    valid_email "$ADMIN_EMAIL" || die "Ungültige E-Mail-Adresse: $ADMIN_EMAIL"
    ask ADMIN_NAME "Name des Admins" "${OPT_ADMIN_NAME:-}"
    if [[ $ADMIN_NAME == *[[:cntrl:]]* ]]; then die "Der Name enthält Steuerzeichen."; fi
    if [ "${#ADMIN_NAME}" -lt 2 ] || [ "${#ADMIN_NAME}" -gt 80 ]; then die "Der Name muss 2 bis 80 Zeichen lang sein."; fi

    if [ -n "$OPT_ADMIN_PW_FILE" ]; then
        [ -r "$OPT_ADMIN_PW_FILE" ] || die "Die Passwort-Datei ist nicht lesbar: $OPT_ADMIN_PW_FILE"
        IFS= read -r ADMIN_PW <"$OPT_ADMIN_PW_FILE" || true
        ADMIN_PW=${ADMIN_PW%$'\r'}
        if [ -z "$ADMIN_PW" ]; then die "Die Passwort-Datei ist leer."; fi
    elif [ -n "$env_pw" ]; then
        ADMIN_PW=$env_pw
    elif [ "$OPT_GEN_PW" = 1 ]; then
        ADMIN_PW=$(gen_password); ADMIN_PW_GENERATED=1
    elif [ "$ASSUME_YES" = 1 ]; then
        die "Im unbeaufsichtigten Modus braucht das Admin-Konto ein Passwort: --admin-password-file, TCD_ADMIN_PASSWORD oder --generate-admin-password."
    elif ask_yn "Passwort zufällig erzeugen lassen (wird direkt nach dem Anlegen einmal angezeigt)?" n; then
        ADMIN_PW=$(gen_password); ADMIN_PW_GENERATED=1
    else
        local again="" tries=0
        while :; do
            tries=$((tries + 1))
            ask_secret ADMIN_PW "Passwort (mind. 10 Zeichen, Eingabe bleibt unsichtbar)"
            ask_secret again "Passwort wiederholen"
            if [ "$ADMIN_PW" != "$again" ]; then
                printf '  Die Eingaben sind nicht gleich.\n' >&2
            elif check_password_quiet; then
                break
            fi
            if [ "$tries" -ge 5 ]; then die "Zu viele Fehlversuche."; fi
        done
        again=""
    fi
    check_password_quiet || die "Das Passwort wird nicht akzeptiert."
}

gather_archive() {
    say ""
    say "${C_BOLD}Archiv für Ausweisfotos${C_RESET} – nach der Prüfung liegen die (verschlüsselten) Fotos nur noch hier, bis der Kunde den Dienst beendet."
    say "  Die Datenschutzerklärung verspricht ein getrenntes, geschütztes Speichersystem: am besten ein eigenes Volume oder ein S3-Bucket."
    local env_ak=$ENV_S3_AK env_sk=$ENV_S3_SK
    ENV_S3_AK=""; ENV_S3_SK=""

    local configured=0
    if [ "$CUR_VAULT_DRIVER" = s3 ] || [ "$CUR_VAULT_DIR_SET" = yes ] || [ "$CUR_ARCHIVE_FILES" -gt 0 ]; then configured=1; fi

    if [ "$OPT_NO_ARCHIVE" = 1 ]; then
        ARCH_MODE=skip
    elif [ -n "$OPT_S3_ENDPOINT" ] || [ -n "$OPT_S3_BUCKET" ]; then
        ARCH_MODE=s3
    elif [ -n "$OPT_ARCHIVE_DIR" ]; then
        ARCH_MODE=dir
    elif [ "$configured" = 1 ]; then
        ARCH_MODE=keep
        if [ "$CUR_VAULT_DRIVER" = s3 ]; then say "  Das Archiv ist bereits eingerichtet (S3 $CUR_S3_ENDPOINT / $CUR_S3_BUCKET) – es wird nur erneut getestet."
        else say "  Das Archiv ist bereits eingerichtet ($CUR_VAULT_DIR) – es wird nur erneut getestet."; fi
    elif [ "$ASSUME_YES" = 1 ]; then
        ARCH_MODE=dir
    else
        say "    1) Verzeichnis (außerhalb des Webroots, möglichst eigenes Volume)"
        say "    2) S3-kompatibler Speicher (AWS S3, Hetzner, Backblaze, Wasabi, MinIO …)"
        say "    3) später einrichten"
        local choice=""
        ask_choice choice "Auswahl" 1 1 2 3
        case $choice in 2) ARCH_MODE=s3 ;; 3) ARCH_MODE=skip ;; *) ARCH_MODE=dir ;; esac
    fi

    # Ein Archiv mit Ausweisdateien wird nie stillschweigend umgehängt: die Dateien wären dann unauffindbar.
    if [ "$CUR_ARCHIVE_FILES" -gt 0 ] && { [ "$ARCH_MODE" = dir ] || [ "$ARCH_MODE" = s3 ]; }; then
        local same=0
        if [ "$ARCH_MODE" = dir ] && [ "$CUR_VAULT_DRIVER" = directory ] && [ -n "$OPT_ARCHIVE_DIR" ] && [ "$(physical_path "$OPT_ARCHIVE_DIR")" = "$(physical_path "$CUR_VAULT_DIR")" ]; then same=1; fi
        if [ "$ARCH_MODE" = s3 ] && [ "$CUR_VAULT_DRIVER" = s3 ] && [ "${OPT_S3_ENDPOINT%/}" = "${CUR_S3_ENDPOINT%/}" ] && [ "$OPT_S3_BUCKET" = "$CUR_S3_BUCKET" ] && { [ -z "$OPT_S3_PREFIX" ] || [ "$OPT_S3_PREFIX" = "$CUR_S3_PREFIX" ]; }; then same=1; fi
        if [ "$same" = 0 ]; then
            die "Im bisherigen Archiv liegen $CUR_ARCHIVE_FILES Ausweisdatei(en). Ein Wechsel des Archivs ist nur von Hand möglich (Dateien kopieren, dann Einstellung ändern) – sonst wären die Dateien unauffindbar und würden bei einer Löschung nicht mitgelöscht. Nichts wurde verändert."
        fi
    fi

    case $ARCH_MODE in
        dir)
            local def=${OPT_ARCHIVE_DIR:-}
            if [ -z "$def" ]; then
                local base
                if [ "$DATA_INSIDE" = 1 ]; then base=$(dirname -- "$ROOT_REAL"); else base=$(dirname -- "$DATA_DIR"); fi
                if [ "$base" != / ] && [ -w "$base" ]; then def="$base/tcd-vault-archive"; fi
            fi
            if [ -z "$def" ] && [ "$ASSUME_YES" = 1 ]; then
                ARCH_MODE=skip
                warn "Für das Archiv gibt es keinen beschreibbaren Ort außerhalb des Webroots – es wurde NICHT eingerichtet (mit --archive-dir PFAD oder --s3-… erneut starten)."
                return 0
            fi
            ask ARCH_DIR "Archiv-Verzeichnis (neu oder leer)" "$def"
            if [ -z "$ARCH_DIR" ]; then ARCH_MODE=skip; return 0; fi
            check_path_text "$ARCH_DIR" "Archiv"
            ARCH_DIR=$(physical_path "$ARCH_DIR")
            if path_inside "$ARCH_DIR" "$ROOT_REAL"; then die "Das Archiv darf nicht im Webroot liegen: $ARCH_DIR"; fi
            if [ "$ARCH_DIR" = "$DATA_DIR" ]; then die "Das Archiv muss ein eigenes Verzeichnis sein (nicht das Datenverzeichnis)."; fi
            if [ -n "$MOVE_FROM" ] && path_inside "$ARCH_DIR" "$MOVE_FROM"; then die "Das Archiv darf nicht im bisherigen Datenverzeichnis liegen."; fi
            validate_new_dir "$ARCH_DIR" "Das Archiv-Verzeichnis" ".tcd-vault"
            if path_inside "$ARCH_DIR" "$DATA_DIR"; then
                note "Das Archiv liegt innerhalb des Datenverzeichnisses – das ist KEIN getrenntes Speichersystem."
                todo "Archiv auf ein getrenntes Speichersystem legen (eigenes Volume oder S3) oder die Datenschutzerklärung anpassen."
            fi
            ;;
        s3)
            local keep_defaults=0
            if [ "$CUR_VAULT_DRIVER" = s3 ]; then keep_defaults=1; fi
            if [ "$keep_defaults" = 1 ]; then
                S3_ENDPOINT=${OPT_S3_ENDPOINT:-$CUR_S3_ENDPOINT}; S3_BUCKET=${OPT_S3_BUCKET:-$CUR_S3_BUCKET}
                S3_REGION=${OPT_S3_REGION:-$CUR_S3_REGION}; S3_PREFIX=${OPT_S3_PREFIX:-$CUR_S3_PREFIX}; S3_PATH_STYLE=${OPT_S3_PATH_STYLE:-$CUR_S3_PATH_STYLE}
            else
                S3_ENDPOINT=$OPT_S3_ENDPOINT; S3_BUCKET=$OPT_S3_BUCKET; S3_REGION=$OPT_S3_REGION; S3_PREFIX=$OPT_S3_PREFIX; S3_PATH_STYLE=$OPT_S3_PATH_STYLE
            fi
            S3_REGION=${S3_REGION:-eu-central-1}
            S3_PREFIX=${S3_PREFIX:-tcd-id/}
            S3_PATH_STYLE=${S3_PATH_STYLE:-true}
            ask S3_ENDPOINT "S3-Endpunkt (https://…)" "$S3_ENDPOINT"
            ask S3_BUCKET "Bucket-Name" "$S3_BUCKET"
            ask S3_REGION "Region" "$S3_REGION"
            ask S3_PREFIX "Präfix im Bucket" "$S3_PREFIX"
            ask_choice S3_PATH_STYLE "Path-Style-Adressierung (true/false)" "$S3_PATH_STYLE" true false
            S3_ENDPOINT=${S3_ENDPOINT%/}
            if [ -z "$S3_ENDPOINT" ] || [ -z "$S3_BUCKET" ]; then die "S3 braucht mindestens Endpunkt und Bucket."; fi
            valid_url "$S3_ENDPOINT" || die "Ungültiger S3-Endpunkt: $S3_ENDPOINT"
            case $S3_ENDPOINT in
                https://*) ;;
                http://127.0.0.1|http://127.0.0.1:*|http://localhost|http://localhost:*) note "S3-Endpunkt ohne https (nur zum lokalen Testen)." ;;
                *) die "Der S3-Endpunkt muss mit https:// beginnen (die Anwendung lehnt unverschlüsselte Verbindungen ab)." ;;
            esac
            case $S3_PATH_STYLE in true|false) ;; *) die "--s3-path-style muss true oder false sein." ;; esac
            if ! [[ $S3_BUCKET =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ ]] || [[ $S3_BUCKET == *..* ]]; then
                die "Ungültiger Bucket-Name: $S3_BUCKET (3–63 Zeichen: Kleinbuchstaben, Ziffern, Punkt, Bindestrich)"
            fi
            [[ $S3_REGION =~ ^[A-Za-z0-9._-]{1,128}$ ]] || die "Ungültige Region: $S3_REGION"
            if [ -n "$S3_PREFIX" ]; then
                if ! [[ $S3_PREFIX =~ ^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*/?$ ]] || [[ $S3_PREFIX =~ (^|/)\.\.?(/|$) ]]; then
                    die "Ungültiges Präfix: $S3_PREFIX (z. B. tcd-id/ – ohne führenden Schrägstrich, ohne ..)"
                fi
            fi
            S3_ACCESS=$env_ak; S3_SECRET=$env_sk
            if [ -z "$S3_ACCESS" ] && [ -z "$S3_SECRET" ] && [ "$CUR_VAULT_DRIVER" = s3 ] && [ "$CUR_S3_KEYS" = yes ]; then
                if ask_yn "Die gespeicherten S3-Zugangsdaten behalten?" j; then S3_KEEP_KEYS=1; fi
            fi
            if [ "$S3_KEEP_KEYS" != 1 ]; then
                if [ -z "$S3_ACCESS" ] || [ -z "$S3_SECRET" ]; then
                    if [ "$ASSUME_YES" = 1 ]; then die "S3-Zugangsdaten fehlen: Umgebungsvariablen TCD_S3_ACCESS_KEY und TCD_S3_SECRET_KEY setzen."; fi
                    if [ -z "$S3_ACCESS" ]; then ask_secret S3_ACCESS "S3 Access Key (Eingabe bleibt unsichtbar)"; fi
                    if [ -z "$S3_SECRET" ]; then ask_secret S3_SECRET "S3 Secret Key (Eingabe bleibt unsichtbar)"; fi
                fi
                S3_ACCESS=$(trim "$S3_ACCESS"); S3_SECRET=$(trim "$S3_SECRET")
                if ! [[ $S3_ACCESS =~ ^[!-~]{1,256}$ ]] || ! [[ $S3_SECRET =~ ^[!-~]{1,256}$ ]]; then
                    die "Die S3-Zugangsdaten fehlen oder enthalten Leer-/Steuerzeichen."
                fi
            fi
            ;;
    esac
}

gather_vault() {
    say ""
    say "${C_BOLD}Ausweis-Tresor${C_RESET} – Ausweisfotos werden mit einem ÖFFENTLICHEN Schlüssel verschlüsselt; der PRIVATE Schlüssel gehört nie auf einen Server."
    local out fp
    if [ "$CUR_VAULT_KEY" = ok ]; then
        VAULT_MODE=keep
        say "  Es ist bereits ein Tresor-Schlüssel gespeichert (Fingerabdruck $CUR_VAULT_FP) – er bleibt unverändert."
        if [ "$OPT_VAULT_MODE" = file ]; then
            out=$(hp_stateless vault-key fingerprint <"$OPT_VAULT_PUBKEY" 2>&1) || true
            fp=$(printf '%s\n' "$out" | awk -F'\t' '$1 == "OK" { print $3; exit }')
            if [ "$fp" != "$CUR_VAULT_FP" ]; then
                bad "--vault-public-key wurde NICHT übernommen: es ist bereits ein anderer Schlüssel gespeichert. Ein Austausch geht nur im Admin-Bereich (Settings → Identity vault, mit Passwort); ältere Fotos brauchen weiterhin den alten privaten Schlüssel."
            fi
        elif [ "$OPT_VAULT_MODE" = generate ]; then
            warn "--vault-generate wurde ignoriert: es ist bereits ein Tresor-Schlüssel gespeichert."
        fi
        local leftover="${OPT_VAULT_KEYDIR:-${HOME:-/root}/tcd-vault-key}/id-vault-private-key.pem"
        if [ -f "$leftover" ]; then bad "Der private Tresor-Schlüssel liegt noch auf dem Server: $leftover – herunterladen, sichern, dann löschen:  shred -u $(shq "$leftover")"; fi
        return 0
    fi
    if [ "$CUR_VAULT_KEY" = invalid ]; then
        warn "Der gespeicherte Tresor-Schlüssel ist ungültig – bitte im Admin-Bereich (Settings → Identity vault) ersetzen."
        VAULT_MODE=later
        return 0
    fi

    if [ -n "$OPT_VAULT_MODE" ]; then
        VAULT_MODE=$OPT_VAULT_MODE; VAULT_PUBKEY=$OPT_VAULT_PUBKEY; VAULT_KEYDIR=$OPT_VAULT_KEYDIR
    elif [ "$ASSUME_YES" = 1 ]; then
        VAULT_MODE=later
    else
        say "    1) Später im Admin-Bereich erzeugen (empfohlen – der Schlüssel entsteht in deinem Browser, nie auf dem Server)"
        say "    2) Ich habe schon einen öffentlichen Schlüssel (Datei) – jetzt speichern"
        say "    3) Schlüsselpaar HIER auf dem Server erzeugen (der private Schlüssel muss danach heruntergeladen und gelöscht werden)"
        local choice=""
        ask_choice choice "Auswahl" 1 1 2 3
        case $choice in
            2) VAULT_MODE="file"; ask VAULT_PUBKEY "Pfad der Datei mit dem öffentlichen Schlüssel (PEM)" "" ;;
            3) VAULT_MODE="generate" ;;
            *) VAULT_MODE="later" ;;
        esac
    fi

    if [ "$VAULT_MODE" = file ]; then
        if [ -z "$VAULT_PUBKEY" ]; then die "Kein Pfad zur Datei mit dem öffentlichen Schlüssel angegeben."; fi
        VAULT_PUBKEY=$(lexical_path "$VAULT_PUBKEY")
        if [ ! -f "$VAULT_PUBKEY" ] || [ ! -r "$VAULT_PUBKEY" ]; then die "Die Datei mit dem öffentlichen Schlüssel ist nicht lesbar: $VAULT_PUBKEY"; fi
        if ! out=$(hp_stateless vault-key fingerprint <"$VAULT_PUBKEY" 2>&1); then
            die "$(hp_fail_text "$out") Datei: $VAULT_PUBKEY. Es wurde nichts verändert."
        fi
        say "  → Schlüssel geprüft, Fingerabdruck $(printf '%s\n' "$out" | awk -F'\t' '$1 == "OK" { print $3; exit }')"
    elif [ "$VAULT_MODE" = generate ]; then
        has_cmd openssl || die "Zum Erzeugen eines Schlüsselpaars wird das Programm \"openssl\" gebraucht."
        if [ -z "$VAULT_KEYDIR" ]; then ask VAULT_KEYDIR "Verzeichnis für den privaten Schlüssel (außerhalb des Webroots)" "${HOME:-/root}/tcd-vault-key"; fi
        check_path_text "$VAULT_KEYDIR" "Schlüssel-Verzeichnis"
        VAULT_KEYDIR=$(physical_path "$VAULT_KEYDIR")
        local d
        for d in "$ROOT_REAL" "$DATA_DIR" ${ARCH_DIR:+"$ARCH_DIR"} ${MOVE_FROM:+"$MOVE_FROM"}; do
            if path_inside "$VAULT_KEYDIR" "$d"; then die "Der private Schlüssel darf nicht im Webroot, im Datenverzeichnis oder im Archiv liegen: $VAULT_KEYDIR"; fi
            if path_inside "$d" "$VAULT_KEYDIR"; then die "Das Schlüssel-Verzeichnis darf Website, Daten oder Archiv nicht enthalten: $VAULT_KEYDIR"; fi
        done
        if [ -L "$VAULT_KEYDIR" ]; then die "Das Schlüssel-Verzeichnis darf kein symbolischer Link sein."; fi
        if [ -d "$VAULT_KEYDIR" ]; then
            if [ ! -O "$VAULT_KEYDIR" ]; then die "Das Schlüssel-Verzeichnis gehört nicht $(id -un): $VAULT_KEYDIR"; fi
            if [ -e "$VAULT_KEYDIR/id-vault-private-key.pem" ]; then die "Dort liegt schon ein privater Schlüssel: $VAULT_KEYDIR/id-vault-private-key.pem (nichts überschrieben)."; fi
            if ! dir_is_empty "$VAULT_KEYDIR"; then die "Das Schlüssel-Verzeichnis ist nicht leer: $VAULT_KEYDIR"; fi
        elif [ ! -d "$(dirname -- "$VAULT_KEYDIR")" ]; then
            die "Das übergeordnete Verzeichnis von $VAULT_KEYDIR existiert nicht."
        fi
    else
        VAULT_MODE=later
    fi
}

gather_misc() {
    say ""
    say "${C_BOLD}Sonstiges${C_RESET}"
    if [ "$OPT_NO_CRON" = 1 ]; then
        CRON_WANTED=0
    elif ! ask_yn "Täglichen Wartungs-Job einrichten (löscht nie geprüfte Ausweis-Uploads nach 14 Tagen)?" j; then
        CRON_WANTED=0
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
    say "  Proxy/CDN          : $([ "$PROXY" = true ] && printf 'ja (X-Forwarded-Proto wird vertraut)' || printf 'nein')"
    say "  Datenverzeichnis   : $DATA_DIR"
    if [ -n "$MOVE_FROM" ]; then say "  Umzug              : von $MOVE_FROM (die Website ist währenddessen nur lesbar)"; fi
    if [ "$CUR_ADMIN" = yes ]; then say "  Admin-Konto        : vorhanden (bleibt)"; else say "  Admin-Konto        : $ADMIN_EMAIL ($ADMIN_NAME)"; fi
    case $ARCH_MODE in
        keep) say "  Archiv             : bleibt wie eingerichtet (wird getestet)" ;;
        dir) say "  Archiv             : Verzeichnis $ARCH_DIR" ;;
        s3) say "  Archiv             : S3 $S3_ENDPOINT / $S3_BUCKET / $S3_PREFIX$([ "$S3_KEEP_KEYS" = 1 ] && printf ' (gespeicherte Zugangsdaten)')" ;;
        skip) say "  Archiv             : später (bis dahin: vault-archive im Datenverzeichnis)" ;;
    esac
    case $VAULT_MODE in
        keep) say "  Tresor-Schlüssel   : vorhanden (bleibt)" ;;
        later) say "  Tresor-Schlüssel   : später im Admin-Bereich" ;;
        file) say "  Tresor-Schlüssel   : aus Datei $VAULT_PUBKEY" ;;
        generate) say "  Tresor-Schlüssel   : wird hier erzeugt, privater Schlüssel in $VAULT_KEYDIR" ;;
    esac
    say "  Wartungs-Cronjob   : $([ "$CRON_WANTED" = 1 ] && printf 'ja (%s)' "$OPT_CRON_SCHEDULE" || printf 'nein')"
    if is_root; then say "  Webserver-Benutzer : $WEB_USER:$WEB_GROUP"; else say "  Benutzer           : $(id -un)${WEB_GROUP:+, Gruppe $WEB_GROUP}"; fi
    say ""
    if [ "$ASSUME_YES" != 1 ]; then
        ask_yn "So einrichten?" j || die "Abgebrochen – es wurde nichts verändert."
    fi
}

# ------------------------------------------------------------------------------------------------------------------
#  3. Datenverzeichnis (inkl. Umzug), Rechte
# ------------------------------------------------------------------------------------------------------------------
POINTER_FILE=""

write_marker() {   # write_marker DATEI TEXT – in einem Verzeichnis, das PHP beschreiben kann, als Webserver-Benutzer schreiben
    local dir
    dir=$(dirname -- "$1")
    if is_root && [ "$(stat -c '%u' -- "$dir" 2>/dev/null || echo 0)" != 0 ]; then
        as_web sh -c 'rm -f -- "$2"; umask 022; printf "%s\n" "$1" >"$2"' sh "$2" "$1" 2>/dev/null || warn "Die Markierung $1 konnte nicht geschrieben werden."
    else
        rm -f -- "$1" 2>/dev/null || true
        ( umask 022; set -C; printf '%s\n' "$2" >"$1" ) 2>/dev/null || warn "Die Markierung $1 konnte nicht geschrieben werden."
    fi
}

write_pointer() {
    local tmp="$POINTER_FILE.tmp$$"
    printf '%s\n' "$DATA_DIR" >"$tmp" || die "Der Zeiger $POINTER_FILE konnte nicht geschrieben werden (Rechte auf app/?)."
    chmod 0644 -- "$tmp"
    mv -f -- "$tmp" "$POINTER_FILE"
}

make_data_dir() {      # legt DATA_DIR an (nur die letzte Komponente) und prüft, dass PHP hinein kann
    local created=0 rc=0
    if [ ! -d "$DATA_DIR" ]; then
        mkdir -m 0750 -- "$DATA_DIR" || die "Das Verzeichnis $DATA_DIR konnte nicht angelegt werden (Rechte?)."
        created=1
    fi
    if [ "$created" = 1 ] || is_root || [ -O "$DATA_DIR" ]; then
        own "$DATA_DIR"
        if [ -n "$WEB_GROUP" ] && ! is_root; then chmod 2770 -- "$DATA_DIR" 2>/dev/null || true; else web_chmod 0750 "$DATA_DIR"; fi
    fi
    web_can_write "$DATA_DIR" || rc=$?
    if ! web_can_enter "$DATA_DIR" || [ "$rc" = 1 ]; then
        if [ "$created" = 1 ]; then rmdir -- "$DATA_DIR" 2>/dev/null || true; fi
        die "PHP$(is_root && printf ' (Benutzer %s)' "$WEB_USER") kann $DATA_DIR nicht betreten oder beschreiben – vermutlich ist ein übergeordnetes Verzeichnis gesperrt (z. B. ein Home-Verzeichnis mit Rechten 700). Bitte einen anderen Ort wählen. Nichts wurde verändert."
    fi
    if [ "$created" = 1 ]; then ok "Verzeichnis angelegt: $DATA_DIR"; else ok "Verzeichnis vorhanden: $DATA_DIR"; fi
}

remove_old_entries() { # Inhalt des alten Datenverzeichnisses entfernen (außer .htaccess/.gitkeep/.tcd-moved) – als Webserver-Benutzer,
                       # damit untergeschobene Symlinks root nie auf fremde Dateien lenken können
    local old=$1
    if has_cmd shred; then
        as_web find "$old" -mindepth 1 -type f ! -name .htaccess ! -name .gitkeep ! -name .tcd-moved -exec shred -u -- {} + 2>/dev/null || true
    fi
    as_web find "$old" -mindepth 1 -maxdepth 1 ! -name .htaccess ! -name .gitkeep ! -name .tcd-moved -exec rm -rf -- {} + 2>/dev/null || true
    if [ -n "$(find "$old" -mindepth 1 -maxdepth 1 ! -name .htaccess ! -name .gitkeep ! -name .tcd-moved 2>/dev/null | head -n1)" ]; then
        warn "Nicht alles in $old konnte gelöscht werden – bitte von Hand prüfen und löschen (es sind Kopien der Daten)."
    fi
}

move_data() {
    local old=$MOVE_FROM e name n=0 mode
    make_data_dir
    if [ "$RESUME_MOVE" = 1 ]; then
        note "Ein abgebrochener Umzug wird neu begonnen: der unvollständige Inhalt von $DATA_DIR wird verworfen (das Original in $old ist unverändert)."
        as_web find "$DATA_DIR" -mindepth 1 -maxdepth 1 ! -name .tcd-move-incomplete -exec rm -rf -- {} +
    fi
    as_web sh -c 'rm -f -- "$2" && printf "%s\n" "$1" >"$2"' sh "$old" "$DATA_DIR/.tcd-move-incomplete"

    # Während des Kopierens kann die Website nur lesen: kein Request kann eine neue Datenbank/einen neuen Schlüssel anlegen.
    chmod u+w -- "$old" 2>/dev/null || true          # ein früher abgebrochener Umzug kann es schreibgeschützt hinterlassen haben
    mode=$(stat -c '%a' -- "$old" 2>/dev/null || echo 750)
    if [ -L "$old" ]; then die "Das bisherige Datenverzeichnis ist ein symbolischer Link – bitte von Hand umziehen."; fi
    MOVE_LOCKED_DIR=$old; MOVE_LOCKED_MODE=$mode
    chmod a-w -- "$old"
    say "  Die Website ist jetzt kurz nur lesbar. Kopiere …"
    for e in "$old"/* "$old"/.[!.]* "$old"/..?*; do
        if [ ! -e "$e" ] && [ ! -L "$e" ]; then continue; fi
        name=${e##*/}
        case $name in .htaccess|.gitkeep|.tcd-moved|config.local.lock) continue ;; esac
        # als Webserver-Benutzer kopieren: er besitzt Quelle und Ziel, root folgt so keinen untergeschobenen Links
        as_web cp -pR -- "$e" "$DATA_DIR/" || die "Kopieren von $e ist fehlgeschlagen. Das Original in $old ist unverändert; der Installer kann erneut gestartet werden."
        n=$((n + 1))
    done
    as_web cmp -s -- "$old/config.local.php" "$DATA_DIR/config.local.php" || die "Prüfung nach dem Kopieren fehlgeschlagen (config.local.php). Original unverändert in $old."
    for e in "$old"/*.sqlite; do
        if [ -f "$e" ]; then
            as_web cmp -s -- "$e" "$DATA_DIR/${e##*/}" || die "Prüfung nach dem Kopieren fehlgeschlagen (${e##*/}). Original unverändert in $old."
        fi
    done
    if [ -n "$WEB_GROUP" ] && ! is_root; then
        chgrp -hR "$WEB_GROUP" -- "$DATA_DIR" 2>/dev/null || true
        chmod -R g+rwX -- "$DATA_DIR" 2>/dev/null || true
    fi

    # Umschalten: ab jetzt nutzt die Website das neue Verzeichnis.
    write_pointer
    ok "$n Einträge kopiert und geprüft; die Website nutzt jetzt $DATA_DIR."

    chmod "$mode" -- "$old"; MOVE_LOCKED_DIR=""
    remove_old_entries "$old"
    as_web rm -f -- "$DATA_DIR/.tcd-move-incomplete"
    ok "Die alte Kopie in $old wurde gelöscht."
}

finish_interrupted_move() {    # Zeiger steht schon, aber das Aufräumen fehlt noch
    if [ ! -f "$DATA_DIR/.tcd-move-incomplete" ]; then return 0; fi
    local old
    old=$(head -n1 -- "$DATA_DIR/.tcd-move-incomplete" 2>/dev/null || true)
    # Die Markierung liegt in einem Verzeichnis, das PHP beschreiben kann: aufgeräumt wird deshalb nur der bekannte Ort <Webroot>/data.
    if [ -n "$old" ] && [ "$old" != "$ROOT_REAL/data" ]; then
        warn "Ein früherer Umzug wurde nicht fertig aufgeräumt. Bitte die alte Kopie von Hand prüfen und löschen: $old"
        old=""
    fi
    if [ -n "$old" ] && [ -d "$old" ] && [ ! -L "$old" ] && [ -f "$old/config.local.php" ]; then
        if as_web cmp -s -- "$old/config.local.php" "$DATA_DIR/config.local.php"; then
            chmod u+w -- "$old" 2>/dev/null || true
            remove_old_entries "$old"
            ok "Aufräumen eines früheren Umzugs abgeschlossen ($old geleert)."
        else
            warn "In $old liegt noch eine abweichende config.local.php aus einem früheren Umzug – bitte von Hand prüfen und löschen."
        fi
    fi
    as_web rm -f -- "$DATA_DIR/.tcd-move-incomplete"
}

setup_data_dir() {
    step "2/7  Datenverzeichnis"
    POINTER_FILE="$ROOT/app/data-dir.local"
    if [ -n "$MOVE_FROM" ]; then
        move_data
    else
        make_data_dir
    fi

    if [ "$DATA_INSIDE" = 1 ]; then
        if [ -f "$POINTER_FILE" ]; then rm -f -- "$POINTER_FILE"; ok "Alter Zeiger auf ein anderes Datenverzeichnis entfernt."; fi
        if [ -f "$ROOT/data/.tcd-moved" ]; then rm -f -- "$ROOT/data/.tcd-moved"; fi
    else
        if [ ! -f "$POINTER_FILE" ] || [ "$(head -n1 -- "$POINTER_FILE" 2>/dev/null || true)" != "$DATA_DIR" ]; then
            write_pointer
            ok "Zeiger geschrieben: app/data-dir.local → $DATA_DIR"
        else
            ok "Zeiger auf das Datenverzeichnis ist aktuell."
        fi
        # Fällt der Zeiger einmal weg (z. B. bei einem Update gelöscht), verweigert die Anwendung den Dienst, statt leer neu zu starten.
        if [ -d "$ROOT/data" ] && [ ! -L "$ROOT/data" ]; then
            write_marker "$ROOT/data/.tcd-moved" "$DATA_DIR"
        fi
        finish_interrupted_move
    fi
}

setup_permissions() {
    step "3/7  Rechte"
    local d rc
    mkdir -p -- "$ROOT/legal"
    # sitemap.xml wird von PHP erzeugt (nicht im Git); die Datei muss existieren, weil PHP das Webroot selbst nicht beschreiben darf.
    if [ ! -e "$ROOT/sitemap.xml" ]; then ( umask 022; : >"$ROOT/sitemap.xml" ); fi
    if is_root; then
        chown -hR "$WEB_USER:$WEB_GROUP" -- "$DATA_DIR"
        for d in "$ROOT/legal" "$ROOT"/legal-notice-*; do
            if [ -d "$d" ] && [ ! -L "$d" ]; then chown -hR "$WEB_USER:$WEB_GROUP" -- "$d"; fi
        done
        if [ -f "$ROOT/sitemap.xml" ] && [ ! -L "$ROOT/sitemap.xml" ]; then chown -h "$WEB_USER:$WEB_GROUP" -- "$ROOT/sitemap.xml"; fi
    elif [ -n "$WEB_GROUP" ]; then
        for d in "$DATA_DIR" "$ROOT/legal" "$ROOT"/legal-notice-*; do
            if [ -d "$d" ]; then
                own "$d" -R
                chmod -R g+rwX -- "$d" 2>/dev/null || warn "Gruppenrechte auf $d konnten nicht vollständig gesetzt werden."
            fi
        done
        chmod 2770 -- "$DATA_DIR" 2>/dev/null || true
        if [ -f "$ROOT/sitemap.xml" ]; then own "$ROOT/sitemap.xml"; chmod g+w -- "$ROOT/sitemap.xml" 2>/dev/null || true; fi
    fi
    if [ -f "$DATA_DIR/config.local.php" ]; then web_chmod 0640 "$DATA_DIR/config.local.php"; fi
    if [ -d "$DATA_DIR/vault-inbox" ]; then web_chmod 0700 "$DATA_DIR/vault-inbox"; fi

    rc=0; web_can_write "$DATA_DIR" || rc=$?
    case $rc in
        0) ok "PHP kann im Datenverzeichnis schreiben." ;;
        2) note "Ob PHP (Gruppe $WEB_GROUP) schreiben kann, lässt sich als normaler Benutzer nicht prüfen – nach der Einrichtung im Admin-Bereich unter Settings → System checks kontrollieren." ;;
        *) bad "PHP kann im Datenverzeichnis NICHT schreiben: $DATA_DIR" ;;
    esac
    rc=0; web_can_write "$ROOT/legal" || rc=$?
    if [ "$rc" = 1 ]; then warn "PHP kann in $ROOT/legal nicht schreiben – freigegebene Impressen könnten nicht veröffentlicht werden."
    elif [ "$rc" = 0 ]; then ok "PHP kann Impressums-Seiten in legal/ schreiben."; fi
    if [ -f "$ROOT/sitemap.xml" ]; then
        rc=0; web_can_write "$ROOT/sitemap.xml" || rc=$?
        if [ "$rc" = 1 ]; then warn "PHP kann sitemap.xml nicht aktualisieren (Rechte von $ROOT/sitemap.xml)."
        elif [ "$rc" = 0 ]; then ok "PHP kann sitemap.xml aktualisieren."; fi
    fi
}

# ------------------------------------------------------------------------------------------------------------------
#  4./5. Anwendung, Admin
# ------------------------------------------------------------------------------------------------------------------
setup_app() {
    step "4/7  Datenbank, Schlüssel und Einstellungen"
    local out
    out=$(hp init 2>&1) || { printf '%s\n' "$out" >&2; die "Die Initialisierung ist fehlgeschlagen (siehe oben)."; }
    show_results "$out"

    local cfg="site_url=$SITE_URL"$'\n'"trust_forwarded_proto=$PROXY"
    if [ "$MAIL_DOMAIN" != "$CUR_MAIL_DOMAIN" ] || [ "$CUR_INSTALLED" != yes ]; then
        cfg+=$'\n'"mail.from=no-reply@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.contact=contact@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.info=info@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.imprint=imprint@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.privacy=privacy-policy@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.admin_notify=imprint@$MAIL_DOMAIN"
        cfg+=$'\n'"mail.requests=contact@$MAIL_DOMAIN"
    fi
    out=$(printf '%s\n' "$cfg" | hp config-set 2>&1) || { printf '%s\n' "$out" >&2; die "Die Einstellungen konnten nicht geschrieben werden."; }
    ok "Einstellungen gespeichert (Adresse, Postfächer, Proxy: $PROXY)."

    # Impressums-Seiten, Weiterleitungen und sitemap.xml werden aus der Datenbank erzeugt (sie liegen bewusst nicht im Git).
    local rc=0
    out=$(as_web env "TCD_DATA_DIR=$DATA_DIR" "$PHP" "$ROOT/tools/publish-all.php" 2>&1) || rc=$?
    if [ "$rc" -eq 0 ]; then ok "Impressums-Seiten und sitemap.xml erzeugt."
    else bad "Impressums-Seiten/sitemap.xml konnten nicht erzeugt werden: $(printf '%s' "$out" | tail -n2 | tr '\n' ' ')"
    fi

    todo "Postfächer beim Mail-Anbieter anlegen: contact@, info@, imprint@, privacy-policy@ und no-reply@$MAIL_DOMAIN (SPF/DKIM für $MAIL_DOMAIN einrichten, sonst landen Mails im Spam)."
    todo "Backups: $DATA_DIR vollständig sichern – Datenbank und config.local.php (Datenschlüssel!) IMMER zusammen; ohne den Schlüssel sind die Adressdaten für immer unlesbar. Backups verschlüsseln. Den privaten Tresor-Schlüssel NICHT in dieses Backup."
}

setup_admin() {
    step "5/7  Admin-Konto"
    if [ "$CUR_ADMIN" = yes ]; then
        ok "Es gibt schon ein Admin-Konto – nichts geändert."
        return 0
    fi
    local out rc=0
    out=$(printf '%s\n' "email=$ADMIN_EMAIL" "name=$ADMIN_NAME" "password=$ADMIN_PW" | hp admin-create 2>&1) || rc=$?
    if [ "$rc" -eq 0 ]; then
        ok "Admin-Konto angelegt: $ADMIN_EMAIL – Anmeldung: $SITE_URL/admin/login.php"
        todo "Als Admin anmelden und unter Account die Zwei-Faktor-Anmeldung aktivieren (für alle, die Ausweisfotos sehen dürfen, dringend)."
        if [ "$ADMIN_PW_GENERATED" = 1 ]; then
            printf '\n  %s>>> Zufälliges Admin-Passwort (wird nur angezeigt, nicht gespeichert):  %s  <<<%s\n' "$C_BOLD$C_YELLOW" "$ADMIN_PW" "$C_RESET"
            printf '      Jetzt im Passwort-Manager ablegen.\n\n'
            SECRET_NOTES+=("Admin-Passwort für $ADMIN_EMAIL: $ADMIN_PW")
        fi
    elif [ "$rc" -eq 3 ]; then
        ok "Es gibt schon ein Admin-Konto – nichts geändert."
    else
        bad "Das Admin-Konto konnte nicht angelegt werden: $(hp_fail_text "$out") – alternativ: php tools/create-admin.php oder $SITE_URL/admin/setup.php."
    fi
    ADMIN_PW=""
}

# ------------------------------------------------------------------------------------------------------------------
#  6. Archiv und Tresor-Schlüssel
# ------------------------------------------------------------------------------------------------------------------
secure_delete() {
    if has_cmd shred && shred -u -n 3 -- "$1" 2>/dev/null; then return 0; fi
    local size
    size=$(wc -c <"$1" 2>/dev/null || echo 0)
    if [ "${size:-0}" -gt 0 ]; then head -c "$size" /dev/urandom >"$1" 2>/dev/null || true; fi
    rm -f -- "$1"
}

setup_archive() {
    step "6/7  Ausweis-Tresor"
    local out="" rc=0 cfg
    case $ARCH_MODE in
        dir)
            if [ ! -d "$ARCH_DIR" ]; then
                if ! mkdir -m 0700 -- "$ARCH_DIR"; then bad "Das Archiv-Verzeichnis konnte nicht angelegt werden: $ARCH_DIR"; return 0; fi
                ok "Archiv-Verzeichnis angelegt: $ARCH_DIR"
            else
                ok "Archiv-Verzeichnis vorhanden: $ARCH_DIR"
            fi
            own "$ARCH_DIR"
            if [ -n "$WEB_GROUP" ] && ! is_root; then chmod 2770 -- "$ARCH_DIR" 2>/dev/null || true; else web_chmod 0700 "$ARCH_DIR"; fi
            if ! web_can_enter "$ARCH_DIR"; then
                bad "PHP kann das Archiv-Verzeichnis $ARCH_DIR nicht betreten (übergeordnetes Verzeichnis gesperrt?)."
                return 0
            fi
            if [ "$(fs_id "$ARCH_DIR")" = "$(fs_id "$DATA_DIR")" ] || [ "$(fs_id "$ARCH_DIR")" = "$(fs_id "$ROOT_REAL")" ]; then
                note "Das Archiv liegt auf demselben Dateisystem wie die Website ($(describe_fs "$ARCH_DIR")) – technisch in Ordnung, aber kein getrenntes Speichersystem."
                todo "Bevor Kunden Ausweise hochladen: das Archiv auf ein getrenntes Speichersystem legen (eigenes Volume/Mount oder S3) – das verspricht die Datenschutzerklärung – oder die Erklärung anpassen."
            fi
            cfg=$'vault.driver=directory\n'"vault.dir=$ARCH_DIR"
            out=$(printf '%s\n' "$cfg" | hp config-set 2>&1) || rc=$?
            ;;
        s3)
            cfg=$'vault.driver=s3\n'"vault.s3.endpoint=$S3_ENDPOINT"$'\n'"vault.s3.bucket=$S3_BUCKET"$'\n'"vault.s3.region=$S3_REGION"$'\n'"vault.s3.prefix=$S3_PREFIX"$'\n'"vault.s3.path_style=$S3_PATH_STYLE"
            if [ "$S3_KEEP_KEYS" != 1 ]; then cfg+=$'\n'"vault.s3.access_key=$S3_ACCESS"$'\n'"vault.s3.secret_key=$S3_SECRET"; fi
            out=$(printf '%s\n' "$cfg" | hp config-set 2>&1) || rc=$?
            cfg=""; S3_ACCESS=""; S3_SECRET=""
            ;;
        keep) : ;;
        *)
            note "Das Archiv wird später eingerichtet (bis dahin landen archivierte Ausweisdateien in $DATA_DIR/vault-archive)."
            todo "Archiv einrichten (eigenes Volume oder S3): bash installer.sh --archive-dir PFAD  bzw.  --s3-endpoint … – BEVOR Kunden Ausweise hochladen."
            return 0
            ;;
    esac
    if [ "$rc" -ne 0 ]; then
        bad "Die Archiv-Einstellungen konnten nicht gespeichert werden: $(hp_fail_text "$out")"
        return 0
    fi

    say "  Teste das Archiv (schreiben, zurücklesen, löschen eines Probeobjekts) …"
    rc=0
    out=$(hp vault-probe 2>&1) || rc=$?
    show_results "$out"
    if [ "$rc" -ne 0 ]; then
        todo "Archiv reparieren und unter Admin → Settings → Identity vault → Test archive erneut testen (oder den Installer erneut starten) – Kunden-Uploads erst danach."
    fi
    todo "Aufbewahrungsdauer der Backups festlegen (und in der Datenschutzerklärung nennen). Bei S3: keine Versionierung/Object-Lock, sonst ist die Löschung nicht unwiderruflich."
}

setup_vault_key() {
    local out rc=0
    case $VAULT_MODE in
        keep) ok "Tresor-Schlüssel vorhanden (Fingerabdruck $CUR_VAULT_FP)." ;;
        later)
            note "Noch kein Tresor-Schlüssel – Kunden können erst Ausweisfotos hochladen, wenn er im Admin-Bereich gesetzt ist."
            todo "Admin → Settings → Identity vault: Schlüsselpaar im Browser erzeugen, den PRIVATEN Schlüssel offline sichern (am besten zweimal, nie auf einen Server), den öffentlichen speichern."
            ;;
        file)
            out=$(hp vault-key set <"$VAULT_PUBKEY" 2>&1) || rc=$?
            show_results "$out"
            if [ "$rc" -eq 0 ]; then
                todo "Den zum öffentlichen Schlüssel gehörenden PRIVATEN Schlüssel offline aufbewahren (mit Sicherungskopie). Geht er verloren, sind alle Ausweisfotos für immer unlesbar."
            fi
            ;;
        generate) generate_vault_key ;;
    esac
}

generate_vault_key() {
    local priv="$VAULT_KEYDIR/id-vault-private-key.pem" pub="$VAULT_KEYDIR/id-vault-public-key.pem" out fp rc=0
    if [ ! -d "$VAULT_KEYDIR" ]; then
        if ! ( umask 077; mkdir -- "$VAULT_KEYDIR" ); then bad "Das Verzeichnis für den Schlüssel konnte nicht angelegt werden: $VAULT_KEYDIR"; return 0; fi
    fi
    chmod 0700 -- "$VAULT_KEYDIR"
    say "  Erzeuge ein RSA-Schlüsselpaar mit 4096 Bit (das dauert einige Sekunden) …"
    if ! ( umask 077; openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$priv" 2>/dev/null ); then
        rm -f -- "$priv"
        bad "openssl konnte kein Schlüsselpaar erzeugen."
        return 0
    fi
    chmod 0600 -- "$priv"
    if ! openssl pkey -in "$priv" -pubout -out "$pub" 2>/dev/null; then
        secure_delete "$priv"
        bad "Der öffentliche Schlüssel konnte nicht abgeleitet werden (privater Schlüssel wieder gelöscht)."
        return 0
    fi
    chmod 0644 -- "$pub"
    out=$(hp vault-key set <"$pub" 2>&1) || rc=$?
    if [ "$rc" -ne 0 ]; then
        secure_delete "$priv"
        bad "Der öffentliche Schlüssel wurde nicht gespeichert ($(hp_fail_text "$out")); der private Schlüssel wurde wieder gelöscht."
        return 0
    fi
    show_results "$out"
    fp=$(hp_stateless vault-key fingerprint <"$pub" 2>/dev/null | awk -F'\t' '$1 == "OK" { print $3 }' || true)

    local host user
    host=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo "SERVER")
    user=$(id -un)
    say ""
    say "  ${C_BOLD}${C_YELLOW}WICHTIG – privater Schlüssel${C_RESET}"
    say "    Datei        : $priv"
    say "    Fingerabdruck: $fp"
    say "    1. JETZT auf deinen eigenen Rechner herunterladen, z. B. dort ausführen:  scp $user@$host:$(shq "$priv") ."
    say "    2. Mindestens eine Sicherungskopie an einem anderen Ort anlegen (verschlüsselter USB-Stick, Tresor)."
    say "    3. Danach vom Server löschen. Wer diese Datei hat, kann ALLE Ausweisfotos lesen; geht sie verloren, sind alle Fotos unlesbar."
    if [ "$ASSUME_YES" = 1 ] || [ ! -t 0 ]; then
        bad "Der private Tresor-Schlüssel liegt noch auf dem Server: $priv – herunterladen, sichern, dann löschen:  shred -u $(shq "$priv")"
        return 0
    fi
    local confirm=""
    ask confirm "Tippe GESICHERT, wenn du die Datei heruntergeladen und gesichert hast (sonst nur Enter)" ""
    if [ "$confirm" = "GESICHERT" ] && ask_yn "Den privaten Schlüssel jetzt unwiderruflich vom Server löschen?" j; then
        secure_delete "$priv"
        if [ -e "$priv" ]; then bad "Die Datei $priv konnte nicht gelöscht werden."; else ok "Der private Schlüssel wurde vom Server gelöscht."; fi
    else
        bad "Der private Tresor-Schlüssel liegt noch auf dem Server: $priv – herunterladen, sichern, dann löschen:  shred -u $(shq "$priv")"
    fi
}

# ------------------------------------------------------------------------------------------------------------------
#  7. Cronjob, Schutz, Tests
# ------------------------------------------------------------------------------------------------------------------
cron_escape() { printf '%s' "${1//%/\\%}"; }

setup_cron() {
    step "7/7  Wartungs-Job, Zugriffsschutz, Abschlusstest"
    local line out rc=0
    line="$OPT_CRON_SCHEDULE $(cron_escape "$(shq "$PHP")") $(cron_escape "$(shq "$ROOT/tools/maintenance.php")") >/dev/null 2>&1 $SITE_TAG"

    out=$(as_web env -u TCD_DATA_DIR "$PHP" "$ROOT/tools/maintenance.php" 2>&1) || rc=$?
    if [ "$rc" -eq 0 ]; then ok "Wartungsskript läuft: $(printf '%s' "$out" | head -n1)"
    else bad "Das Wartungsskript (tools/maintenance.php) lief nicht fehlerfrei: $(printf '%s' "$out" | head -n3 | tr '\n' ' ')"
    fi

    if [ "$CRON_WANTED" != 1 ]; then
        todo "Täglichen Wartungs-Job selbst einrichten$(is_root && printf ' (als %s)' "$WEB_USER"):  $line"
        return 0
    fi
    if ! has_cmd crontab; then
        note "Das Programm \"crontab\" fehlt – den Wartungs-Job bitte im Hosting-Panel (Cronjobs) anlegen."
        todo "Täglichen Wartungs-Job im Hosting-Panel anlegen:  $line"
        return 0
    fi
    local -a cron_cmd=(crontab)
    if is_root; then cron_cmd=(crontab -u "$WEB_USER"); fi
    local current="" new
    current=$("${cron_cmd[@]}" -l 2>/dev/null || true)
    if printf '%s\n' "$current" | grep -qxF -- "$line"; then
        ok "Der Wartungs-Job ist bereits eingetragen."
        return 0
    fi
    new=$(printf '%s\n' "$current" | grep -vF -- "$SITE_TAG" | awk -v r="$ROOT/tools/maintenance.php" '!(index($0, r) && $0 ~ /# tcd-studio-maintenance$/)' || true)
    if [ -n "$new" ]; then new=$(printf '%s\n%s' "$new" "$line"); else new=$line; fi
    if printf '%s\n' "$new" | "${cron_cmd[@]}" - 2>/dev/null; then
        ok "Wartungs-Job eingetragen ($OPT_CRON_SCHEDULE)$(is_root && printf ' für den Benutzer %s' "$WEB_USER")."
    else
        warn "Der Wartungs-Job konnte nicht in die crontab geschrieben werden."
        todo "Täglichen Wartungs-Job selbst einrichten:  $line"
    fi
}


# ------------------------------------------------------------------------------------------------------------------
#  Server-Pakete und Webserver (nur als root auf Debian/Ubuntu)
# ------------------------------------------------------------------------------------------------------------------
NGINX_PRESENT=0
APACHE_CHANGED=0

install_packages() {
    if [ "$MODE_CHECK" = 1 ] || [ "$OPT_NO_PACKAGES" = 1 ] || ! is_root || ! has_cmd apt-get || ! has_cmd dpkg; then return 0; fi
    local -a want=() missing=()
    local p
    if has_cmd nginx && ! has_cmd apache2; then
        NGINX_PRESENT=1
        want=(php-fpm php-cli php-sqlite3 php-gd php-mbstring php-curl php-xml)
    else
        want=(apache2 libapache2-mod-php php-cli php-sqlite3 php-gd php-mbstring php-curl php-xml)
        if [ "$OPT_NO_HTTPS" != 1 ]; then want+=(certbot python3-certbot-apache); fi
    fi
    for p in "${want[@]}"; do
        if ! dpkg -s "$p" >/dev/null 2>&1; then missing+=("$p"); fi
    done
    if [ ${#missing[@]} -eq 0 ]; then return 0; fi
    say "  Fehlende Pakete: ${missing[*]}"
    if ! ask_yn "Jetzt per apt installieren?" j; then
        note "Pakete nicht installiert (bash installer.sh --no-packages)."
        return 0
    fi
    say "  Installiere (das dauert ein bis zwei Minuten) …"
    if ! DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null 2>&1; then die "apt-get update ist fehlgeschlagen (Internet/Paketquellen prüfen)."; fi
    if ! DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "${missing[@]}" >/dev/null 2>&1; then
        die "Die Installation der Pakete ist fehlgeschlagen. Von Hand prüfen: apt-get install ${missing[*]}"
    fi
    ok "Pakete installiert: ${missing[*]}"
    hash -r
    detect_webserver
}

SITE_HOST=""
setup_webserver() {
    SITE_HOST=${SITE_URL#*://}; SITE_HOST=${SITE_HOST%%[:/]*}
    if [ "$OPT_NO_WEBSERVER" = 1 ] || ! is_root || [ ! -d /etc/apache2/sites-available ] || ! has_cmd a2ensite; then
        if [ "$NGINX_PRESENT" = 1 ] || [[ $WEBSERVER == nginx* ]]; then
            todo "nginx: einen server{}-Block für $SITE_HOST mit root $ROOT, PHP über php-fpm und den Sperr-Regeln oben anlegen."
        fi
        return 0
    fi
    if [[ ! $SITE_HOST =~ ^[A-Za-z0-9.-]+$ ]] || [[ $ROOT == *[[:space:]\"\\]* ]]; then
        todo "Apache-Seite für $SITE_HOST von Hand anlegen (DocumentRoot $ROOT, AllowOverride All)."
        return 0
    fi
    local conf="/etc/apache2/sites-available/$SITE_HOST.conf" marker="# angelegt von installer.sh (tcd-studio)" www="" tmp
    if [[ $SITE_HOST != www.* ]]; then www="ServerAlias www.$SITE_HOST"; fi
    if [ -f "$conf" ] && ! grep -qF -- "$marker" "$conf"; then
        if grep -q "DocumentRoot[[:space:]]*\"\?$ROOT\"\?[[:space:]]*$" "$conf" && grep -qi "AllowOverride[[:space:]]*All" "$conf"; then
            ok "Apache-Seite $conf ist vorhanden (eigene Konfiguration, bleibt unverändert)."
        else
            warn "Es gibt bereits $conf (nicht vom Installer). Bitte prüfen: DocumentRoot $ROOT und im <Directory>-Block AllowOverride All – sonst gelten die .htaccess-Schutzregeln nicht."
        fi
    else
        tmp=$(mktemp)
        TMP_FILES+=("$tmp")
        cat >"$tmp" <<VHOST
$marker
<VirtualHost *:80>
    ServerName $SITE_HOST
    $www
    DocumentRoot $ROOT
    <Directory $ROOT>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <IfModule php_module>
        php_admin_value upload_max_filesize 9M
        php_admin_value post_max_size 20M
    </IfModule>
    <IfModule php7_module>
        php_admin_value upload_max_filesize 9M
        php_admin_value post_max_size 20M
    </IfModule>
    ErrorLog \${APACHE_LOG_DIR}/$SITE_HOST-error.log
    CustomLog \${APACHE_LOG_DIR}/$SITE_HOST-access.log combined
</VirtualHost>
VHOST
        if [ -f "$conf" ] && cmp -s "$tmp" "$conf"; then
            ok "Apache-Seite $SITE_HOST ist aktuell."
        else
            install -m 0644 "$tmp" "$conf"
            APACHE_CHANGED=1
            ok "Apache-Seite angelegt: $conf (DocumentRoot $ROOT, AllowOverride All, Upload-Grenzen)."
        fi
    fi
    local m
    for m in rewrite alias headers; do
        if [ ! -e "/etc/apache2/mods-enabled/$m.load" ]; then a2enmod -q "$m" >/dev/null 2>&1 && APACHE_CHANGED=1; fi
    done
    if [ ! -e "/etc/apache2/sites-enabled/$SITE_HOST.conf" ]; then a2ensite -q "$SITE_HOST" >/dev/null 2>&1 && APACHE_CHANGED=1; fi
    if [ "$APACHE_CHANGED" = 1 ]; then
        if apache2ctl configtest >/dev/null 2>&1; then
            if systemctl restart apache2 >/dev/null 2>&1 || service apache2 restart >/dev/null 2>&1; then ok "Apache neu gestartet."
            else bad "Apache konnte nicht neu gestartet werden: systemctl status apache2"; fi
        else
            bad "Die Apache-Konfiguration ist fehlerhaft (apache2ctl configtest) – Apache wurde NICHT neu gestartet."
        fi
    else
        ok "Apache ist bereits eingerichtet."
    fi
    setup_https
}

setup_https() {
    case $SITE_URL in https://*) ;; *) return 0 ;; esac
    if [ "$OPT_NO_HTTPS" = 1 ] || ! has_cmd certbot; then
        todo "HTTPS-Zertifikat einrichten:  certbot --apache -d $SITE_HOST"
        return 0
    fi
    if [ -d "/etc/letsencrypt/live/$SITE_HOST" ]; then ok "HTTPS-Zertifikat für $SITE_HOST ist vorhanden."; return 0; fi
    local -a doms=(-d "$SITE_HOST") mail=(--register-unsafely-without-email)
    if [[ $SITE_HOST != www.* ]] && getent hosts "www.$SITE_HOST" >/dev/null 2>&1; then doms+=(-d "www.$SITE_HOST"); fi
    if [ -n "${ADMIN_EMAIL:-}" ]; then mail=(-m "$ADMIN_EMAIL"); fi
    if ! getent hosts "$SITE_HOST" >/dev/null 2>&1; then
        warn "$SITE_HOST ist per DNS (noch) nicht auflösbar – kein HTTPS-Zertifikat. Wenn die Domain auf diesen Server zeigt: bash installer.sh erneut starten."
        todo "DNS: A-Eintrag von $SITE_HOST (und www) auf die IP dieses Servers setzen, dann bash installer.sh erneut starten (holt das Zertifikat)."
        return 0
    fi
    say "  Hole ein HTTPS-Zertifikat von Let's Encrypt für $SITE_HOST …"
    if certbot --apache --non-interactive --agree-tos "${mail[@]}" --redirect "${doms[@]}" >/dev/null 2>&1; then
        ok "HTTPS ist eingerichtet (Zertifikat wird automatisch erneuert)."
    else
        warn "Das HTTPS-Zertifikat konnte nicht geholt werden (zeigt die Domain wirklich auf diesen Server? Port 80 offen?). Details: certbot --apache -d $SITE_HOST"
        todo "HTTPS einrichten: certbot --apache -d $SITE_HOST  (vorher DNS und Firewall Port 80/443 prüfen)."
    fi
}

HTACCESS_BEGIN="# BEGIN tcd-installer v2"
HTACCESS_OLD_BEGIN="# BEGIN tcd-installer"
HTACCESS_END="# END tcd-installer"

setup_guard() {
    local f="$ROOT/.htaccess" block
    block="$HTACCESS_BEGIN
<FilesMatch \"^(installer\\.sh|data-dir\\.local)\$\">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>
<IfModule mod_alias.c>
    RedirectMatch 404 /\.git(/|\$)
</IfModule>
$HTACCESS_END"
    if [ -f "$f" ] && [ ! -L "$f" ] && ! grep -qxF -- "$HTACCESS_BEGIN" "$f" && grep -qxF -- "$HTACCESS_OLD_BEGIN" "$f"; then
        # ältere Fassung des Blocks entfernen, die neue wird unten angehängt
        local tmpf="$f.tcd-tmp$$"
        awk -v b="$HTACCESS_OLD_BEGIN" -v e="$HTACCESS_END" '$0 == b { skip = 1; next } skip && $0 == e { skip = 0; next } !skip' "$f" >"$tmpf" && chmod 0644 -- "$tmpf" && mv -f -- "$tmpf" "$f"
    fi
    if [ -L "$f" ]; then
        warn "$f ist ein symbolischer Link – wird nicht verändert."
    elif [ -f "$f" ] && grep -qF -- "$HTACCESS_BEGIN" "$f"; then
        ok "Schutzregeln (installer.sh, .git) in .htaccess vorhanden."
    elif [ -f "$f" ]; then
        if printf '\n%s\n' "$block" >>"$f"; then ok "Schutzregeln (installer.sh, .git) an die bestehende .htaccess angehängt (der bisherige Inhalt bleibt)."; else warn "Die .htaccess konnte nicht ergänzt werden."; fi
    elif printf '%s\n' "$block" >"$f" && chmod 0644 -- "$f"; then
        ok "Schutzregeln (installer.sh, .git) in neuer .htaccess angelegt."
    else
        warn "Die .htaccess konnte nicht angelegt werden."
    fi
    if [[ $WEBSERVER == nginx* || $WEBSERVER == Caddy* || $WEBSERVER == LiteSpeed* ]]; then
        warn "Webserver: $WEBSERVER. Die .htaccess-Dateien gelten hier NICHT (bei LiteSpeed nur teilweise). app/, data/, tools/, backend/, docs/ und installer.sh MÜSSEN im Webserver gesperrt werden:"
        print_nginx_rules
        todo "Zugriffsregeln für $WEBSERVER einbauen (siehe oben) – ohne sie sind app/ und ggf. data/ abrufbar."
    fi
}

print_nginx_rules() {
    say "      # nginx: in den server{}-Block der Website (^~ hat Vorrang vor regulären Ausdrücken wie location ~ \\.php\$)"
    say "      location ^~ /app/     { deny all; return 404; }"
    say "      location ^~ /data/    { deny all; return 404; }"
    say "      location ^~ /tools/   { deny all; return 404; }"
    say "      location ^~ /backend/ { deny all; return 404; }"
    say "      location ^~ /docs/    { deny all; return 404; }"
    say "      location = /installer.sh { deny all; return 404; }"
    say "      location ^~ /.git     { deny all; return 404; }"
    say "      client_max_body_size 20m;   # Ausweisfotos; dazu in PHP: upload_max_filesize = 9M, post_max_size = 20M"
}

http_code() {      # http_code URL [follow]  – HTTP-Status (mit "follow": nach Weiterleitungen)
    local code
    local -a extra=()
    if [ "${2:-}" = follow ]; then extra=(-L --max-redirs 5); fi
    code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 12 --connect-timeout 6 ${extra[@]+"${extra[@]}"} "$1" 2>/dev/null) || code=000
    printf '%s' "${code:-000}"
}

http_checks() {
    if [ "$OPT_SKIP_HTTP" = 1 ] || ! has_cmd curl; then
        say "  Abschluss-Test über HTTP übersprungen."
        todo "Von außen prüfen: $SITE_URL/ , /portal/ und /admin/login.php laden – und dass $SITE_URL/app/config.php, /tools/ und /installer.sh NICHT abrufbar sind (oder: bash installer.sh --check)."
        return 0
    fi
    say "  Teste $SITE_URL von diesem Server aus …"
    local code path
    code=$(http_code "$SITE_URL/")
    if [ "$code" = 000 ]; then
        warn "$SITE_URL ist von diesem Server aus (noch) nicht erreichbar (DNS/HTTPS/Zertifikat?). Der Test entfällt – nach der Domain-Einrichtung wiederholen: bash installer.sh --check"
        todo "Wenn Domain und HTTPS stehen: bash installer.sh --check  (prüft Erreichbarkeit und Zugriffsschutz)."
        return 0
    fi
    case $code in 2*|3*) ok "Startseite antwortet (HTTP $code)." ;; *) warn "Die Startseite antwortet mit HTTP $code." ;; esac
    for path in /portal/login.php /admin/login.php; do
        code=$(http_code "$SITE_URL$path" follow)
        case $code in
            200) ok "$path antwortet (HTTP 200) – PHP und Datenbank arbeiten im Webserver." ;;
            *) bad "$path antwortet mit HTTP $code – PHP läuft im Webserver nicht fehlerfrei (Server-Log prüfen; Rechte auf das Datenverzeichnis? open_basedir? PHP-Version?)." ;;
        esac
    done

    # Zugriffsschutz: Testdatei in die zu schützenden Verzeichnisse legen, abrufen (Weiterleitungen folgen), sofort löschen.
    local token rel probe exposed=0
    token="probe-$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
    local -a rels=(app tools backend docs)
    if [ "$DATA_INSIDE" = 1 ]; then rels+=(data); fi
    for rel in "${rels[@]}"; do
        probe="$ROOT/$rel/$token.txt"
        if [ ! -d "$ROOT/$rel" ]; then continue; fi
        TMP_FILES+=("$probe")
        if ! ( umask 022; set -C; printf 'installer-probe\n' >"$probe" ) 2>/dev/null; then continue; fi
        code=$(http_code "$SITE_URL/$rel/$token.txt" follow)
        rm -f -- "$probe"
        if [ "$code" = 200 ]; then
            exposed=1
            bad "GEFAHR: /$rel/ ist über das Internet abrufbar!"
        fi
    done
    if [ -f "$ROOT/.git/HEAD" ]; then
        code=$(http_code "$SITE_URL/.git/HEAD" follow)
        if [ "$code" = 200 ]; then exposed=1; bad "GEFAHR: /.git/ ist über das Internet abrufbar (Quellcode und Verlauf)!"; fi
    fi
    if [ -f "$ROOT/installer.sh" ]; then
        code=$(http_code "$SITE_URL/installer.sh" follow)
        if [ "$code" = 200 ]; then exposed=1; bad "/installer.sh ist über das Internet abrufbar – löschen oder sperren."; fi
    fi
    if [ "$exposed" = 0 ]; then
        ok "app/, tools/, backend/, docs/$([ "$DATA_INSIDE" = 1 ] && printf ', data/')$([ -d "$ROOT/.git" ] && printf ', .git/') und installer.sh sind von außen nicht abrufbar."
    else
        say "  So wird gesperrt:"
        say "    Apache: Die mitgelieferten .htaccess-Dateien greifen nur mit \"AllowOverride All\" (oder mindestens AuthConfig/Limit)"
        say "            im <Directory>-Block der Website. Alternativ dieselben Regeln direkt in die Server-Konfiguration."
        say "    nginx :"
        print_nginx_rules
    fi
    case $SITE_URL in http://*) warn "Die Website wird ohne HTTPS ausgeliefert – Ausweis-Uploads werden abgelehnt, bis HTTPS aktiv ist." ;; esac
}

final_report() {
    local out rc=0
    out=$(hp report 2>&1) || rc=$?
    say "  Prüfungen der Anwendung (wie im Admin-Bereich unter Settings → System checks, dort auf Englisch):"
    show_results "$out"
}

maybe_test_mail() {
    if [ -z "$TEST_MAIL_TO" ]; then return 0; fi
    local out
    out=$(hp mail-test "$TEST_MAIL_TO" 2>&1) || true
    show_results "$out"
}

remove_installer() {
    local yes=0
    if [ "$OPT_REMOVE_INSTALLER" = 1 ]; then
        yes=1
    elif [ "$ASSUME_YES" != 1 ] && [ -t 0 ]; then
        if ask_yn "installer.sh jetzt vom Server löschen? (er liegt weiterhin im Repository)" n; then yes=1; fi
    fi
    if [ "$yes" = 1 ]; then
        if rm -f -- "$ROOT/installer.sh"; then ok "installer.sh wurde gelöscht."; else warn "installer.sh konnte nicht gelöscht werden."; fi
    fi
}

# ------------------------------------------------------------------------------------------------------------------
#  Abschluss
# ------------------------------------------------------------------------------------------------------------------
summary() {
    local headline=$1
    say ""
    say "${C_BOLD}==================================================================${C_RESET}"
    if [ ${#PROBLEMS[@]} -eq 0 ]; then
        say "${C_BOLD}${C_GREEN} $headline${C_RESET}"
    else
        say "${C_BOLD}${C_YELLOW} $headline – aber ${#PROBLEMS[@]} Problem(e) brauchen deine Aufmerksamkeit:${C_RESET}"
        local p
        for p in "${PROBLEMS[@]}"; do say "   ✘ $p"; done
    fi
    say "${C_BOLD}==================================================================${C_RESET}"
    if [ -n "$SITE_URL" ]; then
        say "  Admin-Bereich : $SITE_URL/admin/login.php"
        say "  Kundenbereich : $SITE_URL/portal/"
    fi
    if [ -n "$DATA_DIR" ]; then say "  Datenordner   : $DATA_DIR"; fi
    if [ -n "$LOG_FILE" ]; then say "  Protokoll     : $LOG_FILE (ohne Passwörter)"; fi
    if [ ${#SECRET_NOTES[@]} -gt 0 ]; then
        say ""
        say "${C_BOLD}${C_YELLOW}  Einmalige Angaben (werden nirgends gespeichert):${C_RESET}"
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

run_check_mode() {
    step "2/2  Vorhandene Installation"
    SITE_URL=${OPT_SITE_URL:-}
    if [ "$REQ_FAILED" = 1 ]; then
        bad "Wegen fehlender Voraussetzungen wird die Installation nicht weiter geprüft."
        summary "Prüfung abgeschlossen"
        exit 3
    fi
    read_current_state
    SITE_URL=${SITE_URL:-$CUR_SITE_URL}
    SITE_URL=${SITE_URL%/}
    case $CUR_PROBLEM in
        pointer_target_missing) bad "app/data-dir.local verweist auf $CUR_DATA_DIR, aber das Verzeichnis fehlt (Volume nicht eingehängt?). Die Website verweigert so lange den Dienst." ;;
        pointer_invalid) bad "app/data-dir.local ist unlesbar oder ungültig – die Website verweigert den Dienst." ;;
        moved_marker_without_pointer) bad "data/.tcd-moved ist vorhanden, aber app/data-dir.local fehlt – die Website verweigert den Dienst. Zeiger wiederherstellen." ;;
    esac
    if [ "$CUR_INSTALLED" = yes ] && [ -z "$CUR_PROBLEM" ]; then
        DATA_DIR=$CUR_DATA_DIR
        say "  Installiert. Datenverzeichnis: $CUR_DATA_DIR · Admin: $CUR_ADMIN · Tresor-Schlüssel: $CUR_VAULT_KEY · Archiv: $CUR_VAULT_DRIVER"
        DATA_INSIDE=0
        if [ "$(physical_path "$CUR_DATA_DIR")" = "$ROOT_REAL/data" ]; then DATA_INSIDE=1; fi
        final_report
        if [ -n "$SITE_URL" ]; then http_checks; fi
        summary "Prüfung abgeschlossen"
    elif [ -z "$CUR_PROBLEM" ]; then
        note "Hier ist noch nichts installiert. Zum Einrichten: bash installer.sh"
        summary "Prüfung abgeschlossen – noch nicht installiert"
    else
        summary "Prüfung abgeschlossen"
    fi
    if [ ${#PROBLEMS[@]} -gt 0 ]; then exit 3; fi
    exit 0
}

# ------------------------------------------------------------------------------------------------------------------
#  Ablauf
# ------------------------------------------------------------------------------------------------------------------
ENV_ADMIN_PW=""; ENV_S3_AK=""; ENV_S3_SK=""

fix_group_perms() {    # nur als normaler Benutzer mit --web-group: alles Nötige für die Gruppe schreibbar machen
    if is_root || [ -z "$WEB_GROUP" ]; then return 0; fi
    local d
    for d in "$DATA_DIR" "$ROOT/legal" "$ROOT"/legal-notice-* ${ARCH_DIR:+"$ARCH_DIR"}; do
        if [ -d "$d" ] && [ ! -L "$d" ]; then
            chgrp -hR "$WEB_GROUP" -- "$d" 2>/dev/null || true
            chmod -R g+rwX -- "$d" 2>/dev/null || true
            find "$d" -type d -exec chmod g+s -- {} + 2>/dev/null || true
        fi
    done
    if [ -f "$ROOT/sitemap.xml" ]; then chgrp "$WEB_GROUP" -- "$ROOT/sitemap.xml" 2>/dev/null || true; chmod g+rw -- "$ROOT/sitemap.xml" 2>/dev/null || true; fi
}

main() {
    # Geheimnisse aus der Umgebung sofort übernehmen und entfernen: kein Kindprozess (auch nicht PHP als www-data) erbt sie.
    ENV_ADMIN_PW=${TCD_ADMIN_PASSWORD-}; ENV_S3_AK=${TCD_S3_ACCESS_KEY-}; ENV_S3_SK=${TCD_S3_SECRET_KEY-}
    unset TCD_ADMIN_PASSWORD TCD_S3_ACCESS_KEY TCD_S3_SECRET_KEY
    parse_args "$@"
    say "${C_BOLD}TCD-Studio – Installer $INSTALLER_VERSION${C_RESET}"
    detect_root
    log_line "---- Start: installer.sh (als $(id -un))"
    if [ -n "${TCD_DATA_DIR:-}" ]; then
        # Die Anwendung bevorzugt diese Variable – also genau dieses Verzeichnis verwenden, statt eine zweite Installation anzulegen.
        if [ -z "$OPT_DATA_DIR" ]; then OPT_DATA_DIR=$TCD_DATA_DIR; fi
        note "Die Umgebungsvariable TCD_DATA_DIR ist gesetzt – es wird $OPT_DATA_DIR verwendet (und per app/data-dir.local fest eingetragen)."
        unset TCD_DATA_DIR
    fi
    detect_webserver
    detect_web_user
    if ! is_root && [ -n "$WEB_GROUP" ]; then umask 007; fi
    check_requirements

    if [ "$MODE_CHECK" = 1 ]; then run_check_mode; fi

    read_current_state
    explain_state_problem
    say ""
    if [ "$CUR_INSTALLED" = yes ]; then
        say "  Eine Installation wurde gefunden ($CUR_DATA_DIR$([ "$CUR_SOURCE" = env ] && printf ", über TCD_DATA_DIR")). Der Installer ergänzt nur, was fehlt, und ändert nichts Bestehendes."
        say "  Admin-Konto: $CUR_ADMIN · Tresor-Schlüssel: $CUR_VAULT_KEY · Archiv: $CUR_VAULT_DRIVER · Ausweisdateien im Archiv: $CUR_ARCHIVE_FILES"
    else
        say "  Noch keine Installation – es wird neu eingerichtet."
    fi

    gather_data_dir
    gather_site
    gather_admin
    gather_archive
    gather_vault
    gather_misc
    show_plan

    setup_data_dir
    setup_permissions
    setup_app
    setup_admin
    setup_archive
    setup_vault_key
    setup_cron
    setup_guard
    setup_webserver
    fix_group_perms
    maybe_test_mail
    remove_installer
    http_checks
    final_report
    summary "Fertig. Die Installation ist abgeschlossen."
    if [ ${#PROBLEMS[@]} -gt 0 ]; then exit 3; fi
    exit 0
}

main "$@"
