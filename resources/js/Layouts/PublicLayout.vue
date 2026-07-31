<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AgentSidebar from '@/Components/AgentSidebar.vue';

const logout = () => router.post(route('logout'));

const isMenuOpen = ref(false);
const isTerminalMenuOpen = ref(false);
const page = usePage();
const carritoCount = computed(() => page.props.carritoCount ?? 0);
const carritoTotal = computed(() => page.props.carritoTotal ?? 0);
const user = computed(() => page.props.auth?.user ?? null);

const navSearch = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchDropdown = ref(false);
const searchContainerRef = ref(null);
let searchTimeout = null;

const onNavSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    const query = navSearch.value.trim();
    if (query.length < 2) {
        searchResults.value = [];
        showSearchDropdown.value = false;
        return;
    }

    searchTimeout = setTimeout(async () => {
        isSearching.value = true;
        try {
            const res = await fetch(route('catalogo.buscar-ajax', { q: query }));
            if (res.ok) {
                const data = await res.json();
                searchResults.value = data;
                showSearchDropdown.value = true;
            }
        } catch (e) {
            console.error(e);
        } finally {
            isSearching.value = false;
        }
    }, 200);
};

const handleClickOutside = (event) => {
    if (searchContainerRef.value && !searchContainerRef.value.contains(event.target)) {
        showSearchDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const executeNavSearch = () => {
    if (!navSearch.value.trim()) return;
    showSearchDropdown.value = false;
    router.get(route('catalogo.index'), { search: navSearch.value.trim() });
};

const fmtARS = (val) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val);

const activeDropdown = ref(null);

const menuMangas = [
    { nombre: 'Ivrea Argentina', search: 'Ivrea' },
    { nombre: 'Panini Comics', search: 'Panini' },
    { nombre: 'Ovni Press', search: 'Ovni Press' },
    { nombre: 'ECC Ediciones', search: 'ECC' },
    { nombre: 'Planeta Cómic', search: 'Planeta' },
    { nombre: 'Distrito Manga', search: 'Distrito Manga' },
    { nombre: 'Milky Way', search: 'Milky Way' },
];

const menuComics = [
    { nombre: 'Ovni Press', search: 'Ovni' },
    { nombre: 'Panini Comics', search: 'Panini' },
    { nombre: 'ECC Ediciones', search: 'ECC' },
    { nombre: 'Planeta Cómic', search: 'Planeta' },
    { nombre: 'Moebius / Indep.', search: 'Moebius' },
];

const filterBySearch = (query) => {
    router.get(route('catalogo.index'), { search: query });
};

const toast = ref(null);
let toastTimer = null;

const showToast = (msg, type) => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { msg, type };
    toastTimer = setTimeout(() => toast.value = null, 3500);
};

watch(() => page.props.flash, (flash) => {
    if (flash?.success) showToast(flash.success, 'success');
    else if (flash?.warning) showToast(flash.warning, 'warning');
    else if (flash?.error) showToast(flash.error, 'error');
}, { deep: true });
</script>

