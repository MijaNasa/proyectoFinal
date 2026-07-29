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
            Swal.fire({
                title: '¡Pago registrado!',
                text: 'El pago fue registrado y el saldo actualizado',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF'
            });
        }
    });
};

const showConsolidarModal = ref(false);
const consolidarForm = useForm({
    direccion_envio: '', // Se permite cargar o editar en el modal
    latitud: null,
    longitud: null,
});

const onSeleccionarDireccionConsolidar = (f) => {
    // GeoJSON: coordinates viene como [lon, lat]
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
            Swal.fire({
                title: '¡Consolidado!',
                text: 'Los pedidos fueron agrupados para su envío.',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF'
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
            Swal.fire({
                title: '¡Cliente actualizado!',
                text: 'Los datos del cliente han sido modificados',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF'
            });
        }
    });
};

const eliminarPago = (pago) => {
    Swal.fire({
        title: '¿Anular este pago?',
        text: 'Se eliminará el registro y se descontará del saldo actual del cliente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clientes.pago.destroy', { cliente: props.cliente.id, transaccion: pago.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Anulado',
                        text: 'El pago ha sido anulado correctamente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        background: '#1A1A1A', color: '#FFF'
                    });
                }
            });
        }
    });
};

const eliminarVenta = (ventaId) => {
    Swal.fire({
        title: '¿Eliminar venta permanentemente?',
        text: 'Esta acción borrará la venta cancelada de la base de datos de forma permanente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('ventas.destroy', ventaId), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminada',
                        text: 'La venta ha sido eliminada permanentemente.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        background: '#1A1A1A', color: '#FFF'
                    });
                }
            });
        }
    });
};

const eliminarTodasCanceladas = () => {
    Swal.fire({
        title: '¿Eliminar todas las canceladas?',
        text: 'Esta acción borrará permanentemente TODAS las ventas canceladas de este cliente de la base de datos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar todas',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('clientes.ventas-canceladas.destroy', props.cliente.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminadas',
                        text: 'Todas las ventas canceladas han sido eliminadas.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        background: '#1A1A1A', color: '#FFF'
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
            Swal.fire({
                title: '¡Suscripción Creada!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                background: '#1A1A1A', color: '#FFF'
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'El cliente ya está suscrito a esta serie o hubo un error.',
                icon: 'error',
                background: '#1A1A1A', color: '#FFF'
            });
        }
    });
};

