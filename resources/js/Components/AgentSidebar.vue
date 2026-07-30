<script setup>
import { ref } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';

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
const hasPermiso = (codigo) => page.props.auth.esAdmin || (page.props.auth.permisos?.includes(codigo) ?? false);

const toggleGroup = (group) => {
    expandedGroups.value[group] = !expandedGroups.value[group];
};
</script>

<template>
    <aside class="flex flex-col w-64 h-screen bg-brand-surface border-r border-white/5 z-50 transition-all duration-300">
        <!-- Logo area -->
        <div class="h-16 flex items-center px-6 border-b border-white/5 shrink-0">
            <Link :href="route('dashboard')">
                <ApplicationLogo class="h-8 w-auto fill-current text-brand-red" />
            </Link>
        </div>

        <!-- Navigation Area -->
        <div class="flex-1 overflow-y-auto py-6 space-y-2 px-4 scrollbar-hide">
            <NavLink :href="route('dashboard')" :active="route().current('dashboard')" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/5 transition-all">
                <span class="text-[11px] font-black uppercase tracking-widest transition-colors" :class="route().current('dashboard') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Dashboard</span>
            </NavLink>

            <NavLink :href="route('notificaciones.index')" :active="route().current('notificaciones.index')" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/5 transition-all group">
                <span class="text-[11px] font-black uppercase tracking-widest transition-colors" :class="route().current('notificaciones.index') ? 'text-brand-red' : 'text-white/50 group-hover:text-white'">Notificaciones</span>
            </NavLink>

            <!-- Group: Libros -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('colecciones.acceder')" class="space-y-1">
                <button @click="toggleGroup('books')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-white/5 transition-all group" :class="(route().current('libros.*') || route().current('catalogo.ajustes.*')) ? 'text-brand-red bg-white/[0.02]' : 'text-white/40 hover:text-white'">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Catálogo</span>
                    <svg :class="{'rotate-180': expandedGroups.books}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.books" class="pl-4 space-y-1">
                    <DropdownLink :href="route('libros.index')" :active="route().current('libros.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('libros.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Catálogo Principal</DropdownLink>
                    <DropdownLink v-if="$page.props.auth.esAdmin" :href="route('catalogo.ajustes.index')" :active="route().current('catalogo.ajustes.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('catalogo.ajustes.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Características Catálogo</DropdownLink>
                </div>
            </div>

            <!-- Group: Operaciones -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('ventas.acceder') || hasPermiso('caja.acceder') || hasPermiso('gastos.acceder')" class="space-y-1">
                <button @click="toggleGroup('operations')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-white/5 transition-all group" :class="(route().current('ventas.*') || route().current('cierre-cajas.*') || route().current('gastos.*')) ? 'text-brand-red bg-white/[0.02]' : 'text-white/40 hover:text-white'">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Ventas y Caja</span>
                    <svg :class="{'rotate-180': expandedGroups.operations}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.operations" class="pl-4 space-y-1">
                    <DropdownLink v-if="$page.props.auth.esAdmin || hasPermiso('ventas.acceder')" :href="route('ventas.index')" :active="route().current('ventas.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('ventas.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Ventas</DropdownLink>
                    <DropdownLink v-if="$page.props.auth.esAdmin || hasPermiso('caja.acceder')" :href="route('cierre-cajas.index')" :active="route().current('cierre-cajas.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('cierre-cajas.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Cierres de Caja</DropdownLink>
                    <DropdownLink v-if="$page.props.auth.esAdmin || hasPermiso('gastos.acceder')" :href="route('gastos.index')" :active="route().current('gastos.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('gastos.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Gastos</DropdownLink>
                </div>
            </div>

            <!-- Group: Inventario -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('stock.acceder') || hasPermiso('repartos.acceder')" class="space-y-1">
                <button @click="toggleGroup('inventory')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-white/5 transition-all group" :class="(route().current('sucursales.*') || route().current('stocks.*') || route().current('logistica.*') || route().current('rutas-reparto.*')) ? 'text-brand-red bg-white/[0.02]' : 'text-white/40 hover:text-white'">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Logística y Stock</span>
                    <svg :class="{'rotate-180': expandedGroups.inventory}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.inventory" class="pl-4 space-y-1">
                    <DropdownLink :href="route('stocks.index')" :active="route().current('stocks.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('stocks.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Stock</DropdownLink>
                    <DropdownLink :href="route('logistica.index')" :active="route().current('logistica.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('logistica.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Historial de Logística</DropdownLink>
                    <DropdownLink :href="route('sucursales.index')" :active="route().current('sucursales.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('sucursales.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Sucursales</DropdownLink>
                    <DropdownLink v-if="$page.props.auth.esAdmin || hasPermiso('repartos.acceder')" :href="route('rutas-reparto.index')" :active="route().current('rutas-reparto.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('rutas-reparto.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Rutas de Reparto</DropdownLink>
                </div>
            </div>

            <!-- Group: Personas -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('clientes.acceder')" class="space-y-1">
                <button @click="toggleGroup('people')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-white/5 transition-all group" :class="(route().current('clientes.*') || route().current('suscripciones.*')) ? 'text-brand-red bg-white/[0.02]' : 'text-white/40 hover:text-white'">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Clientes</span>
                    <svg :class="{'rotate-180': expandedGroups.people}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.people" class="pl-4 space-y-1">
                    <DropdownLink :href="route('clientes.index')" :active="route().current('clientes.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('clientes.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Clientes</DropdownLink>
                    <DropdownLink :href="route('suscripciones.index')" :active="route().current('suscripciones.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('suscripciones.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Suscripciones</DropdownLink>
                </div>
            </div>

            <!-- Group: Proveedores -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('proveedores.acceder')" class="space-y-1">
                <button @click="toggleGroup('proveedores')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-white/5 transition-all group" :class="(route().current('proveedores.*') || route().current('ordenes-compra.*')) ? 'text-brand-red bg-white/[0.02]' : 'text-white/40 hover:text-white'">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Proveedores</span>
                    <svg :class="{'rotate-180': expandedGroups.proveedores}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.proveedores" class="pl-4 space-y-1">
                    <DropdownLink :href="route('proveedores.index')" :active="route().current('proveedores.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('proveedores.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Proveedores</DropdownLink>
                    <DropdownLink :href="route('ordenes-compra.index')" :active="route().current('ordenes-compra.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('ordenes-compra.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Órdenes de Compra</DropdownLink>
                </div>
            </div>

            <!-- Group: Reportes -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('reportes.acceder')" class="space-y-1">
                <NavLink :href="route('reportes.index')" :active="route().current('reportes.*')" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/5 transition-all">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] transition-colors" :class="route().current('reportes.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Reportes</span>
                </NavLink>
            </div>

            <!-- Group: Administración -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('cargos.gestionar') || hasPermiso('empleados.acceder')" class="space-y-1">
                <button @click="toggleGroup('admin')" class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-white/5 transition-all group" :class="(route().current('cargos.*') || route().current('empleados.*')) ? 'text-brand-red bg-white/[0.02]' : 'text-white/40 hover:text-white'">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Administración</span>
                    <svg :class="{'rotate-180': expandedGroups.admin}" class="h-4 w-4 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.admin" class="pl-4 space-y-1">
                    <DropdownLink v-if="$page.props.auth.esAdmin || hasPermiso('empleados.acceder')" :href="route('empleados.index')" :active="route().current('empleados.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('empleados.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Recursos Humanos</DropdownLink>
                    <DropdownLink v-if="$page.props.auth.esAdmin || hasPermiso('cargos.gestionar')" :href="route('cargos.index')" :active="route().current('cargos.*')" class="block py-2 text-[10px] font-bold uppercase transition-colors" :class="route().current('cargos.*') ? 'text-brand-red' : 'text-white/50 hover:text-white'">Cargos y Accesos</DropdownLink>
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
        <div class="p-6 border-t border-white/5 bg-black/20 relative shrink-0">
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
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
