<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import { router } from '@inertiajs/vue3';

const props = defineProps({
    status: String, // 'success' | 'pending' | 'failure'
    venta:  Object,
});

const uploadForm = useForm({
    comprobante: null,
});

const deleteComprobante = () => {
    if (confirm('¿Estás seguro de que querés eliminar el comprobante?')) {
        router.delete(route('mi-cuenta.comprobante.delete', props.venta.id), {
            preserveScroll: true
        });
    }
};

const uploadComprobante = () => {
    uploadForm.post(route('mi-cuenta.comprobante', props.venta.id), {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            // The status might not change immediately in this view, 
            // but the page will reload and backend might set something if needed.
            // Or we just rely on a success message if any.
        },
    });
};

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const config = computed(() => ({
    success: {
        icon:     '✓',
        iconBg:   'bg-green-500',
        title:    '¡Pago aprobado!',
        subtitle: 'Tu pedido fue confirmado y está en preparación.',
        color:    'text-green-400',
    },
    pending: {
        icon:     '⏳',
        iconBg:   'bg-yellow-500',
        title:    'Pago pendiente',
        subtitle: 'Tu pago está siendo procesado. Te notificaremos cuando se confirme.',
        color:    'text-yellow-400',
    },
    failure: {
        icon:     '✕',
        iconBg:   'bg-brand-red',
        title:    'Pago no procesado',
        subtitle: 'Hubo un problema con tu pago. Podés intentarlo nuevamente.',
        color:    'text-brand-red',
    },
}[props.status] ?? {}));
</script>