const eliminarSuscripcion = (suscripcion) => {
    Swal.fire({
        title: '¿Dar de baja suscripción?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('suscripciones.destroy', suscripcion.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminada',
                        text: 'La suscripción ha sido dada de baja.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        background: '#1A1A1A', color: '#FFF'
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
            Swal.fire({
                title: '¡Actualizada!',
                text: `La suscripción ha sido ${nuevoEstado === 'activa' ? 'activada' : 'pausada'}.`,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                background: '#1A1A1A', color: '#FFF'
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
            <div class="flex items-center gap-4">
                <Link :href="route('clientes.index')" class="text-white/30 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </Link>
                <div>
                    <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                        Seguimiento de <span class="text-brand-red not-italic">Cliente</span>
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl sm:px-6 lg:px-8 space-y-8">

                <!-- Ficha del cliente -->
                <div class="card p-0 overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-red/20 to-transparent p-6 border-b border-white/5">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-2">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-2xl font-black uppercase tracking-tighter">
                                        {{ cliente.user.name }} {{ cliente.user.apellido }}
                                    </h3>
                                </div>
                                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/90 font-medium">
                                    <span class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                        {{ cliente.user.email }}
                                    </span>
                                    <span v-if="cliente.user.dni" class="flex items-center gap-2 font-mono font-bold">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 00-3 3h6a3 3 0 00-3-3z" /></svg>
                                        DNI: {{ cliente.user.dni }}
                                    </span>
                                    <span v-if="cliente.user.telefono" class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        {{ cliente.user.telefono }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-wrap md:flex-nowrap gap-2 text-right">
                                <button @click="showPagoModal = true" class="py-2 px-4 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white transition-colors text-xs font-bold uppercase tracking-wider rounded-xl border border-green-500/50 hover:border-transparent cursor-pointer">
                                    Registrar Pago
                                </button>
                                <a :href="route('clientes.pdf', cliente.id)" target="_blank" class="py-2 px-4 bg-white/5 hover:bg-white/10 text-white transition-colors text-xs font-bold uppercase tracking-wider rounded-xl border border-white/10 hover:border-white/20 text-center">
                                    Informe de balance
                                </a>
                                <button @click="openEditModal" class="py-2 px-4 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white transition-colors text-xs font-bold uppercase tracking-wider rounded-xl border border-blue-500/50 hover:border-transparent cursor-pointer">
                                    Editar Datos
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 divide-x divide-white/5">
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black text-white">{{ stats.cantidad_ventas }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Compras</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-3xl font-black italic" :class="cliente.saldo_actual < 0 ? 'text-brand-red' : 'text-green-400'">{{ formatCurrency(cliente.saldo_actual) }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Balance de Cuenta</p>
                        </div>
                        <div class="p-6 text-center">
                            <p class="text-xl sm:text-2xl font-black text-white py-1">{{ stats.ultima_compra ? formatFecha(stats.ultima_compra) : 'N/A' }}</p>
                            <p class="text-[9px] uppercase tracking-widest text-white/30 font-black mt-1">Última Compra</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs (Catálogo / Ajustes style matching user screenshot) -->
                <div class="border-b border-white/10 mb-6">
                    <div class="flex items-center gap-1 overflow-x-auto">
                        <button
                            @click="cambiarTab('compras')"
                            class="px-6 py-3.5 text-xs font-black uppercase tracking-wider transition-all border-b-2 cursor-pointer flex items-center gap-2 shrink-0"
                            :class="tabActiva === 'compras' 
                                ? 'bg-[#1A1A1A] text-white border-brand-red shadow-sm' 
                                : 'bg-transparent text-white/40 border-transparent hover:text-white/80 hover:bg-white/[0.02]'"
                        >
                            COMPRAS ({{ stats.cantidad_ventas }})
                        </button>
                        <button
                            @click="cambiarTab('pagos')"
                            class="px-6 py-3.5 text-xs font-black uppercase tracking-wider transition-all border-b-2 cursor-pointer flex items-center gap-2 shrink-0"
                            :class="tabActiva === 'pagos' 
                                ? 'bg-[#1A1A1A] text-white border-brand-red shadow-sm' 
                                : 'bg-transparent text-white/40 border-transparent hover:text-white/80 hover:bg-white/[0.02]'"
                        >
                            PAGOS ({{ pagos.length }})
                        </button>
                        <button
                            @click="cambiarTab('acumulados')"
                            class="px-6 py-3.5 text-xs font-black uppercase tracking-wider transition-all border-b-2 cursor-pointer flex items-center gap-2 shrink-0"
                            :class="tabActiva === 'acumulados' 
                                ? 'bg-[#1A1A1A] text-white border-brand-red shadow-sm' 
                                : 'bg-transparent text-white/40 border-transparent hover:text-white/80 hover:bg-white/[0.02]'"
                        >
                            ACUMULADOS ({{ acumulados.length }})
                        </button>
                        <button
                            @click="cambiarTab('canceladas')"
                            class="px-6 py-3.5 text-xs font-black uppercase tracking-wider transition-all border-b-2 cursor-pointer flex items-center gap-2 shrink-0"
                            :class="tabActiva === 'canceladas' 
                                ? 'bg-[#1A1A1A] text-white border-brand-red shadow-sm' 
                                : 'bg-transparent text-white/40 border-transparent hover:text-white/80 hover:bg-white/[0.02]'"
                        >
                            CANCELADAS ({{ canceladas.total }})
                        </button>
                        <button
                            @click="cambiarTab('suscripciones')"
                            class="px-6 py-3.5 text-xs font-black uppercase tracking-wider transition-all border-b-2 cursor-pointer flex items-center gap-2 shrink-0"
                            :class="tabActiva === 'suscripciones' 
                                ? 'bg-[#1A1A1A] text-white border-brand-red shadow-sm' 
                                : 'bg-transparent text-white/40 border-transparent hover:text-white/80 hover:bg-white/[0.02]'"
                        >
                            SUSCRIPCIONES ({{ cliente.suscripciones?.length || 0 }})
                        </button>
                    </div>
                </div>

                <!-- Tab: Compras -->
                <div v-if="tabActiva === 'compras'">
                    <div v-if="ventas.data.length === 0" class="card py-16 text-center text-white/20 italic">
                        Este cliente no tiene compras registradas.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="venta in ventas.data"
                            :key="venta.id"
                            class="card p-0 overflow-hidden border border-white/10 bg-[#111]"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-5 border-b border-white/5">
                                <div class="flex items-center gap-4">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id, return_client: cliente.id })" class="text-white hover:text-white/80 text-xs font-mono font-bold hover:underline transition-colors" title="Ver en historial de ventas">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span class="bg-[#1a1a1a] border border-white/10 text-white font-bold text-xs rounded-full px-3 py-1 inline-flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full shrink-0" :class="estadoConfig[venta.estado]?.bgDot || 'bg-white/40'"></span>
                                        {{ estadoConfig[venta.estado]?.label ?? venta.estado }}
                                    </span>
                                    <span class="text-xs text-white/40 font-bold">{{ formatFecha(venta.fecha) }}</span>
                                    <span v-if="venta.sucursal" class="text-xs text-white/30 font-bold">{{ venta.sucursal.nombre }}</span>
                                </div>
                                <p class="text-lg font-bold text-white">{{ formatCurrency(venta.total) }}</p>
                            </div>
                            <div class="px-5 py-3 space-y-1.5">
                                <div
                                    v-for="detalle in venta.detalles"
                                    :key="detalle.id"
                                    class="flex justify-between text-sm"
                                >
                                    <span class="text-white/70 line-clamp-1">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-white/40 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                    <span class="font-normal text-white/60 ml-4 flex-shrink-0">{{ formatCurrency(detalle.subtotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación ventas -->
                        <div v-if="ventas.links?.length > 3" class="flex justify-center gap-2 pt-2">
                            <Link
                                v-for="link in ventas.links"
                                :key="link.label"
                                :href="(link.url || '#') + '&tabActiva=compras'"
                                class="px-4 py-2 rounded-lg border border-white/5 text-sm font-black uppercase tracking-tighter transition-all"
                                :class="{ 'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20 pointer-events-none': !link.url }"
                            >{{ decodeLabel(link.label) }}</Link>
                        </div>
                    </div>
                </div>

                <!-- Tab: Canceladas -->
                <div v-if="tabActiva === 'canceladas'">
                    <div v-if="canceladas.data.length === 0" class="card py-16 text-center text-white/20 italic">
                        No hay ventas canceladas para este cliente.
                    </div>

                    <div v-else class="space-y-4">
                        <div class="flex justify-end mb-4">
                            <button @click="eliminarTodasCanceladas" class="px-4 py-2 bg-brand-red/20 hover:bg-brand-red text-brand-red hover:text-white transition-colors text-[10px] font-black uppercase tracking-widest rounded-lg border border-brand-red/50 hover:border-transparent">
                                Eliminar todas las canceladas
                            </button>
                        </div>
                        <div
                            v-for="venta in canceladas.data"
                            :key="venta.id"
                            class="card p-0 overflow-hidden border border-brand-red/30 opacity-70 hover:opacity-100 transition-opacity"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-5 border-b border-white/5 bg-brand-red/5">
                                <div class="flex items-center gap-4">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id, return_client: cliente.id })" class="text-white/20 hover:text-brand-red text-xs font-mono transition-colors" title="Ver en historial de ventas">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded bg-brand-red text-white">CANCELADO</span>
                                    <span class="text-xs text-white/30 font-bold">{{ formatFecha(venta.fecha) }}</span>
                                    <span v-if="venta.sucursal" class="text-xs text-white/20 font-bold">{{ venta.sucursal.nombre }}</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="text-xl font-black text-brand-red italic line-through">{{ formatCurrency(venta.total) }}</p>
                                    <button @click="eliminarVenta(venta.id)" class="text-white/30 hover:text-brand-red transition-colors" title="Eliminar Permanentemente">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="px-5 py-3 space-y-1.5">
                                <div
                                    v-for="detalle in venta.detalles"
                                    :key="detalle.id"
                                    class="flex justify-between text-sm"
                                >
                                    <span class="text-white/60 line-clamp-1">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-white/30 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación canceladas -->
                        <div v-if="canceladas.links?.length > 3" class="flex justify-center gap-2 pt-2">
                            <Link
                                v-for="link in canceladas.links"
                                :key="link.label"
                                :href="(link.url || '#') + '&tabActiva=canceladas'"
                                class="px-4 py-2 rounded-lg border border-white/5 text-sm font-black uppercase tracking-tighter transition-all"
                                :class="{ 'bg-brand-red text-white border-brand-red shadow-lg': link.active, 'text-white/20 pointer-events-none': !link.url }"
                            >{{ decodeLabel(link.label) }}</Link>
                        </div>
                    </div>
                </div>

                <!-- Tab: Acumulados -->
                <div v-if="tabActiva === 'acumulados'">
                    <div v-if="acumulados.length === 0" class="card py-16 text-center text-white/20 italic">
                        No hay pedidos en acumulación para este cliente.
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div class="flex justify-end mb-4">
                            <button @click="showConsolidarModal = true" class="btn-primary py-2 px-6 text-[10px]">
                                GENERAR ENVÍO CONSOLIDADO ({{ acumulados.length }} TICKETS)
                            </button>
                        </div>
                        
                        <div v-for="venta in acumulados" :key="venta.id" class="card p-0 overflow-hidden border border-brand-red/30">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-5 border-b border-white/5 bg-brand-red/5">
                                <div class="flex items-center gap-4">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id })" class="text-white/20 hover:text-brand-red text-xs font-mono transition-colors">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded bg-[#25D366]/20 text-[#25D366]">
                                        Acumulado
                                    </span>
                                    <span class="text-xs text-white/30 font-bold">{{ formatFecha(venta.fecha) }}</span>
                                </div>
                                <p class="text-xl font-black text-brand-red italic">{{ formatCurrency(venta.total) }}</p>
                            </div>
                            <div class="px-5 py-3 space-y-1.5">
                                <div v-for="detalle in venta.detalles" :key="detalle.id" class="flex justify-between text-sm">
                                    <span class="text-white/60 line-clamp-1">
                                        {{ detalle.libro?.master?.titulo ?? 'Libro' }}
                                        <span class="text-white/30 text-xs ml-1">x{{ detalle.cantidad }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pagos -->
                <div v-if="tabActiva === 'pagos'">
                    <div v-if="pagos.length === 0" class="card py-16 text-center text-white/20 italic">
                        Este cliente no tiene pagos registrados.
                    </div>

                    <div v-else class="card p-0 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.03] border-b border-white/5">
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30">Fecha</th>
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30">Método</th>
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30">Descripción</th>
                                    <th class="p-4 text-[10px] font-black uppercase tracking-widest text-white/30 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="pago in pagos" :key="pago.id" class="transition-colors" :class="pago.descripcion && (pago.descripcion.includes('Carga diferida') || pago.descripcion.includes('[PAGO ATRASADO')) ? 'bg-yellow-500/10 hover:bg-yellow-500/20' : 'hover:bg-white/[0.02]'">
                                    <td class="p-4 text-xs text-white/50 font-bold">{{ formatFecha(pago.fecha) }}</td>
                                    <td class="p-4">
                                        <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 bg-white/5 rounded text-white/60">
                                            {{ pago.metodo_pago }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-sm text-white/50 italic">{{ pago.descripcion }}</td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <span class="font-black text-green-400">{{ formatCurrency(pago.monto) }}</span>
                                            <button @click="eliminarPago(pago)" title="Anular pago" class="text-white/20 hover:text-brand-red transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Suscripciones -->
                <div v-if="tabActiva === 'suscripciones'" class="space-y-6">
                    <!-- Top Bar with Collapsible Button -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <h4 class="text-xs font-black uppercase tracking-widest text-white/60 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> SUSCRIPCIONES DEL CLIENTE ({{ cliente.suscripciones?.length || 0 }})
                        </h4>
                        <button 
                            @click="showNuevaSuscripcion = !showNuevaSuscripcion" 
                            class="btn-primary px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider flex items-center gap-2 cursor-pointer transition-all shrink-0 shadow-lg shadow-brand-red/20"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ showNuevaSuscripcion ? 'Cerrar Formulario' : 'Nueva Suscripción' }}
                        </button>
                    </div>

                    <!-- Formulario Desplegable de Suscripción -->
                    <div v-if="showNuevaSuscripcion" class="card p-6 bg-[#111] border border-brand-red/40 rounded-2xl shadow-xl space-y-6 transition-all">
                        <div class="flex items-center justify-between border-b border-white/10 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-brand-red/10 border border-brand-red/20 rounded-xl text-brand-red">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black uppercase tracking-wider text-white">NUEVA SUSCRIPCIÓN A SERIE</h4>
                                    <p class="text-xs text-white/40 font-medium">Asociá un título para reservar automáticamente cada nuevo tomo publicado</p>
                                </div>
                            </div>
                            <button type="button" @click="showNuevaSuscripcion = false" class="text-white/40 hover:text-white transition-colors cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="suscribir" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            <div class="md:col-span-6 space-y-1.5 text-left">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50">SELECCIONE SERIE *</label>
                                <select v-model="suscripcionForm.libro_master_id" required class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer" :class="{'border-brand-red': suscripcionForm.errors.libro_master_id}">
                                    <option value="" disabled class="bg-[#1A1A1A]">-- Selecciona Serie --</option>
                                    <option v-for="lm in libro_masters" :key="lm.id" :value="lm.id" class="bg-[#1A1A1A]">{{ lm.titulo }}</option>
                                </select>
                                <p v-if="suscripcionForm.errors.libro_master_id" class="text-brand-red text-[10px] mt-1">{{ suscripcionForm.errors.libro_master_id }}</p>
                            </div>

                            <div class="md:col-span-4 space-y-1.5 text-left">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50">SUCURSAL DE RETIRO *</label>
                                <select v-model="suscripcionForm.sucursal_id" required class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white focus:outline-none focus:border-brand-red/50 cursor-pointer" :class="{'border-brand-red': suscripcionForm.errors.sucursal_id}">
                                    <option value="" disabled class="bg-[#1A1A1A]">-- Selecciona Sucursal --</option>
                                    <option v-for="suc in sucursales" :key="suc.id" :value="suc.id" class="bg-[#1A1A1A]">{{ suc.nombre }}</option>
                                </select>
                                <p v-if="suscripcionForm.errors.sucursal_id" class="text-brand-red text-[10px] mt-1">{{ suscripcionForm.errors.sucursal_id }}</p>
                            </div>

                            <div class="md:col-span-2">
                                <button type="submit" class="btn-primary w-full py-3 px-6 text-xs font-black tracking-widest uppercase rounded-xl cursor-pointer shadow-lg shadow-brand-red/30 transition-all flex items-center justify-center gap-2" :disabled="suscripcionForm.processing">
                                    {{ suscripcionForm.processing ? '...' : 'SUSCRIBIR' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla de Suscripciones -->
                    <div v-if="!cliente.suscripciones?.length" class="card py-16 text-center text-white/20 italic">
                        Este cliente no tiene suscripciones registradas.
                    </div>
                    
                    <div v-else class="bg-[#111] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-white/10 bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50">
                                        <th class="p-4">Serie</th>
                                        <th class="p-4">Sucursal de Retiro</th>
                                        <th class="p-4">Fecha de Alta</th>
                                        <th class="p-4 text-center">Estado</th>
                                        <th class="p-4 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-xs">
                                    <tr v-for="susc in cliente.suscripciones" :key="susc.id" class="hover:bg-white/[0.01] transition-colors" :class="susc.estado === 'pausada' ? 'opacity-60' : ''">
                                        <td class="p-4">
                                            <div class="font-black text-white uppercase text-sm">
                                                {{ susc.serie?.titulo || 'Serie' }}
                                            </div>
                                        </td>
                                        <td class="p-4 text-white/80 font-bold uppercase">
                                            {{ susc.sucursal?.nombre || 'Todas' }}
                                        </td>
                                        <td class="p-4 text-white/60 font-mono font-medium">
                                            {{ formatFecha(susc.created_at) }}
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full border inline-block" :class="susc.estado === 'activa' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'">
                                                {{ susc.estado }}
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <div class="flex justify-end items-center gap-2">
                                                <!-- Icono Baja -->
                                                <button 
                                                    @click="eliminarSuscripcion(susc)" 
                                                    class="p-2 rounded-lg bg-white/5 hover:bg-red-500/20 text-white/50 hover:text-red-500 border border-white/10 hover:border-red-500/30 transition-colors cursor-pointer"
                                                    title="Dar de baja suscripción"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>
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
            <div v-if="showPagoModal" class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showPagoModal = false" />
                <div class="relative w-full max-w-lg bg-[#1A1A1A] border border-brand-red/40 rounded-2xl shadow-[0_0_50px_rgba(230,25,25,0.15)] overflow-hidden flex flex-col pointer-events-auto">
                    <div class="bg-gradient-to-r from-brand-red to-black p-5 flex justify-between items-center relative overflow-hidden">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white">
                            REGISTRAR <span class="text-white">PAGO</span>
                        </h3>
                        <button type="button" @click="showPagoModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    
                    <form @submit.prevent="submitPago" class="p-8 space-y-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">MONTO A PAGAR *</label>
                                <div class="relative flex items-center">
                                    <span class="absolute left-4 text-2xl font-black text-white/40 pointer-events-none">$</span>
                                    <input 
                                        type="number" 
                                        v-model="pagoForm.monto" 
                                        step="0.01"
                                        min="0.01"
                                        class="input-field w-full bg-black/40 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-2xl font-black text-white focus:outline-none focus:border-brand-red/50"
                                        :class="{ 'border-brand-red': pagoForm.errors.monto }"
                                        required
                                    >
                                </div>
                                <p v-if="pagoForm.errors.monto" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.monto }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">MÉTODO DE PAGO</label>
                                    <select v-model="pagoForm.metodo_pago" class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-3.5 py-2.5 font-bold uppercase text-xs text-white">
                                        <option value="Efectivo" class="bg-[#1A1A1A]">Efectivo</option>
                                        <option value="Transferencia" class="bg-[#1A1A1A]">Transferencia</option>
                                        <option value="Tarjeta" class="bg-[#1A1A1A]">Tarjeta</option>
                                        <option value="Débito" class="bg-[#1A1A1A]">Débito</option>
                                        <option value="Mercado Pago" class="bg-[#1A1A1A]">Mercado Pago</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">FECHA REAL DEL PAGO *</label>
                                    <input 
                                        type="date" 
                                        v-model="pagoForm.fecha_real" 
                                        @click="triggerDatePicker"
                                        class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-3.5 py-2.5 font-mono font-bold text-xs text-white focus:outline-none focus:border-brand-red/50 cursor-pointer"
                                        :class="{ 'border-brand-red': pagoForm.errors.fecha_real }"
                                        required
                                    >
                                    <p v-if="pagoForm.errors.fecha_real" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.fecha_real }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">COMPROBANTE / OBSERVACIONES</label>
                                <input 
                                    type="text" 
                                    v-model="pagoForm.descripcion" 
                                    class="input-field w-full bg-black/40 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white"
                                    :class="{ 'border-brand-red': pagoForm.errors.descripcion }"
                                    placeholder="Ref: Transferencia #12345..."
                                >
                                <p v-if="pagoForm.errors.descripcion" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.descripcion }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 pt-6 border-t border-white/10">
                            <button type="button" @click="showPagoModal = false" class="px-6 py-3 font-black text-white/40 hover:text-white transition-colors uppercase text-xs tracking-wider cursor-pointer">
                                CANCELAR
                            </button>
                            <button type="submit" :disabled="pagoForm.processing" class="btn-primary px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-brand-red/30 transition-all cursor-pointer">
                                {{ pagoForm.processing ? 'PROCESANDO...' : 'CONFIRMAR PAGO' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal Consolidar Pedidos -->
        <template v-if="showConsolidarModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showConsolidarModal = false"></div>
        <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
            <div class="w-full max-w-lg card p-0 overflow-hidden shadow-2xl pointer-events-auto border border-brand-red/50">
                <div class="bg-gradient-to-r from-brand-red/20 to-transparent p-6 border-b border-white/5 flex justify-between items-center">
                    <h3 class="text-xl font-black uppercase tracking-tighter italic">
                        Generar <span class="text-white">Consolidado</span>
                    </h3>
                    <button @click="showConsolidarModal = false" class="text-white/30 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submitConsolidar" class="p-8 space-y-6">
                    <div class="space-y-4">
                        <div class="bg-brand-red/10 border border-brand-red/20 p-4 rounded-lg">
                            <p class="text-[10px] font-black uppercase tracking-widest text-brand-red mb-1">Total a consolidar:</p>
                            <p class="text-2xl font-black italic">{{ acumulados.length }} Tickets</p>
                        </div>
                        
                        <div>
                            <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Dirección de Envío Consolidada *</label>
                            <DireccionAutocomplete
                                v-model="consolidarForm.direccion_envio"
                                @select="onSeleccionarDireccionConsolidar"
                                class="input-field w-full bg-black/40 text-sm"
                                :class="{ 'border-brand-red': consolidarForm.errors.direccion_envio }"
                                placeholder="Ej: San Martín 123, CABA..."
                            />
                            <p class="text-[9px] text-white/40 font-bold mt-2 leading-tight">
                                Ingresá o verificá la dirección a la cual se enviarán todos estos pedidos. Si el cliente no tenía dirección, podés cargarla aquí mismo.
                            </p>
                            <p v-if="consolidarForm.errors.direccion_envio" class="text-brand-red text-xs mt-1">{{ consolidarForm.errors.direccion_envio }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-6 border-t border-white/5">
                        <button type="button" @click="showConsolidarModal = false" class="px-6 py-2 font-black text-white/30 hover:text-white transition-colors uppercase text-[10px] tracking-widest">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="consolidarForm.processing" class="px-10 py-3 btn-primary uppercase text-xs tracking-widest rounded-lg transition-colors disabled:opacity-50">
                            {{ consolidarForm.processing ? 'Procesando...' : 'Confirmar Envío' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </template>

        <!-- Modal Editar Cliente -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showEditModal = false" />
                <div class="relative w-full max-w-xl bg-[#1A1A1A] border border-brand-red/40 rounded-2xl shadow-[0_0_50px_rgba(230,25,25,0.15)] overflow-hidden flex flex-col pointer-events-auto">
                    <div class="bg-gradient-to-r from-brand-red to-black p-5 flex justify-between items-center relative overflow-hidden">
                        <h3 class="text-xl font-black uppercase tracking-tighter text-white">
                            EDITAR CLIENTE
                        </h3>
                        <button type="button" @click="showEditModal = false" class="text-white/80 hover:text-white transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <form @submit.prevent="submitEdit" class="p-8 space-y-6">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.3em] text-white border-b border-white/10 pb-2 mb-6">INFORMACIÓN PERSONAL</h4>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">NOMBRE</label>
                                    <input v-model="editForm.name" type="text" class="input-field w-full font-bold uppercase border-white/10 text-white bg-black/40" required>
                                    <p v-if="editForm.errors.name" class="text-brand-red text-xs mt-1">{{ editForm.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">APELLIDO</label>
                                    <input v-model="editForm.apellido" type="text" class="input-field w-full font-bold uppercase border-white/10 text-white bg-black/40">
                                    <p v-if="editForm.errors.apellido" class="text-brand-red text-xs mt-1">{{ editForm.errors.apellido }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">DNI / DOCUMENTO</label>
                                    <input v-model="editForm.dni" @input="editForm.dni = editForm.dni.replace(/[^A-Za-z0-9]/g, '')" type="text" maxlength="20" class="input-field w-full font-mono border-white/10 text-white bg-black/40">
                                    <p v-if="editForm.errors.dni" class="text-brand-red text-xs mt-1">{{ editForm.errors.dni }}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">TELÉFONO MÓVIL *</label>
                                    <input v-model="editForm.telefono" type="text" class="input-field w-full border-white/10 text-white bg-black/40" required>
                                    <p v-if="editForm.errors.telefono" class="text-brand-red text-xs mt-1">{{ editForm.errors.telefono }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/50 mb-2">EMAIL DE CONTACTO *</label>
                                <input v-model="editForm.email" type="email" class="input-field w-full border-white/10 text-white bg-black/40" required>
                                <p v-if="editForm.errors.email" class="text-brand-red text-xs mt-1">{{ editForm.errors.email }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 pt-6 border-t border-white/10">
                            <button type="button" @click="showEditModal = false" class="px-6 py-3 font-black text-white/40 hover:text-white transition-colors uppercase text-xs tracking-wider cursor-pointer">
                                CANCELAR
                            </button>
                            <button type="submit" :disabled="editForm.processing" class="btn-primary px-8 py-3 rounded-xl text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-brand-red/30 transition-all cursor-pointer">
                                {{ editForm.processing ? 'PROCESANDO...' : 'GUARDAR CAMBIOS' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
