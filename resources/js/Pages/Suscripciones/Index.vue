<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
};

const props = defineProps({
    suscripciones: Object,
    topSeries: Array,
    filters: Object
});

const search = ref(props.filters.search || '');
const estadoFiltro = ref(props.filters.estado || '');

watch([search, estadoFiltro], debounce(([newSearch, newEstado]) => {
    router.get(route('suscripciones.index'), {
        search: newSearch,
        estado: newEstado
    }, { preserveState: true, preserveScroll: true, replace: true });
}, 300));

const decodeLabel = (label) => {
    if (!label) return '';
    return label.replace('&laquo;', '«').replace('&raquo;', '»').replace('Previous', 'Ant').replace('Next', 'Sig');
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    });
};
</script>

<template>
    <Head title="Suscripciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-suscripciones">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">GESTIÓN DE SUSCRIPCIONES</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-suscripciones">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Top Series con más suscriptores -->
                <div v-if="topSeries && topSeries.length" class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 text-center">Top Series con más suscriptores</h3>
                    <div class="flex flex-wrap justify-center gap-4">
                        <div v-for="(top, index) in topSeries" :key="top.libro_master_id"
                            class="w-48 bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col justify-between items-center text-center relative group overflow-hidden shadow-xl hover:border-white/10 transition-all">
                            
                            <div class="w-full flex flex-col items-center text-center">
                                <div class="text-xs font-bold text-zinc-500 mb-2">#{{ index + 1 }}</div>
                                <img :src="top.serie.portada_url || '/images/no-cover.png'" class="w-20 h-28 object-cover rounded-xl shadow-md mb-3 border border-white/5 mx-auto" />
                                <div class="font-bold text-xs leading-tight line-clamp-2 text-white group-hover:text-zinc-200 transition-colors min-h-[2.5rem] text-center w-full">{{ top.serie.titulo }}</div>
                            </div>

                            <div class="mt-3 flex items-baseline justify-center gap-1.5 w-full">
                                <span class="text-2xl font-bold text-white font-mono tracking-tight leading-none">{{ top.total }}</span>
                                <span class="text-xs uppercase tracking-wider text-zinc-400 font-semibold">Activos</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controles y Filtros Reactivos -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col sm:flex-row gap-4 items-center justify-between shadow-xl">
                    <div class="relative w-full flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por cliente o serie..."
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        />
                    </div>

                    <!-- Filtro Estado -->
                    <div class="w-full sm:w-64">
                        <select
                            v-model="estadoFiltro"
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                        >
                            <option value="" class="bg-[#131316] text-zinc-400">Todos los estados</option>
                            <option value="activa" class="bg-[#131316] text-white">Activas</option>
                            <option value="pausada" class="bg-[#131316] text-white">Pausadas</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla de Suscripciones -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4">Cliente</th>
                                    <th class="p-4">Obra Suscripta</th>
                                    <th class="p-4">Tomo Inicio</th>
                                    <th class="p-4">Sucursal</th>
                                    <th class="p-4">Alta</th>
                                    <th class="p-4 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-if="!suscripciones.data.length">
                                    <td colspan="6" class="p-12 text-center text-zinc-500 italic">
                                        No se encontraron suscripciones.
                                    </td>
                                </tr>
                                <tr v-for="sub in suscripciones.data" :key="sub.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4">
                                        <Link :href="route('clientes.index', { search: sub.cliente.user.email })" class="block">
                                            <div class="font-bold text-white tracking-tight group-hover:text-zinc-200 transition-colors">{{ sub.cliente.user.name }} {{ sub.cliente.user.apellido }}</div>
                                            <div class="text-xs text-zinc-400 font-medium mt-0.5">{{ sub.cliente.user.email }}</div>
                                        </Link>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-white capitalize">{{ sub.serie.titulo }}</div>
                                    </td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-xs font-mono font-bold text-white">
                                            Desde Tomo #{{ sub.tomo_inicio || 1 }}
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-xs font-semibold text-zinc-300">{{ sub.sucursal.nombre }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-xs font-medium text-zinc-400">{{ formatDate(sub.created_at) }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="sub.estado === 'activa' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                                            <span class="capitalize">{{ sub.estado }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="suscripciones.last_page > 1" class="flex justify-center gap-2 mt-6">
                    <Link v-for="link in suscripciones.links" :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                        :class="link.active
                            ? 'bg-white text-black border-white shadow-md'
                            : link.url
                                ? 'text-zinc-500 hover:text-white bg-white/5'
                                : 'text-zinc-600 cursor-not-allowed'"
                        v-html="decodeLabel(link.label)" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-suscripciones,
.page-suscripciones * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
