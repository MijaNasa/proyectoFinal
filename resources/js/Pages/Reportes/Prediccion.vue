<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    categorias: Array,
});

const chartFontFamily = 'Montserrat, sans-serif';
const fmtNum = (n) => new Intl.NumberFormat('es-AR').format(n || 0);

const nivel = ref('obra'); // 'libro' | 'obra' | 'categoria'
const niveles = [
    { id: 'obra',      label: 'Por Obra' },
    { id: 'libro',     label: 'Por Libro' },
    { id: 'categoria', label: 'Por Categoría' },
];

const cambiarNivel = (n) => {
    nivel.value = n;
    seleccion.value = null;
    busqueda.value = '';
    resultados.value = [];
    datos.value = null;
};

// --- Búsqueda (libro / obra) ---
const busqueda = ref('');
const resultados = ref([]);
const showDropdown = ref(false);
const buscando = ref(false);
let debounceTimer = null;

const buscar = (q) => {
    clearTimeout(debounceTimer);
    if (!q || q.trim().length < 1) {
        resultados.value = [];
        return;
    }
    debounceTimer = setTimeout(async () => {
        buscando.value = true;
        try {
            const res = await window.axios.get(route('reportes.prediccion.buscar'), {
                params: { nivel: nivel.value, q: q.trim() },
            });
            resultados.value = res.data;
            showDropdown.value = true;
        } catch (e) {
            console.error('Error al buscar:', e);
        } finally {
            buscando.value = false;
        }
    }, 300);
};

// --- Selección y carga de datos ---
const seleccion = ref(null);
const datos = ref(null);
const cargando = ref(false);

const seleccionar = async (item) => {
    seleccion.value = item;
    busqueda.value = item.label ?? item.nombre;
    resultados.value = [];
    showDropdown.value = false;
    await cargarDatos(item.id);
};

const seleccionarCategoria = async (e) => {
    const id = e.target.value;
    if (!id) { seleccion.value = null; datos.value = null; return; }
    const cat = props.categorias.find(c => c.id == id);
    seleccion.value = cat;
    await cargarDatos(id);
};

const cargarDatos = async (id) => {
    cargando.value = true;
    datos.value = null;
    try {
        const res = await window.axios.get(route('reportes.prediccion.datos'), {
            params: { nivel: nivel.value, id },
        });
        datos.value = res.data;
    } catch (e) {
        console.error('Error al cargar predicción:', e);
    } finally {
        cargando.value = false;
    }
};

const chart = computed(() => {
    if (!datos.value) return null;
    return {
        series: [
            { name: 'Unidades vendidas', data: datos.value.valores },
            { name: 'Tendencia (suavizado)', data: datos.value.suavizado },
        ],
        options: {
            chart: { type: 'line', toolbar: { show: false }, background: 'transparent', fontFamily: chartFontFamily },
            theme: { mode: 'dark' },
            colors: ['#38bdf8', '#fbbf24'],
            stroke: { curve: 'smooth', width: [2, 3], dashArray: [0, 4] },
            xaxis: { categories: datos.value.etiquetas, labels: { style: { colors: '#71717a', fontSize: '10px', fontFamily: chartFontFamily } } },
            yaxis: { labels: { style: { colors: '#71717a', fontFamily: chartFontFamily }, formatter: v => fmtNum(v) } },
            grid: { borderColor: '#ffffff0d' },
            legend: { labels: { colors: '#a1a1aa' }, fontFamily: chartFontFamily },
            tooltip: { theme: 'dark' },
            dataLabels: { enabled: false },
        },
    };
});
</script>

<template>
    <Head title="Predicción de Demanda" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-reportes">
                <div>
                    <Link :href="route('reportes.index')" class="text-xs font-semibold text-zinc-400 hover:text-white transition-colors flex items-center gap-1 mb-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        <span>Volver a Reportes</span>
                    </Link>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">PREDICCIÓN DE DEMANDA</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-reportes">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Nivel -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-2 shadow-xl">
                    <div class="flex items-center gap-2">
                        <button
                            v-for="n in niveles" :key="n.id"
                            @click="cambiarNivel(n.id)"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all"
                            :class="nivel === n.id ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            {{ n.label }}
                        </button>
                    </div>
                </div>

                <!-- Selector -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                    <label class="block text-xs font-semibold text-zinc-400 mb-2">
                        {{ nivel === 'categoria' ? 'Elegí una categoría' : (nivel === 'obra' ? 'Buscá una obra por título' : 'Buscá un libro por título o ISBN') }}
                    </label>

                    <select
                        v-if="nivel === 'categoria'"
                        @change="seleccionarCategoria"
                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                    >
                        <option value="" class="bg-black text-zinc-300">-- Seleccioná --</option>
                        <option v-for="c in props.categorias" :key="c.id" :value="c.id" class="bg-black text-zinc-300">{{ c.nombre }}</option>
                    </select>

                    <div v-else class="relative">
                        <input
                            v-model="busqueda"
                            type="text"
                            :placeholder="nivel === 'obra' ? 'Ej: One Piece' : 'Ej: 9789500765432 o One Piece'"
                            @input="buscar(busqueda)"
                            @focus="showDropdown = resultados.length > 0"
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium placeholder-zinc-600 focus:outline-none focus:border-white/30"
                        />
                        <div v-if="showDropdown && resultados.length" class="absolute z-20 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl max-h-64 overflow-y-auto">
                            <button
                                v-for="r in resultados" :key="r.id"
                                type="button"
                                @mousedown.prevent="seleccionar(r)"
                                class="w-full text-left px-4 py-2.5 text-xs font-semibold text-white hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                            >
                                {{ r.label }}
                            </button>
                        </div>
                        <div v-if="showDropdown" class="fixed inset-0 z-10" @click="showDropdown = false" />
                    </div>
                </div>

                <!-- Resultado -->
                <div v-if="cargando" class="bg-[#131316] border border-white/5 rounded-2xl p-10 shadow-xl text-center text-zinc-500 text-sm">
                    Calculando...
                </div>

                <template v-else-if="datos">
                    <div v-if="datos.historial_insuficiente" class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-400 font-medium">
                        ⚠️ Historial insuficiente ({{ datos.semanas_con_ventas }} semana(s) con ventas en los últimos {{ 16 }}). El pronóstico puede no ser confiable todavía.
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2 bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-sm font-bold text-white tracking-tight mb-4">{{ datos.nombre }} — últimas 16 semanas</h3>
                            <VueApexCharts v-if="chart" type="line" height="280" :options="chart.options" :series="chart.series" />
                        </div>

                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex flex-col justify-center items-center text-center">
                            <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2">Pronóstico próxima semana</span>
                            <div class="text-4xl font-black text-white tracking-tight">{{ fmtNum(datos.pronostico_proxima_semana) }}</div>
                            <div class="text-xs text-zinc-500 mt-1">unidades estimadas</div>
                        </div>
                    </div>
                </template>

                <div v-else class="bg-[#131316] border border-white/5 rounded-2xl p-10 shadow-xl text-center text-zinc-500 text-sm">
                    Elegí {{ nivel === 'categoria' ? 'una categoría' : (nivel === 'obra' ? 'una obra' : 'un libro') }} para ver la predicción.
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>