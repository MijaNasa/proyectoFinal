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

const open = reactive({
    categorias:  false,
    autores:     false,
    series:      false,
    proveedores: false,
    idiomas:     false,
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
    const total = getStockTotal(libro);
    if (total === 0) return 'sin_stock';
    if (total < 5)  return 'pocos';
    return 'disponible';
};
const stockLabel = { disponible: 'Disponible', pocos: 'Quedan pocos', sin_stock: 'Sin stock' };
const stockClass = { disponible: 'text-green-400', pocos: 'text-yellow-400', sin_stock: 'text-red-400' };

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

const getIdiomas = (libro) => [libro.master?.idioma?.nombre].filter(Boolean);

const tieneVariasEdiciones = (libro) => false;

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
    const walk = (x - startX) * 2; // scroll-fast multiplier
    carouselRef.value.scrollLeft = scrollLeft - walk;
};
</script>

<template>
    <Head title="Catálogo de Libros" />

    <PublicLayout>
        <!-- Hero -->
        <div class="relative overflow-hidden py-24 bg-gradient-to-b from-brand-red/10 to-transparent">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-6xl md:text-8xl font-black uppercase tracking-tighter leading-none mb-6">
                    Tu Próximo <br><span class="text-brand-red italic">Capítulo</span> Inicia Aquí
                </h1>
                <p class="text-white/40 text-lg md:text-xl max-w-2xl mx-auto font-medium">
                    Explora nuestra colección curada de títulos maestros.<br>Literatura excepcional, disponible ahora.
                </p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="flex flex-col lg:flex-row gap-12">

                <!-- Sidebar -->
                <aside class="w-full lg:w-52 flex-shrink-0">

                    <!-- Búsqueda -->
                    <div class="mb-6">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-4 underline decoration-2 underline-offset-4">Búsqueda</h3>
                        <input
                            v-model="search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="BUSCAR..."
                            class="w-full bg-white/5 border border-white/10 rounded-lg py-3 px-4 text-sm text-white focus:border-brand-red outline-none transition-all placeholder:text-white/40 font-bold uppercase"
                        >
                    </div>

                    <!-- Categoría -->
                    <div v-if="categorias.length" class="border-t border-white/5">
                        <button @click="open.categorias = !open.categorias" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.categorias || selected.categoria ? 'text-white' : 'text-white/50'">
                            <span>Categoría <span v-if="selected.categoria" class="text-brand-red">(1)</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.categorias ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.categorias" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in categorias" :key="item.id" @click="toggle('categoria', item.id)" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.categoria === item.id ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.categoria === item.id" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors">{{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Autor -->
                    <div v-if="autores.length" class="border-t border-white/5">
                        <button @click="open.autores = !open.autores" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.autores || selected.autor ? 'text-white' : 'text-white/50'">
                            <span>Autor <span v-if="selected.autor" class="text-brand-red">(1)</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.autores ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.autores" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in autores" :key="item.id" @click="toggle('autor', item.id)" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.autor === item.id ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.autor === item.id" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors line-clamp-1">{{ item.apellido }}, {{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Serie -->
                    <div v-if="series.length" class="border-t border-white/5">
                        <button @click="open.series = !open.series" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.series || selected.serie ? 'text-white' : 'text-white/50'">
                            <span>Serie <span v-if="selected.serie" class="text-brand-red">(1)</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.series ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.series" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in series" :key="item.id" @click="toggle('serie', item.id)" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.serie === item.id ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.serie === item.id" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors line-clamp-1">{{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Proveedor -->
                    <div v-if="proveedores.length" class="border-t border-white/5">
                        <button @click="open.proveedores = !open.proveedores" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.proveedores || selected.proveedor ? 'text-white' : 'text-white/50'">
                            <span>Proveedor <span v-if="selected.proveedor" class="text-brand-red">(1)</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.proveedores ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.proveedores" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in proveedores" :key="item.id" @click="toggle('proveedor', item.id)" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.proveedor === item.id ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.proveedor === item.id" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors line-clamp-1">{{ item.nombre_empresa }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Idioma -->
                    <div v-if="idiomas.length" class="border-t border-white/5">
                        <button @click="open.idiomas = !open.idiomas" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.idiomas || selected.idioma ? 'text-white' : 'text-white/50'">
                            <span>Idioma <span v-if="selected.idioma" class="text-brand-red">(1)</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.idiomas ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.idiomas" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in idiomas" :key="item.id" @click="toggle('idioma', item.id)" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.idioma === item.id ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.idioma === item.id" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors">{{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-white/5 pt-4">
                        <button v-if="hayFiltrosActivos" @click="limpiarFiltros" class="w-full py-3 text-[10px] font-black uppercase tracking-widest text-brand-red/60 hover:text-brand-red transition-colors border border-brand-red/20 rounded-lg hover:border-brand-red/40">
                            Limpiar todo
                        </button>
                    </div>
                </aside>

                <!-- Contenido -->
                <div class="flex-1 min-w-0 overflow-hidden">

                    <!-- Preventas Activas -->
                    <div v-if="preventas?.length > 0" class="mb-12">
                        <div class="flex items-center gap-3 mb-6">
                            <h2 class="text-xl font-black uppercase tracking-[0.2em] text-transparent bg-clip-text bg-gradient-to-r from-brand-red to-orange-500">Preventas Activas</h2>
                            <div class="h-px flex-1 bg-gradient-to-r from-brand-red/30 to-transparent"></div>
                        </div>

                        <div 
                            ref="carouselRef"
                            class="flex gap-5 overflow-x-auto pb-4 snap-x cursor-grab active:cursor-grabbing select-none scrollbar-hide"
                            @mousedown="onMouseDown"
                            @mouseleave="onMouseLeave"
                            @mouseup="onMouseUp"
                            @mousemove="onMouseMove"
                            style="scrollbar-width: none; -ms-overflow-style: none;"
                        >
                            <component
                                :is="getStockStatus(libro) === 'sin_stock' ? 'div' : Link"
                                v-for="libro in preventas"
                                :key="'prev-'+libro.id"
                                :href="getStockStatus(libro) === 'sin_stock' ? undefined : route('catalogo.show', libro.id)"
                                class="group flex-shrink-0 w-40 md:w-48 snap-start"
                                :class="{ 'cursor-not-allowed': getStockStatus(libro) === 'sin_stock' }"
                            >
                                <div class="relative aspect-[2/3] overflow-hidden rounded-xl bg-black border-2 border-brand-red/50 shadow-[0_0_15px_rgba(230,25,25,0.2)] transition-all duration-500 group-hover:-translate-y-2 group-hover:shadow-[0_15px_35px_rgba(230,25,25,0.4)] group-hover:border-brand-red">
                                    <div class="absolute top-2 left-2 z-10 bg-brand-red text-white text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded shadow-lg">Preventa</div>
                                    <img
                                        :src="libro.portada_url"
                                        :alt="libro.titulo"
                                        class="w-full h-full object-cover transition-all duration-700 pointer-events-none group-hover:scale-110"
                                        draggable="false"
                                    >
                                    <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-white/50 bg-black/80 px-2 py-1 rounded">Sin Stock</span>
                                    </div>
                                </div>
                                <div class="mt-3 space-y-1 text-left px-1">
                                    <h3 class="font-black uppercase tracking-tighter text-sm leading-tight transition-colors line-clamp-2 group-hover:text-brand-red">{{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}</h3>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-white/40 line-clamp-1">{{ libro.master?.autor ? libro.master.autor.apellido + ', ' + libro.master.autor.nombre : 'Autor Desconocido' }}</span>
                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-base font-black text-brand-red italic">{{ getPrecio(libro) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </component>
                        </div>
                    </div>

                    <div v-if="preventas?.length > 0" class="flex items-center gap-3 mb-6">
                        <h2 class="text-xl font-black uppercase tracking-[0.2em] text-white">Stock Disponible</h2>
                        <div class="h-px flex-1 bg-gradient-to-r from-white/10 to-transparent"></div>
                    </div>

                    <!-- Chips activos -->
                    <div v-if="activeChips.length" class="flex flex-wrap gap-2 mb-6">
                        <button
                            v-for="chip in activeChips"
                            :key="chip.key"
                            @click="removeChip(chip)"
                            class="flex items-center gap-2 px-3 py-1.5 bg-brand-red/10 border border-brand-red/30 rounded-full text-[10px] font-black uppercase tracking-widest text-brand-red hover:bg-brand-red/20 transition-all group"
                        >
                            {{ chip.label }}
                            <svg class="w-3 h-3 opacity-60 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <p class="text-xs font-bold uppercase tracking-widest text-white/20 mb-8">
                        {{ libros.total }} resultado{{ libros.total !== 1 ? 's' : '' }}
                    </p>

                    <!-- Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-5">
                        <component
                            :is="getStockStatus(libro) === 'sin_stock' ? 'div' : Link"
                            v-for="libro in libros.data"
                            :key="libro.id"
                            :href="getStockStatus(libro) === 'sin_stock' ? undefined : route('catalogo.show', libro.id)"
                            class="group"
                            :class="{ 'cursor-not-allowed': getStockStatus(libro) === 'sin_stock' }"
                        >
                            <div
                                class="relative aspect-[2/3] overflow-hidden rounded-xl bg-white/5 border border-white/10 transition-all duration-500"
                                :class="getStockStatus(libro) === 'sin_stock' ? 'opacity-50' : 'group-hover:border-brand-red group-hover:-translate-y-1 group-hover:shadow-[0_12px_30px_rgba(230,25,25,0.15)]'"
                            >
                                <img
                                    :src="libro.portada_url"
                                    :alt="libro.titulo"
                                    class="w-full h-full object-cover grayscale transition-all duration-700"
                                    :class="getStockStatus(libro) === 'sin_stock' ? '' : 'group-hover:grayscale-0 scale-100 group-hover:scale-105'"
                                >
                                <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-white/50 bg-black/80 px-2 py-1 rounded">Sin Stock</span>
                                </div>
                            </div>
                            <div class="mt-3 space-y-1">
                                <h3 class="font-black uppercase tracking-tighter text-sm leading-tight transition-colors line-clamp-2" :class="{ 'group-hover:text-brand-red': getStockStatus(libro) !== 'sin_stock', 'text-white/40': getStockStatus(libro) === 'sin_stock' }">{{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}</h3>
                                <div class="flex flex-col gap-1">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-white/40 line-clamp-1">{{ libro.master?.autor ? libro.master.autor.apellido + ', ' + libro.master.autor.nombre : 'Autor Desconocido' }}</span>
                                    <div class="flex items-center justify-between">
                                        <div class="flex flex-col">
                                            <span v-if="libro.permite_preventa" class="text-[10px] font-black text-white/40 line-through leading-none">{{ getPrecioOriginal(libro) }}</span>
                                            <span class="text-base font-black text-brand-red italic">{{ getPrecio(libro) }}</span>
                                        </div>
                                        <span v-if="getStockStatus(libro) !== 'disponible'" :class="['text-[8px] font-black uppercase tracking-widest', stockClass[getStockStatus(libro)]]">{{ stockLabel[getStockStatus(libro)] }}</span>
                                    </div>
                                    <div v-if="tieneVariasEdiciones(libro)" class="flex gap-1 flex-wrap">
                                        <span
                                            v-for="idioma in getIdiomas(libro)"
                                            :key="idioma"
                                            class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 bg-white/5 border border-white/10 rounded text-white/40"
                                        >{{ idioma }}</span>
                                    </div>
                                    <button
                                        v-if="!tieneVariasEdiciones(libro) && getStockStatus(libro) !== 'sin_stock'"
                                        @click.stop.prevent="agregarAlCarrito(libro)"
                                        class="mt-1 w-full py-2 rounded-lg bg-brand-red/10 border border-brand-red/30 text-brand-red text-[9px] font-black uppercase tracking-widest hover:bg-brand-red hover:text-white transition-all"
                                    >Agregar al Carrito</button>
                                </div>
                            </div>
                        </component>
                    </div>

                    <!-- Sin resultados -->
                    <div v-if="libros.data.length === 0" class="py-24 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white/10 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <h3 class="text-2xl font-black uppercase tracking-widest text-white/30">Sin resultados</h3>
                        <button @click="limpiarFiltros" class="mt-4 text-xs font-black uppercase tracking-widest text-brand-red/60 hover:text-brand-red transition-colors">Limpiar filtros</button>
                    </div>

                    <!-- Paginación -->
                    <div v-if="libros.links?.length > 3" class="mt-10 flex justify-center gap-2 flex-wrap">
                        <Link
                            v-for="link in libros.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            class="px-4 py-2 rounded-lg border border-white/10 text-sm font-black uppercase tracking-tighter transition-all"
                            :class="{ 'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/30 pointer-events-none': !link.url }"
                        >{{ decodeLabel(link.label) }}</Link>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
