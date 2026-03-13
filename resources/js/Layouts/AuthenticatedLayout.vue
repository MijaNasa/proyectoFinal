<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div>
        <div class="min-h-screen bg-brand-black">
            <nav
                class="border-b border-white/5 bg-brand-surface/80 backdrop-blur-md sticky top-0 z-50"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-10 w-auto fill-current text-brand-red"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                    class="text-white hover:text-brand-red transition-colors"
                                >
                                    Dashboard
                                </NavLink>
                                
                                <!-- Catálogos -->
                                <div class="hidden sm:flex sm:items-center sm:ms-6">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white/70 hover:text-brand-red focus:outline-none transition ease-in-out duration-150">
                                                Catálogo
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <div class="bg-brand-surface border border-white/10 rounded-md overflow-hidden">
                                                <DropdownLink :href="route('categorias.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Categorías</DropdownLink>
                                                <DropdownLink :href="route('autores.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Autores</DropdownLink>
                                                <DropdownLink :href="route('editoriales.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Editoriales</DropdownLink>
                                                <DropdownLink :href="route('idiomas.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Idiomas</DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- Libros -->
                                <div class="hidden sm:flex sm:items-center sm:ms-2">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white/70 hover:text-brand-red focus:outline-none transition ease-in-out duration-150">
                                                Libros
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <div class="bg-brand-surface border border-white/10 rounded-md overflow-hidden">
                                                <DropdownLink :href="route('libro-masters.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Títulos</DropdownLink>
                                                <DropdownLink :href="route('libros.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Ediciones</DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- Operaciones -->
                                <div class="hidden sm:flex sm:items-center sm:ms-2">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white/70 hover:text-brand-red focus:outline-none transition ease-in-out duration-150">
                                                Operaciones
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <div class="bg-brand-surface border border-white/10 rounded-md overflow-hidden">
                                                <DropdownLink :href="route('ventas.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Ventas</DropdownLink>
                                                <DropdownLink :href="route('cierre-cajas.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Cajas</DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- Inventario -->
                                <div class="hidden sm:flex sm:items-center sm:ms-2">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white/70 hover:text-brand-red focus:outline-none transition ease-in-out duration-150">
                                                Inventario
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <div class="bg-brand-surface border border-white/10 rounded-md overflow-hidden">
                                                <DropdownLink :href="route('sucursales.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Sucursales</DropdownLink>
                                                <DropdownLink :href="route('stocks.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Stock</DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- Personas -->
                                <div class="hidden sm:flex sm:items-center sm:ms-2">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white/70 hover:text-brand-red focus:outline-none transition ease-in-out duration-150">
                                                Personas
                                                <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <div class="bg-brand-surface border border-white/10 rounded-md overflow-hidden">
                                                <DropdownLink :href="route('clientes.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Clientes</DropdownLink>
                                                <DropdownLink :href="route('empleados.index')" class="hover:bg-brand-red/10 hover:text-brand-red transition-colors">Empleados</DropdownLink>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-brand-surface px-3 py-2 text-sm font-medium leading-4 text-white transition duration-150 ease-in-out hover:text-brand-red focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}

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
                                        <div class="bg-brand-surface border border-white/10 rounded-md overflow-hidden">
                                            <DropdownLink
                                                :href="route('profile.edit')"
                                                class="hover:bg-brand-red/10 hover:text-brand-red transition-colors"
                                            >
                                                Profile
                                            </DropdownLink>
                                            <DropdownLink
                                                :href="route('logout')"
                                                method="post"
                                                as="button"
                                                class="hover:bg-brand-red/10 hover:text-brand-red transition-colors"
                                            >
                                                Log Out
                                            </DropdownLink>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-white/70 transition duration-150 ease-in-out hover:bg-brand-surface hover:text-brand-red focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
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
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden bg-brand-surface/90 backdrop-blur-md"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                            class="text-white active:bg-brand-red/20 active:text-brand-red"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-white/10 pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-white"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-white/50">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')" class="text-white/70 hover:text-brand-red">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-white/70 hover:text-brand-red"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-transparent border-b border-white/5"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
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
