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
Boje/fontovi/logo/brend ime — infrastruktura za sledeće Dizajn korake.
Puna istorija (uključujući `.env.example` dotenv hotfix) je u
`docs/design.md`.
- Brend ime **"Ex Libris"** dolazi iz `APP_NAME` (`.env`) — `PayPalGateway::
  createOrder`-ov `brand_name` fallback prati isti string
  (`env('APP_NAME', 'Ex Libris')`), ne diraj odvojeno.
- **Kad menjaš `.env.example`:** pazi da ne spojiš dva bloka bez newline-a
  između — CI je jednom pukao na `Dotenv` parse grešci baš zbog toga
  (detalji u `docs/design.md`).
- **Paleta** (`resources/css/app.css` `:root`, `--brand-*`, hex — ne HSL
  triplet kao shadcn `--background`/`--foreground`): `--brand-header-bg:
  #E4CBAE`, `--brand-header-text: #6B4423`, `--brand-header-text-muted:
  #9C7A54`, `--brand-accent: #D9713A`, `--brand-accent-hover: #E8935A`,
  `--brand-page-bg: #FFFCF8`, `--brand-card-bg: #F6EEE3`,
  `--brand-text-primary: #2E241C`, `--brand-text-secondary: #8A7461`. Samo
  `:root`, nema `.dark` varijante. Tailwind klase: `bg-brand-header`,
  `text-brand-header-text`, `text-brand-header-muted`, `bg-brand-accent`,
  `hover:bg-brand-accent-hover`, `bg-brand-page`, `bg-brand-card`,
  `text-brand-text-primary`, `text-brand-text-secondary`. Namerno odvojeno
  od shadcn `accent` tokena (ne pregazuje ga).
  **Tailwind opacity modifikator (`/15` i sl.) NE radi na `brand-*`
  tokenima** — definisani su kao plain hex string, ne kao Tailwind-ova
  `withOpacityValue` funkcija; Tailwind 3.4 to tiho ignoriše (bez greške,
  bez efekta). Za providnost koristi statičke boje (`white/40` i sl.).
- **Font**: Google Font "Lora" (`fonts.bunny.net`, isti CDN kao Figtree) →
  `font-serif` Tailwind klasa (naslovi). `font-sans` (Figtree, telo/forme)
  nepromenjen.
- `resources/js/Components/Logo.vue` — jedan `color` prop (default
  `currentColor`), veličina skalira preko `font-size` na roditelju (nema
  `size` prop). Koristi lucide-vue-next `BookOpen` ikonu.
- `public/favicon.svg` je primarni (`BookOpen`, `#6B4423`); staro
  `favicon.ico` ostaje kao `rel="alternate icon"` fallback, **nije
  regenerisano** iz nove ikone (nema ImageMagick/GD u ovom okruženju) —
  otvoreno kad alat postane dostupan.

## Dizajn — header/navigacija (Faza 6, korak 2)
Nav traka (`AuthenticatedLayout.vue` + `NavLink.vue`/`ResponsiveNavLink.vue`)
na brand-* tokenima. Puna istorija u `docs/design.md`.
- Scope: SAMO nav traka, ne sadržaj stranica ispod nje (Shop/Product/Cart/
  Checkout/admin nisu dirani ovim korakom).
- Border na `brand-header` pozadini koristi statičku `border-black/10`, ne
  `border-brand-header/10` — vidi opacity-modifikator gotcha u koraku 1.
- Cart bedž: `bg-brand-accent` + `ring-2 ring-brand-page` (ne gola boja) —
  brand-accent i brand-header-bg su obe tople nijanse, nizak kontrast bez
  prstena.
- **Gotcha:** novu FontAwesome ikonu MORAŠ registrovati u
  `resources/js/app.js`-ovom `library.add(...)` pozivu, inače
  "Could not find one or more icon(s)" u konzoli na svakoj stranici koja
  renderuje `AuthenticatedLayout` (uhvaćeno sa `faSearch` u ovom koraku).
- ✅ **REŠENO (Faza 6, korak 5)** — `AuthenticatedLayout.vue` i
  `GuestLayout.vue` NISU ista komponenta i ne dele nav traku;
  `GuestLayout.vue` (Breeze auth stranice) je bio bez ikakve navigacije.
  Odluka: `GuestLayout.vue` ostaje minimalan (bez pune nav trake), dobija
  samo link "Nazad u prodavnicu" — vidi "Dizajn — Breeze auth" niže.

## Dizajn — kupovni katalog: Shop.vue i Product.vue (Faza 6, korak 3)
Shop/Product vizuelni redizajn na brand-* tokenima; logika (filteri,
debounce, pretraga, paginacija, cart) nedirana. Puna istorija (WCAG
kontrast računice, evolucija filter panela) u `docs/design.md`.
- Čist Tailwind, ne shadcn-vue (shadcn je samo podešen, ništa dodato — vidi
  Stack) — bespoke elementi (placeholder korica, drawer) se ne mapiraju
  čisto na generičke shadcn primitive.
- `BookCoverPlaceholder.vue` — pozadina se **deterministički** bira iz
  `hash(naslov) % 6` nad paletom od 6 nijansi (`#F6EEE3` `#EAD9C5`
  `#E4CBAE` `#D9C2A6` `#C9AD8F` `#DDCFC0`). **Autor/naslov tekst mora
  ostati na `text-brand-text-primary`**, ne `text-brand-text-secondary` —
  pada ispod WCAG AA na svih 6 pozadina (tačni brojevi u
  `docs/design.md`); razlika naslov/autor ide kroz font/stil (`font-serif`
  vs `italic`), ne boju.
- `BookCard.vue`: root je plain `<div>`, ne `<Link>` (dugme unutar linka =
  ugnježden interaktivni element). `<Link>` oko korice ima `tabindex="-1"
  aria-hidden="true"` (naslov ispod je zaseban `<Link>` na isto odredište —
  bez ovoga dupli Tab-stop). `CatalogController::card()` šalje
  `product_id` u Shop listing payload (dodato ovim korakom, koristi ga
  "U korpu" dugme na kartici).
- **Mobilni filter panel (`Shop.vue`) MORA koristiti `<Teleport to="body"
  :disabled="isDesktopFilters">`** (`isDesktopFilters` prati
  `matchMedia('(min-width: 768px)')`) — bez toga drawer/overlay zavise od
  DOM redosleda i `z-index` podudaranja sa `<nav>`, krhko na svaku buduću
  izmenu layout-a. `role="dialog"`/`aria-modal` su uslovljeni istim flagom
  (odsutni na desktopu, gde panel nije dijalog). Isti wrapper `<div>` se
  koristi i kao desktop statična kolona — Teleport se NE sme bezuslovno
  uključiti.
- **Vitest za `.vue` SFC komponente** (prvi put u ovom koraku) zahteva:
  `vue()` plugin u `vitest.config.js` (odvojen od `vite.config.js`),
  `@vue/test-utils` kao devDependency, i `route()` pozvan iz `<template>`-a
  ide kroz `global.mocks: { route }` (ne `globalThis.route`, koji pokriva
  samo `<script setup>` JS pozive — vidi `HeaderSearch.test.js`/
  `Shop.test.js`).
- **Gotcha:** svaki `setTimeout`/`matchMedia` listener zakazan u
  `onMounted` MORA imati odgovarajući cleanup u `onUnmounted` —
  `debounceTimer` u `Shop.vue` je ovo prvo promašio (curenje navigacije
  posle unmount-a), ispravljeno ovde.

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
  (`md:sticky md:top-24 md:max-h-[calc(100vh-7rem)]`, `overflow-y-auto`
  — panel se ne proteže ispod viewport-a kad su svi filteri aktivni,
  sam skroluje interno; provereno na 1366×768 da je i poslednja kontrola
  i dalje dostupna), isti dizajn (grupe, custom `ChevronDown` strelica,
  cena sa € sufiksom) deli mobilni drawer (Teleport/aria iz PR #60
  nepromenjeni).
  **`removeFilter('price_min'/'price_max')`** (klik na × na cenovnom
  chip-u) — pored sinhronog `apply()`, `form.price_min`/`price_max`
  ostaju u debounce watch-u pa bi bez `await nextTick(); clearTimeout(...)`
  posle `apply()`-a poslao potpuno redundantan drugi, identičan zahtev
  ~400ms kasnije (potvrđeno testom pre ove ispravke, isti mehanizam kao
  `syncingFromProps` iznad — watch se ne izvršava sinhrono unutar iste
  funkcije).
