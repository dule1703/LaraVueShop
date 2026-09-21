<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const page = usePage();
const showingNavigationDropdown = ref(false);
const cart = useCartStore();
</script>

<template>
  <div>
    <div class="min-h-screen bg-gray-50">
      <nav class="border-b border-gray-100 bg-white shadow-sm sticky top-0 z-50">
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 justify-between">
            <div class="flex">
              <!-- Logo -->
              <div class="flex shrink-0 items-center">
                <Link :href="route('home')">
                  <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                </Link>
              </div>
              <!-- Navigation Links -->
              <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">                
                <NavLink :href="route('shop')" :active="route().current('shop')">
                  Shop
                </NavLink>
              </div>
            </div>

            <!-- Search bar (desktop) -->
            <div class="hidden md:flex flex-1 max-w-xl mx-8 items-center">
              <div class="relative w-full">
                <input
                  type="text"
                  placeholder="Search products..."
                  class="w-full bg-gray-100 border border-transparent focus:border-gray-300 focus:ring-2 focus:ring-[#FF2D20] rounded-3xl py-3 px-6 pl-12 text-sm placeholder:text-gray-400 transition-all"
                />
                <font-awesome-icon 
                  :icon="['fas', 'search']" 
                  class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 text-lg"
                />
              </div>
            </div>

            <!-- Right side: Cart + User -->
            <div class="flex items-center gap-x-6">
              <!-- Cart -->
              <NavLink :href="route('cart')" :active="route().current('cart')" class="flex items-center gap-x-1 text-xl hover:text-[#FF2D20] transition">
                <font-awesome-icon :icon="['fas', 'shopping-cart']" />
                <span v-if="cart.itemCount > 0" class="ml-1 inline-flex items-center rounded-full bg-[#FF2D20] px-2.5 py-1 text-xs font-bold text-white">
                  {{ cart.itemCount }}
                </span>
              </NavLink>

              <!-- Admin Links -->
              <div v-if="page.props.auth?.user?.role === 'admin'" class="hidden md:flex space-x-8 text-sm font-medium">
                <NavLink :href="route('admin.categories.index')" :active="route().current('admin.categories.*')">
                  Categories
                </NavLink>
                <NavLink :href="route('admin.products.index')" :active="route().current('admin.products.*')">
                  Products
                </NavLink>
                <NavLink :href="route('admin.books.index')" :active="route().current('admin.books.*')">
                  Books
                </NavLink>
                <NavLink :href="route('admin.authors.index')" :active="route().current('admin.authors.*')">
                  Authors
                </NavLink>
                <NavLink :href="route('admin.publishers.index')" :active="route().current('admin.publishers.*')">
                  Publishers
                </NavLink>
                <NavLink :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                  Orders
                </NavLink>
              </div>

              <!-- User Dropdown -->
              <div v-if="page.props.auth?.user" class="relative ms-3 hidden sm:block">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <span class="inline-flex rounded-md">
                      <button
                        type="button"
                        class="inline-flex items-center rounded-2xl border border-transparent bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition"
                      >
                        {{ page.props.auth?.user?.name ?? 'Guest' }}
                        <svg class="-me-1 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </span>
                  </template>
                  <template #content>
                    <DropdownLink :href="route('profile.edit')">
                      Profile
                    </DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">
                      Log Out
                    </DropdownLink>
                  </template>
                </Dropdown>
              </div>

              <!-- Login / Register -->
              <div v-else class="hidden sm:flex items-center gap-x-4 text-sm">
                <Link :href="route('login')" class="font-medium text-gray-700 hover:text-black">Log in</Link>
                <Link :href="route('register')" class="rounded-2xl bg-[#FF2D20] px-5 py-2.5 font-medium text-white hover:bg-[#e0281c] transition">Register</Link>
              </div>

              <!-- Hamburger -->
              <div class="-me-2 flex items-center sm:hidden">
                <button
                  @click="showingNavigationDropdown = !showingNavigationDropdown"
                  class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 transition"
                >
                  <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Responsive Navigation Menu (original structure kept) -->
        <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden border-t border-gray-100 bg-white">
          <div class="space-y-1 pb-3 pt-2 px-4">
            <ResponsiveNavLink :href="route('home')" :active="route().current('home')">
              Home
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('shop')" :active="route().current('shop')">
              Shop
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('cart')" :active="route().current('cart')">
              Cart
              <span v-if="cart.itemCount > 0" class="ml-2 inline-flex items-center rounded-full bg-[#FF2D20] px-2 py-1 text-xs font-bold text-white">
                {{ cart.itemCount }}
              </span>
            </ResponsiveNavLink>

            <!-- Admin links mobile -->
            <template v-if="page.props.auth?.user?.role === 'admin'">
              <ResponsiveNavLink :href="route('admin.categories.index')" :active="route().current('admin.categories.*')">
                Categories
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.products.index')" :active="route().current('admin.products.*')">
                Products
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.books.index')" :active="route().current('admin.books.*')">
                Books
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.authors.index')" :active="route().current('admin.authors.*')">
                Authors
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.publishers.index')" :active="route().current('admin.publishers.*')">
                Publishers
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                Orders
              </ResponsiveNavLink>
            </template>
          </div>

          <!-- Responsive User Section -->
          <div v-if="page.props.auth?.user" class="border-t border-gray-200 pb-4 pt-4 px-4">
            <div class="text-base font-medium text-gray-800 mb-1">{{ page.props.auth?.user?.name ?? 'Guest' }}</div>
            <div class="text-sm text-gray-500">{{ page.props.auth?.user?.email }}</div>
            <div class="mt-4 space-y-1">
              <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
              <ResponsiveNavLink :href="route('logout')" method="post" as="button">Log Out</ResponsiveNavLink>
            </div>
          </div>
          <div v-else class="border-t border-gray-200 pb-4 pt-4 px-4 space-y-1">
            <ResponsiveNavLink :href="route('login')">Log in</ResponsiveNavLink>
            <ResponsiveNavLink :href="route('register')">Register</ResponsiveNavLink>
          </div>
        </div>
      </nav>

      <!-- Page Heading -->
      <header v-if="$slots.header" class="bg-white shadow">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Page Content -->
      <main>
        <slot />
      </main>
    </div>
  </div>
</template>
