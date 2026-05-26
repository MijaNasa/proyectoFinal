<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    pedidos: Object,
});

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const formatFecha = (fecha) =>
    new Date(fecha).toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' });

const estadoConfig = {
    pendiente_pago:     { label: 'Pendiente de pago',  color: 'text-yellow-400 bg-yellow-400/10 border-yellow-400/20' },
    en_preparacion:     { label: 'En preparación',     color: 'text-blue-400 bg-blue-400/10 border-blue-400/20' },
    listo_para_retirar: { label: 'Listo para retirar', color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    enviado:            { label: 'Enviado',             color: 'text-purple-400 bg-purple-400/10 border-purple-400/20' },
    entregado:          { label: 'Entregado',           color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    retirado:           { label: 'Retirado',            color: 'text-green-400 bg-green-400/10 border-green-400/20' },
    cancelado:          { label: 'Cancelado',           color: 'text-red-400 bg-red-400/10 border-red-400/20' },
};
</script>

<template>
    <Head title="Mis Pedidos" />

    <PublicLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-5xl font-black uppercase tracking-tighter mb-12">
                Mis <span class="text-brand-red italic">Pedidos</span>
            </h1>

            <!-- Sin pedidos -->
            <div v-if="!pedidos.data.length" class="py-32 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white/10 mb-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="text-xl font-black uppercase tracking-widest text-white/20 mb-3">Todavía no hiciste ningún pedido</h3>
                <p class="text-white/20 text-sm mb-8">Explorá el catálogo y hacé tu primera compra.</p>
                <a :href="route('catalogo.index')" class="btn-primary py-3 px-8 rounded-full text-sm font-black uppercase tracking-widest">
                    Ver Catálogo
                </a>
            </div>

            <!-- Lista de pedidos -->
            <div v-else class="space-y-6">
                <div
                    v-for="pedido in pedidos.data"
                    :key="pedido.id"
                    class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 hover:border-white/20 transition-all"
                >
                    <!-- Header del pedido -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <span class="font-black text-lg"># {{ pedido.id }}</span>
                                <span
                                    class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-full border"
                                    :class="estadoConfig[pedido.estado]?.color ?? 'text-white/40 bg-white/5 border-white/10'"
                                >
                                    {{ estadoConfig[pedido.estado]?.label ?? pedido.estado }}
                                </span>
                            </div>
                            <p class="text-white/40 text-xs font-bold uppercase tracking-widest">
                                {{ formatFecha(pedido.fecha) }}
                                · {{ pedido.tipo_envio === 'retiro' ? 'Retiro en sucursal' : 'Envío a domicilio' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-black text-brand-red italic">{{ formatPrecio(pedido.total) }}</p>
                        </div>
                    </div>

                    <!-- Items del pedido -->
                    <div class="space-y-2 border-t border-white/5 pt-4">
                        <div
                            v-for="(item, i) in pedido.items"
                            :key="i"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-white/60">
                                {{ item.titulo }}
                                <span class="text-white/30 text-xs">x{{ item.cantidad }}</span>
                            </span>
                            <span class="font-black text-white/80">{{ formatPrecio(item.subtotal) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="pedidos.links?.length > 3" class="mt-10 flex justify-center gap-2">
                <Link
                    v-for="link in pedidos.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="px-4 py-2 rounded-lg border border-white/10 text-sm font-black uppercase tracking-tighter transition-all"
                    :class="{ 'bg-brand-red text-white border-brand-red': link.active, 'text-white/30 pointer-events-none': !link.url }"
                >{{ decodeLabel(link.label) }}</Link>
            </div>
        </div>
    </PublicLayout>
</template>
