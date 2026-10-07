<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/cart';
import Logo from '@/Components/Logo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import HeaderSearch from '@/Components/HeaderSearch.vue';
import { Link } from '@inertiajs/vue3';

const page = usePage();
const showingNavigationDropdown = ref(false);
const cart = useCartStore();
</script>

<template>
  <div>
    <div class="min-h-screen bg-gray-50">
      <nav class="border-b border-black/10 bg-brand-header shadow-sm sticky top-0 z-50">
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 justify-between">
            <div class="flex">
              <!-- Logo -->
              <div class="flex shrink-0 items-center">
                <Link :href="route('home')" class="text-2xl text-brand-header-text">
                  <Logo />
                </Link>
              </div>
              <!-- Navigation Links -->
              <div class="hidden space-x-8 md:-my-px md:ms-10 md:flex">
                <NavLink :href="route('shop')" :active="route().current('shop')" size="base" class="font-semibold">
                  Prodavnica
                </NavLink>
              </div>
            </div>

            <!-- Search bar (desktop) -->
            <div class="hidden md:flex flex-1 max-w-xl mx-8 items-center">
              <HeaderSearch
                input-class="w-full bg-brand-page border border-transparent focus:border-brand-accent focus:ring-2 focus:ring-brand-accent rounded-3xl py-3 px-6 pl-12 pr-10 text-sm text-brand-text-primary placeholder:text-brand-header-muted transition-all"
                icon-class="absolute left-5 top-1/2 -translate-y-1/2 text-brand-header-muted text-lg"
              />
            </div>

            <!-- Right side: Cart + User -->
            <div class="flex items-center gap-x-6">
              <!-- Cart -->
              <NavLink :href="route('cart')" :active="route().current('cart')" class="flex items-center gap-x-1 text-xl hover:text-brand-accent transition">
                <font-awesome-icon :icon="['fas', 'shopping-cart']" />
                <span v-if="cart.itemCount > 0" class="ml-1 inline-flex items-center rounded-full bg-brand-accent px-2.5 py-1 text-xs font-bold text-white ring-2 ring-brand-page">
                  {{ cart.itemCount }}
                </span>
              </NavLink>

              <!-- Admin Links -->
              <div v-if="page.props.auth?.user?.role === 'admin'" class="hidden md:flex space-x-8 text-sm font-medium">
                <NavLink :href="route('admin.categories.index')" :active="route().current('admin.categories.*')">
                  Kategorije
                </NavLink>
                <NavLink :href="route('admin.products.index')" :active="route().current('admin.products.*')">
                  Proizvodi
                </NavLink>
                <NavLink :href="route('admin.books.index')" :active="route().current('admin.books.*')">
                  Knjige
                </NavLink>
                <NavLink :href="route('admin.authors.index')" :active="route().current('admin.authors.*')">
                  Autori
                </NavLink>
                <NavLink :href="route('admin.publishers.index')" :active="route().current('admin.publishers.*')">
                  Izdavači
                </NavLink>
                <NavLink :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                  Porudžbine
                </NavLink>
              </div>

              <!-- User Dropdown -->
              <div v-if="page.props.auth?.user" class="relative ms-3 hidden md:block">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <span class="inline-flex rounded-md">
                      <button
                        type="button"
                        class="inline-flex items-center rounded-2xl border border-transparent bg-brand-page px-4 py-2 text-sm font-medium text-brand-header-text hover:bg-brand-card transition"
                      >
                        {{ page.props.auth?.user?.name ?? 'Gost' }}
                        <svg class="-me-1 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </span>
                  </template>
                  <template #content>
                    <DropdownLink :href="route('profile.edit')">
                      Profil
                    </DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">
                      Odjava
                    </DropdownLink>
                  </template>
                </Dropdown>
              </div>

              <!-- Login / Register -->
              <div v-else class="hidden md:flex items-center gap-x-4 text-sm">
                <Link :href="route('login')" class="font-medium text-brand-header-muted hover:text-brand-header-text">Prijava</Link>
                <Link :href="route('register')" class="rounded-2xl bg-brand-accent px-5 py-2.5 font-medium text-white hover:bg-brand-accent-hover transition">Registracija</Link>
              </div>

              <!-- Hamburger -->
              <div class="-me-2 flex items-center md:hidden">
                <button
                  @click="showingNavigationDropdown = !showingNavigationDropdown"
                  class="inline-flex items-center justify-center rounded-md p-2 text-brand-header-muted hover:bg-brand-card hover:text-brand-header-text transition"
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

        <!-- Search — drugi red na < md, UVEK vidljiv (ne zavisi od
             showingNavigationDropdown) da search ne bude sakriven iza
             hamburgera. Na md+ je desktop traka iznad već dovoljna, pa je
             ovaj red md:hidden. Nije duplirana u hamburger meniju ispod
             (uklonjeno odatle) — jedno mesto za search na mobilnom. -->
        <div class="md:hidden border-t border-black/10 bg-brand-header px-4 py-3">
          <HeaderSearch
            input-class="w-full bg-brand-page border border-transparent focus:border-brand-accent focus:ring-2 focus:ring-brand-accent rounded-3xl py-2.5 px-4 pl-11 pr-9 text-sm text-brand-text-primary placeholder:text-brand-header-muted transition-all"
            icon-class="absolute left-4 top-1/2 -translate-y-1/2 text-brand-header-muted"
          />
        </div>

        <!-- Responsive Navigation Menu (original structure kept) -->
        <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="md:hidden border-t border-black/10 bg-brand-header">
          <div class="space-y-1 pb-3 pt-2 px-4">
            <ResponsiveNavLink :href="route('home')" :active="route().current('home')">
              Početna
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('shop')" :active="route().current('shop')">
              Prodavnica
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('cart')" :active="route().current('cart')">
              Korpa
              <span v-if="cart.itemCount > 0" class="ml-2 inline-flex items-center rounded-full bg-brand-accent px-2 py-1 text-xs font-bold text-white ring-2 ring-brand-page">
                {{ cart.itemCount }}
              </span>
            </ResponsiveNavLink>

            <!-- Admin links mobile -->
            <template v-if="page.props.auth?.user?.role === 'admin'">
              <ResponsiveNavLink :href="route('admin.categories.index')" :active="route().current('admin.categories.*')">
                Kategorije
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.products.index')" :active="route().current('admin.products.*')">
                Proizvodi
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.books.index')" :active="route().current('admin.books.*')">
                Knjige
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.authors.index')" :active="route().current('admin.authors.*')">
                Autori
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.publishers.index')" :active="route().current('admin.publishers.*')">
                Izdavači
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('admin.orders.index')" :active="route().current('admin.orders.*')">
                Porudžbine
              </ResponsiveNavLink>
            </template>
          </div>

          <!-- Responsive User Section -->
          <div v-if="page.props.auth?.user" class="border-t border-black/10 pb-4 pt-4 px-4">
            <div class="text-base font-medium text-brand-header-text mb-1">{{ page.props.auth?.user?.name ?? 'Gost' }}</div>
            <div class="text-sm text-brand-header-muted">{{ page.props.auth?.user?.email }}</div>
            <div class="mt-4 space-y-1">
              <ResponsiveNavLink :href="route('profile.edit')">Profil</ResponsiveNavLink>
              <ResponsiveNavLink :href="route('logout')" method="post" as="button">Odjava</ResponsiveNavLink>
            </div>
          </div>
          <div v-else class="border-t border-black/10 pb-4 pt-4 px-4 space-y-1">
            <ResponsiveNavLink :href="route('login')">Prijava</ResponsiveNavLink>
            <ResponsiveNavLink :href="route('register')">Registracija</ResponsiveNavLink>
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
