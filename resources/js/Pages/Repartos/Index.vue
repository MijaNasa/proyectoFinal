<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    rutas:        Object,
    repartidores: Array,
    stats:        Object,
    filters:      Object,
});

const search       = ref(props.filters.search       || '');
const desde        = ref(props.filters.desde        || '');
const hasta        = ref(props.filters.hasta        || '');
const estadoFiltro = ref(props.filters.estado       || '');

const showModal          = ref(false);
const showRepartidorDrop = ref(false);

const repartidorLabel = computed(() => {
    if (!form.repartidor_id) return 'Sin asignar';
    const r = props.repartidores.find(r => r.id == form.repartidor_id);
    return r ? `${r.user?.name ?? ''} ${r.user?.apellido ?? ''}`.trim() : 'Sin asignar';
});

const selectRepartidor = (id) => {
    form.repartidor_id = id;
    showRepartidorDrop.value = false;
};

const form = useForm({
    repartidor_id: '',
});

const crearNuevaRutaDirecta = () => {
    form.post(route('rutas-reparto.store'));
};

const aplicarFiltros = () => {
    router.get(route('rutas-reparto.index'), {
        search: search.value || undefined,
        desde: desde.value || undefined,
        hasta: hasta.value || undefined,
        estado: estadoFiltro.value || undefined,
    }, { 
        preserveState: true,
        replace: true
    });
};

const limpiarFiltros = () => {
    search.value = '';
    desde.value = '';
    hasta.value = '';
    estadoFiltro.value = '';
    aplicarFiltros();
};

let searchTimeout = null;
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        aplicarFiltros();
    }, 300);
});

