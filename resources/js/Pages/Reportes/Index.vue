<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    tab:           String,
    filters:       Object,
    sucursales:    Array,
    reporteVentas: Object,
    reporteStock:  Object,
    reporteBalance:Object,
});

const activeTab  = ref(props.tab || 'ventas');
const desde      = ref(props.filters.desde);
const hasta      = ref(props.filters.hasta);
const sucursalId = ref(props.filters.sucursalId || '');
const showSucursalDrop = ref(false);

const sucursalLabel = computed(() => {
    if (!sucursalId.value) return 'Todas las sucursales';
    const s = props.sucursales.find(s => s.id == sucursalId.value);
    return s ? s.nombre : 'Todas las sucursales';
});

const selectSucursal = (id) => { sucursalId.value = id; showSucursalDrop.value = false; };

const triggerDatePicker = (e) => {
    if (e.target && typeof e.target.showPicker === 'function') {
        e.target.showPicker();
    }
};

const aplicar = () => {
    router.get(route('reportes.index'), {
        tab: activeTab.value,
        desde: desde.value,
        hasta: hasta.value,
        sucursal_id: sucursalId.value,
    }, { preserveState: true, preserveScroll: true });
};

watch([desde, hasta, sucursalId], () => {
    aplicar();
});

const switchTab = (tab) => {
    activeTab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
};

const fmt = (n) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n || 0);
const fmtNum = (n) => new Intl.NumberFormat('es-AR').format(n || 0);

// ApexCharts common font setup
const chartFontFamily = 'Montserrat, sans-serif';

// ── VENTAS CHARTS ──────────────────────────────────────────
const ventasDiaChart = computed(() => {
    if (!props.reporteVentas) return null;
    const dias = props.reporteVentas.porDia;
    return {
        series: [
            { name: 'Total ($)', data: dias.map(d => d.total) },
        ],
        options: {
            chart: { type: 'area', toolbar: { show: false }, background: 'transparent', fontFamily: chartFontFamily },
            theme: { mode: 'dark' },
            colors: ['#38bdf8'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.01 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: dias.map(d => d.dia), labels: { style: { colors: '#71717a', fontSize: '10px', fontFamily: chartFontFamily } } },
            yaxis: { labels: { style: { colors: '#71717a', fontFamily: chartFontFamily }, formatter: v => '$' + fmtNum(v) } },
            grid: { borderColor: '#ffffff0d' },
            tooltip: { theme: 'dark', y: { formatter: v => fmt(v) } },
            dataLabels: { enabled: false },
        },
    };
});

const topProductosChart = computed(() => {
    if (!props.reporteVentas) return null;
    const items = props.reporteVentas.topProductos.slice(0, 8);
    return {
        series: [{ name: 'Unidades', data: items.map(i => i.unidades) }],
        options: {
            chart: { type: 'bar', toolbar: { show: false }, background: 'transparent', fontFamily: chartFontFamily },
            theme: { mode: 'dark' },
            colors: ['#34d399'],
            plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
            xaxis: { categories: items.map(i => i.titulo.length > 25 ? i.titulo.slice(0, 25) + '…' : i.titulo), labels: { style: { colors: '#71717a', fontSize: '10px', fontFamily: chartFontFamily } } },
            yaxis: { labels: { style: { colors: '#a1a1aa', fontSize: '10px', fontFamily: chartFontFamily }, maxWidth: 160 } },
            grid: { borderColor: '#ffffff0d' },
            tooltip: { theme: 'dark' },
            dataLabels: { enabled: false },
        },
    };
});

const porTipoChart = computed(() => {
    if (!props.reporteVentas) return null;
    const items = props.reporteVentas.porTipo;
    return {
        series: items.map(i => i.total),
        options: {
            chart: { type: 'donut', background: 'transparent', fontFamily: chartFontFamily },
            theme: { mode: 'dark' },
            colors: ['#38bdf8', '#818cf8', '#34d399', '#fbbf24'],
            labels: items.map(i => i.tipo || 'Sin tipo'),
            legend: { labels: { colors: '#a1a1aa' }, fontFamily: chartFontFamily },
            dataLabels: { style: { colors: ['#fff'], fontFamily: chartFontFamily } },
            tooltip: { theme: 'dark', y: { formatter: v => fmt(v) } },
            plotOptions: { pie: { donut: { size: '65%' } } },
        },
    };
});



