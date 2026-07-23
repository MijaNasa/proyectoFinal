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
            <div class="flex justify-between items-end">
                <div>
                    <h2 class="text-3xl font-black uppercase tracking-tighter text-white leading-none">Gestión de <span class="text-brand-red not-italic">Suscripciones</span></h2>
                    <p class="text-xs font-bold uppercase tracking-widest text-white/30 mt-2">Métricas y listado de fidelización</p>
                </div>
            </div>
        </template>

        <div class="p-8">
            <!-- Top Series -->
            <div class="mb-10" v-if="topSeries && topSeries.length">
                <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-brand-red mb-4">Top Series con más suscriptores</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div v-for="(top, index) in topSeries" :key="top.libro_master_id" class="bg-white/[0.02] border border-white/5 rounded-2xl p-4 flex flex-col items-center text-center relative group overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-16 h-16 bg-brand-red/10 rounded-full blur-xl group-hover:bg-brand-red/20 transition-all"></div>
                        <div class="text-[10px] font-black text-white/40 mb-2">#{{ index + 1 }}</div>
                        <img v-if="top.serie.portada_url" :src="top.serie.portada_url" class="w-16 h-24 object-cover rounded shadow-lg mb-3" />
                        <div v-else class="w-16 h-24 bg-white/5 rounded flex items-center justify-center mb-3 text-[8px] uppercase font-black text-white/20">Sin Foto</div>
                        <div class="font-black text-[11px] uppercase leading-tight line-clamp-2 text-white/80 group-hover:text-brand-red transition-colors min-h-[2.5rem]">{{ top.serie.titulo }}</div>
                        <div class="mt-2 flex items-baseline justify-center gap-1.5">
                            <span class="text-3xl font-black text-white italic tracking-tighter leading-none">{{ top.total }}</span>
                            <span class="text-[9px] uppercase tracking-widest text-white/30 font-bold not-italic">Activos</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controles y Filtros -->
            <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 mb-6">
                <div class="flex flex-col md:flex-row gap-4 items-end w-full">
                    <div class="w-full md:flex-1">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-white/40 block mb-2">Buscar cliente o serie</label>
                        <div class="relative">
                            <input v-model="search" type="text" placeholder="Ej. Batman, Perez..." class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white placeholder-white/20 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all">
                        </div>
                    </div>
                    <div class="w-full md:w-64">
                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-white/40 block mb-2">Estado</label>
                        <select v-model="estadoFiltro" class="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all appearance-none">
                            <option value="">Todos</option>
                            <option value="activa">Activas</option>
                            <option value="pausada">Pausadas</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla de Suscripciones -->
            <div class="bg-white/[0.02] border border-white/5 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5 bg-white/[0.02]">
                                <th class="p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/40">Cliente</th>
                                <th class="p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/40">Obra Suscripta</th>
                                <th class="p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/40">Sucursal</th>
                                <th class="p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/40">Alta</th>
                                <th class="p-4 text-[10px] font-black uppercase tracking-[0.2em] text-white/40">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="sub in suscripciones.data" :key="sub.id" class="hover:bg-white/[0.02] transition-colors group">
                                <td class="p-4">
                                    <Link :href="route('clientes.index', { search: sub.cliente.user.email })" class="block">
                                        <div class="font-black text-xs uppercase tracking-tight group-hover:text-brand-red transition-colors">{{ sub.cliente.user.name }} {{ sub.cliente.user.apellido }}</div>
                                        <div class="text-[9px] font-bold text-white/30 uppercase tracking-widest">{{ sub.cliente.user.email }}</div>
                                    </Link>
                                </td>
                                <td class="p-4">
                                    <div class="font-black text-xs uppercase tracking-tight text-white/80">{{ sub.serie.titulo }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-[10px] font-bold uppercase text-white/50 tracking-widest">{{ sub.sucursal.nombre }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-[10px] font-bold text-white/40 tracking-widest">{{ formatDate(sub.created_at) }}</div>
                                </td>
                                <td class="p-4">
                                    <span :class="[
                                        'px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded',
                                        sub.estado === 'activa' ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'
                                    ]">
                                        {{ sub.estado }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!suscripciones.data.length">
                                <td colspan="5" class="p-8 text-center text-white/20 text-xs font-bold uppercase tracking-widest">
                                    No se encontraron suscripciones
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="p-4 border-t border-white/5 bg-white/[0.02] flex items-center justify-between" v-if="suscripciones.links?.length > 3">
                    <span class="text-[10px] font-bold uppercase text-white/30 tracking-widest">
                        Mostrando {{ suscripciones.from }} a {{ suscripciones.to }} de {{ suscripciones.total }}
                    </span>
                    <div class="flex gap-1">
                        <Link v-for="link in suscripciones.links" :key="link.label" :href="link.url || '#'" 
                              class="px-3 py-1 rounded border border-white/5 transition-all text-[10px] font-black uppercase tracking-tighter" 
                              :class="{'bg-brand-red text-white border-brand-red': link.active, 'text-white/20': !link.url, 'text-white/60 hover:bg-white/10': link.url && !link.active}">
                            {{ decodeLabel(link.label) }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
