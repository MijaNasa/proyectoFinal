<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import Swal from 'sweetalert2';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    ruta:               Object,
    repartidores:       Array,
    ventas_disponibles: Array,
});

const page = usePage();
const canGestionar = computed(() => {
    return page.props.auth?.esAdmin || page.props.auth?.esGerente || !page.props.auth?.esRepartidor;
});
const isRepartidorAsignado = computed(() => {
    return props.ruta?.repartidor_id && props.ruta.repartidor_id === page.props.auth?.empleado?.id;
});
const canOperar = computed(() => {
    return canGestionar.value || isRepartidorAsignado.value;
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
        container: '!z-[20000]',
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-repartos',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2',
        input: '!bg-zinc-900 !text-white !border !border-white/20 !rounded-xl !p-3 !text-sm focus:!border-white/40'
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
    if (localParadas.value[fromIndex]?.estado === 'entregada' || localParadas.value[index]?.estado === 'entregada') return;
    
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
        title: '¿Finalizar ruta de reparto?',
        text: 'Las ventas ya entregadas quedarán registradas como completadas. Las entregas pendientes, en camino o fallidas volverán a preparación para ser reprogramadas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar ruta',
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
    if (!props.ruta.activa || props.ruta.estado === 'finalizada') return;
    if (parada.estado === 'entregada') return;
    paradaEditando.value = parada;
    estadoForm.estado = parada.estado;
    estadoForm.observaciones = parada.observaciones ?? '';
};

const cerrarEstadoModal = () => { paradaEditando.value = null; estadoForm.reset(); };

const ejecutarSubmitEstado = (parada) => {
    const targetParada = parada || paradaEditando.value;
    if (!targetParada) return;
    estadoForm.patch(
        route('rutas-reparto.actualizar-parada', { rutas_reparto: props.ruta.id, parada: targetParada.id }),
        { 
            preserveScroll: true,
            onSuccess: cerrarEstadoModal 
        }
    );
};

const submitEstado = () => {
    if (!paradaEditando.value) return;
    const currentParada = paradaEditando.value;

    if (estadoForm.estado === 'entregada') {
        const ventaId = currentParada.venta?.id || currentParada.venta_id || '';
        darkSwal.fire({
            title: '¿Confirmar entrega?',
            text: `¿Confirmás la entrega de la venta #${ventaId}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar entrega',
            cancelButtonText: 'Cancelar',
        }).then(result => {
            if (result.isConfirmed) {
                ejecutarSubmitEstado(currentParada);
            }
        });
        return;
    }

    ejecutarSubmitEstado(currentParada);
};

const isOptimizing = ref(false);

const optimizar = () => {
    if (isOptimizing.value) return;
    isOptimizing.value = true;
    router.post(route('rutas-reparto.optimizar', props.ruta.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isOptimizing.value = false;
            paradaEnFocoId.value = null;
            nextTick(() => {
                updateMap();
            });
            darkSwal.fire({
                title: 'Ruta optimizada',
                text: 'Se reorganizaron las paradas pendientes por proximidad.',
                icon: 'success',
                timer: 1600,
                showConfirmButton: false,
            });
        },
        onError: () => {
            isOptimizing.value = false;
        }
    });
};

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
// Modo Reparto (Cockpit / Próxima Parada)
// ──────────────────────────────────────────
const vistaModoReparto = ref(Boolean(props.ruta.activa));
const paradaEnFocoId = ref(null);

const paradaActiva = computed(() => {
    if (localParadas.value.length === 0) return null;
    if (paradaEnFocoId.value) {
        const found = localParadas.value.find(p => p.id === paradaEnFocoId.value);
        if (found) return found;
    }
    const enCamino = localParadas.value.find(p => p.estado === 'en camino');
    if (enCamino) return enCamino;
    const pendiente = localParadas.value.find(p => p.estado === 'pendiente');
    if (pendiente) return pendiente;
    const fallida = localParadas.value.find(p => p.estado === 'fallida');
    if (fallida) return fallida;
    return localParadas.value[localParadas.value.length - 1] || null;
});

const seleccionarParadaEnFoco = (parada) => {
    if (!parada) return;
    paradaEnFocoId.value = parada.id;
    centrarEnParada(parada);
};

const centrarEnParada = (parada) => {
    if (!map || !parada || !parada.latitud || !parada.longitud) return;
    map.flyTo([parada.latitud, parada.longitud], 16, { animate: true, duration: 1 });
};

let marcadorRepartidor = null;

const centrarEnRepartidor = () => {
    if (!map) return;
    if (!navigator.geolocation) {
        darkSwal.fire({
            title: 'GPS no disponible',
            text: 'Tu dispositivo o navegador no tiene acceso a geolocalización.',
            icon: 'info'
        });
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            if (marcadorRepartidor) {
                marcadorRepartidor.setLatLng([lat, lng]);
            } else {
                const icon = L.divIcon({
                    className: 'repartidor-gps-marker',
                    html: `
                        <div class="relative flex items-center justify-center w-8 h-8">
                            <span class="absolute w-8 h-8 rounded-full bg-blue-500/40 animate-ping"></span>
                            <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs shadow-lg border-2 border-white">
                                🛵
                            </div>
                        </div>
                    `,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                });
                marcadorRepartidor = L.marker([lat, lng], { icon, zIndexOffset: 2000 }).addTo(map);
            }

            map.flyTo([lat, lng], 16, { animate: true, duration: 1 });
        },
        () => {
            darkSwal.fire({
                title: 'Ubicación actual',
                text: 'No se pudo obtener tu ubicación. Verificá que el GPS esté activo y tengas los permisos concedidos.',
                icon: 'info'
            });
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
    );
};

const obtenerTelefonoCliente = (venta) => {
    if (!venta) return null;
    const tel = venta.cliente?.user?.telefono || venta.user?.telefono;
    if (!tel) return null;
    return String(tel).trim();
};

const cleanPhoneForWhatsApp = (tel) => {
    if (!tel) return '';
    let clean = tel.replace(/\D/g, '');
    if (clean.startsWith('0')) clean = clean.substring(1);
    if (!clean.startsWith('54') && clean.length >= 10) clean = '549' + clean;
    return clean;
};

const getGoogleMapsUrl = (parada) => {
    if (!parada) return '#';
    if (parada.latitud && parada.longitud) {
        return `https://www.google.com/maps/dir/?api=1&destination=${parada.latitud},${parada.longitud}`;
    }
    const dir = parada.venta?.direccion_envio ? splitDireccion(parada.venta.direccion_envio).principal : '';
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(dir)}`;
};

const getWazeUrl = (parada) => {
    if (!parada) return '#';
    if (parada.latitud && parada.longitud) {
        return `https://waze.com/ul?ll=${parada.latitud},${parada.longitud}&navigate=yes`;
    }
    const dir = parada.venta?.direccion_envio ? splitDireccion(parada.venta.direccion_envio).principal : '';
    return `https://waze.com/ul?q=${encodeURIComponent(dir)}&navigate=yes`;
};

const marcarEnCaminoDirecto = (parada) => {
    if (!parada || !props.ruta.activa) return;
    router.patch(
        route('rutas-reparto.actualizar-parada', { rutas_reparto: props.ruta.id, parada: parada.id }),
        { estado: 'en camino' },
        {
            preserveScroll: true,
            onSuccess: () => {
                darkSwal.fire({
                    title: '¡En camino!',
                    text: `Iniciaste el recorrido hacia la parada #${parada.orden}.`,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                });
            }
        }
    );
};

const marcarEntregadaDirecto = (parada) => {
    if (!parada || !props.ruta.activa) return;
    const ventaId = parada.venta?.id || parada.venta_id || '';
    darkSwal.fire({
        title: '¿Confirmar entrega?',
        text: `¿Confirmás la entrega de la venta #${ventaId}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar entrega',
        cancelButtonText: 'Cancelar',
    }).then(result => {
        if (result.isConfirmed) {
            router.patch(
                route('rutas-reparto.actualizar-parada', { rutas_reparto: props.ruta.id, parada: parada.id }),
                { estado: 'entregada' },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        paradaEnFocoId.value = null;
                        darkSwal.fire({
                            title: '¡Entrega confirmada!',
                            text: `La venta #${ventaId} se registró como entregada.`,
                            icon: 'success',
                            timer: 1600,
                            showConfirmButton: false,
                        });
                    }
                }
            );
        }
    });
};

