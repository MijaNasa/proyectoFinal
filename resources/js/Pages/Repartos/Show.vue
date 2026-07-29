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

const localParadas = ref([]);
watch(() => props.ruta.paradas, (newVal) => {
    localParadas.value = [...(newVal || [])];
}, { immediate: true, deep: true });

const draggedIndex = ref(null);

const onDragStart = (index, event) => {
    draggedIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', index);
    }
};

const onDragEnter = (event) => {
    event.preventDefault();
};

const onDragOver = (event) => {
    event.preventDefault();
};

const onDrop = (index) => {
    if (draggedIndex.value === null || draggedIndex.value === index) return;
    
    const draggedItem = localParadas.value[draggedIndex.value];
    localParadas.value.splice(draggedIndex.value, 1);
    localParadas.value.splice(index, 0, draggedItem);
    
    // Update local order
    localParadas.value.forEach((p, i) => { p.orden = i + 1; });
    
    // Send to backend
    guardarNuevoOrden();
    
    draggedIndex.value = null;
};

const guardarNuevoOrden = () => {
    const orden = localParadas.value.map(p => p.id);
    router.post(route('rutas-reparto.reordenar', props.ruta.id), { orden }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            updateMap();
        }
    });
};

// ──────────────────────────────────────────
// Editar cabecera de ruta
// ──────────────────────────────────────────
const editForm = useForm({
    repartidor_id: props.ruta.repartidor_id ?? '',
});

const submitEdit = () => editForm.put(route('rutas-reparto.update', props.ruta.id));

const iniciarRuta = () => {
    if (confirm("¿Estás seguro de iniciar la ruta? Esto notificará a los clientes que sus pedidos están en camino.")) {
        router.post(route('rutas-reparto.iniciar', props.ruta.id), {}, {
            preserveScroll: true
        });
    }
};

