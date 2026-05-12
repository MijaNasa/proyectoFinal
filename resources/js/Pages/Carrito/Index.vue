<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    items: Object,
});

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const actualizarCantidad = (libroId, cantidad) => {
    if (cantidad < 1) return;
    router.patch(route('carrito.actualizar', libroId), { cantidad }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const quitar = (libroId) => {
    router.delete(route('carrito.quitar', libroId), {
        preserveScroll: true,
        preserveState: true,
    });
};

const vaciar = () => {
    router.delete(route('carrito.vaciar'), {
        preserveScroll: true,
    });
};

const irACheckout = () => {
    router.visit(route('checkout.index'));
};
</script>

<template>
    <Head title="Mi Carrito" />

    <PublicLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-5xl font-black uppercase tracking-tighter mb-12">
                Mi <span class="text-brand-red italic">Carrito</span>
            </h1>

            <!-- Carrito vacío -->
            <div v-if="!items.items.length" class="py-32 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 mx-auto text-white/10 mb-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="text-2xl font-black uppercase tracking-widest text-white/20 mb-4">Tu carrito está vacío</h3>
                <p class="text-white/20 text-sm mb-8">Explorá nuestro catálogo y encontrá tu próxima lectura.</p>
                <Link :href="route('catalogo.index')" class="btn-primary py-3 px-8 rounded-full text-sm font-black uppercase tracking-widest">
                    Ver Catálogo
                </Link>
            </div>

            <!-- Carrito con items -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-12">

                <!-- Lista de items -->
                <div class="lg:col-span-2 space-y-4">
                    <div
                        v-for="item in items.items"
                        :key="item.libro_id"
                        class="flex gap-6 bg-white/[0.03] border border-white/10 rounded-2xl p-6 hover:border-white/20 transition-all"
                    >
                        <!-- Portada -->
                        <div class="w-16 flex-shrink-0">
                            <img
                                :src="item.portada_url"
                                :alt="item.titulo"
                                class="w-full aspect-[2/3] object-cover rounded-lg border border-white/10"
                            >
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <h3 class="font-black uppercase tracking-tighter text-lg leading-tight mb-1 line-clamp-2">{{ item.titulo }}</h3>
                            <p class="text-xs font-bold uppercase tracking-widest text-white/30 mb-4">{{ item.editorial }} · ISBN: {{ item.isbn }}</p>
                            <div class="flex items-center gap-4">
                                <!-- Cantidad -->
                                <div class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-lg px-3 py-1.5">
                                    <button
                                        @click="actualizarCantidad(item.libro_id, item.cantidad - 1)"
                                        class="text-white/50 hover:text-white font-black text-base leading-none w-5 text-center"
                                    >−</button>
                                    <span class="text-sm font-black text-white w-5 text-center">{{ item.cantidad }}</span>
                                    <button
                                        @click="actualizarCantidad(item.libro_id, item.cantidad + 1)"
                                        class="text-white/50 hover:text-white font-black text-base leading-none w-5 text-center"
                                    >+</button>
                                </div>
                                <!-- Quitar -->
                                <button @click="quitar(item.libro_id)" class="text-[10px] font-black uppercase tracking-widest text-white/20 hover:text-brand-red transition-colors">
                                    Quitar
                                </button>
                            </div>
                        </div>

                        <!-- Subtotal -->
                        <div class="flex-shrink-0 text-right">
                            <div class="text-2xl font-black text-brand-red italic">
                                {{ formatPrecio(item.precio * item.cantidad) }}
                            </div>
                            <div class="text-xs font-bold text-white/20 mt-1">
                                {{ formatPrecio(item.precio) }} c/u
                            </div>
                        </div>
                    </div>

                    <!-- Vaciar carrito -->
                    <div class="text-right pt-2">
                        <button @click="vaciar" class="text-[10px] font-black uppercase tracking-widest text-white/20 hover:text-brand-red transition-colors">
                            Vaciar carrito
                        </button>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="lg:col-span-1">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8 sticky top-28">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-8 underline decoration-2 underline-offset-4">Resumen del Pedido</h3>

                        <div class="space-y-4 mb-8">
                            <div class="flex justify-between text-sm">
                                <span class="text-white/40 font-bold uppercase tracking-widest text-xs">Subtotal ({{ items.count }} item{{ items.count !== 1 ? 's' : '' }})</span>
                                <span class="font-black text-white">{{ formatPrecio(items.total) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-white/40 font-bold uppercase tracking-widest text-xs">Envío</span>
                                <span class="font-black text-white/40 text-xs">Se calcula al siguiente paso</span>
                            </div>
                            <div class="border-t border-white/10 pt-4 flex justify-between">
                                <span class="font-black uppercase tracking-widest text-sm">Total</span>
                                <span class="text-2xl font-black text-brand-red italic">{{ formatPrecio(items.total) }}</span>
                            </div>
                        </div>

                        <button
                            @click="irACheckout"
                            class="w-full bg-brand-red hover:bg-brand-red/80 text-white font-black text-sm uppercase tracking-widest py-4 rounded-xl transition-all"
                        >
                            Finalizar Compra
                        </button>

                        <Link :href="route('catalogo.index')" class="mt-4 block text-center text-[10px] font-black uppercase tracking-widest text-white/20 hover:text-white transition-colors py-2">
                            ← Seguir comprando
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
