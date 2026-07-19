<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    venta: Object,
});

const page = usePage();
const puedeEditarEstado = page.props.auth?.esAdmin || page.props.auth?.esGerente;

const estados = [
    { value: 'pendiente_pago',     label: 'Pendiente de pago' },
    { value: 'esperando_traslado', label: 'Esperando traslado' },
    { value: 'en_preparacion',     label: 'En preparación' },
    { value: 'listo_para_retiro',  label: 'Listo para retirar' },
    { value: 'acumulado',          label: 'Acumulado' },
    { value: 'enviado',            label: 'Enviado' },
    { value: 'finalizado',         label: 'Finalizado' },
    { value: 'cancelado',          label: 'Cancelado' },
];

const estadoForm = useForm({ 
    estado: props.venta.estado 
});

const guardarEstado = async () => {
    if (estadoForm.estado === 'cancelado' && props.venta.estado !== 'cancelado') {
        const { isConfirmed } = await Swal.fire({
            title: '¿Confirmar cancelación?',
            text: 'Esta acción anulará la venta y devolverá el stock de todos los artículos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No, mantener',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        
        if (!isConfirmed) {
            estadoForm.estado = props.venta.estado;
            return;
        }
    }

    estadoForm.patch(route('ventas.estado', props.venta.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: '¡Actualizado!',
                text: 'El estado se ha actualizado correctamente.',
                icon: 'success',
                confirmButtonColor: '#3b82f6',
                background: '#111827',
                color: '#fff'
            });
        }
    });
};

const fmt = (n) => new Intl.NumberFormat('es-AR', {
    style: 'currency', currency: 'ARS', maximumFractionDigits: 2
}).format(n || 0);

const fmtDate = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-AR', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const metodoPago = props.venta.transacciones?.[0]?.metodo_pago ?? '—';

const print = () => window.print();
</script>

