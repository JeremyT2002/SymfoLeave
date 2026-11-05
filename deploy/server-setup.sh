#!/usr/bin/env bash
# SymfoLeave – einmalige, idempotente Einrichtung auf einem Debian/Ubuntu-VPS.
#
#   curl -fsSL https://raw.githubusercontent.com/JeremyT2002/SymfoLeave/main/deploy/server-setup.sh | sudo bash
#
# Optional: BRANCH=<branch> DEPLOY_USER=<user> DOMAIN=<domain> vor "bash" setzen (sudo -E bzw. env …).
# Fasst ausschließlich /var/www/<DOMAIN> und eine eigene, neue vHost-Datei an.
set -euo pipefail

DOMAIN="${DOMAIN:-symfoleave.jeremythelen.de}"
PARENT_DOMAIN="${DOMAIN#*.}"
APP_DIR="/var/www/${DOMAIN}"
REPO_URL="${REPO_URL:-https://github.com/JeremyT2002/SymfoLeave.git}"
BRANCH="${BRANCH:-main}"
DEPLOY_USER="${DEPLOY_USER:-${SUDO_USER:-}}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/root/symfoleave-backups/${STAMP}"

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Bitte als root ausführen (sudo bash server-setup.sh)."
[[ -n "$DEPLOY_USER" && "$DEPLOY_USER" != "root" ]] || die "DEPLOY_USER unbekannt. Per sudo aus dem eigenen Konto starten oder DEPLOY_USER=<user> setzen."
id "$DEPLOY_USER" >/dev/null 2>&1 || die "Benutzer $DEPLOY_USER existiert nicht."
command -v apt-get >/dev/null || die "Nur Debian/Ubuntu (apt) wird unterstützt."
mkdir -p "$BACKUP_DIR"
chmod 700 /root/symfoleave-backups

as_user() { sudo -u "$DEPLOY_USER" -H "$@"; }

# ---------------------------------------------------------------- Erkennung
if systemctl is-active --quiet nginx 2>/dev/null; then
    WEBSERVER=nginx
elif systemctl is-active --quiet apache2 2>/dev/null; then
    WEBSERVER=apache
else
    die "Weder nginx noch apache2 läuft."
fi

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
if [[ -z "$PHP_VERSION" ]]; then
    PHP_VERSION="$(systemctl list-units --type=service --state=running --no-legend 'php*-fpm*' | grep -oE 'php[0-9]+\.[0-9]+' | head -1 | sed 's/php//')"
fi
[[ -n "$PHP_VERSION" ]] || die "Keine PHP-Version gefunden."
FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
php_ok="$(php -r 'echo PHP_VERSION_ID >= 80200 ? 1 : 0;' 2>/dev/null || echo 0)"
[[ "$php_ok" == 1 ]] || die "PHP ${PHP_VERSION} ist zu alt – SymfoLeave benötigt PHP ≥ 8.2."

WEB_USER="www-data"
if [[ -f "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf" ]]; then
    WEB_USER="$(awk -F= '/^\s*user\s*=/{gsub(/ /,"",$2); print $2; exit}' "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf")"
    WEB_USER="${WEB_USER:-www-data}"
fi
WEB_GROUP="$(id -gn "$WEB_USER")"
log "Webserver: $WEBSERVER · PHP-FPM ${PHP_VERSION} (${FPM_SOCKET}) · Web-User ${WEB_USER} · Deploy-User ${DEPLOY_USER}"

# ------------------------------------------ Statuscodes der bestehenden Seiten
list_hosts() {
    {
        echo "$PARENT_DOMAIN"
        if [[ $WEBSERVER == nginx ]]; then
            grep -rhoE '^\s*server_name\s+[^;]+' /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null | sed -E 's/^\s*server_name\s+//' | tr ' ' '\n'
        else
            grep -rhoiE '^\s*Server(Name|Alias)\s+.+' /etc/apache2/sites-enabled/ 2>/dev/null | awk '{for(i=2;i<=NF;i++) print $i}'
        fi
    } | grep -E "(^|\.)${PARENT_DOMAIN//./\\.}$" | grep -v '\*' | grep -vx "$DOMAIN" | sort -u
}
status_snapshot() {
    local h
    for h in $(list_hosts); do
        printf '%s %s\n' "$h" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://${h}/" || echo 000)"
    done
}
status_snapshot > "$BACKUP_DIR/status-before.txt"
log "Statuscodes vorher:"; sed 's/^/    /' "$BACKUP_DIR/status-before.txt"

