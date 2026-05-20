<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: String, // 'success' | 'pending' | 'failure'
    venta:  Object,
});

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