// ── BALANCE CHARTS ─────────────────────────────────────────
const balanceMesChart = computed(() => {
    if (!props.reporteBalance) return null;
    const items = props.reporteBalance.porMes;
    return {
        series: [
            { name: 'Ingresos', data: items.map(i => i.ingresos) },
            { name: 'Ganancia Neta', data: items.map(i => i.rentabilidad) }
        ],
        options: {
            chart: { type: 'area', toolbar: { show: false }, background: 'transparent', fontFamily: chartFontFamily },
            theme: { mode: 'dark' },
            colors: ['#38bdf8', '#34d399'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.01 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: items.map(i => i.mes), labels: { style: { colors: '#71717a', fontSize: '10px', fontFamily: chartFontFamily } } },
            yaxis: { labels: { style: { colors: '#71717a', fontFamily: chartFontFamily }, formatter: v => '$' + fmtNum(v) } },
            grid: { borderColor: '#ffffff0d' },
            tooltip: { theme: 'dark', y: { formatter: v => fmt(v) } },
            dataLabels: { enabled: false },
        },
    };
});

const balanceSucursalChart = computed(() => {
    if (!props.reporteBalance) return null;
    const items = props.reporteBalance.porSucursal;
    return {
        series: [
            { name: 'Ingresos', data: items.map(i => i.ingresos) },
            { name: 'Ganancia Neta', data: items.map(i => i.rentabilidad) }
        ],
        options: {
            chart: { type: 'bar', toolbar: { show: false }, background: 'transparent', fontFamily: chartFontFamily },
            theme: { mode: 'dark' },
            colors: ['#38bdf8', '#34d399'],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
            xaxis: { categories: items.map(i => i.nombre), labels: { style: { colors: '#a1a1aa', fontFamily: chartFontFamily } } },
            yaxis: { labels: { style: { colors: '#71717a', fontFamily: chartFontFamily }, formatter: v => '$' + fmtNum(v) } },
            grid: { borderColor: '#ffffff0d' },
            tooltip: { theme: 'dark', y: { formatter: v => fmt(v) } },
            dataLabels: { enabled: false },
        },
    };
});
</script>

