<script setup>
import { ref, watch } from 'vue';
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

const showModal = ref(false);

const form = useForm({
    repartidor_id: '',
});

const abrirNuevaRuta = () => {
    form.reset();
    showModal.value = true;
};

const cerrarNuevaRuta = () => {
    showModal.value = false;
    form.reset();
};

const crearNuevaRutaDirecta = () => {
    form.post(route('rutas-reparto.store'), {
        onSuccess: () => { showModal.value = false; },
    });
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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-repartos',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const eliminar = (ruta) => {
    if (ruta.estado === 'finalizada') return;
    darkSwal.fire({
        title: '¿Eliminar ruta?',
        text: `"${ruta.nombre}" será eliminada permanentemente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
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
            <div class="flex items-center justify-between w-full page-repartos">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">RUTAS DE REPARTO</h2>
                </div>
                <button
                    @click="abrirNuevaRuta"
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>NUEVA RUTA</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-repartos">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Filter Bar Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 shadow-xl space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                        <!-- Buscador -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Búsqueda</label>
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Ruta o repartidor..."
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium"
                                />
                            </div>
                        </div>

                        <!-- Desde -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Desde</label>
                            <input
                                v-model="desde"
                                @change="aplicarFiltros"
                                @click="$event.target.showPicker && $event.target.showPicker()"
                                type="date"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                            />
                        </div>

                        <!-- Hasta -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Hasta</label>
                            <input
                                v-model="hasta"
                                @change="aplicarFiltros"
                                @click="$event.target.showPicker && $event.target.showPicker()"
                                type="date"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                            />
                        </div>

                        <!-- Estado -->
                        <div class="w-full">
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Estado</label>
                            <select v-model="estadoFiltro" @change="aplicarFiltros" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3.5 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                <option value="" class="bg-[#131316] text-zinc-400">Todas las rutas</option>
                                <option value="pendiente" class="bg-[#131316] text-white">Pendiente</option>
                                <option value="activa" class="bg-[#131316] text-white">En Curso</option>
                                <option value="finalizada" class="bg-[#131316] text-white">Finalizada</option>
                            </select>
                        </div>
                    </div>
                    <div v-if="search || desde || hasta || estadoFiltro" class="flex justify-end pt-1">
                        <button @click="limpiarFiltros" class="text-xs font-semibold uppercase tracking-wider text-rose-400 hover:underline cursor-pointer">
                            Limpiar Filtros
                        </button>
                    </div>
                </div>

                <!-- Tabla Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left table-fixed">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 w-[25%]">Ruta</th>
                                    <th class="p-4 w-[20%]">Fecha</th>
                                    <th class="p-4 w-[25%]">Repartidor</th>
                                    <th class="p-4 w-[20%] text-center">Paradas</th>
                                    <th class="p-4 w-[18%] text-center">Estado</th>
                                    <th class="p-4 w-[12%] text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-if="!rutas.data.length">
                                    <td colspan="6" class="text-center p-12 text-zinc-500 italic">
                                        No hay rutas registradas
                                    </td>
                                </tr>
                                <tr
                                    v-for="ruta in rutas.data"
                                    :key="ruta.id"
                                    class="hover:bg-white/[0.02] transition-colors"
                                >
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight">{{ formatNombreRuta(ruta.nombre) }}</div>
                                        <div class="text-xs text-zinc-400 font-medium mt-0.5">
                                            {{ (ruta.paradas?.length ?? 0) === 1 ? '1 parada' : `${ruta.paradas?.length ?? 0} paradas` }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm font-semibold text-zinc-300">
                                        {{ formatFecha(ruta.fecha) }}
                                    </td>
                                    <td class="p-4 text-sm font-bold text-white capitalize">
                                        {{ ruta.repartidor?.user ? `${ruta.repartidor.user.name} ${ruta.repartidor.user.apellido ?? ''}` : 'Sin asignar' }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <div v-if="ruta.paradas?.length" class="flex flex-col items-center gap-1.5">
                                            <span class="text-xs font-semibold text-emerald-400">
                                                {{ contarEntregadas(ruta.paradas) }}/{{ ruta.paradas.length }} entregadas
                                            </span>
                                            <div class="w-24 h-1.5 bg-white/5 rounded-full overflow-hidden border border-white/5">
                                                <div
                                                    class="h-full bg-emerald-400 rounded-full transition-all"
                                                    :style="{ width: (contarEntregadas(ruta.paradas) / ruta.paradas.length * 100) + '%' }"
                                                ></div>
                                            </div>
                                        </div>
                                        <span v-else class="text-zinc-500 text-xs font-medium">Sin paradas</span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                            <span
                                                class="w-2 h-2 rounded-full shrink-0"
                                                :class="{
                                                    'bg-amber-400': ruta.estado === 'pendiente',
                                                    'bg-sky-400': ruta.estado === 'activa',
                                                    'bg-emerald-400': ruta.estado === 'finalizada'
                                                }"
                                            ></span>
                                            <span>{{ ruta.estado === 'pendiente' ? 'Pendiente' : (ruta.estado === 'activa' ? 'En Curso' : 'Finalizada') }}</span>
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <Link
                                                :href="route('rutas-reparto.show', ruta.id)"
                                                class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all"
                                                title="Ver detalle de ruta"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>
                                            <button
                                                v-if="ruta.estado !== 'finalizada'"
                                                @click="eliminar(ruta)"
                                                class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                                title="Eliminar ruta"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="rutas.links?.length > 3" class="flex justify-center gap-2 mt-6">
                    <Link
                        v-for="link in rutas.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- Modal: Nueva Ruta -->
        <div v-if="showModal" class="page-repartos">
            <div class="fixed inset-0 z-[110] bg-black/90 backdrop-blur-md" @click="cerrarNuevaRuta"></div>
            <div class="fixed inset-0 z-[120] flex items-center justify-center p-4 pointer-events-none">
                <div class="relative w-full max-w-md bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                    <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Nueva Ruta de Reparto</h3>
                        <button @click="cerrarNuevaRuta" class="text-zinc-400 hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <form @submit.prevent="crearNuevaRutaDirecta" class="p-6 space-y-4">
                        <p class="text-xs text-zinc-400 leading-relaxed">
                            Se crea como pendiente. Podés dejarla sin repartidor y asignar todo (repartidor y pedidos a entregar) después, desde el detalle de la ruta.
                        </p>
                        <div>
                            <label class="block text-xs font-semibold text-zinc-400 mb-1.5">Repartidor</label>
                            <select v-model="form.repartidor_id" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30">
                                <option value="" class="bg-black text-zinc-300">Sin asignar (lo hago después)</option>
                                <option v-for="r in props.repartidores" :key="r.id" :value="r.id" class="bg-black text-zinc-300">{{ r.user?.name }} {{ r.user?.apellido }}</option>
                            </select>
                            <p v-if="form.errors.repartidor_id" class="text-red-400 text-xs mt-1">{{ form.errors.repartidor_id }}</p>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="cerrarNuevaRuta" class="flex-1 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="flex-1 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md disabled:opacity-50">
                                {{ form.processing ? 'Creando...' : 'Crear Ruta' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-repartos,
.page-repartos * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
