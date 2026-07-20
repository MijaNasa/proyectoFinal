<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import Swal from 'sweetalert2';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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

const toggleActiva = () => {
    editForm.activa = !editForm.activa;
    submitEdit();
};

const repartidorLabel = computed(() => {
    if (!editForm.repartidor_id) return 'Sin asignar';
    const r = props.repartidores.find(r => r.id == editForm.repartidor_id);
    return r ? `${r.user?.name ?? ''} ${r.user?.apellido ?? ''}`.trim() : 'Sin asignar';
});

// ──────────────────────────────────────────
// Dropdown Asignar Paradas
// ──────────────────────────────────────────
const showAsignarModal = ref(false);
const ventaSearch      = ref('');
const seleccionadas    = ref([]);

const asignarForm = useForm({ venta_ids: [] });

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
    ventasFiltradas.value.length > 0 && ventasFiltradas.value.every(v => seleccionadas.value.includes(v.id))
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
// Modificar Estado Parada
// ──────────────────────────────────────────
const estadoForm = useForm({ estado: '', observaciones: '' });
const paradaEditando = ref(null);

const abrirEstadoModal = (parada) => {
    paradaEditando.value = parada;
    estadoForm.estado = parada.estado;
    estadoForm.observaciones = parada.observaciones ?? '';
};

const cerrarEstadoModal = () => { paradaEditando.value = null; estadoForm.reset(); };

const submitEstado = () => {
    estadoForm.patch(
        route('rutas-reparto.actualizar-parada', { rutas_reparto: props.ruta.id, parada: paradaEditando.value.id }),
        { onSuccess: cerrarEstadoModal }
    );
};

const optimizar = () => router.post(route('rutas-reparto.optimizar', props.ruta.id));

// ──────────────────────────────────────────
// Helpers visuales
// ──────────────────────────────────────────
const estadoConfig = {
    pendiente:   { label: 'Pendiente', color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/30', hex: '#facc15' },
    'en camino': { label: 'En camino', color: 'text-blue-400 bg-blue-400/10 border-blue-400/30', hex: '#60a5fa' },
    entregada:   { label: 'Entregada', color: 'text-green-400 bg-green-400/10 border-green-400/30', hex: '#4ade80' },
    fallida:     { label: 'Fallida',   color: 'text-red-400 bg-red-400/10 border-red-400/30', hex: '#f87171' },
};

const counts = computed(() => {
    const arr = props.ruta.paradas || [];
    return {
        total: arr.length,
        entregadas: arr.filter(p => p.estado === 'entregada').length,
        pendientes: arr.filter(p => p.estado === 'pendiente').length,
        en_camino:  arr.filter(p => p.estado === 'en camino').length,
        fallidas:   arr.filter(p => p.estado === 'fallida').length,
    };
});

const formatFecha = (f) =>
    new Date(f + 'T00:00:00').toLocaleDateString('es-AR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });

// ──────────────────────────────────────────
// Mapa Leaflet
// ──────────────────────────────────────────
let map = null;
let markers = [];
let polyline = null;
const mapContainer = ref(null);

const initMap = () => {
    if (map) return;
    map = L.map(mapContainer.value, { zoomControl: false }).setView([-32.9442426, -60.6505388], 13);
    
    L.control.zoom({ position: 'topleft' }).addTo(map);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    updateMap();
};

const updateMap = () => {
    if (!map) return;
    
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    if (polyline) map.removeLayer(polyline);

    const validParadas = props.ruta.paradas?.filter(p => p.latitud && p.longitud) || [];
    if (validParadas.length === 0) return;

    const latlngs = validParadas.map(p => [p.latitud, p.longitud]);

    validParadas.forEach((p) => {
        const color = estadoConfig[p.estado]?.hex || '#facc15';
        const iconHtml = `
            <div class="relative w-6 h-6 rounded-full flex items-center justify-center font-black text-[10px] text-[#000] shadow-lg"
                 style="background-color: ${color}; border: 1.5px solid ${color};">
                ${p.orden}
                <div class="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-0 h-0"
                     style="border-left: 3px solid transparent; border-right: 3px solid transparent; border-top: 4px solid ${color};">
                </div>
            </div>
        `;
        const icon = L.divIcon({
            html: iconHtml,
            className: '',
            iconSize: [24, 24],
            iconAnchor: [12, 28]
        });

        const marker = L.marker([p.latitud, p.longitud], { icon }).addTo(map);
        marker.bindPopup(`<b class="text-black uppercase text-[10px]">Parada ${p.orden}</b><br><span class="text-black text-xs">${p.venta?.direccion_envio}</span>`);
        markers.push(marker);
    });

    polyline = L.polyline(latlngs, { color: '#ffffff', weight: 2, opacity: 0.3, dashArray: '5, 5' }).addTo(map);

    if (latlngs.length > 0) {
        map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40] });
    }
};

