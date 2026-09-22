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
- Pretraga teksta (`books.search_text`, ćirilica/latinica) **još nije
  implementirana**; `search_text` se ne popunjava. Sortiranje po ceni/datumu
  nije dodato.
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
