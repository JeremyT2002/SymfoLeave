#!/usr/bin/env bash
# SymfoLeave – Update auf dem VPS (als Deploy-User im Projektordner ausführen).
#   cd /var/www/symfoleave.jeremythelen.de && bash deploy/deploy.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${BRANCH:-$(git -C "$APP_DIR" rev-parse --abbrev-ref HEAD)}"
cd "$APP_DIR"

console() { php bin/console "$@" --env=prod --no-debug; }

if [[ -f var/data_prod.db ]]; then
    mkdir -p var/backups
    cp -a var/data_prod.db "var/backups/data_prod-$(date +%Y%m%d-%H%M%S).db"
    # nur die letzten 10 Backups behalten
    find var/backups -name 'data_prod-*.db' -printf '%T@ %p\n' | sort -rn | tail -n +11 | cut -d' ' -f2- | xargs -r rm -f
fi

git fetch --quiet origin "$BRANCH"
git pull --quiet --ff-only origin "$BRANCH"

APP_ENV=prod composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
console tailwind:build --minify
console asset-map:compile >/dev/null
console doctrine:migrations:migrate --no-interaction --allow-no-migration
console cache:clear
console cache:warmup

echo "Deploy fertig: $(git log -1 --format='%h %s')"
