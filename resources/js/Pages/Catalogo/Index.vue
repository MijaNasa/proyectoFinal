<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, reactive } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    libros: Object,
    preventas: Array,
    categorias: Array,
    autores: Array,
    series: Array,
    proveedores: Array,
    idiomas: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const selected = reactive({
    categoria:  props.filters?.categoria  || null,
    autor:      props.filters?.autor      || null,
    serie:      props.filters?.serie      || null,
    proveedor:  props.filters?.proveedor  || null,
    idioma:     props.filters?.idioma     || null,
});

const applyFilters = () => {
    router.get(route('catalogo.index'), {
        search:    search.value || undefined,
        categoria: selected.categoria || undefined,
        autor:     selected.autor     || undefined,
        serie:     selected.serie     || undefined,
        proveedor: selected.proveedor || undefined,
        idioma:    selected.idioma    || undefined,
    }, { preserveState: false });
};

const toggle = (key, id) => {
    selected[key] = selected[key] === id ? null : id;
    applyFilters();
};

const limpiarFiltros = () => {
    search.value = '';
    Object.keys(selected).forEach(k => selected[k] = null);
    applyFilters();
};

const activeChips = computed(() => {
    const chips = [];
    if (selected.categoria) {
        const item = props.categorias.find(x => x.id === selected.categoria);
        if (item) chips.push({ label: item.nombre, key: 'categoria' });
    }
    if (selected.autor) {
        const item = props.autores.find(x => x.id === selected.autor);
        if (item) chips.push({ label: `${item.apellido}, ${item.nombre}`, key: 'autor' });
    }
    if (selected.serie) {
        const item = props.series.find(x => x.id === selected.serie);
        if (item) chips.push({ label: item.nombre, key: 'serie' });
    }
    if (selected.proveedor) {
        const item = props.proveedores.find(x => x.id === selected.proveedor);
        if (item) chips.push({ label: item.nombre_empresa, key: 'proveedor' });
    }
    if (selected.idioma) {
        const item = props.idiomas.find(x => x.id === selected.idioma);
        if (item) chips.push({ label: item.nombre, key: 'idioma' });
    }
    return chips;
});

const removeChip = (chip) => {
    selected[chip.key] = null;
    applyFilters();
};

const hayFiltrosActivos = computed(() => search.value || activeChips.value.length);

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

const fmt = (v) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

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
    carouselRef.value.classList.remove('active');
};
const onMouseUp = () => {
    isDown = false;
    carouselRef.value.classList.remove('active');
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
    <Head title="Catálogo de Libros" />

    <PublicLayout>
        <div class="page-catalogo">
            <!-- Hero -->
            <div v-if="!hayFiltrosActivos" class="relative overflow-hidden py-16 sm:py-20 bg-gradient-to-b from-white/[0.04] to-transparent">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
                    <h1 class="text-5xl md:text-7xl font-bold tracking-tight uppercase leading-none text-white">
                        Tu Próximo <br><span class="text-zinc-400 italic">Capítulo</span> Inicia Aquí
                    </h1>
                    <p class="text-zinc-400 text-sm font-medium max-w-xl mx-auto">Explora nuestro catálogo completo de manga, cómics y ediciones especiales.</p>
                    
                    <!-- Prominent Hero Search Bar -->
                    <div class="max-w-2xl mx-auto pt-2">
                        <form @submit.prevent="applyFilters" class="relative flex items-center">
                            <span class="absolute left-5 text-zinc-500 pointer-events-none">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="¿Qué manga o cómic estás buscando hoy? (título, autor, editorial...)"
                                class="w-full bg-[#131316] border border-white/10 rounded-2xl py-4 pl-14 pr-32 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 shadow-2xl transition-all font-medium"
                            >
                            <button
                                type="submit"
                                class="absolute right-2 px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95"
                            >
                                BUSCAR
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                
                <!-- Preventas Activas -->
                <div v-if="preventas?.length > 0" class="space-y-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Preventas Activas</h2>
                        <div class="h-px flex-1 bg-white/5"></div>
                    </div>

                    <div 
                        ref="carouselRef"
                        class="flex gap-5 overflow-x-auto pb-4 snap-x cursor-grab active:cursor-grabbing select-none scrollbar-hide items-stretch"
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
                                    <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/80 flex items-center justify-center">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 bg-zinc-900/90 px-2.5 py-1 rounded-xl border border-white/10">Sin Stock</span>
                                    </div>
                                </Link>
                                <div class="p-3 flex flex-col flex-1 justify-between space-y-2 text-left">
                                    <div>
                                        <h3 class="font-bold text-xs leading-snug text-white transition-colors line-clamp-2 h-9 flex items-center" :title="`${libro.master?.titulo} ${libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : ''}`">
                                            {{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}
                                        </h3>
                                    </div>
                                    <div class="pt-1 flex items-baseline gap-2">
                                        <span class="text-sm font-bold text-white font-mono">{{ getPrecio(libro) }}</span>
                                        <span v-if="libro.permite_preventa" class="text-xs text-zinc-500 font-mono line-through">{{ getPrecioOriginal(libro) }}</span>
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
                                    class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-xs font-bold text-black bg-white hover:bg-zinc-200 transition-colors"
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

                <div v-if="preventas?.length > 0" class="flex items-center gap-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-white">Stock Disponible</h2>
                    <div class="h-px flex-1 bg-white/5"></div>
                </div>

                <!-- Chips activos -->
                <div v-if="activeChips.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="chip in activeChips"
                        :key="chip.key"
                        @click="removeChip(chip)"
                        class="flex items-center gap-2 px-3 py-1.5 bg-white/5 border border-white/10 rounded-xl text-xs font-semibold text-white hover:bg-white/10 transition-all group"
                    >
                        <span>{{ chip.label }}</span>
                        <svg class="w-3 h-3 text-zinc-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Grid principal de catálogo -->
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-5 items-stretch">
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
                                class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-xs font-bold text-black bg-white hover:bg-zinc-200 transition-colors"
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

                <!-- Sin resultados -->
                <div v-if="libros.data.length === 0" class="py-24 text-center">
                    <svg class="h-16 w-16 mx-auto text-zinc-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="text-xl font-bold uppercase text-zinc-400">Sin resultados</h3>
                    <button @click="limpiarFiltros" class="mt-4 text-xs font-semibold uppercase tracking-wider text-zinc-300 hover:text-white transition-colors">Limpiar filtros</button>
                </div>

                <!-- Paginación -->
                <div v-if="libros.links?.length > 3" class="mt-10 flex justify-center gap-2 flex-wrap">
                    <Link
                        v-for="link in libros.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-4 py-2 rounded-xl border border-white/5 text-xs font-semibold transition-all"
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                    >{{ decodeLabel(link.label) }}</Link>
                </div>

                <!-- Features -->
                <section class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-12 mt-12 border-t border-white/5">
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
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-catalogo,
.page-catalogo * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
