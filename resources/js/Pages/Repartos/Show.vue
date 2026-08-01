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
    darkSwal.fire({
        title: '¿Quitar entrega?',
        text: `La venta #${parada.venta_id} volverá a estar disponible en preparación.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
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
        darkSwal.fire({
            title: 'Sin repartidor asignado',
            text: 'Debes asignar un repartidor antes de poder iniciar la ruta.',
            icon: 'warning',
        });
        return;
    }
    if (!localParadas.value || localParadas.value.length === 0) {
        darkSwal.fire({
            title: 'Sin paradas asignadas',
            text: 'Para iniciar una ruta debes asignarle al menos una parada.',
            icon: 'warning',
        });
        return;
    }
    darkSwal.fire({
        title: '¿Iniciar ruta de reparto?',
        text: 'Esto cambiará las ventas a estado enviado y notificará el reparto.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, iniciar ruta',
        cancelButtonText: 'Cancelar',
    }).then(result => {
        if (result.isConfirmed) {
            router.post(route('rutas-reparto.iniciar', props.ruta.id), {}, { preserveScroll: true });
        }
    });
};

const finalizarRuta = () => {
    darkSwal.fire({
        title: '¿Finalizar ruta?',
        text: 'Las ventas no entregadas volverán a estar disponibles.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar',
    }).then(result => {
        if (result.isConfirmed) {
            router.post(route('rutas-reparto.finalizar', props.ruta.id), {}, { preserveScroll: true });
        }
    });
};

// ──────────────────────────────────────────
// Asignar Ventas (Multi-selección)
// ──────────────────────────────────────────
const showAsignarModal = ref(false);
const seleccionadas = ref([]);
const ventaSearch = ref('');

const ventasFiltradas = computed(() => {
    if (!ventaSearch.value) return props.ventas_disponibles;
    const q = ventaSearch.value.toLowerCase();
    return props.ventas_disponibles.filter(v =>
        String(v.id).includes(q) ||
        (v.cliente?.user?.name && v.cliente.user.name.toLowerCase().includes(q)) ||
        (v.cliente?.user?.apellido && v.cliente.user.apellido.toLowerCase().includes(q)) ||
        (v.user?.name && v.user.name.toLowerCase().includes(q)) ||
        (v.user?.apellido && v.user.apellido.toLowerCase().includes(q)) ||
        (v.destinatario_envio && v.destinatario_envio.toLowerCase().includes(q)) ||
        (v.direccion_envio && v.direccion_envio.toLowerCase().includes(q))
    );
});

const toggleVenta = (id) => {
    const index = seleccionadas.value.indexOf(id);
    if (index > -1) {
        seleccionadas.value.splice(index, 1);
    } else {
        seleccionadas.value.push(id);
    }
};

const toggleTodasVentas = () => {
    if (seleccionadas.value.length === ventasFiltradas.value.length) {
        seleccionadas.value = [];
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
        darkSwal.fire({
            title: 'Sin selección',
            text: 'Debes seleccionar al menos una entrega para agregar a la ruta.',
            icon: 'info',
        });
        return;
    }
    
    isSubmittingAsignar.value = true;
    const idsParaAgregar = [...seleccionadas.value];
    showAsignarModal.value = false;

    router.post(
        route('rutas-reparto.asignar-venta', props.ruta.id),
        { venta_ids: idsParaAgregar },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmittingAsignar.value = false;
                seleccionadas.value = [];
                ventaSearch.value = '';
                darkSwal.fire({
                    title: 'Entregas agregadas',
                    text: 'Las entregas se agregaron correctamente a la ruta.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            onError: (err) => {
                isSubmittingAsignar.value = false;
                showAsignarModal.value = true;
                console.error("Error al asignar entregas:", err);
                darkSwal.fire({
                    title: 'No se pudo agregar',
                    text: 'Ocurrió un error al agregar las entregas.',
                    icon: 'error',
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
    pendiente:   { label: 'Pendiente', bgDot: 'bg-amber-400', hex: '#facc15', color: 'bg-amber-400/10 text-amber-300 border-amber-400/20' },
    'en camino': { label: 'En camino', bgDot: 'bg-sky-400',   hex: '#60a5fa', color: 'bg-sky-400/10 text-sky-300 border-sky-400/20' },
    entregada:   { label: 'Entregada', bgDot: 'bg-emerald-400', hex: '#4ade80', color: 'bg-emerald-400/10 text-emerald-300 border-emerald-400/20' },
    fallida:     { label: 'Fallida',   bgDot: 'bg-rose-400',   hex: '#f87171', color: 'bg-rose-400/10 text-rose-300 border-rose-400/20' },
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
        map = L.map(mapContainer.value).setView(originCoords, 13);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; CartoDB &copy; OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        const storeIcon = L.divIcon({
            html: `
                <div class="w-8 h-8 rounded-full bg-white border-2 border-black flex items-center justify-center shadow-lg font-bold text-xs">
                    📍
                </div>
            `,
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
        L.marker(originCoords, { icon: storeIcon }).addTo(map).bindPopup('<b class="text-black uppercase text-xs">Depósito Central</b>');

        updateMap();
    } catch (e) {
        console.error("Leaflet init error:", e);
    }
};

const updateMap = () => {
    if (!map) return;
    try {
        markers.forEach(m => map.removeLayer(m));
        markers = [];
        if (polyline) {
            try { map.removeLayer(polyline); } catch(e){}
            polyline = null;
        }

        const validParadas = localParadas.value.filter(p => p.latitud && p.longitud);
        if (validParadas.length === 0) {
            map.setView(originCoords, 13);
            return;
        }

        let latlngs = [];
        if (props.ruta.activa) {
            const targetIndex = validParadas.findIndex(p => ['en camino', 'pendiente'].includes(p.estado));
            let startCoord = originCoords;
            let endCoord = originCoords;

            if (targetIndex !== -1) {
                endCoord = [validParadas[targetIndex].latitud, validParadas[targetIndex].longitud];
                if (targetIndex > 0) {
                    startCoord = [validParadas[targetIndex - 1].latitud, validParadas[targetIndex - 1].longitud];
                }
            } else {
                endCoord = originCoords;
                if (validParadas.length > 0) {
                    startCoord = [validParadas[validParadas.length - 1].latitud, validParadas[validParadas.length - 1].longitud];
                }
            }
            latlngs = [startCoord, endCoord];
        } else {
            latlngs = [originCoords, ...validParadas.map(p => [p.latitud, p.longitud]), originCoords];
        }

        validParadas.forEach((p) => {
            const color = estadoConfig[p.estado]?.hex || '#facc15';
            const iconHtml = `
                <div class="relative w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] text-black shadow-lg"
                     style="background-color: ${color}; border: 1.5px solid ${color};">
                    ${p.orden}
                </div>
            `;
            const icon = L.divIcon({
                html: iconHtml,
                className: '',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const marker = L.marker([p.latitud, p.longitud], { icon }).addTo(map);
            const { principal, obs } = splitDireccion(p.venta?.direccion_envio);
            marker.bindPopup(`<b class="text-black uppercase text-xs">Parada ${p.orden}</b><br><span class="text-black text-xs">${principal}</span>${obs ? `<br><span class="text-amber-600 text-xs font-bold">🔔 ${obs}</span>` : ''}`);
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
                            color: '#ffffff',
                            weight: 3,
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
                    if (map) {
                        if (polyline) { try { map.removeLayer(polyline); } catch(e){} }
                        polyline = L.polyline(latlngs, {
                            color: '#ffffff',
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
        <template #header>
            <div class="flex items-center justify-between w-full page-repartos">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">{{ formatNombreRuta(ruta.nombre) }}</h2>
                </div>
                <Link
                    :href="route('rutas-reparto.index')"
                    class="px-5 py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs border border-white/10 transition-all flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver a rutas</span>
                </Link>
            </div>
        </template>

        <div class="py-8 page-repartos">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Header Info & Progreso Card -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/5 pb-4">
                        <div class="flex items-center gap-3">
                            <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                                {{ formatFecha(ruta.fecha) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div v-if="ruta.estado === 'pendiente'" class="inline-block relative">
                                <select 
                                    v-model="editForm.repartidor_id" 
                                    @change="submitEdit"
                                    class="bg-transparent border border-white/10 rounded-md px-3 py-0 w-56 text-xs text-white font-bold uppercase tracking-wider focus:outline-none focus:border-white/30 cursor-pointer"
                                >
                                    <option value="" class="bg-[#131316]">Sin Repartidor</option>
                                    <option v-for="r in repartidores" :key="r.id" :value="r.id" class="bg-[#131316]">
                                        {{ r.user?.name }} {{ r.user?.apellido }}
                                    </option>
                                </select>
                            </div>
                            <p v-else class="text-xs font-bold text-white uppercase tracking-wider">
                                {{ ruta.repartidor?.user?.name ?? 'Sin Repartidor' }} {{ ruta.repartidor?.user?.apellido ?? '' }}
                            </p>
                            
                            <span v-if="ruta.activa" class="px-3.5 py-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-xl text-xs font-semibold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>En Curso</span>
                            </span>
                            <span v-else-if="ruta.estado === 'finalizada'" class="px-3.5 py-1.5 bg-white/5 text-zinc-300 border border-white/5 rounded-xl text-xs font-semibold">
                                Finalizada
                            </span>
                        </div>
                    </div>

                    <!-- Progreso Visual -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Progreso</span>
                            <span class="text-xs font-semibold text-zinc-400">Total: {{ counts.total }} Paradas</span>
                        </div>
                        
                        <div class="relative w-full h-2 bg-white/5 rounded-full overflow-hidden border border-white/5">
                            <div class="absolute top-0 left-0 h-full bg-emerald-400 rounded-full transition-all duration-700"
                                 :style="{ width: counts.total ? (counts.entregadas / counts.total * 100) + '%' : '0%' }"></div>
                        </div>
                        <div class="flex justify-between mt-3 text-xs font-semibold text-zinc-400">
                            <span>{{ counts.pendientes }} Pendientes</span>
                            <span>{{ counts.en_camino }} En Camino</span>
                            <span>{{ counts.fallidas }} Fallidas</span>
                            <span class="text-emerald-400 font-bold">{{ counts.entregadas }} Entregadas</span>
                        </div>
                    </div>
                </div>

                <!-- Grid Principal: Mapa y Timeline -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    
                    <!-- Columna Izquierda: Mapa -->
                    <div class="lg:col-span-6 h-[450px] bg-[#131316] border border-white/5 rounded-2xl overflow-hidden relative shadow-xl">
                        <div ref="mapContainer" class="w-full h-full z-0"></div>
                        
                        <!-- Leyenda Flotante -->
                        <div class="absolute top-4 right-4 z-[400] bg-[#0d0d0f]/90 backdrop-blur-md border border-white/10 rounded-2xl p-4 shadow-xl">
                            <h5 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Leyenda</h5>
                            <div class="space-y-1.5 text-xs font-medium">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                                    <span class="text-white">Entregada</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                                    <span class="text-white">Pendiente</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span>
                                    <span class="text-white">En camino</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                                    <span class="text-white">Fallida</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Paradas e Interactivos -->
                    <div class="lg:col-span-6 bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex flex-col h-[450px]">
                        <div class="flex justify-between items-center mb-4 pb-3 border-b border-white/5">
                            <h4 class="text-sm font-bold uppercase tracking-wider text-white">Entregas Asignadas</h4>
                            <div class="flex gap-2">
                                <button v-if="ruta.estado === 'pendiente'" @click="showAsignarModal = true" class="px-4 py-2 bg-white hover:bg-zinc-200 text-black text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                                    + Agregar Entrega
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                            <div v-for="(parada, idx) in localParadas" :key="parada.id"
                                 draggable="true"
                                 @dragstart="onDragStart(idx, $event)"
                                 @dragend="onDragEnd"
                                 @dragenter="onDragEnter"
                                 @dragover="onDragOver"
                                 @drop="onDrop(idx)"
                                 class="p-4 bg-[#0d0d0f] border border-white/5 rounded-2xl flex items-center justify-between gap-4 transition-all group hover:border-white/20 cursor-grab active:cursor-grabbing">
                                <div class="flex items-center gap-3">
                                    <div class="w-7 h-7 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center font-bold text-xs text-white shrink-0">
                                        {{ parada.orden }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-white group-hover:text-zinc-200 transition-colors">
                                            {{ splitDireccion(parada.venta?.direccion_envio).principal }}
                                        </div>
                                        <div class="text-xs text-zinc-400 font-medium">
                                            Cliente: {{ obtenerNombreCliente(parada.venta) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button @click="abrirEstadoModal(parada)" class="px-3 py-1 rounded-xl text-xs font-semibold border transition-all" :class="estadoConfig[parada.estado]?.color">
                                        {{ estadoConfig[parada.estado]?.label }}
                                    </button>
                                    <button @click="abrirVentaDetalle(parada.venta)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Ver detalle">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </button>
                                    <button v-if="ruta.estado === 'pendiente'" @click="quitarParada(parada)" class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" title="Quitar">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>

                            <div v-if="localParadas.length === 0" class="h-48 flex items-center justify-center text-zinc-500 text-sm font-semibold italic">
                                No hay entregas asignadas a esta ruta
                            </div>
                        </div>

                        <!-- Footer Iniciar / Finalizar Ruta -->
                        <div class="pt-4 mt-4 border-t border-white/5 flex justify-end gap-3">
                            <button v-if="ruta.estado === 'pendiente'" @click="iniciarRuta" class="w-full py-3 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95">
                                INICIAR RUTA DE REPARTO
                            </button>
                            <button v-if="ruta.activa" @click="finalizarRuta" class="w-full py-3 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95">
                                FINALIZAR RUTA DE REPARTO
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal: Actualizar Estado Parada -->
        <Teleport to="body">
            <div v-if="paradaEditando" class="page-repartos">
                <div class="fixed inset-0 z-[9998] bg-black/90 backdrop-blur-md" @click="cerrarEstadoModal" />
                <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-sm bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Actualizar Estado
                            </h3>
                            <button @click="cerrarEstadoModal" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitEstado" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="opt in ['en camino', 'entregada', 'fallida']" :key="opt"
                                        type="button" @click="estadoForm.estado = opt"
                                        class="py-2.5 rounded-xl border text-xs font-semibold transition-all capitalize"
                                        :class="estadoForm.estado === opt ? estadoConfig[opt]?.color + ' border-current' : 'border-white/10 text-zinc-400 hover:text-white bg-[#131316]'">
                                    {{ estadoConfig[opt]?.label }}
                                </button>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Observaciones</label>
                                <textarea v-model="estadoForm.observaciones" rows="3" class="w-full bg-[#131316] border border-white/10 rounded-xl p-3 text-xs text-white font-medium focus:outline-none focus:border-white/30 resize-none" placeholder="Motivo de fallo, detalles..."></textarea>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="cerrarEstadoModal" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="estadoForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                    <span>Confirmar</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Detalle de Venta / Productos a entregar -->
        <Teleport to="body">
            <div v-if="ventaDetalle" class="page-repartos">
                <div class="fixed inset-0 z-[9998] bg-black/90 backdrop-blur-md" @click="cerrarVentaDetalle" />
                <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                    Detalle de Venta #{{ ventaDetalle.id }}
                                </h3>
                                <p class="text-xs text-zinc-400 font-medium mt-0.5">
                                    Cliente: <span class="text-white font-bold">{{ obtenerNombreCliente(ventaDetalle) }}</span>
                                </p>
                            </div>
                            <button @click="cerrarVentaDetalle" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-5 text-sm">
                            <div class="space-y-3">
                                <div>
                                    <span class="text-xs font-semibold text-zinc-400 block mb-0.5">Dirección</span>
                                    <span class="font-bold text-white text-sm leading-snug block">{{ splitDireccion(ventaDetalle.direccion_envio).principal }}</span>
                                    <p v-if="splitDireccion(ventaDetalle.direccion_envio).obs" class="text-amber-400 text-xs mt-1 font-medium">
                                        🔔 {{ splitDireccion(ventaDetalle.direccion_envio).obs }}
                                    </p>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-white/5">
                                <h4 class="text-xs font-semibold text-zinc-400 mb-3">
                                    Productos a entregar ({{ contarItemsVenta(ventaDetalle) }})
                                </h4>
                                <div class="space-y-2">
                                    <div v-for="d in ventaDetalle.detalles" :key="d.id" class="flex items-center justify-between text-xs text-white font-bold py-2 border-b border-white/5 last:border-none">
                                        <span>{{ formatNombreLibro(d.libro) }}</span>
                                        <span class="text-zinc-400 font-mono">x{{ d.cantidad }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button @click="cerrarVentaDetalle" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Asignar Ventas a Ruta -->
        <Teleport to="body">
            <div v-if="showAsignarModal" class="page-repartos">
                <div class="fixed inset-0 z-[9998] bg-black/90 backdrop-blur-md" @click="cerrarAsignarModal" />
                <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-2xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Agregar Entregas a la Ruta
                            </h3>
                            <button @click="cerrarAsignarModal" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="ventaSearch"
                                    type="text"
                                    placeholder="Buscar por cliente, dirección o #venta..."
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium"
                                />
                            </div>

                            <div class="flex justify-between items-center text-xs text-zinc-400 font-semibold px-1">
                                <span>{{ seleccionadas.length }} entregas seleccionadas</span>
                                <button @click="toggleTodasVentas" class="text-rose-400 hover:underline">
                                    {{ seleccionadas.length === ventasFiltradas.length ? 'Desmarcar todas' : 'Marcar todas' }}
                                </button>
                            </div>

                            <div class="max-h-64 overflow-y-auto space-y-2 pr-1">
                                <div v-for="v in ventasFiltradas" :key="v.id"
                                     @click="toggleVenta(v.id)"
                                     class="p-3.5 bg-[#131316] border border-white/5 rounded-xl flex items-center justify-between cursor-pointer hover:border-white/20 transition-all">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" :checked="seleccionadas.includes(v.id)" class="rounded border-white/10 bg-[#0d0d0f] text-emerald-500 focus:ring-0 h-4 w-4" />
                                        <div>
                                            <div class="text-sm font-bold text-white">Venta #{{ v.id }} — {{ obtenerNombreCliente(v) }}</div>
                                            <div class="text-xs text-zinc-400 font-medium">{{ splitDireccion(v.direccion_envio).principal }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="ventasFiltradas.length === 0" class="p-8 text-center text-zinc-500 italic text-sm">
                                    No hay entregas disponibles en preparación
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button @click="cerrarAsignarModal" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button @click="submitAsignar" :disabled="isSubmittingAsignar || seleccionadas.length === 0" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-30">
                                    <span>AGREGAR ({{ seleccionadas.length }})</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-repartos,
.page-repartos * {
    font-family: 'Montserrat', sans-serif !important;
}

.leaflet-container { background: #0d0d0f !important; }
.leaflet-control-zoom a {
    background-color: #131316 !important;
    color: #fff !important;
    border-color: rgba(255,255,255,0.1) !important;
}
.leaflet-control-zoom a:hover {
    background-color: #27272a !important;
}
</style>