<template>
    <Head title="Confirmación de Compra" />

    <PublicLayout>
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">

            <!-- Ícono -->
            <div
                class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-8 text-3xl font-black text-white"
                :class="config.iconBg"
            >
                {{ config.icon }}
            </div>

            <!-- Título -->
            <h1 class="text-4xl font-black uppercase tracking-tighter mb-4">
                {{ config.title }}
            </h1>
            <p class="text-white/50 text-base mb-10">
                {{ config.subtitle }}
            </p>

            <!-- Detalle del pedido (si existe) -->
            <div v-if="venta" class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 mb-10 text-left">
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-white/30 mb-4">Detalle del pedido</h3>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-white/50">Número de pedido</span>
                    <span class="font-black">#{{ venta.id }}</span>
                </div>
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-white/50">Total</span>
                    <span class="font-black text-brand-red italic text-lg">{{ formatPrecio(venta.total) }}</span>
                </div>
                <div v-if="venta.tipo_envio" class="flex justify-between text-sm">
                    <span class="text-white/50">Entrega</span>
                    <span class="font-black capitalize">{{ venta.tipo_envio === 'retiro' ? 'Retiro en sucursal' : 'Envío a domicilio' }}</span>
                </div>
            </div>

            <!-- Datos Bancarios para Transferencia -->
            <div v-if="venta && venta.metodo_pago === 'Transferencia'" class="bg-blue-500/10 border border-blue-500/20 rounded-2xl p-6 mb-10 text-left">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-2xl">🏦</span>
                    <h3 class="text-sm font-black uppercase tracking-widest text-blue-400">Datos Bancarios</h3>
                </div>
                <p class="text-white/70 text-sm mb-4 leading-relaxed">
                    Para que podamos preparar tu pedido, por favor transferí <strong class="text-white">{{ formatPrecio(venta.total) }}</strong> a la siguiente cuenta y <strong>subí el comprobante</strong> desde la sección "Mis Pedidos".
                </p>
                <div class="space-y-3 bg-black/20 p-4 rounded-xl text-sm">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-white/5 pb-2">
                        <span class="text-white/40 font-bold uppercase tracking-widest text-[10px]">CBU</span>
                        <span class="font-mono text-white/90">0000003100010000000000</span>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-white/5 pb-2">
                        <span class="text-white/40 font-bold uppercase tracking-widest text-[10px]">Alias</span>
                        <span class="font-mono text-white/90">LIBRERIA.ANTIGRAVITY</span>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-white/5 pb-2">
                        <span class="text-white/40 font-bold uppercase tracking-widest text-[10px]">Titular</span>
                        <span class="font-bold text-white/90">Puro Comic</span>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                        <span class="text-white/40 font-bold uppercase tracking-widest text-[10px]">Banco</span>
                        <span class="font-bold text-white/90">Banco Ficticio</span>
                    </div>
                </div>

                <!-- Subir Comprobante Inmediato -->
                <div v-if="venta.metodo_pago === 'Transferencia' && venta.estado === 'pendiente_pago'" class="mt-6 pt-6 border-t border-white/10">
                    <h4 class="text-xs font-black uppercase tracking-widest text-white/70 mb-3 text-left">¿Ya transferiste? Subí el comprobante</h4>
                    
                    <div v-if="venta.comprobante_path" class="flex items-center gap-3 p-4 bg-green-500/10 border border-green-500/20 rounded-xl">
                        <span class="text-green-400">✅</span>
                        <p class="text-xs font-medium text-white/80">Comprobante enviado exitosamente. Esperando verificación.</p>
                        <div class="ml-auto flex items-center gap-3">
                            <a :href="route('mi-cuenta.comprobante.ver', venta.id)" target="_blank" class="text-[10px] font-bold text-green-400 uppercase hover:underline">Ver adjunto</a>
                            <button @click="deleteComprobante" class="text-white/40 hover:text-red-400 transition-colors" title="Eliminar comprobante">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>
                    
                    <form v-else @submit.prevent="uploadComprobante" class="flex flex-col sm:flex-row items-center gap-3 p-4 bg-white/5 border border-white/10 rounded-xl">
                        <input 
                            type="file" 
                            accept="image/*"
                            @input="uploadForm.comprobante = $event.target.files[0]"
                            class="w-full text-xs text-white/50 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-brand-red/20 file:text-brand-red hover:file:bg-brand-red/30 cursor-pointer"
                            required
                        >
                        <button 
                            type="submit"
                            :disabled="uploadForm.processing || !uploadForm.comprobante"
                            class="w-full sm:w-auto px-4 py-2 bg-brand-red text-white text-[10px] font-black uppercase rounded-lg disabled:opacity-50"
                        >
                            {{ uploadForm.processing ? 'Enviando...' : 'Enviar' }}
                        </button>
                    </form>
                    <p v-if="uploadForm.errors.comprobante" class="text-red-400 text-[10px] mt-2 text-left">{{ uploadForm.errors.comprobante }}</p>
                </div>
            </div>

            <!-- Acciones -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <template v-if="status === 'success' || status === 'pending'">
                    <Link
                        :href="route('mi-cuenta.index')"
                        class="bg-brand-red hover:bg-brand-red/80 text-white font-black text-sm uppercase tracking-widest py-4 px-8 rounded-xl transition-all"
                    >
                        Ver mis pedidos
                    </Link>
                    <Link
                        :href="route('catalogo.index')"
                        class="border border-white/10 hover:border-white/30 text-white font-black text-sm uppercase tracking-widest py-4 px-8 rounded-xl transition-all"
                    >
                        Seguir comprando
                    </Link>
                </template>

                <template v-if="status === 'failure'">
                    <Link
                        :href="route('carrito.index')"
                        class="bg-brand-red hover:bg-brand-red/80 text-white font-black text-sm uppercase tracking-widest py-4 px-8 rounded-xl transition-all"
                    >
                        Volver al carrito
                    </Link>
                    <Link
                        :href="route('catalogo.index')"
                        class="border border-white/10 hover:border-white/30 text-white font-black text-sm uppercase tracking-widest py-4 px-8 rounded-xl transition-all"
                    >
                        Ver catálogo
                    </Link>
                </template>
            </div>
        </div>
    </PublicLayout>
</template>