const finalizarRuta = () => {
    if (confirm("¿Estás seguro de finalizar la ruta manualmente? Las ventas no entregadas volverán a estar disponibles.")) {
        router.post(route('rutas-reparto.finalizar', props.ruta.id), {}, {
            preserveScroll: true
        });
    }
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
    const arr = localParadas.value || [];
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
const originCoords = [-32.9473682, -60.6364222];

const initMap = () => {
    if (map) return;
    map = L.map(mapContainer.value, { zoomControl: false }).setView(originCoords, 13);
    
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

    // Marcador de origen (A/Z)
    const originMarkerIcon = L.divIcon({
        html: `<div class="w-6 h-6 rounded-full border-2 border-white bg-black text-white flex items-center justify-center text-[10px] font-bold shadow-lg">A/Z</div>`,
        className: ''
    });
    const originMarker = L.marker(originCoords, { icon: originMarkerIcon }).addTo(map);
    markers.push(originMarker);

    const validParadas = localParadas.value?.filter(p => p.latitud && p.longitud) || [];
    if (validParadas.length === 0) return;

    // Crear lista de puntos
    let latlngs = [];
    const todasFinalizadas = validParadas.length > 0 && validParadas.every(p => ['entregada', 'fallida'].includes(p.estado));

    if (props.ruta.estado === 'finalizada') {
        latlngs = []; // No mostrar caminos si ya está finalizada
    } else if (props.ruta.activa || todasFinalizadas) {
        // Encontrar el objetivo actual (el primero que no está terminado)
        const targetIndex = validParadas.findIndex(p => ['en camino', 'pendiente'].includes(p.estado));
        
        let startCoord = originCoords;
        let endCoord = originCoords;

        if (targetIndex !== -1) {
            endCoord = [validParadas[targetIndex].latitud, validParadas[targetIndex].longitud];
            if (targetIndex > 0) {
                startCoord = [validParadas[targetIndex - 1].latitud, validParadas[targetIndex - 1].longitud];
            }
        } else {
            // Todos terminados, volvemos a casa
            endCoord = originCoords;
            if (validParadas.length > 0) {
                startCoord = [validParadas[validParadas.length - 1].latitud, validParadas[validParadas.length - 1].longitud];
            }
        }
        
        latlngs = [startCoord, endCoord];
    } else {
        // Si no está iniciada y no está terminada, mostramos todo el recorrido
        latlngs = [originCoords, ...validParadas.map(p => [p.latitud, p.longitud]), originCoords];
    }

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

    if (latlngs.length > 1) {
            const coordinatesString = latlngs.map(coord => `${coord[1]},${coord[0]}`).join(';');
            
            fetch(`https://router.project-osrm.org/route/v1/driving/${coordinatesString}?overview=full&geometries=geojson`)
                .then(res => res.json())
                .then(data => {
                    if (data.routes && data.routes.length > 0) {
                        const routeCoords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                        polyline = L.polyline(routeCoords, {
                            color: '#3b82f6', // Un azul brillante para que resalte como GPS
                            weight: 4,
                            opacity: 0.8
                        }).addTo(map);
                        map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
                    } else {
                        throw new Error("No routes found");
                    }
                })
                .catch(err => {
                    console.error("OSRM error, falling back to straight lines:", err);
                    polyline = L.polyline(latlngs, {
                        color: '#fff',
                        weight: 2,
                        dashArray: '5, 10',
                        opacity: 0.5
                    }).addTo(map);
                    map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
                });
        }
};

onMounted(() => {
    nextTick(() => initMap());
});

watch(localParadas, () => {
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
                    <span v-if="ruta.activa" class="px-4 py-2 bg-green-500/20 text-green-400 border border-green-500/50 rounded-lg text-xs font-black uppercase tracking-widest flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                        En Curso
                    </span>
                </div>
            </div>

            <!-- 2. PROGRESO -->
            <div class="bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl flex flex-col gap-8">
                <!-- Progreso Visual -->
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
                <div class="order-2 lg:order-none lg:col-span-12 xl:col-span-5 h-[350px] lg:h-[600px] bg-[#111] border border-white/5 rounded-2xl overflow-hidden relative shadow-2xl">
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
                <div class="order-1 lg:order-none lg:col-span-8 xl:col-span-4 bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl flex flex-col h-[600px]">
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

                        <div v-if="!localParadas?.length" class="text-center text-white/30 text-xs mt-10">
                            Sin paradas asignadas.
                        </div>

                        <!-- START NODE -->
                        <div class="relative z-10 flex gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 border-[#111] text-xs font-black shrink-0 mt-1 bg-white text-black shadow-[0_0_15px_rgba(255,255,255,0.5)]">
                                A
                            </div>
                            <div class="flex-1 bg-white/5 border border-white/10 rounded-xl p-4 opacity-70">
                                <h4 class="font-bold text-white uppercase text-xs mb-1">Punto de Partida</h4>
                                <p class="text-xs text-white/70 whitespace-normal font-medium">📍 San Martin 843, Rosario</p>
                            </div>
                        </div>

                        <!-- Card Parada -->
                        <div v-for="(parada, index) in localParadas" :key="parada.id" 
                             class="relative z-10 flex gap-4 cursor-grab active:cursor-grabbing"
                             :class="{ 'opacity-50 grayscale transition-all duration-500': ['entregada', 'fallida'].includes(parada.estado), 'opacity-25': draggedIndex === index }"
                             draggable="true"
                             @dragstart="onDragStart(index, $event)"
                             @dragenter="onDragEnter($event)"
                             @dragover="onDragOver($event)"
                             @drop="onDrop(index)">
                            <!-- Circular Badge -->
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 border-[#111] text-xs font-black shrink-0 mt-1"
                                 :class="estadoConfig[parada.estado]?.color.split(' ')[1] + ' text-white'">
                                {{ parada.orden }}
                            </div>
                            
                            <!-- Card Content -->
                            <div class="flex-1 bg-white/5 border border-white/10 hover:border-white/20 transition-colors rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-base font-black text-white truncate pr-2">
                                        {{ parada.venta?.cliente?.user?.name || parada.venta?.user?.name || 'Cliente Web' }} {{ parada.venta?.cliente?.user?.apellido || parada.venta?.user?.apellido || '' }}
                                    </h5>
                                    <span class="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md border shrink-0"
                                          :class="estadoConfig[parada.estado]?.color">
                                        {{ estadoConfig[parada.estado]?.label }}
                                    </span>
                                </div>
                                <p class="text-sm text-white/90 mb-3 whitespace-normal font-bold capitalize">
                                    📍 {{ parada.venta?.direccion_envio }}
                                </p>
                                
                                <div class="flex items-center gap-3 text-xs text-white/50 font-black uppercase mb-4">
                                    <span>Venta #{{ parada.venta?.id }}</span>
                                    <span>·</span>
                                    <span>{{ parada.venta?.detalles?.length ?? 0 }} Ítem(s)</span>
                                </div>

                                <div class="flex gap-2">
                                    <button v-if="ruta.estado !== 'finalizada'" @click="abrirEstadoModal(parada)" class="flex-1 py-3 rounded-md bg-white/5 border border-white/10 hover:bg-brand-red hover:border-brand-red hover:text-white transition-all text-[10px] font-black uppercase tracking-widest text-white/70">
                                        Cambiar Estado
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- END NODE -->
                        <div class="relative z-10 flex gap-4 mt-6">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 border-[#111] text-xs font-black shrink-0 mt-1 bg-white text-black shadow-[0_0_15px_rgba(255,255,255,0.5)]">
                                Z
                            </div>
                            <div class="flex-1 bg-white/5 border border-white/10 rounded-xl p-4 opacity-70">
                                <h4 class="font-bold text-white uppercase text-xs mb-1">Punto de Retorno</h4>
                                <p class="text-xs text-white/70 whitespace-normal font-medium">📍 San Martin 843, Rosario</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Formulario & Acciones -->
                <div class="lg:col-span-4 xl:col-span-3 bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl h-fit">
                    
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-4">Datos y Acciones</h4>

                    <div v-if="ruta.estado !== 'finalizada'" class="grid grid-cols-2 gap-2 mb-6">
                        <button @click="showAsignarModal = true" class="py-2.5 rounded-lg border border-white/10 hover:bg-white/5 text-[9px] font-black uppercase tracking-widest text-white/70 transition-colors">
                            Agregar Entrega
                        </button>
                        <button @click="optimizar" class="py-2.5 rounded-lg bg-brand-red hover:bg-red-500 text-[9px] font-black uppercase tracking-widest text-white transition-colors flex items-center justify-center gap-1 shadow-[0_0_15px_rgba(230,25,25,0.3)]">
                            ⚡ Optimizar Ruta
                        </button>
                    </div>

                    <div v-if="ruta.estado !== 'finalizada'" class="w-full h-px bg-white/5 mb-6"></div>

                    <form @submit.prevent="submitEdit" class="space-y-4">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-white/30 mb-1.5">Repartidor Asignado</label>
                            <select v-model="editForm.repartidor_id" :disabled="ruta.estado === 'finalizada' || ruta.activa" class="w-full bg-[#1a1a1a] border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:border-brand-red/50 focus:outline-none appearance-none disabled:opacity-50">
                                <option value="">Sin asignar</option>
                                <option v-for="r in repartidores" :key="r.id" :value="r.id">
                                    {{ r.user?.name }} {{ r.user?.apellido }}
                                </option>
                            </select>
                        </div>
                        <div class="pt-4" v-if="ruta.estado !== 'finalizada'">
                            <button type="submit" :disabled="editForm.processing || ruta.activa" class="w-full btn-primary py-3 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(230,25,25,0.2)]">
                                {{ editForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                            </button>
                        </div>
                    </form>

                    <!-- Iniciar Ruta -->
                    <div class="mt-6 bg-white/5 border border-white/10 rounded-xl p-5 shadow-inner">
                        <h4 class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-3 text-center">Control de Ruta</h4>
                        <div v-if="ruta.activa" class="space-y-3">
                            <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-4 text-center">
                                <span class="text-green-400 text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                                    Ruta En Curso
                                </span>
                            </div>
                            <button type="button" @click="finalizarRuta" class="w-full py-3 bg-[#1a1a1a] border border-white/10 hover:border-brand-red/50 hover:bg-brand-red/10 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex justify-center items-center gap-2">
                                🛑 Finalizar Ruta
                            </button>
                        </div>
                        <div v-else-if="ruta.estado === 'finalizada'" class="bg-white/5 border border-white/10 rounded-xl p-4 text-center">
                            <span class="text-white/40 text-xs font-black uppercase tracking-widest">
                                Ruta Finalizada
                            </span>
                        </div>
                        <div v-else>
                            <button type="button" @click="iniciarRuta" class="w-full py-3 bg-brand-red hover:bg-red-500 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_15px_rgba(230,25,25,0.3)] transition-all flex justify-center items-center gap-2">
                                🚀 Iniciar Ruta
                            </button>
                        </div>
                    </div>
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
                            <button v-for="opt in ['en camino', 'entregada', 'fallida']" :key="opt"
                                    type="button" @click="estadoForm.estado = opt"
                                    class="py-3.5 rounded-lg border text-[10px] font-black uppercase tracking-wider transition-all"
                                    :class="estadoForm.estado === opt ? estadoConfig[opt]?.color + ' border-current scale-95' : 'border-white/10 text-white/30 hover:border-white/30'">
                                {{ estadoConfig[opt]?.label }}
                            </button>
                        </div>

                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-white/40 mb-1">Observaciones</label>
                            <textarea v-model="estadoForm.observaciones" rows="2" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-white/20 focus:outline-none focus:border-brand-red/50 resize-none" placeholder="Motivo de fallo, detalles..."></textarea>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="cerrarEstadoModal" class="flex-1 py-3.5 rounded-lg border border-white/10 text-[10px] font-black uppercase tracking-widest text-white/40 hover:bg-white/5">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="estadoForm.processing" class="flex-1 btn-primary py-3.5 rounded-lg text-[10px] font-black uppercase tracking-widest">
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