# ------------------------------------------------------- Pakete (nur hinzufügen)
log "Fehlende Pakete ergänzen (keine Upgrades, nichts entfernen) …"
need=()
for ext in sqlite3 intl mbstring xml curl zip; do
    dpkg -s "php${PHP_VERSION}-${ext}" >/dev/null 2>&1 || need+=("php${PHP_VERSION}-${ext}")
done
for bin in git unzip acl; do
    dpkg -s "$bin" >/dev/null 2>&1 || need+=("$bin")
done
if ((${#need[@]})); then
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-upgrade --no-install-recommends "${need[@]}"
    systemctl reload "php${PHP_VERSION}-fpm" || systemctl restart "php${PHP_VERSION}-fpm"
fi
if ! command -v composer >/dev/null; then
    log "Composer installieren …"
    tmp="$(mktemp -d)"
    php -r "copy('https://getcomposer.org/installer', '${tmp}/composer-setup.php');"
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    actual="$(php -r "echo hash_file('sha384', '${tmp}/composer-setup.php');")"
    [[ "$expected" == "$actual" ]] || die "Composer-Installer: Prüfsumme stimmt nicht."
    php "${tmp}/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp"
fi

# ------------------------------------------------------------- Code holen
if [[ -d "$APP_DIR/.git" ]]; then
    log "Repository vorhanden – aktualisieren …"
    as_user git -C "$APP_DIR" fetch --quiet origin "$BRANCH"
    as_user git -C "$APP_DIR" checkout --quiet "$BRANCH"
    as_user git -C "$APP_DIR" pull --quiet --ff-only origin "$BRANCH"
else
    if [[ -d "$APP_DIR" ]] && [[ -n "$(ls -A "$APP_DIR")" ]]; then
        warn "$APP_DIR ist nicht leer → verschiebe nach ${APP_DIR}.bak-${STAMP}"
        mv "$APP_DIR" "${APP_DIR}.bak-${STAMP}"
    fi
    install -d -o "$DEPLOY_USER" -g "$WEB_GROUP" "$APP_DIR"
    log "Klonen ($BRANCH) …"
    as_user git clone --quiet --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
chown -R "$DEPLOY_USER:$WEB_GROUP" "$APP_DIR"

# ---------------------------------------------------------------- .env.local
if [[ ! -f "$APP_DIR/.env.local" ]]; then
    log ".env.local erzeugen …"
    umask 027
    cat > "$APP_DIR/.env.local" <<ENV
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(openssl rand -hex 32)
APP_DEMO=1
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_prod.db"
DEFAULT_URI=https://${DOMAIN}
ENV
    umask 022
    chown "$DEPLOY_USER:$WEB_GROUP" "$APP_DIR/.env.local"
    chmod 640 "$APP_DIR/.env.local"
fi

# --------------------------------------------------------------- Build
cd "$APP_DIR"
install -d -o "$DEPLOY_USER" -g "$WEB_GROUP" -m 2775 var
setfacl -R -m "u:${WEB_USER}:rwX" -m "u:${DEPLOY_USER}:rwX" var
setfacl -dR -m "u:${WEB_USER}:rwX" -m "u:${DEPLOY_USER}:rwX" var

log "Composer, Assets, Migrationen …"
as_user env APP_ENV=prod composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
as_user php bin/console tailwind:build --minify --env=prod --no-debug
as_user php bin/console asset-map:compile --env=prod --no-debug >/dev/null
as_user php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod --no-debug
if [[ ! -s var/data_prod.db ]] || ! as_user php bin/console dbal:run-sql "SELECT COUNT(*) FROM user" --env=prod --no-debug >/dev/null 2>&1; then
    as_user php bin/console app:demo:reset --env=prod --no-debug
fi
as_user php bin/console cache:clear --env=prod --no-debug
as_user php bin/console cache:warmup --env=prod --no-debug
setfacl -R -m "u:${WEB_USER}:rwX" var

# ---------------------------------------------------------- SSL-Zertifikat
CERT_DIR=""
for d in /etc/letsencrypt/live/*/; do
    [[ -f "${d}fullchain.pem" ]] || continue
    if openssl x509 -in "${d}fullchain.pem" -noout -ext subjectAltName 2>/dev/null \
        | grep -qE "DNS:(\*\.${PARENT_DOMAIN//./\\.}|${DOMAIN//./\\.})(,|$)"; then
        CERT_DIR="${d%/}"; break
    fi
done
[[ -n "$CERT_DIR" ]] && log "Vorhandenes Zertifikat gefunden: $CERT_DIR"

# ---------------------------------------------------------------- vHost
TEMPLATE="$APP_DIR/deploy/${WEBSERVER}.conf"
if [[ $WEBSERVER == nginx ]]; then
    VHOST="/etc/nginx/sites-available/${DOMAIN}.conf"
    ENABLED="/etc/nginx/sites-enabled/${DOMAIN}.conf"
    TEST_CMD=(nginx -t)
    RELOAD_CMD=(systemctl reload nginx)
else
    VHOST="/etc/apache2/sites-available/${DOMAIN}.conf"
    ENABLED="/etc/apache2/sites-enabled/${DOMAIN}.conf"
    TEST_CMD=(apachectl configtest)
    RELOAD_CMD=(systemctl reload apache2)
fi

render_vhost() {
    local out
    out="$(sed "s#__PHP_FPM_SOCKET__#${FPM_SOCKET}#g" "$TEMPLATE")"
    if [[ -n "$CERT_DIR" && $WEBSERVER == nginx ]]; then
        out="$(sed -e 's#^\s*listen 80;#    listen 443 ssl;\n    http2 on;\n    ssl_certificate '"$CERT_DIR"'/fullchain.pem;\n    ssl_certificate_key '"$CERT_DIR"'/privkey.pem;#' \
                   -e 's#^\s*listen \[::\]:80;#    listen [::]:443 ssl;#' <<<"$out")"
        out+=$'\n\nserver {\n    listen 80;\n    listen [::]:80;\n    server_name '"$DOMAIN"$';\n    return 301 https://$host$request_uri;\n}\n'
    elif [[ -n "$CERT_DIR" ]]; then
        local body
        body="$(sed -n '/<VirtualHost/,/<\/VirtualHost>/p' <<<"$out" | sed '1d;$d')"
        out="<VirtualHost *:80>
    ServerName ${DOMAIN}
    Redirect permanent / https://${DOMAIN}/
</VirtualHost>

<IfModule mod_ssl.c>
<VirtualHost *:443>
${body}
    SSLEngine on
    SSLCertificateFile ${CERT_DIR}/fullchain.pem
    SSLCertificateKeyFile ${CERT_DIR}/privkey.pem
</VirtualHost>
</IfModule>"
    fi
    printf '%s\n' "$out"
}

NEW_VHOST="$(render_vhost)"
if [[ -f "$VHOST" ]] && grep -q 'managed-by: certbot\|# managed by Certbot' "$VHOST"; then
    log "vHost wurde von certbot ergänzt – bleibt unverändert."
elif [[ -f "$VHOST" ]] && [[ "$(cat "$VHOST")" == "$NEW_VHOST" ]]; then
    log "vHost unverändert."
else
    log "vHost schreiben: $VHOST"
    had_vhost=0; had_link=0
    [[ -f "$VHOST" ]] && { cp -a "$VHOST" "$BACKUP_DIR/"; had_vhost=1; }
    [[ -e "$ENABLED" ]] && had_link=1
    [[ $WEBSERVER == apache ]] && a2enmod -q rewrite proxy_fcgi setenvif headers >/dev/null
    [[ $WEBSERVER == apache && -n "$CERT_DIR" ]] && a2enmod -q ssl >/dev/null
    printf '%s\n' "$NEW_VHOST" > "$VHOST"
    if [[ $WEBSERVER == nginx ]]; then ln -sfn "$VHOST" "$ENABLED"; else a2ensite -q "${DOMAIN}.conf" >/dev/null; fi

    if "${TEST_CMD[@]}" >/dev/null 2>&1; then
        "${RELOAD_CMD[@]}"
    else
        warn "Config-Test fehlgeschlagen – Rollback!"
        "${TEST_CMD[@]}" || true
        if ((had_vhost)); then cp -a "$BACKUP_DIR/$(basename "$VHOST")" "$VHOST"; else rm -f "$VHOST"; fi
        if ((!had_link)); then rm -f "$ENABLED"; fi
        "${TEST_CMD[@]}" >/dev/null 2>&1 && "${RELOAD_CMD[@]}"
        die "vHost wurde zurückgerollt. Details siehe Ausgabe oben."
    fi
fi

# --------------------------------------------- certbot (falls kein Zertifikat)
SSL_NOTE=""
if [[ -z "$CERT_DIR" ]]; then
    PUBLIC_IP="$(curl -4 -fsS --max-time 10 https://api.ipify.org || hostname -I | awk '{print $1}')"
    DNS_IP="$(getent ahostsv4 "$DOMAIN" | awk 'NR==1{print $1}')"
    if [[ -n "$DNS_IP" && "$DNS_IP" == "$PUBLIC_IP" ]] && command -v certbot >/dev/null; then
        log "Zertifikat per certbot anfordern …"
        email="$(grep -rhoE 'mailto:[^"]+' /etc/letsencrypt/accounts/ 2>/dev/null | head -1 | sed 's/mailto://')"
        plugin="--nginx"; [[ $WEBSERVER == apache ]] && plugin="--apache"
        cp -a "$VHOST" "$BACKUP_DIR/$(basename "$VHOST").pre-certbot"
        contact=(--register-unsafely-without-email)
        [[ -n "$email" ]] && contact=(-m "$email")
        if certbot "$plugin" -d "$DOMAIN" --non-interactive --agree-tos --redirect "${contact[@]}"; then
            "${TEST_CMD[@]}" >/dev/null 2>&1 || { cp -a "$BACKUP_DIR/$(basename "$VHOST").pre-certbot" "$VHOST"; "${TEST_CMD[@]}" && "${RELOAD_CMD[@]}"; }
        else
            SSL_NOTE="certbot fehlgeschlagen – vorerst nur HTTP."
        fi
    else
        SSL_NOTE="DNS zeigt nicht auf diesen Server (DNS: ${DNS_IP:-–}, Server: ${PUBLIC_IP:-?}) oder certbot fehlt.
    Benötigt: A-Record ${DOMAIN} → ${PUBLIC_IP:-<Server-IP>}
    Danach: sudo certbot --${WEBSERVER/apache/apache} -d ${DOMAIN} --non-interactive --agree-tos --redirect"
    fi
fi

# ------------------------------------------------------------------- Cron
CRON_LINE="30 3 * * * cd ${APP_DIR} && php bin/console app:demo:reset --env=prod --no-debug >/dev/null 2>&1"
if ! crontab -u "$WEB_USER" -l 2>/dev/null | grep -qF "app:demo:reset"; then
    log "Cron für ${WEB_USER} anlegen (03:30 Demo-Reset) …"
    { crontab -u "$WEB_USER" -l 2>/dev/null || true; echo "$CRON_LINE"; } | crontab -u "$WEB_USER" -
fi

# ------------------------------------------------------------ Abschlusstest
sleep 1
code_local="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN}" http://127.0.0.1/login || true)"
code_https="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://${DOMAIN}/login" || echo 000)"
blocked_ok=1
for path in /.env /.env.local /.git/config /var/ /composer.json; do
    c="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN}" "http://127.0.0.1${path}" || true)"
    [[ "$c" == 200 ]] && { warn "${path} liefert 200!"; blocked_ok=0; }
done

status_snapshot > "$BACKUP_DIR/status-after.txt"
if diff -q "$BACKUP_DIR/status-before.txt" "$BACKUP_DIR/status-after.txt" >/dev/null; then
    others="unverändert ✓"
else
    others="GEÄNDERT – bitte prüfen:"$'\n'"$(diff "$BACKUP_DIR/status-before.txt" "$BACKUP_DIR/status-after.txt" || true)"
fi

cat <<SUMMARY

────────────────────── SymfoLeave – Status ──────────────────────
 Webserver        : ${WEBSERVER}, PHP-FPM ${PHP_VERSION}
 Verzeichnis      : ${APP_DIR} (Branch ${BRANCH})
 vHost            : ${VHOST}
 Zertifikat       : ${CERT_DIR:-keins (siehe Hinweis)}
 HTTP lokal /login: ${code_local}  (über Host-Header, Redirect auf HTTPS = 301)
 HTTPS /login     : ${code_https}
 Geheime Pfade    : $([[ $blocked_ok == 1 ]] && echo 'gesperrt ✓' || echo 'NICHT gesperrt!')
 Andere Websites  : ${others}
 Cron             : 03:30 app:demo:reset (${WEB_USER})
 Backups          : ${BACKUP_DIR}
${SSL_NOTE:+ Hinweis          : ${SSL_NOTE}}
──────────────────────────────────────────────────────────────────
SUMMARY
