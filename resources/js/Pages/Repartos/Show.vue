<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    ruta:               Object,
    repartidores:       Array,
    ventas_disponibles: Array,
});

// ──────────────────────────────────────────
// Editar cabecera de ruta
// ──────────────────────────────────────────
const editForm = useForm({
    nombre:        props.ruta.nombre,
    fecha:         props.ruta.fecha,
    repartidor_id: props.ruta.repartidor_id ?? '',
    activa:        !!props.ruta.activa,
});

const submitEdit = () => editForm.put(route('rutas-reparto.update', props.ruta.id));

// ──────────────────────────────────────────
// Dropdown custom de repartidor
// ──────────────────────────────────────────
const showRepartidorDrop = ref(false);

const repartidorLabel = computed(() => {
    if (!editForm.repartidor_id) return 'Sin asignar';
    const r = props.repartidores.find(r => r.id == editForm.repartidor_id);
    return r ? `${r.user?.name ?? ''} ${r.user?.apellido ?? ''}`.trim() : 'Sin asignar';
});

const selectRepartidor = (id) => {
    editForm.repartidor_id = id;
    showRepartidorDrop.value = false;
};

// ──────────────────────────────────────────
// Asignar venta
// ──────────────────────────────────────────
const showAsignarModal = ref(false);
const ventaSearch      = ref('');
const seleccionadas    = ref([]);

const asignarForm = useForm({
    venta_ids:     [],
    observaciones: '',
});

const ventasFiltradas = computed(() => {
    if (!ventaSearch.value) return props.ventas_disponibles;
    const q = ventaSearch.value.toLowerCase();
    return props.ventas_disponibles.filter(v =>
        v.cliente?.user?.name?.toLowerCase().includes(q) ||
        v.cliente?.user?.apellido?.toLowerCase().includes(q) ||
        String(v.id).includes(q) ||
        v.direccion_envio?.toLowerCase().includes(q)
    );
});

const toggleVenta = (id) => {
    const idx = seleccionadas.value.indexOf(id);
    if (idx === -1) seleccionadas.value.push(id);
    else seleccionadas.value.splice(idx, 1);
};

const todasSeleccionadas = computed(() =>
    ventasFiltradas.value.length > 0 &&
    ventasFiltradas.value.every(v => seleccionadas.value.includes(v.id))
);

const toggleTodas = () => {
    if (todasSeleccionadas.value) {
        seleccionadas.value = seleccionadas.value.filter(id => !ventasFiltradas.value.some(v => v.id === id));
    } else {
        const nuevos = ventasFiltradas.value.map(v => v.id).filter(id => !seleccionadas.value.includes(id));
        seleccionadas.value.push(...nuevos);
    }
};

const cerrarAsignarModal = () => {
    showAsignarModal.value = false;
    asignarForm.reset();
    seleccionadas.value = [];
    ventaSearch.value = '';
};

const submitAsignar = () => {
    asignarForm.venta_ids = seleccionadas.value;
    asignarForm.post(route('rutas-reparto.asignar-venta', props.ruta.id), {
        onSuccess: cerrarAsignarModal,
    });
};


// ──────────────────────────────────────────
// Estado de parada
// ──────────────────────────────────────────
const estadoForm = useForm({ estado: '', observaciones: '' });
const paradaEditando = ref(null);

const abrirEstadoModal = (parada) => {
    paradaEditando.value = parada;
    estadoForm.estado        = parada.estado;
    estadoForm.observaciones = parada.observaciones ?? '';
};

const cerrarEstadoModal = () => { paradaEditando.value = null; estadoForm.reset(); };

const submitEstado = () => {
    estadoForm.patch(
        route('rutas-reparto.actualizar-parada', { rutas_reparto: props.ruta.id, parada: paradaEditando.value.id }),
        { onSuccess: cerrarEstadoModal }
    );
};

// ──────────────────────────────────────────
// Eliminar parada
// ──────────────────────────────────────────
const eliminarParada = (parada) => {
    Swal.fire({
        title: '¿Quitar parada?',
        text: 'Se removerá la entrega de esta ruta.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
        background: '#111',
        color: '#fff',
    }).then(r => {
        if (r.isConfirmed) {
            router.delete(route('rutas-reparto.remove-parada', { rutas_reparto: props.ruta.id, parada: parada.id }));
        }
    });
};

// ──────────────────────────────────────────
// Optimizar ruta
// ──────────────────────────────────────────
const optimizar = () => {
    router.post(route('rutas-reparto.optimizar', props.ruta.id));
};

