import { defineStore } from 'pinia';

export const useCartStore = defineStore('cart', {
    state: () => ({
        items: [],
    }),

    getters: {
        totalAmount: (state) => {
            return state.items.reduce((total, item) => total + item.price * item.quantity, 0);
        },
        itemCount: (state) => {
            return state.items.reduce((total, item) => total + item.quantity, 0);
        },
    },

    actions: {
        addItem(product, quantity = 1) {
            const existing = this.items.find(item => item.id === product.id);
            if (existing) {
                existing.quantity += quantity;
            } else {
                this.items.push({ ...product, quantity });
            }
            this.saveToLocalStorage();
            this.syncWithBackend();
        },

        removeItem(productId) {
            this.items = this.items.filter(item => item.id !== productId);
            this.saveToLocalStorage();
            this.syncWithBackend();
        },

        clearCart() {
            this.items = [];
            this.saveToLocalStorage();
            this.syncWithBackend();
        },

        saveToLocalStorage() {
            localStorage.setItem('cart', JSON.stringify(this.items));
        },

        loadFromLocalStorage() {
            const saved = localStorage.getItem('cart');
            if (saved) {
                this.items = JSON.parse(saved);
            }
        },

        syncWithBackend() {
            if (this.$page?.props?.auth?.user) { // ako je ulogovan
                router.post(route('cart.sync'), { items: this.items }, {
                    onSuccess: () => console.log('Korpa sinhronizovana sa backend-om'),
                    onError: (err) => console.error('Greška pri sinhronizaciji korpe:', err),
                });
            }
        },
    },
});