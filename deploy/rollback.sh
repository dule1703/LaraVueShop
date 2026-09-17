#!/bin/bash
#
# Rollback na prethodni (ili eksplicitno naveden) release.
# Poziva se preko SSH-a, isti obrazac kao finish-release.sh:
#   ssh ... "bash ~/projects/laravue-shop/deploy/<env>/rollback.sh <env> [timestamp]"
#
# Ne radi novi build, ne radi composer install, ne dira migracije - samo
# pomera 'current' symlink na vec postojeci, vec pripremljen release.
#
set -euo pipefail

ENV_NAME="${1:?Upotreba: rollback.sh <production|staging> [timestamp]}"
TARGET_TS="${2:-}"   # opciono - bez ovoga, rollback ide na release NEPOSREDNO PRE trenutnog

# ==== ISTE VREDNOSTI KAO U finish-release.sh - MORAJU DA SE POKLAPAJU =======
CPANEL_USER_HOME="/home/ddweba"
PROJECT_NAME="laravue-shop"
OPCACHE_RESET_URL=""   # npr. https://app.example.com/__deploy/opcache-reset?token=XXXX (prazno = preskoci)
# ==============================================================================

PROJECT_ROOT="$CPANEL_USER_HOME/projects/$PROJECT_NAME"
DEPLOY_BASE="$PROJECT_ROOT/deploy/$ENV_NAME"
RELEASES_DIR="$DEPLOY_BASE/releases"
CURRENT_LINK="$DEPLOY_BASE/current"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

# --- lock: isti .deploy.lock kao finish-release.sh, sprecava da rollback i deploy trcé istovremeno ---
mkdir -p "$DEPLOY_BASE"
exec 200>"$DEPLOY_BASE/.deploy.lock"
flock -n 200 || { echo "Deploy/rollback za $ENV_NAME je vec u toku - prekidam."; exit 1; }

if [ ! -L "$CURRENT_LINK" ]; then
    echo "GRESKA: $CURRENT_LINK ne postoji ili nije symlink - nema aktivnog release-a za rollback." >&2
    exit 1
fi

CURRENT_RELEASE="$(basename "$(readlink -f "$CURRENT_LINK")")"
log "Trenutno aktivan release: $CURRENT_RELEASE"

if [ -z "$TARGET_TS" ]; then
    # bez eksplicitnog timestamp-a: uzmi release neposredno PRE trenutnog (sortirano opadajuce)
    TARGET_TS="$(cd "$RELEASES_DIR" && ls -1 | sort -r | awk -v cur="$CURRENT_RELEASE" '
        found_cur==1 { print; exit }
        $0==cur { found_cur=1 }
    ')"
    if [ -z "$TARGET_TS" ]; then
        echo "GRESKA: Nema starijeg release-a za rollback (trenutni je najstariji sacuvan)." >&2
        exit 1
    fi
    log "Nije prosledjen timestamp - biram prethodni release: $TARGET_TS"
fi

TARGET_RELEASE="$RELEASES_DIR/$TARGET_TS"

if [ ! -d "$TARGET_RELEASE" ]; then
    echo "GRESKA: $TARGET_RELEASE ne postoji. Dostupni release-ovi:" >&2
    ls -1 "$RELEASES_DIR" >&2
    exit 1
fi

if [ "$TARGET_TS" = "$CURRENT_RELEASE" ]; then
    echo "GRESKA: Target release ($TARGET_TS) je vec aktivan - nema sta da se rollback-uje." >&2
    exit 1
fi

log "== ROLLBACK [$ENV_NAME]: $CURRENT_RELEASE -> $TARGET_TS =="

# ATOMSKI symlink swap unazad - identican mehanizam kao napred u finish-release.sh
ln -sfn "$TARGET_RELEASE" "$CURRENT_LINK.tmp"
mv -Tf "$CURRENT_LINK.tmp" "$CURRENT_LINK"
log "current -> $TARGET_RELEASE"

if [ -n "$OPCACHE_RESET_URL" ]; then
    curl -fsS "$OPCACHE_RESET_URL" || log "UPOZORENJE: opcache-reset poziv nije uspeo (proveri rucno)"
fi

log "== KRAJ rollback-a [$ENV_NAME] - uspesno. current sada pokazuje na $TARGET_TS =="
log "NAPOMENA: migracije NISU automatski vracene. Ako je release $CURRENT_RELEASE dodao novu migraciju,"
log "ta kolona/tabela i dalje postoji u bazi - to je ocekivano i bezbedno zbog expand/contract pristupa."