const reportarFallidaDirecto = (parada) => {
    if (!parada || !props.ruta.activa) return;
    const ventaId = parada.venta?.id || parada.venta_id || '';
    darkSwal.fire({
        title: '¿Reportar entrega fallida?',
        text: `¿Deseas marcar como fallida la entrega de la venta #${ventaId}? Volverá a preparación.`,
        input: 'textarea',
        inputPlaceholder: 'Comentario u observación (opcional)...',
        showCancelButton: true,
        confirmButtonText: 'Sí, marcar fallida',
        cancelButtonText: 'Cancelar',
    }).then(result => {
        if (result.isConfirmed) {
            router.patch(
                route('rutas-reparto.actualizar-parada', { rutas_reparto: props.ruta.id, parada: parada.id }),
                { estado: 'fallida', observaciones: result.value ? result.value.trim() : null },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        paradaEnFocoId.value = null;
                    }
                }
            );
        }
    });
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
            const isActiva = Boolean(paradaActiva.value && p.id === paradaActiva.value.id && vistaModoReparto.value);
            const iconHtml = `
                <div class="relative flex items-center justify-center">
                    ${isActiva ? '<span class="absolute w-8 h-8 rounded-full bg-emerald-400/40 animate-ping"></span>' : ''}
                    <div class="relative w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] text-black shadow-xl border ${isActiva ? 'border-2 border-white ring-2 ring-emerald-400 scale-110' : 'border-black'}"
                         style="background-color: ${color};">
                        ${p.estado === 'entregada' ? '✓' : p.orden}
                    </div>
                </div>
            `;
            const icon = L.divIcon({
                html: iconHtml,
                className: '',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });

            const marker = L.marker([p.latitud, p.longitud], { icon }).addTo(map);
            const { principal, obs } = splitDireccion(p.venta?.direccion_envio);
            marker.bindPopup(`<b class="text-black uppercase text-xs">Parada ${p.orden}</b><br><span class="text-black text-xs">${principal}</span>${obs ? `<br><span class="text-amber-600 text-xs font-bold">🔔 ${obs}</span>` : ''}`);
            marker.on('click', () => {
                if (vistaModoReparto.value) {
                    seleccionarParadaEnFoco(p);
                }
            });
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
    nextTick(() => {
        initMap();
    });
});

