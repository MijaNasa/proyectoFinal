<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { decodeLabel } from '@/composables/useDecodeLabel';
import Swal from 'sweetalert2';

const props = defineProps({
    cliente: Object,
    ventas:  Object,
    acumulados: Array,
    pagos:   Array,
    stats:   Object,
    libro_masters: Array,
    sucursales: Array,
});

const tabActiva = ref('compras');

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
    direccion_envio: '' // Se permite cargar o editar en el modal
});

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

const suscribir = () => {
    suscripcionForm.post(route('suscripciones.store'), {
        preserveScroll: true,
        onSuccess: () => {
            suscripcionForm.reset('libro_master_id', 'sucursal_id');
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


const formatCurrency = (v) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

const formatFecha = (f) =>
    new Date(f).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });

const estadoConfig = {
    pendiente_pago:     { label: 'Pendiente de pago',  color: 'text-yellow-400 bg-yellow-400/10' },
    en_preparacion:     { label: 'En preparación',     color: 'text-blue-400 bg-blue-400/10' },
    listo_para_retirar: { label: 'Listo para retirar', color: 'text-green-400 bg-green-400/10' },
    enviado:            { label: 'Enviado',             color: 'text-purple-400 bg-purple-400/10' },
    entregado:          { label: 'Entregado',           color: 'text-green-400 bg-green-400/10' },
    retirado:           { label: 'Retirado',            color: 'text-green-400 bg-green-400/10' },
    completada:         { label: 'Completada',          color: 'text-green-400 bg-green-400/10' },
    cancelado:          { label: 'Cancelado',           color: 'text-red-400 bg-red-400/10' },
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
                        Seguimiento de <span class="text-brand-red italic">Cliente</span>
                    </h2>
                    <p class="text-white/30 text-xs uppercase tracking-widest font-bold mt-0.5">
                        {{ cliente.user.name }} {{ cliente.user.apellido }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl sm:px-6 lg:px-8 space-y-8">

                <!-- Ficha del cliente -->
                <div class="card p-0 overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-red/20 to-transparent p-6 border-b border-white/5">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="space-y-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-2xl font-black uppercase tracking-tighter">
                                        {{ cliente.user.name }} {{ cliente.user.apellido }}
                                    </h3>
                                    <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 bg-brand-red text-white rounded">
                                        {{ cliente.tipo_cliente?.nombre ?? 'Sin tipo' }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-4 text-xs text-white/40 font-bold">
                                    <span>{{ cliente.user.email }}</span>
                                    <span v-if="cliente.user.dni">DNI: {{ cliente.user.dni }}</span>
                                    <span v-if="cliente.user.telefono">{{ cliente.user.telefono }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] uppercase tracking-widest font-black mb-1" :class="cliente.saldo_actual < 0 ? 'text-brand-red' : 'text-green-400'">
                                    {{ cliente.saldo_actual < 0 ? 'DEUDA PENDIENTE' : 'SALDO A FAVOR' }}
                                </p>
                                <p class="text-3xl font-black italic" :class="cliente.saldo_actual < 0 ? 'text-brand-red' : 'text-green-400'">
                                    <span v-if="cliente.saldo_actual < 0">- </span>{{ formatCurrency(Math.abs(cliente.saldo_actual)) }}
                                </p>
                                <button @click="showPagoModal = true" class="mt-4 w-full py-2 bg-green-600/20 hover:bg-green-600 text-green-400 hover:text-white transition-colors text-[10px] font-black uppercase tracking-widest rounded-lg border border-green-500/50 hover:border-transparent">
                                    Registrar Pago
                                </button>
                                <a :href="route('clientes.pdf', cliente.id)" target="_blank" class="mt-2 block text-center w-full py-2 bg-white/5 hover:bg-white/10 text-white transition-colors text-[10px] font-black uppercase tracking-widest rounded-lg border border-white/10 hover:border-white/20">
                                    Informe de balance
                                </a>
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

                <!-- Tabs -->
                <div class="flex gap-1 border-b border-white/10">
                    <button
                        @click="tabActiva = 'compras'"
                        class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="tabActiva === 'compras' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                    >
                        Compras ({{ stats.cantidad_ventas }})
                    </button>
                    <button
                        @click="tabActiva = 'pagos'"
                        class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="tabActiva === 'pagos' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                    >
                        Pagos ({{ pagos.length }})
                    </button>
                    <button
                        @click="tabActiva = 'acumulados'"
                        class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="tabActiva === 'acumulados' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                    >
                        Acumulados ({{ acumulados.length }})
                    </button>
                    <button
                        @click="tabActiva = 'suscripciones'"
                        class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                        :class="tabActiva === 'suscripciones' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                    >
                        Suscripciones ({{ cliente.suscripciones?.length || 0 }})
                    </button>
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
                            class="card p-0 overflow-hidden"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-5 border-b border-white/5">
                                <div class="flex items-center gap-4">
                                    <Link :href="route('ventas.index', { search: '#TK-' + String(venta.id).padStart(6, '0'), view: venta.id, return_client: cliente.id })" class="text-white/20 hover:text-brand-red text-xs font-mono transition-colors" title="Ver en historial de ventas">#TK-{{ String(venta.id).padStart(6, '0') }}</Link>
                                    <span
                                        class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded"
                                        :class="estadoConfig[venta.estado]?.color ?? 'text-white/40 bg-white/5'"
                                    >{{ estadoConfig[venta.estado]?.label ?? venta.estado }}</span>
                                    <span class="text-xs text-white/30 font-bold">{{ formatFecha(venta.fecha) }}</span>
                                    <span v-if="venta.sucursal" class="text-xs text-white/20 font-bold">{{ venta.sucursal.nombre }}</span>
                                </div>
                                <p class="text-xl font-black text-brand-red italic">{{ formatCurrency(venta.total) }}</p>
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
                                    <span class="font-black text-white/70 ml-4 flex-shrink-0">{{ formatCurrency(detalle.subtotal) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Paginación ventas -->
                        <div v-if="ventas.links?.length > 3" class="flex justify-center gap-2 pt-2">
                            <Link
                                v-for="link in ventas.links"
                                :key="link.label"
                                :href="link.url || '#'"
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
                <div v-if="tabActiva === 'suscripciones'">
                    <!-- Crear Suscripción -->
                    <div class="card p-6 mb-6">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.4em] text-brand-red border-b border-brand-red/20 pb-2 mb-6">Nueva Suscripción</h4>
                        <form @submit.prevent="suscribir" class="flex flex-col md:flex-row gap-4 items-end">
                            <div class="flex-1">
                                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-white/40 mb-2">Colección / Serie</label>
                                <select v-model="suscripcionForm.libro_master_id" required class="input-field w-full text-xs" :class="{'border-brand-red': suscripcionForm.errors.libro_master_id}">
                                    <option value="">Seleccione una serie...</option>
                                    <option v-for="s in libro_masters" :key="s.id" :value="s.id">{{ s.titulo }}</option>
                                </select>
                                <div v-if="suscripcionForm.errors.libro_master_id" class="text-brand-red text-[10px] mt-1">{{ suscripcionForm.errors.libro_master_id }}</div>
                            </div>
                            <div class="w-full md:w-1/4">
                                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-white/40 mb-2">Sucursal de Retiro</label>
                                <select v-model="suscripcionForm.sucursal_id" required class="input-field w-full text-xs">
                                    <option value="">Seleccione...</option>
                                    <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">{{ suc.nombre }}</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary" :disabled="suscripcionForm.processing">Suscribir</button>
                        </form>
                    </div>

                    <!-- Lista de Suscripciones -->
                    <div v-if="!cliente.suscripciones?.length" class="card py-16 text-center text-white/20 italic">
                        Este cliente no tiene suscripciones activas.
                    </div>
                    
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="susc in cliente.suscripciones" :key="susc.id" class="card p-5 flex flex-col justify-between" :class="susc.estado === 'pausada' ? 'opacity-50' : 'border-white/10'">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h5 class="text-sm font-black uppercase">{{ susc.serie?.titulo || 'Serie' }}</h5>
                                    <p class="text-[10px] text-white/40 font-mono mt-1">Suscrito el {{ formatFecha(susc.created_at) }}</p>
                                    <p v-if="susc.sucursal" class="text-[10px] text-brand-red font-bold mt-1 uppercase">Sucursal: {{ susc.sucursal.nombre }}</p>
                                </div>
                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded" :class="susc.estado === 'activa' ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'">
                                    {{ susc.estado }}
                                </span>
                            </div>
                            <div class="flex gap-2 justify-end border-t border-white/5 pt-4 mt-auto">
                                <button @click="eliminarSuscripcion(susc)" class="text-[10px] font-black uppercase text-white/40 hover:text-brand-red transition-colors px-3 py-1 bg-white/5 rounded">Baja</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Pago -->
        <template v-if="showPagoModal">
        <div class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md" @click="showPagoModal = false"></div>
        <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
            <div class="w-full max-w-lg card p-0 overflow-hidden shadow-2xl pointer-events-auto border border-white/10">
                <div class="bg-gradient-to-r from-green-600/20 to-transparent p-6 border-b border-white/5 flex justify-between items-center">
                    <h3 class="text-xl font-black uppercase tracking-tighter italic">
                        Registrar <span class="text-white">Pago</span>
                    </h3>
                    <button @click="showPagoModal = false" class="text-white/30 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submitPago" class="p-8 space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Monto a pagar</label>
                            <input 
                                type="number" 
                                v-model="pagoForm.monto" 
                                step="0.01"
                                min="0.01"
                                class="input-field w-full bg-black/40 text-2xl font-black italic tracking-tighter"
                                :class="{ 'border-brand-red': pagoForm.errors.monto }"
                                required
                            >
                            <p v-if="pagoForm.errors.monto" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.monto }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Método de Pago</label>
                                <select v-model="pagoForm.metodo_pago" class="input-field w-full bg-black/40 font-black uppercase text-xs">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Débito">Débito</option>
                                    <option value="Cuenta Corriente">Cuenta Corriente</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Fecha Real del Pago</label>
                                <input 
                                    type="date" 
                                    v-model="pagoForm.fecha_real" 
                                    class="input-field w-full bg-black/40 font-black uppercase text-xs"
                                    :class="{ 'border-brand-red': pagoForm.errors.fecha_real }"
                                    required
                                >
                                <p v-if="pagoForm.errors.fecha_real" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.fecha_real }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase text-white/30 mb-2">Comprobante / Observaciones</label>
                            <input 
                                type="text" 
                                v-model="pagoForm.descripcion" 
                                class="input-field w-full bg-black/40 text-sm"
                                :class="{ 'border-brand-red': pagoForm.errors.descripcion }"
                                placeholder="Ref: Transferencia #12345..."
                            >
                            <p v-if="pagoForm.errors.descripcion" class="text-brand-red text-xs mt-1">{{ pagoForm.errors.descripcion }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4 pt-6 border-t border-white/5">
                        <button type="button" @click="showPagoModal = false" class="px-6 py-2 font-black text-white/30 hover:text-white transition-colors uppercase text-[10px] tracking-widest">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="pagoForm.processing" class="px-10 py-3 bg-green-600 hover:bg-green-500 text-white font-black uppercase text-xs tracking-widest rounded-lg transition-colors disabled:opacity-50">
                            {{ pagoForm.processing ? 'Procesando...' : 'Confirmar Pago' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </template>

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
                            <input 
                                type="text" 
                                v-model="consolidarForm.direccion_envio" 
                                class="input-field w-full bg-black/40 text-sm"
                                :class="{ 'border-brand-red': consolidarForm.errors.direccion_envio }"
                                placeholder="Ej: San Martín 123, CABA..."
                                required
                            >
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
    </AuthenticatedLayout>
</template>