<template>
    <Head title="Reportes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-reportes">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">REPORTES Y ANÁLISIS</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-reportes">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Filtros globales Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Desde</label>
                            <input 
                                v-model="desde" 
                                type="date"
                                @click="triggerDatePicker"
                                class="bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" 
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Hasta</label>
                            <input 
                                v-model="hasta" 
                                type="date"
                                @click="triggerDatePicker"
                                class="bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" 
                            />
                        </div>
                        <div class="relative">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal</label>
                            <button type="button" @click="showSucursalDrop = !showSucursalDrop"
                                class="flex items-center gap-3 bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 min-w-48">
                                <span>{{ sucursalLabel }}</span>
                                <svg class="w-4 h-4 text-zinc-500 ml-auto transition-transform" :class="{ 'rotate-180': showSucursalDrop }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div v-if="showSucursalDrop" class="absolute z-20 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                                <button type="button" @click="selectSucursal('')" class="w-full text-left px-4 py-2.5 text-xs font-semibold text-zinc-400 hover:bg-white/5 transition-colors border-b border-white/5">
                                    Todas las sucursales
                                </button>
                                <button v-for="s in sucursales" :key="s.id" type="button" @click="selectSucursal(s.id)"
                                    class="w-full text-left px-4 py-2.5 text-xs font-semibold text-white hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                                    :class="{ 'text-emerald-400': sucursalId == s.id }">
                                    {{ s.nombre }}
                                </button>
                            </div>
                            <div v-if="showSucursalDrop" class="fixed inset-0 z-10" @click="showSucursalDrop = false" />
                        </div>
                    </div>
                </div>

                <!-- Tabs Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-2 shadow-xl">
                    <div class="flex items-center gap-2">
                        <button
                            v-for="t in [{id:'ventas',label:'Ventas'},{id:'stock',label:'Stock'},{id:'balance',label:'Balance'}]"
                            :key="t.id"
                            @click="switchTab(t.id)"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all"
                            :class="activeTab === t.id ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            {{ t.label }}
                        </button>
                        <Link
                            :href="route('reportes.prediccion')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all text-zinc-400 hover:text-white bg-transparent flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                            Predicción de Demanda
                        </Link>
                    </div>
                </div>

                <!-- ══════════════ TAB: VENTAS ══════════════ -->
                <template v-if="activeTab === 'ventas' && reporteVentas">

                    <!-- Stats -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Total Ventas</p>
                            <p class="text-3xl font-bold text-white font-mono">{{ fmtNum(reporteVentas.totales?.cantidad) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Ingresos</p>
                            <p class="text-2xl font-bold text-emerald-400 font-mono">{{ fmt(reporteVentas.totales?.total) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Ticket Promedio</p>
                            <p class="text-2xl font-bold text-sky-400 font-mono">
                                {{ reporteVentas.totales?.cantidad > 0 ? fmt(reporteVentas.totales.total / reporteVentas.totales.cantidad) : '$0' }}
                            </p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Tipos de Venta</p>
                            <p class="text-3xl font-bold text-purple-400 font-mono">{{ reporteVentas.porTipo?.length }}</p>
                        </div>
                    </div>

                    <!-- Gráficas: evolución + donut -->
                    <div class="grid lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-4">Evolución de Ingresos</p>
                            <template v-if="ventasDiaChart && reporteVentas.porDia?.length">
                                <VueApexCharts type="area" height="220"
                                    :options="ventasDiaChart.options"
                                    :series="ventasDiaChart.series" />
                            </template>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-16">Sin datos en el período</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-4">Por Tipo de Venta</p>
                            <template v-if="porTipoChart && reporteVentas.porTipo?.length">
                                <VueApexCharts type="donut" height="220"
                                    :options="porTipoChart.options"
                                    :series="porTipoChart.series" />
                            </template>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-16">Sin datos</p>
                        </div>
                    </div>

                    <!-- Top Productos + Top Clientes -->
                    <div class="grid lg:grid-cols-2 gap-4">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-4">Top 8 Productos</p>
                            <template v-if="topProductosChart && reporteVentas.topProductos?.length">
                                <VueApexCharts type="bar" height="240"
                                    :options="topProductosChart.options"
                                    :series="topProductosChart.series" />
                            </template>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-16">Sin datos</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-4">Top Clientes</p>
                            <div v-if="reporteVentas.topClientes?.length" class="space-y-2 overflow-y-auto max-h-60 pr-1">
                                <div v-for="(c, i) in reporteVentas.topClientes" :key="i"
                                    class="flex items-center justify-between px-4 py-2.5 rounded-xl bg-white/[0.02] border border-white/5 hover:bg-white/[0.04] transition-colors">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-bold text-zinc-500 w-5 text-right">#{{ i+1 }}</span>
                                        <div>
                                            <p class="text-xs font-bold text-white capitalize">{{ c.nombre || 'Sin nombre' }}</p>
                                            <p class="text-xs text-zinc-400 font-medium mt-0.5">{{ c.compras }} compras</p>
                                        </div>
                                    </div>
                                    <span class="text-xs font-bold font-mono text-emerald-400">{{ fmt(c.total) }}</span>
                                </div>
                            </div>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-16">Sin datos</p>
                        </div>
                    </div>

                </template>

                <!-- ══════════════ TAB: STOCK ══════════════ -->
                <template v-if="activeTab === 'stock' && reporteStock">

                    <!-- Stats -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Total Unidades</p>
                            <p class="text-3xl font-bold text-white font-mono">{{ fmtNum(reporteStock.totales.total_unidades) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Títulos Únicos</p>
                            <p class="text-3xl font-bold text-sky-400 font-mono">{{ fmtNum(reporteStock.totales.titulos_unicos) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Sin Stock</p>
                            <p class="text-3xl font-bold text-rose-400 font-mono">{{ reporteStock.totales.sin_stock }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Stock Bajo (≤5)</p>
                            <p class="text-3xl font-bold text-amber-400 font-mono">{{ reporteStock.totales.stock_bajo }}</p>
                        </div>
                    </div>



                    <!-- Top Rotación: Más Vendidos vs Estancados -->
                    <div class="grid lg:grid-cols-2 gap-4" v-if="reporteStock.rotacion">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                            <div class="px-6 py-4 border-b border-white/5 bg-white/[0.01]">
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    <span>Top 10 Mayor Rotación (Últimos 30 días)</span>
                                </p>
                            </div>
                            <div v-if="reporteStock.rotacion.masVendidos?.length" class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                                <div v-for="(item, i) in reporteStock.rotacion.masVendidos" :key="i"
                                    class="flex items-center justify-between px-6 py-3.5 hover:bg-white/[0.02]">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-bold text-zinc-500 w-5 text-right">#{{ i+1 }}</span>
                                        <p class="text-xs font-bold text-white">{{ item.titulo }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold text-emerald-400">
                                        {{ item.unidades }} vendidas
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-8">Sin ventas en el período</p>
                        </div>

                        <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                            <div class="px-6 py-4 border-b border-white/5 bg-white/[0.01]">
                                <p class="text-xs font-bold uppercase tracking-wider text-rose-400 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Top 10 Estancados (Sin ventas en >60 días)</span>
                                </p>
                            </div>
                            <div v-if="reporteStock.rotacion.estancados?.length" class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                                <div v-for="(item, i) in reporteStock.rotacion.estancados" :key="i"
                                    class="flex items-center justify-between px-6 py-3.5 hover:bg-white/[0.02]">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-bold text-zinc-500 w-5 text-right">#{{ i+1 }}</span>
                                        <p class="text-xs font-bold text-white">{{ item.titulo }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs font-bold text-rose-400">
                                        {{ item.disponible }} varados
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-8">Sin productos estancados</p>
                        </div>
                    </div>

                    <!-- Tablas: sin stock + bajo -->
                    <div class="grid lg:grid-cols-2 gap-4">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                            <div class="px-6 py-4 border-b border-white/5 bg-white/[0.01]">
                                <p class="text-xs font-bold uppercase tracking-wider text-rose-400">Sin Stock</p>
                            </div>
                            <div v-if="reporteStock.sinStock?.length" class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                                <div v-for="(item, i) in reporteStock.sinStock" :key="i"
                                    class="flex items-center justify-between px-6 py-3.5 hover:bg-white/[0.02]">
                                    <div>
                                        <p class="text-xs font-bold text-white">{{ item.titulo.length > 50 ? item.titulo.slice(0,50)+'…' : item.titulo }}</p>
                                        <p class="text-xs text-zinc-400 font-semibold mt-0.5">{{ item.sucursal }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs font-bold text-rose-400">
                                        0
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-8">Todo con stock</p>
                        </div>

                        <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                            <div class="px-6 py-4 border-b border-white/5 bg-white/[0.01]">
                                <p class="text-xs font-bold uppercase tracking-wider text-amber-400">Stock Bajo (1-5 unid.)</p>
                            </div>
                            <div v-if="reporteStock.stockBajo?.length" class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                                <div v-for="(item, i) in reporteStock.stockBajo" :key="i"
                                    class="flex items-center justify-between px-6 py-3.5 hover:bg-white/[0.02]">
                                    <div>
                                        <p class="text-xs font-bold text-white">{{ item.titulo.length > 50 ? item.titulo.slice(0,50)+'…' : item.titulo }}</p>
                                        <p class="text-xs text-zinc-400 font-semibold mt-0.5">{{ item.sucursal }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs font-bold text-amber-400">
                                        {{ item.disponible }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-8">Sin títulos con stock bajo</p>
                        </div>
                    </div>

                </template>

                <!-- ══════════════ TAB: BALANCE ══════════════ -->
                <template v-if="activeTab === 'balance' && reporteBalance">

                    <!-- Stats -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Ingresos Totales</p>
                            <p class="text-2xl font-bold text-sky-400 font-mono">{{ fmt(reporteBalance.totalIngresos) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Costo de Mercadería</p>
                            <p class="text-2xl font-bold text-rose-400 font-mono">{{ fmt(reporteBalance.totalCogs) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Ganancia Neta</p>
                            <p class="text-2xl font-bold text-emerald-400 font-mono">{{ fmt(reporteBalance.totalRentabilidad) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Ventas Realizadas</p>
                            <p class="text-2xl font-bold text-white font-mono">{{ fmtNum(reporteBalance.totalVentas) }}</p>
                        </div>
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 shadow-xl">
                            <p class="text-xs uppercase font-semibold text-zinc-400 mb-1">Ticket Promedio</p>
                            <p class="text-2xl font-bold text-zinc-300 font-mono">{{ fmt(reporteBalance.ticketPromedio) }}</p>
                        </div>
                    </div>

                    <!-- Evolución mensual -->
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-4">Ingresos por Mes</p>
                        <template v-if="balanceMesChart && reporteBalance.porMes?.length">
                            <VueApexCharts type="area" height="240"
                                :options="balanceMesChart.options"
                                :series="balanceMesChart.series" />
                        </template>
                        <p v-else class="text-zinc-500 text-xs italic text-center py-16">Sin datos en el período</p>
                    </div>

                    <!-- Por sucursal -->
                    <div class="grid lg:grid-cols-2 gap-4">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-4">Ingresos por Sucursal</p>
                            <template v-if="balanceSucursalChart && reporteBalance.porSucursal?.length">
                                <VueApexCharts type="bar" height="220"
                                    :options="balanceSucursalChart.options"
                                    :series="balanceSucursalChart.series" />
                            </template>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-8">Sin datos</p>
                        </div>

                        <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                            <div class="px-6 py-4 border-b border-white/5 bg-white/[0.01]">
                                <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">Detalle por Sucursal</p>
                            </div>
                            <div v-if="reporteBalance.porSucursal?.length" class="divide-y divide-white/5">
                                <div v-for="(s, i) in reporteBalance.porSucursal" :key="i"
                                    class="flex items-center justify-between px-6 py-4 hover:bg-white/[0.02] transition-colors">
                                    <div>
                                        <p class="text-xs font-bold text-white">{{ s.nombre }}</p>
                                        <p class="text-xs text-zinc-400 font-semibold mt-0.5">{{ s.ventas }} ventas</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-bold font-mono text-sky-400">{{ fmt(s.ingresos) }}</p>
                                        <p class="text-xs font-bold font-mono text-emerald-400 mt-0.5">Rent. {{ fmt(s.rentabilidad) }}</p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-zinc-500 text-xs italic text-center py-8">Sin datos</p>
                        </div>
                    </div>

                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-reportes,
.page-reportes * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
