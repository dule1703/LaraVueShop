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
    init() {
      const page = usePage();
      this.user = page.props.auth?.user || null;
      this.previousUserId = this.user?.id || null;

      // Watch za promene korisnika
      watch(
        () => page.props.auth?.user,
        async (newUser, oldUser) => {
          const newUserId = newUser?.id || null;
          const oldUserId = oldUser?.id || null;

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