<template>
    <div class="min-h-screen bg-[#0A0A0A] text-white font-sans selection:bg-brand-red selection:text-white flex">

        <!-- ─── Persistent Sidebar (Admin / Empleado, desktop only) ─── -->
        <div
            v-if="page.props.auth?.empleado || page.props.auth?.esAdmin"
            class="hidden xl:flex w-64 shrink-0"
        >
            <!-- Fixed so it doesn't scroll with content -->
            <div class="fixed top-0 left-0 w-64 h-screen bg-[#131316] border-r border-white/5 z-40">
                <AgentSidebar />
            </div>
        </div>

        <!-- ─── Main Content Column ─── -->
        <div class="flex-1 min-w-0 flex flex-col">

            <!-- ═══════════════════════════════════════════════════════
                 STICKY HEADER (banner included so it always stays visible)
                 ═══════════════════════════════════════════════════════ -->
            <header class="sticky top-0 z-50 bg-[#1A1A1A] border-b border-white/10 shadow-2xl">

                <!-- Announcement Strip -->
                <div class="bg-white/5 text-white/90 py-1.5 px-4 text-center text-[11px] font-bold uppercase tracking-wider border-b border-white/10 truncate">
                    ENVÍOS GRATIS A PARTIR DE $80.000 A SUCURSAL DE CORREO / ENVÍOS GRATIS A DOMICILIO A PARTIR DE $100.000
                </div>

                <!-- Top Utility Bar -->
                <div class="bg-[#0A0A0A] border-b border-white/10">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center justify-between h-14">

                            <!-- Left: Logo (terminal button only on mobile for admin/employee) -->
                            <div class="flex items-center gap-4">
                                <!-- Mobile terminal toggle (hidden on xl when sidebar is always shown) -->
                                <button
                                    v-if="page.props.auth?.empleado || page.props.auth?.esAdmin"
                                    @click="isTerminalMenuOpen = true"
                                    class="xl:hidden flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-white bg-white/10 border border-white/20 rounded hover:bg-white/20 transition-colors"
                                >
                                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                    </svg>
                                    <span>Terminal</span>
                                </button>

                                <!-- Store Logo (no terminal icon next to it) -->
                                <Link :href="route('catalogo.index')" class="flex items-center gap-2 group">
                                    <div class="w-8 h-8 bg-brand-red flex items-center justify-center rounded group-hover:rotate-6 transition-transform shadow-[0_0_15px_rgba(230,25,25,0.4)]">
                                        <span class="text-xl font-black italic">P</span>
                                    </div>
                                    <span class="text-lg font-extrabold uppercase tracking-tight text-white group-hover:text-brand-red transition-colors">
                                        Puro<span class="text-brand-red">Comic</span>
                                    </span>
                                </Link>
                            </div>

                            <!-- Right: Auth actions + Cart -->
                            <div class="hidden md:flex items-center space-x-6 text-xs font-bold uppercase tracking-wider text-white/70">
                                <template v-if="user">
                                    <Link :href="route('mi-cuenta.index')" class="flex items-center gap-1.5 hover:text-white transition-colors">
                                        <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Mi Cuenta</span>
                                    </Link>
                                    <button @click="logout" class="hover:text-brand-red transition-colors text-white/40">
                                        Salir
                                    </button>
                                </template>

                                <template v-else>
                                    <Link :href="route('register')" class="flex items-center gap-1.5 hover:text-white transition-colors">
                                        <svg class="w-4 h-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 0112 0v1H3v-1z" />
                                        </svg>
                                        <span>Crear Cuenta</span>
                                    </Link>

                                    <Link :href="route('login')" class="flex items-center gap-1.5 hover:text-white transition-colors">
                                        <svg class="w-4 h-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                        </svg>
                                        <span>Iniciar Sesión</span>
                                    </Link>
                                </template>

                                <!-- Carrito -->
                                <Link :href="route('carrito.index')" class="flex items-center gap-2 px-3 py-1.5 bg-white/5 hover:bg-white/10 rounded-lg border border-white/10 text-white transition-all group">
                                    <svg class="w-4 h-4 text-brand-red group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z" />
                                    </svg>
                                    <span>{{ carritoCount }} - {{ fmtARS(carritoTotal) }}</span>
                                </Link>
                            </div>

                            <!-- Mobile Hamburger -->
                            <div class="md:hidden flex items-center gap-3">
                                <Link :href="route('carrito.index')" class="relative p-1 text-white/70">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span v-if="carritoCount > 0" class="absolute -top-1 -right-1 bg-brand-red text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                                        {{ carritoCount }}
                                    </span>
                                </Link>
                                <button @click="isMenuOpen = !isMenuOpen" class="p-1 text-white/70 hover:text-white">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path v-if="!isMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lower Navbar with Category Dropdowns & Search -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 hidden md:block">
                    <div class="flex items-center justify-between h-12">
                        <!-- Navigation Links -->
                        <nav class="flex items-center space-x-1 text-xs font-bold uppercase tracking-wider text-white/70">
                            <Link :href="route('catalogo.index')" class="px-3 py-2 rounded-md hover:text-white hover:bg-white/5 transition-colors">
                                INICIO
                            </Link>

                            <!-- Mangas Dropdown -->
                            <div class="relative" @mouseenter="activeDropdown = 'mangas'" @mouseleave="activeDropdown = null">
                                <button class="flex items-center gap-1 px-3 py-2 rounded-md hover:text-white hover:bg-white/5 transition-colors">
                                    <span>MANGAS</span>
                                    <svg class="w-3 h-3 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <transition name="fade">
                                    <div v-if="activeDropdown === 'mangas'" class="absolute left-0 top-full mt-1 w-48 bg-[#1A1A1A] border border-white/10 rounded-lg shadow-xl py-2 z-50">
                                        <button
                                            v-for="item in menuMangas"
                                            :key="item.nombre"
                                            @click="filterBySearch(item.search)"
                                            class="w-full text-left px-4 py-2 text-xs text-white/70 hover:text-white hover:bg-brand-red/20 transition-colors uppercase font-bold"
                                        >{{ item.nombre }}</button>
                                    </div>
                                </transition>
                            </div>

                            <!-- Comics Dropdown -->
                            <div class="relative" @mouseenter="activeDropdown = 'comics'" @mouseleave="activeDropdown = null">
                                <button class="flex items-center gap-1 px-3 py-2 rounded-md hover:text-white hover:bg-white/5 transition-colors">
                                    <span>COMICS</span>
                                    <svg class="w-3 h-3 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <transition name="fade">
                                    <div v-if="activeDropdown === 'comics'" class="absolute left-0 top-full mt-1 w-48 bg-[#1A1A1A] border border-white/10 rounded-lg shadow-xl py-2 z-50">
                                        <button
                                            v-for="item in menuComics"
                                            :key="item.nombre"
                                            @click="filterBySearch(item.search)"
                                            class="w-full text-left px-4 py-2 text-xs text-white/70 hover:text-white hover:bg-brand-red/20 transition-colors uppercase font-bold"
                                        >{{ item.nombre }}</button>
                                    </div>
                                </transition>
                            </div>

                            <!-- Categorías Dropdown -->
                            <div class="relative" @mouseenter="activeDropdown = 'categorias'" @mouseleave="activeDropdown = null">
                                <button class="flex items-center gap-1 px-3 py-2 rounded-md hover:text-white hover:bg-white/5 transition-colors">
                                    <span>CATEGORÍAS</span>
                                    <svg class="w-3 h-3 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <transition name="fade">
                                    <div v-if="activeDropdown === 'categorias'" class="absolute left-0 top-full mt-1 w-48 bg-[#1A1A1A] border border-white/10 rounded-lg shadow-xl py-2 z-50 max-h-[60vh] overflow-y-auto custom-scrollbar">
                                        <Link
                                            v-for="cat in $page.props.globalCategorias"
                                            :key="cat.id"
                                            :href="route('catalogo.index', { categoria: cat.id })"
                                            class="block w-full text-left px-4 py-2 text-xs text-white/70 hover:text-white hover:bg-brand-red/20 transition-colors uppercase font-bold"
                                        >{{ cat.nombre }}</Link>
                                    </div>
                                </transition>
                            </div>

                            <Link :href="route('catalogo.index')" class="px-3 py-2 rounded-md hover:text-white hover:bg-white/5 transition-colors">PREVENTAS</Link>
                            <Link :href="route('nosotros')" class="px-3 py-2 rounded-md hover:text-white hover:bg-white/5 transition-colors">NOSOTROS</Link>
                        </nav>

                        <!-- Search Bar -->
                        <div ref="searchContainerRef" class="relative w-80 md:w-[460px] lg:w-[560px]">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="navSearch"
                                @input="onNavSearchInput"
                                @focus="showSearchDropdown = searchResults.length > 0"
                                @keyup.enter="executeNavSearch"
                                type="text"
                                placeholder="Buscar mangas, cómics, autores, editoriales..."
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-2xl py-2.5 pl-10 pr-10 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                            >
                            <button @click="executeNavSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </button>

                            <!-- Live Autocomplete Panel -->
                            <transition name="fade">
                                <div
                                    v-if="showSearchDropdown && (searchResults.length || isSearching)"
                                    class="absolute right-0 left-0 top-full mt-2 w-full bg-[#131316] border border-white/10 rounded-2xl shadow-2xl overflow-hidden z-50 divide-y divide-white/5 max-h-96 overflow-y-auto"
                                >
                                    <div v-if="isSearching" class="p-4 text-center text-xs text-zinc-400 font-medium">
                                        Buscando tomos...
                                    </div>

                                    <template v-else-if="searchResults.length">
                                        <Link
                                            v-for="item in searchResults"
                                            :key="item.id"
                                            :href="route('catalogo.show', item.id)"
                                            @click="showSearchDropdown = false; navSearch = ''"
                                            class="flex items-center gap-3 p-3.5 hover:bg-white/5 transition-colors group"
                                        >
                                            <img
                                                :src="item.portada_url"
                                                :alt="item.titulo"
                                                @error="$event.target.src = '/images/no-cover.png'"
                                                class="w-10 h-14 object-cover rounded-xl bg-[#0d0d0f] border border-white/5 shrink-0"
                                            >
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-xs font-bold text-white group-hover:text-zinc-200 transition-colors truncate">
                                                    <span v-if="item.es_preventa" class="text-emerald-400 font-semibold">PREVENTA - </span>{{ item.titulo }}
                                                </h4>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    <p class="text-xs font-bold font-mono text-white">{{ item.precio }}</p>
                                                    <p v-if="item.es_preventa" class="text-xs font-medium font-mono text-zinc-500 line-through">{{ item.precio_original }}</p>
                                                </div>
                                            </div>
                                            <svg class="w-4 h-4 text-zinc-500 group-hover:text-white transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </Link>
                                    </template>

                                    <div v-else-if="navSearch.trim().length >= 2" class="p-4 text-center text-xs text-zinc-400 font-medium">
                                        No se encontraron tomos coincidentes.
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>

                <!-- Mobile Dropdown Menu -->
                <transition name="fade">
                    <div v-if="isMenuOpen" class="md:hidden bg-[#1A1A1A] border-b border-white/10 p-4 space-y-4 text-xs font-bold uppercase tracking-wider">
                        <Link :href="route('catalogo.index')" class="block py-1 text-white/70 hover:text-white">Inicio</Link>

                        <div class="space-y-1 pl-2 border-l border-white/10">
                            <div class="text-brand-red text-[10px] font-bold tracking-widest uppercase">Editoriales (Mangas)</div>
                            <button v-for="item in menuMangas" :key="item.nombre" @click="filterBySearch(item.search); isMenuOpen = false" class="block py-1 text-white/40 hover:text-white">
                                {{ item.nombre }}
                            </button>
                        </div>

                        <div class="space-y-1 pl-2 border-l border-white/10">
                            <div class="text-brand-red text-[10px] font-bold tracking-widest uppercase">Editoriales (Comics)</div>
                            <button v-for="item in menuComics" :key="item.nombre" @click="filterBySearch(item.search); isMenuOpen = false" class="block py-1 text-white/40 hover:text-white">
                                {{ item.nombre }}
                            </button>
                        </div>

                        <template v-if="user">
                            <Link :href="route('mi-cuenta.index')" class="block py-1 text-white/70 hover:text-white">Mi Cuenta</Link>
                            <button @click="logout" class="block py-1 text-white/40 hover:text-brand-red">Salir</button>
                        </template>
                        <template v-else>
                            <Link :href="route('register')" class="block py-1 text-white/70 hover:text-white">Crear Cuenta</Link>
                            <Link :href="route('login')" class="block py-1 text-white/70 hover:text-white">Iniciar Sesión</Link>
                        </template>
                    </div>
                </transition>
            </header>

            <!-- Terminal Sidebar Drawer (mobile/tablet only; desktop uses fixed sidebar above) -->
            <transition name="slide-left">
                <div v-if="isTerminalMenuOpen" class="xl:hidden fixed inset-y-0 left-0 z-[60] flex">
                    <AgentSidebar />
                    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm -z-10" @click="isTerminalMenuOpen = false"></div>
                    <button @click="isTerminalMenuOpen = false" class="absolute top-4 -right-12 text-white/60 hover:text-white p-2">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </transition>

            <!-- Page Content -->
            <main class="flex-1">
                <slot />
            </main>
        </div>

        <!-- Toast Notification -->
        <transition name="toast">
            <div
                v-if="toast"
                class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl text-sm font-black uppercase tracking-widest"
                :class="{
                    'bg-green-500/90 text-white':  toast.type === 'success',
                    'bg-yellow-500/90 text-black': toast.type === 'warning',
                    'bg-brand-red/90 text-white':  toast.type === 'error',
                }"
            >
                <span>{{ toast.msg }}</span>
            </div>
        </transition>
    </div>
</template>

<style>
html {
    overflow-y: scroll;
    scrollbar-gutter: stable;
}
</style>