onMounted(() => {
    nextTick(() => initMap());
});

watch(() => props.ruta.paradas, () => {
    nextTick(() => updateMap());
}, { deep: true });

</script>

<template>
    <Head :title="`Ruta: ${ruta.nombre}`" />

    <AuthenticatedLayout>
        <div class="max-w-[1600px] mx-auto px-4 py-8 space-y-6">

            <!-- 1. HEADER (Mockup: RUTA #4 (ACTIVA)) -->
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-3xl font-black uppercase tracking-tighter flex items-center gap-3">
                        Ruta #{{ ruta.nombre }}
                        <span class="text-lg" :class="ruta.activa ? 'text-green-400' : 'text-white/20'">
                            ({{ ruta.activa ? 'ACTIVA' : 'INACTIVA' }})
                        </span>
                    </h2>
                    <p class="text-white/40 text-xs font-bold uppercase tracking-widest mt-1">
                        {{ formatFecha(ruta.fecha) }} - {{ ruta.repartidor?.user?.name ?? 'Sin Repartidor' }} {{ ruta.repartidor?.user?.apellido ?? '' }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-black uppercase tracking-widest text-white/50">Ruta Activa</span>
                    <!-- Switch -->
                    <button 
                        type="button"
                        @click="toggleActiva"
                        class="w-12 h-6 rounded-full relative transition-colors duration-300 focus:outline-none"
                        :class="editForm.activa ? 'bg-green-400' : 'bg-white/10'"
                    >
                        <div class="w-4 h-4 bg-white rounded-full absolute top-1 transition-all duration-300 shadow-sm"
                             :class="editForm.activa ? 'left-7' : 'left-1'"></div>
                    </button>
                </div>
            </div>

            <!-- 2. RESUMEN Y PROGRESO -->
            <div class="bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl flex flex-col xl:flex-row gap-8">
                <!-- Resumen (Izquierda) -->
                <div class="flex-1 xl:max-w-md">
                    <h4 class="text-[9px] font-black uppercase tracking-widest text-white/30 mb-4">Resumen de Ruta</h4>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <div class="w-full h-1 bg-green-400 rounded-full mb-2"></div>
                            <span class="text-[10px] font-black text-green-400 uppercase block">{{ counts.entregadas }} Entregadas</span>
                        </div>
                        <div>
                            <div class="w-full h-1 bg-yellow-400 rounded-full mb-2"></div>
                            <span class="text-[10px] font-black text-yellow-400 uppercase block">{{ counts.pendientes }} Pendientes</span>
                        </div>
                        <div>
                            <div class="w-full h-1 bg-blue-400 rounded-full mb-2"></div>
                            <span class="text-[10px] font-black text-blue-400 uppercase block">{{ counts.en_camino }} En Camino</span>
                        </div>
                        <div>
                            <div class="w-full h-1 bg-red-400 rounded-full mb-2"></div>
                            <span class="text-[10px] font-black text-red-400 uppercase block">{{ counts.fallidas }} Fallidas</span>
                        </div>
                    </div>
                </div>

                <div class="hidden xl:block w-px bg-white/5 mx-2"></div>

                <!-- Progreso Visual (Derecha) -->
                <div class="flex-1 flex flex-col justify-center">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-[9px] font-black uppercase tracking-widest text-white/30">Progreso</h4>
                        <span class="text-[9px] font-black uppercase tracking-widest text-white/50">Total: {{ counts.total }} Paradas</span>
                    </div>
                    
                    <div class="relative w-full h-1 bg-white/10 rounded-full mt-2">
                        <div class="absolute top-0 left-0 h-1 bg-brand-red rounded-full transition-all duration-700"
                             :style="{ width: counts.total ? (counts.entregadas / counts.total * 100) + '%' : '0%' }"></div>
                        
                        <!-- Puntos distribuidos (hasta 10 puntos visuales max) -->
                        <div v-for="i in Math.min(counts.total, 10)" :key="i"
                             class="absolute top-1/2 -translate-y-1/2 w-3 h-3 rounded-full border-2 border-[#111] transition-colors"
                             :class="i <= counts.entregadas ? 'bg-brand-red' : 'bg-white/20'"
                             :style="{ left: ((i - 1) / Math.max(counts.total - 1, 1) * 100) + '%' }">
                        </div>
                    </div>
                    <div class="flex justify-between mt-3 px-1 text-[9px] font-black uppercase tracking-widest text-white/30">
                        <span>{{ counts.pendientes }} Pendientes</span>
                        <span>{{ counts.en_camino }} En Camino</span>
                        <span>{{ counts.fallidas }} Fallidas</span>
                    </div>
                </div>
            </div>

            <!-- 3. CONTENIDO PRINCIPAL (3 Columnas) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Columna Izquierda: Mapa -->
                <div class="lg:col-span-12 xl:col-span-5 h-[600px] bg-[#111] border border-white/5 rounded-2xl overflow-hidden relative shadow-2xl">
                    <div ref="mapContainer" class="w-full h-full z-0"></div>
                    
                    <!-- Leyenda Flotante -->
                    <div class="absolute top-4 right-4 z-[400] bg-[#111]/90 backdrop-blur-md border border-white/10 rounded-xl p-3 shadow-xl">
                        <h5 class="text-[8px] font-black uppercase tracking-widest text-white/50 mb-2">Leyenda</h5>
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                                <span class="text-[9px] font-bold text-white uppercase">Entregada</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>
                                <span class="text-[9px] font-bold text-white uppercase">Pendiente</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>
                                <span class="text-[9px] font-bold text-white uppercase">En camino</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                                <span class="text-[9px] font-bold text-white uppercase">Fallida</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Central: Timeline de Paradas -->
                <div class="lg:col-span-8 xl:col-span-4 bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl flex flex-col h-[600px]">
                    <div class="flex items-center justify-between mb-6 shrink-0">
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-white/40">Paradas</h4>
                        <div class="relative">
                            <button @click="showAsignarModal = !showAsignarModal" class="btn-primary px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
                                + Agregar Entrega
                            </button>
                            <!-- Dropdown Asignar (simplificado) -->
                            <div v-if="showAsignarModal" class="absolute right-0 top-full mt-2 w-72 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl z-[500] flex flex-col max-h-[400px]">
                                <div class="p-3 border-b border-white/10 bg-[#111]">
                                    <input v-model="ventaSearch" type="text" placeholder="Buscar..." class="w-full bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none" />
                                </div>
                                <div class="overflow-y-auto flex-1 p-2 space-y-1">
                                    <label v-for="v in ventasFiltradas" :key="v.id" class="flex items-start gap-2 p-2 hover:bg-white/5 rounded cursor-pointer">
                                        <input type="checkbox" :checked="seleccionadas.includes(v.id)" @change="toggleVenta(v.id)" class="mt-0.5" />
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-black text-white leading-tight truncate">#{{ v.id }} - {{ v.cliente?.user?.name }}</p>
                                            <p class="text-[9px] text-white/40 truncate">{{ v.direccion_envio }}</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="p-2 border-t border-white/10 bg-[#111]">
                                    <button @click="submitAsignar" class="w-full btn-primary py-1.5 rounded-lg text-[10px] uppercase font-black">
                                        Agregar {{ seleccionadas.length }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto pr-2 space-y-4 relative">
                        <!-- Timeline Line -->
                        <div class="absolute left-[19px] top-4 bottom-4 w-px bg-white/10 z-0"></div>

                        <div v-if="!ruta.paradas?.length" class="text-center text-white/30 text-xs mt-10">
                            Sin paradas asignadas.
                        </div>

                        <!-- Card Parada -->
                        <div v-for="parada in ruta.paradas" :key="parada.id" class="relative z-10 flex gap-4"
                             :class="{ 'opacity-50 grayscale transition-all duration-500': ['entregada', 'fallida'].includes(parada.estado) }">
                            <!-- Circular Badge -->
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 border-[#111] text-xs font-black shrink-0 mt-1"
                                 :class="estadoConfig[parada.estado]?.color.split(' ')[1] + ' text-white'">
                                {{ parada.orden }}
                            </div>
                            
                            <!-- Card Content -->
                            <div class="flex-1 bg-white/5 border border-white/10 hover:border-white/20 transition-colors rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-sm font-black text-white truncate pr-2">
                                        {{ parada.venta?.cliente?.user?.name }} {{ parada.venta?.cliente?.user?.apellido }}
                                    </h5>
                                    <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md border shrink-0"
                                          :class="estadoConfig[parada.estado]?.color">
                                        {{ estadoConfig[parada.estado]?.label }}
                                    </span>
                                </div>
                                <p class="text-xs text-white/70 mb-3 whitespace-normal font-medium">
                                    📍 {{ parada.venta?.direccion_envio }}
                                </p>
                                
                                <div class="flex items-center gap-3 text-[9px] text-white/30 font-bold uppercase mb-4">
                                    <span>Venta #{{ parada.venta?.id }}</span>
                                    <span>·</span>
                                    <span>{{ parada.venta?.detalles?.length ?? 0 }} Ítem(s)</span>
                                </div>

                                <div class="flex gap-2">
                                    <button @click="abrirEstadoModal(parada)" class="flex-1 py-1.5 rounded-md bg-white/5 border border-white/10 hover:bg-brand-red hover:border-brand-red hover:text-white transition-all text-[9px] font-black uppercase tracking-widest text-white/70">
                                        Cambiar Estado
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Formulario & Acciones -->
                <div class="lg:col-span-4 xl:col-span-3 bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl h-fit">
                    
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-4">Datos y Acciones</h4>

                    <div class="grid grid-cols-2 gap-2 mb-6">
                        <button @click="showAsignarModal = true" class="py-2.5 rounded-lg border border-white/10 hover:bg-white/5 text-[9px] font-black uppercase tracking-widest text-white/70 transition-colors">
                            Agregar Entrega
                        </button>
                        <button @click="optimizar" class="py-2.5 rounded-lg bg-brand-red hover:bg-red-500 text-[9px] font-black uppercase tracking-widest text-white transition-colors flex items-center justify-center gap-1 shadow-[0_0_15px_rgba(230,25,25,0.3)]">
                            ⚡ Optimizar Ruta
                        </button>
                    </div>

                    <div class="w-full h-px bg-white/5 mb-6"></div>

                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-white/30 mb-1.5">Nombre</label>
                            <input v-model="editForm.nombre" type="text" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red/50 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-white/30 mb-1.5">Fecha</label>
                            <input v-model="editForm.fecha" type="date" class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red/50 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-white/30 mb-1.5">Repartidor</label>
                            <select v-model="editForm.repartidor_id" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red/50 focus:outline-none appearance-none">
                                <option value="">Sin asignar</option>
                                <option v-for="r in repartidores" :key="r.id" :value="r.id">
                                    {{ r.user?.name }} {{ r.user?.apellido }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <input type="checkbox" v-model="editForm.activa" class="w-4 h-4 accent-brand-red rounded bg-white/5 border-white/10" />
                            <span class="text-[10px] font-black uppercase tracking-widest text-white/50">Ruta Activa</span>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" :disabled="editForm.processing" class="w-full btn-primary py-3 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(230,25,25,0.2)]">
                                {{ editForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- Modal: Actualizar Estado Parada -->
        <Teleport to="body">
            <div v-if="paradaEditando" class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="cerrarEstadoModal" />
                <div class="relative bg-[#111] border border-white/10 rounded-2xl p-6 w-full max-w-sm shadow-2xl">
                    <h3 class="text-lg font-black uppercase tracking-tighter mb-1">
                        Actualizar <span class="text-brand-red italic">Estado</span>
                    </h3>
                    <p class="text-[10px] text-white/40 font-bold uppercase tracking-widest mb-5 truncate">
                        Parada #{{ paradaEditando.orden }} — {{ paradaEditando.venta?.cliente?.user?.name }}
                    </p>

                    <form @submit.prevent="submitEstado" class="space-y-4">
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="opt in ['pendiente', 'en camino', 'entregada', 'fallida']" :key="opt"
                                    type="button" @click="estadoForm.estado = opt"
                                    class="py-2.5 rounded-lg border text-[9px] font-black uppercase tracking-wider transition-all"
                                    :class="estadoForm.estado === opt ? estadoConfig[opt]?.color + ' border-current scale-95' : 'border-white/10 text-white/30 hover:border-white/30'">
                                {{ estadoConfig[opt]?.label }}
                            </button>
                        </div>

                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-white/40 mb-1">Observaciones</label>
                            <textarea v-model="estadoForm.observaciones" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50 resize-none" placeholder="Motivo de fallo, detalles..."></textarea>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="cerrarEstadoModal" class="flex-1 py-2.5 rounded-lg border border-white/10 text-[9px] font-black uppercase tracking-widest text-white/40 hover:bg-white/5">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="estadoForm.processing" class="flex-1 btn-primary py-2.5 rounded-lg text-[9px] font-black uppercase tracking-widest">
                                Confirmar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Transiciones suaves y retoques leaflet */
.leaflet-container { background: #111; }
.leaflet-control-zoom a {
    background-color: #1a1a1a !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.1) !important;
}
.leaflet-control-zoom a:hover {
    background-color: #333 !important;
}
</style>