- **`@tailwindcss/forms` plugin** — bio uvezen u `tailwind.config.js` ali
  NIKAD dodat u `plugins: []` (pre-postojeći propust, otkriven dok "Samo na
  stanju" checkbox nije hteo da postane terakota). Dodat sa **`strategy:
  'class'`** (NE podrazumevano `'base'`, koje bi globalno resetovalo izgled
  SVAKOG `<input>`/`<select>`/`<textarea>` na sajtu — admin forme, Breeze
  auth, checkout — daleko van opsega ovog dizajna). `'class'` čini reset
  opt-in preko `form-*` klasa; trenutno ima efekta samo na `form-checkbox`
  klasi na "Samo na stanju" checkbox-u, ništa drugo na sajtu nije dirano.
- Testovi: `resources/js/Components/HeaderSearch.test.js` (7),
  `resources/js/Pages/Shop.test.js` (9 — regresija search-a, redundantan
  apply() i za `props.filters`-sinhro i za `removeFilter()` cene,
  chip-ovi). Feature test za `/shop?search=...` već postoji u
  `tests/Feature/Catalog/ShopCatalogTest.php` (7 testova) — bug/refaktor su
  čisto frontend, backend nedirano. `npx vite build`, `npm run test` (33
  passed), `php artisan test` (348 passed) prolaze. Vizuelno provereno
  (headless Chrome + CDP) na 1400/700/390/1366×768px sa aktivnim filterima
  (poslednje uz stvarni scroll stranice — `position: sticky` se ne
  "zakači" dok se ne skroluje, provera na scroll=0 daje lažan utisak da
  panel ne staje u viewport).

## Dizajn — Cart/Checkout redizajn + sačuvane adrese (Faza 6, korak 4)
Cart/Checkout redizajn (brand-* tokeni, srpski tekst) + sačuvane adrese
(`addresses` tabela). Puna istorija (zašto dual-write, agent-split
backend/frontend) u `docs/design.md`.
- `addresses`: `user_id`, `recipient_name`, `phone`, `line1`, `line2?`,
  `city`, `postal_code`, `country` (default `'Srbija'`), `is_default`.
  `orders` dobija paralelne `shipping_*` snapshot kolone + `shipping_
  address_id` (nullable FK, `nullOnDelete`, **čist audit trag** — prikaz
  porudžbine čita ISKLJUČIVO `shipping_*` kolone, nikad `shippingAddress`
  relaciju, isti snapshot princip kao `product_name`/`product_price`).
- **Dual-write, namerno:** `OrderController::store` i dalje puni STARE
  `orders` kolone (`first_name`/`last_name`/`address`/`city`/
  `postal_code`/`phone`) — `Admin/Orders/*.vue` ih čitaju direktno i nisu
  dirani. `first_name`/`last_name` = naivan split `recipient_name`-a na
  prvi razmak (Address model namerno nema odvojena imena).
- `app/Services/AddressService.php` (isti obrazac kao BookService/
  InventoryService): prva adresa korisnika je UVEK default; `setDefault()`
  je JEDINO mesto koje garantuje najviše jednu default adresu po
  korisniku (`update()` nikad ne upisuje `is_default` direktno); `delete()`
  namerno NE unapređuje drugu adresu u default.
- **IDOR (isti princip kao Faza 0, problem #5):** `AddressController`
  lookup ide ISKLJUČIVO kroz `$request->user()->addresses()->
  findOrFail($id)` — nikad `Address::find()` niti implicitni `{address}`
  route-model-binding (parametar rute je običan `int`). `Gate::authorize()`
  (ne `$this->authorize()` — bazni `Controller` nema `AuthorizesRequests`
  trait u ovom Laravel 12 skeletu) posle lookup-a je odbrana u dubini.
  `AddressPolicy` auto-discovered (bez ručne registracije, isto kao
  `OrderPolicy` — ovaj repo nema `AuthServiceProvider`).
- `OrderController::store` prima ILI `address_id` (mora pripadati
  `Auth::user()`, gost ga NIKAD ne sme poslati — 422 pre bilo kakvog upisa)
  ILI `shipping.{recipient_name,phone,line1,line2,city,postal_code,
  country}` (obavezno kad `address_id` nedostaje). `save_address`
  (checkbox) čuva novu adresu u ISTOJ transakciji kao porudžbina.
- `Checkout.vue`: `<select>` sačuvanih adresa (default prva, backend šalje
  default-first) + "+ Nova adresa" → inline `shipping.*` polja + "Sačuvaj
  kao podrazumevanu" checkbox (samo ulogovani). Gost uvek samo inline,
  nikad `<select>`/checkbox. `onMounted` redosled korpe
  (`loadFromBackend()` pa `hydrate()`) NEDIRAN — vidi "Korpa — refaktor"
  gore.
- `Profile/Edit.vue` dobija `AddressManagement.vue` sekciju (lista, modal
  dodaj/izmeni, obriši preko `window.confirm()` — ne `DeleteConfirmation.vue`,
  API se nije uklopio sa "svaka akcija sopstveni `useForm()`" obrascem).
- Testovi: `AddressManagementTest.php` (16), `CheckoutAddressTest.php` (16,
  uključujući snapshot immutability — izmena/brisanje sačuvane adrese
  POSLE porudžbine ne menja `shipping_*` te porudžbine). `php artisan
  test`: 380 passed. `Checkout.test.js`/`AddressManagement.test.js` (13
  novih). `npm run test`: 46 passed.

## Dizajn — Breeze auth (Faza 6, korak 5)
**ODLUKA (rešava otvoreno pitanje iz koraka 2):** `GuestLayout.vue` OSTAJE
minimalan, NE dobija punu nav traku kao `AuthenticatedLayout.vue` — samo
link "Nazad u prodavnicu" (`/shop`, lucide `ArrowLeft`) iznad Logo-a. Puna
istorija (kontrast proba brand-page vs brand-header) u `docs/design.md`.
- Pozadina `GuestLayout.vue` je `bg-brand-header` (ne `brand-page`) — probano
  oba, `brand-page` (#FFFCF8) je vizuelno skoro identična beloj kartici forme
  (nizak kontrast), `brand-header` (#E4CBAE) jasno kontrastira i vizuelno
  vezuje auth stranice za istu traku boju kao glavni nav (Faza 6, korak 2).
  Link i Logo koriste `text-brand-header-text` — isti par tokena, već
  provereni za kontrast na `brand-header` pozadini u koraku 2, ponovo
  iskorišćeni ovde.
- **Naslovi dodati po stranici** (nisu postojali ranije — karta je imala
  samo `<Head title="...">`, ništa vidljivo): `<h1 class="font-serif ...">`
  na sve 4 stranice koje imaju formu bez ranije vidljivog naslova ("Prijavite
  se", "Registracija", "Zaboravljena lozinka", "Nova lozinka", "Potvrda email
  adrese", "Potvrdite lozinku") — jedino mesto gde je ovaj korak dirao
  pojedinačne auth stranice (naslov je po definiciji različit tekst po
  stranici, ne može ići kroz deljenu komponentu). `<Head title>` (browser tab,
  engleski) nije menjan — nije vidljiv u UI, van opsega.
- **Deljene forme komponente** (`TextInput.vue`, `InputLabel.vue`,
  `PrimaryButton.vue`, `SecondaryButton.vue`, `Checkbox.vue`) prebačene na
  brand-* tokene (border `border-black/20`, focus `brand-accent`, tekst
  `brand-text-primary`, `PrimaryButton` `bg-brand-accent`/`hover:brand-accent-hover`).
  `InputError.vue` NIJE menjan — `text-red-600` na beloj pozadini (karta je
  uvek bela, i na auth stranicama i na Profile-u) već prolazi WCAG AA, nema
  potrebe za izmenom.
  **`Checkbox.vue` gotcha (isti kao "Samo na stanju" checkbox iz koraka 3):**
  `text-brand-accent` sam po sebi nema efekta na `<input type="checkbox">`
  bez `@tailwindcss/forms` `form-checkbox` klase (plugin je `strategy:
  'class'`, opt-in). Dodato `form-checkbox h-4 w-4` — identičan pattern kao
  `Shop.vue`/`Checkout.vue`/`AddressManagement.vue` (`grep`-om potvrđeno pre
  izmene, isti string kopiran radi konzistentnosti).
- **NE `@tailwindcss/forms` 'base'/'class' reset za tekstualne inpute** —
  `TextInput.vue` je stilizovan direktno (border/focus/tekst boje), plugin
  ostaje rezervisan samo za `form-checkbox` slučaj (nepromenjeno iz koraka 3).
- **Posledica dele komponente (očekivano, ne bag):** `TextInput`/
  `InputLabel`/`PrimaryButton`/`Checkbox` su deljeni i van auth stranica —
  `Profile/Partials/UpdateProfileInformationForm.vue`,
  `UpdatePasswordForm.vue`, `DeleteUserForm.vue` (`SecondaryButton`) i
  `AddressManagement.vue` (već brand-stilizovan modal iz koraka 4, ali
  njegov `TextInput`/`SecondaryButton` je do sada tiho ostajao na starom
  indigo fokusu — ova izmena ga uskladila, bonus, ne regresija) sada takođe
  dobijaju brand-* boje na inputima/dugmićima. Naslovi/tekst u ta 3 Profile
  partiala (`text-gray-900`/`text-gray-600`) NISU dirani — i dalje odudaraju
  od `AddressManagement.vue` suseda, isto poznato ograničenje kao u koraku 4.
- **Grep pre pretpostavke:** provereno da nijedna auth Feature test
  (`tests/Feature/Auth/*.php`) ne asertuje na render-ovani markup/tekst (sve
  su Inertia-component/redirect asercije) — redizajn nije mogao da ih
  pokvari, potvrđeno i pokretanjem (`php artisan test --filter=Auth`: 52
  passed, uključujući svih 6 Breeze auth test fajlova nepromenjenih).
- Vizuelno provereno (headless Chrome, `--headless=new --screenshot`,
  `php artisan serve`): `/login` i `/register` — kontrast pozadina/karta,
  naslov, dugme, checkbox. `npx vite build`, `npm run test` (49 passed, bez
  novih — čist CSS/markup redizajn, nema nove logike za testirati),
  `php artisan test` (380 passed, nepromenjeno — nema novih backend testova,
  ovaj korak ne dira backend) prolaze.

## PayPal capture greška — stvarna poruka umesto pogrešne dijagnoze (housekeeping)
`PaymentFailed.vue` je tvrdila statičan, pogrešan uzrok ("PERMISSION_DENIED —
Sandbox permission limitation"). Dijagnoza preko produkcionog loga (order
#21, `PayPal capture error`) je pokazala stvaran uzrok: PayPal
`error.details[0].issue = INSTRUMENT_DECLINED` — platno sredstvo je odbijeno
od procesora/banke, nije aplikacijski/permission problem.
- `PayPalController::success()` — catch grana sad parsira
  `$response['error']['details'][0]['description']` (fallback
  `$response['error']['message']`, pa Srpski generički tekst ako ništa od
  toga ne postoji) i šalje ga kao `flash.error` na `payment.failed` redirect.
  `$response` je inicijalizovan na `null` PRE `try` bloka — sprečava
  "undefined variable" upozorenje ako `captureOrder()` baci izuzetak PRE
  ijedne dodele (npr. mrežna greška, nema strukturiran PayPal odgovor
  uopšte). Raw JSON i dalje ide u `Log::error` (nepromenjeno) — dijagnoza
  preko SSH-a i dalje ima pun odgovor za grep, samo se korisniku ne šalje
  sirov JSON.
- `PaymentFailed.vue` — ceo pogrešan "Note" blok obrisan. Čita
  `usePage().props.flash?.error` (isti obrazac kao ostale flash poruke u
  aplikaciji, `HandleInertiaRequests`) — prikazuje stvarni razlog kad
  postoji, inače ništa (nema izmišljenog fallback uzroka na stranici; kad
  bekend nema šta da prosledi, `flash.error` je prazan i "Razlog" blok se ne
  renderuje). Uklonjen i neiskorišćen `message` prop (nikad nije bio
  prosleđivan — `routes/web.php` GET `/payment/failed/{order}` šalje samo
  `order`).
- **Dizajn (stranica nikad nije prošla kroz Fazu 6):** prebačena na brand-*
  tokene i srpski tekst, isti vizuelni jezik kao Checkout/Profile
  (font-serif naslov, `brand-accent` primarno dugme, lucide `XCircle`
  umesto emoji ✗). Cena ide kroz `formatPrice()` (isti razlog kao katalog —
  broj sa SQLite-a, string sa MariaDB-a).
  **Napomena (nije dirano ovim zadatkom):** `OrderSuccess.vue` i
  `OrderCodSuccess.vue` (sused-stranice u istom `Orders/` folderu) su i
  dalje na starom indigo/gray/engleskom stilu — nikad nisu prošle kroz Fazu
  6, isti status kao `PaymentFailed.vue` pre ovog fix-a. Van opsega ovog
  zadatka (zadatak je eksplicitno naveo samo `PaymentFailed.vue`).
- Testovi: `tests/Feature/PayPalPaymentTest.php` — postojeći
  `test_neuspelo_placanje_vraca_zalihu_i_upisuje_stock_movement` proširen sa
  `assertSessionHas('error', ...)` za generički fallback slučaj (fake gateway
  bez `error` ključa), nov
  `test_neuspelo_placanje_prosledjuje_konkretan_paypal_razlog` (fake gateway
  sa strukturom identičnom stvarnom produkcijskom odgovoru — potvrđuje da se
  `details[0].description` tačno prosleđuje kao flash poruka). `php artisan
  test`: 381 passed (380 + 1 nov). `resources/js/Pages/Orders/
  PaymentFailed.test.js` (nov, 3 testa — prikazuje `flash.error` kad
  postoji, sakriva "Razlog" blok kad ga nema, ne tvrdi više pogrešnu
  PERMISSION_DENIED dijagnozu). `npm run test`: 52 passed (49 + 3 nova).
  `npx vite build` prolazi. Vizuelno provereno (headless Chrome screenshot
  preko privremene, necommit-ovane preview rute koja simulira ulogovanog
  korisnika sa `failed` porudžbinom i stvarnim PayPal razlogom u
  `flash.error` — obrisana posle provere, nije deo PR-a) — kontrast,
  naslov, dugme, "Razlog" blok sa pravim tekstom.

## ✅ REŠENO — checkout na produkciji nije prelazio dalje (ni PayPal ni COD)
**Kritičan produkcijski bag, otkriven i popravljen istog dana.** Svaki
ulogovan korisnik koji je birao SAČUVANU adresu (`address_id`) na checkout-u
dobijao je nemu grešku — dugme se "vratilo", ništa se nije desilo, ni PayPal
ni COD. `POZNATI PROBLEMI`/testovi (380/380 pre ovog fix-a) ovo nisu uhvatili
jer nijedan test nije slao `shipping.*` kao PRAZAN STRING uz `address_id`
(izostavljali su ih ili slali `null`) — tačno ono što pravi frontend
(Inertia `useForm`) uvek šalje.
- **Uzrok:** `OrderController::store` validira `shipping.recipient_name/
  phone/line1/city/postal_code` kao `required_without:address_id|string|
  max:255` — BEZ `nullable`. `Checkout.vue` uvek šalje `shipping.*` kao
  prazne stringove preko `useForm`-a, čak i kad je adresa izabrana i inline
  polja sakrivena (`v-if="showInlineFields"`, vidi "Cart/Checkout redizajn").
  Laravel-ov default `ConvertEmptyStringsToNull` middleware te prazne
  stringove pretvara u `null` PRE validacije; `required_without:address_id`
  je zadovoljen (adresa postoji, nije obavezno), ali `required_without` samo
  znači "nije obavezno" — ne i "preskoči ostala pravila". Bez eksplicitnog
  `nullable`, `string` pravilo se ipak izvršava nad `null` vrednošću i puca
  sa 5 grešaka (`shipping.recipient_name`/`.phone`/`.line1`/`.city`/
  `.postal_code`, "must be a string").
  **Zašto niko nije video grešku:** te greške STVARNO stignu u Inertia
  `form.errors`, ali odgovarajući `<InputError>` elementi u `Checkout.vue`
  žive unutar `<div v-if="showInlineFields">`, koji je `false` čim je
  adresa izabrana — blok se ne renderuje, greške ostaju nevidljive. Catch-all
  (`form.errors.message || form.errors.items`) ne hvata `shipping.*` ključeve.
  Bag pogađa OBA payment metoda (validacija je pre grananja `payment_method`)
  — reprodukovano i za COD (task koji je prijavio bag) i objašnjenje se
  primenjuje identično na PayPal.
- **Fix:** dodat `nullable` ispred `required_without:address_id` na svih pet
  pravila (`app/Http/Controllers/OrderController.php`). `shipping.line2` i
  `shipping.country` već su imali `nullable` — nedirano. `nullable` ne slabi
  `required_without` za slučaj kad je polje STVARNO izostavljeno (ne samo
  `null`) — postojeći test `test_bez_address_id_shipping_polja_su_obavezna`
  (bez `address_id` I bez `shipping` ključa uopšte) i dalje prolazi
  nepromenjen, potvrđuje da regresija nije uvedena.
- **Regresioni test** (`tests/Feature/Checkout/CheckoutAddressTest.php::
  test_porudzbina_sa_sacuvanom_adresom_prihvata_prazne_shipping_stringove`)
  šalje `address_id` + `shipping.*` kao doslovno prazne stringove (`''`,
  `line2`/`country` kao `null`) — tačno pravi frontend payload. **Provereno
  da puca PRE fix-a** (identične 5 grešaka kao na produkciji) **i prolazi
  POSLE** — dokaz da test stvarno pokriva ovaj slučaj, ne lažno zelen.
- Dijagnoza (pre ovog fix-a, poseban zadatak istog dana) je urađena preko
  reprodukcije na PRODUKCIJI: privremeni test nalog preko stvarnog
  registracionog forma + stvarna sačuvana adresa preko `/profile`, `POST
  /orders` preko HTTP klijenta koji tačno prati Inertia-in protokol
  (X-Inertia header, XSRF-TOKEN cookie → X-XSRF-TOKEN header, Referer),
  SSH provera `laravel.log`-a da potvrdi da "Order created" nikad nije
  logovano za ovaj slučaj. Test nalog obrisan preko `/profile` Delete
  Account forme, obrisanje eksplicitno verifikovano (login sa istim
  kredencijalima posle brisanja vraća "These credentials do not match our
  records.").
- Testovi: `php artisan test`: 382 passed (381 + 1 nov). Pun `CheckoutAddressTest`
  (16 testova, uključujući novi) i pun suite prolaze.
- **HITNO — posle merge-a u `develop` i provere na staging-u, ovaj fix ide
  ODMAH i u `main`** (isti tok kao PR #76 jutros), ne čeka se sledeći redovni
  ciklus — produkcija je trenutno pokvarena za SVAKI ulogovan checkout sa
  sačuvanom adresom.
  ✅ **Urađeno isti dan** — PR #77 merge-ovan u `develop`, pa odmah i u
  `main` (PR #78). Produkcija je popravljena.

## Dizajn — Admin panel, korak 1: Dashboard + Categories (pilot obrazac)
Poslednji korak u dogovorenom redosledu redizajna (Shop/Product → Cart/
Checkout → Breeze auth → **admin panel**). Categories je namerno prvi CRUD
(3 fajla, bez image upload-a/relacija) — obrazac uspostavljen ovde
(deljene komponente) se ponovo koristi u Products/Books, Authors/
Publishers, Orders u narednim koracima.

**Deo 1 — Dashboard.vue.** Ranije prazan stub ("Ulogovani ste!"). Sada:
- `routes/web.php` `/dashboard` closure čita `$request->user()->role ===
  'admin'` (isti mehanizam kao `EnsureUserIsAdmin` middleware — `users.role`
  kolona, ne novi autorizacioni sistem) i za admina računa 4 READ-ONLY COUNT
  upita (`stats` prop): `total_orders`, `pending_orders` (`status =
  'pending'`), `total_products`, `low_stock_products` (`stock IS NOT NULL
  AND stock < 5` — isti prag kao `BookController::
  DEFAULT_LOW_STOCK_THRESHOLD` iz Faze 5, ne izmišljen novi broj). Za
  ne-admina `stats` je `null`.
- `Dashboard.vue`: `v-if="stats"` prikazuje 4 kartice (brand-* tokeni,
  font-serif brojevi); kartica niske zalihe dobija amber isticanje kad je
  `low_stock_products > 0`. `v-else` prikazuje prostu dobrodošlicu sa
  imenom korisnika (`usePage().props.auth.user.name`).
- Nema nove tabele/migracije — čisto čitanje postojećih `orders`/
  `products` tabela.
- Test: `tests/Feature/DashboardTest.php` (2 — admin vidi tačne brojke
  preko seed-ovanih porudžbina/proizvoda, običan korisnik dobija
  `stats: null`).

**Deo 2 — deljene admin komponente** (`resources/js/Components/Admin/`),
uspostavljene NA Categories, za ponovnu upotrebu u sledeća 3 koraka:
- `AdminPageHeader.vue` — `title` prop (font-serif h1) + `#actions` slot
  (dugme "+ Dodaj ...").
- `AdminTable.vue` — `headers` (niz stringova za `<thead>`, `bg-brand-card`)
  + default slot za `<tbody>` redove (pozivalac isporučuje `<tr>`/`<td
  class="px-6 py-4">`) + `isEmpty`/`emptyMessage` za prazno stanje. Border
  stil (`divide-y divide-black/5`), ne zebra — isti obrazac kao Checkout
  cart lista/AddressManagement.
- `StatusBadge.vue` — `active` (Boolean) → "Aktivno" (zeleno) / "Neaktivno"
  (sivo, `bg-black/5`). Namerno NE brand-accent za status (jedna topla
  boja ne nosi semantiku aktivno/neaktivno) — zeleno/sivo je čitljivije za
  skeniranje tabele.
- `Categories/Index.vue` koristi sve tri; `Categories/Create.vue`/
  `Edit.vue` koriste POSTOJEĆE Breeze deljene forme komponente
  (`InputLabel`/`TextInput`/`InputError`/`Checkbox`/`PrimaryButton`, već
  brand-stilizovane od Faze 6 koraka 5) — nema duple stilizacije inputa.
  `Otkaži` dugme je `<Link>` sa ručno kopiranim `SecondaryButton` klasama
  (ne sam `SecondaryButton` — on renderuje `<button>`, ugnježden `<a>`
  unutar `<button>` je nevalidan HTML i ne bi radio kao Inertia navigacija).
- **`DeleteConfirmation.vue` DIRANA** (deljena, koristi je i Products/Books/
  Authors/Publishers — pet admin sekcija odjednom, ne samo Categories):
  prebačena na brand-* tokene, sad iznutra koristi stvarne
  `SecondaryButton`/`DangerButton` komponente (manje duplog stila, crvena
  boja za destruktivnu akciju nedirana — isti obrazac kao `DangerButton`
  svuda drugde). Tekst preveden na srpski; poruka potvrde više NE koristi
  `itemType` prop gramatički uklopljen u rečenicu (npr. "obrišite category
  X" bi na srpskom zahtevalo padežnu deklinaciju po tipu — "kategoriju"/
  "autora"/"izdavača" — fragilna mapa za 5 poziva); umesto toga generička
  rečenica sa `itemName` u podebljanom tekstu ("Da li želite trajno da
  obrišete **{{ itemName }}**?"), `itemType` prop ostaje deklarisan
  (nekorišćen u tekstu) radi kompatibilnosti sa 5 postojećih poziva.
  Ostale 4 admin sekcije (Products/Books/Authors/Publishers) i dalje imaju
  stari indigo/engleski izgled OKO modala — prihvaćena privremena
  nedoslednost dok ne dođu na red u narednim koracima (isti obrazac kao
  `AddressManagement.vue` u Faza 6 koraku 4).
- Backend flash poruke u `CategoryController` prevedene na srpski
  ("Kategorija je uspešno dodata/izmenjena/obrisana.") — vidljive korisniku
  preko istog `page.props.flash.success` mehanizma.
- Tekst preveden: "Add New category"→"Dodaj kategoriju", "Edit"→"Izmeni",
  "Actions"→"Akcije", "Active"/"Inactive"→"Aktivno"/"Neaktivno", itd.
- Testovi: `tests/Feature/Admin/AdminAccessTest.php` (56 postojećih, nema
  markup/tekst asercija — samo status kod/DB, redizajn ih nije mogao
  pokvariti, potvrđeno pokretanjem). `php artisan test`: 384 passed (382 +
  2 nova). `npm run test`: 52 passed (nepromenjeno — nema novih JS testova,
  Vue komponente su nove ali bez postojeće vitest infrastrukture za admin
  stranice u ovom koraku). `npx vite build` prolazi. Vizuelno provereno
  (headless Chrome screenshot preko privremenih, necommit-ovanih preview
  ruta — Dashboard sa lažnim brojkama, Categories Index/Create/Edit sa
  pravim podacima iz dev baze — obrisane posle provere, nisu deo PR-a):
  kontrast, font-serif naslovi, status bedževi, amber isticanje niske
  zalihe.

## Dizajn — Admin panel, korak 2: Products + Books + slike korica (Deo 0-4)

### Deo 0 — Izvodljivost automatskog uvoza korica sa Open Library — REZULTAT: ~6%, ODLUČENO DA SE NE GRADI
Testirano na SVIH 33 knjiga sa `isbn13` u bazi (manje od predloženih 30-50,
ali to je cela populacija kandidata). Signal za "nema korice" utvrđen
empirijski (Open Library nema 404 za nepostojeću koricu — vraća 302 redirect
ka archive.org): **1×1 GIF, tačno 43 bajta**, identičan hash na 4 nezavisna
lažna ISBN-a; prava korica je uvek JPEG od nekoliko desetina KB. `Content-
Length` header je nepouzdan (uvek `0` zbog redirect-a) — mora se meriti
veličina fajla NAKON što se isprati redirekcija (`curl -L`).
**Rezultat: 2/33 (~6%)** — pogodak samo za "Seobe" (Crnjanski) i "Tesla,
portret među maskama" (Pištalo), oba vizuelno potvrđena kao ispravne korice.
Ostalih 31 (Andrić, Kiš, Pekić, Selimović, Pavić...) — promašaj, očekivano za
domaće autore na anglo-centričnom katalogu.
**Odluka korisnika** (pitanje postavljeno eksplicitno posle ovog rezultata,
ispod 15-20% praga iz zadatka): **NE graditi Deo 1** (`catalog:fetch-covers`
automatizacija) — fokus samo na Deo 2 (ručni upload). Ako katalog kasnije
poraste ka 300-500 knjiga i i dalje treba ovo, ~6% stopa i metodologija
(43-bajtni GIF signal) ostaju ovde zapisani za ponovnu procenu, ne treba
ponovo empirijski istraživati od nule.

### Deo 1 — PRESKOČEN (odluka korisnika, vidi Deo 0)

### Deo 2 — Ručni upload slike (fallback, sad i jedini put)
`app/Support/ProductImageUploader.php` (nov, deljen između `ProductController`
i `BookController` preko `BookRequest` — obe admin sekcije pišu u istu
`products.image` kolonu, isti `'public'` disk). `resolve(Request $request,
?string $currentImage): ?string` — tri ishoda:
1. Nov fajl otpremljen (`image`) → sačuvaj (`Storage::disk('public')->
   store('products', ...)`), obriši stari LOKALNI fajl ako je postojao.
2. `remove_image` checkbox → obriši trenutnu (lokalnu) sliku, postavi `NULL`.
3. Ni jedno ni drugo → vrati POSTOJEĆU vrednost nedirnutu — bitno za Edit
   forme: `<input type="file">` se ne može unapred popuniti postojećim
   URL-om (HTML ograničenje), pa "ništa nije izabrano" mora značiti "ne
   diraj sliku", ne "obriši je".
- **"Lokalna" detekcija za brisanje** — poredi prefiks URL-a sa
  `Storage::disk('public')->url('')`, NE `config('app.url') . '/storage/'`
  (ručna konkatenacija). Eksterni URL (npr. postojeći `picsum.photos` unos)
  se nikad ne pokušava obrisati — nije na našem disku.
  **Gotcha otkriven pri pisanju testa:** `Storage::fake('public')` u
  testovima GUBI eksplicitan `'url'` config (vidi
  `Storage::buildDiskConfiguration()` u frameworku — ne prenosi 'url' iz
  originalnog config-a) i vraća RELATIVNU putanju (`/storage/...`) umesto
  pune (`http://.../storage/...`). Ručna `config('app.url')` konkatenacija
  bi tiho promašila poređenje u testovima (fajl bi "izgledao eksterno" i
  nikad se ne bi obrisao) iako u produkciji radi — otkriveno kroz 2 padajuća
  testa, popravljeno korišćenjem `Storage::disk('public')->url('')` kao
  izvora istine umesto pretpostavke o formatu.
- Validacija (i `ProductController` i `BookRequest`): `nullable|file|
  mimes:jpg,jpeg,png,webp|max:2048` + `remove_image => nullable|boolean`.
  `products.image` je već bio `nullable` u šemi (Faza 1) — bez migracije.
- **Frontend:** `Admin/Products/Create.vue`/`Edit.vue` i deljeni
  `Admin/BookForm.vue` (koristi ga i `Admin/Books/Create.vue`/`Edit.vue`) —
  `<input type="file">` stilizovan preko Tailwind `file:*` varijanti
  (`file:bg-brand-accent` dugme). Edit forme dodatno prikazuju trenutnu
  sliku (`BookCoverPlaceholder` preview, 128×128) + "Ukloni trenutnu sliku"
  checkbox (samo kad `product.image`/`book.image` postoji). Inertia
  `useForm().put(url, { forceFormData: true })` — File u payload-u
  automatski prebacuje zahtev na multipart + `_method` spoofing (Inertia-ino
  ugrađeno ponašanje, ne ručna logika).
  **`BookRequest::productAttributes()` namerno NE uključuje `'image'`** —
  FormRequest nema pristup postojećoj `products.image` vrednosti bez
  route-bound `$book` (koji ne postoji na `store()`), pa `BookController`
  eksplicitno dodaje `ProductImageUploader::resolve($request, $book?->
  product?->image)` u rezultat pre poziva `BookService`.
- **Testovi popravljeni (regresija otkrivena pri pisanju):** `bookPayload()`
  (`tests/Concerns/BuildsBookPayload.php`) i dva mesta u
  `AdminAccessTest.php` su slali `'image' => 'https://example.com/...'`
  (URL string) — validno pod STARIM `url` pravilom, nevalidno pod novim
  `file` pravilom. Uklonjeno iz payload-a (nullable, izostavljanje je
  ispravno); jedna asercija u `BookCrudTest.php` promenjena sa "image je
  sačuvan URL" na "image je NULL" (payload ga više ne šalje).
  `database/factories/ProductFactory.php`'s `'image' => 'https://
  picsum.photos/...'` NIJE dirano — factory piše direktno u DB preko
  Eloquent-a, ne prolazi kroz HTTP validaciju, pa i dalje ispravno seed-uje
  postojeće demo proizvode sa URL slikama.
- Nov `tests/Feature/Admin/ProductImageUploadTest.php` (9 testova — upload
  uspešan/kreira storage fajl, odbija pogrešan mime, odbija fajl preko
  2048 KB, edit bez nove slike ne menja postojeću, `remove_image` briše i
  fajl i vrednost, zamena briše staru LOKALNU sliku, zamena NE dira eksterni
  URL, isto za Book put preko `BookRequest`). `UploadedFile::fake()->
  create(...)` namerno, NE `->image()` — potonji zahteva GD/Imagick
  ekstenziju, potvrđeno odsutnu u ovom okruženju (`php -m | grep -i gd`
  prazno) PRE pisanja testa.

### Deo 3 — BookCoverPlaceholder.vue robusnost
`<img>` dobija `@error` handler → `imageFailed` ref → pada nazad na
tipografski placeholder (isti kao da `image` prop nikad nije ni postojao).
`watch(() => props.image, ...)` resetuje zastavicu kad se prop promeni
(sprečava da ostane trajno "failed" iz prethodnog URL-a na istoj mount-ovanoj
instanci, npr. u tabeli gde redovi mogu da se re-renderuju). Ponovo
iskorišćeno kao thumbnail u `Admin/Products/Index.vue` i
`Admin/Books/Index.vue` (Deo 4) — null `image` prop tamo je sad čest slučaj
otkad URL više nije obavezan, pa ovaj fallback nije samo teoretski.
Test: `BookCoverPlaceholder.test.js` (nov — trigger `error` event na `<img>`,
potvrdi pad na placeholder sa naslovom/autorom).

### Deo 4 — Products + Books CRUD redizajn
Ponovo korišćen obrazac iz koraka 1 (`AdminPageHeader`/`AdminTable`/
`StatusBadge`, `DeleteConfirmation` nedirana ovde) — nijedna nova
tabela/header komponenta.
- `Admin/Products/Index.vue` — thumbnail kolona (`BookCoverPlaceholder`,
  48×48) sad ima smisla otkad slike stvarno postoje/mogu nedostajati
  elegantno. `formatPrice()` iz `lib/bookLabels.js` (isti razlog kao
  katalog — broj sa SQLite-a, string sa MariaDB-a).
- `Admin/Books/Index.vue` — isti pattern + thumbnail, zadržan nisko-zaliha
  filter dugme i `RestockForm` (Faza 5) u koloni akcija, nedirano
  funkcionalno.
- `Admin/Components/RestockForm.vue` — sitna vizuelna uskladba (brand-*
  border/focus/tekst boje), `emerald-600` trigger/submit dugme namerno
  ZADRŽANO (semantička razlika od `brand-accent` — "dopuni zalihu" je
  distinktna pozitivna akcija, ne generička primarna akcija).
- Flash poruke u `ProductController` prevedene na srpski ("Proizvod je
  uspešno dodat/izmenjen/obrisan.") — `BookController`/`CategoryController`
  su već bili na srpskom od ranijih koraka.
- **Poznato, NEDIRANO (pre-postojeće, van opsega ovog koraka):** primećeno
  pri vizuelnoj proveri da `Admin/Products/Edit.vue`'s "Kategorija" select
  prikazuje prazno kad proizvod pripada DEAKTIVIRANOJ kategoriji (npr.
  legacy `Electronics` posle `catalog:cleanup-legacy-categories`, Faza 3
  deo 3) — `ProductController::edit()`/`create()` filtriraju `categories`
  prop na `is_active=true`, pa `form.category_id` ne poklapa nijednu
  `<option>`. Pre-postojeće ponašanje (isti filter je bio prisutan i pre
  ovog koraka), nedirano — nije u opsegu "Products/Books CRUD redizajn".
- Testovi: puna `tests/Feature/Admin/` (154, uključujući `AdminAccessTest`
  markup-neosetljive provere) i pun suite (**393 passed**, 384 + 9 novih iz
  Dela 2) prolaze. `npm run test`: 53 passed (52 + 1 novi iz Dela 3).
  `npx vite build` prolazi. Vizuelno provereno (headless Chrome screenshot
  preko privremenih, necommit-ovanih preview ruta sa pravim podacima iz dev
  baze — obrisane posle provere): Products/Books Index sa thumbnail-ovima i
  bedževima, Create/Edit forme sa file upload-om i "ukloni sliku" tokom
  (uključujući stvaran network round-trip do `picsum.photos` za postojeću
  demo sliku — potvrđeno da prikaz radi, prvi screenshot je uhvatio
  mid-load stanje pre nego što je slika stigla, drugi sa dužim
  `--virtual-time-budget` je potvrdio ispravan prikaz).

## Dizajn — Admin panel, korak 3: Authors + Publishers

### Deo 1 — Authors
Prelazi na brand-* tokene, srpski tekst, `AdminPageHeader`/`AdminTable`
(BEZ `StatusBadge` — `Author` nema `is_active`/status polje). Index
zadržava kolonu "Knjiga" (`books_count`) i `Pagination`, nedirano
funkcionalno.
- **`authors.photo` prebačen sa URL text input-a na pravi file upload**
  (multipart, `mimes:jpg,jpeg,png,webp`, `max:2048`, isti `'public'` disk),
  isti obrazac kao `products.image` iz koraka 2 (Deo 2).
- **Generalizacija umesto duplirane klase:** `app/Support/
  ProductImageUploader.php::resolve()` je parametrizovan sa tri nova
  opciona argumenta — `$fileField = 'image'`, `$removeField =
  'remove_image'`, `$folder = 'products'`. Svi postojeći pozivi
  (Products/Books, korak 2) ih izostavljaju i zadržavaju stare default-e —
  ponašanje im nepromenjeno (potvrđeno: `ProductImageUploadTest`, 9/9,
  prolazi bez izmena). `AuthorController::store/update` pozivaju
  `ProductImageUploader::resolve($request, $currentPhoto, 'photo',
  'remove_photo', 'authors')`. Izabrana generalizacija umesto tankog
  analognog `AuthorPhotoUploader`-a jer je izmena čisto aditivna (nova
  opciona polja sa default-ima koji reprodukuju staro ponašanje) — nije
  bilo potrebe dirati `ProductController`/`BookController`/`BookRequest`
  pozive, pa nije bilo rizika za regresiju na već mergovanom kodu iz
  koraka 2.
  `AuthorRequest::rules()`: `photo` sad `nullable|file|mimes:...|max:2048`
  (bilo `nullable|url|max:255`), dodato `remove_photo =>
  nullable|boolean`. `AuthorController::store/update` grade `$data` iz
  `$request->validated()`, uklanjaju `remove_photo` (nije `authors`
  kolona) i prepisuju `photo` rezolvovanom vrednošću — isti obrazac kao
  `BookRequest::productAttributes()` + `ProductImageUploader::resolve()`
  u `BookController` (korak 2).
- `AuthorForm.vue`: prepisan po uzoru na `BookForm.vue` (koristi
  `InputLabel`/`TextInput`/`InputError`/`Checkbox`/`PrimaryButton`, `file:*`
  Tailwind varijante za file input, preview trenutne fotografije preko
  `BookCoverPlaceholder` — radi i za autora bez ijedne izmene, `author`
  prop u `BookCoverPlaceholder` je opcioni sa default-om `''`).
  **Thumbnail oblik: kvadratni (`rounded-lg`), NE kružni (`rounded-full`)**
  — vizuelno provereno da kružni `overflow-hidden` maskira
  `BookCoverPlaceholder`-ov tipografski fallback (placeholder tekst je
  layoutovan za kvadrat/`line-clamp`, kružna maska mu odseca uglove i tekst
  postaje nečitljiv na malim veličinama, npr. 48×48 u Index tabeli). Isti
  fallback se koristi i za placeholder BEZ fotografije (većina autora u dev
  bazi trenutno nema `photo`), pa je ovo česta putanja, ne rubni slučaj —
  zadržan kvadratni oblik i u Index tabeli (48×48) i u forma-preview-u
  (128×128), isti kao Products/Books (korak 2), umesto uvođenja novog
  oblika samo za autore.
- `Admin/Authors/Index.vue`: dodata kolona "Fotografija" (thumbnail,
  `BookCoverPlaceholder`), stranica prebačena na `bg-brand-page` wrapper +
  `flash.error` prikaz (ranije je postojao samo `flash.success`, isti kao
  `Admin/Books/Index.vue` otkad postoji `error` flash za "autor ima
  knjige, ne može se obrisati").

### Deo 2 — Publishers
`Publisher` nema sliku ni status polje (samo `name`/`slug`/`website`) —
najprostiji preostali CRUD, čisto vizuelni prelaz na brand-* tokene +
`AdminPageHeader`/`AdminTable` (isti obrazac kao Categories u koraku 1).
Nikakve backend izmene — `PublisherController`/`PublisherRequest` već
imaju srpske flash poruke i ispravnu `website` (`url`) validaciju od
ranije, nedirano.

### Testovi
`AuthorPublisherCrudTest.php` (postojeći, 10 testova) — dva testa su slala
`photo` kao URL string (isti problem kao `BuildsBookPayload` u koraku 2,
Deo 2): `test_admin_dodaje_autora_...` je jednostavno izostavio `photo` iz
payload-a (nije predmet tog testa), `test_autor_zahteva_ime_...` je
preimenovan u `..._ispravan_tip_fotografije` i sad šalje
`UploadedFile::fake()->create('dokument.pdf', ...)` umesto stringa
`'nije-url'`. Svih 10 i dalje prolazi.
Nov `tests/Feature/Admin/AuthorPhotoUploadTest.php` (5 testova — manji
skup od `ProductImageUploadTest`-a, ista logika je već pokrivena tamo):
upload pri kreiranju, izmena bez nove fotografije ne menja postojeću,
`remove_photo` briše fajl i vrednost, zamena briše staru LOKALNU
fotografiju, zamena NE dira eksterni URL.
Pun suite: `php artisan test` — **398 passed** (393 + 5 novih). `npm run
test`: 53 passed (nepromenjeno — nema novih JS testova, isti razlog kao
korak 1: nema postojeće vitest infrastrukture za admin Vue stranice van
onoga što je već pokriveno). `npx vite build` prolazi. Vizuelno provereno
(headless Chrome screenshot preko privremene, necommit-ovane preview rute
koja loguje postojećeg admin korisnika iz dev baze — obrisana posle
provere, nije deo PR-a): Authors Index (kvadratni thumbnail-ovi, prazan
placeholder za autore bez fotografije), Authors Create (prazna forma sa
file input-om), Authors Edit (postojeća fotografija — privremeno
postavljena preko `tinker` na test URL, vraćena na `NULL` posle provere —
preview + "Ukloni trenutnu fotografiju" checkbox), Publishers Index
(12 izdavača iz dev baze, konzistentno sa Categories iz koraka 1).

## Dizajn — Admin panel, korak 4: Orders (POSLEDNJI korak admin panel faze)
Ovim je Faza 6 (Shop/Product → Cart/Checkout → Breeze auth → admin panel)
u celosti gotova.

### Deo 1 — Index.vue
Prelazi na brand-* tokene, srpski tekst (bilo "Orders"/"Buyer"/"Guest"/
"Details"), `AdminPageHeader`/`AdminTable` — **bez** dugmeta u header
akcijama (porudžbine se ne kreiraju ručno kroz admin).
- Ručno pisana paginacija (duplirala je logiku iz deljene `Pagination.vue`)
  zamenjena sa `<Pagination :links="orders.links" />` — ista komponenta kao
  Books/Authors/Publishers.
- Nov `resources/js/Components/Admin/OrderStatusBadge.vue` — `StatusBadge.vue`
  iz koraka 1 je boolean (aktivno/neaktivno), ne pokriva 8 mogućih Order
  statusa. Boje/labele žive u novom `resources/js/lib/orderLabels.js`
  (`orderStatusLabels`, `orderStatusStyles`) — isti obrazac kao
  `bookLabels.js`. Boje namerno odstupaju od brand-* palete gde semantika
  to zahteva (amber=na čekanju, plavo=u obradi/poslato, zeleno=plaćeno/
  završeno, sivo=dostavljeno, crveno=otkazano/neuspelo) — isti princip kao
  `StatusBadge` odluka iz koraka 1 (jedna topla boja ne nosi više stanja).
  Pill oblik/tipografija dosledni ostatku admin panela.
- `paymentMethodLabels` (isti fajl) — `cod`→"Pouzećem", `paypal`→"PayPal /
  kartica", isti tekst kao `Checkout.vue`.

### Deo 2 — Show.vue
Prelazi na brand-* tokene, srpski tekst, isti vizuelni jezik (kartice,
`font-serif` naslovi).
- **Adresa isporuke sad čita `shipping_*` snapshot kolone**
  (`shipping_recipient_name/phone/line1/line2/city/postal_code/country` —
  imena potvrđena u `database/migrations/
  2026_09_29_100001_add_shipping_snapshot_to_orders_table.php`), ne stari
  `order.address`/`city`/`postal_code` string — stari prikaz nije imao
  `line2` ni državu uopšte, netačno za slanje pošiljke.
  **Fallback za istorijske porudžbine:** `shipping_*` kolone su nullable i
  prazne za porudžbine kreirane PRE "Cart/Checkout redizajn + sačuvane
  adrese" (Faza 6, korak 4) — `hasShippingSnapshot = Boolean(order.
  shipping_line1)` grana prikaz nazad na stare `address`/`city`/
  `postal_code`/`phone` kolone (dual-write ih i dalje puni, vidi taj korak)
  uz kratku napomenu iznad bloka, umesto praznih polja.
- **Status akcije PROŠIRENE, ne samo redizajnirane.** Stari UI je imao
  dugmad SAMO kad je `order.status === 'pending'` (Plaćeno/U obradi/
  Otkazano) — nije postojao put do `shipped`/`delivered` iz UI-ja uopšte,
  iako ih `OrderController::update()` prihvata. Sad dostupne akcije zavise
  od TRENUTNOG statusa preko `orderStatusTransitions` mape (nov
  `resources/js/lib/orderLabels.js`):
  ```
  pending    → processing, paid, cancelled   (nepromenjeno iz starog UI-ja)
  processing → paid, shipped, cancelled
  paid       → shipped, cancelled
  shipped    → delivered
  delivered / completed / cancelled / failed → terminalno, nema akcija
  ```
  **Obrazloženje redosleda:** sistem sam postavlja samo `pending`
  (kreiranje porudžbine), `paid` (uspešan PayPal capture) i `cancelled`/
  `failed` (PayPal cancel/neuspeh, sa povratom zaliha — Faza 5). Ostala tri
  statusa (`processing`/`shipped`/`delivered`) su ISKLJUČIVO ručne admin
  akcije — praktično najviše za COD porudžbine, koje ostaju `pending` dok
  admin ručno ne vodi kroz tok ispunjenja. Otud je otkazivanje dostupno dok
  god porudžbina NIJE poslata (posle `shipped` otkazivanje više nema
  smisla u ovom modelu — roba je već na putu). `completed` nema automatski
  put do njega (nijedan kod u aplikaciji ga ne postavlja) — ostavljen kao
  terminalna, bez akcija, dokumentovano ovde radi transparentnosti, ne
  brisan iz enum-a (backend validacija ga i dalje prihvata, van obima ove
  izmene da se menja enum). **Ne enforce-uje se na backend-u** —
  `OrderController::update()` i dalje prihvata bilo koji od 8 statusa
  (`required|in:...`), ovo je čisto UI vođenje kroz smislen redosled, lako
  izmenjivo kasnije ako se pokaže pogrešno.
  Flash poruke u `OrderController::update()`/`destroy()` prevedene na
  srpski ("Status porudžbine je izmenjen.", "Porudžbina je obrisana.").
- Native `confirm()` za promenu statusa ostaje (nije destruktivna akcija,
  poseban modal bi bio nepotrebno širenje obima). Brisanje porudžbine sad
  ide preko postojeće `DeleteConfirmation.vue` (dosledno ostalih 5 admin
  sekcija) umesto native `confirm()`.

### Deo 3 — NAMERNO NEDIRANO (van obima ovog koraka)
- Otkazivanje porudžbine iz admin panela tada NIJE vraćalo zalihu (samo
  `$order->update()`), za razliku od PayPal cancel/fail toka
  (`InventoryService::restoreStock`, Faza 5). **✅ REŠENO naknadno u
  housekeeping čišćenju** (vidi "Housekeeping — UX doslednost + otkazivanje
  vraća zalihu" niže) — zabeleženo ovde samo kao istorijski kontekst zašto
  ovaj korak nije dirao to ponašanje.
- Autorizacija (`admin` middleware) — nedirana, van obima ovog koraka.

### Testovi
Postojeći `tests/Feature/Admin/AdminAccessTest.php` (`orders.index/show/
update/destroy` u centralnoj listi) i dalje prolaze nepromenjeni — ne
asertuju na markup/tekst, samo status kod/DB (isti oprez kao ranije,
potvrđeno pokretanjem). Backend transition logiku NE enforce-uje (vidi
Deo 2), pa nema novih backend testova za redosled prelaza.
Nov `resources/js/Pages/Admin/Orders/Show.test.js` (9 testova, vitest) —
pokriva status-prelaz UI logiku direktno (pending/processing/paid/shipped
prikazuju očekivana dugmad, delivered/cancelled/failed/completed su
terminalni bez ijednog dugmeta, klik zove `router.patch` sa tačnim
statusom) i adresu isporuke (koristi `shipping_*` kad postoji, pada nazad
na stara polja kad ne postoji). `route()` u `<template>`-u (DeleteConfirmation
`:delete-url`) mockovan preko `global.mocks`, `route()` u `<script setup>`
JS pozivima preko `globalThis.route` — isti gotcha kao Shop.test.js/
Checkout.test.js iz ranijih koraka (vidi CLAUDE.md "Pretraga i filteri
kataloga").
Pun suite: `php artisan test` — **398 passed** (nepromenjeno — samo
prevod flash poruka, bez nove backend logike koja bi trebalo testirati).
`npm run test`: 62 passed (53 + 9 novih). `npx vite build` prolazi.
Vizuelno provereno (headless Chrome screenshot preko privremene,
necommit-ovane preview rute — obrisana posle provere): Orders Index
(bedževi u 5 boja, pagination), Show za `processing` (tri dugmeta: Plaćeno/
Poslato/Otkazano), `shipped` (samo Dostavljeno), i legacy porudžbinu bez
`shipping_*` snapshot-a (fallback tekst + stara polja). Test podaci (6
porudžbina, po jedna za svaki relevantan status) kreirani preko `tinker` i
obrisani odmah posle provere — nisu deo PR-a niti ostali u dev bazi.
Usput otkriven i ispravljen sitan whitespace bag: "Registrovan korisnik:"
i vrednost su se lepili bez razmaka kad su `<dt>`/`<dd>` bili na odvojenim
linijama u template-u (Vue-ov whitespace: 'condense' briše newline između
tagova, ne kolabira ga u razmak) — ispravljeno spajanjem na jednu liniju,
isti obrazac kao ostali `<dt>`/`<dd>` parovi na istoj stranici.

## Housekeeping — UX doslednost + otkazivanje vraća zalihu
Čišćenje 4 sitne, nepovezane stavke (3 su bile zabeležene kao otvorene u
ovom fajlu, 1 nova) — ne nova faza, jednokratni housekeeping PR.

**Deo 1 — Logo/Shop istaknutiji u headeru.** `AuthenticatedLayout.vue`:
logo link `text-xl` → `text-2xl` (`Logo.vue` sam skalira ikonu/tekst preko
nasleđenog `font-size`, nedirano). `NavLink.vue` dobija opcioni `size` prop
(`'sm'` default, `'base'` za veći tekst) — menja se UNUTAR istog
`computed()` bloka koji već gradi klase, ne kroz spoljni `class` override
(Tailwind-ov generisani CSS ne garantuje redosled klasa iz template-a, pa bi
spoljni `text-base` mogao tiho izgubiti od unutrašnjeg `text-sm`). Samo Shop
link u headeru dobija `size="base"` (pored postojećeg `font-semibold`);
svih 6 admin nav linkova (Categories/Products/Books/Authors/Publishers/
Orders) ostaju na default `'sm'` preko istog `NavLink.vue`, nedirano.
`GuestLayout.vue` logo (`text-4xl`) nije dirano — drugačiji kontekst (auth
stranice, bez Shop linka pored za poređenje), vizuelno već dovoljno
istaknut.

**Deo 2 — Select sačuvanih adresa vizuelno nerazlučiv od text inputa**
(`Checkout.vue`). Dodat `lucide-vue-next` `ChevronDown` apsolutno
pozicioniran desno u polju (`pointer-events-none` da ne ometa klik na sam
select) + `cursor-pointer` na samom `<select>`-u — čisto vizuelna
afordanca, funkcionalnost (`v-model="form.address_id"`) nedirana.

**Deo 3 — Deaktivirana kategorija prazan select** (`Admin/Products/
Edit.vue`). Potvrđen uzrok: `ProductController::edit()` šalje `categories`
prop filtriran na `is_active=true`; ako proizvod pripada kategoriji koja je
u međuvremenu deaktivirana (npr. preko `catalog:cleanup-legacy-categories`,
Faza 3 deo 3), `form.category_id` se ne poklapa ni sa jednim `<option>` i
select izgleda prazan. Fix: nov `categoryOptions` computed u `Edit.vue`
— ako trenutna kategorija proizvoda nije među aktivnim, doda se na kraj
liste sa oznakom "(neaktivna)" u tekstu opcije (npr. "Electronics
(neaktivna)"), umesto da bude tiho izostavljena. Čisto frontend fix, bez
backend izmene — `product.category` relacija je već bila učitana i
prosleđena (`$product->load('category')`), samo nekorišćena za ovu svrhu.
**Napomena:** identičan filter (`is_active=true` bez uključivanja trenutne
kategorije) postoji i u `BookController::formOptions()` za Books
Create/Edit — nije potvrđeno da se manifestuje (knjige se ređe prebacuju
u legacy kategorije), ali isti obrazac fix-a bi važio ako se ikad primeti;
nije dirano ovim taskom (van navedenog obima, samo `Products/Edit.vue`).

**Deo 4 — Ručno otkazivanje porudžbine ne vraća zalihu**
(`Admin/Orders`, otvoreno od koraka 4). `InventoryService::restoreStock()`
već postoji i radi tačno ovo (Faza 5) — `OrderController::update()` (Admin)
sad ga poziva kad `$validated['status'] === 'cancelled'`, umesto plain
`$order->update()`. Za SVE ostale statuse (`processing`/`paid`/`shipped`/
`delivered`/...) ostaje nepromenjen plain update — `restoreStock()` je
namenjen isključivo terminalnom "porudžbina neće biti ispunjena" prelazu i
sam postavlja status unutar zaključane transakcije (ne duplirati poziv).
`reason` string: `'admin_cancel'` — postojeći PayPal pozivi koriste
`'cancel'` (buyer/PayPal-inicirano otkazivanje) i `'payment_failed'`, nova
vrednost razlikuje trigger izvor u `stock_movements` istoriji. Dodato u
`StockMovement::REASONS` konstantu (bila zatvorena lista `['order',
'cancel', 'payment_failed', 'restock', 'manual']`, sad + `'admin_cancel'`)
— `tests/Feature/Catalog/StockMovementTest.php` asertuje `reason` protiv
ove konstante, pa je trebalo produžiti listu, ne samo koristiti vrednost
mimo nje. `Admin\OrderController` sad prima `InventoryService` kroz
konstruktor (isti obrazac kao `BookController`), ne `app()` helper.

### Testovi
Nov `tests/Feature/Admin/OrderStatusUpdateTest.php` (3 testa, isti obrazac
kao `PayPalPaymentTest.php`'s restoreStock testovi): otkazivanje vraća
zalihu i upisuje `stock_movements` red (`reason = 'admin_cancel'`),
dvostruko otkazivanje ne duplira povrat (isti guard kao PayPal tok), ostali
statusi (npr. `shipped`) ne diraju zalihu niti upisuju `stock_movements`.
Postojeći `AdminAccessTest::test_admin_can_update_order_status` (status
`shipped`, ne `cancelled`) i `StockMovementTest` i dalje prolaze
nepromenjeni. `php artisan test`: **401 passed** (398 + 3 nova). `npm run
test`: 62 passed (nepromenjeno — Deo 1-3 su čist CSS/markup/select-opcije,
bez nove JS logike koja bi trebalo testirati; potvrđeno pokretanjem da
postojeći `Checkout.test.js` i dalje prolazi). `npx vite build` prolazi.
Vizuelno provereno (headless Chrome screenshot preko privremene,
necommit-ovane preview rute — obrisana posle provere, test adresa kreirana
za proveru Dela 2 obrisana odmah posle): header sa istaknutim Shop linkom
(`/shop`), Checkout select sa ChevronDown strelicom (privremena sačuvana
adresa admin naloga), `Admin/Products/Edit.vue` za proizvod #3 (Sony
slušalice, kategorija "Electronics" deaktivirana preko ranijeg
`catalog:cleanup-legacy-categories`) — select sad prikazuje "Electronics
(neaktivna)" umesto praznog polja.

## ✅ REŠENO — admin lista knjiga nije prikazivala slike
`Admin\BookController@index` je eager-load-ovao `product:id,name,slug,price,
stock,is_active` bez `image`, pa je `book.product.image` u `Admin/Books/
Index.vue` uvek bio `undefined` i `BookCoverPlaceholder` je padao na
tipografski fallback (šema/putanje nisu bili problem). Dodato `image` u
select. Regresija: `tests/Feature/Admin/BookIndexImageTest.php` (pada bez
ispravke). Pravilo: kad se `product:` select-uje po kolonama, proveri da li
Vue stranica čita još neko polje.

## ✅ REŠENO — header na srpskom (latinica)
`AuthenticatedLayout.vue` (jedina nav traka; `GuestLayout.vue` nema navigaciju)
je imao engleske labele, i za gosta i za admina: Shop→Prodavnica, Home→Početna,
Cart→Korpa, Categories/Products/Books/Authors/Publishers/Orders→Kategorije/
Proizvodi/Knjige/Autori/Izdavači/Porudžbine, Profile→Profil, Log Out→Odjava,
Log in→Prijava, Register→Registracija, `'Guest'`→`'Gost'` (desktop i mobilni
meni). Direktna zamena, bez i18n biblioteke. Nijedan JS/PHP test ne asertuje
na ove tekstove. Nove nav stavke pisati odmah na srpskom.

## ✅ REŠENO — brisanje proizvoda/kategorija (FK 1451) → soft delete
`DELETE /admin/products/{id}` i `/admin/categories/{id}` su pucali sa
QueryException 1451 (`order_items.product_id` je `restrict`; a
`products.category_id` je `cascade`, pa je brisanje kategorije povlačilo
brisanje proizvoda iz porudžbina).
- Migracija `2026_10_07_100000_add_soft_deletes_to_products_and_categories_table`
  samo dodaje nullable `deleted_at` (unazad kompatibilno, expand/contract).
  **Treba `php artisan migrate` na svakom okruženju** (deploy pipeline je
  već pokreće).
- `Product` i `Category` koriste `app/Models/Concerns/SoftDeletesFreeingSlug`:
  soft delete preimenuje slug u `slug-deleted-{id}` (slug je `unique`, id je
  jedinstven, kolona je varchar(255)) i `delete()` ide u `DB::transaction`.
  `restore()` slug NE vraća (nema restore UI-ja).
- **`Book` NEMA SoftDeletes (odluka).** `ProductObserver::deleted` tvrdo briše
  povezanu `Book` (author_book ide kaskadno preko FK) unutar iste transakcije
  kao soft delete proizvoda — pad brisanja knjige poništava i soft delete.
  Zato nema siročadi `Book` redova (koji bi lažno brojali
  `Author/Publisher::withCount('books')`, blokirali brisanje autora i držali
  `books.isbn13` unique indeks). Cena: restore proizvoda ne vraća knjigu.
  `forceDelete()` preskače observer (FK cascade radi isto).
- `Category` se ne briše dok ima (ne-obrisane) proizvode ili potkategorije
  (soft delete ne okida FK cascade/`nullOnDelete`) — flash `error`, prikazan
  na `Admin/Categories/Index.vue` i `Admin/Products/Index.vue` (dodat blok).
  `destroy` metode hvataju `QueryException` kao poslednju zaštitu.
- `OrderItem::product()`, `StockMovement::product()`, `Product::category()`
  su `withTrashed()` (stare porudžbine/istorija zaliha se i dalje prikazuju);
  `InventoryService::restoreStock` koristi `Product::withTrashed()` za povrat
  zaliha. Global scope ne važi za JOIN-ove, pa `BookCatalog::query`,
  `CatalogController::show` i `Admin\BookController@index` imaju eksplicitno
  `whereNull('products.deleted_at')`. Korpa (`/api/cart/products`) i
  `OrderController::store` (`Product::find`) tiho odbijaju obrisane proizvode.
- **Tvrdo brisanje u testovima/komandama:** testovi koji proveravaju DB-nivo
  FK (`restrict`/`nullOnDelete`) koriste `forceDelete()`.
  `catalog:cleanup-legacy-categories` je privremeno prebačena na
  `forceDelete()` (isto ponašanje kao ranije) — prelazi na soft delete u
  sledećem koraku.
- `Admin\BookController::destroy` i dalje odbija knjigu iz porudžbine (nije
  menjano) iako bi soft delete to sada dozvolio — poslovna odluka, otvoreno.
- **Validacija i soft delete:** `unique` nad `products.name`/`categories.name`
  (store i update, `ProductController`/`CategoryController`) i `products.slug`
  (`BookRequest`) koristi `Rule::unique(...)->whereNull('deleted_at')`, a
  `category_id` `Rule::exists('categories','id')->whereNull('deleted_at')` —
  soft-obrisan naziv ne blokira novi unos, a obrisana kategorija se ne prihvata.
  `ImportBooks` nije menjan: koristi `where('slug')` upite (global scope
  izuzima obrisane) a slug obrisanih redova je već preimenovan. `OrderController`
  `exists:products,id` namerno ostavljen: `Product::find` odmah posle daje
  poruku "knjiga više ne postoji".
- **Gotcha:** `catch (QueryException)` bez `use Illuminate\Database\QueryException;`
  u namespace-u kontrolera tiho ne hvata ništa (nepostojeća klasa, bez greške) —
  uhvaćeno tek pregledom PR-a; test forsira izuzetak preko `Model::deleting()`
  listenera i proverava flash `error`. Ne koristiti `sed` za višelinijske/
  backslash izmene `use` linija u Git Bash-u (tiho ne pogodi) — proveri `grep`-om.
- Testovi: `tests/Feature/Catalog/SoftDeleteTest.php` (17). Pun suite:
  `php artisan test` 420 passed.

## Planirano/otvoreno
Trenutno nema otvorenih UX/dizajn stavki.
