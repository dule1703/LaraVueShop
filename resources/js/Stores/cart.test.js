import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import axios from 'axios';
import { useCartStore } from './cart';

vi.mock('axios', () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

// product_id 43 postoji u bazi; ovo je ono što server VRAĆA (istina),
// nasuprot onome što je stara korpa "mislila" da je cena/naziv.
const SERVER_PRODUCT_43 = { id: 43, name: 'Prava knjiga iz baze', price: 19.99, image: 'prava-slika.jpg', stock: 5, available: true };

// Stari oblik korpe (pre refaktora): pun snapshot sa lažnom/zastarelom cenom.
const OLD_FORMAT_ITEM = { id: 43, name: 'Test', price: 999, image: 'test.jpg', quantity: 3 };

beforeEach(() => {
  setActivePinia(createPinia());
  localStorage.clear();
  vi.clearAllMocks();
  globalThis.route = (name) => name;

  axios.get.mockResolvedValue({ data: { products: [SERVER_PRODUCT_43] } });
  axios.post.mockResolvedValue({ data: { success: true } });
});

describe('normalizeItems (preko loadFromLocalStorage): stari oblik {id,...} -> {product_id, quantity}', () => {
  it('mapira id -> product_id i odbacuje staru cenu/naziv/sliku', () => {
    localStorage.setItem('cart', JSON.stringify([OLD_FORMAT_ITEM]));

    const cart = useCartStore();
    cart.loadFromLocalStorage();

    // Stavka SE MAPIRA (ne odbacuje) - product_id: 43 je stvaran, validan ID.
    expect(cart.items).toEqual([{ product_id: 43, quantity: 3 }]);

    // productDetails je prazan dok se hydrate() ne pozove - cena 999 iz
    // stare korpe se ne prenosi nigde, totalAmount je 0, ne 999*3.
    expect(cart.productDetails).toEqual({});
    expect(cart.totalAmount).toBe(0);
  });

  it('ISPRAVAN redosled (load pa hydrate): cena/naziv dolaze sa servera, nikad iz stare korpe', async () => {
    localStorage.setItem('cart', JSON.stringify([OLD_FORMAT_ITEM]));

    const cart = useCartStore();
    cart.loadFromLocalStorage();
    await cart.hydrate();

    expect(axios.get).toHaveBeenCalledTimes(1);
    expect(cart.productDetails[43].price).toBe(19.99);
    expect(cart.productDetails[43].name).toBe('Prava knjiga iz baze');
    // 19.99 * 3, NIKAD 999 * 3 (stara "zamrznuta" cena se nikad ne koristi)
    expect(cart.totalAmount).toBeCloseTo(59.97);
  });
});

describe('race condition: hydrate() pozvan pre nego što je korpa učitana', () => {
  it('hydrate() na praznoj korpi ne fetch-uje ništa (items je [] u tom trenutku)', async () => {
    const cart = useCartStore();

    await cart.hydrate();

    expect(axios.get).not.toHaveBeenCalled();
    expect(cart.productDetails).toEqual({});
  });

  it('reprodukuje prijavljeni bag: item ostane na 0,00 € posle kasnijeg load-a jer se hydrate() ne re-triggeruje', async () => {
    localStorage.setItem('cart', JSON.stringify([OLD_FORMAT_ITEM]));

    const cart = useCartStore();

    // Ovo je bio tačan redosled u starom Cart.vue: onMounted je zvao SAMO
    // cart.hydrate(), oslanjajući se da je app.js već učitao korpu.
    // app.js zapravo poziva loadFromLocalStorage()/loadFromBackend() TEK
    // POSLE app.mount(el) - tj. POSLE što se onMounted stranice već izvršio
    // (Vue mounted hook-ovi dece se pozivaju sinhrono unutar mount()).
    // Dakle hydrate() ovde vidi items === [] i odmah odustaje:
    await cart.hydrate();
    expect(axios.get).not.toHaveBeenCalled();
    expect(cart.productDetails).toEqual({});

    // app.js tek sada (posle mount-a) učitava korpu iz localStorage-a:
    cart.loadFromLocalStorage();

    // Stavka je tu i ispravno mapirana (normalizacija radi)...
    expect(cart.items).toEqual([{ product_id: 43, quantity: 3 }]);

    // ...ali hydrate() se NE re-triggeruje automatski kad se items promeni,
    // pa productDetails ostaje prazan zauvek (dok se komponenta ponovo ne
    // mount-uje) - korisnik vidi stavku u korpi sa cenom "0.00 €".
    expect(cart.productDetails).toEqual({});
    expect(cart.totalAmount).toBe(0);
  });

  it('ISPRAVLJEN redosled (Cart.vue/Checkout.vue posle fix-a): load pa hydrate u onMounted-u nema ovaj problem', async () => {
    localStorage.setItem('cart', JSON.stringify([OLD_FORMAT_ITEM]));

    const cart = useCartStore();

    // Cart.vue/Checkout.vue posle fix-a rade tačno ovim redosledom u onMounted-u.
    await cart.loadFromBackend(); // gost -> interno pada nazad na loadFromLocalStorage()
    await cart.hydrate();

    expect(cart.totalAmount).toBeCloseTo(59.97);
  });
});
