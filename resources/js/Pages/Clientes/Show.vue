<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';
import Swal from 'sweetalert2';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';

const props = defineProps({
    cliente: Object,
    ventas:  Object,
    canceladas: Object,
    acumulados: Array,
    pagos:   Array,
    stats:   Object,
    libro_masters: Array,
    sucursales: Array,
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-clientes',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const urlParams = new URLSearchParams(window.location.search);
const tabActiva = ref(urlParams.get('tabActiva') || 'compras');

const cambiarTab = (tab) => {
    tabActiva.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tabActiva', tab);
        window.history.replaceState({}, '', url);
    }
};

const suscripcionForm = useForm({
    cliente_id: props.cliente.id,
    libro_master_id: '',
    sucursal_id: '',
    tomo_inicio: 1,
});

const showPagoModal = ref(false);
const pagoForm = useForm({
    monto: '',
    metodo_pago: 'Efectivo',
    fecha_real: new Date().toISOString().split('T')[0],
    descripcion: ''
});

const submitPago = () => {
    pagoForm.post(route('clientes.pago', props.cliente.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPagoModal.value = false;
            pagoForm.reset('monto', 'descripcion');
            darkSwal.fire({
                title: '¡Pago registrado!',
                text: 'El pago fue registrado y el saldo actualizado.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
};

const showConsolidarModal = ref(false);
const consolidarForm = useForm({
    direccion_envio: '',
    latitud: null,
    longitud: null,
});

const onSeleccionarDireccionConsolidar = (f) => {
    const [lon, lat] = f.geometry?.coordinates ?? [];
    consolidarForm.latitud  = lat ?? null;
    consolidarForm.longitud = lon ?? null;
};

const submitConsolidar = () => {
    consolidarForm.post(route('clientes.consolidar', props.cliente.id), {
        preserveScroll: true,
        onSuccess: () => {
            showConsolidarModal.value = false;
            consolidarForm.reset();
            darkSwal.fire({
                title: '¡Consolidado!',
                text: 'Los pedidos fueron agrupados para su envío.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
};

const showEditModal = ref(false);
const editForm = useForm({
    name: props.cliente.user.name,
    apellido: props.cliente.user.apellido || '',
    email: props.cliente.user.email,
    dni: props.cliente.user.dni || '',
    telefono: props.cliente.user.telefono || '',
});

const openEditModal = () => {
    editForm.name = props.cliente.user.name;
    editForm.apellido = props.cliente.user.apellido || '';
    editForm.email = props.cliente.user.email;
    editForm.dni = props.cliente.user.dni || '';
    editForm.telefono = props.cliente.user.telefono || '';
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('clientes.update', props.cliente.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            darkSwal.fire({
                title: '¡Cliente actualizado!',
                text: 'Los datos del cliente han sido modificados.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
};

const eliminarPago = (pago) => {
    darkSwal.fire({
        title: '¿Anular este pago?',
        text: 'Se eliminará el registro y se descontará del saldo actual del cliente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clientes.pago.destroy', { cliente: props.cliente.id, transaccion: pago.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Anulado',
                        text: 'El pago ha sido anulado correctamente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const eliminarVenta = (ventaId) => {
    darkSwal.fire({
        title: '¿Eliminar venta permanentemente?',
        text: 'Esta acción borrará la venta cancelada de la base de datos de forma permanente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('ventas.destroy', ventaId), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminada',
                        text: 'La venta ha sido eliminada permanentemente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const eliminarTodasCanceladas = () => {
    darkSwal.fire({
        title: '¿Eliminar todas las canceladas?',
        text: 'Esta acción borrará permanentemente TODAS las ventas canceladas de este cliente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar todas',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clientes.ventas-canceladas.destroy', props.cliente.id), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminadas',
                        text: 'Todas las ventas canceladas han sido eliminadas.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const showNuevaSuscripcion = ref(false);

const suscribir = () => {
    suscripcionForm.post(route('suscripciones.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            suscripcionForm.reset('libro_master_id', 'sucursal_id');
            showNuevaSuscripcion.value = false;
            cambiarTab('suscripciones');
            darkSwal.fire({
                title: '¡Suscripción Creada!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
        onError: () => {
            darkSwal.fire({
                title: 'Error',
                text: 'El cliente ya está suscrito a esta serie o hubo un error.',
                icon: 'error'
            });
        }
    });
};

const eliminarSuscripcion = (suscripcion) => {
    darkSwal.fire({
        title: '¿Dar de baja suscripción?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('suscripciones.destroy', suscripcion.id), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminada',
                        text: 'La suscripción ha sido dada de baja.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const toggleSuscripcion = (susc) => {
    const nuevoEstado = susc.estado === 'activa' ? 'pausada' : 'activa';
    router.patch(route('suscripciones.update', susc.id), { estado: nuevoEstado }, {
        preserveScroll: true,
        onSuccess: () => {
            darkSwal.fire({
                title: '¡Actualizada!',
                text: `La suscripción ha sido ${nuevoEstado === 'activa' ? 'activada' : 'pausada'}.`,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
};

const formatCurrency = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });

const triggerDatePicker = (e) => {
    if (e && e.currentTarget && typeof e.currentTarget.showPicker === 'function') {
        try { e.currentTarget.showPicker(); } catch (err) {}
    }
};

const estadoConfig = {
    pendiente_pago:     { label: 'Pendiente de pago',  bgDot: 'bg-amber-400' },
    en_preventa:        { label: 'Esperando preventa', bgDot: 'bg-fuchsia-400' },
    en_preparacion:     { label: 'En preparación',     bgDot: 'bg-sky-400' },
    esperando_traslado: { label: 'Esperando traslado', bgDot: 'bg-purple-400' },
    listo_para_retiro:  { label: 'Listo para retiro',  bgDot: 'bg-emerald-400' },
    enviado:            { label: 'Enviado',             bgDot: 'bg-indigo-400' },
    entregado:          { label: 'Entregado',           bgDot: 'bg-emerald-400' },
    retirado:           { label: 'Retirado',            bgDot: 'bg-emerald-400' },
    completada:         { label: 'Completada',          bgDot: 'bg-emerald-400' },
    finalizado:         { label: 'Finalizado',          bgDot: 'bg-emerald-400' },
    cancelado:          { label: 'Cancelado',           bgDot: 'bg-rose-500' },
};
</script>

<template>
    <Head :title="`Historial — ${cliente.user.name} ${cliente.user.apellido}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-clientes">
                <div class="flex items-center gap-3">
                    <Link :href="route('clientes.index')" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-white tracking-tight uppercase">
                            SEGUIMIENTO DE CLIENTE
                        </h2>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showPagoModal = true" class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95">
                        Registrar Pago
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 page-clientes">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Ficha del cliente Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="p-6 border-b border-white/5 bg-white/[0.01]">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-2">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-2xl font-bold text-white tracking-tight capitalize">
                                        {{ cliente.user.name }} {{ cliente.user.apellido }}
                                    </h3>
                                </div>
                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-xs text-zinc-400 font-medium">
                                    <span class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                        {{ cliente.user.email }}
                                    </span>
                                    <span v-if="cliente.user.dni" class="flex items-center gap-2 font-mono font-semibold text-zinc-300">
                                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 00-3 3h6a3 3 0 00-3-3z" /></svg>
                                        DNI: {{ cliente.user.dni }}
                                    </span>
                                    <span v-if="cliente.user.telefono" class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        {{ cliente.user.telefono }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-wrap md:flex-nowrap gap-2">
                                <a :href="route('clientes.pdf', cliente.id)" target="_blank" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold border border-white/10 transition-all flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-4 h-4 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>Estado de Cuenta (PDF)</span>
                                </a>
                                <button @click="openEditModal" class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 rounded-xl text-xs font-semibold border border-white/10 transition-all">
                                    Editar Datos
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-3 divide-x divide-white/5 text-center">
                        <div class="p-6">
                            <p class="text-3xl font-bold text-white">{{ stats.cantidad_ventas }}</p>
                            <p class="text-xs uppercase font-semibold text-zinc-400 mt-1">Compras</p>
                        </div>
                        <div class="p-6">
                            <p class="text-3xl font-bold font-mono" :class="cliente.saldo_actual < 0 ? 'text-rose-400' : 'text-emerald-400'">{{ formatCurrency(cliente.saldo_actual) }}</p>
                            <p class="text-xs uppercase font-semibold text-zinc-400 mt-1">Balance de Cuenta</p>
                        </div>
                        <div class="p-6">
                            <p class="text-xl sm:text-2xl font-bold text-white py-1">{{ stats.ultima_compra ? formatFecha(stats.ultima_compra) : 'N/A' }}</p>
                            <p class="text-xs uppercase font-semibold text-zinc-400 mt-1">Última Compra</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-2 shadow-xl">
                    <div class="flex items-center gap-2 overflow-x-auto">
                        <button
                            @click="cambiarTab('compras')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                            :class="tabActiva === 'compras' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            COMPRAS ({{ stats.cantidad_ventas }})
                        </button>
                        <button
                            @click="cambiarTab('pagos')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                            :class="tabActiva === 'pagos' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            PAGOS ({{ pagos.length }})
                        </button>
                        <button
                            @click="cambiarTab('acumulados')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                            :class="tabActiva === 'acumulados' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            ACUMULADOS ({{ acumulados.length }})
                        </button>
                        <button
                            @click="cambiarTab('canceladas')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                            :class="tabActiva === 'canceladas' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            CANCELADAS ({{ canceladas.total }})
                        </button>
                        <button
                            @click="cambiarTab('suscripciones')"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                            :class="tabActiva === 'suscripciones' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                        >
                            SUSCRIPCIONES ({{ cliente.suscripciones?.length || 0 }})
                        </button>
                    </div>
                </div>

                <!-- Tab: Compras -->
                <div v-if="tabActiva === 'compras'">
                    <div v-if="ventas.data.length === 0" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                        Este cliente no tiene compras registradas.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="venta in ventas.data"
                            :key="venta.id"
                            class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 border-b border-white/5 bg-white/[0.01]">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id, return_client: cliente.id })" class="text-white hover:text-zinc-300 text-xs font-mono font-bold hover:underline transition-colors" title="Ver en historial de ventas">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                        <span class="w-2 h-2 rounded-full shrink-0" :class="estadoConfig[venta.estado]?.bgDot || 'bg-zinc-500'"></span>
                                        <span>{{ estadoConfig[venta.estado]?.label ?? venta.estado }}</span>
                                    </span>
                                    <span class="text-xs text-zinc-400 font-semibold">{{ formatFecha(venta.fecha) }}</span>
                                    <span v-if="venta.sucursal" class="text-xs text-zinc-500 font-semibold">{{ venta.sucursal.nombre }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <p class="text-base font-bold text-white">{{ formatCurrency(venta.total) }}</p>
                                    <a :href="route('ventas.comprobante-pdf', venta.id)" target="_blank" class="p-1.5 text-zinc-400 hover:text-white hover:bg-white/5 rounded-lg border border-white/5 transition-all" title="Descargar Reporte / Seña en PDF">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="p-4 space-y-2 text-sm">
                                <div
                                    v-for="detalle in venta.detalles"
                                    :key="detalle.id"
                                    class="flex justify-between"
                                >
                                    <span class="text-zinc-300 font-medium truncate">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-zinc-500 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                    <span class="font-semibold text-zinc-400 ml-4 shrink-0">{{ formatCurrency(detalle.subtotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación ventas -->
                        <div v-if="ventas.links?.length > 3" class="flex justify-center gap-2 mt-6">
                            <Link
                                v-for="link in ventas.links"
                                :key="link.label"
                                :href="(link.url || '#') + '&tabActiva=compras'"
                                class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                                :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                                v-html="decodeLabel(link.label)"
                            ></Link>
                        </div>
                    </div>
                </div>

                <!-- Tab: Canceladas -->
                <div v-if="tabActiva === 'canceladas'">
                    <div v-if="canceladas.data.length === 0" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                        No hay ventas canceladas para este cliente.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="flex justify-end mb-2">
                            <button @click="eliminarTodasCanceladas" class="px-4 py-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-xl text-xs font-semibold border border-rose-500/20 transition-all">
                                Eliminar todas las canceladas
                            </button>
                        </div>
                        <div
                            v-for="venta in canceladas.data"
                            :key="venta.id"
                            class="bg-[#131316] border border-rose-500/20 rounded-2xl overflow-hidden shadow-xl opacity-80 hover:opacity-100 transition-opacity"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 border-b border-white/5 bg-rose-500/5">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id, return_client: cliente.id })" class="text-zinc-400 hover:text-white text-xs font-mono transition-colors" title="Ver en historial de ventas">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs font-semibold text-rose-400">
                                        <span class="w-2 h-2 rounded-full bg-rose-400 shrink-0"></span>
                                        <span>CANCELADO</span>
                                    </span>
                                    <span class="text-xs text-zinc-400 font-semibold">{{ formatFecha(venta.fecha) }}</span>
                                    <span v-if="venta.sucursal" class="text-xs text-zinc-500 font-semibold">{{ venta.sucursal.nombre }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="text-base font-bold text-rose-400 line-through">{{ formatCurrency(venta.total) }}</p>
                                    <button @click="eliminarVenta(venta.id)" class="p-2 text-zinc-500 hover:text-rose-400 transition-colors" title="Eliminar Permanentemente">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="p-4 space-y-2 text-sm">
                                <div
                                    v-for="detalle in venta.detalles"
                                    :key="detalle.id"
                                    class="flex justify-between"
                                >
                                    <span class="text-zinc-400 truncate">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-zinc-500 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación canceladas -->
                        <div v-if="canceladas.links?.length > 3" class="flex justify-center gap-2 mt-6">
                            <Link
                                v-for="link in canceladas.links"
                                :key="link.label"
                                :href="(link.url || '#') + '&tabActiva=canceladas'"
                                class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                                :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                                v-html="decodeLabel(link.label)"
                            ></Link>
                        </div>
                    </div>
                </div>

                <!-- Tab: Acumulados -->
                <div v-if="tabActiva === 'acumulados'">
                    <div v-if="acumulados.length === 0" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                        No hay pedidos en acumulación para este cliente.
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div class="flex justify-end mb-2">
                            <button @click="showConsolidarModal = true" class="px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                GENERAR ENVÍO CONSOLIDADO ({{ acumulados.length }} TICKETS)
                            </button>
                        </div>
                        
                        <div v-for="venta in acumulados" :key="venta.id" class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 border-b border-white/5 bg-white/[0.01]">
                                <div class="flex items-center gap-3">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id })" class="text-zinc-400 hover:text-white text-xs font-mono transition-colors">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs font-semibold text-emerald-400">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                        <span>Acumulado</span>
                                    </span>
                                    <span class="text-xs text-zinc-400 font-semibold">{{ formatFecha(venta.fecha) }}</span>
                                </div>
                                <p class="text-base font-bold text-white">{{ formatCurrency(venta.total) }}</p>
                            </div>
                            <div class="p-4 space-y-2 text-sm">
                                <div v-for="detalle in venta.detalles" :key="detalle.id" class="flex justify-between">
                                    <span class="text-zinc-300 truncate">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-zinc-500 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pagos -->
                <div v-if="tabActiva === 'pagos'">
                    <div class="flex justify-end mb-3">
                        <a :href="route('clientes.pdf', cliente.id)" target="_blank" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold border border-white/10 transition-all flex items-center gap-1.5 shadow-sm">
                            <svg class="w-4 h-4 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span>Descargar Reporte de Pagos (PDF)</span>
                        </a>
                    </div>

                    <div v-if="pagos.length === 0" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                        Este cliente no tiene pagos registrados.
                    </div>

                    <div v-else class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                        <th class="p-4">Fecha</th>
                                        <th class="p-4">Método</th>
                                        <th class="p-4">Descripción</th>
                                        <th class="p-4 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-sm">
                                    <tr v-for="pago in pagos" :key="pago.id" class="hover:bg-white/[0.02] transition-colors">
                                        <td class="p-4 text-xs font-semibold text-zinc-300">{{ formatFecha(pago.fecha) }}</td>
                                        <td class="p-4">
                                            <span class="px-2.5 py-1 bg-white/5 rounded-xl border border-white/5 text-xs font-semibold text-zinc-300">
                                                {{ pago.metodo_pago }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-xs text-zinc-400 italic">{{ pago.descripcion }}</td>
                                        <td class="p-4 text-right font-bold text-emerald-400">
                                            <div class="flex items-center justify-end gap-3">
                                                <span>{{ formatCurrency(pago.monto) }}</span>
                                                <button @click="eliminarPago(pago)" title="Anular pago" class="p-1 text-zinc-500 hover:text-rose-400 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Suscripciones -->
                <div v-if="tabActiva === 'suscripciones'" class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Suscripciones ({{ cliente.suscripciones?.length || 0 }})
                        </h4>
                        <button 
                            @click="showNuevaSuscripcion = !showNuevaSuscripcion" 
                            class="px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>{{ showNuevaSuscripcion ? 'Cerrar Formulario' : 'Nueva Suscripción' }}</span>
                        </button>
                    </div>

                    <!-- Formulario Desplegable de Suscripción -->
                    <div v-if="showNuevaSuscripcion" class="bg-[#131316] border border-white/10 rounded-2xl p-6 shadow-xl space-y-6">
                        <div class="flex items-center justify-between border-b border-white/5 pb-4">
                            <div>
                                <h4 class="text-sm font-bold uppercase tracking-wider text-white">NUEVA SUSCRIPCIÓN A SERIE</h4>
                                <p class="text-xs text-zinc-400 font-medium">Asociá un título para reservar automáticamente cada nuevo tomo publicado</p>
                            </div>
                            <button type="button" @click="showNuevaSuscripcion = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="suscribir" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            <div class="md:col-span-4 space-y-1 text-left">
                                <label class="block text-xs font-semibold text-zinc-400">SELECCIONE SERIE *</label>
                                <select v-model="suscripcionForm.libro_master_id" required class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:border-white/30 cursor-pointer" :class="{'border-rose-500': suscripcionForm.errors.libro_master_id}">
                                    <option value="" disabled class="bg-[#131316]">-- Selecciona Serie --</option>
                                    <option v-for="lm in libro_masters" :key="lm.id" :value="lm.id" class="bg-[#131316]">{{ lm.titulo }}</option>
                                </select>
                                <p v-if="suscripcionForm.errors.libro_master_id" class="text-rose-400 text-xs font-semibold mt-1">{{ suscripcionForm.errors.libro_master_id }}</p>
                            </div>

                            <div class="md:col-span-2 space-y-1 text-left">
                                <label class="block text-xs font-semibold text-zinc-400">TOMO INICIO *</label>
                                <input v-model.number="suscripcionForm.tomo_inicio" type="number" min="1" required class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold font-mono text-white focus:outline-none focus:border-white/30" placeholder="Ej: 5" />
                                <p v-if="suscripcionForm.errors.tomo_inicio" class="text-rose-400 text-xs font-semibold mt-1">{{ suscripcionForm.errors.tomo_inicio }}</p>
                            </div>

                            <div class="md:col-span-4 space-y-1 text-left">
                                <label class="block text-xs font-semibold text-zinc-400">SUCURSAL DE RETIRO *</label>
                                <select v-model="suscripcionForm.sucursal_id" required class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:border-white/30 cursor-pointer" :class="{'border-rose-500': suscripcionForm.errors.sucursal_id}">
                                    <option value="" disabled class="bg-[#131316]">-- Selecciona Sucursal --</option>
                                    <option v-for="suc in sucursales" :key="suc.id" :value="suc.id" class="bg-[#131316]">{{ suc.nombre }}</option>
                                </select>
                                <p v-if="suscripcionForm.errors.sucursal_id" class="text-rose-400 text-xs font-semibold mt-1">{{ suscripcionForm.errors.sucursal_id }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <button type="submit" class="w-full py-2.5 px-4 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50" :disabled="suscripcionForm.processing">
                                    {{ suscripcionForm.processing ? '...' : 'SUSCRIBIR' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla de Suscripciones -->
                    <div v-if="!cliente.suscripciones?.length" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                        Este cliente no tiene suscripciones registradas.
                    </div>
                    
                    <div v-else class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                        <th class="p-4">Serie</th>
                                        <th class="p-4">Tomo Inicio</th>
                                        <th class="p-4">Sucursal de Retiro</th>
                                        <th class="p-4">Fecha de Alta</th>
                                        <th class="p-4 text-center">Estado</th>
                                        <th class="p-4 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-sm">
                                    <tr v-for="susc in cliente.suscripciones" :key="susc.id" class="hover:bg-white/[0.02] transition-colors" :class="susc.estado === 'pausada' ? 'opacity-50' : ''">
                                        <td class="p-4">
                                            <div class="font-bold text-white capitalize">
                                                {{ susc.serie?.titulo || 'Serie' }}
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-xs font-mono font-bold text-white">
                                                Tomo {{ susc.tomo_inicio || 1 }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-zinc-300 font-semibold text-xs">
                                            {{ susc.sucursal?.nombre || 'Todas' }}
                                        </td>
                                        <td class="p-4 text-zinc-400 font-medium text-xs">
                                            {{ formatFecha(susc.created_at) }}
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                                <span class="w-2 h-2 rounded-full shrink-0" :class="susc.estado === 'activa' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                                                <span class="capitalize">{{ susc.estado }}</span>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <button 
                                                @click="eliminarSuscripcion(susc)" 
                                                class="p-2 text-zinc-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                                title="Dar de baja suscripción"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Pago -->
        <Teleport to="body">
            <div v-if="showPagoModal" class="page-clientes">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showPagoModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-2xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Registrar Pago
                            </h3>
                            <button type="button" @click="showPagoModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        
                        <form @submit.prevent="submitPago" class="p-6 space-y-4">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Monto a pagar *</label>
                                    <div class="relative flex items-center">
                                        <span class="absolute left-4 text-xl font-bold text-zinc-500 pointer-events-none">$</span>
                                        <input 
                                            type="number" 
                                            v-model="pagoForm.monto" 
                                            step="0.01"
                                            min="0.01"
                                            class="w-full bg-[#131316] border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-xl font-bold text-white focus:outline-none focus:border-white/30"
                                            :class="{ 'border-rose-500': pagoForm.errors.monto }"
                                            required
                                        >
                                    </div>
                                    <p v-if="pagoForm.errors.monto" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.monto }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Método de Pago</label>
                                        <select v-model="pagoForm.metodo_pago" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                            <option value="Efectivo" class="bg-[#131316]">Efectivo</option>
                                            <option value="Transferencia" class="bg-[#131316]">Transferencia</option>
                                            <option value="Tarjeta" class="bg-[#131316]">Tarjeta</option>
                                            <option value="Débito" class="bg-[#131316]">Débito</option>
                                            <option value="Mercado Pago" class="bg-[#131316]">Mercado Pago</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha Real *</label>
                                        <input 
                                            type="date" 
                                            v-model="pagoForm.fecha_real" 
                                            @click="triggerDatePicker"
                                            class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer"
                                            :class="{ 'border-rose-500': pagoForm.errors.fecha_real }"
                                            required
                                        >
                                        <p v-if="pagoForm.errors.fecha_real" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.fecha_real }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Observaciones</label>
                                    <input 
                                        type="text" 
                                        v-model="pagoForm.descripcion" 
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': pagoForm.errors.descripcion }"
                                        placeholder="Ref: Transferencia #12345..."
                                    >
                                    <p v-if="pagoForm.errors.descripcion" class="text-rose-400 text-xs font-semibold mt-1">{{ pagoForm.errors.descripcion }}</p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showPagoModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="pagoForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>CONFIRMAR PAGO</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Consolidar Pedidos -->
        <Teleport to="body">
            <div v-if="showConsolidarModal" class="page-clientes">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showConsolidarModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Generar Consolidado
                            </h3>
                            <button @click="showConsolidarModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        
                        <form @submit.prevent="submitConsolidar" class="p-6 space-y-4">
                            <div class="space-y-4">
                                <div class="bg-[#131316] border border-white/5 p-4 rounded-xl">
                                    <p class="text-xs font-semibold text-zinc-400 mb-1">Total a consolidar:</p>
                                    <p class="text-2xl font-bold text-white">{{ acumulados.length }} Tickets</p>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Dirección de Envío Consolidada *</label>
                                    <DireccionAutocomplete
                                        v-model="consolidarForm.direccion_envio"
                                        @select="onSeleccionarDireccionConsolidar"
                                        class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                        :class="{ 'border-rose-500': consolidarForm.errors.direccion_envio }"
                                        placeholder="Ej: San Martín 123, CABA..."
                                    />
                                    <p class="text-xs text-zinc-500 font-medium mt-2">
                                        Ingresá o verificá la dirección a la cual se enviarán todos estos pedidos.
                                    </p>
                                    <p v-if="consolidarForm.errors.direccion_envio" class="text-rose-400 text-xs font-semibold mt-1">{{ consolidarForm.errors.direccion_envio }}</p>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showConsolidarModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="consolidarForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>Confirmar Envío</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Editar Cliente -->
        <Teleport to="body">
            <div v-if="showEditModal" class="page-clientes">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showEditModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Editar Cliente
                            </h3>
                            <button type="button" @click="showEditModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        
                        <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre *</label>
                                    <input v-model="editForm.name" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': editForm.errors.name}" required>
                                    <p v-if="editForm.errors.name" class="text-rose-400 text-xs font-semibold mt-1 block">{{ editForm.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Apellido</label>
                                    <input v-model="editForm.apellido" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': editForm.errors.apellido}">
                                    <p v-if="editForm.errors.apellido" class="text-rose-400 text-xs font-semibold mt-1 block">{{ editForm.errors.apellido }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">DNI / Documento</label>
                                    <input v-model="editForm.dni" @input="editForm.dni = editForm.dni.replace(/[^A-Za-z0-9]/g, '')" type="text" maxlength="20" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-white/30" :class="{'border-rose-500': editForm.errors.dni}">
                                    <p v-if="editForm.errors.dni" class="text-rose-400 text-xs font-semibold mt-1 block">{{ editForm.errors.dni }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono Móvil *</label>
                                    <input v-model="editForm.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': editForm.errors.telefono}" required>
                                    <p v-if="editForm.errors.telefono" class="text-rose-400 text-xs font-semibold mt-1 block">{{ editForm.errors.telefono }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-zinc-400 mb-1">Email de Contacto *</label>
                                <input v-model="editForm.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': editForm.errors.email}" required>
                                <p v-if="editForm.errors.email" class="text-rose-400 text-xs font-semibold mt-1 block">{{ editForm.errors.email }}</p>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showEditModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>GUARDAR CAMBIOS</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-clientes,
.page-clientes * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
