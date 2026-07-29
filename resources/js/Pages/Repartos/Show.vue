<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
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

const onDragEnd = () => {
    draggedIndex.value = null;
};

const onDragEnter = (event) => {
    event.preventDefault();
};

const onDragOver = (event) => {
    event.preventDefault();
};

const onDrop = (index) => {
    const fromIndex = draggedIndex.value;
    draggedIndex.value = null;
    
    if (fromIndex === null || fromIndex === index) return;
    
    const draggedItem = localParadas.value[fromIndex];
    localParadas.value.splice(fromIndex, 1);
    localParadas.value.splice(index, 0, draggedItem);
    
    // Update local order
    localParadas.value.forEach((p, i) => { p.orden = i + 1; });
    
    // Send to backend
    guardarNuevoOrden();
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

const quitarParada = (parada) => {
    Swal.fire({
        title: '¿Quitar entrega?',
        text: `La venta #${parada.venta_id} volverá a estar disponible en preparación.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
        background: '#111',
        color: '#fff',
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('rutas-reparto.remove-parada', { rutas_reparto: props.ruta.id, parada: parada.id }), {
                preserveScroll: true,
            });
        }
    });
};

const iniciarRuta = () => {
    if (!editForm.repartidor_id) {
        Swal.fire({
            title: 'Sin repartidor asignado',
            text: 'Debes asignar un repartidor antes de poder iniciar la ruta.',
            icon: 'warning',
            confirmButtonColor: '#e61919',
            background: '#111',
            color: '#fff',
        });
        return;
    }
    if (!localParadas.value || localParadas.value.length === 0) {
        Swal.fire({
            title: 'Sin paradas asignadas',
            text: 'Para iniciar una ruta debes asignarle al menos una parada.',
            icon: 'warning',
            confirmButtonColor: '#e61919',
            background: '#111',
            color: '#fff',
        });
        return;
    }
    Swal.fire({
        title: '¿Iniciar ruta de reparto?',
        text: 'Esto cambiará las ventas a estado enviado y notificará el reparto.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, iniciar ruta',
        cancelButtonText: 'Cancelar',
        background: '#111',
        color: '#fff',
    }).then(result => {
        if (result.isConfirmed) {
            router.post(route('rutas-reparto.iniciar', props.ruta.id), {}, { preserveScroll: true });
        }
    });
};

const finalizarRuta = () => {
    Swal.fire({
        title: '¿Finalizar ruta?',
        text: 'Las ventas no entregadas volverán a estar disponibles.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar',
        background: '#111',
        color: '#fff',
    }).then(result => {
        if (result.isConfirmed) {
            router.post(route('rutas-reparto.finalizar', props.ruta.id), {}, { preserveScroll: true });
        }
    });
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

const isSubmittingAsignar = ref(false);

const submitAsignar = () => {
    if (props.ruta.estado === 'finalizada') return;
    if (!seleccionadas.value || seleccionadas.value.length === 0) {
        Swal.fire({
            title: 'Sin selección',
            text: 'Debes seleccionar al menos una entrega para agregar a la ruta.',
            icon: 'info',
            confirmButtonColor: '#e61919',
            background: '#111',
            color: '#fff',
        });
        return;
    }
    
    isSubmittingAsignar.value = true;
    const idsParaAgregar = [...seleccionadas.value];
    showAsignarModal.value = false; // Cerrar desplegable de inmediato

    router.post(
        route('rutas-reparto.asignar-venta', props.ruta.id),
        { venta_ids: idsParaAgregar },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmittingAsignar.value = false;
                seleccionadas.value = [];
                ventaSearch.value = '';
                Swal.fire({
                    title: 'Entregas agregadas',
                    text: 'Las entregas se agregaron correctamente a la ruta.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                    background: '#111',
                    color: '#fff',
                });
            },
            onError: (err) => {
                isSubmittingAsignar.value = false;
                showAsignarModal.value = true;
                console.error("Error al asignar entregas:", err);
                Swal.fire({
                    title: 'No se pudo agregar',
                    text: 'Ocurrió un error al agregar las entregas.',
                    icon: 'error',
                    confirmButtonColor: '#e61919',
                    background: '#111',
                    color: '#fff',
                });
            },
            onFinish: () => {
                isSubmittingAsignar.value = false;
            }
        }
    );
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
    pendiente:   { label: 'Pendiente', bgDot: 'bg-amber-400', hex: '#facc15', color: 'bg-amber-400/20 text-amber-300' },
    'en camino': { label: 'En camino', bgDot: 'bg-sky-400',   hex: '#60a5fa', color: 'bg-sky-400/20 text-sky-300' },
    entregada:   { label: 'Entregada', bgDot: 'bg-emerald-400', hex: '#4ade80', color: 'bg-emerald-400/20 text-emerald-300' },
    fallida:     { label: 'Fallida',   bgDot: 'bg-rose-400',   hex: '#f87171', color: 'bg-rose-400/20 text-rose-300' },
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

const ventaDetalle = ref(null);
const abrirVentaDetalle = (venta) => { ventaDetalle.value = venta; };
const cerrarVentaDetalle = () => { ventaDetalle.value = null; };
const contarItemsVenta = (venta) => {
    if (!venta?.detalles) return 0;
    return venta.detalles.reduce((sum, d) => sum + (d.cantidad || 1), 0);
};

const formatNombreLibro = (libro) => {
    if (!libro) return 'Libro';
    const titulo = libro.master?.titulo ?? 'Libro';
    const tomo = libro.numero_tomo;
    if (tomo && tomo !== 'Único' && tomo !== '0') {
        const tomoClean = String(tomo).replace(/^tomo\s*/i, '');
        return `${titulo} - Tomo ${tomoClean}`;
    }
    return titulo;
};

const obtenerNombreCliente = (v) => {
    if (!v) return 'Cliente';
    if (v.cliente?.user?.name) {
        return `${v.cliente.user.name} ${v.cliente.user.apellido ?? ''}`.trim();
    }
    if (v.user?.name) {
        return `${v.user.name} ${v.user.apellido ?? ''}`.trim();
    }
    if (v.destinatario_envio) {
        return v.destinatario_envio.trim();
    }
    return 'Cliente Web';
};

const limpiarDireccionText = (rawStr) => {
    if (!rawStr) return '';
    let cleaned = rawStr
        .replace(/,?\s*C\.?P\.?\s*[A-Z0-9_-]+/gi, '')
        .replace(/,?\s*\b[A-Z]\d{4}[A-Z]{3}\b/gi, '');
    
    const parts = cleaned.split(',').map(p => p.trim()).filter(Boolean);
    const uniqueParts = [];
    for (const part of parts) {
        if (!uniqueParts.some(p => p.toLowerCase() === part.toLowerCase())) {
            uniqueParts.push(part);
        }
    }
    return uniqueParts.join(', ');
};

const splitDireccion = (direccion) => {
    if (!direccion) return { principal: '', obs: '' };
    const [principal, obs] = direccion.split('|').map(s => s.trim());
    return {
        principal: limpiarDireccionText(principal),
        obs: obs ? obs.replace(/^Obs:\s*/i, '') : ''
    };
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

// ──────────────────────────────────────────
// Mapa Leaflet
// ──────────────────────────────────────────
let map = null;
let markers = [];
let polyline = null;
const mapContainer = ref(null);
const originCoords = [-32.9473682, -60.6364222];

const initMap = () => {
    if (!mapContainer.value) return;
    if (map) return;
    try {
        if (mapContainer.value._leaflet_id) {
            delete mapContainer.value._leaflet_id;
        }
        map = L.map(mapContainer.value, { zoomControl: false }).setView(originCoords, 13);
        
        L.control.zoom({ position: 'topleft' }).addTo(map);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; CartoDB',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        updateMap();
    } catch (err) {
        console.error("Leaflet init error:", err);
    }
};

const updateMap = () => {
    if (!map) return;
    try {
        markers.forEach(m => {
            try { map.removeLayer(m); } catch(e){}
        });
        markers = [];
        if (polyline) {
            try { map.removeLayer(polyline); } catch(e){}
            polyline = null;
        }

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
            const { principal, obs } = splitDireccion(p.venta?.direccion_envio);
            marker.bindPopup(`<b class="text-black uppercase text-[10px]">Parada ${p.orden}</b><br><span class="text-black text-xs">${principal}</span>${obs ? `<br><span class="text-yellow-600 text-xs font-bold">🔔 ${obs}</span>` : ''}`);
            markers.push(marker);
        });

        if (latlngs.length > 1) {
            const coordinatesString = latlngs.map(coord => `${coord[1]},${coord[0]}`).join(';');
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2500);

            fetch(`https://router.project-osrm.org/route/v1/driving/${coordinatesString}?overview=full&geometries=geojson`, { signal: controller.signal })
                .then(res => res.json())
                .then(data => {
                    clearTimeout(timeoutId);
                    if (data.routes && data.routes.length > 0) {
                        const routeCoords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                        if (polyline) { try { map.removeLayer(polyline); } catch(e){} }
                        polyline = L.polyline(routeCoords, {
                            color: '#3b82f6',
                            weight: 4,
                            opacity: 0.8
                        }).addTo(map);
                        if (polyline.getBounds().isValid()) {
                            map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
                        }
                    } else {
                        throw new Error("No routes found");
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    console.error("OSRM error, falling back to straight lines:", err);
                    if (map) {
                        if (polyline) { try { map.removeLayer(polyline); } catch(e){} }
                        polyline = L.polyline(latlngs, {
                            color: '#fff',
                            weight: 2,
                            dashArray: '5, 10',
                            opacity: 0.5
                        }).addTo(map);
                        if (polyline.getBounds().isValid()) {
                            map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
                        }
                    }
                });
        }
    } catch (e) {
        console.error("Leaflet updateMap error:", e);
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
                    <Link
                        :href="route('rutas-reparto.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-white/70 hover:text-white transition-all mb-4 cursor-pointer"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Volver a rutas de reparto
                    </Link>
                    <h2 class="text-3xl font-black uppercase tracking-tighter flex items-center gap-3">
                        {{ formatNombreRuta(ruta.nombre) }}
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
                    <div class="flex justify-between mt-3 px-1 text-xs font-bold uppercase tracking-wider text-white/70">
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
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white/50">Paradas</h4>
                        <div v-if="ruta.estado !== 'finalizada'" class="relative">
                            <button @click.stop="showAsignarModal = !showAsignarModal" class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-white bg-white/10 hover:bg-white/20 border border-white/10 transition-colors flex items-center gap-1.5 cursor-pointer">
                                + Agregar entrega
                            </button>

                            <!-- Backdrop para cerrar al hacer click afuera -->
                            <div v-if="showAsignarModal" class="fixed inset-0 z-[40]" @click="cerrarAsignarModal"></div>

                            <!-- Dropdown Desplegable Simple -->
                            <div v-if="showAsignarModal" @click.stop class="absolute right-0 top-full mt-2 w-80 bg-[#1a1a1a] border border-white/10 rounded-xl overflow-hidden shadow-2xl z-[50] flex flex-col max-h-[420px]">
                                <div class="p-3 border-b border-white/10 bg-[#111] space-y-2">
                                    <div class="relative w-full">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-white/40">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </span>
                                        <input
                                            v-model="ventaSearch"
                                            type="text"
                                            placeholder="Buscar por cliente o dirección..."
                                            class="w-full bg-white/5 border border-white/10 rounded-lg pl-8 pr-2.5 py-1.5 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 placeholder-white/30"
                                        />
                                    </div>
                                    <div v-if="ventasFiltradas.length" class="flex justify-between items-center px-1">
                                        <span class="text-[10px] text-white/50 font-bold uppercase">{{ seleccionadas.length }} seleccionadas</span>
                                        <button type="button" @click="toggleTodas" class="text-[10px] font-bold text-brand-red hover:underline cursor-pointer">
                                            {{ todasSeleccionadas ? 'Desmarcar todas' : 'Marcar todas' }}
                                        </button>
                                    </div>
                                </div>

                                <div class="overflow-y-auto flex-1 p-2 space-y-1">
                                    <div v-if="!ventas_disponibles?.length" class="text-center py-6 text-white/40 text-xs font-bold">
                                        No hay ventas pendientes disponibles
                                    </div>
                                    <div v-else-if="!ventasFiltradas.length" class="text-center py-6 text-white/40 text-xs font-bold">
                                        Sin resultados
                                    </div>
                                    <div
                                        v-for="v in ventasFiltradas"
                                        :key="v.id"
                                        @click="toggleVenta(v.id)"
                                        class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-white/5 cursor-pointer transition-colors"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="seleccionadas.includes(v.id)"
                                            class="mt-0.5 rounded bg-black border-white/20 text-brand-red focus:ring-0 w-3.5 h-3.5 cursor-pointer pointer-events-none"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-bold text-white leading-tight truncate">
                                                Venta #{{ v.id }} - {{ v.cliente?.user?.name ?? 'Cliente' }} {{ v.cliente?.user?.apellido ?? '' }}
                                            </p>
                                            <p class="text-[10px] text-white/50 truncate mt-0.5 font-medium">
                                                📍 {{ splitDireccion(v.direccion_envio).principal }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-2.5 border-t border-white/10 bg-[#111] flex gap-2">
                                    <button
                                        type="button"
                                        @click="cerrarAsignarModal"
                                        class="flex-1 py-1.5 rounded-lg border border-white/10 text-xs font-bold text-white/50 hover:bg-white/5 transition-colors cursor-pointer"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        @click="submitAsignar"
                                        :disabled="!seleccionadas.length || isSubmittingAsignar"
                                        class="flex-1 btn-primary py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                                    >
                                        {{ isSubmittingAsignar ? 'Guardando...' : `Agregar (${seleccionadas.length})` }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto pr-2 space-y-4 relative">
                        <!-- Timeline Line -->
                        <div class="absolute left-[19px] top-4 bottom-4 w-px bg-white/10 z-0"></div>

                        <div v-if="!localParadas?.length" class="text-center text-white/30 text-xs font-medium mt-10">
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
                             class="relative z-10 flex gap-4 cursor-grab active:cursor-grabbing transition-opacity duration-200"
                             :class="{ 'opacity-50 grayscale transition-all duration-500': ['entregada', 'fallida'].includes(parada.estado), 'opacity-25': draggedIndex === index }"
                             draggable="true"
                             @dragstart="onDragStart(index, $event)"
                             @dragend="onDragEnd"
                             @dragenter="onDragEnter($event)"
                             @dragover="onDragOver($event)"
                             @drop="onDrop(index)">
                            <!-- Circular Badge -->
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 border-[#111] text-xs font-black shrink-0 mt-1"
                                 :class="estadoConfig[parada.estado]?.color || 'bg-white/10 text-white'">
                                {{ parada.orden }}
                            </div>
                            
                            <!-- Card Content -->
                            <div class="flex-1 bg-white/5 border border-white/10 hover:border-white/20 transition-colors rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-sm font-bold text-white truncate pr-2">
                                        {{ obtenerNombreCliente(parada.venta) }}
                                    </h5>
                                    <span class="bg-[#1a1a1a] border border-white/10 text-white font-bold text-xs rounded-full px-3 py-1 inline-flex items-center gap-1.5 shrink-0">
                                        <span class="w-2 h-2 rounded-full" :class="estadoConfig[parada.estado]?.bgDot"></span>
                                        {{ estadoConfig[parada.estado]?.label }}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <p class="text-xs text-white/90 whitespace-normal font-bold capitalize">
                                        📍 {{ splitDireccion(parada.venta?.direccion_envio).principal }}
                                    </p>
                                    <p v-if="splitDireccion(parada.venta?.direccion_envio).obs" class="text-xs text-yellow-400/80 mt-1 whitespace-normal font-bold">
                                        🔔 {{ splitDireccion(parada.venta?.direccion_envio).obs }}
                                    </p>
                                </div>

                                <button
                                    @click="abrirVentaDetalle(parada.venta)"
                                    type="button"
                                    class="flex items-center gap-1.5 text-xs text-white/70 hover:text-white font-medium hover:underline mb-4 cursor-pointer transition-colors"
                                    title="Ver detalle de productos a entregar"
                                >
                                    <span>Venta #{{ parada.venta?.id }}</span>
                                    <span>·</span>
                                    <span>{{ contarItemsVenta(parada.venta) }} Ítem(s)</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>

                                <div class="flex gap-2">
                                    <button v-if="ruta.activa && ruta.estado !== 'finalizada'" @click="abrirEstadoModal(parada)" class="flex-1 py-2 rounded-lg bg-white/15 hover:bg-white/25 border border-white/20 text-white font-bold text-xs uppercase tracking-wider transition-all shadow-sm cursor-pointer">
                                        Cambiar Estado
                                    </button>
                                    <button
                                        v-if="!ruta.activa && ruta.estado !== 'finalizada'"
                                        @click="quitarParada(parada)"
                                        class="p-2 rounded-lg bg-white/10 hover:bg-red-500/20 hover:text-brand-red border border-white/10 transition-colors text-white/60 cursor-pointer"
                                        title="Quitar entrega de la ruta"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
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
                <div class="lg:col-span-4 xl:col-span-3 bg-[#111] border border-white/5 rounded-2xl p-6 shadow-2xl h-fit space-y-6">
                    
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white/50 mb-2">Repartidor Asignado</h4>
                        <select
                            v-model="editForm.repartidor_id"
                            @change="submitEdit"
                            :disabled="ruta.estado === 'finalizada' || ruta.activa"
                            class="w-full bg-[#1a1a1a] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs font-bold text-white focus:border-brand-red/50 focus:outline-none cursor-pointer disabled:opacity-50"
                        >
                            <option value="">Sin asignar</option>
                            <option v-for="r in repartidores" :key="r.id" :value="r.id">
                                {{ r.user?.name }} {{ r.user?.apellido }}
                            </option>
                        </select>
                    </div>

                    <div v-if="ruta.estado !== 'finalizada' && localParadas.length > 1">
                        <button
                            @click="optimizar"
                            class="w-full py-2.5 rounded-xl bg-white/15 hover:bg-white/25 border border-white/20 text-xs font-bold text-white transition-all shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                        >
                            ⚡ Optimizar Recorrido
                        </button>
                    </div>

                    <!-- Control de Ruta -->
                    <div class="bg-white/5 border border-white/10 rounded-xl p-5 shadow-inner">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white/50 mb-3 text-center">Control de Ruta</h4>
                        
                        <div v-if="ruta.activa" class="space-y-3">
                            <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-3 text-center">
                                <span class="text-green-400 text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                                    Ruta En Curso
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="finalizarRuta"
                                class="w-full py-2.5 bg-white/10 border border-white/10 hover:bg-brand-red hover:border-brand-red text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex justify-center items-center gap-2 cursor-pointer"
                            >
                                🛑 Finalizar Ruta
                            </button>
                        </div>
                        
                        <div v-else-if="ruta.estado === 'finalizada'" class="bg-white/5 border border-white/10 rounded-xl p-3 text-center">
                            <span class="text-white/40 text-xs font-bold uppercase tracking-wider">
                                Ruta Finalizada
                            </span>
                        </div>
                        
                        <div v-else>
                            <button
                                type="button"
                                @click="iniciarRuta"
                                :disabled="!editForm.repartidor_id || localParadas.length === 0"
                                class="w-full py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors flex justify-center items-center gap-2"
                                :class="(editForm.repartidor_id && localParadas.length > 0) ? 'bg-brand-red hover:bg-red-700 text-white cursor-pointer shadow-md' : 'bg-white/5 text-white/30 border border-white/5 cursor-not-allowed'"
                            >
                                🚀 Iniciar Ruta
                            </button>
                            <p v-if="!editForm.repartidor_id" class="text-[11px] text-white/40 text-center font-medium mt-2">
                                Debes asignar un repartidor para poder iniciar la ruta.
                            </p>
                            <p v-else-if="localParadas.length === 0" class="text-[11px] text-white/40 text-center font-medium mt-2">
                                Debes asignar al menos 1 parada para iniciar el recorrido.
                            </p>
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
                    <h3 class="text-lg font-black uppercase tracking-tighter mb-1 text-white">
                        Actualizar Estado
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

        <!-- Modal: Detalle de Venta / Productos a entregar -->
        <Teleport to="body">
            <div v-if="ventaDetalle" class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="cerrarVentaDetalle" />
                <div class="relative bg-[#141414] border border-white/10 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden flex flex-col">
                    
                    <!-- Header Limpio -->
                    <div class="p-6 border-b border-white/10 flex justify-between items-start bg-[#161616]">
                        <div>
                            <h3 class="text-xl font-bold text-white tracking-tight">
                                Detalle de Venta #{{ ventaDetalle.id }}
                            </h3>
                            <p class="text-sm text-white/60 mt-1 font-medium">
                                Cliente: <span class="text-white font-bold">{{ obtenerNombreCliente(ventaDetalle) }}</span>
                            </p>
                        </div>
                        <button @click="cerrarVentaDetalle" class="text-white/40 hover:text-white p-1.5 rounded-lg hover:bg-white/5 transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Contenido en Fondo Único con Fuente Más Grande -->
                    <div class="p-6 space-y-6 text-sm">
                        <!-- Información de Entrega -->
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <span class="text-base shrink-0 mt-0.5">📍</span>
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-white/40 block mb-0.5">Dirección</span>
                                    <span class="font-bold text-white text-base leading-snug">{{ splitDireccion(ventaDetalle.direccion_envio).principal }}</span>
                                    <p v-if="splitDireccion(ventaDetalle.direccion_envio).obs" class="text-yellow-400 text-sm mt-1.5 font-medium">
                                        🔔 {{ splitDireccion(ventaDetalle.direccion_envio).obs }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="ventaDetalle.destinatario_envio || ventaDetalle.telefono_envio" class="grid grid-cols-2 gap-4 pt-4 border-t border-white/5">
                                <div v-if="ventaDetalle.destinatario_envio">
                                    <span class="text-xs font-bold uppercase tracking-wider text-white/40 block mb-0.5">Recibe</span>
                                    <span class="font-bold text-white text-sm">{{ ventaDetalle.destinatario_envio }}</span>
                                </div>
                                <div v-if="ventaDetalle.telefono_envio">
                                    <span class="text-xs font-bold uppercase tracking-wider text-white/40 block mb-0.5">Teléfono</span>
                                    <span class="font-bold text-white text-sm">{{ ventaDetalle.telefono_envio }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Productos a Entregar (Línea Continua: Titulo - Tomo N (xCant)) -->
                        <div class="pt-4 border-t border-white/10">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-white/40 mb-3">
                                Productos a entregar ({{ contarItemsVenta(ventaDetalle) }})
                            </h4>
                            <div class="space-y-3">
                                <div v-for="d in ventaDetalle.detalles" :key="d.id" class="flex items-center gap-2 text-sm text-white font-bold py-1 border-b border-white/5 last:border-none">
                                    <span class="text-white/30">•</span>
                                    <span class="leading-snug">
                                        {{ formatNombreLibro(d.libro) }}
                                    </span>
                                    <span class="text-white/60 font-black text-sm ml-1 shrink-0">
                                        x{{ d.cantidad }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Limpio -->
                    <div class="p-5 border-t border-white/10 flex justify-end bg-[#161616]">
                        <button @click="cerrarVentaDetalle" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 text-white font-bold text-sm transition-colors cursor-pointer">
                            Cerrar
                        </button>
                    </div>
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
