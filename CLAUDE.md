# CLAUDE.md — LaraVueShop

Ovaj fajl čita Claude Code na početku svake sesije u ovom repo-u. Sadrži
konvencije i kontekst koji ne treba svaki put ponovo objašnjavati.

## Komunikacija
- Odgovaraj na srpskom jeziku.
- Za složenije arhitekturne ili bezbednosne odluke koristi extended thinking.

## Radni tok
- Kad se završi korak/faza, ažuriranje `CLAUDE.md` (nova saznanja, rešeni
  problemi, otvorene napomene) **MORA** biti deo **ISTOG commit-a** kao i
  kod — nikad poseban commit/push samo za dokumentaciju. Standardna praksa.
- Promene idu preko PR-a ka `develop`/`main`; korisnik merge-uje (bez
  direktnog push-a).
- **Feature grane su privremene.** Kad se grana merge-uje u `develop` (i
  kasnije u `main`, ako grana ide i tim putem), i lokalna i remote grana
  se **brišu** — ne ostaju kao arhiva. Merge commit čuva istoriju, brisanje
  grane ne gubi ništa. Važi za svaku granu otvorenu za pojedinačni korak/fazu.

### ⚠️ Obavezna checklista u SVAKOM izveštaju o završenom koraku/fazi
Bez obzira da li je odgovor "ništa", izveštaj **MORA** eksplicitno navesti:
1. **Migracije** — da li treba pokrenuti migracije na lokalu/staging/
   production nakon pull-a/deploy-a (npr. nove tabele).
2. **Ručne artisan komande van deploy pipeline-a** — da li postoji komanda
   koja NIJE u deploy pipeline-u i mora se ručno pokrenuti na svakom
   okruženju posebno (npr. `catalog:import-books`,
   `catalog:convert-products-to-books`) — ako da, TAČNE komande i putanje
   po okruženju (lokalno vs SSH na staging/production, uključujući
   `PHP_BIN` putanju sa servera).
3. **Nove zavisnosti** (npm/composer paket) koje zahtevaju install/lock
   fajl osvežavanje pre nego što promene budu vidljive.

Ovo ide na kraj svakog izveštaja kao kratka checklista, ne samo kad se
Claude Code seti.

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
- JS test runner: **vitest** (`npm run test`), dodat uz cart race-condition
  fix (vidi Korpa niže) — do tada projekat nije imao JS testove, samo
  PHPUnit. `vitest.config.js` je odvojen od `vite.config.js` i ručno definiše
  `@` alias (inače dolazi iz `laravel-vite-plugin`, koji se pod `vitest`-om
  ne pokreće). `npm install` zahteva `--legacy-peer-deps` (postojeći
  `@vitejs/plugin-vue@^5` traži peer `vite@^5||^6`, projekat je na `vite@^7`
  — preduslovan mismatch, ne nešto što je vitest uveo).
- DB: MariaDB 10.11 (produkcija/staging), SQLite `:memory:` u testovima
- Plaćanja: **PayPal implementiran** (srmklive/paypal, paypal-server-sdk);
  **Stripe NIJE implementiran** — `stripe/stripe-php` je instaliran ali se
  nigde ne koristi, validacija dozvoljava samo `paypal` i `cod`
