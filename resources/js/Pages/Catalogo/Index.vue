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

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">
            <!-- Preventas Activas -->
                    <div v-if="preventas?.length > 0" class="mb-12">
                        <div class="flex items-center gap-3 mb-6">
                            <h2 class="text-xl font-black uppercase tracking-[0.2em] text-white">Preventas Activas</h2>
                            <div class="h-px flex-1 bg-white/10"></div>
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
                                class="group flex-shrink-0 w-44 md:w-52 snap-start flex flex-col justify-between h-full bg-white/[0.03] hover:bg-white/[0.06] border border-white/10 hover:border-brand-red/50 rounded-xl overflow-hidden shadow-lg transition-all duration-300"
                                :class="{ 'opacity-50': getStockStatus(libro) === 'sin_stock' }"
                            >
                                <div class="flex flex-col flex-1">
                                    <Link
                                        :href="getStockStatus(libro) === 'sin_stock' ? undefined : route('catalogo.show', libro.id)"
                                        class="block relative aspect-[2/3] overflow-hidden bg-black/40 border-b border-white/5"
                                        :class="{ 'cursor-not-allowed': getStockStatus(libro) === 'sin_stock' }"
                                    >
                                        <div class="absolute top-2 left-2 z-10 bg-brand-red text-white text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded shadow">Preventa</div>
                                        <img
                                            :src="libro.portada_url"
                                            :alt="libro.titulo"
                                            @error="$event.target.src = '/images/no-cover.png'"
                                            class="w-full h-full object-cover transition-transform duration-700 pointer-events-none group-hover:scale-105"
                                            draggable="false"
                                        >
                                        <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/70 flex items-center justify-center">
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-white/70 bg-black/80 px-2 py-1 rounded border border-white/10">Sin Stock</span>
                                        </div>
                                    </Link>
                                    <div class="p-3 flex flex-col flex-1 justify-between space-y-2 text-left">
                                        <div>
                                            <h3 class="font-bold text-xs leading-snug text-white group-hover:text-brand-red transition-colors line-clamp-2 h-9 flex items-center" :title="`${libro.master?.titulo} ${libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : ''}`">
                                                {{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}
                                            </h3>
                                            <p class="text-[10px] font-medium text-white/50 truncate mt-1">
                                                {{ libro.master?.autor ? libro.master.autor.apellido + ', ' + libro.master.autor.nombre : 'Autor Desconocido' }}
                                            </p>
                                        </div>
                                        <div class="pt-1">
                                            <span class="text-sm font-bold text-white">{{ getPrecio(libro) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto border-t border-white/10 grid grid-cols-2 divide-x divide-white/10 bg-black/40 text-center">
                                    <Link
                                        :href="route('catalogo.show', libro.id)"
                                        class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-[10px] font-bold uppercase tracking-wider text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Detalles</span>
                                    </Link>

                                    <button
                                        v-if="getStockStatus(libro) !== 'sin_stock'"
                                        @click.stop.prevent="agregarAlCarrito(libro)"
                                        class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-brand-red transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z" />
                                        </svg>
                                        <span>Comprar</span>
                                    </button>
                                    <div v-else class="flex items-center justify-center py-2.5 px-1.5 text-[10px] font-bold uppercase tracking-wider text-white/30 cursor-not-allowed">
                                        Sin Stock
                                    </div>
                                </div>
                            </div>
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
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-5 items-stretch">
                        <div
                            v-for="libro in libros.data"
                            :key="libro.id"
                            class="group flex flex-col justify-between h-full bg-white/[0.03] hover:bg-white/[0.06] border border-white/10 hover:border-brand-red/50 rounded-xl overflow-hidden shadow-lg transition-all duration-300"
                            :class="{ 'opacity-50': getStockStatus(libro) === 'sin_stock' }"
                        >
                            <div class="flex flex-col flex-1">
                                <Link
                                    :href="getStockStatus(libro) === 'sin_stock' ? undefined : route('catalogo.show', libro.id)"
                                    class="block relative aspect-[2/3] overflow-hidden bg-black/40 border-b border-white/5"
                                    :class="{ 'cursor-not-allowed': getStockStatus(libro) === 'sin_stock' }"
                                >
                                    <img
                                        :src="libro.portada_url"
                                        :alt="libro.titulo"
                                        @error="$event.target.src = '/images/no-cover.png'"
                                        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                        :class="{ 'grayscale': getStockStatus(libro) === 'sin_stock' }"
                                    >
                                    <div v-if="libro.permite_preventa" class="absolute top-2 left-2 z-10 bg-brand-red text-white text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded shadow">Preventa</div>
                                    <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/70 flex items-center justify-center">
                                        <span class="text-[9px] font-bold uppercase tracking-wider text-white/70 bg-black/80 px-2 py-1 rounded border border-white/10">Sin Stock</span>
                                    </div>
                                </Link>
                                <div class="p-3 flex flex-col flex-1 justify-between space-y-2 text-left">
                                    <div>
                                        <h3
                                            class="font-bold text-xs leading-snug text-white transition-colors line-clamp-2 h-9 flex items-center"
                                            :class="{ 'group-hover:text-brand-red': getStockStatus(libro) !== 'sin_stock', 'text-white/40': getStockStatus(libro) === 'sin_stock' }"
                                            :title="`${libro.master?.titulo} ${libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : ''}`"
                                        >
                                            {{ libro.master?.titulo }} {{ libro.numero_tomo ? '- Tomo ' + libro.numero_tomo : '' }}
                                        </h3>
                                        <p class="text-[10px] font-medium text-white/50 truncate mt-1">
                                            {{ libro.master?.autor ? libro.master.autor.apellido + ', ' + libro.master.autor.nombre : 'Autor Desconocido' }}
                                        </p>
                                    </div>
                                    <div class="pt-1 flex items-baseline justify-between">
                                        <div class="flex flex-col">
                                            <span v-if="libro.permite_preventa" class="text-[10px] text-white/40 line-through leading-none">{{ getPrecioOriginal(libro) }}</span>
                                            <span class="text-sm font-bold text-white">{{ getPrecio(libro) }}</span>
                                        </div>
                                        <span v-if="getStockStatus(libro) !== 'disponible'" :class="['text-[8px] font-bold uppercase tracking-wider', stockClass[getStockStatus(libro)]]">{{ stockLabel[getStockStatus(libro)] }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-auto border-t border-white/10 grid grid-cols-2 divide-x divide-white/10 bg-black/40 text-center">
                                <Link
                                    :href="route('catalogo.show', libro.id)"
                                    class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-[10px] font-bold uppercase tracking-wider text-white/70 hover:text-white hover:bg-white/10 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Detalles</span>
                                </Link>

                                <button
                                    v-if="getStockStatus(libro) !== 'sin_stock'"
                                    @click.stop.prevent="agregarAlCarrito(libro)"
                                    class="flex items-center justify-center gap-1 py-2.5 px-1.5 text-[10px] font-bold uppercase tracking-wider text-white hover:bg-brand-red transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z" />
                                    </svg>
                                    <span>Comprar</span>
                                </button>
                                <div v-else class="flex items-center justify-center py-2.5 px-1.5 text-[10px] font-bold uppercase tracking-wider text-white/30 cursor-not-allowed">
                                    Sin Stock
                                </div>
                            </div>
                        </div>
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
    </PublicLayout>
</template>
