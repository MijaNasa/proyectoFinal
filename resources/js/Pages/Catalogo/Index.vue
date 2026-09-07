<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, reactive, watch, nextTick } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    libros: Object,
    preventas: Array,
    proveedoresFiltro: {
        type: Array,
        default: () => []
    },
    categoriasFiltro: {
        type: Array,
        default: () => []
    },
    precioRango: {
        type: Object,
        default: () => ({ min: 1000, max: 50000 })
    },
    categorias: Array,
    autores: Array,
    series: Array,
    proveedores: Array,
    idiomas: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const inputPrecioMin = ref(props.filters?.precio_min || '');
const inputPrecioMax = ref(props.filters?.precio_max || '');
const isFiltering = ref(false);
const mobileFiltersOpen = ref(false);
const catalogScrollRef = ref(null);

const scrollToTop = () => {
    nextTick(() => {
        if (catalogScrollRef.value) {
            catalogScrollRef.value.scrollTop = 0;
        }
        window.scrollTo({ top: 0 });
    });
};

const onPaginationClick = (url) => {
    if (!url) return;
    scrollToTop();
};

watch(() => props.libros?.current_page, (newPage, oldPage) => {
    if (newPage && newPage !== oldPage) {
        scrollToTop();
    }
});

const parseInitialArray = (val) => {
    if (Array.isArray(val)) return val.map(Number);
    if (typeof val === 'string' && val.trim() !== '') {
        return val.split(',').map(s => Number(s.trim())).filter(Boolean);
    }
    if (typeof val === 'number') return [val];
    return [];
};

const selected = reactive({
    categoria:  parseInitialArray(props.filters?.categoria),
    proveedor:  parseInitialArray(props.filters?.proveedor),
    autor:      props.filters?.autor      || null,
    idioma:     props.filters?.idioma     || null,
    tipo:       props.filters?.tipo       || null,
    preventa:   Boolean(props.filters?.preventa),
    solo_stock: Boolean(props.filters?.solo_stock),
    precio_min: props.filters?.precio_min || '',
    precio_max: props.filters?.precio_max || '',
    orden:      props.filters?.orden      || 'relevancia',
});

const applyFilters = () => {
    isFiltering.value = true;
    router.get(route('catalogo.index'), {
        search:     search.value?.trim() || undefined,
        proveedor:  selected.proveedor.length ? selected.proveedor.join(',') : undefined,
        categoria:  selected.categoria.length ? selected.categoria.join(',') : undefined,
        autor:      selected.autor      || undefined,
        idioma:     selected.idioma     || undefined,
        tipo:       selected.tipo       || undefined,
        preventa:   selected.preventa   ? true : undefined,
        solo_stock: selected.solo_stock ? true : undefined,
        precio_min: selected.precio_min !== '' ? selected.precio_min : undefined,
        precio_max: selected.precio_max !== '' ? selected.precio_max : undefined,
        orden:      selected.orden !== 'relevancia' ? selected.orden : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['libros', 'preventas', 'proveedoresFiltro', 'categoriasFiltro', 'filters'],
        onFinish: () => {
            isFiltering.value = false;
        },
    });
};

const toggleProveedor = (id) => {
    const numId = Number(id);
    const idx = selected.proveedor.indexOf(numId);
    if (idx > -1) {
        selected.proveedor.splice(idx, 1);
    } else {
        selected.proveedor.push(numId);
    }
    applyFilters();
};

const toggleCategoria = (id) => {
    const numId = Number(id);
    const idx = selected.categoria.indexOf(numId);
    if (idx > -1) {
        selected.categoria.splice(idx, 1);
    } else {
        selected.categoria.push(numId);
    }
    applyFilters();
};

const toggleSoloStock = () => {
    selected.solo_stock = !selected.solo_stock;
    applyFilters();
};

const togglePreventa = () => {
    selected.preventa = !selected.preventa;
    applyFilters();
};

const aplicarRangoPrecio = () => {
    selected.precio_min = inputPrecioMin.value ? String(inputPrecioMin.value) : '';
    selected.precio_max = inputPrecioMax.value ? String(inputPrecioMax.value) : '';
    applyFilters();
};

const cambiarOrden = (nuevoOrden) => {
    selected.orden = nuevoOrden;
    applyFilters();
};