const eliminar = (ruta) => {
    if (ruta.estado === 'finalizada') return;
    Swal.fire({
        title: '¿Eliminar ruta?',
        text: `"${ruta.nombre}" será eliminada permanentemente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#111',
        color: '#fff',
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('rutas-reparto.destroy', ruta.id));
        }
    });
};

const formatNombreRuta = (nombre) => {
    if (!nombre) return 'Envío #0000';
    const match = String(nombre).match(/\d+/);
    if (match) {
        const num = match[0].padStart(4, '0');
        return `Envío #${num}`;
    }
    return nombre;
};

const contarEntregadas = (paradas) => (paradas || []).filter(p => p.estado === 'entregada').length;

const formatFecha = (f) => {
    if (!f) return '—';
    const iso = String(f).slice(0, 10) + 'T00:00:00';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return String(f).slice(0, 10);
    const str = d.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
    return str.replace('.', '');
};
</script>

<template>
    <Head title="Repartos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter">
                        Gestión de <span class="text-brand-red not-italic">Repartos</span>
                    </h2>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                        Planificación y seguimiento de entregas
                    </p>
                </div>
                <button @click="crearNuevaRutaDirecta" :disabled="form.processing" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ form.processing ? 'Creando...' : 'Nueva Ruta' }}
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">

            <!-- Barra de Filtros -->
            <div class="card p-4 border-white/5 space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                    <!-- Buscador -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Búsqueda</label>
                        <div class="relative w-full">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-white/40">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Ruta o repartidor..."
                                class="w-full bg-black/40 border border-white/10 rounded-lg pl-9 pr-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 placeholder-white/30"
                            />
                        </div>
                    </div>

                    <!-- Desde -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Desde</label>
                        <input
                            v-model="desde"
                            @change="aplicarFiltros"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            type="date"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer"
                        />
                    </div>

                    <!-- Hasta -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Hasta</label>
                        <input
                            v-model="hasta"
                            @change="aplicarFiltros"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            type="date"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer"
                        />
                    </div>

                    <!-- Estado -->
                    <div class="w-full">
                        <label class="block text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5">Estado</label>
                        <select v-model="estadoFiltro" @change="aplicarFiltros" class="w-full bg-black/40 border border-white/10 rounded-lg px-3.5 py-2 text-xs font-medium text-white/90 focus:outline-none focus:border-brand-red/50 cursor-pointer">
                            <option value="" class="bg-[#1a1a1a] text-white/60">Todas las rutas</option>
                            <option value="pendiente" class="bg-[#1a1a1a] text-white">Pendiente</option>
                            <option value="activa" class="bg-[#1a1a1a] text-white">En Curso</option>
                            <option value="finalizada" class="bg-[#1a1a1a] text-white">Finalizada</option>
                        </select>
                    </div>
                </div>
                <div v-if="search || desde || hasta || estadoFiltro" class="flex justify-end pt-1">
                    <button @click="limpiarFiltros" class="text-[10px] font-black uppercase tracking-wider text-brand-red hover:underline cursor-pointer">
                        Limpiar Filtros
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="card p-0 overflow-hidden">
                <table class="w-full text-left table-fixed">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/[0.01] uppercase text-xs font-bold tracking-wider text-white/50">
                            <th class="p-4 w-[25%]">Ruta</th>
                            <th class="p-4 w-[20%]">Fecha</th>
                            <th class="p-4 w-[25%]">Repartidor</th>
                            <th class="p-4 w-[20%] text-center">Paradas</th>
                            <th class="p-4 w-[18%] text-center">Estado</th>
                            <th class="p-4 w-[12%] text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-if="!rutas.data.length">
                            <td colspan="6" class="text-center py-12 text-white/20 font-bold uppercase tracking-widest text-xs">
                                No hay rutas registradas
                            </td>
                        </tr>
                        <tr
                            v-for="ruta in rutas.data"
                            :key="ruta.id"
                            class="hover:bg-white/[0.02] transition-colors"
                        >
                            <td class="p-4">
                                <p class="text-sm font-bold text-white">{{ formatNombreRuta(ruta.nombre) }}</p>
                                <p class="text-xs text-white/50 font-medium">
                                    {{ (ruta.paradas?.length ?? 0) === 1 ? '1 parada' : `${ruta.paradas?.length ?? 0} paradas` }}
                                </p>
                            </td>
                            <td class="p-4 text-sm font-bold text-white">
                                {{ formatFecha(ruta.fecha) }}
                            </td>
                            <td class="p-4 text-sm font-bold text-white">
                                {{ ruta.repartidor?.user ? `${ruta.repartidor.user.name} ${ruta.repartidor.user.apellido ?? ''}` : 'Sin asignar' }}
                            </td>
                            <td class="p-4 text-center">
                                <div v-if="ruta.paradas?.length" class="flex flex-col items-center gap-1">
                                    <span class="text-xs font-bold text-emerald-400/90">
                                        {{ contarEntregadas(ruta.paradas) }}/{{ ruta.paradas.length }} entregadas
                                    </span>
                                    <div class="w-20 h-1.5 bg-white/10 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-emerald-400 rounded-full transition-all"
                                            :style="{ width: (contarEntregadas(ruta.paradas) / ruta.paradas.length * 100) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <span v-else class="text-white/30 text-xs font-medium">Sin paradas</span>
                            </td>
                            <td class="p-4 text-center">
                                <span class="bg-[#1a1a1a] border border-white/10 text-white font-bold text-xs rounded-full px-3 py-1 inline-flex items-center gap-1.5 shadow-sm">
                                    <span
                                        class="w-2 h-2 rounded-full"
                                        :class="{
                                            'bg-amber-400': ruta.estado === 'pendiente',
                                            'bg-sky-400': ruta.estado === 'activa',
                                            'bg-emerald-400': ruta.estado === 'finalizada'
                                        }"
                                    ></span>
                                    {{ ruta.estado === 'pendiente' ? 'Pendiente' : (ruta.estado === 'activa' ? 'En Curso' : 'Finalizada') }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="route('rutas-reparto.show', ruta.id)"
                                        class="p-1.5 text-white/40 hover:text-white transition-colors hover:bg-white/5 rounded-lg"
                                        title="Ver detalle de ruta"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button
                                        v-if="ruta.estado !== 'finalizada'"
                                        @click="eliminar(ruta)"
                                        class="p-1.5 text-white/40 hover:text-brand-red transition-colors hover:bg-white/5 rounded-lg"
                                        title="Eliminar ruta"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="rutas.links?.length > 3" class="flex justify-center gap-2">
                <Link
                    v-for="link in rutas.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="px-4 py-2 rounded-lg border border-white/10 text-xs font-bold uppercase transition-all"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }"
                >{{ decodeLabel(link.label) }}</Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