watch(localParadas, () => {
    nextTick(() => updateMap());
}, { deep: true });

watch(vistaModoReparto, () => {
    nextTick(() => {
        if (map) map.invalidateSize();
    });
});

watch(paradaActiva, () => {
    nextTick(() => {
        updateMap();
    });
});
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
                            <div v-if="ruta.estado === 'pendiente' && canGestionar" class="inline-block relative">
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

                            <!-- Switcher de Modo de Vista cuando la ruta está activa -->
                            <div v-if="ruta.activa" class="flex items-center bg-black/40 border border-white/10 rounded-xl p-1 shrink-0">
                                <button 
                                    type="button"
                                    @click="vistaModoReparto = true" 
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                    :class="vistaModoReparto ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white'"
                                    title="Vista ergonómica para repartidor centrada en la próxima entrega"
                                >
                                    <span>🛵</span>
                                    <span>Próxima Parada</span>
                                </button>
                                <button 
                                    type="button"
                                    @click="vistaModoReparto = false" 
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                    :class="!vistaModoReparto ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white'"
                                    title="Ver lista completa de entregas"
                                >
                                    <span>📋</span>
                                    <span>Lista Completa</span>
                                </button>
                            </div>
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
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    
                    <!-- Columna Izquierda: Mapa -->
                    <div class="lg:col-span-6 h-[460px] lg:h-[620px] bg-[#131316] border border-white/5 rounded-2xl overflow-hidden relative shadow-xl lg:sticky lg:top-6">
                        <div ref="mapContainer" class="w-full h-full z-0"></div>

                        <!-- Botones flotantes sobre el mapa en Modo Reparto -->
                        <div v-if="vistaModoReparto && paradaActiva" class="absolute bottom-4 left-4 z-[400] flex flex-wrap items-center gap-2">
                            <button 
                                type="button" 
                                @click="centrarEnRepartidor"
                                class="px-3.5 py-2.5 bg-[#0d0d0f]/90 hover:bg-[#18181b] backdrop-blur-md border border-white/10 text-white rounded-xl text-xs font-bold shadow-xl transition-all flex items-center gap-2 active:scale-95 cursor-pointer"
                                title="Centrar el mapa en mi ubicación actual"
                            >
                                <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                                <span>Centrar en repartidor</span>
                            </button>
                            <a 
                                :href="getGoogleMapsUrl(paradaActiva)"
                                target="_blank"
                                class="px-3.5 py-2.5 bg-[#0d0d0f]/90 hover:bg-[#18181b] text-zinc-300 hover:text-white backdrop-blur-md border border-white/10 rounded-xl text-xs font-bold shadow-xl transition-all flex items-center gap-2 active:scale-95"
                                title="Abrir en Google Maps si falla el mapa del sistema"
                            >
                                <span>🗺️</span>
                                <span>Google Maps</span>
                            </a>
                        </div>
                        
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

                    <!-- Columna Derecha: Según Modo Reparto o Lista Clásica -->
                    <div class="lg:col-span-6">
                        <!-- VISTA A: MODO REPARTO (Próxima Parada) -->
                        <div v-if="vistaModoReparto && ruta.activa">
                            <!-- All Completed Banner -->
                            <div v-if="counts.total > 0 && counts.entregadas === counts.total" class="p-8 bg-gradient-to-b from-emerald-500/10 to-[#131316] border border-emerald-500/20 rounded-2xl text-center space-y-4 shadow-2xl flex flex-col items-center justify-center h-auto lg:h-[620px]">
                                <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 text-3xl flex items-center justify-center mx-auto shadow-inner">
                                    🎉
                                </div>
                                <div>
                                    <h3 class="text-xl font-extrabold text-white uppercase tracking-tight">¡Todas las entregas completadas!</h3>
                                    <p class="text-xs text-zinc-400 mt-1 font-medium">Has finalizado con éxito todas las paradas de este reparto.</p>
                                </div>
                                <button 
                                    v-if="ruta.activa && canOperar" 
                                    @click="finalizarRuta" 
                                    type="button" 
                                    class="w-full max-w-sm py-3.5 bg-red-600/90 hover:bg-red-600 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>FINALIZAR / CERRAR RUTA</span>
                                </button>
                                <button 
                                    v-else-if="ruta.estado === 'finalizada'" 
                                    disabled 
                                    type="button" 
                                    class="w-full max-w-sm py-3.5 bg-zinc-800/80 border border-white/10 text-zinc-400 font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed flex items-center justify-center gap-2 select-none opacity-80"
                                >
                                    <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>RUTA FINALIZADA</span>
                                </button>
                            </div>

                            <!-- Próxima Parada Card -->
                            <div v-else-if="paradaActiva" class="bg-[#131316] border border-white/5 rounded-2xl p-5 sm:p-6 shadow-xl flex flex-col justify-between h-auto lg:h-[620px] overflow-y-auto space-y-4">
                                <!-- Sección Superior: Header, Dirección, WhatsApp, Obs, Artículos -->
                                <div class="space-y-4">
                                    <!-- Header de la parada -->
                                    <div class="flex items-center justify-between gap-2 pb-3 border-b border-white/5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="px-3 py-1 bg-white/5 text-zinc-300 border border-white/10 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-2 shrink-0">
                                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                                <span>PARADA #{{ paradaActiva.orden }}</span>
                                            </span>
                                            <span class="px-2.5 py-1 bg-white/5 text-zinc-300 border border-white/10 rounded-xl text-xs font-bold whitespace-nowrap shrink-0">
                                                Venta #{{ paradaActiva.venta?.id }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <!-- Botón optimizar ruta idéntico al de lista completa -->
                                            <button 
                                                v-if="canOperar && localParadas.filter(p => p.estado !== 'entregada').length > 1" 
                                                @click="optimizar" 
                                                :disabled="isOptimizing"
                                                type="button"
                                                class="px-3.5 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl transition-all shadow-md active:scale-95 flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                                                title="Optimizar el orden de las entregas inteligentemente usando proximidad geográfica"
                                            >
                                                <svg class="w-3.5 h-3.5 text-purple-200" :class="{'animate-spin': isOptimizing}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <span>Optimizar Ruta</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Dirección & Destinatario -->
                                    <div class="space-y-2">
                                        <div>
                                            <span class="text-[10px] uppercase font-bold tracking-wider text-zinc-400 block mb-0.5">Dirección de Entrega</span>
                                            <h3 class="text-base sm:text-lg font-bold text-white leading-snug">
                                                {{ splitDireccion(paradaActiva.venta?.direccion_envio).principal }}
                                            </h3>
                                        </div>

                                        <!-- Misma línea: Destinatario, Envío, Enviar mensaje al cliente (WhatsApp) -->
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-zinc-300 pt-0.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-zinc-500">Destinatario:</span>
                                                <strong class="text-white">{{ obtenerNombreCliente(paradaActiva.venta) }}</strong>
                                            </div>
                                            <div v-if="paradaActiva.venta?.tipo_envio" class="flex items-center gap-1.5">
                                                <span class="text-zinc-500">Envío:</span>
                                                <span class="text-zinc-200 capitalize">{{ paradaActiva.venta.tipo_envio }}</span>
                                            </div>
                                            <div v-if="obtenerTelefonoCliente(paradaActiva.venta)" class="flex items-center">
                                                <a 
                                                    :href="`https://wa.me/${cleanPhoneForWhatsApp(obtenerTelefonoCliente(paradaActiva.venta))}`" 
                                                    target="_blank" 
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 rounded-lg border border-emerald-500/20 text-[11px] font-semibold transition-all cursor-pointer"
                                                    title="Enviar mensaje de WhatsApp al cliente"
                                                >
                                                    <span class="text-xs">💬</span>
                                                    <span>Enviar mensaje al cliente</span>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Observación de entrega si existe -->
                                        <div v-if="splitDireccion(paradaActiva.venta?.direccion_envio).obs" class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-2.5 flex items-start gap-2 text-xs text-amber-300 font-medium">
                                            <span class="text-sm leading-none">🔔</span>
                                            <div class="leading-relaxed">
                                                <strong class="font-bold text-amber-200">Indicaciones:</strong> {{ splitDireccion(paradaActiva.venta?.direccion_envio).obs }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Artículos a entregar (Siempre visible y sin portada) -->
                                    <div class="bg-[#0d0d0f] border border-white/5 rounded-xl p-3 space-y-2.5">
                                        <div class="flex items-center justify-between text-xs pb-2 border-b border-white/5">
                                            <span class="font-bold text-zinc-300 flex items-center gap-2">
                                                <span>📦</span>
                                                <span>Artículos a entregar</span>
                                            </span>
                                            <span class="bg-white/10 text-white font-bold px-2 py-0.5 rounded-md text-[11px]">
                                                {{ contarItemsVenta(paradaActiva.venta) }} unid.
                                            </span>
                                        </div>
                                        <div class="space-y-1.5 max-h-40 overflow-y-auto pr-0.5">
                                            <div 
                                                v-for="d in paradaActiva.venta?.detalles" 
                                                :key="d.id" 
                                                class="flex items-center justify-between gap-3 text-xs py-1.5 px-2.5 rounded-lg bg-white/[0.02] border border-white/5"
                                            >
                                                <div class="truncate flex-1 min-w-0">
                                                    <span class="font-semibold text-white truncate block">{{ formatNombreLibro(d.libro) }}</span>
                                                    <span class="text-[10px] text-zinc-500 font-mono" v-if="d.libro?.isbn">ISBN: {{ d.libro.isbn }}</span>
                                                </div>
                                                <span class="px-2 py-0.5 bg-white/10 text-white font-bold text-xs rounded shrink-0">
                                                    x{{ d.cantidad }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección Inferior: Acciones de Entrega y Cierre de Ruta -->
                                <div class="pt-3 border-t border-white/5 space-y-3">
                                    <div v-if="paradaActiva.estado === 'entregada'" class="p-3 bg-white/[0.03] border border-white/10 rounded-xl text-center text-xs font-bold text-zinc-300 flex items-center justify-center gap-2">
                                        <span class="text-emerald-400">✓</span>
                                        <span>Esta venta ya fue registrada como entregada</span>
                                    </div>

                                    <div v-else class="space-y-2.5">
                                        <!-- Si está pendiente, botón para iniciar viaje -->
                                        <button 
                                            v-if="paradaActiva.estado === 'pendiente'" 
                                            @click="marcarEnCaminoDirecto(paradaActiva)" 
                                            type="button" 
                                            class="w-full py-3 bg-white/5 hover:bg-white/10 text-white border border-white/10 font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                                            <span>Iniciar viaje hacia esta parada (En camino)</span>
                                        </button>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <!-- Marcar Entregada -->
                                            <button 
                                                @click="marcarEntregadaDirecto(paradaActiva)" 
                                                type="button" 
                                                class="py-3 bg-white hover:bg-zinc-200 text-black font-extrabold text-xs uppercase tracking-wider rounded-xl transition-all shadow-xl active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                                            >
                                                <span class="text-emerald-600 font-black">✓</span>
                                                <span>Confirmar Entrega</span>
                                            </button>

                                            <!-- Reportar Fallida -->
                                            <button 
                                                @click="reportarFallidaDirecto(paradaActiva)" 
                                                type="button" 
                                                class="py-3 bg-white/5 hover:bg-rose-500/10 text-zinc-400 hover:text-rose-300 border border-white/10 font-bold text-xs uppercase tracking-wider rounded-xl transition-all active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                                            >
                                                <span>⚠️</span>
                                                <span>Reportar Fallida</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Botón para cerrar ruta -->
                                    <div class="pt-1">
                                        <button v-if="ruta.activa && canOperar" @click="finalizarRuta" type="button" class="w-full py-2.5 bg-red-600/80 hover:bg-red-600 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span>FINALIZAR / CERRAR RUTA</span>
                                        </button>
                                        <button v-else-if="ruta.estado === 'finalizada'" disabled type="button" class="w-full py-2.5 bg-zinc-800/80 border border-white/10 text-zinc-400 font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed flex items-center justify-center gap-2 select-none opacity-80">
                                            <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>RUTA FINALIZADA</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- VISTA B: LISTA CLÁSICA (Cuando no está en Modo Reparto o ruta pendiente) -->
                        <div v-else class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex flex-col h-auto lg:h-[620px]">
                            <div class="flex justify-between items-center mb-4 pb-3 border-b border-white/5">
                                <h4 class="text-sm font-bold uppercase tracking-wider text-white">Entregas Asignadas</h4>
                                <div class="flex items-center gap-2">
                                    <button 
                                        v-if="ruta.estado !== 'finalizada' && localParadas.length > 1 && canOperar" 
                                        @click="optimizar" 
                                        type="button"
                                        class="px-3.5 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-bold rounded-xl transition-all shadow-md active:scale-95 flex items-center gap-1.5 cursor-pointer"
                                        title="Optimizar el orden de las entregas inteligentemente usando proximidad geográfica"
                                    >
                                        <svg class="w-3.5 h-3.5 text-purple-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <span>Optimizar Ruta</span>
                                    </button>
                                    <button v-if="ruta.estado === 'pendiente' && canGestionar" @click="showAsignarModal = true" class="px-4 py-2 bg-white hover:bg-zinc-200 text-black text-xs font-bold rounded-xl transition-all shadow-md active:scale-95 cursor-pointer">
                                        + Agregar Entrega
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                                <div v-for="(parada, idx) in localParadas" :key="parada.id"
                                     :draggable="canOperar && ruta.estado !== 'finalizada' && parada.estado !== 'entregada'"
                                     @dragstart="(canOperar && ruta.estado !== 'finalizada' && parada.estado !== 'entregada') && onDragStart(idx, $event)"
                                     @dragend="onDragEnd"
                                     @dragenter="onDragEnter"
                                     @dragover="onDragOver"
                                     @drop="(canOperar && ruta.estado !== 'finalizada' && parada.estado !== 'entregada') && onDrop(idx)"
                                     class="p-4 border rounded-2xl flex items-center justify-between gap-4 transition-all group"
                                     :class="[
                                         parada.estado === 'entregada' 
                                             ? 'opacity-40 bg-white/[0.02] border-white/5 cursor-default' 
                                             : 'bg-[#0d0d0f] border-white/5 hover:border-white/20',
                                         (canOperar && ruta.estado !== 'finalizada' && parada.estado !== 'entregada') ? 'cursor-grab active:cursor-grabbing' : ''
                                     ]">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-7 h-7 rounded-xl flex items-center justify-center font-bold text-xs shrink-0"
                                             :class="parada.estado === 'entregada' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400' : 'bg-white/5 border border-white/10 text-white'">
                                            <span v-if="parada.estado === 'entregada'">✓</span>
                                            <span v-else>{{ parada.orden }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-bold transition-colors truncate"
                                                 :class="parada.estado === 'entregada' ? 'text-zinc-400' : 'text-white group-hover:text-zinc-200'"
                                                 :title="splitDireccion(parada.venta?.direccion_envio).principal">
                                                {{ splitDireccion(parada.venta?.direccion_envio).principal }}
                                            </div>
                                            <div class="text-xs text-zinc-400 font-medium truncate">
                                                <span class="font-bold" :class="parada.estado === 'entregada' ? 'text-zinc-300' : 'text-white'">Venta #{{ parada.venta?.id }}</span> &bull; Cliente: {{ obtenerNombreCliente(parada.venta) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <!-- Si ya fue entregada, badge estático no editable -->
                                        <div v-if="parada.estado === 'entregada'" 
                                             class="px-3 py-1 rounded-xl text-xs font-semibold border bg-emerald-500/10 text-emerald-400 border-emerald-500/20 cursor-default select-none flex items-center gap-1.5 whitespace-nowrap shrink-0"
                                             title="Entrega confirmada (no modificable)">
                                            <span>✓</span>
                                            <span>Entregada</span>
                                        </div>
                                        <!-- Si la ruta está activa y el usuario puede operar -->
                                        <button v-else-if="canOperar && ruta.activa && ruta.estado !== 'finalizada'" 
                                                @click="abrirEstadoModal(parada)" 
                                                class="px-3 py-1 rounded-xl text-xs font-semibold border transition-all cursor-pointer hover:brightness-110 whitespace-nowrap shrink-0" 
                                                :class="estadoConfig[parada.estado]?.color">
                                            {{ estadoConfig[parada.estado]?.label }}
                                        </button>
                                        <!-- Si la ruta aún no se inició o no puede operar -->
                                        <div v-else 
                                             class="px-3 py-1 rounded-xl text-xs font-semibold border transition-all cursor-default select-none whitespace-nowrap shrink-0 opacity-75" 
                                             :class="estadoConfig[parada.estado]?.color"
                                             :title="!ruta.activa ? 'Debes iniciar la ruta de reparto para actualizar estados' : ''">
                                            {{ estadoConfig[parada.estado]?.label }}
                                        </div>

                                        <button @click="abrirVentaDetalle(parada.venta)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all cursor-pointer shrink-0" title="Ver detalle">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </button>
                                        <button v-if="ruta.estado === 'pendiente' && canGestionar && parada.estado !== 'entregada'" @click="quitarParada(parada)" class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all cursor-pointer shrink-0" title="Quitar">
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
                                <button v-if="ruta.estado === 'pendiente' && canOperar" @click="iniciarRuta" class="w-full py-3 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 cursor-pointer">
                                    INICIAR RUTA DE REPARTO
                                </button>
                                <button v-else-if="ruta.activa && canOperar" @click="finalizarRuta" class="w-full py-3 bg-red-600/90 hover:bg-red-600 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>FINALIZAR / CERRAR RUTA</span>
                                </button>
                                <button v-else-if="ruta.estado === 'finalizada'" disabled type="button" class="w-full py-3 bg-zinc-800/80 border border-white/10 text-zinc-400 font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed flex items-center justify-center gap-2 select-none opacity-80">
                                    <svg class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>RUTA FINALIZADA</span>
                                </button>
                            </div>
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

.swal2-container {
    z-index: 20000 !important;
}

.swal2-select {
    background-color: #18181b !important;
    color: #ffffff !important;
    color-scheme: dark !important;
}

.swal2-select option {
    background-color: #18181b !important;
    color: #ffffff !important;
}
</style>
