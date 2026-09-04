<script setup>
import { ref, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

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
const unreadNotificationsCount = computed(() => {
    return page.props.unreadNotificationsCount ?? page.props.auth?.unreadNotificationsCount ?? 0;
});

const toggleGroup = (group) => {
    expandedGroups.value[group] = !expandedGroups.value[group];
};
</script>

<template>
    <aside class="flex flex-col w-64 h-screen bg-[#0d0d0f] border-r border-white/10 z-50 transition-all duration-300 select-none">
        <!-- Logo area (Solo el Logo original) -->
        <div class="h-16 flex items-center px-6 border-b border-white/5 shrink-0">
            <Link :href="route('dashboard')">
                <ApplicationLogo class="h-8 w-auto fill-current text-brand-red" />
            </Link>
        </div>

        <!-- Navigation Area -->
        <div class="flex-1 overflow-y-auto py-6 space-y-1.5 px-4 scrollbar-hide">
            <!-- Dashboard -->
            <Link 
                :href="route('dashboard')" 
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                :class="route().current('dashboard') ? 'bg-white/10 text-white shadow-sm' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                <span>Dashboard</span>
            </Link>

            <!-- Notificaciones -->
            <Link 
                :href="route('notificaciones.index')" 
                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold group"
                :class="route().current('notificaciones.index') ? 'bg-white/10 text-white shadow-sm' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span>Notificaciones</span>
                </div>
                <span 
                    v-if="unreadNotificationsCount > 0" 
                    class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-extrabold text-white bg-brand-red rounded-full shadow-sm shadow-brand-red/40"
                >
                    {{ unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount }}
                </span>
            </Link>

            <!-- Group: Catálogo -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('colecciones.acceder')" class="space-y-1">
                <button 
                    @click="toggleGroup('books')" 
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="(route().current('libros.*') || route().current('catalogo.ajustes.*')) ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        <span>Catálogo</span>
                    </div>
                    <svg :class="{'rotate-180': expandedGroups.books}" class="h-3.5 w-3.5 transition-transform duration-300 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.books" class="pl-9 pr-2 space-y-1">
                    <Link :href="route('libros.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('libros.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Productos</Link>
                    <Link v-if="$page.props.auth.esAdmin" :href="route('catalogo.ajustes.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('catalogo.ajustes.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Categorías y Atributos</Link>
                </div>
            </div>

            <!-- Group: Ventas y Caja -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('ventas.acceder') || hasPermiso('caja.acceder') || hasPermiso('gastos.acceder') || hasPermiso('repartos.acceder')" class="space-y-1">
                <button 
                    @click="toggleGroup('operations')" 
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="(route().current('ventas.*') || route().current('cierre-cajas.*') || route().current('gastos.*') || route().current('rutas-reparto.*')) ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span>Ventas y Caja</span>
                    </div>
                    <svg :class="{'rotate-180': expandedGroups.operations}" class="h-3.5 w-3.5 transition-transform duration-300 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.operations" class="pl-9 pr-2 space-y-1">
                    <Link v-if="$page.props.auth.esAdmin || hasPermiso('ventas.acceder')" :href="route('ventas.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('ventas.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Ventas</Link>
                    <Link v-if="$page.props.auth.esAdmin || hasPermiso('caja.acceder')" :href="route('cierre-cajas.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('cierre-cajas.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Cierres de Caja</Link>
                    <Link v-if="$page.props.auth.esAdmin || hasPermiso('gastos.acceder')" :href="route('gastos.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('gastos.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Gastos</Link>
                    <Link v-if="$page.props.auth.esAdmin || hasPermiso('repartos.acceder')" :href="route('rutas-reparto.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('rutas-reparto.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Rutas de Reparto</Link>
                </div>
            </div>

            <!-- Group: Logística y Stock -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('stock.acceder')" class="space-y-1">
                <button 
                    @click="toggleGroup('inventory')" 
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="(route().current('stocks.*') || route().current('logistica.*')) ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span>Logística y Stock</span>
                    </div>
                    <svg :class="{'rotate-180': expandedGroups.inventory}" class="h-3.5 w-3.5 transition-transform duration-300 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.inventory" class="pl-9 pr-2 space-y-1">
                    <Link :href="route('stocks.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('stocks.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Stock</Link>
                    <Link :href="route('logistica.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('logistica.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Historial Logística</Link>
                </div>
            </div>

            <!-- Group: Clientes -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('clientes.acceder')" class="space-y-1">
                <button 
                    @click="toggleGroup('people')" 
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="(route().current('clientes.*') || route().current('suscripciones.*')) ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span>Clientes</span>
                    </div>
                    <svg :class="{'rotate-180': expandedGroups.people}" class="h-3.5 w-3.5 transition-transform duration-300 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.people" class="pl-9 pr-2 space-y-1">
                    <Link :href="route('clientes.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('clientes.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Lista de Clientes</Link>
                    <Link :href="route('suscripciones.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('suscripciones.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Suscripciones</Link>
                </div>
            </div>

            <!-- Group: Proveedores -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('proveedores.acceder')" class="space-y-1">
                <button 
                    @click="toggleGroup('proveedores')" 
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="(route().current('proveedores.*') || route().current('ordenes-compra.*')) ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0h4m-4 0H7m4 0V7m0 0h4m-4 0H7" />
                        </svg>
                        <span>Proveedores</span>
                    </div>
                    <svg :class="{'rotate-180': expandedGroups.proveedores}" class="h-3.5 w-3.5 transition-transform duration-300 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.proveedores" class="pl-9 pr-2 space-y-1">
                    <Link :href="route('proveedores.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('proveedores.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Proveedores</Link>
                    <Link :href="route('ordenes-compra.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('ordenes-compra.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Órdenes de Compra</Link>
                </div>
            </div>

            <!-- Group: Reportes -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('reportes.acceder')">
                <Link 
                    :href="route('reportes.index')" 
                    class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="route().current('reportes.*') ? 'bg-white/10 text-white shadow-sm' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Reportes</span>
                </Link>
            </div>

            <!-- Group: Administración -->
            <div v-if="$page.props.auth.esAdmin || hasPermiso('cargos.gestionar') || hasPermiso('empleados.acceder')" class="space-y-1">
                <button 
                    @click="toggleGroup('admin')" 
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all text-sm font-semibold"
                    :class="(route().current('cargos.*') || route().current('empleados.*') || route().current('sucursales.*')) ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white hover:bg-white/5'"
                >
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Administración</span>
                    </div>
                    <svg :class="{'rotate-180': expandedGroups.admin}" class="h-3.5 w-3.5 transition-transform duration-300 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-show="expandedGroups.admin" class="pl-9 pr-2 space-y-1">
                    <Link v-if="$page.props.auth.esAdmin || hasPermiso('empleados.acceder')" :href="route('empleados.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('empleados.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Recursos Humanos</Link>
                    <Link :href="route('sucursales.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('sucursales.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Sucursales</Link>
                    <Link v-if="$page.props.auth.esAdmin || hasPermiso('cargos.gestionar')" :href="route('cargos.index')" class="block py-2 px-3 rounded-lg text-sm font-semibold transition-colors" :class="route().current('cargos.*') ? 'text-white bg-white/5' : 'text-zinc-400 hover:text-white'">Cargos y Accesos</Link>
                </div>
            </div>

            <!-- Ver Catálogo tienda -->
            <a
                :href="route('catalogo.index')"
                target="_blank"
                class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-zinc-400 hover:text-white hover:bg-white/5 transition-all text-sm font-semibold mt-4"
            >
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                <span>Ver Tienda</span>
            </a>
        </div>

        <!-- User Area -->
        <div class="p-4 border-t border-white/5 bg-[#08080a] shrink-0 space-y-3 user-area">
            <Link :href="route('profile.edit')" class="flex items-center gap-3.5 group p-2 rounded-xl hover:bg-white/5 transition-colors">
                <div class="h-10 w-10 rounded-full bg-zinc-800 flex items-center justify-center border border-white/10 shrink-0">
                    <span class="text-base font-bold text-white uppercase">{{ $page.props.auth.user.name.substring(0,2) }}</span>
                </div>
                <div class="flex-1 overflow-hidden leading-tight">
                    <div class="text-base font-semibold text-white truncate">{{ $page.props.auth.user.name }}</div>
                    <div class="text-sm text-zinc-400 truncate mt-0.5">
                        {{ $page.props.auth.user.email || ($page.props.auth.esAdmin ? 'Administrador' : 'Operador') }}
                    </div>
                </div>
            </Link>

            <!-- Prominent White Logout Button -->
            <Link 
                :href="route('logout')" 
                method="post" 
                as="button" 
                class="w-full flex items-center justify-center gap-2 bg-white hover:bg-zinc-200 text-black font-bold text-sm py-2.5 rounded-xl transition-all shadow-md active:scale-95"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Cerrar sesión</span>
            </Link>
        </div>
    </aside>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');

.user-area, .user-area * {
    font-family: 'Montserrat', sans-serif !important;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
