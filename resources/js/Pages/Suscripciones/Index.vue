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
            <div class="flex items-center justify-between min-h-[42px] w-full">
                <h2 class="text-3xl font-black leading-none text-white tracking-tighter uppercase">Gestión de <span class="text-brand-red not-italic">Suscripciones</span></h2>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">

                <!-- Top Series -->
                <div v-if="topSeries && topSeries.length" class="text-center">
                    <h3 class="text-xs font-black uppercase tracking-widest text-white mb-4 text-center">Top Series con más suscriptores</h3>
                    <div class="flex flex-wrap justify-center gap-4">
                        <div v-for="(top, index) in topSeries" :key="top.libro_master_id"
                            class="w-48 bg-[#141414] border border-white/10 rounded-2xl p-4 flex flex-col justify-between items-center text-center relative group overflow-hidden shadow-xl">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-brand-red/10 rounded-full blur-xl group-hover:bg-brand-red/20 transition-all"></div>
                            
                            <div class="w-full flex flex-col items-center text-center">
                                <div class="text-[10px] font-black text-white/40 mb-2">#{{ index + 1 }}</div>
                                <img :src="top.serie.portada_url || '/images/no-cover.png'" class="w-20 h-28 object-cover rounded-lg shadow-lg mb-3 border border-white/10 mx-auto" />
                                <div class="font-black text-xs uppercase leading-tight line-clamp-2 text-white/90 group-hover:text-brand-red transition-colors min-h-[2.5rem] text-center w-full">{{ top.serie.titulo }}</div>
                            </div>

                            <div class="mt-3 flex items-baseline justify-center gap-1.5 w-full">
                                <span class="text-3xl font-black text-white font-mono tracking-tight leading-none">{{ top.total }}</span>
                                <span class="text-[10px] uppercase tracking-widest text-white/40 font-bold">Activos</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controles y Filtros Reactivos -->
                <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                    <div class="relative w-full flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-white/40">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar por cliente o serie..."
                            class="w-full bg-[#141414] border border-white/10 rounded-xl pl-11 pr-4 py-3 text-xs font-bold text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 transition-all"
                        />
                    </div>

                    <!-- Filtro Estado -->
                    <div class="w-full sm:w-64">
                        <select
                            v-model="estadoFiltro"
                            class="w-full bg-[#141414] border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer uppercase"
                        >
                            <option value="" class="bg-[#1A1A1A]">Todos los estados</option>
                            <option value="activa" class="bg-[#1A1A1A]">Activas</option>
                            <option value="pausada" class="bg-[#1A1A1A]">Pausadas</option>
                        </select>
                    </div>
                </div>

                <!-- Tabla de Suscripciones -->
                <div class="bg-[#141414] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/10">
                                <th class="p-4">Cliente</th>
                                <th class="p-4">Obra Suscripta</th>
                                <th class="p-4">Sucursal</th>
                                <th class="p-4">Alta</th>
                                <th class="p-4 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <tr v-if="!suscripciones.data.length">
                                <td colspan="5" class="p-12 text-center text-white/30 italic">
                                    No se encontraron suscripciones.
                                </td>
                            </tr>
                            <tr v-for="sub in suscripciones.data" :key="sub.id" class="hover:bg-white/[0.01] transition-colors group">
                                <td class="p-4">
                                    <Link :href="route('clientes.index', { search: sub.cliente.user.email })" class="block">
                                        <div class="font-bold text-sm text-white group-hover:text-brand-red transition-colors">{{ sub.cliente.user.name }} {{ sub.cliente.user.apellido }}</div>
                                        <div class="text-xs text-white/40 font-medium">{{ sub.cliente.user.email }}</div>
                                    </Link>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-sm text-white/90">{{ sub.serie.titulo }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm font-medium text-white/70">{{ sub.sucursal.nombre }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-sm font-medium text-white/70">{{ formatDate(sub.created_at) }}</div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#1E1E1E] border border-white/5 shadow-sm">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="sub.estado === 'activa' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                                        <span class="text-xs font-black uppercase tracking-wider text-white">
                                            {{ sub.estado }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="suscripciones.last_page > 1" class="flex justify-center gap-2 pt-2">
                    <Link v-for="link in suscripciones.links" :key="link.label"
                        :href="link.url ?? '#'"
                        class="px-3 py-1.5 rounded-xl border border-white/5 text-xs font-bold uppercase transition-all"
                        :class="link.active
                            ? 'bg-brand-red text-white border-brand-red'
                            : link.url
                                ? 'text-white/40 hover:text-white hover:bg-white/5'
                                : 'text-white/20 cursor-default'"
                        v-html="decodeLabel(link.label)" />
                </div>
        </div>
    </AuthenticatedLayout>
</template>
