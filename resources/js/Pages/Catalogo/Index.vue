<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, reactive } from 'vue';

const props = defineProps({
    libros: Array,
    categorias: Array,
    autores: Array,
    series: Array,
    editoriales: Array,
    idiomas: Array,
    filters: Object,
});

// Filtros
const search = ref('');
const selected = reactive({
    categorias:  [],
    autores:     [],
    series:      [],
    editoriales: [],
    idiomas:     [],
});

// Secciones colapsables
const open = reactive({
    categorias:  false,
    autores:     false,
    series:      false,
    editoriales: false,
    idiomas:     false,
});

// Chips activos
const activeChips = computed(() => {
    const chips = [];
    selected.categorias.forEach(id => {
        const item = props.categorias.find(x => x.id === id);
        if (item) chips.push({ label: item.nombre, key: 'categorias', id });
    });
    selected.autores.forEach(id => {
        const item = props.autores.find(x => x.id === id);
        if (item) chips.push({ label: `${item.apellido}, ${item.nombre}`, key: 'autores', id });
    });
    selected.series.forEach(id => {
        const item = props.series.find(x => x.id === id);
        if (item) chips.push({ label: item.nombre, key: 'series', id });
    });
    selected.editoriales.forEach(id => {
        const item = props.editoriales.find(x => x.id === id);
        if (item) chips.push({ label: item.nombre, key: 'editoriales', id });
    });
    selected.idiomas.forEach(id => {
        const item = props.idiomas.find(x => x.id === id);
        if (item) chips.push({ label: item.nombre, key: 'idiomas', id });
    });
    return chips;
});

const removeChip = (chip) => {
    selected[chip.key] = selected[chip.key].filter(id => id !== chip.id);
};

const limpiarFiltros = () => {
    search.value = '';
    Object.keys(selected).forEach(k => selected[k] = []);
};

const hayFiltrosActivos = computed(() => search.value || activeChips.value.length);

// Lógica de filtrado
const filteredLibros = computed(() => {
    return props.libros.filter(libro => {
        const q = search.value.toLowerCase();
        const matchesSearch = !q ||
            libro.titulo.toLowerCase().includes(q) ||
            (libro.titulo_original?.toLowerCase().includes(q)) ||
            (libro.autor?.nombre.toLowerCase().includes(q)) ||
            (libro.autor?.apellido.toLowerCase().includes(q));

        const matchesCategoria  = !selected.categorias.length  || selected.categorias.includes(libro.categoria_id);
        const matchesAutor      = !selected.autores.length      || selected.autores.includes(libro.autor_id);
        const matchesSerie      = !selected.series.length       || libro.libros?.some(l => selected.series.includes(l.serie_id));
        const matchesEditorial  = !selected.editoriales.length  || libro.libros?.some(l => selected.editoriales.includes(l.editorial_id));
        const matchesIdioma     = !selected.idiomas.length      || libro.libros?.some(l => selected.idiomas.includes(l.idioma_id));

        return matchesSearch && matchesCategoria && matchesAutor && matchesSerie && matchesEditorial && matchesIdioma;
    });
});

// Stock
const getStockStatus = (libro) => {
    const total = libro.stock_total ?? 0;
    if (total === 0) return 'sin_stock';
    if (total < 5)  return 'pocos';
    return 'disponible';
};
const stockLabel = { disponible: 'Disponible', pocos: 'Quedan pocos', sin_stock: 'Sin stock' };
const stockClass = { disponible: 'text-green-400', pocos: 'text-yellow-400', sin_stock: 'text-red-400' };

