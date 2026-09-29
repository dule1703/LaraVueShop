# Dizajn — puna istorija (Faza 6)

Ovaj fajl čuva punu istoriju/obrazloženje vizuelnog redizajna (Faza 6,
koraci 1-4): debugging priče, WCAG kontrast računice, evoluciju arhitekture
kroz korake ("probali smo X, nije radilo, zato Y"). `CLAUDE.md` sadrži samo
distilovana pravila i gotcha upozorenja iz ovih koraka — kad ti treba
razlog/kontekst iza neke odluke, ili detalji koji nisu čist "rule of thumb",
ovde su.

Struktura prati `CLAUDE.md`: jedan odeljak po Faza 6 koraku, istim
naslovom kao odgovarajuća (sad skraćena) sekcija tamo.

---

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

---

## Dizajn — header/navigacija (Faza 6, korak 2)
Cilj: nav traka stvarno koristi `brand-*` tokene iz koraka 1 (ne samo
`Logo.vue`, koji je already bio ožičen). Samo `AuthenticatedLayout.vue` +
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

---

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
  Vidi sekciju **CI** u `CLAUDE.md`.

---

## Dizajn — Cart/Checkout redizajn + sačuvane adrese (Faza 6, korak 4)
Spaja vizuelni redizajn `Cart.vue`/`Checkout.vue` (brand-* tokeni, Lora
naslovi, srpski tekst — obe stranice su do sada bile na engleskom, jedini
preostali izuzetak od Faze 6) sa novom funkcionalnošću sačuvanih adresa
(`addresses` tabela), kako je i najavljeno u prethodnoj verziji `CLAUDE.md`.
Rađeno kroz dva paralelna pod-zadatka (backend Opus, frontend Sonnet — vidi
"Sub-agent pravila" u `CLAUDE.md`, IDOR-osetljiv deo je razlog za Opus).

### Data model
- `addresses` — `user_id` (FK users, `cascadeOnDelete`), `recipient_name`,
  `phone`, `line1`, `line2` (nullable), `city`, `postal_code`, `country`
  (default `'Srbija'`), `is_default` (boolean).
- `orders` dobija paralelne `shipping_*` snapshot kolone (`shipping_
  recipient_name/phone/line1/line2/city/postal_code/country`, sve nullable)
  + `shipping_address_id` (nullable FK → `addresses`, `nullOnDelete`, **čist
  audit trag** — prikaz porudžbine čita isključivo `shipping_*` kolone,
  nikad `shippingAddress` relaciju, isti princip kao `product_name`/
  `product_price` na `order_items`).
- **Namerni dual-write, ne propust:** `OrderController::store` i dalje puni
  i STARE `orders` kolone (`first_name`, `last_name`, `address`, `city`,
  `postal_code`, `phone`) za svaku novu porudžbinu — `Admin/Orders/Index.vue`
  i `Show.vue` ih i dalje čitaju direktno i nisu dirani ovim korakom.
  `first_name`/`last_name` se dobijaju naivnim split-om `recipient_name`-a na
  prvi razmak (dokumentovano pojednostavljenje — `Address` model namerno ima
  samo jedno `recipient_name` polje, ne odvojeno ime/prezime, pa višedelna
  imena mogu da se podele "pogrešno", npr. "Jovan Petar Jovanović" →
  first="Jovan", last="Petar Jovanović"; nema pouzdanog načina da se to
  izbegne bez kršenja zadatog Address modela).

### `app/Services/AddressService.php`
Isti obrazac kao `BookService`/`InventoryService` — sva upisivanja idu
isključivo odavde:
- `create()` — prva adresa korisnika je UVEK podrazumevana (nema druge u
  odnosu na koju bi bila "ne-podrazumevana"), bez obzira na `is_default` iz
  zahteva; svaka sledeća ide kroz `setDefault()` ako je `is_default`
  tražen.
