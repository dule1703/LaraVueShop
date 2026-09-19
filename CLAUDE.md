# CLAUDE.md — LaraVueShop

Ovaj fajl čita Claude Code na početku svake sesije u ovom repo-u. Sadrži
konvencije i kontekst koji ne treba svaki put ponovo objašnjavati.

## Komunikacija
- Odgovaraj na srpskom jeziku.
- Za složenije arhitekturne ili bezbednosne odluke koristi extended thinking.

## Model selection strategija
- **Haiku** — čitanje fajlova, formatiranje, prosti checks
- **Sonnet** — svakodnevni kod, YAML, debugging (~80% zadataka) — **default**
- **Opus** — složena arhitektura, multi-file refactoring, bezbednosne odluke

## Sub-agent pravila
- Uvek eksplicitno override na Sonnet, osim kada zadatak jasno zahteva Opus
  (navedi razlog ako biraš Opus).
- Paralelni sub-agenti za nezavisne zadatke (npr. nezavisni test fajlovi).
- Centralni orchestrator koordinira zadatke; ne izvršava detalje sam.

## Šta je ovaj projekat
Vežbovni e-commerce projekat koji se proširuje u **online knjižaru**
(domaći autori, po žanrovima i oblastima). Nije prava produkcija.

Odluke o opsegu (potvrđene):
- **Samo knjige** — nema drugih vrsta proizvoda u opsegu
- Valuta: **EUR** (PayPal ne podržava RSD kao valutu naplate)
- Ciljna veličina kataloga: 300-500 knjiga na početku, ali šema i upiti
  moraju da podnesu i hiljade (paginacija obavezna, nema učitavanja
  celog kataloga odjednom)
- Podrška za **ćirilicu i latinicu** u prikazu i pretrazi je obavezna
  (normalizovana `books.search_text` kolona, ne fulltext)

## Stack
- Laravel 12 (PHP), Inertia.js 2, Vue 3 — CSR, ne SSR
- PHP 8.2 lokalno / PHP 8.4 na serveru (razlika je namerna, vidi Deploy)
- Node 22 lokalno; frontend build se radi na GitHub Actions runner-u
- DB: MariaDB 10.11 (produkcija/staging), SQLite `:memory:` u testovima
- Plaćanja: **PayPal implementiran** (srmklive/paypal, paypal-server-sdk);
  **Stripe NIJE implementiran** — `stripe/stripe-php` je instaliran ali se
  nigde ne koristi, validacija dozvoljava samo `paypal` i `cod`
- Frontend: Tailwind, lucide ikone. shadcn-vue je samo podešen
  (`components.json`), nijedna komponenta još nije dodata

## Autentikacija — koristi postojeći Breeze
- Projekat ima **Laravel Breeze** sa kompletnim auth tokom (registracija,
  login, password reset, email verification, profil) i postojećim testovima
  koji ga pokrivaju.
- **Ne praviti paralelne auth rute/kontrolere.** Ako treba drugačiji
  izgled, menjaju se samo Vue stranice; rute i kontroleri ostaju.
- Podaci o kupcu (adresa, telefon) treba da žive uz korisnički nalog
  (profil / `addresses` tabela), a `orders` i dalje čuva **snapshot**
  adrese u trenutku porudžbine (isti obrazac kao `product_name`/
  `product_price` — istorijska porudžbina se ne sme menjati unazad).

## POZNATI PROBLEMI — popraviti pre širenja kataloga (Faza 0)
Otkriveno u auditu; svaki novi deo kataloga povećava štetu od ovih rupa:
1. ✅ **REŠENO (Faza 0, korak 1)** — admin rute su bile zaštićene samo
   `auth`, bez provere uloge. Rešenje: `app/Http/Middleware/EnsureUserIsAdmin.php`,
   registrovan kao alias `admin` u `bootstrap/app.php`, primenjen kao
   `['auth', 'admin']` u `routes/web.php:56` (`auth` mora ostati prvi —
   gost dobija redirect na login, ne 403). Pokriveno testovima u
   `tests/Feature/Admin/AdminAccessTest.php` (18 ruta × gost/ne-admin/admin).
