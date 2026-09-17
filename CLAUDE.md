# CLAUDE.md — LaraVueShop

Ovaj fajl čita Claude Code na početku svake sesije u ovom repo-u. Sadrži
konvencije i kontekst koji ne treba svaki put ponovo objašnjavati.

## Komunikacija
- Odgovaraj na srpskom jeziku.
- Za složenije arhitekturne ili bezbednosne odluke koristi extended thinking
  (npr. Redis migracija, promene u deploy pipeline-u).

## Model selection strategija
- **Haiku** — čitanje fajlova, formatiranje, prosti checks
- **Sonnet** — svakodnevni kod, YAML, debugging (~80% zadataka) — **default**
- **Opus** — složena arhitektura, multi-file refactoring, bezbednosne odluke

## Sub-agent pravila
- Uvek eksplicitno override na Sonnet, osim kada zadatak jasno zahteva Opus
  (navedi razlog ako biraš Opus).
- Paralelni sub-agenti za nezavisne zadatke (npr. nezavisni test fajlovi).
- Centralni orchestrator koordinira zadatke; ne izvršava detalje sam.

## Stack
- Laravel 12 (PHP), Inertia.js 2, Vue 3 — CSR, ne SSR
- PHP 8.2 lokalno / PHP 8.4 na serveru (razlika je namerna, vidi Deploy)
- Node 22 lokalno; frontend build se radi na GitHub Actions runner-u, ne na
  serveru
- DB: MariaDB 10.11 (produkcija/staging), SQLite in-memory u CI testovima
- Plaćanja: PayPal (paypal/paypal-server-sdk, srmklive/paypal) i Stripe
  (stripe/stripe-php) — u testovima uvek mock-ovano, nikad pravi API pozivi
- Frontend UI: shadcn-vue komponente (style: new-york), Tailwind, lucide ikone

## Deploy arhitektura (Faze 1-4, potvrđeno radi)
- Shared cPanel hosting (unlimited.rs), korisnik `ddweba`,
  `/home/ddweba/projects/laravue-shop/`
- GitHub repo: `dule1703/LaraVueShop`; `main` → production,
  `develop` → staging (branch protection + PR tok aktivan)
- CI/CD: GitHub Actions (`.github/workflows/deploy.yml`) — build na runner-u
  (composer install radi Ziggy, npm build), tar+ssh transfer (rsync nije
  dostupan na hostingu), `finish-release.sh` na serveru radi
  composer install --no-dev, migracije, cache, atomski symlink swap
  (`releases/<timestamp>` → `current`)
- Rollback: postoji formalizovana skripta (workflow_dispatch, bira se
  release timestamp)
- Testovi: Feature testovi na PHP 8.4 (poklapa server), SQLite :memory:,
  pokreću se pre deploy-a
- Notifikacije: email preko postojećeg SMTP-a (mail.ddwebapps.com) na
  uspeh/neuspeh deploy-a
- GitHub MCP connector povezan i korišćen (claude.ai custom connector →
  api.githubcopilot.com/mcp)
- Inode budžet je stvarno ograničenje na hostingu (ne disk) — zato
  `KEEP_RELEASES` (production=5, staging=3) i cleanup starih release-ova

## Poznati "gotcha"-ovi (ne ponavljati grešku)
- `current`/`current.tmp` MORA biti symlink, nikad običan direktorijum —
  atomic swap (`mv -T`) inače puca
- `finish-release.sh` na serveru se PREPISUJE svakim deploy-em — ručne
  izmene preko File Manager-a se gube, sve mora ići kroz git
- rsync nije dostupan na ovom hosting nalogu — tar+ssh je trajno rešenje

## Granice rada
- `deploy/*/shared/.env` i `deploy/*/shared/storage/` žive na serveru,
  nisu deo repo-a — ne pokušavati ih generisati/commit-ovati
- `vendor/` se ne šalje na server preko tar-a — instalira se na serveru
- Migracije moraju biti unazad-kompatibilne (expand/contract), bez
  maintenance moda

## Otvoreno (Faza 5, u toku)
- Redis migracija za cache/session/queue (trenutno `database` driver)
- Konsolidacija `finish-release.sh` i `rollback.sh` u zajednički
  `deploy/common.sh`
- Prava test pokrivenost: checkout tok, PayPal/Stripe (mock), cart logika