- `update()` — `is_default` se namerno NE upisuje direktno (uvek preko
  `setDefault()`, jedino mesto koje garantuje "najviše jedna podrazumevana
  po korisniku").
- `setDefault()` — `DB::transaction`, skida `is_default` sa svih ostalih
  adresa korisnika pa postavlja na ovu.
- `delete()` — namerno NE unapređuje drugu adresu u podrazumevanu (produktna
  odluka, ne propust — korisnik sam bira sledeću).

### IDOR zaštita — `AddressController` (isti princip kao Faza 0, problem #5)
Svaki lookup ide kroz `$request->user()->addresses()->findOrFail($id)`
(relaciono skopiran upit), **nikad** `Address::find($id)` niti implicitni
`{address}` route-model-binding (zato je parametar rute običan `int`, ne
`Address` type-hint — implicitno bindovanje bi razrešilo adresu globalno,
preko svih korisnika, PRE bilo kakve provere). Tuđa i nepostojeća adresa
daju identičnu 404 (ne otkriva se koji ID postoji). `Gate::authorize()`
posle lookup-a je odbrana u dubini (bazni `Controller` u ovom Laravel 12
skeletu nema `AuthorizesRequests` trait, pa `$this->authorize()` ne postoji
— `Gate::authorize()` radi identično). `AddressPolicy` (view/update/delete,
`$address->user_id === $user->id`) je auto-discovered po konvenciji
(`App\Models\Address` → `App\Policies\AddressPolicy`), isto kao `OrderPolicy`
— nema ručne registracije, u ovom repo-u ne postoji `AuthServiceProvider`.
Rute: `POST /profile/addresses`, `PATCH /profile/addresses/{address}`,
`DELETE /profile/addresses/{address}`, `PATCH /profile/addresses/{address}/
default` — u postojećoj `auth` grupi pored `profile.*` ruta,
`whereNumber('address')` na sve tri `{address}` rute (nenumerički ID bi bez
toga bacio `TypeError`/500 na `int` parametru umesto 404).

### `OrderController::store` — novi oblik zahteva
Zamenjuje stare ravne `first_name/last_name/address/city/postal_code/phone`
prop bilo ILI `address_id` (ID sačuvane adrese ULOGOVANOG korisnika) ILI
`shipping.{recipient_name,phone,line1,line2,city,postal_code,country}`
(obavezno kad `address_id` nedostaje — `required_without:address_id`).
- **Gost nikad ne sme da pošalje `address_id`** (isti IDOR oblik kao Faza 0,
  problem #5) — ako pošalje, `ValidationException` na `address_id`, PRE
  bilo kakvog upisa u bazu.
- Ulogovan korisnik: `address_id` se razrešava isključivo kroz
  `Auth::user()->addresses()->find($id)` (relaciono skopirano) — tuđa i
  nepostojeća adresa daju **istu** poruku greške.
- `save_address` (checkbox "Sačuvaj kao podrazumevanu adresu") ima smisla
  samo kad `address_id` NIJE poslat (nova inline adresa) i korisnik je
  ulogovan — tada `AddressService::create(..., ['is_default' => true])` ide
  u ISTOJ `DB::transaction` kao kreiranje porudžbine (ako porudžbina padne,
  npr. nema zaliha, ni nova adresa ne ostaje).
- `shipping_address_id` na kreiranoj porudžbini je ID sačuvane adrese kad je
  korišćena (bilo postojeća preko `address_id`, bilo novosačuvana preko
  `save_address`), inače `NULL` (jednokratna adresa, nije sačuvana).

### Frontend
- **`Checkout.vue`** — potpuno redizajniran (brand-* tokeni, srpski tekst).
  `useForm` polja: `email, notes, payment_method, items, address_id,
  save_address, shipping{...}`. Ulogovan korisnik SA sačuvanim adresama
  vidi `<select>` (podrazumevana adresa je prva u nizu — backend šalje
  default-first, unapred izabrana) + opciju "+ Nova adresa"; biranje realne
  adrese sakriva inline polja. Ulogovan korisnik BEZ sačuvanih adresa i gost
  (gost nikad ne vidi `<select>` niti checkbox — nema nalog na koji bi se
  adresa sačuvala) idu direktno na inline polja. `onMounted` redosled
  korpe (`cart.loadFromBackend()` pa `cart.hydrate()`) je NEDIRAN — vidi
  "Korpa — refaktor..." u `CLAUDE.md`, dve prethodne trke rešene tim
  redosledom.
- **`Cart.vue`** — samo vizuelni/tekstualni redizajn (brand-* tokeni, srpski
  tekst), logika i `onMounted` redosled nedirani. `<a href="/checkout">`
  namerno ostaje pun (ne-SPA) link, ne Inertia `<Link>` — `/checkout` ionako
  treba svež `addresses` prop sa servera pri svakom ulasku.
- **`Profile/Edit.vue`** — nova sekcija preko
  `resources/js/Pages/Profile/Partials/AddressManagement.vue` (lista adresa
  kao kartice, značka "Podrazumevana", modal za dodavanje/izmenu — deli
  jednu `Modal.vue` instancu za oba moda preko `editingAddress` ref-a, isti
  obrazac kao `DeleteUserForm.vue`). Brisanje ide preko `window.confirm()`
  (ne `DeleteConfirmation.vue` — njen API, građen oko `deleteUrl` stringa i
  internog `router.delete()`, se nije uklopio sa "svaka akcija sopstveni
  `useForm()`" obrascem ostatka komponente). `ProfileController::edit` i
  `/checkout` ruta sada šalju `addresses` prop (`$user->addresses()->
  orderByDesc('is_default')->orderByDesc('id')->get()` — podrazumevana
  prva); gost na `/checkout` dobija prazan niz. Ostale 3 Profile sekcije
  (`UpdateProfileInformationForm`, `UpdatePasswordForm`, `DeleteUserForm`)
  NISU redizajnirane (i dalje `text-gray-*`) — nova sekcija namerno ne
  prelazi potpuno na brand-* naslove da ne bi vizuelno odudarala od suseda;
  koristi `bg-brand-card` kartice i `text-brand-accent` akcente, ali
  zadržava `text-gray-900`/`text-gray-600` za header tekst iz istog razloga.
- Flash poruke (`page.props.flash.success/error`) — isti obrazac kao admin
  `Index.vue` stranice, dodate na `Checkout.vue` i `Profile/Edit.vue` jer
  adresne akcije sad redirect-uju nazad sa flash porukom.

### Testovi
Backend: `tests/Feature/Profile/AddressManagementTest.php` (16 — CRUD, prva
adresa uvek default, `setDefault` skida prethodnu, IDOR na sve tri
`{address}` rute vraća 404 i ne menja tuđu adresu, gost redirect na login,
validacija), `tests/Feature/Checkout/CheckoutAddressTest.php` (16 —
checkout sa `address_id`, checkout sa inline `shipping` + `save_address`,
tuđa/nepostojeća adresa daje 422 bez kreiranja porudžbine, gost sa
`address_id` daje 422, **snapshot immutability**: izmena/brisanje sačuvane
adrese POSLE porudžbine ne menja `shipping_*` kolone te porudžbine, legacy
kolone i dalje popunjene). `tests/Feature/OrderStoreTest.php`/
`OrderAccessTest.php` — postojeći testovi prebačeni na novi (`shipping{...}`)
oblik zahteva, nijedna asercija menjana. `php artisan test`: **380 passed**
(348 + 32 nova).
Frontend: `resources/js/Pages/Checkout.test.js` (4 — podrazumevana adresa
unapred izabrana i sakriva inline polja, "+ Nova adresa" otkriva inline
polja + checkbox, gost vidi samo inline polja bez `<select>`/checkbox-a,
ulogovan korisnik bez sačuvanih adresa preskače `<select>`),
`resources/js/Pages/Profile/Partials/AddressManagement.test.js` (9 — lista
+ značka, prazno stanje, modal prefill za izmenu, `.post`/`.patch` sa
tačnim payload-om i rutom, `.delete` uz potvrdu/otkazivanje, `setDefault`,
dugme sakriveno na već-podrazumevanoj). `npm run test`: **46 passed** (33 +
13 novih). `npx vite build` prolazi.

---

## Dizajn — Breeze auth (Faza 6, korak 5)
Poslednji redizajn korak u dogovorenom redosledu (Shop/Product → Cart/
Checkout → Breeze auth → admin panel, koji ostaje van opsega — nije
dogovoren).

### Odluka: GuestLayout ostaje minimalan, ne dobija nav traku
Otvoreno pitanje iz koraka 2 (`AuthenticatedLayout.vue` i `GuestLayout.vue`
nisu ista komponenta, `GuestLayout` nema navigaciju) je rešeno eksplicitnom
korisničkom odlukom, ne istraživanjem — dobijena je kao gotov zahtev pre
implementacije, ne kao nešto što je ovaj korak sam otkrio/odmerio. Razlog
nije dokumentovan van same odluke (verovatno: auth stranice su namerno
fokusiran, izolovan flow — puna nav traka sa cart bedžom/pretragom bi
odvlačila pažnju sa login/register forme). Dodato je samo minimalno: link
"Nazad u prodavnicu" (`/shop`) + zadržan postojeći Logo (link ka `/`).

### Kontrast: brand-header umesto brand-page za pozadinu
Task je eksplicitno tražio probu oba kandidata (`--brand-page-bg: #FFFCF8`
i `--brand-header-bg: #E4CBAE`) i zadržavanje onog koji bolje kontrastira sa
belom (`bg-white`) karticom forme. `brand-page-bg` je vizuelno gotovo
identična beloj pozadini (razlika je ~1-2 nijanse u kanalu, ispod praga
opažljivosti na običnom monitoru) — karta bi "nestala" u pozadinu, gubi se
vizuelna granica forme. `brand-header-bg` je jasno tamnija/toplija nijansa
(vidljiva u screenshotu ispod), daje jasnu granicu oko bele kartice I
dodatno vizuelno vezuje auth stranice za identičnu boju trake koju
`AuthenticatedLayout.vue` nav koristi (Faza 6, korak 2) — auth stranice
"izgledaju kao deo istog sajta" umesto izolovanog ekrana. Tekst linka/Logo-a
koristi `text-brand-header-text` (#6B4423) — isti par tokena kao nav traka,
kontrast već proveren i dokumentovan u koraku 2, nije ponovo računat ovde
(reuse odluke, ne nova provera).

Nije probana treća opcija (bez ijednog `--brand-*` tokena, npr. plain
`bg-white` sa border-om oko kartice) — task je eksplicitno ograničio izbor
na `brand-page`/`brand-header`, van opsega da se predlaže treća.

### Naslovi po stranici — otkriveno da ne postoje
Pre ovog koraka nijedna od 6 auth stranica nije imala vidljiv naslov u
kartici — samo `<Head title="Log in">` i sl. (menja `<title>` u browser
tabu, nevidljivo u samom UI-ju). Task-ov "Stil" odeljak eksplicitno traži
`font-serif` naslove "Prijavite se"/"Registracija", što implicira da naslov
treba da POSTOJI, ne samo da se stilizuje ako već postoji. Ovo je jedino
mesto gde je ovaj korak dirao svaku auth stranicu pojedinačno (uprkos
"SCOPE" napomeni da se stranice ne diraju kad deljene komponente pokrivaju
sve) — naslov je nužno tekst specifičan za stranicu, ne može živeti u
deljenoj komponenti. Dodato: `<h1 class="mb-6 font-serif text-2xl
font-semibold text-brand-text-primary">` sa srpskim tekstom na svih 6
stranica (Login "Prijavite se", Register "Registracija", ForgotPassword
"Zaboravljena lozinka", ResetPassword "Nova lozinka", VerifyEmail "Potvrda
email adrese", ConfirmPassword "Potvrdite lozinku") — ostatak teksta na tim
stranicama (labele, poruke, dugmad) OSTAJE na engleskom, nije preveden;
ovaj korak nije "prevedi Breeze na srpski", samo naslovi iz task opisa.

### Deljene komponente i posledica na Profile
`TextInput.vue`, `InputLabel.vue`, `PrimaryButton.vue`, `SecondaryButton.vue`,
`Checkbox.vue` su prešle sa `indigo-*`/`gray-*` na brand-* tokene
(`border-black/20`, `focus:border-brand-accent`, `focus:ring-brand-accent`,
`text-brand-text-primary`, `bg-brand-accent`/`hover:bg-brand-accent-hover`).
`InputError.vue` namerno nije diran — provera: karta na kojoj se ove
komponente prikazuju je UVEK bela (i na auth stranicama i na Profile-u),
`text-red-600` na beloj pozadini već prolazi WCAG AA (~4.8:1), nema
scenarija u ovom repo-u gde bi crvena greška sela na tamnu brand pozadinu.

Pre izmene je grep-om potvrđeno (task je eksplicitno tražio proveru, ne
pretpostavku) da ove komponente NISU izolovane na auth stranice — koristi ih
i `Profile/Partials/UpdateProfileInformationForm.vue`,
`UpdatePasswordForm.vue`, `DeleteUserForm.vue` (`SecondaryButton` na Cancel
dugmetu) i `AddressManagement.vue` (modal iz Faze 6 koraka 4). Ovo je
POSLEDICA, ne greška: ta 3 Profile partiala (koji od koraka 4 svesno NISU
redizajnirani — vidi gore, "NISU redizajnirane i dalje text-gray-*") sada
imaju brand-obojene inpute/dugmad, dok im naslovi (`text-gray-900`) ostaju
nedirani. Rezultat je delimično brand-stilizovan Profile — isto poznato
ograničenje kao u koraku 4, samo malo dublje (ranije je odudarao ceo
`AddressManagement.vue` blok, sad odudaraju samo naslovi/tekst dok su
inputi/dugmad ujednačeni). `AddressManagement.vue` je dobio čist bonus:
njegov `TextInput` je od koraka 4 tiho koristio stari `indigo-500` fokus
prsten unutar inače brand-stilizovanog modala (previd tog koraka, ne
primećen tada) — sad je usklađen bez dodatne izmene.

**`Checkbox.vue` forms-plugin gotcha** (isti mehanizam kao "Samo na stanju"
checkbox iz koraka 3, CLAUDE.md Stack/`@tailwindcss/forms` napomena):
`text-brand-accent` na golom `<input type="checkbox">` nema efekta bez
`form-checkbox` klase (`@tailwindcss/forms` je `strategy: 'class'`, opt-in
po klasi). Originalni `Checkbox.vue` (`rounded border-gray-300
text-indigo-600 shadow-sm focus:ring-indigo-500`) je zato VEĆ imao isti
"tiho ne radi" problem i pre ovog koraka — `text-indigo-600` nikad nije
imao efekta, checkbox je uvek bio na browser-default plavoj kvačici. Task
ovo nije eksplicitno tražio da se popravi, ali je logična posledica prelaska
na brand boje (bez `form-checkbox` bi `text-brand-accent` isto tiho ne
radio, ostavljajući identičan pre-postojeći bag). Rešenje: kopiran tačan,
već-vetovan string iz `Shop.vue`/`Checkout.vue`/`AddressManagement.vue`
(`form-checkbox h-4 w-4 rounded border-black/20 text-brand-accent
focus:ring-brand-accent`, potvrđeno grep-om pre izmene) umesto smišljanja
novog — konzistentnost sa tri postojeća mesta.

### Forms plugin — potvrđena pretpostavka iz task opisa
Task je pretpostavio da `TextInput.vue`/`Checkbox.vue` treba stilizovati
direktno (border/focus/tekst) umesto oslanjanja na `@tailwindcss/forms`
`'base'`/`'class'` reset, uz napomenu da javim ako pri implementaciji
zaključim suprotno. Nije bilo razloga za odstupanje: plugin ostaje
rezervisan za `form-checkbox` slučaj (opisano gore), a text inputi rade
identično sa direktnim `border-*`/`focus:*` klasama kao što je Shop.vue
filter panel već radio bez plugina — nema novog razloga da se to menja ovde.

### Testovi i verifikacija
Nijedan `tests/Feature/Auth/*.php` test ne asertuje na render-ovani
markup/tekst (grep pre pretpostavke, kako je task tražio — svi su Inertia
component-prop/redirect asercije), pa dodavanje `<h1>` naslova i promena CSS
klasa nije moglo da ih pokvari. Potvrđeno pokretanjem: `php artisan test
--filter=Auth` (52 passed, svih 6 Breeze fajlova + ostali Auth-vezani
testovi), pa pun `php artisan test` (**380 passed**, nepromenjeno —
backend nije diran ovim korakom). `npm run test` (**49 passed**, nepromenjeno
— čist CSS/markup redizajn nema novu logiku koju bi trebalo testirati).
`npx vite build` prolazi bez grešaka.

Vizuelna provera: headless Chrome (`chrome.exe --headless=new
--disable-gpu --screenshot=... --window-size=...`, isti mehanizam kao CDP
provere u ranijim koracima, ovde bez interaktivnog CDP protokola jer je
provera bila statična — samo render, bez klikova/fokusa) protiv `php
artisan serve` na `/login` i `/register`: potvrđen kontrast
`brand-header`/bela karta, čitljivost naslova (font-serif), boja
primarnog dugmeta, izgled checkbox-a. Login/Register su bili dovoljni
uzorak — preostale 4 stranice dele identičnu `GuestLayout.vue` +
identične deljene komponente, nema stranično-specifičnog CSS-a koji bi
zahtevao poseban screenshot.
