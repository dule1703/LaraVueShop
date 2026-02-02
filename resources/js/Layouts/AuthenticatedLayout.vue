<script setup>
import { ref } from 'vue';
import { useCartStore } from '@/Stores/cart';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const cart = useCartStore();
cart.loadFromLocalStorage();

</script>

<template>
  <div>
    <div class="min-h-screen bg-gray-100">
      <nav class="border-b border-gray-100 bg-white">
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 justify-between">
            <div class="flex">
              <!-- Logo vodi na početnu (Shop/Home) -->
              <div class="flex shrink-0 items-center">
                <Link :href="route('home')">
                  <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                </Link>
              </div>

              <!-- Javne navigacione stavke (vidljive svima) -->
              <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                <NavLink :href="route('home')" :active="route().current('home')">
                    Home / Shop
                </NavLink>
                <NavLink :href="route('cart')" :active="route().current('cart')">
                    <font-awesome-icon icon="shopping-cart" class="mr-2" />
                    Cart
                    <span v-if="cart.itemCount > 0" class="ml-2 inline-flex items-center rounded-full bg-red-600 px-2 py-1 text-xs font-bold text-white">
                        {{ cart.itemCount }}
                    </span>
                </NavLink>
              </div>
            </div>

            <!-- Desna strana: admin linkovi, profile/logout ili login/register -->
            <div class="hidden sm:ms-6 sm:flex sm:items-center">
              <!-- Admin linkovi – samo za ulogovane admine -->
              <div v-if="$page.props.auth?.user?.role === 'admin'" class="flex space-x-8">
                <NavLink :href="route('admin.categories.index')" :active="route().current('admin.categories.*')">
                  Categories
                </NavLink>
                <NavLink :href="route('admin.products.index')" :active="route().current('admin.products.*')">
                  Products
                </NavLink>
                <NavLink :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                  Orders
                </NavLink>
              </div>

              <!-- Ako je ulogovan (bilo koja uloga) -->
              <div v-if="$page.props.auth?.user" class="relative ms-3">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <span class="inline-flex rounded-md">
                      <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                      >
                        {{ $page.props.auth?.user?.name ?? 'Gost' }}
                        <svg
                          class="-me-0.5 ms-2 h-4 w-4"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                          />
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

              <!-- Ako nije ulogovan -->
              <div v-else class="flex items-center space-x-4">
                <Link :href="route('login')" class="text-sm text-gray-700 hover:text-gray-900 underline">
                  Log in
                </Link>
                <Link :href="route('register')" class="text-sm text-gray-700 hover:text-gray-900 underline">
                  Register
                </Link>
              </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
              <button
                @click="showingNavigationDropdown = !showingNavigationDropdown"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
              >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path
                    :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div
          :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
          class="sm:hidden"
        >
          <div class="space-y-1 pb-3 pt-2">
            <ResponsiveNavLink :href="route('home')" :active="route().current('home')">
              Home / Shop
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('cart')" :active="route().current('cart')">
                <font-awesome-icon :icon="['fas', 'shopping-cart']" class="mr-2" />
                Cart
                <span v-if="cart.itemCount > 0" class="ml-2 inline-flex items-center rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-bold text-white">
                    {{ cart.itemCount }}
                </span>
            </ResponsiveNavLink>
          </div>

          <div class="border-t border-gray-200 pb-1 pt-4">
            <!-- Admin linkovi – samo za admine -->
            <div v-if="$page.props.auth?.user?.role === 'admin'" class="space-y-1">
              <ResponsiveNavLink :href="route('admin.categories.index')" :active="route().current('admin.categories.*')">
                Categories
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.products.index')" :active="route().current('admin.products.*')">
                Products
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                Orders
              </ResponsiveNavLink>
            </div>

            <!-- Profile / Log Out – za ulogovane -->
            <div v-if="$page.props.auth?.user" class="mt-3 space-y-1">
              <ResponsiveNavLink :href="route('profile.edit')">
                Profile
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                Log Out
              </ResponsiveNavLink>
            </div>

            <!-- Login / Register – za neulogovane -->
            <div v-else class="mt-3 space-y-1">
              <ResponsiveNavLink :href="route('login')">
                Log in
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('register')">
                Register
              </ResponsiveNavLink>
            </div>
          </div>
        </div>
      </nav>

      <!-- Page Heading -->
      <header class="bg-white shadow" v-if="$slots.header">
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