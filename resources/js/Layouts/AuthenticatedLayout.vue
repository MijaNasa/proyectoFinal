<script setup>
import { ref, watch } from 'vue';
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
    repartos: false,
    reportes: false,
    proveedores: false,
});

const page = usePage();
const hasPermiso = (codigo) => page.props.auth.permisos?.includes(codigo) ?? false;

const toast = ref(null);
let toastTimer = null;
const showToast = (msg, type) => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { msg, type };
    toastTimer = setTimeout(() => toast.value = null, 3500);
};
watch(() => page.props.flash, (flash) => {
    if (flash?.success) showToast(flash.success, 'success');
    else if (flash?.error)   showToast(flash.error,   'error');
    else if (flash?.warning) showToast(flash.warning, 'warning');
}, { deep: true });

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

                <NavLink :href="route('notificaciones.index')" :active="route().current('notificaciones.index')" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/5 transition-all group">
                    <span class="text-[11px] font-black uppercase tracking-widest group-hover:text-brand-red transition-colors">Notificaciones</span>
                </NavLink>



                <!-- Group: Libros -->
                <div v-if="hasPermiso('colecciones.acceder')" class="space-y-1">
                    <button @click="toggleGroup('books')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em]">Colecciones</span>
                        <svg :class="{'rotate-180': expandedGroups.books}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.books" class="pl-4 space-y-1">
                        <DropdownLink :href="route('libros.index')" :active="route().current('libros.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Catálogo Principal</DropdownLink>
                        <DropdownLink :href="route('precios.index')" :active="route().current('precios.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Precios</DropdownLink>
                        <DropdownLink v-if="$page.props.auth.esAdmin" :href="route('catalogo.ajustes.index')" :active="route().current('catalogo.ajustes.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Ajustes de Catálogo</DropdownLink>
                    </div>
                </div>

                <!-- Group: Operaciones -->
                <div v-if="hasPermiso('ventas.acceder') || hasPermiso('caja.acceder') || hasPermiso('gastos.acceder')" class="space-y-1">
                    <button @click="toggleGroup('operations')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-brand-red italic">Terminal Ventas</span>
                        <svg :class="{'rotate-180': expandedGroups.operations}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.operations" class="pl-4 space-y-1">
                        <DropdownLink v-if="hasPermiso('ventas.acceder')" :href="route('ventas.index')" :active="route().current('ventas.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Nueva Venta / Historial</DropdownLink>
                        <DropdownLink v-if="hasPermiso('caja.acceder')" :href="route('cierre-cajas.index')" :active="route().current('cierre-cajas.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Cierres de Caja</DropdownLink>
                        <DropdownLink v-if="hasPermiso('gastos.acceder')" :href="route('gastos.index')" :active="route().current('gastos.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Gastos</DropdownLink>
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
                        <DropdownLink :href="route('logistica.index')" :active="route().current('logistica.*')" class="block py-2 text-[10px] font-bold uppercase text-brand-red hover:text-white">Logística y Traslados</DropdownLink>
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
                        <DropdownLink v-if="hasPermiso('clientes.acceder')" :href="route('suscripciones.index')" :active="route().current('suscripciones.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Suscripciones</DropdownLink>
                        <DropdownLink v-if="hasPermiso('empleados.acceder')" :href="route('empleados.index')" :active="route().current('empleados.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Recursos Humanos</DropdownLink>
                    </div>
                </div>

                <!-- Group: Repartos -->
                <div v-if="hasPermiso('repartos.acceder')" class="space-y-1">
                    <button @click="toggleGroup('repartos')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-400">Repartos</span>
                        <svg :class="{'rotate-180': expandedGroups.repartos}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.repartos" class="pl-4 space-y-1">
                        <DropdownLink :href="route('rutas-reparto.index')" :active="route().current('rutas-reparto.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Rutas de Reparto</DropdownLink>
                    </div>
                </div>

                <!-- Group: Proveedores -->
                <div v-if="hasPermiso('proveedores.acceder')" class="space-y-1">
                    <button @click="toggleGroup('proveedores')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg text-white/40 hover:text-white hover:bg-white/5 transition-all group">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-orange-400">Proveedores</span>
                        <svg :class="{'rotate-180': expandedGroups.proveedores}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div v-show="expandedGroups.proveedores" class="pl-4 space-y-1">
                        <DropdownLink :href="route('proveedores.index')" :active="route().current('proveedores.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Proveedores</DropdownLink>
                        <DropdownLink :href="route('series.index')" :active="route().current('series.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Series</DropdownLink>
                        <DropdownLink :href="route('ordenes-compra.index')" :active="route().current('ordenes-compra.*')" class="block py-2 text-[10px] font-bold uppercase text-white/50 hover:text-brand-red">Órdenes de Compra</DropdownLink>
                    </div>
                </div>

                <!-- Group: Reportes -->
                <div v-if="hasPermiso('reportes.acceder')" class="space-y-1">
                    <NavLink :href="route('reportes.index')" :active="route().current('reportes.*')" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/5 transition-all">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-green-400">Reportes</span>
                    </NavLink>
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
                <!-- Ver Catálogo (visible para todos) -->
                <a
                    :href="route('catalogo.index')"
                    target="_blank"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-white/30 hover:text-white hover:bg-white/5 transition-all mt-2"
                >
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    <span class="text-[10px] font-black uppercase tracking-widest">Ver Catálogo</span>
                </a>
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
    <!-- Toast global -->
    <transition name="toast">
        <div
            v-if="toast"
            class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl text-sm font-black uppercase tracking-widest"
            :class="{
                'bg-green-500/90 text-white':  toast.type === 'success',
                'bg-yellow-500/90 text-black': toast.type === 'warning',
                'bg-red-600/90 text-white':    toast.type === 'error',
            }"
        >
            <svg v-if="toast.type === 'success'" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <svg v-else-if="toast.type === 'warning'" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ toast.msg }}
        </div>
    </transition>
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
.toast-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.toast-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.toast-enter-from  { opacity: 0; transform: translateY(12px); }
.toast-leave-to    { opacity: 0; transform: translateY(12px); }
</style>