// ──────────────────────────────────────────
// Helpers visuales
// ──────────────────────────────────────────
const estadoConfig = {
    pendiente:   { label: 'Pendiente',  color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/30' },
    'en camino': { label: 'En camino',  color: 'text-blue-400 bg-blue-400/10 border-blue-400/30' },
    entregada:   { label: 'Entregada',  color: 'text-green-400 bg-green-400/10 border-green-400/30' },
    fallida:     { label: 'Fallida',    color: 'text-red-400 bg-red-400/10 border-red-400/30' },
};

const formatPrecio = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const formatFecha = (f) =>
    new Date(f + 'T00:00:00').toLocaleDateString('es-AR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });

const progressPct = computed(() => {
    const total = props.ruta.paradas?.length ?? 0;
    if (!total) return 0;
    const done = props.ruta.paradas.filter(p => p.estado === 'entregada').length;
    return Math.round((done / total) * 100);
});
</script>

<template>
    <Head :title="`Ruta: ${ruta.nombre}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <a :href="route('rutas-reparto.index')" class="text-white/30 hover:text-brand-red transition-colors text-xs font-black uppercase tracking-widest">
                            ← Repartos
                        </a>
                    </div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter">
                        {{ ruta.nombre }}
                    </h2>
                    <p class="text-white/30 text-xs font-bold uppercase tracking-widest mt-1">
                        {{ formatFecha(ruta.fecha) }}
                        · {{ ruta.repartidor?.user?.name ?? 'Sin repartidor' }}
                        {{ ruta.repartidor?.user?.apellido ?? '' }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="text-[10px] font-black px-3 py-1 rounded-full border"
                        :class="ruta.activa ? 'text-green-400 bg-green-400/10 border-green-400/30' : 'text-white/20 bg-white/5 border-white/10'"
                    >{{ ruta.activa ? 'Activa' : 'Cerrada' }}</span>
                    <button @click="optimizar" class="text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-lg bg-white/5 hover:bg-blue-500/20 hover:text-blue-400 border border-white/10 hover:border-blue-400/30 transition-all">
                        ⚡ Optimizar Ruta
                    </button>
                    <button @click="showAsignarModal = true" class="btn-primary px-5 py-2 rounded-xl text-xs font-black uppercase tracking-widest">
                        + Agregar Entrega
                    </button>
                </div>
            </div>
        </template>

        <div class="px-8 py-8 space-y-8">

            <!-- Progreso -->
            <div v-if="ruta.paradas?.length" class="bg-white/[0.02] border border-white/10 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[10px] font-black uppercase tracking-widest text-white/40">
                        Progreso de entregas
                    </p>
                    <p class="text-sm font-black text-white">
                        {{ ruta.paradas.filter(p => p.estado === 'entregada').length }} /
                        {{ ruta.paradas.length }} entregadas
                    </p>
                </div>
                <div class="w-full bg-white/5 rounded-full h-2">
                    <div class="bg-brand-red h-2 rounded-full transition-all duration-500" :style="`width: ${progressPct}%`" />
                </div>
                <div class="flex gap-4 mt-3 text-[10px] font-black uppercase tracking-widest">
                    <span class="text-yellow-400">{{ ruta.paradas.filter(p=>p.estado==='pendiente').length }} pendientes</span>
                    <span class="text-blue-400">{{ ruta.paradas.filter(p=>p.estado==='en camino').length }} en camino</span>
                    <span class="text-green-400">{{ ruta.paradas.filter(p=>p.estado==='entregada').length }} entregadas</span>
                    <span class="text-red-400">{{ ruta.paradas.filter(p=>p.estado==='fallida').length }} fallidas</span>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

                <!-- Lista de Paradas -->
                <div class="xl:col-span-2 space-y-4">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-white/40">
                        Paradas ({{ ruta.paradas?.length ?? 0 }})
                    </h3>

                    <div v-if="!ruta.paradas?.length" class="bg-white/[0.02] border border-white/10 rounded-2xl p-12 text-center">
                        <p class="text-white/20 font-black uppercase tracking-widest text-xs">
                            No hay paradas en esta ruta
                        </p>
                        <p class="text-white/10 text-xs mt-2">Agregá entregas con el botón "Agregar Entrega"</p>
                    </div>

                    <div
                        v-for="parada in ruta.paradas"
                        :key="parada.id"
                        class="bg-white/[0.02] border border-white/10 rounded-2xl p-5 hover:border-white/20 transition-all"
                        :class="{ 'border-green-400/30 bg-green-400/[0.02]': parada.estado === 'entregada', 'border-red-400/30 bg-red-400/[0.02]': parada.estado === 'fallida' }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <!-- Número de orden -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center">
                                <span class="text-xs font-black text-white/40">{{ parada.orden }}</span>
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="font-black text-white">
                                        {{ parada.venta?.cliente?.user?.name }}
                                        {{ parada.venta?.cliente?.user?.apellido }}
                                    </span>
                                    <span
                                        class="text-[10px] font-black px-2 py-0.5 rounded-full border"
                                        :class="estadoConfig[parada.estado]?.color"
                                    >{{ estadoConfig[parada.estado]?.label }}</span>
                                </div>

                                <p class="text-xs text-white/40 font-bold mb-2">
                                    📍 {{ parada.venta?.direccion_envio ?? 'Sin dirección' }}
                                </p>

                                <div class="flex flex-wrap gap-2 text-[10px] text-white/30 font-bold">
                                    <span>Venta #{{ parada.venta?.id }}</span>
                                    <span>·</span>
                                    <span class="text-brand-red font-black">{{ formatPrecio(parada.venta?.total) }}</span>
                                    <span>·</span>
                                    <span>{{ parada.venta?.detalles?.length ?? 0 }} ítem(s)</span>
                                </div>

                                <!-- Items de la venta -->
                                <div class="mt-3 space-y-1">
                                    <div
                                        v-for="det in parada.venta?.detalles"
                                        :key="det.id"
                                        class="flex justify-between text-xs text-white/40"
                                    >
                                        <span>{{ det.libro?.master?.titulo }} <span class="text-white/20">x{{ det.cantidad }}</span></span>
                                        <span>{{ formatPrecio(det.subtotal) }}</span>
                                    </div>
                                </div>

                                <p v-if="parada.observaciones" class="mt-2 text-xs text-yellow-400/70 italic">
                                    💬 {{ parada.observaciones }}
                                </p>

                                <div v-if="parada.latitud && parada.longitud" class="mt-1 text-[10px] text-white/20 font-mono">
                                    {{ parada.latitud }}, {{ parada.longitud }}
                                </div>
                            </div>

                            <!-- Acciones -->
                            <div class="flex flex-col gap-2 flex-shrink-0">
                                <button
                                    @click="abrirEstadoModal(parada)"
                                    class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-brand-red hover:text-white border border-white/10 transition-all whitespace-nowrap"
                                >
                                    Actualizar
                                </button>
                                <button
                                    v-if="parada.estado !== 'entregada'"
                                    @click="eliminarParada(parada)"
                                    class="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg bg-white/5 hover:bg-red-900/50 hover:text-red-400 border border-white/10 transition-all"
                                >
                                    Quitar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel lateral: Editar ruta -->
                <div class="xl:col-span-1">
                    <div class="bg-white/[0.02] border border-white/10 rounded-2xl p-6 sticky top-6">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-5">
                            Datos de la Ruta
                        </h3>

                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Nombre</label>
                                <input
                                    v-model="editForm.nombre"
                                    type="text"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50"
                                />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Fecha</label>
                                <input
                                    v-model="editForm.fecha"
                                    type="date"
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red/50"
                                />
                            </div>
                            <div class="relative">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1">Repartidor</label>
                                <button
                                    type="button"
                                    @click="showRepartidorDrop = !showRepartidorDrop"
                                    class="w-full flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none hover:border-brand-red/50 transition-colors"
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
                                        :class="{ 'text-brand-red': editForm.repartidor_id == r.id }"
                                    >
                                        {{ r.user?.name }} {{ r.user?.apellido }}
                                    </button>
                                </div>
                                <div v-if="showRepartidorDrop" class="fixed inset-0 z-10" @click="showRepartidorDrop = false" />
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="checkbox" v-model="editForm.activa" id="activa" class="w-4 h-4 accent-brand-red" />
                                <label for="activa" class="text-xs font-black uppercase tracking-widest text-white/50 cursor-pointer">
                                    Ruta activa
                                </label>
                            </div>
                            <button type="submit" :disabled="editForm.processing" class="w-full btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                                {{ editForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Agregar Entrega -->
        <Teleport to="body">
            <div v-if="showAsignarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="cerrarAsignarModal" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl p-8 w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
                    <h3 class="text-xl font-black uppercase tracking-tighter mb-6">
                        Agregar <span class="text-brand-red italic">Entregas</span>
                    </h3>

                    <form @submit.prevent="submitAsignar" class="space-y-4">
                        <!-- Buscador -->
                        <input
                            v-model="ventaSearch"
                            type="text"
                            placeholder="Buscar por cliente, dirección o # venta..."
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50"
                        />

                        <!-- Lista de pedidos pendientes -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40">
                                    Pedidos pendientes de entrega ({{ seleccionadas.length }} seleccionados)
                                </label>
                                <button
                                    type="button"
                                    @click="toggleTodas"
                                    class="text-[10px] font-black uppercase tracking-widest text-brand-red hover:underline"
                                >
                                    {{ todasSeleccionadas ? 'Destildar todas' : 'Tildar todas' }}
                                </button>
                            </div>

                            <div class="border border-white/10 rounded-xl overflow-hidden max-h-72 overflow-y-auto divide-y divide-white/5">
                                <label
                                    v-for="v in ventasFiltradas"
                                    :key="v.id"
                                    class="flex items-start gap-3 px-4 py-3 hover:bg-white/5 transition-colors cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="seleccionadas.includes(v.id)"
                                        @change="toggleVenta(v.id)"
                                        class="mt-1 w-4 h-4 accent-brand-red"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-sm font-black text-white">
                                            #{{ v.id }} — {{ v.cliente?.user?.name }} {{ v.cliente?.user?.apellido }}
                                        </p>
                                        <p class="text-xs text-white/40">
                                            📍 {{ v.direccion_envio ?? 'Sin dirección' }}
                                            · {{ formatPrecio(v.total) }}
                                        </p>
                                    </div>
                                </label>
                                <div v-if="!ventasFiltradas.length" class="px-4 py-6 text-center text-xs text-white/30">
                                    No hay pedidos pendientes de entrega
                                </div>
                            </div>
                            <p v-if="asignarForm.errors.venta_ids" class="text-red-400 text-xs mt-1">{{ asignarForm.errors.venta_ids }}</p>
                            <p class="text-[10px] text-white/20 mt-1">La ubicación (lat/long) se completa automáticamente a partir de la dirección de envío.</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Observaciones (opcional)</label>
                            <textarea
                                v-model="asignarForm.observaciones"
                                rows="2"
                                placeholder="Ej: Timbre roto, llamar al llegar..."
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50 resize-none"
                            />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="cerrarAsignarModal" class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5 transition-all">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="asignarForm.processing || !seleccionadas.length"
                                class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                {{ asignarForm.processing ? 'Agregando...' : `Agregar ${seleccionadas.length || ''} a Ruta` }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Actualizar Estado Parada -->
        <Teleport to="body">
            <div v-if="paradaEditando" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="cerrarEstadoModal" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl p-8 w-full max-w-sm shadow-2xl">
                    <h3 class="text-xl font-black uppercase tracking-tighter mb-2">
                        Actualizar <span class="text-brand-red italic">Estado</span>
                    </h3>
                    <p class="text-xs text-white/30 font-bold uppercase tracking-widest mb-6">
                        Parada #{{ paradaEditando.orden }} —
                        {{ paradaEditando.venta?.cliente?.user?.name }}
                        {{ paradaEditando.venta?.cliente?.user?.apellido }}
                    </p>

                    <form @submit.prevent="submitEstado" class="space-y-4">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="opt in ['pendiente', 'en camino', 'entregada', 'fallida']"
                                :key="opt"
                                type="button"
                                @click="estadoForm.estado = opt"
                                class="py-3 px-4 rounded-xl border text-xs font-black uppercase tracking-wider transition-all"
                                :class="estadoForm.estado === opt
                                    ? estadoConfig[opt]?.color + ' border-current'
                                    : 'border-white/10 text-white/30 hover:border-white/30'"
                            >
                                {{ estadoConfig[opt]?.label }}
                            </button>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Observaciones</label>
                            <textarea
                                v-model="estadoForm.observaciones"
                                rows="2"
                                placeholder="Ej: Cliente ausente, se reprograma..."
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50 resize-none"
                            />
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="cerrarEstadoModal" class="flex-1 py-3 rounded-xl border border-white/10 text-xs font-black uppercase tracking-widest text-white/40 hover:bg-white/5">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="estadoForm.processing" class="flex-1 btn-primary py-3 rounded-xl text-xs font-black uppercase tracking-widest">
                                {{ estadoForm.processing ? 'Guardando...' : 'Confirmar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
