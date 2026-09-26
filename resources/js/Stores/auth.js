import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import { watch } from 'vue';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    previousUserId: null,
  }),
  
  actions: {
    /**
     * initialUser mora doći sinhrono od strane pozivaoca (initialPage.props.auth.user
     * iz createInertiaApp-ovog setup()-a), NE iz usePage() ovde. usePage() čita
     * modul-level `page` ref koji Inertia-in App komponenta popuni tek u sopstvenom
     * setup()-u, tj. TOKOM app.mount() - a to je POSLE što se stranica (Cart.vue/
     * Checkout.vue) već sinhrono mount-ovala i pozvala cart.loadFromBackend(). Ako bi
     * init() zvao app.js POSLE app.mount(), authStore.user bi u tom trenutku i dalje
     * bio null i ulogovan korisnik bi na svakom punom (ne-SPA) učitavanju stranice bio
     * pogrešno tretiran kao gost (korpa bi se učitala iz praznog/zastarelog
     * localStorage-a umesto sa servera). Vidi app.js.
     */
    init(initialUser = null) {
      const page = usePage();
      this.user = initialUser;
      this.previousUserId = this.user?.id || null;

      // Watch za promene korisnika. `page.props` je undefined dok Inertia-ina
      // App komponenta ne postavi svoj interni `page` ref (tek unutar
      // app.mount(), posle ovog init() poziva - vidi app.js) - watch()
      // sinhrono evaluira ovaj getter ODMAH pri pozivu (radi prikupljanja
      // zavisnosti), bez obzira na `immediate: false`, pa `page.props.auth`
      // (bez `?.` posle `props`) puca sa "Cannot read properties of
      // undefined (reading 'auth')" i taj throw izlazi iz watch()-a jer
      // van komponente nema error-boundary instance koja bi ga progutala -
      // zaustavlja ceo createInertiaApp setup() PRE app.mount(), bela
      // strana. `page.props?.auth?.user` bezbedno vraća undefined dok
      // page.value ne bude postavljen, watch i dalje ostaje pretplaćen na
      // promenu (computed unutar `page.props` čita isti reaktivni `page`
      // ref), pa se okine čim Inertia postavi pravu stranicu.
      watch(
        () => page.props?.auth?.user,
        async (newUser) => {
          const newUserId = newUser?.id || null;
          // NAMERNO čitamo prethodno stanje iz this.previousUserId (postavljeno
          // sinhrono iz initialUser na početku init()-a), NE iz watch()-ovog
          // drugog argumenta (oldUser). Vue watch() internu "staru vrednost"
          // prvi put postavlja na ono što getter vrati PRI SAMOM POZIVU
          // watch()-a (vidi komentar iznad) - a to je uvek undefined (Inertia
          // još nije postavila page.props). Da smo koristili taj oldUser, svaki
          // PUN reload za VEĆ ulogovanog korisnika bi na prvom stvarnom
          // okidanju (kad Inertia popuni page.props tokom app.mount()) lažno
          // izgledao kao tranzicija null -> user, tj. kao SCENARIO 2 (LOGIN) -
          // iako se korisnik samo hidrira, ne prijavljuje. To bi na svakom
          // reload-u nepotrebno duplo gađalo /api/cart i, gore, pogrešno
          // pokrenulo mergeGuestCartOnLogin() ako bi u localStorage-u slučajno
          // ostala gost korpa. this.previousUserId je već tačan (iz initialUser),
          // pa poređenje protiv njega ispravno prepoznaje ovo kao SCENARIO 4
          // (isti korisnik) umesto lažnog login-a. Reprodukovano i potvrđeno
          // u pravom browseru (headless Chrome + CDP), vidi auth.test.js.
          const oldUserId = this.previousUserId;

          console.log('👤 User change detected:', { oldUserId, newUserId });

          // SCENARIO 1: Logout (old user -> null)
          if (oldUserId && !newUserId) {
            console.log('🔓 LOGOUT detected');
            this.handleLogout();
          }
          // SCENARIO 2: Login (null -> new user)
          else if (!oldUserId && newUserId) {
            console.log('🔐 LOGIN detected');
            this.user = newUser;
            await this.handleLogin();
          }
          // SCENARIO 3: User switch (old user -> different user)
          else if (oldUserId && newUserId && oldUserId !== newUserId) {
            console.log('🔄 USER SWITCH detected');
            this.user = newUser;
            await this.handleUserSwitch();
          }
          // SCENARIO 4: User update (same user, possibly changed data)
          else if (oldUserId && newUserId && oldUserId === newUserId) {
            console.log('🔄 USER UPDATE detected (same user)');
            this.user = newUser;
          }

          this.previousUserId = newUserId;
        },
        { immediate: false, deep: true }
      );
    },

    async handleLogin() {
      const cart = useCartStore();
      
      // Proveri da li gost ima stavke u localStorage
      const guestCartData = localStorage.getItem('cart');
      let guestItems = [];
      
      if (guestCartData) {
        try {
          const parsed = JSON.parse(guestCartData);
          
          // Podrška za oba formata
          if (Array.isArray(parsed)) {
            guestItems = parsed;
          } else if (parsed.items && Array.isArray(parsed.items)) {
            guestItems = parsed.items;
          }
          
          console.log('📦 Guest localStorage:', guestItems.length, 'stavki');
        } catch (e) {
          console.error('❌ Greška pri parsiranju guest cart-a:', e);
          guestItems = [];
        }
      }

      if (guestItems.length > 0) {
        console.log('📦 Guest ima', guestItems.length, 'stavki – mergovanje sa backend korpom');
        await cart.mergeGuestCartOnLogin();
      } else {
        console.log('📦 Guest nema stavke – učitavanje čiste backend korpe');
        await cart.loadFromBackend();
      }
      
      // VAŽNO: Uvek očisti localStorage nakon login-a
      localStorage.removeItem('cart');
      console.log('🧹 localStorage očišćen nakon login-a');
    },

    handleLogout() {
      this.user = null;
      this.previousUserId = null;
      
      const cart = useCartStore();
      cart.handleLogout();
      
      // VAŽNO: Očisti localStorage na logout
      localStorage.removeItem('cart');
      console.log('🧹 localStorage očišćen na logout');
    },

    async handleUserSwitch() {
      const cart = useCartStore();
      
      // Pri switch-u između naloga, učitaj čistu korpu novog naloga
      console.log('🔄 Switching user – učitavanje nove korpe iz baze');
      
      // Očisti localStorage da ne bi bilo mešanja
      localStorage.removeItem('cart');
      
      await cart.loadFromBackend();
    },
  },
});