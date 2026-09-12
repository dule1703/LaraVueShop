#!/bin/bash
#
# deploy/finish-release.sh — PLAN A: zavr\u0161ava release NAKON \u0161to je GitHub Actions
# vec rsync-ovao kod preko SSH-a direktno u releases/<timestamp>/.
#
# Poziva se preko SSH-a iz GitHub Actions kao:
#   ssh ... "cd RELEASES_DIR/<timestamp> && bash deploy/finish-release.sh <env> <timestamp>"
#
# Za razliku od stare deploy/run.sh (Plan B, cPanel Git Version Control):
#   - NE kopira kod (rsync sa GH Actions runner-a je vec to uradio)
#   - NE zavisi od .cpanel.yml niti cPanel Git checkout-a
#   - cwd je vec releases/<timestamp>/ kad se skripta pozove (GH Actions radi cd pre ssh komande)
#
set -euo pipefail

ENV_NAME="${1:?Upotreba: finish-release.sh <production|staging> <timestamp>}"
TS="${2:?Upotreba: finish-release.sh <production|staging> <timestamp>}"

# ==== PODESI OVE VREDNOSTI ZA SVOJ NALOG ====================================
CPANEL_USER_HOME="/home/ddweba"                     # potvrdjeno: echo $HOME u cPanel Terminalu
PROJECT_NAME="laravue-shop"                         # namespace po projektu -> projects/<PROJECT_NAME>/...
PHP_BIN="/usr/local/bin/php"                   # tacna putanja: cPanel > Select PHP Version > "PHP Binary (CLI)"
COMPOSER_BIN="/usr/local/bin/composer"    # proveri: which composer   (cPanel Terminal)
OPCACHE_RESET_URL=""                                # npr. https://app.example.com/__deploy/opcache-reset?token=XXXX (prazno = preskoci)
KEEP_RELEASES=5                                     # production=5, staging=3 (vidi inode racunicu u uputstvu)
# ==============================================================================

PROJECT_ROOT="$CPANEL_USER_HOME/projects/$PROJECT_NAME"
DEPLOY_BASE="$PROJECT_ROOT/deploy/$ENV_NAME"
RELEASES_DIR="$DEPLOY_BASE/releases"
SHARED_DIR="$DEPLOY_BASE/shared"
CURRENT_LINK="$DEPLOY_BASE/current"
NEW_RELEASE="$RELEASES_DIR/$TS"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

# --- lock: sprecava da se dva deploy-a istog env-a preklope ---
mkdir -p "$DEPLOY_BASE"
exec 200>"$DEPLOY_BASE/.deploy.lock"
flock -n 200 || { echo "Deploy za $ENV_NAME je vec u toku — prekidam."; exit 1; }

log "== ZAVRSAVANJE deploy-a [$ENV_NAME] -> release $TS =="

if [ ! -d "$NEW_RELEASE" ]; then
    echo "GRESKA: $NEW_RELEASE ne postoji — rsync korak iz GitHub Actions nije uspeo pre ove skripte." >&2
    exit 1
fi

if [ ! -f "$SHARED_DIR/.env" ]; then
    echo "GRESKA: $SHARED_DIR/.env ne postoji. Napravi ga rucno pre prvog deploy-a (vidi uputstvo, korak A.2)." >&2
    exit 1
fi

cd "$NEW_RELEASE"

# 1) poveži shared resurse (storage/ i .env NISU deo rsync-a — vec zive na serveru)
rm -rf storage
ln -s "$SHARED_DIR/storage" storage
ln -sf "$SHARED_DIR/.env" .env

# 2) PHP zavisnosti (vendor/ se ne prenosi rsync-om — instalira se ovde, na serveru, ispravnom PHP verzijom)
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --no-progress

# 3) public/storage symlink unutar NOVOG release-a (mora se ponoviti svaki put)
"$PHP_BIN" artisan storage:link

# 4) migracije — moraju biti unazad-kompatibilne (expand/contract), bez maintenance moda
"$PHP_BIN" artisan migrate --force

# 5) cache
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

# 6) ATOMSKI symlink swap current -> novi release
ln -sfn "$NEW_RELEASE" "$CURRENT_LINK.tmp"
mv -Tf "$CURRENT_LINK.tmp" "$CURRENT_LINK"
log "current -> $NEW_RELEASE"

# 7) opcache reset (CLI i LSPHP web pool imaju ODVOJEN opcache — CLI migrate ga ne cisti)
if [ -n "$OPCACHE_RESET_URL" ]; then
    curl -fsS "$OPCACHE_RESET_URL" || log "UPOZORENJE: opcache-reset poziv nije uspeo (proveri rucno)"
fi

# 8) cleanup starih release-ova — INODE BUDZET (svaki release sa vendor/ ~15-25k inoda)
cd "$RELEASES_DIR"
ls -1 | sort -r | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
    log "brisem stari release: $old"
    rm -rf -- "$old"
done

log "== KRAJ deploy-a [$ENV_NAME] — uspesno =="
