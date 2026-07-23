<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
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

const aplicar = () => {
    router.get(route('reportes.index'), {
        tab: activeTab.value,
        desde: desde.value,
        hasta: hasta.value,
        sucursal_id: sucursalId.value,
    }, { preserveState: false });
};

const switchTab = (tab) => {
    activeTab.value = tab;
    router.get(route('reportes.index'), {
        tab,
        desde: desde.value,
        hasta: hasta.value,
        sucursal_id: sucursalId.value,
    }, { preserveState: false });
};

const fmt = (n) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n || 0);
const fmtNum = (n) => new Intl.NumberFormat('es-AR').format(n || 0);

// ── VENTAS CHARTS ──────────────────────────────────────────
const ventasDiaChart = computed(() => {
    if (!props.reporteVentas) return null;
    const dias = props.reporteVentas.porDia;
    return {
        series: [
            { name: 'Total ($)', data: dias.map(d => d.total) },
        ],
        options: {
            chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
            theme: { mode: 'dark' },
            colors: ['#e61919'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.01 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: dias.map(d => d.dia), labels: { style: { colors: '#ffffff44', fontSize: '10px' } } },
            yaxis: { labels: { style: { colors: '#ffffff44' }, formatter: v => '$' + fmtNum(v) } },
            grid: { borderColor: '#ffffff10' },
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
            chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
            theme: { mode: 'dark' },
            colors: ['#e61919'],
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
            xaxis: { categories: items.map(i => i.titulo.length > 25 ? i.titulo.slice(0, 25) + '…' : i.titulo), labels: { style: { colors: '#ffffff44', fontSize: '10px' } } },
            yaxis: { labels: { style: { colors: '#ffffff66', fontSize: '10px' }, maxWidth: 160 } },
            grid: { borderColor: '#ffffff10' },
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
            chart: { type: 'donut', background: 'transparent' },
            theme: { mode: 'dark' },
            colors: ['#e61919', '#3b82f6', '#22c55e', '#f59e0b'],
            labels: items.map(i => i.tipo || 'Sin tipo'),
            legend: { labels: { colors: '#ffffff88' } },
            dataLabels: { style: { colors: ['#fff'] } },
            tooltip: { theme: 'dark', y: { formatter: v => fmt(v) } },
            plotOptions: { pie: { donut: { size: '65%' } } },
        },
    };
});