const getPrecio = (libro) => {
    const precio = libro.libros?.[0]?.precio_actual?.precio_venta;
    if (!precio) return 'Consultar';
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(precio);
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
                            type="text"
                            placeholder="BUSCAR..."
                            class="w-full bg-white/5 border border-white/10 rounded-lg py-3 px-4 text-sm text-white focus:border-brand-red outline-none transition-all placeholder:text-white/40 font-bold uppercase"
                        >
                    </div>

                    <!-- Categoría -->
                    <div v-if="categorias.length" class="border-t border-white/5">
                        <button @click="open.categorias = !open.categorias" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.categorias || selected.categorias.length ? 'text-white' : 'text-white/50'">
                            <span>Categoría <span v-if="selected.categorias.length" class="text-brand-red">({{ selected.categorias.length }})</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.categorias ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.categorias" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in categorias" :key="item.id" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.categorias.includes(item.id) ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.categorias.includes(item.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <input type="checkbox" :value="item.id" v-model="selected.categorias" class="sr-only">
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors">{{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Autor -->
                    <div v-if="autores.length" class="border-t border-white/5">
                        <button @click="open.autores = !open.autores" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.autores || selected.autores.length ? 'text-white' : 'text-white/50'">
                            <span>Autor <span v-if="selected.autores.length" class="text-brand-red">({{ selected.autores.length }})</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.autores ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.autores" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in autores" :key="item.id" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.autores.includes(item.id) ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.autores.includes(item.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <input type="checkbox" :value="item.id" v-model="selected.autores" class="sr-only">
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors line-clamp-1">{{ item.apellido }}, {{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Serie -->
                    <div v-if="series.length" class="border-t border-white/5">
                        <button @click="open.series = !open.series" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.series || selected.series.length ? 'text-white' : 'text-white/50'">
                            <span>Serie <span v-if="selected.series.length" class="text-brand-red">({{ selected.series.length }})</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.series ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.series" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in series" :key="item.id" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.series.includes(item.id) ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.series.includes(item.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <input type="checkbox" :value="item.id" v-model="selected.series" class="sr-only">
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors line-clamp-1">{{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Editorial -->
                    <div v-if="editoriales.length" class="border-t border-white/5">
                        <button @click="open.editoriales = !open.editoriales" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.editoriales || selected.editoriales.length ? 'text-white' : 'text-white/50'">
                            <span>Editorial <span v-if="selected.editoriales.length" class="text-brand-red">({{ selected.editoriales.length }})</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.editoriales ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.editoriales" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in editoriales" :key="item.id" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.editoriales.includes(item.id) ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.editoriales.includes(item.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <input type="checkbox" :value="item.id" v-model="selected.editoriales" class="sr-only">
                                <span class="text-xs font-bold uppercase tracking-wide text-white/50 group-hover:text-white transition-colors line-clamp-1">{{ item.nombre }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Idioma -->
                    <div v-if="idiomas.length" class="border-t border-white/5">
                        <button @click="open.idiomas = !open.idiomas" class="w-full flex items-center justify-between py-4 text-xs font-black uppercase tracking-[0.2em] hover:text-white transition-colors" :class="open.idiomas || selected.idiomas.length ? 'text-white' : 'text-white/50'">
                            <span>Idioma <span v-if="selected.idiomas.length" class="text-brand-red">({{ selected.idiomas.length }})</span></span>
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open.idiomas ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="open.idiomas" class="pb-4 space-y-2.5 max-h-48 overflow-y-auto pr-1">
                            <label v-for="item in idiomas" :key="item.id" class="flex items-center gap-3 cursor-pointer group">
                                <div class="w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition-all" :class="selected.idiomas.includes(item.id) ? 'bg-brand-red border-brand-red' : 'bg-white/5 border-white/20'">
                                    <svg v-if="selected.idiomas.includes(item.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <input type="checkbox" :value="item.id" v-model="selected.idiomas" class="sr-only">
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
                <div class="flex-1 min-w-0">

                    <!-- Chips activos -->
                    <div v-if="activeChips.length" class="flex flex-wrap gap-2 mb-6">
                        <button
                            v-for="chip in activeChips"
                            :key="`${chip.key}-${chip.id}`"
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
                        {{ filteredLibros.length }} resultado{{ filteredLibros.length !== 1 ? 's' : '' }}
                    </p>

                    <!-- Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-5">
                        <Link v-for="libro in filteredLibros" :key="libro.id" :href="route('catalogo.show', libro.id)" class="group">
                            <div class="relative aspect-[2/3] overflow-hidden rounded-xl bg-white/5 border border-white/10 transition-all duration-500 group-hover:border-brand-red group-hover:-translate-y-1 group-hover:shadow-[0_12px_30px_rgba(230,25,25,0.15)]">
                                <img :src="libro.portada_url" :alt="libro.titulo" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700 scale-100 group-hover:scale-105">
                                <div v-if="getStockStatus(libro) === 'sin_stock'" class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-white/50 bg-black/80 px-2 py-1 rounded">Sin Stock</span>
                                </div>
                                <div v-else class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/80 to-transparent p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                                    <span class="inline-block px-2 py-1 bg-brand-red text-[9px] font-black uppercase tracking-widest text-white rounded">Ver Detalle</span>
                                </div>
                            </div>
                            <div class="mt-3 space-y-1">
                                <h3 class="font-black uppercase tracking-tighter text-sm leading-tight group-hover:text-brand-red transition-colors line-clamp-2">{{ libro.titulo }}</h3>
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-white/40 line-clamp-1">{{ libro.autor ? libro.autor.apellido + ', ' + libro.autor.nombre : 'Autor Desconocido' }}</span>
                                    <div class="flex items-center justify-between">
                                        <span class="text-base font-black text-brand-red italic">{{ getPrecio(libro) }}</span>
                                        <span :class="['text-[8px] font-black uppercase tracking-widest', stockClass[getStockStatus(libro)]]">{{ stockLabel[getStockStatus(libro)] }}</span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Sin resultados -->
                    <div v-if="filteredLibros.length === 0" class="py-24 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white/10 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <h3 class="text-2xl font-black uppercase tracking-widest text-white/30">Sin resultados</h3>
                        <button @click="limpiarFiltros" class="mt-4 text-xs font-black uppercase tracking-widest text-brand-red/60 hover:text-brand-red transition-colors">Limpiar filtros</button>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