const limpiarFiltros = () => {
    search.value = '';
    selected.proveedor = [];
    selected.categoria = [];
    selected.autor = null;
    selected.idioma = null;
    selected.tipo = null;
    selected.preventa = false;
    selected.solo_stock = false;
    selected.precio_min = '';
    selected.precio_max = '';
    selected.orden = 'relevancia';
    inputPrecioMin.value = '';
    inputPrecioMax.value = '';
    applyFilters();
};

const limpiarSearch = () => {
    search.value = '';
    applyFilters();
};

watch(() => props.filters, (newFilters) => {
    if (!newFilters) return;
    search.value        = newFilters.search || '';
    selected.categoria  = parseInitialArray(newFilters.categoria);
    selected.proveedor  = parseInitialArray(newFilters.proveedor);
    selected.autor      = newFilters.autor || null;
    selected.idioma     = newFilters.idioma || null;
    selected.tipo       = newFilters.tipo || null;
    selected.preventa   = Boolean(newFilters.preventa);
    selected.solo_stock = Boolean(newFilters.solo_stock);
    selected.precio_min = newFilters.precio_min || '';
    selected.precio_max = newFilters.precio_max || '';
    selected.orden      = newFilters.orden || 'relevancia';
    inputPrecioMin.value = newFilters.precio_min || '';
    inputPrecioMax.value = newFilters.precio_max || '';
}, { deep: true });

const hayFiltrosActivos = computed(() => {
    return Boolean(
        props.filters?.search ||
        selected.proveedor.length ||
        selected.categoria.length ||
        selected.precio_min ||
        selected.precio_max ||
        selected.solo_stock ||
        selected.preventa ||
        selected.tipo ||
        selected.autor
    );
});

const esLandingCatalogo = computed(() => {
    return !hayFiltrosActivos.value && (props.libros?.current_page ?? 1) === 1;
});

const totalFiltrosAplicados = computed(() => {
    let count = 0;
    if (selected.proveedor.length) count += selected.proveedor.length;
    if (selected.categoria.length) count += selected.categoria.length;
    if (selected.precio_min || selected.precio_max) count += 1;
    if (selected.solo_stock) count += 1;
    if (selected.preventa) count += 1;
    if (selected.tipo) count += 1;
    return count;
});

const getStockTotal = (libro) =>
    libro.stocks?.reduce((s, st) => s + (st.cantidad_disponible ?? 0), 0) ?? 0;

const getStockStatus = (libro) => {
    if (libro?.permite_preventa) return 'preventa';
    const total = getStockTotal(libro);
    if (total === 0) return 'sin_stock';
    if (total < 5)  return 'pocos';
    return 'disponible';
};

const stockLabel = { disponible: 'Disponible', pocos: 'Quedan pocos', sin_stock: 'Sin stock', preventa: 'Preventa' };
const stockClass = { disponible: 'text-emerald-400', pocos: 'text-amber-400', sin_stock: 'text-rose-400', preventa: 'text-cyan-400' };

const fmt = (v) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(v);

const getPrecio = (libro) => {
    if (libro.precio_actual) {
        let precio = libro.precio_actual.precio_venta;
        if (libro.permite_preventa) {
            precio = precio * 0.90;
        }
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(precio);
    }
    return 'Consultar';
};

const getPrecioOriginal = (libro) => {
    if (libro.precio_actual) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(libro.precio_actual.precio_venta);
    }
    return '';
};

