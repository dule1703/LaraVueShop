import { defineStore } from 'pinia';
import axios from 'axios';
import { useAuthStore } from '@/Stores/auth';

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    isLoading: false,
  }),
  
  getters: {
    totalAmount: (state) => {
      if (!Array.isArray(state.items)) return 0;
      return state.items.reduce((sum, item) => {
        return sum + (item.price || 0) * (item.quantity || 0);
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
     * Dodaj proizvod u korpu
     */
    addItem(product, qty = 1) {
      if (!Array.isArray(this.items)) this.items = [];
      
      const existing = this.items.find(i => i.id === product.id);
      if (existing) {
        existing.quantity = (existing.quantity || 0) + qty;
      } else {
        this.items.push({ ...product, quantity: qty });
      }
      
      console.log('➕ Dodato u korpu:', product.name, '| Ukupno stavki:', this.items.length);
      
      this.saveToLocalStorage();
      this.syncWithBackend();
    },

    /**
     * Smanji količinu proizvoda
     */
    decreaseQuantity(productId) {
      if (!Array.isArray(this.items)) this.items = [];
      
      const item = this.items.find(i => i.id === productId);
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
      this.items = this.items.filter(i => i.id !== productId);
      
      console.log('🗑️ Uklonjeno iz korpe | Preostalo stavki:', this.items.length);
      
      this.saveToLocalStorage();
      this.syncWithBackend();
    },

    /**
     * Očisti celu korpu
     */
    clearCart() {
        this.items = [];
        this.saveToLocalStorage();
        
        const authStore = useAuthStore();
        if (authStore.user) {
            console.log('Clearing cart on backend...');
            this.syncWithBackend();  
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
        console.log('💾 Sačuvano u localStorage (guest):', this.items.length, 'stavki');
      } else {
        console.log('⏭️ Skip localStorage (user je ulogovan)');
      }
    },

    /**
     * Učitaj korpu iz localStorage (SAMO za gosta)
     */
    loadFromLocalStorage() {
      const authStore = useAuthStore();
      
      // Ne učitavamo localStorage ako je korisnik ulogovan
      if (authStore.user) {
        console.log('⚠️ User je ulogovan - skipujem localStorage');
        return;
      }

      const data = localStorage.getItem('cart');
      if (data) {
        try {
          const parsed = JSON.parse(data);
          this.items = Array.isArray(parsed) ? parsed : [];
          console.log('📂 Učitano iz localStorage:', this.items.length, 'stavki');
        } catch (e) {
          console.error('❌ Greška pri parsiranju cart-a iz localStorage-a:', e);
          localStorage.removeItem('cart');
          this.items = [];
        }
      } else {
        this.items = [];
        console.log('📂 localStorage prazan');
      }
    },

    /**
     * Sinhronizuj korpu sa backend-om (SAMO za ulogovane)
     */
    
async syncWithBackend() {
  const authStore = useAuthStore();
  
  if (!authStore.user) {
    console.log('🚫 Sync skipped – korisnik nije ulogovan');
    return;
  }

  try {
    // ✅ Eksplicitno kreiraj plain array (ne Proxy)
    const itemsToSend = Array.isArray(this.items) 
      ? JSON.parse(JSON.stringify(this.items))  
      : [];
    
    const payload = { 
      items: itemsToSend
    };
    
    console.log('📤 Sending sync request:', payload);
    console.log('   Items type:', typeof payload.items, Array.isArray(payload.items));
    
    await axios.post(route('api.cart.sync'), payload);
    console.log('✅ Sync success – korpa ažurirana u bazi | Stavki:', this.items.length);
  } catch (err) {
    console.error('❌ Sync error:', err.response?.data || err.message);
  }
},
    /**
     * Učitaj korpu iz backend-a (SAMO za ulogovane)
     */
    async loadFromBackend() {
    const authStore = useAuthStore();
    
    if (!authStore.user) {
        console.log('User not logged in – loading from localStorage');
        this.loadFromLocalStorage();
        return;
    }

    try {
        const response = await axios.get(route('api.cart.show'));
        const backendCart = response.data.cart;

        // Ako nema korpe ili items je null/undefined – koristi prazno []
        this.items = Array.isArray(backendCart?.items) ? backendCart.items : [];
        
        console.log('✅ Backend cart loaded:', this.items.length, 'items');
    } catch (err) {
        console.error('❌ Load error:', err);
        this.items = [];
    }
},

    /**
     * Merge guest korpe sa backend-om (poziva se SAMO pri login-u)
     */
    async mergeGuestCartOnLogin() {
      const authStore = useAuthStore();
      
      if (!authStore.user) {
        console.log('🚫 Merge skipped – korisnik nije ulogovan');
        return;
      }

      // Učitaj lokalnu korpu (gostovu)
      const guestCartData = localStorage.getItem('cart');
      let guestItems = [];
      
      if (guestCartData) {
        try {
          const parsed = JSON.parse(guestCartData);
          guestItems = Array.isArray(parsed) ? parsed : [];
          console.log('📦 Guest cart:', guestItems.length, 'stavki');
        } catch (e) {
          console.error('❌ Greška pri parsiranju guest cart-a:', e);
          guestItems = [];
        }
      }

      // Ako gost nema stavki, samo učitaj iz baze
      if (guestItems.length === 0) {
        console.log('📦 Guest cart prazan – učitavam samo backend korpu');
        await this.loadFromBackend();
        return;
      }

      // Učitaj backend korpu
      this.isLoading = true;
      
      try {
        const response = await axios.get(route('api.cart.show'));
        const backendItems = response.data.cart?.items || [];
        
        console.log('📦 MERGE:');
        console.log('   Guest:', guestItems.length, 'stavki');
        console.log('   Backend:', backendItems.length, 'stavki');
        
        // Merguj gostovu korpu sa backend korpom
        let mergedItems = [...backendItems];
        
        guestItems.forEach(guestItem => {
          const existing = mergedItems.find(i => i.id === guestItem.id);
          if (existing) {
            existing.quantity += guestItem.quantity || 0;
            console.log('   ✓ Merged:', guestItem.name, '(total qty:', existing.quantity + ')');
          } else {
            mergedItems.push(guestItem);
            console.log('   + Added:', guestItem.name);
          }
        });

        this.items = mergedItems;
        this.isLoading = false;
        
        console.log('✅ Merge complete – ukupno:', this.items.length, 'stavki');
        
        // Sinhronizuj mergovan rezultat sa backend-om
        await this.syncWithBackend();
        
      } catch (err) {
        console.error('❌ Merge error:', err);
        this.items = [];
        this.isLoading = false;
      }
    },

    /**
     * Logout – očisti SVE
     */
    handleLogout() {
      console.log('🔓 Logout detected – čistim SVE');
      
      // Očisti Pinia state
      this.items = [];
      
      // localStorage će auth.js očistiti
    },
  },
});