// ── STOCK CHARTS ───────────────────────────────────────────
const stockSucursalChart = computed(() => {
    if (!props.reporteStock) return null;
    const items = props.reporteStock.porSucursal;
    return {
        series: [
            { name: 'Disponible', data: items.map(i => i.disponible) },
            { name: 'Reservado',  data: items.map(i => i.reservada) },
        ],
        options: {
            chart: { type: 'bar', toolbar: { show: false }, background: 'transparent', stacked: true },
            theme: { mode: 'dark' },
            colors: ['#22c55e', '#f59e0b'],
            plotOptions: { bar: { borderRadius: 4 } },
            xaxis: { categories: items.map(i => i.nombre), labels: { style: { colors: '#ffffff66' } } },
            yaxis: { labels: { style: { colors: '#ffffff44' } } },
            grid: { borderColor: '#ffffff10' },
            legend: { labels: { colors: '#ffffff88' } },
            tooltip: { theme: 'dark' },
            dataLabels: { enabled: false },
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
            chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
            theme: { mode: 'dark' },
            colors: ['#3b82f6', '#22c55e'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.01 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: items.map(i => i.mes), labels: { style: { colors: '#ffffff44', fontSize: '10px' } } },
            yaxis: { labels: { style: { colors: '#ffffff44' }, formatter: v => '$' + fmtNum(v) } },
            grid: { borderColor: '#ffffff10' },
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
            chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
            theme: { mode: 'dark' },
            colors: ['#3b82f6', '#22c55e'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            xaxis: { categories: items.map(i => i.nombre), labels: { style: { colors: '#ffffff66' } } },
            yaxis: { labels: { style: { colors: '#ffffff44' }, formatter: v => '$' + fmtNum(v) } },
            grid: { borderColor: '#ffffff10' },
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
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter">
                        <span class="text-brand-red not-italic">Reportes</span> & Análisis
                    </h2>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                        Ventas · Stock · Balance financiero
                    </p>
                </div>
            </div>
        </template>

        <div class="px-8 py-8 space-y-6">

            <!-- Filtros globales -->
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Desde</label>
                        <input v-model="desde" type="date"
                            class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-red/50" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Hasta</label>
                        <input v-model="hasta" type="date"
                            class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brand-red/50" />
                    </div>
                    <div class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Sucursal</label>
                        <button type="button" @click="showSucursalDrop = !showSucursalDrop"
                            class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white hover:border-brand-red/50 transition-colors min-w-48">
                            <span>{{ sucursalLabel }}</span>
                            <svg class="w-4 h-4 text-white/30 ml-auto" :class="{ 'rotate-180': showSucursalDrop }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div v-if="showSucursalDrop" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                            <button type="button" @click="selectSucursal('')" class="w-full text-left px-4 py-2.5 text-sm text-white/50 hover:bg-white/5 transition-colors border-b border-white/5">
                                Todas las sucursales
                            </button>
                            <button v-for="s in sucursales" :key="s.id" type="button" @click="selectSucursal(s.id)"
                                class="w-full text-left px-4 py-2.5 text-sm text-white hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                                :class="{ 'text-brand-red': sucursalId == s.id }">
                                {{ s.nombre }}
                            </button>
                        </div>
                        <div v-if="showSucursalDrop" class="fixed inset-0 z-10" @click="showSucursalDrop = false" />
                    </div>
                    <button @click="aplicar" class="btn-primary px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest">
                        Aplicar
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 bg-white/[0.03] border border-white/10 rounded-xl p-1 w-fit">
                <button v-for="t in [{id:'ventas',label:'Ventas'},{id:'stock',label:'Stock'},{id:'balance',label:'Balance'}]"
                    :key="t.id"
                    @click="switchTab(t.id)"
                    class="px-5 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all"
                    :class="activeTab === t.id
                        ? 'bg-brand-red text-white'
                        : 'text-white/30 hover:text-white/60'">
                    {{ t.label }}
                </button>
            </div>

            <!-- ══════════════ TAB: VENTAS ══════════════ -->
            <template v-if="activeTab === 'ventas' && reporteVentas">

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Total Ventas</p>
                        <p class="text-3xl font-black text-white">{{ fmtNum(reporteVentas.totales?.cantidad) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Ingresos</p>
                        <p class="text-2xl font-black text-brand-red">{{ fmt(reporteVentas.totales?.total) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Ticket Promedio</p>
                        <p class="text-2xl font-black text-blue-400">
                            {{ reporteVentas.totales?.cantidad > 0 ? fmt(reporteVentas.totales.total / reporteVentas.totales.cantidad) : '$0' }}
                        </p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Tipos de Venta</p>
                        <p class="text-3xl font-black text-green-400">{{ reporteVentas.porTipo?.length }}</p>
                    </div>
                </div>

                <!-- Gráficas: evolución + donut -->
                <div class="grid lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Evolución de Ingresos</p>
                        <template v-if="ventasDiaChart && reporteVentas.porDia?.length">
                            <VueApexCharts type="area" height="220"
                                :options="ventasDiaChart.options"
                                :series="ventasDiaChart.series" />
                        </template>
                        <p v-else class="text-white/20 text-xs text-center py-16">Sin datos en el período</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Por Tipo de Venta</p>
                        <template v-if="porTipoChart && reporteVentas.porTipo?.length">
                            <VueApexCharts type="donut" height="220"
                                :options="porTipoChart.options"
                                :series="porTipoChart.series" />
                        </template>
                        <p v-else class="text-white/20 text-xs text-center py-16">Sin datos</p>
                    </div>
                </div>

                <!-- Top Productos + Top Clientes -->
                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Top 8 Productos</p>
                        <template v-if="topProductosChart && reporteVentas.topProductos?.length">
                            <VueApexCharts type="bar" height="240"
                                :options="topProductosChart.options"
                                :series="topProductosChart.series" />
                        </template>
                        <p v-else class="text-white/20 text-xs text-center py-16">Sin datos</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Top Clientes</p>
                        <div v-if="reporteVentas.topClientes?.length" class="space-y-2 overflow-y-auto max-h-60">
                            <div v-for="(c, i) in reporteVentas.topClientes" :key="i"
                                class="flex items-center justify-between px-3 py-2 rounded-xl bg-white/5 hover:bg-white/[0.07] transition-colors">
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] font-black text-white/20 w-5 text-right">{{ i+1 }}</span>
                                    <div>
                                        <p class="text-sm font-bold text-white">{{ c.nombre || 'Sin nombre' }}</p>
                                        <p class="text-[10px] text-white/30">{{ c.compras }} compras</p>
                                    </div>
                                </div>
                                <span class="text-sm font-black text-brand-red">{{ fmt(c.total) }}</span>
                            </div>
                        </div>
                        <p v-else class="text-white/20 text-xs text-center py-16">Sin datos</p>
                    </div>
                </div>

            </template>

            <!-- ══════════════ TAB: STOCK ══════════════ -->
            <template v-if="activeTab === 'stock' && reporteStock">

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Total Unidades</p>
                        <p class="text-3xl font-black text-white">{{ fmtNum(reporteStock.totales.total_unidades) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Títulos Únicos</p>
                        <p class="text-3xl font-black text-blue-400">{{ fmtNum(reporteStock.totales.titulos_unicos) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Sin Stock</p>
                        <p class="text-3xl font-black text-brand-red">{{ reporteStock.totales.sin_stock }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Stock Bajo (≤5)</p>
                        <p class="text-3xl font-black text-yellow-400">{{ reporteStock.totales.stock_bajo }}</p>
                    </div>
                </div>

                <!-- Gráfica por sucursal -->
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Stock por Sucursal</p>
                    <template v-if="stockSucursalChart && reporteStock.porSucursal?.length">
                        <VueApexCharts type="bar" height="200"
                            :options="stockSucursalChart.options"
                            :series="stockSucursalChart.series" />
                    </template>
                    <p v-else class="text-white/20 text-xs text-center py-8">Sin datos</p>
                </div>

                <!-- Tablas: sin stock + bajo -->
                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-brand-red">Sin Stock</p>
                        </div>
                        <div v-if="reporteStock.sinStock?.length" class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                            <div v-for="(item, i) in reporteStock.sinStock" :key="i"
                                class="flex items-center justify-between px-6 py-3 hover:bg-white/[0.02]">
                                <div>
                                    <p class="text-sm font-bold text-white">{{ item.titulo.length > 30 ? item.titulo.slice(0,30)+'…' : item.titulo }}</p>
                                    <p class="text-[10px] text-white/30">{{ item.sucursal }}</p>
                                </div>
                                <span class="text-xs font-black text-brand-red px-2 py-0.5 rounded-full bg-brand-red/10 border border-brand-red/20">0</span>
                            </div>
                        </div>
                        <p v-else class="text-white/20 text-xs text-center py-8">Todo con stock</p>
                    </div>

                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-yellow-400">Stock Bajo (1-5 unid.)</p>
                        </div>
                        <div v-if="reporteStock.stockBajo?.length" class="divide-y divide-white/5 max-h-72 overflow-y-auto">
                            <div v-for="(item, i) in reporteStock.stockBajo" :key="i"
                                class="flex items-center justify-between px-6 py-3 hover:bg-white/[0.02]">
                                <div>
                                    <p class="text-sm font-bold text-white">{{ item.titulo.length > 30 ? item.titulo.slice(0,30)+'…' : item.titulo }}</p>
                                    <p class="text-[10px] text-white/30">{{ item.sucursal }}</p>
                                </div>
                                <span class="text-xs font-black text-yellow-400 px-2 py-0.5 rounded-full bg-yellow-400/10 border border-yellow-400/20">{{ item.disponible }}</span>
                            </div>
                        </div>
                        <p v-else class="text-white/20 text-xs text-center py-8">Sin títulos con stock bajo</p>
                    </div>
                </div>

            </template>

            <!-- ══════════════ TAB: BALANCE ══════════════ -->
            <template v-if="activeTab === 'balance' && reporteBalance">

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Ingresos Totales</p>
                        <p class="text-2xl font-black text-blue-400">{{ fmt(reporteBalance.totalIngresos) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Costo (COGS)</p>
                        <p class="text-2xl font-black text-brand-red">{{ fmt(reporteBalance.totalCogs) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Ganancia Neta</p>
                        <p class="text-2xl font-black text-green-400">{{ fmt(reporteBalance.totalRentabilidad) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Ventas Realizadas</p>
                        <p class="text-2xl font-black text-white">{{ fmtNum(reporteBalance.totalVentas) }}</p>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Ticket Promedio</p>
                        <p class="text-2xl font-black text-white/50">{{ fmt(reporteBalance.ticketPromedio) }}</p>
                    </div>
                </div>

                <!-- Evolución mensual -->
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Ingresos por Mes</p>
                    <template v-if="balanceMesChart && reporteBalance.porMes?.length">
                        <VueApexCharts type="area" height="240"
                            :options="balanceMesChart.options"
                            :series="balanceMesChart.series" />
                    </template>
                    <p v-else class="text-white/20 text-xs text-center py-16">Sin datos en el período</p>
                </div>

                <!-- Por sucursal -->
                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-4">Ingresos por Sucursal</p>
                        <template v-if="balanceSucursalChart && reporteBalance.porSucursal?.length">
                            <VueApexCharts type="bar" height="220"
                                :options="balanceSucursalChart.options"
                                :series="balanceSucursalChart.series" />
                        </template>
                        <p v-else class="text-white/20 text-xs text-center py-8">Sin datos</p>
                    </div>

                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden">
                        <div class="px-6 py-4 border-b border-white/5">
                            <p class="text-[10px] font-black uppercase tracking-widest text-white/30">Detalle por Sucursal</p>
                        </div>
                        <div v-if="reporteBalance.porSucursal?.length" class="divide-y divide-white/5">
                            <div v-for="(s, i) in reporteBalance.porSucursal" :key="i"
                                class="flex items-center justify-between px-6 py-4 hover:bg-white/[0.02]">
                                <div>
                                    <p class="text-sm font-bold text-white">{{ s.nombre }}</p>
                                    <p class="text-[10px] text-white/30">{{ s.ventas }} ventas</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-black text-blue-400">{{ fmt(s.ingresos) }}</p>
                                    <p class="text-[10px] font-black text-green-400 mt-0.5">Rent. {{ fmt(s.rentabilidad) }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-white/20 text-xs text-center py-8">Sin datos</p>
                    </div>
                </div>

            </template>

        </div>
    </AuthenticatedLayout>
</template>