2. ✅ **REŠENO (Faza 0, korak 2)** — server je prihvatao `price`/
   `total_price` od klijenta. `OrderController::store`
   (`app/Http/Controllers/OrderController.php:14`) sada isključivo
   računa cenu iz `products.price`; `items.*.price`, `items.*.name` i
   `total_price` iz zahteva se ignorišu.
3. ✅ **REŠENO (Faza 0, korak 2)** — atomsko umanjenje zaliha u
   `DB::transaction`: `UPDATE products SET stock = stock - :q
   WHERE id = :id AND stock >= :q`. `affected === 0` → `ValidationException`
   (422) sa porukom koja knjiga/koliko na stanju, cela transakcija se
   rollback-uje (uključujući prethodne stavke iste porudžbine).
   Testovi: `tests/Feature/OrderStoreTest.php` (4 testa: tampered price,
   dovoljno zaliha, nedovoljno zaliha, race-condition guard — prava
   konkurentnost se ne može testirati na SQLite `:memory:`, jedan proces/
   jedna konekcija; oslanja se na isti `WHERE stock >= :q` guard koji na
   MariaDB-u sa realnim konekcijama rešava trku preko row-level lock-a).
   **✅ PROVERENO** — `Checkout.vue:228-230` već prikazuje
   `form.errors.message || form.errors.items` ispod submit dugmeta (blok
   postoji od pre Faze 0, commit `461e8bec8...`), a Inertia `useForm`
   automatski puni `form.errors` iz 422 odgovora. Pošto backend baca
   `ValidationException::withMessages(['items' => ...])`, korisnik vidi
   tačnu poruku ("Nema dovoljno zaliha za knjigu ... na stanju: X,
   traženo: Y"), ne generičku grešku. Kod nije menjan.
4. ✅ **REŠENO (Faza 0, korak 3)** — `/checkout` (`routes/web.php:68`)
   više ne šalje `Order::latest()->first()` kao prop.
5. ✅ **REŠENO (Faza 0, korak 3)** — IDOR na `/order/success/{order}`,
   `/order/cod-success/{order}`, `/payment/failed/{order}`,
   `/paypal/cancel/{order}`. Rešenje: `app/Policies/OrderPolicy.php::view()`
   (vlasnik preko `user_id`, gost preko `session('guest_order_ids')`,
   upisuje se u `OrderController::store:95-99` samo za `Auth::guest()`).
   Sve četiri rute koriste standardni `middleware('can:view,order')`
   (vidi napomenu u tački 7 — `paypal.cancel` je od Faze 0, koraka 4
   prešao sa posebnog `order.owner` middleware-a na ovaj standardni,
   pošto je `PayPalController::cancel()` sada tipizira `Order $order`).
   Napomena: Cart (`resources/js/Stores/cart.js`) je isključivo
   frontend/localStorage, nema server-side session tracking — nije mogao
   da se reiskoristi za gost/order ownership, pa je `guest_order_ids`
   nov mehanizam.
   Testovi: `tests/Feature/OrderAccessTest.php`.
6. ✅ **REŠENO (Faza 0, korak 1)** — `CategoryFactory` (prepisan
   konstruktor) i prazan `ProductFactory` popravljeni. Dodati helperi:
   `CategoryFactory::active()/inactive()`, `ProductFactory::inactive()/
   outOfStock()`, `UserFactory::admin()`.
7. ✅ **REŠENO (Faza 0, korak 4)** — `PayPalController::__construct` je
   ranije odmah zvao `getAccessToken()` (mrežni poziv) → nije mogao da se
   mock-uje. Rešenje: `app/Contracts/PaymentGateway.php` (interfejs sa
   `createOrder(Order)` / `captureOrder(string)`), implementacija
   `app/Services/PayPalGateway.php` (PayPal klijent i `getAccessToken()`
   se sada lenjo inicijalizuju tek pri prvom stvarnom pozivu, ne u
   konstruktoru), bind u `app/Providers/AppServiceProvider.php::register()`.
   `PayPalController` prima `PaymentGateway` kroz DI. U testovima se
   bind-uje `tests/Doubles/FakePaymentGateway.php` — nema mrežnih poziva.
   Kao posledica, `PayPalController::cancel()` sada tipizira `Order $order`
   (implicit route-model binding), pa je `paypal.cancel` ruta prešla sa
   posebnog `order.owner` middleware-a na standardni `can:view,order`;
   `app/Http/Middleware/AuthorizeOrderAccess.php` i alias `order.owner` su
   uklonjeni (više nemaju svrhu — bila je to zaobilaznica baš za ovaj
   problem). `success()` i `createPayment()` i dalje primaju `$orderId`
   (int) — nisu menjani van zahtevanog opsega.
   Testovi: `tests/Feature/PayPalPaymentTest.php`.
   **✅ REŠENO** — `/paypal/success/{order}` i `/paypal/create-payment/{order}`
   (routes/web.php) sada takođe imaju `middleware('can:view,order')`, isti
   obrazac kao ostale order rute. `PayPalController::success()` i
   `createPayment()` su tipizirani kao `Order $order` (implicit
   route-model binding, isto kao `cancel()`). Testovi (vlasnik prolazi,
   tuđi korisnik/gost bez sesije dobija 403, gateway se ne poziva kad
   autorizacija odbije) u `tests/Feature/PayPalPaymentTest.php`.
8. ✅ **REŠENO (Faza 0, korak 4)** — sandbox PayPal test podaci (buyer
   nalog, ne API kredencijal) su bili hardkodovani u `Checkout.vue:190-199`
   i završavali u javnom JS bundle-u. Uklonjeni iz frontend koda. Za ručno
   testiranje checkout-a kroz PayPal Sandbox koristiti:
   - Email: `sb-ybtyg48467509@personal.example.com`
   - Lozinka: `T-9kqa1B`
   - Test kartice: Visa `4111111111111111`, MasterCard `5148652529369811`

   Ovo je sandbox **buyer** nalog (ručno logovanje na lažnu PayPal
   checkout stranicu tokom testiranja) — **nije isto** što i
   `PAYPAL_SANDBOX_CLIENT_SECRET` (merchant API OAuth secret koji
   `PayPalGateway` koristi server-side za `getAccessToken()`). Ta dva
   kredencijala ne treba mešati; `PAYPAL_SANDBOX_CLIENT_SECRET` ne sme
   nikad stići na frontend.
9. `@/components` alias vs postojeći `resources/js/Components` — razlika
   samo u velikom slovu. Radi na Windows-u, **puca na Ubuntu runner-u**.
   Razrešiti pre prve shadcn-vue komponente.

## Deploy arhitektura (Faze 1-4, potvrđeno radi)
- Shared cPanel hosting (unlimited.rs), korisnik `ddweba`,
  `/home/ddweba/projects/laravue-shop/`
- GitHub repo: `dule1703/LaraVueShop`; `main` → production,
  `develop` → staging (branch protection + PR tok aktivan)
- CI/CD: `.github/workflows/deploy.yml` — build na runner-u, tar+ssh
  transfer (rsync nije dostupan), `finish-release.sh` na serveru:
  composer install --no-dev, migracije, cache, atomski symlink swap
- Rollback skripta (workflow_dispatch, bira se release timestamp)
- Testovi na PHP 8.4 + SQLite `:memory:`, pre deploy-a
- Email notifikacije o deploy-u (mail.ddwebapps.com)
- Inode budžet je stvarno ograničenje (ne disk) — `KEEP_RELEASES`
  production=5, staging=3

## Pravila za migracije i deploy
- Migracije MORAJU biti aditivne/unazad-kompatibilne (expand/contract) —
  `rollback.sh` vraća samo kod, ne šemu, pa stariji release mora da radi
  sa novijom šemom.
- **Jedna DDL promena po migraciji** — na MariaDB-u DDL nije transakcioni;
  migracija koja pukne na pola ostavlja bazu delimično migriranu i sledeći
  deploy puca na "table already exists".
