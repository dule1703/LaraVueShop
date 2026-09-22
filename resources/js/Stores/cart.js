import { defineStore } from 'pinia';
import axios from 'axios';
import { useAuthStore } from '@/Stores/auth';

/**
 * Korpa čuva SAMO {product_id, quantity} - nikad cenu/naziv/sliku.
 * Prihvata i staru šemu ({id, name, price, quantity, ...}) iz localStorage-a/baze
 * radi glatkog prelaza za korpe sačuvane pre refaktora.
 */
function normalizeItems(rawItems) {
  if (!Array.isArray(rawItems)) return [];

  const merged = new Map();
  rawItems.forEach((item) => {
    const productId = item?.product_id ?? item?.id;
    const quantity = Number(item?.quantity) || 0;
    if (productId == null || quantity <= 0) return;

    const existing = merged.get(productId) || 0;
    merged.set(productId, existing + quantity);
  });

  return Array.from(merged, ([product_id, quantity]) => ({ product_id, quantity }));
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    // Sveži podaci o proizvodima (naziv/cena/slika/zalihe), keširano po product_id.
    // Popunjava se isključivo preko hydrate() - nikad iz onoga što je korpa sačuvala.
    productDetails: {},
    isLoading: false,
  }),

  getters: {
    totalAmount: (state) => {
      if (!Array.isArray(state.items)) return 0;
      return state.items.reduce((sum, item) => {
        const details = state.productDetails[item.product_id];
        const price = details ? Number(details.price) || 0 : 0;
        return sum + price * (item.quantity || 0);
      }, 0);
    },

    itemCount: (state) => {
      if (!Array.isArray(state.items)) return 0;
      return state.items.reduce((sum, item) => sum + (item.quantity || 0), 0);
    },

    isEmpty: (state) => !Array.isArray(state.items) || state.items.length === 0,
  },

  actions: {
    /**
     * Dodaj proizvod u korpu (po product_id).
     */
    addItem(productId, qty = 1) {
      if (!Array.isArray(this.items)) this.items = [];

      const existing = this.items.find((i) => i.product_id === productId);
      if (existing) {
        existing.quantity = (existing.quantity || 0) + qty;
      } else {
        this.items.push({ product_id: productId, quantity: qty });
      }

      this.saveToLocalStorage();
      this.syncWithBackend();
    },

    /**
     * Smanji količinu proizvoda
     */
    decreaseQuantity(productId) {
      if (!Array.isArray(this.items)) this.items = [];

      const item = this.items.find((i) => i.product_id === productId);
      if (!item) return;

      if (item.quantity > 1) {
        item.quantity -= 1;
      } else {
        this.removeItem(productId);
        return;
      }

      this.saveToLocalStorage();
      this.syncWithBackend();
    },

    /**
     * Ukloni proizvod iz korpe
     */
    removeItem(productId) {
      if (!Array.isArray(this.items)) this.items = [];
      this.items = this.items.filter((i) => i.product_id !== productId);

      this.saveToLocalStorage();
      this.syncWithBackend();
    },

    /**
     * Očisti celu korpu
     */
    clearCart() {
      this.items = [];
      this.productDetails = {};
      this.saveToLocalStorage();

      const authStore = useAuthStore();
      if (authStore.user) {
        this.syncWithBackend();
      }
    },

    /**
     * Učitaj sveže podatke (naziv/cena/slika/zalihe) za proizvode iz korpe.
     * Stavke čiji proizvod više ne postoji ili nije aktivan se tiho uklone
     * iz korpe (graceful - ne ruši prikaz ni checkout).
     */
    async hydrate() {
      if (!Array.isArray(this.items) || this.items.length === 0) {
        this.productDetails = {};
        return;
      }

      const ids = [...new Set(this.items.map((i) => i.product_id))];
      this.isLoading = true;

      try {
        const response = await axios.get(route('api.cart.products'), { params: { ids } });
        const products = Array.isArray(response.data.products) ? response.data.products : [];

        const details = {};
        products.forEach((p) => { details[p.id] = p; });
        this.productDetails = details;

        const validIds = new Set(products.map((p) => p.id));
        const filtered = this.items.filter((i) => validIds.has(i.product_id));

        if (filtered.length !== this.items.length) {
          this.items = filtered;
          this.saveToLocalStorage();
          this.syncWithBackend();
        }
      } catch (err) {
        console.error('Greška pri učitavanju podataka o proizvodima iz korpe:', err);
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Sačuvaj korpu u localStorage (SAMO za gosta)
     */
    saveToLocalStorage() {
      if (!Array.isArray(this.items)) this.items = [];
      const authStore = useAuthStore();

      // localStorage koristimo SAMO za goste
      if (!authStore.user) {
        localStorage.setItem('cart', JSON.stringify(this.items));
      }
    },

    /**
     * Učitaj korpu iz localStorage (SAMO za gosta)
     */
    loadFromLocalStorage() {
      const authStore = useAuthStore();

      // Ne učitavamo localStorage ako je korisnik ulogovan
      if (authStore.user) {
        return;
      }

      const data = localStorage.getItem('cart');
      if (data) {
        try {
          this.items = normalizeItems(JSON.parse(data));
        } catch (e) {
          console.error('Greška pri parsiranju cart-a iz localStorage-a:', e);
          localStorage.removeItem('cart');
          this.items = [];
        }
      } else {
        this.items = [];
      }
    },

    /**
     * Sinhronizuj korpu sa backend-om (SAMO za ulogovane)
     */
    async syncWithBackend() {
      const authStore = useAuthStore();

      if (!authStore.user) {
        return;
      }

      try {
        // Eksplicitno kreiraj plain array (ne Proxy)
        const itemsToSend = Array.isArray(this.items)
          ? JSON.parse(JSON.stringify(this.items))
          : [];

        await axios.post(route('api.cart.sync'), { items: itemsToSend });
      } catch (err) {
        console.error('Greška pri sinhronizaciji korpe:', err.response?.data || err.message);
      }
    },

    /**
     * Učitaj korpu iz backend-a (SAMO za ulogovane)
     */
    async loadFromBackend() {
      const authStore = useAuthStore();

      if (!authStore.user) {
        this.loadFromLocalStorage();
        return;
      }

      try {
        const response = await axios.get(route('api.cart.show'));
        this.items = normalizeItems(response.data.cart?.items);
      } catch (err) {
        console.error('Greška pri učitavanju korpe sa servera:', err);
        this.items = [];
      }
    },

    /**
     * Merge guest korpe sa backend-om (poziva se SAMO pri login-u)
     */
    async mergeGuestCartOnLogin() {
      const authStore = useAuthStore();

      if (!authStore.user) {
        return;
      }

      const guestCartData = localStorage.getItem('cart');
      let guestItems = [];

      if (guestCartData) {
        try {
          guestItems = normalizeItems(JSON.parse(guestCartData));
        } catch (e) {
          console.error('Greška pri parsiranju guest cart-a:', e);
          guestItems = [];
        }
      }

      if (guestItems.length === 0) {
        await this.loadFromBackend();
        return;
      }

      this.isLoading = true;

      try {
        const response = await axios.get(route('api.cart.show'));
        const backendItems = normalizeItems(response.data.cart?.items);

        const mergedByProductId = new Map(backendItems.map((i) => [i.product_id, { ...i }]));

        guestItems.forEach((guestItem) => {
          const existing = mergedByProductId.get(guestItem.product_id);
          if (existing) {
            existing.quantity += guestItem.quantity || 0;
          } else {
            mergedByProductId.set(guestItem.product_id, { ...guestItem });
          }
        });

        this.items = Array.from(mergedByProductId.values());
        this.isLoading = false;

        await this.syncWithBackend();
      } catch (err) {
        console.error('Greška pri merge-ovanju korpe:', err);
        this.items = [];
        this.isLoading = false;
      }
    },

    /**
     * Logout – očisti SVE
     */
    handleLogout() {
      // Očisti Pinia state
      this.items = [];
      this.productDetails = {};

      // localStorage će auth.js očistiti
    },
  },
});