const agregarAlCarrito = (libro) => {
    router.post(route('carrito.agregar'), {
        libro_id: libro.id,
        cantidad: 1,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

// Drag to scroll logic for Preventas carousel
const carouselRef = ref(null);
let isDown = false;
let startX;
let scrollLeft;

const onMouseDown = (e) => {
    isDown = true;
    carouselRef.value.classList.add('active');
    startX = e.pageX - carouselRef.value.offsetLeft;
    scrollLeft = carouselRef.value.scrollLeft;
};
const onMouseLeave = () => {
    isDown = false;
    carouselRef.value?.classList.remove('active');
};
const onMouseUp = () => {
    isDown = false;
    carouselRef.value?.classList.remove('active');
};
const onMouseMove = (e) => {
    if (!isDown) return;
    e.preventDefault();
    const x = e.pageX - carouselRef.value.offsetLeft;
    const walk = (x - startX) * 2;
    carouselRef.value.scrollLeft = scrollLeft - walk;
};
</script>

<template>
    <Head title="Catálogo de Libros - Puro Comic" />

    <PublicLayout>
        <div class="page-catalogo page-catalogo-split flex flex-col lg:flex-row lg:h-[calc(100vh-134px)] lg:overflow-hidden bg-[#0d0d0f]">

            <!-- ───────────────────────────────────────────────────────── -->
            <!-- PANEL LATERAL IZQUIERDO: FILTROS (Desktop)                -->
            <!-- ───────────────────────────────────────────────────────── -->
            <aside class="hidden lg:flex flex-col w-72 xl:w-80 shrink-0 h-full overflow-y-auto overscroll-contain filter-scrollbar bg-[#111114] border-r border-white/5 select-none">
                
                <!-- Encabezado Sticky del Sidebar -->
                <div class="sticky top-0 z-20 bg-[#111114]/95 backdrop-blur-md px-6 py-5 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-white flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span>Filtrar por</span>
                    </h3>
                    <button
                        v-if="hayFiltrosActivos"
                        type="button"
                        @click="limpiarFiltros"
                        class="text-[11px] text-zinc-400 hover:text-white font-semibold underline transition-colors cursor-pointer"
                    >
                        Limpiar todo
                    </button>
                </div>

                <!-- Contenedor scrollable con todos los filtros -->
                <div class="p-6 space-y-6">
                    <!-- 1. MARCA / EDITORIAL -->
                    <div v-if="proveedoresFiltro?.length > 0" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                                Marca / Editorial
                            </h4>
                            <span class="text-[10px] text-zinc-600 font-mono">{{ proveedoresFiltro.length }}</span>
                        </div>

                        <div class="space-y-1.5">
                            <label 
                                v-for="prov in proveedoresFiltro" 
                                :key="prov.id" 
                                class="flex items-center justify-between gap-2 text-xs text-zinc-300 hover:text-white cursor-pointer py-1 group transition-colors"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <input 
                                        type="checkbox" 
                                        :checked="selected.proveedor.includes(prov.id)"
                                        @change="toggleProveedor(prov.id)"
                                        class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600 focus:ring-0 focus:ring-offset-0 cursor-pointer transition-all"
                                    />
                                    <span class="truncate" :class="{ 'font-bold text-white': selected.proveedor.includes(prov.id) }">
                                        {{ prov.nombre }}
                                    </span>
                                </div>
                                <span class="text-[11px] text-zinc-500 font-mono shrink-0">({{ prov.count }})</span>
                            </label>
                        </div>
                    </div>

                    <!-- 2. RANGO DE PRECIO -->
                    <div class="space-y-3 pt-3 border-t border-white/5">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                            Precio
                        </h4>
                        <div class="flex items-end gap-2">
                            <div class="flex-1 min-w-0">
                                <label class="text-[10px] uppercase font-bold text-zinc-500 block mb-1">Desde</label>
                                <div class="relative">
                                    <span class="absolute left-2.5 top-2 text-xs text-zinc-500 font-mono">$</span>
                                    <input 
                                        v-model="inputPrecioMin" 
                                        type="number" 
                                        :placeholder="String(precioRango?.min || 1000)" 
                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl py-1.5 pl-6 pr-1.5 text-xs text-white placeholder-zinc-600 focus:outline-none focus:border-white/30 font-mono font-medium"
                                        @keyup.enter="aplicarRangoPrecio"
                                    />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="text-[10px] uppercase font-bold text-zinc-500 block mb-1">Hasta</label>
                                <div class="relative">
                                    <span class="absolute left-2.5 top-2 text-xs text-zinc-500 font-mono">$</span>
                                    <input 
                                        v-model="inputPrecioMax" 
                                        type="number" 
                                        :placeholder="String(precioRango?.max || 50000)" 
                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl py-1.5 pl-6 pr-1.5 text-xs text-white placeholder-zinc-600 focus:outline-none focus:border-white/30 font-mono font-medium"
                                        @keyup.enter="aplicarRangoPrecio"
                                    />
                                </div>
                            </div>
                            <button 
                                type="button" 
                                @click="aplicarRangoPrecio" 
                                class="p-2.5 bg-white hover:bg-zinc-200 text-black rounded-xl transition-all font-bold text-xs shadow-md active:scale-95 cursor-pointer shrink-0"
                                title="Aplicar rango de precio"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- 3. CATEGORÍAS -->
                    <div v-if="categoriasFiltro?.length > 0" class="space-y-3 pt-3 border-t border-white/5">
                        <div class="flex items-center justify-between">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                                Categoría
                            </h4>
                            <span class="text-[10px] text-zinc-600 font-mono">{{ categoriasFiltro.length }}</span>
                        </div>

                        <div class="space-y-1.5">
                            <label 
                                v-for="cat in categoriasFiltro" 
                                :key="cat.id" 
                                class="flex items-center justify-between gap-2 text-xs text-zinc-300 hover:text-white cursor-pointer py-1 group transition-colors"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <input 
                                        type="checkbox" 
                                        :checked="selected.categoria.includes(cat.id)"
                                        @change="toggleCategoria(cat.id)"
                                        class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600 focus:ring-0 focus:ring-offset-0 cursor-pointer transition-all"
                                    />
                                    <span class="truncate" :class="{ 'font-bold text-white': selected.categoria.includes(cat.id) }">
                                        {{ cat.nombre }}
                                    </span>
                                </div>
                                <span class="text-[11px] text-zinc-500 font-mono shrink-0">({{ cat.count }})</span>
                            </label>
                        </div>
                    </div>

                    <!-- 4. DISPONIBILIDAD -->
                    <div class="space-y-2.5 pt-3 border-t border-white/5">
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-zinc-400">
                            Disponibilidad
                        </h4>
                        <div class="space-y-2 select-none">
                            <label class="flex items-center gap-2.5 text-xs text-zinc-300 hover:text-white cursor-pointer group">
                                <input 
                                    type="checkbox" 
                                    :checked="selected.solo_stock"
                                    @change="toggleSoloStock"
                                    class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600 focus:ring-0 focus:ring-offset-0 cursor-pointer"
                                />
                                <span :class="{ 'font-bold text-white': selected.solo_stock }">Solo en stock</span>
                            </label>
                            <label class="flex items-center gap-2.5 text-xs text-zinc-300 hover:text-white cursor-pointer group">
                                <input 
                                    type="checkbox" 
                                    :checked="selected.preventa"
                                    @change="togglePreventa"
                                    class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600 focus:ring-0 focus:ring-offset-0 cursor-pointer"
                                />
                                <span :class="{ 'font-bold text-white': selected.preventa }">Preventas activas</span>
                            </label>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ───────────────────────────────────────────────────────── -->
            <!-- PANEL DERECHO: CATÁLOGO (Desktop scroll independiente)    -->
            <!-- ───────────────────────────────────────────────────────── -->
            <div ref="catalogScrollRef" class="flex-1 min-w-0 h-full overflow-y-auto overscroll-contain custom-scrollbar flex flex-col justify-between">
                <div>
                    <!-- Hero Header con Barra de Búsqueda -->
                    <div class="relative overflow-hidden py-10 sm:py-14 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
                            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight uppercase leading-none text-white">
                                Tu Próximo <span class="text-zinc-400 italic">Capítulo</span> Inicia Aquí
                            </h1>
                            <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-xl mx-auto">
                                Explorá nuestro catálogo oficial de mangas, cómics importados y ediciones exclusivas.
                            </p>
                            
                            <!-- Search Bar -->
                            <div class="max-w-2xl mx-auto pt-1">
                                <form @submit.prevent="applyFilters" class="relative flex items-center">
                                    <span class="absolute left-5 text-zinc-500 pointer-events-none">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </span>
                                    <input
                                        v-model="search"
                                        type="text"
                                        placeholder="¿Qué manga o cómic estás buscando? (ej. Demon Slayer, Batman, Ivrea...)"
                                        class="w-full bg-[#131316] border border-white/10 rounded-2xl py-3.5 pl-14 pr-32 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 shadow-2xl transition-all font-medium"
                                    >
                                    <div class="absolute right-2 flex items-center gap-1">
                                        <button
                                            v-if="search"
                                            type="button"
                                            @click="limpiarSearch"
                                            class="p-2 text-zinc-400 hover:text-white rounded-xl hover:bg-white/5 transition-all cursor-pointer"
                                            title="Borrar búsqueda"
                                        >
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                        <button
                                            type="submit"
                                            class="px-5 py-2 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 cursor-pointer"
                                        >
                                            BUSCAR
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Contenedor del Catálogo y Grilla -->
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                        
                        <!-- Preventas Activas (Carrusel superior) -->
                        <div v-if="preventas?.length > 0" class="space-y-4">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                    <span>🔥</span>
                                    <span>Preventas Activas con 10% OFF</span>
                                </span>
                                <div class="h-px flex-1 bg-white/5"></div>
                            </div>

                            <div 
                                ref="carouselRef"
                                class="flex gap-4 overflow-x-auto pb-3 snap-x cursor-grab active:cursor-grabbing select-none scrollbar-hide items-stretch transition-opacity duration-200"
                                :class="{ 'opacity-50 pointer-events-none': isFiltering }"
                                @mousedown="onMouseDown"
                                @mouseleave="onMouseLeave"
                                @mouseup="onMouseUp"
                                @mousemove="onMouseMove"
                                style="scrollbar-width: none; -ms-overflow-style: none;"
                            >
                                <div
                                    v-for="libro in preventas"
                                    :key="'prev-'+libro.id"
                                    class="group flex-shrink-0 w-44 md:w-52 snap-start flex flex-col justify-between h-full bg-[#131316] hover:bg-[#18181c] border border-white/5 hover:border-white/10 rounded-2xl overflow-hidden shadow-xl transition-all duration-300"
                                    :class="{ 'opacity-50': getStockStatus(libro) === 'sin_stock' }"
                                >
                                    <div class="flex flex-col flex-1">
                                        <Link
                                            :href="getStockStatus(libro) === 'sin_stock' ? undefined : route('catalogo.show', libro.id)"
                                            class="block relative aspect-[2/3] overflow-hidden bg-[#0d0d0f] border-b border-white/5"
                                            :class="{ 'cursor-not-allowed': getStockStatus(libro) === 'sin_stock' }"
                                        >
                                            <img
                                                :src="libro.portada_url"
                                                :alt="libro.titulo"
                                                @error="$event.target.src = '/images/no-cover.png'"
                                                class="w-full h-full object-cover transition-transform duration-500 pointer-events-none group-hover:scale-105"
                                                draggable="false"
                                            >
                                            <div class="absolute top-2 left-2 z-10">
                                                <span class="px-2 py-0.5 bg-red-600/90 text-white font-black text-[10px] uppercase tracking-wider rounded-md shadow-md">
                                                    PREVENTA
                                                </span>
                                            </div>
                                            <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/80 flex items-center justify-center">
                                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 bg-zinc-900/90 px-2.5 py-1 rounded-xl border border-white/10">Sin Stock</span>
                                            </div>
                                        </Link>
                                        <div class="p-3 flex flex-col flex-1 justify-between space-y-2 text-left">
                                            <h3 class="font-bold text-xs leading-snug text-white transition-colors line-clamp-2 h-9 flex items-center" :title="`${libro.master?.titulo} ${libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : ''}`">
                                                {{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}
                                            </h3>
                                            <div class="pt-1 flex items-baseline gap-2">
                                                <span class="text-sm font-bold text-white font-mono">{{ getPrecio(libro) }}</span>
                                                <span class="text-xs text-zinc-500 font-mono line-through">{{ getPrecioOriginal(libro) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-auto border-t border-white/5 grid grid-cols-2 divide-x divide-white/5 bg-[#0d0d0f] text-center">
                                        <Link
                                            :href="route('catalogo.show', libro.id)"
                                            class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-xs font-semibold text-zinc-400 hover:text-white transition-colors"
                                        >
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Detalles</span>
                                        </Link>

                                        <button
                                            v-if="getStockStatus(libro) !== 'sin_stock'"
                                            @click.stop.prevent="agregarAlCarrito(libro)"
                                            class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-xs font-bold text-black bg-white hover:bg-zinc-200 transition-colors cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z" />
                                            </svg>
                                            <span>Comprar</span>
                                        </button>
                                        <div v-else class="flex items-center justify-center py-2.5 px-1.5 text-xs font-semibold text-zinc-600 cursor-not-allowed">
                                            Sin Stock
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══════════════════════════════════════════════════════════════ -->
                        <!-- HEADER DE RESULTADOS                                            -->
                        <!-- ═══════════════════════════════════════════════════════════════ -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-white/5">
                            <!-- Título -->
                            <div>
                                <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight leading-snug">
                                    Mostrando <span class="font-extrabold text-white">{{ libros.total }}</span> {{ libros.total === 1 ? 'producto' : 'productos' }} en Catálogo
                                </h2>
                            </div>

                            <!-- Botón filtros móvil & Selector de Orden -->
                            <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto">
                                <!-- Toggle Filtros en Móvil -->
                                <button
                                    type="button"
                                    @click="mobileFiltersOpen = true"
                                    class="lg:hidden px-3.5 py-2 bg-[#131316] border border-white/10 rounded-xl text-xs font-bold text-white flex items-center gap-2 shadow-sm cursor-pointer"
                                >
                                    <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    <span>Filtros</span>
                                </button>

                                <!-- Selector Ordenar Por -->
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-zinc-400 whitespace-nowrap hidden md:inline">Ordenar por:</span>
                                    <select 
                                        v-model="selected.orden" 
                                        @change="cambiarOrden($event.target.value)"
                                        class="bg-[#131316] border border-white/10 rounded-xl px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer shadow-md"
                                    >
                                        <option value="relevancia" class="bg-[#131316]">Relevancia</option>
                                        <option value="precio_asc" class="bg-[#131316]">Menor precio</option>
                                        <option value="precio_desc" class="bg-[#131316]">Mayor precio</option>
                                        <option value="recientes" class="bg-[#131316]">Más recientes</option>
                                        <option value="nombre_asc" class="bg-[#131316]">Nombre (A - Z)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Barra de carga reactiva / sutil en transiciones -->
                        <div class="h-0.5 w-full overflow-hidden rounded-full bg-white/5 transition-opacity duration-300" :class="{ 'opacity-100': isFiltering, 'opacity-0': !isFiltering }">
                            <div class="h-full bg-gradient-to-r from-red-600 via-rose-400 to-red-600 w-full animate-pulse"></div>
                        </div>

                        <!-- Grilla Principal de Libros -->
                        <div 
                            v-if="libros.data.length > 0" 
                            class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5 items-stretch transition-opacity duration-200"
                            :class="{ 'opacity-50 pointer-events-none': isFiltering }"
                        >
                            <div
                                v-for="libro in libros.data"
                                :key="libro.id"
                                class="group flex flex-col justify-between h-full bg-[#131316] hover:bg-[#18181c] border border-white/5 hover:border-white/10 rounded-2xl overflow-hidden shadow-xl transition-all duration-300"
                                :class="{ 'opacity-50': getStockStatus(libro) === 'sin_stock' }"
                            >
                                <div class="flex flex-col flex-1">
                                    <Link
                                        :href="getStockStatus(libro) === 'sin_stock' ? undefined : route('catalogo.show', libro.id)"
                                        class="block relative aspect-[2/3] overflow-hidden bg-[#0d0d0f] border-b border-white/5"
                                        :class="{ 'cursor-not-allowed': getStockStatus(libro) === 'sin_stock' }"
                                    >
                                        <img
                                            :src="libro.portada_url"
                                            :alt="libro.titulo"
                                            @error="$event.target.src = '/images/no-cover.png'"
                                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                            :class="{ 'grayscale': getStockStatus(libro) === 'sin_stock' }"
                                        >

                                        <!-- Badge Preventa -->
                                        <div v-if="libro.permite_preventa" class="absolute top-2 left-2 z-10">
                                            <span class="px-2 py-0.5 bg-red-600/90 text-white font-black text-[10px] uppercase tracking-wider rounded-md shadow-md">
                                                PREVENTA
                                            </span>
                                        </div>

                                        <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/80 flex items-center justify-center">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 bg-zinc-900/90 px-2.5 py-1 rounded-xl border border-white/10">Sin Stock</span>
                                        </div>
                                    </Link>

                                    <div class="p-3.5 flex flex-col flex-1 justify-between space-y-2 text-left">
                                        <div>
                                            <h3
                                                class="font-bold text-xs leading-snug text-white transition-colors line-clamp-2 h-9 flex items-center"
                                                :class="{ 'text-zinc-500': getStockStatus(libro) === 'sin_stock' }"
                                                :title="`${libro.master?.titulo} ${libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : ''}`"
                                            >
                                                {{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}
                                            </h3>
                                        </div>
                                        <div class="pt-1 flex items-baseline justify-between">
                                            <div class="flex flex-col">
                                                <span v-if="libro.permite_preventa" class="text-xs text-zinc-500 font-mono line-through leading-none">{{ getPrecioOriginal(libro) }}</span>
                                                <span class="text-sm font-bold text-white font-mono">{{ getPrecio(libro) }}</span>
                                            </div>
                                            <span v-if="getStockStatus(libro) !== 'disponible'" :class="['text-[10px] font-semibold uppercase tracking-wider', stockClass[getStockStatus(libro)]]">{{ stockLabel[getStockStatus(libro)] }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto border-t border-white/5 grid grid-cols-2 divide-x divide-white/5 bg-[#0d0d0f] text-center">
                                    <Link
                                        :href="route('catalogo.show', libro.id)"
                                        class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-xs font-semibold text-zinc-400 hover:text-white transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Detalles</span>
                                    </Link>

                                    <button
                                        v-if="getStockStatus(libro) !== 'sin_stock'"
                                        @click.stop.prevent="agregarAlCarrito(libro)"
                                        class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-xs font-bold text-black bg-white hover:bg-zinc-200 transition-colors cursor-pointer"
                                    >
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z" />
                                        </svg>
                                        <span>Comprar</span>
                                    </button>
                                    <div v-else class="flex items-center justify-center py-2.5 px-1.5 text-xs font-semibold text-zinc-600 cursor-not-allowed">
                                        Sin Stock
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estado Sin Resultados -->
                        <div v-else class="py-20 text-center max-w-md mx-auto bg-[#131316] border border-white/5 rounded-2xl p-8 shadow-xl">
                            <div class="w-16 h-16 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center mx-auto mb-4 text-emerald-400">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold uppercase text-white">
                                {{ selected.preventa ? 'No hay preventas activas' : 'Sin resultados' }}
                            </h3>
                            <p class="text-xs text-zinc-400 mt-2 font-medium leading-relaxed">
                                No encontramos tomos que coincidan con los filtros seleccionados. Intentá seleccionando otra editorial o restableciendo el rango de precio.
                            </p>
                            <button @click="limpiarFiltros" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-black font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-zinc-200 transition-colors shadow-lg active:scale-95 cursor-pointer">
                                Ver todo el catálogo
                            </button>
                        </div>

                        <!-- Paginación -->
                        <div v-if="libros.links?.length > 3" class="mt-10 flex justify-center gap-2 flex-wrap">
                            <Link
                                v-for="link in libros.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :preserve-state="true"
                                :only="['libros', 'preventas', 'proveedoresFiltro', 'categoriasFiltro', 'filters']"
                                @click="onPaginationClick(link.url)"
                                class="px-4 py-2 rounded-xl border border-white/5 text-xs font-semibold transition-all cursor-pointer"
                                :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                            >{{ decodeLabel(link.label) }}</Link>
                        </div>
                    </div>
                </div>

                <!-- Features Footer (Solo visible en la landing inicial del catálogo, se oculta al filtrar o entrar en pestañas) -->
                <div v-if="esLandingCatalogo" class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pb-12 pt-4">
                    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-8 border-t border-white/5">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">01. Envíos</span>
                            <h3 class="text-xl font-bold uppercase text-white">Rápidos</h3>
                            <p class="text-xs text-zinc-300 font-medium leading-relaxed">
                                Despachamos tus pedidos en 24hs hábiles a todo el país.
                            </p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">02. Seguridad</span>
                            <h3 class="text-xl font-bold uppercase text-white">Compra Segura</h3>
                            <p class="text-xs text-zinc-300 font-medium leading-relaxed">
                                Tus datos están protegidos. Múltiples medios de pago disponibles.
                            </p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">03. Locales</span>
                            <h3 class="text-xl font-bold uppercase text-white">Sucursales</h3>
                            <p class="text-xs text-zinc-300 font-medium leading-relaxed">
                                Buscá tu pedido gratis por nuestros locales de Rosario y Funes.
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- DRAWER DE FILTROS PARA MÓVILES                                  -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="mobileFiltersOpen" class="fixed inset-0 z-[9999] flex">
                <!-- Backdrop -->
                <div @click="mobileFiltersOpen = false" class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"></div>

                <!-- Slide Panel -->
                <div class="relative ml-auto w-full max-w-xs bg-[#131316] border-l border-white/10 h-full p-6 overflow-y-auto space-y-6 shadow-2xl flex flex-col justify-between">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between pb-4 border-b border-white/5">
                            <h3 class="text-sm font-extrabold uppercase tracking-wider text-white">Filtros</h3>
                            <button @click="mobileFiltersOpen = false" class="p-2 text-zinc-400 hover:text-white rounded-xl hover:bg-white/5 transition-all">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Marca / Editorial -->
                        <div v-if="proveedoresFiltro?.length > 0" class="space-y-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Marca / Editorial</h4>
                            <div class="space-y-2 max-h-48 overflow-y-auto filter-scrollbar">
                                <label 
                                    v-for="prov in proveedoresFiltro" 
                                    :key="'m-'+prov.id" 
                                    class="flex items-center justify-between gap-2 text-xs text-zinc-300 cursor-pointer"
                                >
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="checkbox" 
                                            :checked="selected.proveedor.includes(prov.id)"
                                            @change="toggleProveedor(prov.id)"
                                            class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600 focus:ring-0"
                                        />
                                        <span class="truncate" :class="{ 'font-bold text-white': selected.proveedor.includes(prov.id) }">{{ prov.nombre }}</span>
                                    </div>
                                    <span class="text-[11px] text-zinc-500 font-mono">({{ prov.count }})</span>
                                </label>
                            </div>
                        </div>

                        <!-- Rango de Precio -->
                        <div class="space-y-3 pt-3 border-t border-white/5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Precio</h4>
                            <div class="flex items-center gap-2">
                                <input 
                                    v-model="inputPrecioMin" 
                                    type="number" 
                                    placeholder="Desde" 
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl py-2 px-3 text-xs text-white placeholder-zinc-500"
                                />
                                <input 
                                    v-model="inputPrecioMax" 
                                    type="number" 
                                    placeholder="Hasta" 
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl py-2 px-3 text-xs text-white placeholder-zinc-500"
                                />
                            </div>
                            <button 
                                @click="aplicarRangoPrecio" 
                                class="w-full py-2 bg-white text-black font-bold text-xs uppercase tracking-wider rounded-xl cursor-pointer"
                            >
                                Aplicar Precio
                            </button>
                        </div>

                        <!-- Categorías -->
                        <div v-if="categoriasFiltro?.length > 0" class="space-y-3 pt-3 border-t border-white/5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Categoría</h4>
                            <div class="space-y-2 max-h-48 overflow-y-auto filter-scrollbar">
                                <label 
                                    v-for="cat in categoriasFiltro" 
                                    :key="'m-c-'+cat.id" 
                                    class="flex items-center justify-between gap-2 text-xs text-zinc-300 cursor-pointer"
                                >
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="checkbox" 
                                            :checked="selected.categoria.includes(cat.id)"
                                            @change="toggleCategoria(cat.id)"
                                            class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600 focus:ring-0"
                                        />
                                        <span class="truncate" :class="{ 'font-bold text-white': selected.categoria.includes(cat.id) }">{{ cat.nombre }}</span>
                                    </div>
                                    <span class="text-[11px] text-zinc-500 font-mono">({{ cat.count }})</span>
                                </label>
                            </div>
                        </div>

                        <!-- Disponibilidad -->
                        <div class="space-y-2 pt-3 border-t border-white/5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Disponibilidad</h4>
                            <label class="flex items-center gap-2 text-xs text-zinc-300">
                                <input type="checkbox" :checked="selected.solo_stock" @change="toggleSoloStock" class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600" />
                                <span>Solo en stock</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-zinc-300">
                                <input type="checkbox" :checked="selected.preventa" @change="togglePreventa" class="w-4 h-4 rounded bg-[#0d0d0f] border border-white/20 text-red-600" />
                                <span>Preventas</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-white/5 space-y-2">
                        <button 
                            @click="mobileFiltersOpen = false" 
                            class="w-full py-3 bg-white text-black font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg"
                        >
                            Ver {{ libros.total }} resultados
                        </button>
                        <button 
                            @click="limpiarFiltros(); mobileFiltersOpen = false;" 
                            class="w-full py-2.5 bg-white/5 text-zinc-400 font-semibold text-xs uppercase tracking-wider rounded-xl hover:text-white"
                        >
                            Limpiar todo
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-catalogo,
.page-catalogo * {
    font-family: 'Montserrat', sans-serif !important;
}

/* Bloqueo de scroll exterior en desktop para permitir que solo los paneles internos tengan scroll independiente */
@media (min-width: 1024px) {
    html:has(.page-catalogo-split),
    body:has(.page-catalogo-split) {
        overflow: hidden !important;
        height: 100vh !important;
    }
}

.filter-scrollbar,
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}

.filter-scrollbar::-webkit-scrollbar,
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.filter-scrollbar::-webkit-scrollbar-track,
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.filter-scrollbar::-webkit-scrollbar-thumb,
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.12);
    border-radius: 4px;
}

.filter-scrollbar::-webkit-scrollbar-thumb:hover,
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.28);
}
</style>
