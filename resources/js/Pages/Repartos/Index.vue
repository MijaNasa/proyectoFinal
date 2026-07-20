<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    rutas:        Object,
    repartidores: Array,
    stats:        Object,
    filters:      Object,
});

const search = ref(props.filters.search || '');
const fecha  = ref(props.filters.fecha  || '');

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
    nombre:        '',
    fecha:         new Date().toISOString().slice(0, 10),
    repartidor_id: '',
});

const submitSearch = () => {
    router.get(route('rutas-reparto.index'), { search: search.value, fecha: fecha.value }, { preserveState: true });
};

const submitCrear = () => {
    form.post(route('rutas-reparto.store'), {
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
};

const eliminar = (ruta) => {
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

const estadoParada = {
    pendiente:   { label: 'Pendiente',   color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/30' },
    'en camino': { label: 'En camino',   color: 'text-blue-400 bg-blue-400/10 border-blue-400/30' },
    entregada:   { label: 'Entregada',   color: 'text-green-400 bg-green-400/10 border-green-400/30' },
    fallida:     { label: 'Fallida',     color: 'text-red-400 bg-red-400/10 border-red-400/30' },
};

const formatFecha = (f) =>
    new Date(f + 'T00:00:00').toLocaleDateString('es-AR', { weekday: 'short', day: '2-digit', month: 'short' });

const contarEstados = (paradas) =>
    paradas.reduce((acc, p) => { acc[p.estado] = (acc[p.estado] || 0) + 1; return acc; }, {});
</script>

<template>
    <Head title="Repartos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter">
                        Gestión de <span class="text-brand-red italic">Repartos</span>
                    </h2>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                        Planificación y seguimiento de entregas
                    </p>
                </div>
                <button @click="showModal = true" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Nueva Ruta
                </button>
            </div>
        </template>

        <div class="px-8 py-8 space-y-8">

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Total Rutas</p>
                    <p class="text-3xl font-black text-white">{{ stats.total }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Activas</p>
                    <p class="text-3xl font-black text-green-400">{{ stats.activas }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Paradas Hoy</p>
                    <p class="text-3xl font-black text-blue-400">{{ stats.paradas_hoy }}</p>
                </div>
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Entregadas Hoy</p>
                    <p class="text-3xl font-black text-brand-red">{{ stats.entregadas_hoy }}</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-col sm:flex-row gap-3">
                <input
                    v-model="search"
                    @keyup.enter="submitSearch"
                    type="text"
                    placeholder="Buscar por nombre..."
                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"
                />
                <input
                    v-model="fecha"
                    @change="submitSearch"
                    type="date"
                    class="bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50"
                />
                <button @click="submitSearch" class="btn-primary px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                    Filtrar
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white/[0.02] border border-white/10 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-white/30">
                            <th class="text-left px-6 py-4">Ruta</th>
                            <th class="text-left px-6 py-4">Fecha</th>
                            <th class="text-left px-6 py-4">Repartidor</th>
                            <th class="text-center px-6 py-4">Paradas</th>
                            <th class="text-center px-6 py-4">Estado</th>
                            <th class="text-right px-6 py-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rutas.data.length">
                            <td colspan="6" class="text-center py-16 text-white/20 font-bold uppercase tracking-widest text-xs">
                                No hay rutas registradas
                            </td>
                        </tr>
                        <tr
                            v-for="ruta in rutas.data"
                            :key="ruta.id"
                            class="border-b border-white/5 hover:bg-white/[0.02] transition-colors"
                        >
                            <td class="px-6 py-4">
                                <p class="font-black text-white">{{ ruta.nombre }}</p>
                                <p class="text-[10px] text-white/30 font-bold uppercase">
                                    {{ ruta.paradas?.length ?? 0 }} paradas
                                </p>
                            </td>
                            <td class="px-6 py-4 text-white/60 font-bold">
                                {{ formatFecha(ruta.fecha) }}
                            </td>
                            <td class="px-6 py-4 text-white/60 font-bold">
                                {{ ruta.repartidor?.user?.name ?? '—' }}
                                {{ ruta.repartidor?.user?.apellido ?? '' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-1 flex-wrap">
                                    <template v-if="ruta.paradas?.length">
                                        <span
                                            v-for="(count, estado) in contarEstados(ruta.paradas)"
                                            :key="estado"
                                            class="text-[10px] font-black px-2 py-0.5 rounded-full border"
                                            :class="estadoParada[estado]?.color"
                                        >
                                            {{ count }} {{ estadoParada[estado]?.label }}
                                        </span>
                                    </template>
                                    <span v-else class="text-white/20 text-xs">sin paradas</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="text-[10px] font-black px-3 py-1 rounded-full border"
                                    :class="ruta.activa
                                        ? 'text-green-400 bg-green-400/10 border-green-400/30'
                                        : 'text-white/20 bg-white/5 border-white/10'"
                                >
                                    {{ ruta.activa ? 'Activa' : 'Cerrada' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="route('rutas-reparto.show', ruta.id)"
                                        class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-brand-red hover:text-white transition-all"
                                    >
                                        Ver
                                    </Link>
                                    <button
                                        @click="eliminar(ruta)"
                                        class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-red-900/50 hover:text-red-400 transition-all"
                                    >
                                        Eliminar
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
                    class="px-4 py-2 rounded-lg border border-white/10 text-xs font-black uppercase tracking-tighter transition-all"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }"
                >{{ decodeLabel(link.label) }}</Link>
            </div>
        </div>

        <!-- Modal Crear Ruta -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showModal = false" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl p-8 w-full max-w-md shadow-2xl">
                    <h3 class="text-xl font-black uppercase tracking-tighter mb-6">
                        Nueva <span class="text-brand-red italic">Ruta</span>
                    </h3>

                    <form @submit.prevent="submitCrear" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Nombre</label>
                            <input
                                v-model="form.nombre"
                                type="text"
                                placeholder="Ej: Ruta Norte - Sábado"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"
                                :class="{ 'border-red-500': form.errors.nombre }"
                            />
                            <p v-if="form.errors.nombre" class="text-red-400 text-xs mt-1">{{ form.errors.nombre }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Fecha</label>
                            <input
                                v-model="form.fecha"
                                type="date"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50"
                                :class="{ 'border-red-500': form.errors.fecha }"
                            />
                            <p v-if="form.errors.fecha" class="text-red-400 text-xs mt-1">{{ form.errors.fecha }}</p>
                        </div>

                        <div class="relative">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Repartidor</label>
                            <button
                                type="button"
                                @click="showRepartidorDrop = !showRepartidorDrop"
                                class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white hover:border-brand-red/50 transition-colors"
                            >
                                <span>{{ repartidorLabel }}</span>
                                <svg class="w-4 h-4 text-white/30" :class="{ 'rotate-180': showRepartidorDrop }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div v-if="showRepartidorDrop" class="absolute z-20 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl">
                                <button type="button" @click="selectRepartidor('')" class="w-full text-left px-4 py-3 text-sm text-white/40 hover:bg-white/5 transition-colors border-b border-white/5">
                                    Sin asignar
                                </button>
                                <button
                                    v-for="r in repartidores" :key="r.id"
                                    type="button"
                                    @click="selectRepartidor(r.id)"
                                    class="w-full text-left px-4 py-3 text-sm text-white hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                                    :class="{ 'text-brand-red': form.repartidor_id == r.id }"
                                >
                                    {{ r.user?.name }} {{ r.user?.apellido }}
                                </button>
                            </div>
                            <div v-if="showRepartidorDrop" class="fixed inset-0 z-10" @click="showRepartidorDrop = false" />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showModal = false" class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                                {{ form.processing ? 'Creando...' : 'Crear Ruta' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