- Frontend: **Tailwind 3.4** (PostCSS, `@tailwind` direktive — ne v4), postojeća
  aplikacija koristi FontAwesome ikone. shadcn-vue je samo podešen
  (`components.json`), nijedna komponenta još nije dodata. Aliasi su PascalCase
  kao i postojeći folder: `@/Components`, `@/Components/ui` (vidi #9); `@/lib`,
  `@/composables` su lowercase (`resources/js/lib/`: `bookLabels.js`, `utils.js`).
- **shadcn-vue ikone (svesna odluka: miks FontAwesome + lucide).**
  `iconLibrary` u `components.json` ostaje `"lucide"`. Provereno u izolovanom
  projektu: CLI prihvata bilo koji string, ali vrednost `"fontawesome"` ništa ne
  menja — generisane komponente (npr. `dialog`) svejedno uvoze
  `lucide-vue-next`. Iste provere su pokazale da `shadcn-vue add` **ne
  instalira** `lucide-vue-next` (sa `"lucide"` doda `@lucide/vue`, koji
  generisani kod ne uvozi) niti kreira `@/lib/utils` — build puca dok se to ne
  doda. Zato su ovo već instalirano/dodato: `lucide-vue-next`, `clsx`,
  `tailwind-merge@^2` (v3 je za Tailwind 4) i `resources/js/lib/utils.js`
  (`cn()`). Kad se doda prva komponenta: ukloniti višak `@lucide/vue` ako ga
  CLI upiše u `package.json`; nove shadcn komponente koriste lucide, postojeći
  ekrani ostaju na FontAwesome-u.

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
   `['auth', 'admin']` u `routes/web.php:37` (`auth` mora ostati prvi —
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
4. ✅ **REŠENO (Faza 0, korak 3)** — `/checkout` (`routes/web.php:52`)
   više ne šalje `Order::latest()->first()` kao prop.
5. ✅ **REŠENO (Faza 0, koraci 3-4)** — IDOR na `/order/success/{order}`,
   `/order/cod-success/{order}`, `/payment/failed/{order}`,
   `/paypal/cancel/{order}`. Rešenje: `app/Policies/OrderPolicy.php::view()`
   (vlasnik preko `user_id`, gost preko `session('guest_order_ids')`,
   upisuje se u `OrderController::store:95-99` samo za `Auth::guest()`).
   Sve rute (uključujući `paypal.cancel`, nakon što je kontroler
   tipiziran) koriste standardni `middleware('can:view,order')`. Poseban
   `AuthorizeOrderAccess`/`order.owner` middleware je bio privremena
   zaobilaznica dok #7 nije rešen — **obrisan** kad je prestao da bude
   potreban.
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
   zvao `getAccessToken()` (mrežni poziv), nije se mogao mock-ovati.
   Rešenje: `app/Contracts/PaymentGateway.php` interfejs
   (`createOrder(Order)` / `captureOrder(string)`), implementacija
   `app/Services/PayPalGateway.php` sa **lenjom** inicijalizacijom (klijent
   i `getAccessToken()` tek pri prvom stvarnom pozivu, ne u konstruktoru).
   Bind u `AppServiceProvider::register()`. Testovi kroz
   `tests/Doubles/FakePaymentGateway.php` +
   `tests/Feature/PayPalPaymentTest.php` — bez mrežnih poziva.
   Kao posledica, `success()` i `createPayment()` (ranije `$orderId` kao
   int) su takođe tipizirani kao `Order $order` i dobili
   `middleware('can:view,order')` — zatvara isti IDOR obrazac otkriven
   usput tokom ovog koraka (vlasnik prolazi, tuđi korisnik/gost bez
   sesije 403, gateway se ne poziva kad autorizacija odbije).
8. ✅ **REŠENO (Faza 0, korak 4)** — hardkodovan PayPal sandbox test
   buyer nalog (email/lozinka za ručno testiranje checkout-a, **ne**
   `PAYPAL_SANDBOX_CLIENT_SECRET`) je uklonjen iz `Checkout.vue:195` u
   potpunosti (ne premešten u env), jer testni kredencijali ne treba da
   postoje u kodu koji vidi krajnji korisnik čak ni iza env uslova.
   **Test buyer nalog za ručno testiranje (PayPal Sandbox):**
   - Email: `sb-ybtyg48467509@personal.example.com`
   - Lozinka: `T-9kqa1B`
   - Test kartice: Visa `4111111111111111`, MasterCard `5148652529369811`

   Ovo je sandbox **buyer** nalog (ručno logovanje na lažnu PayPal
   checkout stranicu tokom testiranja) — **nije isto** što i
   `PAYPAL_SANDBOX_CLIENT_SECRET` (merchant API OAuth secret koji
   `PayPalGateway` koristi server-side). Ne mešati ta dva kredencijala;
   `PAYPAL_SANDBOX_CLIENT_SECRET` ne sme nikad stići na frontend.
9. ✅ **REŠENO (Faza 3.1)** — `components.json` je imao `@/components` i
   `@/components/ui` (malo `c`), a folder i svih ~30 importa koriste
   `Components`. Radi na Windows-u (case-insensitive), puca na Ubuntu-u, a
   shadcn CLI bi generisao paralelan lowercase folder. Rešenje: aliasi u
   `components.json` prebačeni na `@/Components` i `@/Components/ui` (folder
   se **nije** preimenovao; Vite nema eksplicitan `@` alias, dolazi iz
   `laravel-vite-plugin`). Guard: `tests/Unit/FrontendImportCaseTest.php`
   proverava svaki `@/...` import i svaki alias iz `components.json` protiv
   stvarnih imena fajlova (`scandir`, jer `file_exists` na Windows-u laže) —
   pada na starom `components.json`. Ne uvoditi importe sa drugačijim
   pisanjem slova.

## Knjige — admin (Faza 2)
- Knjiga = `products` red (naslov, slug, cena, zaliha, slika, aktivnost) + `books`
  red (ISBN, izdavač, godina, strane, jezik, pismo, format). Oba se **uvek**
  upisuju kroz `app/Services/BookService.php` u jednoj `DB::transaction`
  (zajedno sa autorima). Ne upisivati `Product`/`Book` direktno iz kontrolera.
- `author_book` se menja isključivo sa `detach()` + `attach()` (po jedan
  `attach` po redu), **nikad `sync()`**: PK je `(book_id, author_id, role)`, a
  `sync()` poredi samo po `author_id`, pa ne može da predstavi istog autora sa
  dve uloge (npr. autor + ilustrator).
- ISBN: `app/Support/Isbn.php` (normalizacija, checksum ISBN-10/13, konverzija),
  pravilo `app/Rules/ValidIsbn.php`. U bazi se čuva kanonski `isbn13` (+ `isbn10`
  kad postoji); unos u formi je jedno polje `isbn`.
- `products.stock = NULL` (neograničeno) forma dozvoljava samo za format `ebook`.
  Checkout (`WHERE stock >= :q`) još ne podržava NULL zalihe — pre prodaje
  e-knjiga to treba rešiti.
- `php artisan catalog:convert-products-to-books` — ručno, idempotentno
  prebacivanje proizvoda iz kategorije `knjige` (i potkategorija) u `books`
  (`--dry-run`, `--language`, `--format`). **Ne ide u deploy pipeline.**
- Inertia deli `flash.success` / `flash.error` (`HandleInertiaRequests`).

## Katalog — javni prikaz (Faza 3, deo 1)
- Rute: `/` i `/shop` (`home`/`shop`) → `CatalogController@index`;
  `/knjiga/{slug}` (`book.show`) → `@show`. Slug je `products.slug`; stare
  `/product/{id}` ruta i `Admin\ProductController::publicIndex/publicShow`
  su **obrisani** (ID u URL-u više ne postoji; grep celog repo-a — Vue, PHP,
  blade, testovi — potvrđuje da nijedan link ne generiše `/product/{id}`;
  Cart čuva samo `{id, name, price, image}`, bez URL-a). Stari generisani
  `resources/js/ziggy.js` (nigde uvožen, sadržao je `product.details`) je
  **obrisan** — Ziggy dolazi iz `@routes` u `app.blade.php`. Katalog prikazuje samo
  aktivne proizvode koji **imaju** `books` red (proizvod bez knjige → 404 /
  nije u listi).
- Sva logika upita je u `app/Services/BookCatalog.php`: `filters()` (ispravne
  vrednosti prolaze, neispravne se tiho ignorišu umesto 422), `query()`,
  `paginate()` (`PER_PAGE = 12`, `withQueryString()`), `options()`. Nikad
  `->get()` cele liste knjiga. Upit ide preko `books` JOIN `products`, sortira
  po `products.name, books.id` (stabilna paginacija).
- Filteri (query string, svi opcioni, kombinuju se sa AND): `category` (slug,
  **uključuje sve potkategorije**), `author` (slug, bilo koja uloga), `publisher`
  (slug), `language` (`sr`), `script` (`Cyrl`/`Latn`), `format`, `price_min`,
  `price_max` (inkluzivno), `in_stock=1`. Nepoznat/neaktivan slug daje **0
  rezultata**, ne „ignoriši filter“. `in_stock` računa `stock IS NULL`
  (e-knjiga) kao dostupno.
- Opcije filtera (`options` prop) sadrže samo autore/izdavače/jezike iz
  aktivnih knjiga i aktivne kategorije kao stablo sa `depth`. Autori i
  izdavači su obični `<select>` — kad broj pređe par stotina, zameniti
  pretragom/typeahead-om.
- Cena stiže kao broj (SQLite) ili string (MariaDB) — frontend uvek koristi
  `formatPrice()` iz `resources/js/lib/bookLabels.js`, testovi porede
  numerički. Srpske labele formata/pisma/uloga su u istom fajlu.
- `Product.vue`: `stock === null` prikazuje „Dostupno“, ali je dugme
  **onemogućeno** (kao i za `stock = 0`) dok se ne reši NULL zaliha u
  `OrderController` (Faza 5, vidi Faza 2 gore). Korpa dobija samo
  `{id, name, price, image}` (`id` = `products.id`); Cart/Pinia store i
  checkout nisu dirani — refaktor na `{id, quantity}` je sledeći korak.
- Pretraga teksta (`books.search_text`, ćirilica/latinica) — vidi Faza 4
  niže. Sortiranje po ceni/datumu nije dodato.
- Testovi: `tests/Feature/Catalog/ShopCatalogTest.php`,
  `BookDetailTest.php`. Vizuelno provereno u pravom browseru (Chrome headless
  preko CDP-a, seed na privremenom SQLite-u): filteri, paginacija sa
  filterima, reset, dodavanje u korpu.

## Uvoz knjiga — CSV (Faza 3, deo 2)
- `php artisan catalog:import-books {csv}` — idempotentna komanda, ručno
  pokretanje, **ne ide u deploy pipeline**. Za svaki red kreira `Product` +
  `Book` (+ autori) isključivo kroz `BookService::create` (jedna transakcija
  po redu; loš red se preskoči, ostali se uvoze).
- CSV kolone: `title, subtitle, authors, publisher, isbn13, isbn10,
  published_year, pages, language, script, format, category, description,
  price`. `authors` je `"Ime Prezime:role"` spojeno sa `;` (npr.
  `Ime:author;Ime2:illustrator`); `price` je opciona — red bez cene dobija
  placeholder 999 EUR i komanda to prijavi po redu i sumarno na kraju.
  Prazna polja ostaju `NULL`, nikad prazan string.
- Zaliha je uvek placeholder 10 (CSV ne nosi taj podatak). Knjige se uvoze sa
  `is_active = true` odmah — **svesna odluka** (vežbovni projekat, ne prava
  produkcija; ne meša se sa Faza 0 pravilima za produkcioni kod).
- Kategorije/autori/izdavači: `findOrCreate` po slugu, postojeći se ne diraju.
  `--parent=<slug>` stavlja *nove* kategorije pod postojećeg roditelja
  (podrazumevano: bez roditelja, top-level).
- Duplikati: prvo po ISBN-u (`isbn13`); kad ISBN ne postoji ni na jednoj
  strani, po naslovu + autoru. Isti naslov sa različitim ISBN-om (drugo
  izdanje) tretira se kao druga knjiga.
- **Otvoreno pitanje pre sledećeg uvoza/Faze 4:** prva partija (44 knjige) je
  uvezena bez `--parent`, pa su nastale 7 novih *top-level* kategorija
  (`klasici-srpske-knjizevnosti`, `kratke-price`, `savremena-srpska-proza`,
  `krimitriler`, `poezija`, `knjige-za-decu-i-mlade`, `drama`) kao braća i
  sestre postojećoj top-level kategoriji `books`, ne kao njena deca. Treba
  odlučiti da li ih premestiti pod `books` (`parent_id`) pre nego što se
  katalog/navigacija oslanjaju na postojeće stablo kategorija.
- Testovi: `tests/Feature/Console/ImportBooksTest.php` (idempotentnost po
  ISBN-u i po naslovu+autoru, cena iz CSV-a vs. placeholder fallback, prazna
  polja, više autora/uloga, nevalidni redovi se preskaču bez rušenja ostatka,
  BOM, slug kolizije, `--parent`, `--dry-run`).

## Čišćenje legacy podataka i UI filter (Faza 3, deo 3)
- Kategorija **"Books"** (slug `books`) **NIJE prazna** — i dalje ima proizvode
  koji nikad nisu konvertovani u `books` red (otkriveno auditom pre čišćenja),
  a bar jedan od njih ima `order_items` (stvarne test porudžbine). Zato je
  **namerno isključena** iz `catalog:cleanup-legacy-categories` — korisnik ih
  ručno rešava kroz admin.
- `php artisan catalog:cleanup-legacy-categories` — ručna, idempotentna komanda
  (isti obrazac kao `catalog:convert-products-to-books`). Podrazumevano cilja
  `clothes-and-shoes`, `electronics`, `home-appliances` (`--category=slug`,
  ponovljivo, menja listu; `--dry-run` ne upisuje ništa).
  - Proizvod bez `order_items`/`stock_movements` → **briše se**.
  - Proizvod SA `order_items` ili `stock_movements` (FK `restrict`, isti
    obrazac kao kod knjiga) → **samo se deaktivira** (`is_active = false`),
    nikad ne briše — istorija porudžbina/zaliha se ne sme izgubiti.
  - Kategorija se briše samo ako joj ne ostane nijedan proizvod (aktivan ili
    deaktiviran); ako joj ostane, **kategorija se takođe deaktivira**
    (`is_active = false`), ne samo proizvodi. Bitno: `BookCatalog::
    categoryOptions()` filtrira dropdown filtera isključivo po
    `Category.is_active`, bez provere da li kategorija ima ijedan
    aktivan proizvod/knjigu — bez ove deaktivacije bi prazna legacy
    kategorija ostala vidljiva u filteru i posle čišćenja proizvoda.
  - **Ne ide u deploy pipeline** — pokreće se ručno na svakom okruženju
    posebno (isto pravilo kao ostale `catalog:*` komande).
  - Testovi: `tests/Feature/Console/CleanupLegacyCategoriesTest.php`
    (uključuje proveru da deaktivirana kategorija nestane iz
    `BookCatalog::options()`).
- `Shop.vue`: uklonjeno dugme "Primeni". Svi filteri se sada primenjuju
  automatski — select/checkbox filteri odmah (`@change`), cena
  (`price_min`/`price_max`) sa debounce-om od 400ms da kucanje ne šalje upit
  na svaki taster. Filter panel više nije `<form>` element (plain `<div>`),
  namerno — bez `<form>` Enter u cenovnim poljima nema šta da submit-uje
  (nema native page reload rizika).

## Korpa — refaktor na {product_id, quantity} (pre Faze 4)
- ✅ **REŠENO** — `resources/js/Stores/cart.js` je ranije čuvao pun snapshot
  proizvoda (`{id, name, price, image, quantity}`) i u `localStorage`-u i u
  `carts.items` (JSON) na serveru, tj. cena/naziv su se "zamrzavali" u
  trenutku dodavanja u korpu. Sada čuva isključivo `{product_id, quantity}`;
  naziv/cena/slika/zalihe se **uvek** učitavaju sa servera preko
  `GET /api/cart/products?ids[]=...` (`CartController::productDetails`,
  javno dostupno i gostu — Cart stranica ne zahteva auth) i keširaju u
  `cart.productDetails` (po `product_id`), nikad iz onoga što je korpa
  ranije sačuvala.
- `hydrate()` akcija poziva taj endpoint i tiho uklanja iz korpe stavke čiji
  `product_id` više ne postoji ili nije aktivan (`is_active`) — graceful, bez
  pada Cart/Checkout stranice. Pozivaju je `Cart.vue` (`onMounted`) i
  `Checkout.vue` (`onMounted`, pre popunjavanja `form.items`).
  `products.stock === null` (e-knjiga) i dalje znači "dostupno" (isto pravilo
  kao katalog, vidi Faza 3).
- `OrderController::store` nije menjan — već je pre ovog refaktora radio
  isključivo sa `items.*.id` (products.id) i `items.*.quantity`, cenu/naziv
  uvek čita iz baze (Faza 0). `Checkout.vue` mapira `cart.items`
  (`{product_id, quantity}`) u `{id, quantity}` samo pri slanju forme.
- Korpe sačuvane pre refaktora (stari oblik u `localStorage`-u ili u
  `carts.items` u bazi) se tiho normalizuju pri učitavanju
  (`normalizeItems()` u `cart.js` mapira `item.id` → `product_id` ako
  `product_id` nedostaje) — nema migracije, `carts.items` kolona je i dalje
  slobodan JSON (šema se ne menja).
- Testovi: `tests/Feature/Api/CartProductDetailsTest.php` (nepostojeći
  `product_id` i neaktivan proizvod se tiho izostavljaju, `stock === null` →
  dostupno, `ids` je obavezan parametar). Ručno provereno: `php artisan
  serve` + `GET /api/cart/products` protiv realne baze vraća samo aktivan
  proizvod i tiho izostavlja nepostojeći ID; `npx vite build` prolazi bez
  grešaka.
- ✅ **REŠENO (race condition otkriven pri code review-u)** — `Cart.vue` je u
  `onMounted` zvao **samo** `cart.hydrate()`, oslanjajući se da je
  `app.js` (`resources/js/app.js`) već učitao korpu
  (`loadFromBackend`/`loadFromLocalStorage`). Ali `app.js` te pozive radi
  **posle** `app.mount(el)`, tj. **posle** što se `onMounted` inicijalne
  stranice već izvršio (Vue izvršava `mounted` hook-ove dece sinhrono unutar
  `mount()`). Na hladnom loadu direktno na `/cart` (ili kad je logovan
  korisnik pa je `loadFromBackend()` async), `hydrate()` je video praznu
  `cart.items`, odmah odustajao (`productDetails` ostaje `{}`), a pošto se
  ne re-triggeruje automatski kad `items` kasnije stigne — korisnik je video
  stavke u korpi sa cenom "0,00 €" dok se stranica ponovo ne mount-uje (npr.
  SPA navigacija na drugu stranicu pa nazad, gde je `cart.items` već
  popunjen iz prethodnog mount-a). **Nije** bilo Vue devtools ni skriveni keš
  — Pinia state se ne sinhronizuje nazad iz `localStorage`-a same od sebe;
  ručna izmena `localStorage`-a u browseru nema efekta dok se ne desi hard
  reload, i tada aktivira isti race ako je `/cart` prva stranica koja se
  učita.
  Popravka: `Cart.vue` i `Checkout.vue` sada u `onMounted`-u rade `await
  cart.loadFromBackend()` (interno pada nazad na `loadFromLocalStorage()` za
  gosta — nema više duple if/else grane po stranici) **pa tek onda** `await
  cart.hydrate()`. `pinia-plugin-persistedstate` je u `package.json`
  (dependencies) ali se **nigde ne koristi** (nema `pinia.use(...)` u
  `app.js`) — nije uzrok, samo neiskorišćena zavisnost (isti obrazac kao
  `stripe/stripe-php`, vidi Stack).
  Test: `resources/js/Stores/cart.test.js` (novi `vitest` setup — projekat do
  sada nije imao JS test runner; `vitest.config.js` ručno dodaje `@` alias
  jer ovde ne radi `laravel-vite-plugin`, vidi Stack). Testovi direktno
  reprodukuju race (hydrate pre load-a ⇒ `productDetails` ostaje `{}` posle
  kasnijeg load-a ⇒ `totalAmount === 0`) i potvrđuju ispravan redosled
  (load pa hydrate ⇒ cena sa servera, nikad iz stare "zamrznute" korpe).
  `npm run test` (`vitest run`) — dodato u `package.json` scripts i u CI
  (`.github/workflows/deploy.yml`, `tests` job, odmah posle `npm run build`
  — pre `composer test`, tako da JS regresija zaustavi pipeline pre nego što
  se PHP testovi i deploy uopšte pokrenu).
- ✅ **REŠENO (drugi, POVEZAN ali nov race, otkriven posle gore opisane
  popravke)** — `/cart` je ispravno prikazivao cenu, ali odmah posle **punog**
  (ne-SPA) prelaska na `/checkout` (npr. klik na obično `<a href="/checkout">`
  dugme na Cart.vue, ili hard refresh direktno na `/cart`/`/checkout`) cena je
  za ULOGOVANOG korisnika pokazivala 0,00 €. Uzrok nije bio u `cart.js` (ta
  logika je već bila ispravna), nego u `app.js`: `authStore.init()` se pozivao
  TEK POSLE `app.mount()`, dok se `onMounted` stranice (Cart.vue/Checkout.vue)
  izvršava SINHRONO unutar `app.mount()` (Vue mounted-hook ponašanje dece, isti
  mehanizam koji je već dokumentovan gore) - dakle PRE `authStore.init()`. Na
  svakom punom učitavanju stranice `cart.loadFromBackend()` je zato video
  `authStore.user === null` i ulogovanog korisnika tretirao kao gosta,
  učitavajući praznu/zastarelu `localStorage` korpu umesto prave korpe sa
  servera. Na SPA navigaciji (Inertia `<Link>`) buga nema, jer `authStore`
  ostaje ispravno inicijalizovan od prvog (punog) učitavanja te iste
  browser-tab sesije - zato je `/cart` (obično dostignut preko `<Link>`)
  izgledao ispravno, a `/checkout` (dostignut preko običnog `<a>`, tj. punog
  reload-a) nije.
  Popravka: `app.js` sada zove `authStore.init(props.initialPage.props.auth?.user
  ?? null)` (i inicijalno učitavanje korpe) PRE `app.mount(el)`, ne posle.
  `props.initialPage` je Inertia-in initial page objekat dostupan sinhrono u
  `createInertiaApp`-ovom `setup()`-u, za razliku od `usePage()` čiji
  modul-level `page` ref popunjava tek Inertia-ina `App` komponenta u SVOM
  `setup()`-u (tj. i dalje unutar `app.mount()`, ali POSLE Cart.vue/Checkout.vue
  onMounted-a - zato `usePage()` ovde nije bio opcija). `auth.js`:
  `init(initialUser = null)` sad prima korisnika eksplicitno umesto da ga sam
  sinhrono čita iz `usePage()`; `usePage()` se i dalje koristi unutra, ali samo
  za `watch()` koji hvata KASNIJE promene (login/logout/switch tokom SPA
  sesije), ne za inicijalnu vrednost.
  Test: `resources/js/Stores/auth.test.js` - jedan test pinuje STARI (bagovan)
  redosled (`app.mount()` pa `authStore.init()`) i dokumentuje tačan simptom
  (korpa/cena ostaju na 0 za ulogovanog korisnika), drugi potvrđuje ispravljen
  redosled (`authStore.init()` pa `app.mount()`) daje tačnu cenu. Pun suite:
  `php artisan test` (348 passed) + `npm run test` (vitest, 7 passed) +
  `npx vite build` prolaze bez grešaka.
- ✅ **REŠENO (treći, POVEZAN race u istom `authStore.init()`, otkriven pri
  ručnoj browser proveri Faze 6 — sama gornja popravka ga je uvela, ne
  redizajn)** — posle gornje popravke, `authStore.init()` se zove PRE
  `app.mount()`, tj. PRE nego što Inertia-ina `App` komponenta ikad postavi
  svoj interni `page` ref (to se dešava tek u NJENOM `setup()`-u, TOKOM
  `app.mount()`). Unutar `init()`, `watch(() => page.props.auth?.user, ...)`
  je i dalje čitao `usePage()` (za KASNIJE promene, vidi gore) - a Vue-ov
  `watch()` **sinhrono evaluira getter jednom odmah pri samom pozivu**, radi
  prikupljanja zavisnosti, bez obzira na `{ immediate: false }`. U tom
  trenutku je `page.props` `undefined` (Inertia ga još nije postavila), pa
  `page.props.auth` (bez `?.` posle `props`) baca `TypeError: Cannot read
  properties of undefined (reading 'auth')`. Taj throw izlazi iz `watch()`-a
  netaknut jer u tom trenutku ne postoji nijedna Vue komponenta/instance
  (poziv dolazi iz `createInertiaApp`-ovog plain JS `setup()` callback-a, ne
  iz komponente) koja bi ga uhvatila preko `errorCaptured`/`app.config.
  errorHandler` — Vue-ov default `logError` u dev modu tada **baca dalje**
  (`throwInDev` podrazumevano `true`), što zaustavlja ceo `createInertiaApp`
  `setup()` PRE poziva `app.mount(el)`. Rezultat: bela prazna strana na
  **svakom** punom (ne-SPA) učitavanju bilo koje stranice, ne samo na
  Checkout-u — potpuno nezavisno od Faze 6 (reprodukovano identično na
  `bc525eb`/`develop`, pre bilo kakvog brendiranja).
  Popravka: getter promenjen u `page.props?.auth?.user` (dodat `?.` posle
  `props`) — bezbedno vraća `undefined` dok `page.value` ne bude postavljen,
  a `watch` ostaje ispravno pretplaćen (čita isti reaktivni `page` ref preko
  `computed()`-a unutar `usePage()`), pa se okine čim Inertia popuni pravu
  stranicu.
  **Drugi, suptilniji problem otkriven pri istoj proveri:** kad se watch prvi
  put stvarno okine (Inertia popuni `page.props` tokom `app.mount()`),
  Vue-ova interna "stara vrednost" za watch callback je `undefined` (iz gore
  opisane bezbedne prve evaluacije) — za VEĆ ulogovanog korisnika na punom
  reload-u, callback je to tumačio kao tranziciju `null -> user`, tj. lažni
  **SCENARIO 2 (LOGIN)**, iako se korisnik samo hidrira, ne prijavljuje. To bi
  na svakom punom reload-u nepotrebno duplo gađalo `/api/cart`, i - gore - da
  je slučajno postojala zaostala gost-korpa u `localStorage`-u, pogrešno bi
  je mergovalo (`mergeGuestCartOnLogin()`) u nalog već ulogovanog korisnika na
  svakom reload-u. Popravka: callback sad poredi `newUserId` protiv
  `this.previousUserId` (store-ovo sopstveno stanje, već tačno postavljeno iz
  `initialUser` na početku `init()`-a), ne protiv `watch()`-ovog internog
  `oldValue` parametra — za već poznatog korisnika ovo sad ispravno pada u
  SCENARIO 4 (isti korisnik, bez akcije), ne SCENARIO 2.
  Oba problema reprodukovana i potvrđena u pravom browseru (headless Chrome
  + CDP, autentikovana sesija preko privremenog test naloga, obrisanog posle
  provere) — **ne samo automatskim testovima**: stari statički mock
  `usePage()` u `auth.test.js` (`() => ({ props: { auth: {} } })`) je
  slučajno sakrio prvi bag jer `page.props` u testu nikad nije bio
  `undefined`. Mock zamenjen pravim Vue `ref()`-om (počinje kao `undefined`,
  `__setInertiaPageForTest()` simulira trenutak kad Inertia popuni stranicu
  tokom `app.mount()`) — sad realno modeluje pravi tajming, i pada na starom
  getteru identičnim stack trace-om kao u browseru (provereno ručno: vraćen
  stari getter privremeno, novi testovi pucaju, pa vraćen fix). Testovi:
  `resources/js/Stores/auth.test.js` (novi `describe` blok "REGRESIJA...").
  Pun suite (na ovoj, odvojenoj grani - vidi ispod): `php artisan test`,
  `npm run test` (vitest, 10 passed), `npx vite build` prolaze bez grešaka.
  **Napomena:** ovaj fix ide u SVOJ PR ka `develop` (ne u Faza 6 brending
  granu) jer bug postoji nezavisno od redizajna — potvrđeno identičnom
  reprodukcijom na `bc525eb` (stanje `develop`-a pre Faze 6).

## Pretraga — ćirilica/latinica (Faza 4)
- `app/Support/SearchText.php::normalize()` — malo slovo + ćirilica
  preslovljena u latinicu + dijakritika uklonjena (č/ć→c, š→s, ž→z, đ→dj;
  `dž`/`џ` ispadnu kao `dz` automatski preko `ž→z`, bez posebnog obrasca za
  dvoslove) + interpunkcija zamenjena razmakom i kolabirana. "Дина" i "Dina"
  daju isti rezultat. Čist string-mapping (ćirilična tabela + `strtr`), bez
  ICU/intl ekstenzije.
- `books.search_text` (kolona je postojala od Faze 1, ali se nije punila) se
  računa iz naslova (`products.name`), podnaslova, originalnog naslova,
  izdavača i svih autora (bez obzira na ulogu), preko
  `app/Support/BookSearchIndexer.php::compute()`.
- Punjenje ide preko četiri observera, registrovana u
  `AppServiceProvider::boot()`:
  - `app/Observers/BookObserver.php` (`Book::saved`) — glavni, računa i
    upisuje `search_text` za tu knjigu.
  - `app/Observers/ProductObserver.php` (`Product::saved`) — odbrambeni
    sloj za slučaj da se `Product` ikad sačuva mimo `BookService`-a.
  - `app/Observers/AuthorObserver.php` / `PublisherObserver.php`
    (`saved`) — kad se **promeni ime/slug autora ili izdavača**, prolaze
    kroz `$author->books()` / `$publisher->books()` sa `chunkById(100, ...)`
    i rade `$book->touch()` po knjizi, što okida `BookObserver` da
    preračuna `search_text` te knjige. `chunkById` bez `select()`-a —
    učitava **pun red** (svaki batch od 100), namerno: raniji pokušaj sa
    `select('books.id')` je bio bag — `BookSearchIndexer` čita
    `product_id`/`publisher_id`/`subtitle`/`original_title` direktno sa
    `Book` instance, pa bi ograničen select ostavio te kolone `NULL` i
    obrisao ih iz `search_text`-a posle `touch()`-a (uhvaćeno testom, vidi
    ispod). `chunkById` sprečava da se sve knjige (izuzetno) plodnog
    autora učitaju odjednom u memoriju.
  Upis u bazu ide preko query buildera (`Book::query()->whereKey()->update()`),
  ne preko `$book->save()`, da se izbegne rekurzivno okidanje observera.
  **Autori na knjizi su poseban slučaj:** menjaju se preko pivot tabele
  (`BookService::replaceAuthors` — `detach()`/`attach()`), što ne okida
  `Book`-ov `saved` event. Zato `BookService::create()`/`update()` posle
  `replaceAuthors()` rade eksplicitni `$book->touch()` da observer preračuna
  `search_text` i sa finalnim autorima. **Posledica:** ako se autori ikad
  vežu mimo `BookService` (npr. direktno `$book->authors()->attach()`, kao u
  nekim starijim testovima), `search_text` se neće osvežiti dok se knjiga
  ponovo ne sačuva/touch-uje — nije problem u aplikaciji jer je `BookService`
  jedini put pisanja (vidi Faza 2), ali treba imati na umu u testovima.
- `php artisan catalog:reindex-search-text` — ručna, idempotentna komanda
  (isti obrazac kao ostale `catalog:*`), **ne ide u deploy pipeline**.
  Backfill za knjige upisane pre Faze 4 (svih 44 uvezenih), ali bezbedno da
  se pokrene bilo kada (preskače knjige čiji je `search_text` već tačan).
  `--dry-run` samo ispisuje šta bi se promenilo.
- Pretraga: `BookCatalog` dobija filter `search` (nova stavka u
  `FILTER_KEYS`), normalizuje upit istim `SearchText::normalize()` i radi
  prost `WHERE books.search_text LIKE '%...%'` (vrednost/`%`/`_` escapovani).
  Namerno **ne** `whereFullText()`/`fullText()` — ne radi na SQLite, ponaša
  se drugačije na MariaDB (već zabranjeno ranije u ovom fajlu). Prazan ili
  samo-razmaci upit se tiho ignoriše (ceo katalog, paginirano), kombinuje se
  sa ostalim filterima kao AND (isti obrazac kao ostali filteri u Faza 3).
- `Shop.vue`: novo pretraga polje (`form.search`) iznad filter panela,
  debounce 400ms (deli isti tajmer sa cenom) + `@keyup.enter` za trenutnu
  pretragu. Ne zahteva `<form>` (isti razlog kao cena — vidi Faza 3, deo 3).
- Testovi: `tests/Unit/SearchTextTest.php` (normalizacija — ćirilica/latinica
  isti rezultat, dijakritika, interpunkcija, null/prazan string),
  `tests/Feature/Catalog/BookSearchIndexTest.php` (create/update preko
  `BookService` popunjava i osvežava `search_text`; izmena imena autora ili
  naziva izdavača osvežava `search_text` svih njegovih knjiga bez ručnog
  reindex-a, uključujući regresioni test sa 120 knjiga jednog autora —
  preko granice `chunkById(100)` — i test da se naslov/podnaslov ne izgube
  posle takve izmene),
  `tests/Feature/Console/ReindexSearchTextTest.php` (backfill, `--dry-run`,
  idempotentnost), `tests/Feature/Catalog/ShopCatalogTest.php` (pretraga na
  ćirilici pronalazi knjigu unetu na latinici i obrnuto, dijakritika/velika
  slova, prazan upit vraća sve, upit bez rezultata ne baca grešku,
  kombinovanje sa drugim filterima). Dve postojeće `BookModelTest` provere
  su ažurirane — `search_text` više nije `NULL` posle create-a (observer ga
  odmah popuni), pa testovi sad proveravaju da je popunjen i da mass-assign
  pokušaj (`fill(['search_text' => ...])`) ne prođe, umesto da provere `NULL`.
  Ručno provereno na realnoj dev bazi (44 uvezenih knjiga, `php artisan
  serve` + Inertia JSON odgovor): pretraga na ćirilici (`Андрић`, `Дервиш`)
  i latinici (`seobe`, `crnjanski`) vraća očekivane naslove; `npx vite build`
  prolazi bez grešaka.

## Inventar — stock_movements (Faza 5, poslednja faza knjižare)
- `stock_movements` tabela postoji od Faze 1, ali logika je nikad nije
  punila. Sva logika upisa/povrata zaliha sad ide isključivo kroz
  `app/Services/InventoryService.php` (isti obrazac kao `BookService` —
  centralni servis, ne piše se direktno po kontrolerima), uvek uz
  odgovarajući `stock_movements` red u istoj `DB::transaction` kao i sama
  promena `products.stock`.
- **Kreiranje porudžbine** — `OrderController::store` (Faza 0) i dalje
  atomski umanjuje zalihu (`WHERE stock >= :q`); dodat je upis jednog
  `stock_movements` reda po stavci (`delta = -quantity`, `reason = 'order'`,
  `order_id` postavljen, `user_id = Auth::id()` — `NULL` za gosta), unutar
  iste transakcije.
- **Otkazivanje/neuspelo plaćanje** — proveren je ceo tok: postoji samo
  PayPal cancel/fail (`PayPalController::cancel/success`); **COD nema
  cancel/fail rutu uopšte** (COD porudžbina ide direktno na
  `order.cod.success`, nema toka koji bi je označio kao otkazanu/neuspelu),
  pa tamo nije ni bilo šta da se popravi. PayPal `cancel()` i `success()`
  (catch grana — capture nije `COMPLETED`) ranije **nisu vraćali zalihu** —
  to je bio propust koji je ova faza zatvorila.
  - `InventoryService::restoreStock(Order $order, string $reason, string $newStatus)`
    vraća zalihu (`+quantity` po stavci), upisuje `stock_movements`
    (`reason` = `'cancel'` ili `'payment_failed'`) i **u istoj transakciji**
    postavlja finalni status porudžbine.
  - **Bitno (otkriveno pri code review-u tokom pisanja):** status porudžbine
    se MORA postaviti unutar iste `lockForUpdate()` transakcije kao povrat
    zaliha, ne posle (u pozivaocu). Prvobitna verzija je vraćala zalihu
    unutar zaključane transakcije, a status je upisivala posle, kao
    odvojeni `$order->update()` poziv — ostavljalo je prozor u kom je drugi
    paralelni zahtev na istu porudžbinu (npr. dupli klik na cancel) i dalje
    video status kao "pending" i dupli put vraćao zalihu. Ispravljeno tako
    da ceo blok (guard + povrat + status) bude jedna zaključana transakcija
    (isti princip kao `WHERE stock >= :q` guard iz Faze 0, samo nad
    `orders` redom umesto `products` redom). Guard: ako je porudžbina već
    `cancelled`/`failed`, povrat se preskače (idempotentno).
- **Ručna dopuna (admin)** — `POST /admin/books/{book}/restock`
  (`BookController::restock`, dugme "Dopuni" u `Admin/Books/Index.vue`,
  modal `resources/js/Components/Admin/RestockForm.vue` sa količinom i
  opcionom napomenom). Upisuje `stock_movements` (`reason = 'manual'`,
  `user_id` = admin koji je izvršio akciju, `note` opciono). `stock = NULL`
  (e-knjiga, neograničena zaliha) se **ne dopunjava** — kontroler odbija
  zahtev pre poziva servisa (nema smisla "dopuniti" neograničenu zalihu, a
  upis bi ostavio zbunjujući `stock_movements` red).
- **Niska zaliha (admin)** — nije napravljena posebna stranica; `GET
  /admin/books?low_stock=1` (opciono `&threshold=N`, default 5) filtrira
  postojeću listu knjiga na `stock IS NOT NULL AND stock < threshold`,
  sortirano rastuće po `stock`. Bez filtera lista ostaje kao pre (sort po
  nazivu), ali redovi ispod praga su i dalje vizuelno istaknuti
  (žuta pozadina) — threshold se uvek računa i šalje kao prop, filter samo
  suzuje listu. `stock = NULL` se nikad ne tretira kao "nizak".
- **OTVORENO PITANJE #5 (stock = NULL za e-knjige) — NIJE REŠENO,
  svesna odluka.** `WHERE stock >= :q` guard u `OrderController::store`
  i dalje ne dozvoljava kupovinu `stock = NULL` proizvoda (SQL poređenje
  sa `NULL` je uvek nepoznato/false). Pošto e-knjige nisu u opsegu
  (vidi "Šta je ovaj projekat" gore), ovo ostaje dokumentovano ograničenje
  — rešava se tek kad/ako e-knjige uđu u opseg, zajedno sa ostatkom te
  odluke (cena, format isporuke), ne parcijalno sad dok se dira ovaj deo
  koda.
- Testovi: `tests/Feature/OrderStoreTest.php` (stock_movements red po
  stavci porudžbine, ispravan `user_id` za gosta/ulogovanog korisnika),
  `tests/Feature/PayPalPaymentTest.php` (cancel i payment_failed vraćaju
  zalihu i upisuju stock_movements; dvostruko otkazivanje ne duplira
  povrat — race-guard test istog stila kao Faza 0
  `dve_porudzbine_za_poslednji_primerak_samo_jedna_uspe`),
  `tests/Feature/Admin/BookRestockTest.php` (uspešna dopuna, validacija
  količine, e-knjiga sa `stock = NULL` odbijena), `tests/Feature/Admin/
  BookLowStockFilterTest.php` (filter/threshold/sort, e-knjiga isključena),
  `tests/Feature/Admin/AdminAccessTest.php` (nova `books.restock` ruta
  dodata u centralnu listu — gost/ne-admin provere). Pun suite:
  `php artisan test` (348 passed) + `npm run test` (vitest, 5 passed) +
  `npx vite build` prolaze bez grešaka.

## Dizajn — vizuelni redizajn brenda (Faza 6, korak 1: temelji)
Bookstore (Faze 0-5) je zatvoren. Redizajn je **nezavisan od backend logike**
i ide stranicu-po-stranicu; ovaj korak je samo infrastruktura (boje, fontovi,
logo, brend ime) — building blockovi za sledeće korake. **Nije dirano:**
sadržaj/layout `Shop.vue`, `Product.vue`, `Cart.vue`, `Checkout.vue`, admin
stranice, Breeze auth stranice (samo logo/brend ime u `AuthenticatedLayout.vue`
i `GuestLayout.vue` je zamenjen — to je deo brendiranja, ne redizajna tih
stranica).
- **Ime brenda: "Ex Libris"** (bilo "LaraVueShop"/"Laravel"). Izvor je
  `APP_NAME` env varijabla (`.env`, `.env.example`) — `<title>` u
  `app.blade.php` i `MAIL_FROM_NAME`/`VITE_APP_NAME` je prate automatski.
  `PayPalGateway::createOrder` (`brand_name` prikazan na PayPal checkout
  stranici) je imao odvojen hardkodovan fallback `env('APP_NAME',
  'LaraVueShop')` — fallback promenjen na `'Ex Libris'` (samo string default,
  logika kreiranja PayPal porudžbine nije dirana).
  ✅ **REŠENO (poseban hotfix, posle merge-a Faze 6 — CI je pukao na
  dotenv parse grešci pre nego što je ovo stiglo da se popravi):**
  `.env.example` je bio zatečen sa dva spojena bloka env varijabli bez
  razdvajajućeg newline-a (linija 9: `APP_FAKER_LOCALE=en_USAPP_NAME=...`).
  Uzrok, potvrđen kroz `git log --follow -p -- .env.example`: commit
  `2f67387` ("Fix DababaseSeeder class name") je ubacio pravu, prilagođenu
  konfiguraciju projekta (mysql, `laravue_shop` baza, pravi mail server,
  PayPal placeholder-i) NASRED originalnog, generičkog Laravel
  `.env.example` stub-a (iz `Initial commit`) — bez uklanjanja ostatka tog
  stub-a, koji je ostao zalepljen odmah iza (drugi, potpuno redundantan
  blok: `sqlite` baza, `log` mailer, generički `hello@example.com`, bez
  PayPal sekcije — ništa jedinstveno projektu). Kad je Faza 6 menjala
  `APP_NAME=Laravel` → `APP_NAME="Ex Libris"` preko `replace_all`, promena
  je ispravno pogodila OBA doslovna pojavljivanja stringa — uključujući ono
  zalepljeno usred linije 9 — ali dodavanje navodnika oko vrednosti
  (`"Ex Libris"`, zbog razmaka) je baš na tom mestu učinilo liniju
  dovoljno "čudnom" da PHP dotenv parser prijavi "Encountered unexpected
  whitespace" umesto da je tiho (pogrešno) parsira kao ranije.
  **Popravka:** ceo drugi (redundantan) blok obrisan — zadržan samo prvi,
  ispravan/prilagođen profil (mysql/pravi mail/PayPal, sad sa
  `APP_NAME="Ex Libris"` na vrhu), sa urednim newline-om na kraju. Fajl
  ide sa 135 na 69 linija, 51 ključ (bez duplikata).
  **Provereno tačno onako kako CI radi**
  (`.github/workflows/deploy.yml`): `cp .env.example .env` pa pravi
  `composer install --no-interaction --prefer-dist` (ne samo test env) —
  `post-autoload-dump` hook pokreće `php artisan package:discover`, što
  bootuje aplikaciju i parsira `.env`; prošlo bez greške. Dodatno
  provereno direktno preko `Dotenv\Dotenv::parse()`/`createImmutable()`.
  Lokalni `.env` (gitignored, nije deo ovog fajla/PR-a) je posle ovog testa
  vraćen na svoj pravi sadržaj iz backupa - test nije trajno izmenio ništa
  van `.env.example`.
- **Paleta** — CSS varijable u `resources/css/app.css` (`:root`, prefiks
  `--brand-*`, **ne** HSL triplet kao postojeći shadcn `--background`/
  `--foreground` tokeni, jer je paleta zadata u heksadecimalnom zapisu):
  `--brand-header-bg: #E4CBAE`, `--brand-header-text: #6B4423`,
  `--brand-header-text-muted: #9C7A54`, `--brand-accent: #D9713A`,
  `--brand-accent-hover: #E8935A`, `--brand-page-bg: #FFFCF8` (izabrana
  topla varijanta ponuđene alternative, ne čisto `#FFFFFF`),
  `--brand-card-bg: #F6EEE3`, `--brand-text-primary: #2E241C`,
  `--brand-text-secondary: #8A7461`. Samo u `:root` — **nema** `.dark`
  varijante (brend boje su za sada fiksne, dark mode za shop nije dizajniran).
  U `tailwind.config.js` mapirane pod `colors.brand` (namerno **odvojeno** od
  postojećeg shadcn `accent` tokena da ga ne pregazi — shadcn komponente kad
  se dodaju i dalje koriste `accent`/`accent-foreground`):
  `bg-brand-header`, `text-brand-header-text`, `text-brand-header-muted`,
  `bg-brand-accent`, `hover:bg-brand-accent-hover`, `bg-brand-page`,
  `bg-brand-card`, `text-brand-text-primary`, `text-brand-text-secondary`.
  Nijedna postojeća stranica još ne koristi ove klase (samo definisane, čekaju
  sledeće korake redizajna).
- **Fontovi** — dodat Google Font **"Lora"** (serif) preko `fonts.bunny.net`
  (isti CDN/obrazac kao postojeći Figtree, `app.blade.php`: jedan
  `<link>` sa `family=figtree:400,500,600|lora:400,500,600,700`). Ovo
  okruženje nema `frontend-design` skill dostupan za CSP proveru — CDN je
  isti već korišćeni domen (`fonts.bunny.net`), nema postojećeg CSP header-a
  u aplikaciji koji bi to blokirao (provereno, nema `Content-Security-Policy`
  nigde u kodu). `tailwind.config.js`: `fontFamily.serif = ['Lora',
  ...defaultTheme.fontFamily.serif]` (klasa `font-serif`); `fontFamily.sans`
  (Figtree, telo/dugmad/forme) nije menjan.
- **Logo** — `resources/js/Components/Logo.vue`. Ikona (linijski crtež,
  `stroke`, ne `fill`) je lucide-vue-next-ov `BookOpen` (projekat već ima
  `lucide-vue-next` instaliran i konvenciju "nove komponente koriste lucide",
  vidi Stack) + wordmark "Ex Libris" u `font-serif` (Lora). Jedan prop,
  `color` (default `currentColor`) — postavlja CSS `color` na wrapper, ikona
  nasleđuje preko `stroke="currentColor"` (lucide default), tekst isto preko
  `color`. Veličina (ikona + tekst) je u `em` jedinicama — skalira se preko
  `font-size`/Tailwind text-size klase na roditelju (npr. `class="text-xl"`
  na `<Link>` koji sadrži `<Logo />`), **nema poseban `size` prop** (nije
  traženo, izbegnuta dodatna površina API-ja). Ovako je ožičen u:
  - `AuthenticatedLayout.vue` (header, `Link` sa `class="text-xl text-gray-800"`)
  - `GuestLayout.vue` (Breeze auth stranice, `Link` sa
    `class="text-4xl text-gray-500"`) — samo logo/brend zamenjen, layout
    kartice/forme nije diran.
  Stari `ApplicationLogo.vue` (img tag ka `public/images/favicon_DD_WebApps.png`,
  nepovezan raster brend) je **obrisan** zajedno sa slikom — posle zamene
  nije imao više nijednog korišćenja (provereno grep-om).
- **Favicon** — `public/favicon.svg` (ista `BookOpen` putanja, `stroke`
  `#6B4423` = `--brand-header-text`), referenciran u `app.blade.php` kao
  primarni (`<link rel="icon" type="image/svg+xml">`). Stari `public/favicon.ico`
  je ostavljen kao `rel="alternate icon"` fallback za stariji browser koji ne
  podržava SVG favicon — **nije regenerisan** iz nove ikone (ovo okruženje
  nema ImageMagick/`convert` ni PHP GD ekstenziju, nema alata da se
  rasterizuje SVG → `.ico`). **Otvoreno:** kad bude dostupan alat za
  rasterizaciju, generisati odgovarajući `favicon.ico` iz iste ikone da i
  stariji browseri dobiju brend, ne generički default.
- Testirano: `npx vite build`, `npm run test` (vitest) i `php artisan test`
  prolaze bez grešaka; `Logo.vue` proveren vizuelno u oba konteksta (svetla
  pozadina header-a, svetla pozadina guest kartice — nema još tamne pozadine
  gde bi se testirao `color` prop na drugačijem primeru).

## Dizajn — header/navigacija (Faza 6, korak 2)
Cilj: nav traka stvarno koristi `brand-*` tokene iz koraka 1 (ne samo
`Logo.vue`, koji je već bio ožičen). Samo `AuthenticatedLayout.vue` +
pomoćne komponente koje isključivo ona koristi (`NavLink.vue`,
`ResponsiveNavLink.vue`) — nijedna Shop/Product/Cart/Checkout/admin/Breeze
auth **stranica** (sadržaj ispod nav trake) nije dirana.
- **Nav pozadina** `bg-white` → `bg-brand-header`; border `border-gray-100`
  → `border-black/10` (brand tokeni su hex vrednosti preko CSS varijabli,
  ne HSL, pa Tailwind-ov `/opacity` modifikator na njima nije pouzdan — vidi
  napomena u koraku 1 — zato je border ostao na statičkoj `black/10` boji,
  ne npr. `border-brand-header/10`).
- **Logo link, dropdown korisnika, hamburger, mobilni meni** — svi stari
  `gray-*`/`indigo-*` prelaze na `brand-header-text`/`brand-header-muted`/
  `brand-card`.
- **`NavLink.vue`/`ResponsiveNavLink.vue`** (koristi ih isključivo
  `AuthenticatedLayout.vue` — provereno grep-om, bezbedno menjati): default
  (neaktivno) stanje `brand-header-muted` → hover `brand-header-text`;
  aktivno stanje `brand-header-text` + `border-brand-accent`
  (desktop)/`bg-brand-card` (mobile). Ovo je namerno "tiši" izgled — koriste
  ga i customer-facing (Shop) i admin linkovi (Categories/Products/Books/
  Authors/Publishers/Orders) podjednako.
- **Shop link ističe se** preko dodatne `class="font-semibold"` na tom
  jednom `<NavLink>` (Vue automatski spaja `class` atribut sa NavLink-ovom
  internom `:class` na root `<Link>` elementu, pošto `inheritAttrs` nije
  isključen) — admin linkovi ostaju na default (regular weight) stilu
  NavLink-a, vizuelno tiši od Shop-a, kako je traženo. Nijedan CSS specifično
  za admin nije dodat — "tišina" dolazi iz IZOSTANKA `font-semibold`, ne iz
  posebne admin klase.
- **Cart bedž** (broj stavki): `bg-[#FF2D20]` (stari Laravel-crveni,
  van palete) → `bg-brand-accent` + `ring-2 ring-brand-page` (beličasti
  prsten). Razlog za prsten: sam `brand-accent` (#D9713A) i `brand-header-bg`
  (#E4CBAE) su obe tople/narandžaste nijanse — izračunat kontrast boja
  bedž-na-pozadini je nizak (~2.1:1), sličan starom crvenom (~2.4:1), pa
  golo popunjavanje ne bi pouzdano "iskočilo" iz pozadine. Prsten rešava to
  nezavisno od tačne nijanse. Vizuelno potvrđeno (headless Chrome + CDP,
  screenshot posle stvarnog dodavanja knjige u korpu kao gost) — bedž se
  jasno vidi na header pozadini.
- **Search bar**: `bg-gray-100`/`focus:ring-[#FF2D20]` → `bg-brand-page`/
  `focus:ring-brand-accent`/`focus:border-brand-accent`; ikonica lupe
  `text-gray-400` → `text-brand-header-muted`.
- **Login/Register dugmad**: `text-gray-700`/`bg-[#FF2D20]` →
  `text-brand-header-muted`/`bg-brand-accent` + `hover:bg-brand-accent-hover`.
- **Sitan fix (konzola, otkriven pri Faza 6 koraku 1 proveri)**:
  `resources/js/app.js` je registrovao samo `faShoppingCart` u
  `library.add(...)`, a nav search bar koristi `['fas', 'search']` — otud
  "Could not find one or more icon(s)" greška u konzoli na SVAKOJ stranici
  koja renderuje `AuthenticatedLayout`. Dodat `faSearch` u isti `library.add`
  poziv. Grep za `font-awesome-icon`/`FontAwesomeIcon` potvrđuje da su to
  jedine dve upotrebe u projektu (`search`, `shopping-cart`) — nema drugih
  neregistrovanih ikona.
- `bg-gray-50` na spoljnom wrapper `<div>` (pozadina ISPOD nav trake, gde
  sadržaj stranice sedi) i `<header v-if="$slots.header" class="bg-white
  shadow">` (slot za naslov stranice, npr. "Profile") su **namerno
  nedirani** — to je sadržaj stranice/page shell, ne nav traka, van opsega
  ovog koraka.
- **OTVOREN, ODVOJEN PROBLEM (prijavljen, ne popravljen ovaj korak)**:
  `AuthenticatedLayout.vue` (nav traka, koristi je Shop/Product/Cart/
  Checkout/Dashboard/Profile/Orders/sve admin stranice) i `GuestLayout.vue`
  (Breeze auth forme: Login/Register/ForgotPassword/ResetPassword/
  VerifyEmail/ConfirmPassword) **nisu ista komponenta i ne dele nav traku**
  — `GuestLayout.vue` nema NIKAKVU navigaciju, samo centrirano `Logo` iznad
  kartice na `bg-gray-100`. Potvrđeno grep-om (`AuthenticatedLayout`: 27
  fajlova, `GuestLayout`: 7 fajlova, bez preklapanja) i vizuelno (headless
  Chrome screenshot `/login` — nema nav trake). Ovo je pre-postojeća
  arhitektura (nije unela Faza 6), ali znači da "ista nav svuda" ne važi
  danas — odluka da li Breeze auth stranice dobiju punu nav traku ili
  ostaju na minimalnom centriranom layoutu čeka dogovor, nije doneta ovde.
- Testirano: `npx vite build`, `npm run test` (vitest), `php artisan test`
  prolaze bez grešaka; konzola bez icon grešaka (headless Chrome + CDP,
  provereno na `/shop`, `/knjiga/{slug}`, `/login`). Vizuelno provereno
  (screenshot): Shop, Product, Cart (uključujući bedž posle stvarnog dodavanja
  u korpu), i nav kao admin (Shop bold/istaknut, admin linkovi tiši,
  korisnički dropdown čitljiv) — sve preko privremenih test naloga
  (`cdp-nav-check@example.com` i sl.), obrisanih posle provere.

## Dizajn — kupovni katalog: Shop.vue i Product.vue (Faza 6, korak 3)
Logika (filteri, debounce, pretraga, paginacija, slug rute, cart, stock
prikaz) **nije dirana** — samo izgled/markup. `/` i `/shop` dele isti
`Shop.vue` (`CatalogController@index`), pa se sve ispod odnosi i na
početnu stranicu.
- **Izbor: čist Tailwind sa `brand-*` tokenima, ne shadcn-vue.** Projekat
  do sada nema nijednu shadcn komponentu dodatu (samo `components.json`
  podešen, vidi Stack), a ovaj korak treba bespoke elemente (tipografski
  placeholder korica, terakota pill dugmad, drawer filter panel) koji se ne
  mapiraju čisto na generičke shadcn primitive (Button/Card/Select) — dodavanje
  shadcn-a ovde bi značilo instalaciju/build rizik za marginalnu korist, a
  Shop/Product su i do sada bili čist Tailwind. Odluka ostaje po potrebi
  revidirana za sledeće korake (npr. ako admin panel redizajn kasnije stvarno
  profitira od shadcn Table/Form komponenti).
- **`BookCoverPlaceholder.vue`** (nova, `resources/js/Components/Catalog/`) —
  generiše tipografski placeholder kad `image` nije prisutna: naslov u
  `font-serif` (Lora), autor ispod, tanak dekorativni okvir. Pozadina se
  **deterministički** bira iz jednostavnog hash-a naslova (`hash % 6`) nad
  palettom od 6 toplih nijansi (`#F6EEE3` `#EAD9C5` `#E4CBAE` `#D9C2A6`
  `#C9AD8F` `#DDCFC0`) — ista knjiga uvek dobija istu boju, susedne kartice
  u gridu variraju. Kad `image` postoji, prikazuje se slika umesto
  placeholder-a (isti prop-interfejs za oba slučaja). Koristi je i
  `BookCard.vue` (Shop grid) i `Product.vue` (detalj knjige) — jedna
  komponenta, bez duplirane placeholder logike.
  ✅ **REŠENO (kontrast autora, posle code review-a)** — autor je prvobitno
  bio `text-brand-text-secondary` (#8A7461). Izračunat WCAG kontrast te boje
  protiv svih 6 nijansi palete: **2.08:1–3.84:1** — nijedna ne dostiže AA
  4.5:1, čak ni najsvetlija (`#F6EEE3`, 3.84:1). Probano i
  `brand-header-text` (#6B4423): prolazi na 5 od 6, ali pada na 3.98:1 na
  najtamnijoj (`#C9AD8F`). Umesto brisanja dve najtamnije nijanse (čime bi
  paleta izgubila varijaciju), autor je prebačen na **`text-brand-text-primary`
  (#2E241C, ista boja kao naslov)** — prolazi **7.1:1–13.2:1** na svih 6,
  sa udobnom marginom. Naslov i autor se i dalje vizuelno razlikuju preko
  veličine/težine/kurziva (`font-serif font-semibold` naslov vs. `italic`
  autor), ne preko boje — validan tipografski obrazac (isti "mastilo",
  hijerarhija preko stila), i mnogo pouzdaniji od oslanjanja na sekundarnu
  boju koja se menja preko 6 različitih pozadina.
  **Napomena o Tailwind opacity modifikatoru:** `brand-*` tokeni su
  definisani kao plain hex string preko CSS varijable (`'var(--brand-x)'`),
  ne kao Tailwind-ova `withOpacityValue` funkcija (koja bi trebalo da bude
  string sa `<alpha-value>` placeholder-om) — Tailwind 3.4 za takve (string,
  ne function) theme vrednosti **tiho ignoriše** `/opacity` modifikator
  (npr. `border-brand-text-primary/15` bi se renderovao kao potpuno
  neprovidna boja, bez greške, bez efekta). Zato dekorativni okvir u
  placeholder-u koristi `border-white/40` (pravi, statički Tailwind `white`,
  opacity tu radi ispravno) umesto opacity-varijante brand tokena — vizuelno
  bolji izbor uostalom (tanka bela linija radi na svih 6 nijansi podjednako
  dobro). Isto pravilo je već primenjeno u koraku 2 (cart bedž `ring-2
  ring-brand-page`, umesto opacity varijante).
- **`BookCard.vue`** — zaobljeni uglovi, `bg-white` kartica sa `bg-brand-*`
  paletom u placeholder-u korice (ne cela kartica, samo "korice" deo, kako je
  i traženo), naslov `font-serif` sa hover u `brand-accent`, hover na celoj
  kartici (`-translate-y-1` + senka). **Terakota "U korpu" pill dugme
  direktno na kartici** — poziva `cart.addItem(book.product_id, 1)`
  (postojeća akcija iz `cart.js`, ništa novo), onemogućeno kad `!available ||
  stock === null` (ista `canBuy` logika kao `Product.vue`, uskladjeno).
  Root kartice je **plain `<div>`, ne `<Link>`** (za razliku od stare
  verzije) — cena/dugme su van `<Link>`-a koji obavija samo koricu i naslov,
  da dugme unutar linka ne bude ugnježdeni interaktivni element (nevalidan
  HTML, i click bi bubble-ovao u navigaciju). `<Link>` oko korice ima
  `tabindex="-1" aria-hidden="true"` (dodato posle code review-a) — naslov
  ispod korice je zaseban `<Link>` na isto odredište, pa bi bez ovoga Tab
  red imao dva uzastopna stop-a za istu destinaciju po kartici; korica sad
  nije fokusibilna niti je vidi screen reader, naslov ostaje jedini
  pristupačni put do `/knjiga/{slug}`.
  **"Dodato ✓" stanje (posle code review-a)** — klik na "U korpu" (pored
  `cart.addItem`) postavlja `justAdded = true` na 1.5s, dugme u tom prozoru
  prikazuje "Dodato ✓" umesto "U korpu". Dugme **nije onemogućeno** dok se
  to stanje prikazuje — ponovni klik odmah dodaje još jedan primerak i samo
  resetuje tajmer (`clearTimeout` + novi `setTimeout`), ne blokira. Vizuelno/
  programski potvrđeno (headless Chrome + CDP): tekst se menja odmah po
  kliku, vraća se na "U korpu" posle ~1.7s, i odmah reaguje na sledeći klik.
  **Backend dodatak (minimalan,
  nužan za ovaj UI):** `CatalogController::card()` do sada nije slao
  `product_id` u Shop listing payload-u (samo `Product::detail()` za
  Product.vue ga je imao) — dodato `'product_id' => $product->id` (podatak
  je već učitan preko postojećeg `with('product:id,...')`, nema nove upit).
  Ovo je jedina backend izmena u ovom koraku; nijedan test ne proverava
  odsustvo tog polja (provereno grep-om), `php artisan test` i dalje 348/348.
  **Card footer je `flex-col`** (cena+dostupnost red, PA pun-širine dugme
  ispod), ne `flex-row justify-between` — na 2-kolonoj mobilnoj mreži uska
  kartica je lomila cenu u novi red kad su cena i dugme bili u istom redu
  (uhvaćeno vizuelnom proverom, ispravljeno pre commit-a).
- **Filter panel (`Shop.vue`)** — select/checkbox/input stilovi na
  `brand-*` tokenima. **Mobilni (`< md`)**: dugme "Filteri" (sa tačkicom kad
  ima aktivnih filtera) otvara **`fixed` drawer sa desne strane** (ne
  accordion koji gura sadržaj) + potamnjeni overlay preko cele stranice,
  zatvara se na X, klik na overlay, "Prikaži rezultate", ili **Escape**.
  ✅ **REŠENO (ispravka posle code review-a — prvobitna verzija BEZ
  `<Teleport>`-a je bila lomljiva)**: overlay + drawer su sad u
  `<Teleport to="body" :disabled="isDesktopFilters">`. Prvobitno rešenje se
  oslanjalo na to da CSS kod jednakih `z-index` vrednosti (i overlay/drawer i
  `<nav>` su `z-50`) iscrtava kasniji DOM element iznad — radilo je, ali
  krhko (zavisi od budućih izmena redosleda markup-a u
  `AuthenticatedLayout`-u ili uvođenja stacking konteksta između njih, npr.
  `transform`/`filter` na nekom ancestor-u, što bi tiho pokvarilo prekrivanje
  bez ikakve greške u konzoli). `<Teleport>` premešta overlay+drawer direktno
  u `<body>`, van `<nav>`-ovog stacking konteksta — pouzdano bez obzira na
  DOM redosled unutar layout-a. **Teleport se ne može bezuslovno uključiti**:
  ista `<div>` se koristi i kao desktop statična kolona u `md:grid`-u, pa bi
  bezuslovan Teleport premestio panel u `<body>` i na desktopu, izbacujući ga
  iz grid layout-a (broken desktop). Rešeno preko `isDesktopFilters`
  (`ref`, prati `window.matchMedia('(min-width: 768px)')`) — Teleport je
  `:disabled="isDesktopFilters"`, tj. **isključen na desktopu** (panel ostaje
  in-place u gridu, kako je i bio) i **uključen na mobilnom** (panel
  teleportovan u `<body>`). `role="dialog"`/`aria-modal="true"` su takođe
  uslovljeni istim `isDesktopFilters` (odsutni na desktopu — na statičnoj
  koloni ta ARIA semantika ne bi bila tačna, tamo to nije dijalog).
  `invisible md:visible` (na `md:` uvek visible, na mobilnom `invisible` kad
  je zatvoren) sprečava da zatvoren (van ekrana, `translate-x-full`) drawer
  ostane fokusibilan Tab-om ili vidljiv screen reader-u — CSS transform sam
  po sebi ne uklanja element iz accessibility stabla/tab reda, `visibility:
  hidden` uklanja. Escape zatvara drawer (`keydown` listener na
  `document`, uklonjen u `onUnmounted`).
  Provereno vizuelno i programski (headless Chrome + CDP): na mobilnom
  (390px) `role="dialog"` element i overlay su potvrđeno direktna deca
  `document.body` posle otvaranja; `document.elementFromPoint()` na
  koordinatama nav-a (i unutar i van širine samog drawer panela) pogađa
  overlay, ne nav ispod njega — nav je stvarno neklikabilan dok je drawer
  otvoren, ne samo vizuelno zatamnjen. Na desktopu (1400px) filter kolona je
  i dalje unutar `.md\:grid` kontejnera (nije teleportovana) — layout
  identičan kao pre ove izmene. Breakpoint ostaje `md:` (kako je traženo —
  "< md breakpoint"). Desktop (`md+`): ista polja, `md:static` bez
  `fixed`/overlay klasa (isti markup, samo druge klase na istom wrapper
  `<div>`-u — nema duplog filter markup-a).
- **`Product.vue`** — 2 kolone na `md+` (korice `md:col-span-2`, detalji
  `md:col-span-3`), 1 kolona ispod toga. Naslov `font-serif`, autori sa
  ulogama (pisci odvojeno od prevodilaca/ilustratora, kao i ranije),
  metapodaci u `<dl>` mreži, opis sa `max-w-prose` + `leading-relaxed`.
  **Fix uhvaćen mobilnom proverom:** dugme "Dodaj u korpu" pored input polja
  za količinu (`flex items-center gap-4`) je lomilo TEKST dugmeta u dva reda
  na uskom ekranu (nedovoljno mesta u redu) — dodato `flex-wrap` na
  kontejner (dugme celo ide u novi red ako ne stane, ne lomi sopstveni
  tekst) + `whitespace-nowrap` na dugme.
- **`Pagination.vue`** — samo boje (`indigo-600` aktivna stranica →
  `brand-accent`), logika/struktura linkova nedirana.
- **Prvi pravi vitest testovi za `.vue` SFC komponente** (do sad je vitest
  testirao samo plain `.js` Pinia store-ove — `auth.test.js`/`cart.test.js`).
  Dva preduslova koja su nedostajala:
  - `vitest.config.js` nije imao `vue()` plugin (samo `vite.config.js`, za
    pravi build) — bez njega vitest ne zna da kompajlira `.vue` import.
    Dodat `@vitejs/plugin-vue` (već je bio zavisnost projekta, samo nije bio
    ožičen za test config).
  - `@vue/test-utils` **nije bio instaliran** — dodat kao devDependency
    (`npm install --save-dev @vue/test-utils --legacy-peer-deps`; isti
    `--legacy-peer-deps` razlog kao i za `npm install`, vidi Stack).
  - `route()` unutar mount-ovanog `<template>`-a kompajlira se kao
    `_ctx.route(...)`, ne kao referenca na `globalThis.route` (obrazac koji
    `auth.test.js` koristi, ali taj fajl nikad ne mount-uje pravi SFC
    `<template>`, samo ručno pisane render funkcije) — mora ići kroz
    `global.mocks: { route: ... }` (vue-test-utils API), inače
    `TypeError: _ctx.route is not a function`.
  - `resources/js/Components/Catalog/BookCoverPlaceholder.test.js` — ista
    boja za isti naslov (dva odvojena mount-a, isti title), `<img>` kad
    postoji `image` prop (bez tipografskog teksta), placeholder tekst
    (naslov+autor) kad `image` nedostaje.
  - `resources/js/Components/Catalog/BookCard.test.js` — dugme omogućeno za
    `available: true, stock: 5`; onemogućeno za `available: false`;
    onemogućeno za `stock: null` (e-knjiga); klik na omogućeno dugme menja
    tekst u "Dodato ✓" i ne zove `axios.post` za gosta (`syncWithBackend` se
    tiho preskače bez `authStore.user`).
- Testirano: `npx vite build`, `npm run test` (vitest, **17 passed** — 10
  postojećih + 3 `BookCoverPlaceholder` + 4 `BookCard`), `php artisan test`
  (348 passed, uključujući `ShopCatalogTest`/`BookDetailTest` bez izmena —
  oba su čisto Inertia-prop bazirana, ne proveravaju render-ovani markup, pa
  promena izgleda nije mogla da ih pokvari). Vizuelno i programski provereno
  (headless Chrome + CDP screenshot + `Runtime.evaluate`/`elementFromPoint`,
  desktop 1400px i mobilni 390px emulacija): Shop grid (uključujući
  "U korpu" → "Dodato ✓" na kartici), Product stranica, knjiga sa `stock = 0`
  (onemogućeno dugme na kartici i na Product stranici, "Nema na stanju"
  crveno) — privremeno postavljeno na realnoj dev knjizi (`na-drini-cuprija`)
  preko tinker-a i **vraćeno na `stock = 10`** posle provere. Mobilni filter
  drawer: otvoren/zatvoren, Escape zatvara, `role="dialog"` element i overlay
  potvrđeno teleportovani direktno pod `document.body`, `elementFromPoint()`
  potvrđuje da nav NIJE klikabilan (ni deo koji drawer panel fizički ne
  pokriva) dok je drawer otvoren, desktop filter kolona ostaje unutar
  `.md:grid` kontejnera (layout identičan kao pre Teleport ispravke).
  Konzola bez grešaka na svim proverenim stranicama.
- ✅ **REŠENO (cleanup provera, posle pitanja da li se čiste `matchMedia`
  listener i `setTimeout`-ovi)** — `matchMedia` listener (`isDesktopFilters`)
  i `BookCard`-ov "Dodato ✓" `setTimeout` su već bili očišćeni u
  `onUnmounted` (deo prvobitnog commit-a). Otkriven i ispravljen jedan
  **pravi, pre-postojeći propust** (nije uveden ovom fazom, ali `Shop.vue`
  je već pod revizijom): `debounceTimer` (cena/pretraga debounce, postoji od
  Faze 3, deo 3) se nikad nije čistio pri unmount-u. Bez toga bi, ako
  korisnik otkuca cenu/pretragu pa odmah pre isteka 400ms klikne na knjigu
  (SPA navigacija na `Product.vue`), zakasneli `apply()` i dalje pozvao
  `router.get(route('shop'), ...)` NAKON što je `Shop.vue` već unmount-ovan
  — tiho bi prekinuo/preusmerio navigaciju na koju je korisnik već otišao.
  Dodato `clearTimeout(debounceTimer)` u isti `onUnmounted` blok kao i
  ostala dva cleanup-a.
- **CI nalaz (prijavljeno, workflow NIJE menjan bez odobrenja):** PR-ovi
  (uključujući ovaj) nemaju check run-ove jer `.github/workflows/deploy.yml`
  ima `on: push: branches: [main, develop]` — **nema `pull_request` trigger
  uopšte**. Workflow se pokreće tek POSLE merge-a (push na `develop`/`main`
  koji nastaje od merge commit-a), nikad na sam otvoren PR. Ovo je
  pre-postojeće stanje (proverено, isto važi za sve dosadašnje PR-ove ove
  faze), ne nešto što je ovaj korak pokvario.
  ✅ **REŠENO** (uz eksplicitno odobrenje) — umesto menjanja `deploy.yml`,
  dodat poseban `.github/workflows/ci.yml` sa `pull_request` trigger-om.
  Vidi sekciju **CI** ispod.

## CI
Dva odvojena GitHub Actions workflow-a u `.github/workflows/`:

- **`ci.yml`** — `pull_request` trigger (`branches: [main, develop]`, **ne**
  `pull_request_target` — namerno: `pull_request_target` bi pokrenuo workflow
  fajl iz BAZNE grane sa write-level podrazumevanim `GITHUB_TOKEN`-om čak i za
  PR sa fork-a, što je nepotreban bezbednosni rizik za workflow koji samo
  pokreće testove i ne treba mu nikakav write pristup). Job `tests`
  (u checks-ima se pojavljuje kao **"tests"**, pod workflow-om **"CI"**)
  ponavlja **identične** korake kao `tests` job u `deploy.yml` (checkout,
  PHP **8.4**, `cp .env.example .env`, `composer install`, Node 22,
  `npm ci --legacy-peer-deps` + `npm run build`, `npm run test`,
  `php artisan key:generate`, `composer test`) — namerno ista PHP verzija
  kao server/`deploy.yml` (usklađeno posle prvobitne verzije koja je koristila
  8.2 "kao lokalno"; razlika prema lokalnom razvoju i dalje postoji, samo
  živi u lokalnom `PHP 8.2` iz Stack-a ispod, ne u dva PHP okruženja unutar
  CI-ja). `permissions: contents: read` (minimalno, workflow ništa ne piše).
  `concurrency` (group po PR broju, `cancel-in-progress: true`) — novi push
  na isti PR otkazuje prethodni, još aktivan run, umesto da čekaju u redu.
  **Bez ijednog secret-a** — isto kao `deploy.yml`-ov `tests` job (SQLite
  `:memory:`, `FakePaymentGateway` u testovima, ne prave PayPal pozive, vidi
  Faza 0/POZNATI PROBLEMI #7).
- **`deploy.yml`** — nepromenjen. `push` trigger (`branches: [main,
  develop]`) — pokreće se TEK POSLE merge-a (na sam merge commit), ne na
  otvoren PR. `tests` job tu i dalje postoji, i dalje PHP 8.4. Sad kad su
  `ci.yml` i `deploy.yml` PHP-verzijski identični, `ci.yml`-ova jedina
  stvarna svrha je **vremenski raspored** provere (PRE merge-a, vidljivo na
  samom PR-u) — ne provera drugog okruženja (to rade lokalni PHP 8.2 razvoj
  vs. oba CI/deploy workflow-a na 8.4).
- **Ručni korak, van ovog PR-a:** da GitHub stvarno **blokira merge** dok
  `ci.yml`-ov `tests` check ne prođe, potrebno je u GitHub repo Settings →
  Branches → branch protection rule za `develop`/`main` → "Require status
  checks to pass before merging" → dodati `tests` (iz `ci.yml`) kao
  obavezan check. Ovo se ne može podesiti iz workflow YAML-a — čisto GitHub
  UI/API podešavanje, van dosega ovog repo-a.

## Pretraga i filteri kataloga — trenutno stanje
(Istorija/dijagnoza bug-ova iz ovog dela je u PR #64/#65/design-shop-filters —
ovde samo kako sad radi.)

- **Jedno search polje u celoj aplikaciji** — `resources/js/Components/
  HeaderSearch.vue`, u header-u (`AuthenticatedLayout.vue`). `Shop.vue`
  **nema** sopstveno search polje niti `form.search` — arhitektonska odluka
  posle bug-a gde je lokalni `apply()` slao zastareo/prazan search i
  pregazio header pretragu. **URL (query string) je jedini izvor istine**:
  `HeaderSearch.vue` čita/piše `search` isključivo preko `usePage().url` +
  `router.get('shop', ...)`; `Shop.vue` čita `props.filters.search` direktno
  (nikad iz lokalnog state-a) kad gradi zahtev (`buildParams()`).
  Renderovana na **dva mesta** u `AuthenticatedLayout.vue`: desktop traka
  (`hidden md:flex`) i drugi red header-a na `< md` koji je **uvek vidljiv**
  (ne zavisi od hamburger dropdown-a) — **nije** duplirana unutar hamburger
  padajućeg menija. `route()` u `HeaderSearch.vue`/`Shop.vue`-ovom `<script
  setup>` JS kodu (van template-a) radi preko pravog `window.route`-a koji
  ubacuje `@routes` Blade direktiva, ne preko Ziggy-jevog Vue plugin-a (koji
  pokriva samo template pozive) — testovi zato moraju `globalThis.route`,
  ne `global.mocks`.
- **Sinhronizacija (`Shop.vue`)** — `syncingFromProps` flag oko
  `Object.assign(form, filters)` sprečava da sinhro iz props-a (back/forward,
  "Poništi sve") sama okine debounce watch na `form.price_min`/`price_max`
  (redundantan zahtev). `router.on('start'/'finish', ...)` (GLOBALNI Inertia
  event-ovi, hvataju i navigacije iz `HeaderSearch.vue`) — lokalni `apply()`
  se ne šalje dok je BILO KOJA Inertia navigacija u letu, zakazuje se
  (`pendingReapply`) i ponovo poziva čim se ta navigacija završi, sa svežim
  `props.filters.search`.
  **Poznato, uže rezidualno ograničenje** (i dalje postoji, provereno ponovo
  posle DEO A refaktora — `Shop.test.js` eksplicitno dokumentuje zašto):
  lokalni filter promenjen DOK je header pretraga u letu može privremeno
  vizuelno da se vrati na podrazumevano kad njen odgovor legne (ne zna za taj
  filter, `Object.assign` ga bezuslovno prepisuje). Van opsega — mnogo ređi
  slučaj od originalnog bug-a, uži prozor u produkciji nego u sporom dev
  okruženju.
- **Breakpoint** — nav (logo/cart/hamburger), search i filter panel
  (Shop.vue) svi koriste **isključivo `md` (768px)** kao prag mobilni/desktop
  — nema rupe gde ništa nije dostupno.
- **Aktivni filteri kao chip-ovi** (`Shop.vue`, iznad rezultata) — uključujući
  search ("Pretraga: seobe"), × na chip-u uklanja SAMO taj filter,
  "Poništi sve" briše sve. Filter panel: `bg-brand-card` kartica na desktopu
  (`md:sticky md:top-24`), isti dizajn (grupe, custom `ChevronDown` strelica,
  cena sa € sufiksom) deli mobilni drawer (Teleport/aria iz PR #60
  nepromenjeni).
- **`@tailwindcss/forms` plugin** — bio uvezen u `tailwind.config.js` ali
  NIKAD dodat u `plugins: []` (pre-postojeći propust, otkriven dok "Samo na
  stanju" checkbox nije hteo da postane terakota). Dodat sa **`strategy:
  'class'`** (NE podrazumevano `'base'`, koje bi globalno resetovalo izgled
  SVAKOG `<input>`/`<select>`/`<textarea>` na sajtu — admin forme, Breeze
  auth, checkout — daleko van opsega ovog dizajna). `'class'` čini reset
  opt-in preko `form-*` klasa; trenutno ima efekta samo na `form-checkbox`
  klasi na "Samo na stanju" checkbox-u, ništa drugo na sajtu nije dirano.
- Testovi: `resources/js/Components/HeaderSearch.test.js` (7),
  `resources/js/Pages/Shop.test.js` (8 — regresija search-a + chip-ovi).
  Feature test za `/shop?search=...` već postoji u
  `tests/Feature/Catalog/ShopCatalogTest.php` (7 testova) — bug/refaktor su
  čisto frontend, backend nedirano. `npx vite build`, `npm run test` (32
  passed), `php artisan test` (348 passed) prolaze. Vizuelno provereno
  (headless Chrome + CDP) na 1400/700/390px sa aktivnim filterima.

### Planirano/otvoreno
- Kad se bude radio redizajn Cart/Checkout stranice, tada dodati i
  funkcionalnost sačuvanih adresa: `addresses` tabela, predpopunjavanje
  checkout forme za ulogovane korisnike, checkbox "sačuvaj kao podrazumevanu
  adresu", profile stranica dobija sekciju za upravljanje adresama. **Ne
  raditi sada** — samo zabeleženo da ne bude zaboravljeno kad dođe taj korak.
- **Odluka na čekanju:** da li Breeze auth stranice (`GuestLayout.vue`)
  dobijaju punu nav traku (kao `AuthenticatedLayout.vue`) ili ostaju na
  minimalnom centriranom layoutu bez navigacije — vidi "header/navigacija
  (Faza 6, korak 2)" gore. Trenutno stanje (bez nav trake na login/register)
  je pre-postojeće, ne uvedeno ovim korakom.
