#!/usr/bin/env bash
# SymfoLeave live von SQLite auf MySQL umstellen (einmalig, als root im Projektordner):
#   bash deploy/switch-to-mysql.sh
# Fragt einmal nach dem MySQL-Root-Passwort, legt Datenbank + Benutzer mit Zufallspasswort an,
# trägt DATABASE_URL in .env.local ein und lädt Schema + Demo-Daten neu. Mehrfach ausführbar.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_NAME="${DB_NAME:-symfoleave}"
DB_USER="${DB_USER:-symfoleave}"
cd "$APP_DIR"

[[ -f .env.local ]] || { echo ".env.local fehlt – zuerst den Prod-Build einrichten."; exit 1; }

DB_PASS="$(openssl rand -hex 24)"
SERVER_VERSION="$(mysqld --version | grep -oP '\d+\.\d+\.\d+' | head -1)"

# Root-Passwort aus MYSQL_ROOT_PASSWORD (nicht interaktiv) oder per Abfrage
if mysql -u root -e 'SELECT 1' >/dev/null 2>&1; then
    root_mysql() { mysql -u root; }   # root per auth_socket (Ubuntu-Standard), kein Passwort nötig
elif [[ -n "${MYSQL_ROOT_PASSWORD:-}" ]]; then
    root_mysql() { MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root; }
else
    echo "Lege Datenbank '$DB_NAME' und Benutzer '$DB_USER' an – bitte das MySQL-Root-Passwort eingeben:"
    root_mysql() { mysql -u root -p; }
fi
root_mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
# Ab hier nur weiter, wenn der neue Benutzer sich wirklich anmelden kann
MYSQL_PWD="$DB_PASS" mysql -u "$DB_USER" -h127.0.0.1 -e "USE \`$DB_NAME\`" || { echo "MySQL-Benutzer funktioniert nicht – .env.local bleibt unverändert."; exit 1; }

# DATABASE_URL ersetzen (bisherige SQLite-Zeile bleibt als Kommentar erhalten)
cp -a .env.local ".env.local.bak-$(date +%Y%m%d-%H%M%S)"
sed -i 's|^DATABASE_URL=\(.*sqlite.*\)$|# vorher: \1|; /^DATABASE_URL=/d' .env.local
echo "DATABASE_URL=\"mysql://$DB_USER:$DB_PASS@127.0.0.1:3306/$DB_NAME?serverVersion=$SERVER_VERSION&charset=utf8mb4\"" >> .env.local
chgrp www-data .env.local
chmod 640 .env.local

console() { runuser -u www-data -- php bin/console "$@" --env=prod --no-debug; }
console cache:clear
console app:demo:reset --no-interaction
console cache:warmup

echo "Fertig: SymfoLeave nutzt jetzt MySQL ($DB_NAME). Die alte SQLite-Datei bleibt in var/ als Sicherung."