<template>
    <Head :title="`Comprobante #${venta.id}`" />

    <!-- Botones de acción — solo pantalla, no impresión -->
    <div class="no-print fixed top-0 left-0 right-0 z-50 bg-[#0a0a0a] border-b border-white/10 px-6 py-3 flex items-center justify-between">
        <Link :href="route('ventas.index')"
            class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-white/40 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </Link>
        <div class="flex items-center gap-3">
            <!-- Cambiar estado (solo admin/gerente) -->
            <div v-if="puedeEditarEstado" class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
                <select
                    v-model="estadoForm.estado"
                    title="Estado de la Venta"
                    class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-xs font-black uppercase tracking-widest text-white focus:outline-none focus:border-white/30 transition-colors w-full sm:w-auto"
                >
                    <option v-for="e in estados" :key="e.value" :value="e.value">{{ e.label }}</option>
                </select>
                <button
                    @click="guardarEstado"
                    :disabled="estadoForm.estado === venta.estado || estadoForm.processing"
                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest bg-white/10 hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-colors w-full sm:w-auto"
                >
                    Guardar
                </button>
            </div>

            <button @click="print"
                class="flex items-center gap-2 bg-[#e61919] hover:bg-red-700 text-white text-xs font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Imprimir
            </button>
        </div>
    </div>

    <!-- Comprobante -->
    <div class="comprobante-wrapper min-h-screen bg-white pt-16 pb-12 px-4 flex justify-center">
        <div class="comprobante w-full max-w-2xl">

            <!-- Encabezado -->
            <div class="border-b-2 border-black pb-6 mb-6">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-3xl font-black uppercase tracking-tighter text-black">PuroComic</h1>
                        <p class="text-sm text-gray-500 mt-0.5">{{ venta.sucursal?.nombre }}</p>
                        <p v-if="venta.sucursal?.calle" class="text-xs text-gray-400 mt-0.5">
                            {{ venta.sucursal.calle }} {{ venta.sucursal.numero }}
                        </p>
                        <p v-if="venta.sucursal?.telefono" class="text-xs text-gray-400">
                            Tel: {{ venta.sucursal.telefono }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-black uppercase tracking-widest text-gray-400">Comprobante de Venta</p>
                        <p class="text-2xl font-black text-black mt-1">#{{ String(venta.id).padStart(6, '0') }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ fmtDate(venta.fecha) }}</p>
                        <span class="inline-block mt-2 text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded border"
                            :class="venta.tipo === 'online'
                                ? 'border-blue-300 text-blue-600 bg-blue-50'
                                : 'border-gray-300 text-gray-600 bg-gray-50'">
                            {{ venta.tipo === 'online' ? 'Venta Online' : 'Venta Presencial' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Datos del cliente -->
            <div class="grid grid-cols-2 gap-6 mb-6 text-sm">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Cliente</p>
                    <Link v-if="venta.cliente_id" :href="route('clientes.show', venta.cliente_id)" class="font-bold text-black hover:text-brand-red transition-colors block">
                        {{ venta.cliente?.user?.name }} {{ venta.cliente?.user?.apellido }}
                    </Link>
                    <p v-else class="font-bold text-black">Cliente Mostrador</p>
                    <p v-if="venta.cliente?.user?.email" class="text-gray-500 text-xs">
                        {{ venta.cliente.user.email }}
                    </p>
                    <p v-if="venta.direccion_envio" class="text-gray-500 text-xs mt-1">
                        Envío: {{ venta.direccion_envio }}
                    </p>
                </div>
                <div>
                    <template v-if="venta.tipo === 'presencial' || venta.origen === 'whatsapp' || venta.origen === 'redes'">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Atendido por</p>
                        <p class="font-bold text-black">{{ venta.atendido_por?.name || '—' }} {{ venta.atendido_por?.apellido || '' }}</p>
                    </template>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 mt-3">Método de pago</p>
                    <p class="font-bold text-black">{{ metodoPago }}</p>
                </div>
            </div>

            <!-- Detalle de items -->
            <table class="w-full text-sm mb-6">
                <thead>
                    <tr class="border-b-2 border-black text-[10px] font-black uppercase tracking-widest text-gray-500">
                        <th class="text-left py-2">Descripción</th>
                        <th class="text-center py-2 w-16">Cant.</th>
                        <th class="text-right py-2 w-28">P. Unit.</th>
                        <th class="text-right py-2 w-28">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="d in venta.detalles" :key="d.id" class="py-2">
                        <td class="py-3">
                            <p class="font-bold text-black">{{ d.libro?.master?.titulo }} - Tomo {{ d.libro?.numero_tomo || 'Único' }}</p>
                            <p v-if="d.libro?.isbn" class="text-[10px] font-mono text-gray-400 mt-0.5">ISBN: {{ d.libro.isbn }}</p>
                        </td>
                        <td class="py-3 text-center font-bold text-gray-700">{{ d.cantidad }}</td>
                        <td class="py-3 text-right font-mono text-gray-700">{{ fmt(d.precio_unitario) }}</td>
                        <td class="py-3 text-right font-black text-black">{{ fmt(d.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Total -->
            <div class="border-t-2 border-black pt-4 flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between text-sm text-gray-500 mb-1">
                        <span>Subtotal</span>
                        <span class="font-mono">{{ fmt(venta.total) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-500 mb-3">
                        <span>IVA incluido</span>
                        <span class="font-mono text-xs">—</span>
                    </div>
                    <div class="flex justify-between text-xl font-black text-black border-t border-black pt-2">
                        <span>TOTAL</span>
                        <span>{{ fmt(venta.total) }}</span>
                    </div>
                </div>
            </div>

            <!-- Pie -->
            <div class="mt-10 pt-6 border-t border-gray-200 text-center">
                <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">
                    ¡Gracias por tu compra en PuroComic!
                </p>
                <p class="text-[10px] text-gray-300 mt-1">
                    Este comprobante es válido como constancia de compra · purocomic.com.ar
                </p>
            </div>

        </div>
    </div>
</template>

<style>
@media print {
    .no-print { display: none !important; }

    .comprobante-wrapper {
        padding-top: 0 !important;
        background: white !important;
    }

    body {
        background: white !important;
        color: black !important;
    }

    @page {
        margin: 1.5cm;
        size: A4;
    }
}

@media screen {
    .comprobante-wrapper {
        background: #f3f3f3;
    }
    .comprobante {
        background: white;
        padding: 3rem;
        box-shadow: 0 4px 40px rgba(0,0,0,0.15);
        border-radius: 8px;
    }
}
</style>
