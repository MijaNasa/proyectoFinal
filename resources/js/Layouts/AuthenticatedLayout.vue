<script setup>
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const showUserMenu = ref(false);
const expandedGroups = ref({
    catalog: false,
    books: false,
    operations: false,
    inventory: false,
    people: false,
    admin: false,
});

const hasPermiso = (codigo) => usePage().props.auth.permisos?.includes(codigo) ?? false;

const toggleGroup = (group) => {
    expandedGroups.value[group] = !expandedGroups.value[group];
};
</script>

<template>
    <div class="flex h-screen bg-brand-black overflow-hidden">
        <!-- Sidebar for Desktop -->
        <aside class="hidden md:flex flex-col w-64 bg-brand-surface border-r border-white/5 z-50 transition-all duration-300">
            <!-- Logo area -->
            <div class="h-16 flex items-center px-6 border-b border-white/5">
                <Link :href="route('dashboard')">
                    <ApplicationLogo />
                </Link>
            </div>

            <!-- Navigation Area -->
            <div class="flex-1 overflow-y-auto py-6 space-y-2 px-4 scrollbar-hide">
                <NavLink :href="route('dashboard')" :active="route().current('dashboard')" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/5 transition-all">
                    <span class="text-[11px] font-black uppercase tracking-widest">Dashboard</span>
                </NavLink>

                <!-- Group: Catálogo -->
                <div v-if="hasPermiso('catalogo.acceder')" class="space-y-1">
                    <button @click="toggleGroup('catalog')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">Catálogo Base</span>
                        <svg :class="{'rotate-180': expandedGroups.catalog}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.catalog" class="pl-4 space-y-1 overflow-hidden transition-all duration-300">
                        <DropdownLink :href="route('categorias.index')" :active="route().current('categorias.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Categorías</DropdownLink>
                        <DropdownLink :href="route('autores.index')" :active="route().current('autores.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Autores</DropdownLink>
                        <DropdownLink :href="route('editoriales.index')" :active="route().current('editoriales.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Editoriales</DropdownLink>
                        <DropdownLink :href="route('idiomas.index')" :active="route().current('idiomas.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Idiomas</DropdownLink>
                    </div>
                </div>

                <!-- Group: Libros -->
                <div v-if="hasPermiso('colecciones.acceder')" class="space-y-1">
                    <button @click="toggleGroup('books')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">Colecciones</span>
                        <svg :class="{'rotate-180': expandedGroups.books}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.books" class="pl-4 space-y-1">
                        <DropdownLink :href="route('libro-masters.index')" :active="route().current('libro-masters.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Títulos</DropdownLink>
                        <DropdownLink :href="route('libros.index')" :active="route().current('libros.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Ediciones</DropdownLink>
                    </div>
                </div>

                <!-- Group: Operaciones -->
                <div v-if="hasPermiso('ventas.acceder') || hasPermiso('caja.acceder')" class="space-y-1">
                    <button @click="toggleGroup('operations')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-brand-red italic">Terminal Ventas</span>
                        <svg :class="{'rotate-180': expandedGroups.operations}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.operations" class="pl-4 space-y-1">
                        <DropdownLink v-if="hasPermiso('ventas.acceder')" :href="route('ventas.index')" :active="route().current('ventas.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Nueva Venta / Historial</DropdownLink>
                        <DropdownLink v-if="hasPermiso('caja.acceder')" :href="route('cierre-cajas.index')" :active="route().current('cierre-cajas.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Cierres de Caja</DropdownLink>
                    </div>
                </div>

                <!-- Group: Inventario -->
                <div v-if="hasPermiso('stock.acceder')" class="space-y-1">
                    <button @click="toggleGroup('inventory')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">Logística</span>
                        <svg :class="{'rotate-180': expandedGroups.inventory}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.inventory" class="pl-4 space-y-1">
                        <DropdownLink :href="route('sucursales.index')" :active="route().current('sucursales.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Sucursales</DropdownLink>
                        <DropdownLink :href="route('stocks.index')" :active="route().current('stocks.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Control de Stock</DropdownLink>
                    </div>
                </div>

                <!-- Group: Personas -->
                <div v-if="hasPermiso('clientes.acceder') || hasPermiso('empleados.acceder')" class="space-y-1">
                    <button @click="toggleGroup('people')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">Relaciones</span>
                        <svg :class="{'rotate-180': expandedGroups.people}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.people" class="pl-4 space-y-1">
                        <DropdownLink v-if="hasPermiso('clientes.acceder')" :href="route('clientes.index')" :active="route().current('clientes.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Clientes</DropdownLink>
                        <DropdownLink v-if="hasPermiso('empleados.acceder')" :href="route('empleados.index')" :active="route().current('empleados.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Recursos Humanos</DropdownLink>
                    </div>
                </div>

                <!-- Group: Administración -->
                <div v-if="hasPermiso('cargos.gestionar')" class="space-y-1">
                    <button @click="toggleGroup('admin')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">Administración</span>
                        <svg :class="{'rotate-180': expandedGroups.admin}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.admin" class="pl-4 space-y-1">
                        <DropdownLink :href="route('cargos.index')" :active="route().current('cargos.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Cargos y Accesos</DropdownLink>
                    </div>
                </div>
            </div>

            <!-- User Area -->
            <div class="p-6 border-t border-white/5 bg-black/20 relative">
                <button @click="showUserMenu = !showUserMenu" class="w-full flex items-center gap-3 group">
                    <div class="h-8 w-8 rounded-full bg-brand-red/20 flex items-center justify-center border border-brand-red/30 group-hover:bg-brand-red transition-all">
                        <span class="text-[10px] font-black text-brand-red group-hover:text-white uppercase">{{ $page.props.auth.user.name.substring(0,2) }}</span>
                    </div>
                    <div class="flex-1 text-left overflow-hidden">
                        <div class="text-[10px] font-black text-white uppercase truncate">{{ $page.props.auth.user.name }}</div>
                        <div class="text-[8px] font-bold text-white/30 uppercase tracking-tighter truncate">
                            {{ $page.props.auth.esAdmin ? 'Administrador' : ($page.props.auth.esGerente ? 'Gerente' : 'Operador Activo') }}
                        </div>
                    </div>
                </button>

                <!-- Menú hacia arriba -->
                <div v-if="showUserMenu" class="absolute bottom-full left-4 right-4 mb-2 bg-brand-surface border border-white/10 rounded-md overflow-hidden shadow-xl z-50">
                    <Link :href="route('profile.edit')" class="block px-4 py-3 text-[10px] font-bold uppercase text-white/60 hover:bg-brand-red/10 hover:text-brand-red transition-colors tracking-widest">Ajustes de Perfil</Link>
                    <Link :href="route('logout')" method="post" as="button" class="w-full text-left px-4 py-3 text-[10px] font-bold uppercase text-white/60 hover:bg-brand-red/10 hover:text-brand-red transition-colors tracking-widest">Cerrar Sesión</Link>
                </div>
                <div v-if="showUserMenu" class="fixed inset-0 z-40" @click="showUserMenu = false"></div>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
            <!-- Mobile Header -->
            <nav class="md:hidden bg-brand-surface border-b border-white/5 p-4 flex justify-between items-center z-50">
                <Link :href="route('dashboard')">
                    <ApplicationLogo class="h-8 w-auto fill-current text-brand-red" />
                </Link>
                <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path v-if="!showingNavigationDropdown" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </nav>

            <!-- Mobile Menu Dropdown -->
            <transition name="fade">
                <div v-if="showingNavigationDropdown" class="md:hidden fixed inset-0 z-40 bg-black/95 backdrop-blur-xl flex flex-col p-10 space-y-6">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" class="text-3xl font-black uppercase tracking-tighter">Dashboard</ResponsiveNavLink>
                    <div class="h-px bg-white/10"></div>
                    <ResponsiveNavLink :href="route('ventas.index')" class="text-xl font-black uppercase text-brand-red italic">Ventas</ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('libros.index')" class="text-xl font-black uppercase">Libros</ResponsiveNavLink>
                    <ResponsiveNavLink :href="route('stocks.index')" class="text-xl font-black uppercase">Inventario</ResponsiveNavLink>
                    <div class="mt-auto pt-10 border-t border-white/10 flex items-center justify-between">
                         <div>
                             <div class="text-lg font-black text-white italic uppercase">{{ $page.props.auth.user.name }}</div>
                             <button @click="router.post(route('logout'))" class="text-xs font-black text-brand-red uppercase tracking-widest mt-2">Cerrar Sesión</button>
                         </div>
                         <button @click="showingNavigationDropdown = false" class="h-12 w-12 rounded-full border border-white/20 flex items-center justify-center text-white">X</button>
                    </div>
                </div>
            </transition>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto relative scroll-smooth bg-brand-black">
                <!-- Page Heading -->
                <header class="bg-brand-black border-b border-white/5" v-if="$slots.header">
                    <div class="mx-auto max-w-7xl px-8 py-10">
                        <slot name="header" />
                    </div>
                </header>

                <div>
                    <slot />
                </div>
                
                <!-- Footer Info -->
                <footer class="p-8 text-center border-t border-white/5 opacity-20">
                    <p class="text-[10px] font-black uppercase tracking-[0.5em]">ERP Internal System &copy; 2026 Puro Comic</p>
                </footer>
            </main>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
