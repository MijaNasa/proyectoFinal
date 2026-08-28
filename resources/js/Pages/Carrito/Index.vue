<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
    items: Object,
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316]',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs uppercase tracking-wider transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-bold text-xs uppercase tracking-wider border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const getLimiteItem = (item) => {
    if (item.permite_preventa) return 5;
    return (item.stock_total && item.stock_total > 0) ? Math.min(5, item.stock_total) : 5;
};

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
    darkSwal.fire({
        title: '¿Vaciar el carrito?',
        text: 'Se eliminarán todos los productos seleccionados de tu carrito de compras.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, vaciar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('carrito.vaciar'), {
                preserveScroll: true,
            });
        }
    });
};

const irACheckout = () => {
    router.visit(route('checkout.index'));
};
</script>

<template>
    <Head title="Mi Carrito | PuroComic" />

    <PublicLayout>
        <div class="page-carrito">
            <!-- Hero Header -->
            <div class="relative overflow-hidden py-12 sm:py-16 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                        <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        Resumen de Selección
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-bold tracking-tight uppercase leading-none text-white">
                        Tu <span class="text-zinc-400 italic">Carrito</span>
                    </h1>
                    <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-xl mx-auto">
                        Revisá los títulos en tu selección, ajustá las cantidades o continuá hacia la finalización del pedido.
                    </p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                <!-- Carrito vacío -->
                <div v-if="!items.items.length" class="max-w-lg mx-auto py-12 sm:py-16">
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-8 sm:p-12 text-center shadow-2xl">
                        <div class="w-20 h-20 bg-white/5 border border-white/10 rounded-2xl flex items-center justify-center mx-auto mb-6 text-zinc-400 shadow-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold uppercase tracking-tight text-white mb-2">Tu carrito está vacío</h3>
                        <p class="text-zinc-400 text-xs sm:text-sm font-medium mb-8 leading-relaxed">
                            Explorá nuestro catálogo y encontrá tu próxima lectura entre mangas, cómics y ediciones especiales.
                        </p>
                        <Link :href="route('catalogo.index')" class="inline-flex items-center justify-center gap-2 px-8 py-3.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 cursor-pointer">
                            <span>Ver Catálogo</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Carrito con items -->
                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8 sm:gap-12 items-start">

                    <!-- Lista de items -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Top Action Bar -->
                        <div class="flex items-center justify-between gap-4 pb-2 border-b border-white/5">
                            <div class="flex items-center gap-3">
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    Productos ({{ items.count }})
                                </h2>
                            </div>
                            <button
                                @click="vaciar"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-400 hover:text-rose-400 transition-colors cursor-pointer group"
                            >
                                <svg class="w-4 h-4 text-zinc-500 group-hover:text-rose-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Vaciar Carrito</span>
                            </button>
                        </div>

                        <!-- Item cards -->
                        <div class="space-y-4">
                            <div
                                v-for="item in items.items"
                                :key="item.libro_id"
                                class="bg-[#131316] border border-white/5 hover:border-white/10 rounded-2xl p-5 sm:p-6 shadow-xl transition-all duration-300 flex flex-col sm:flex-row gap-5 items-start sm:items-center"
                            >
                                <!-- Portada -->
                                <Link
                                    :href="route('catalogo.show', item.libro_id)"
                                    class="w-20 sm:w-24 aspect-[2/3] shrink-0 bg-[#0d0d0f] rounded-xl overflow-hidden border border-white/10 shadow-md relative group block"
                                >
                                    <img
                                        :src="item.portada_url"
                                        :alt="item.titulo"
                                        @error="$event.target.src = '/images/no-cover.png'"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    />
                                </Link>

                                <!-- Detalle del Item -->
                                <div class="flex-1 min-w-0 space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <Link
                                                :href="route('catalogo.show', item.libro_id)"
                                                class="font-bold text-base sm:text-lg text-white leading-snug hover:text-brand-red transition-colors line-clamp-2"
                                            >
                                                {{ item.titulo }}
                                            </Link>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span v-if="item.proveedor" class="text-xs font-semibold text-zinc-400">
                                                    {{ item.proveedor }}
                                                </span>
                                                <span v-if="item.isbn" class="text-[10px] font-mono text-zinc-400 bg-white/5 border border-white/10 px-2 py-0.5 rounded">
                                                    ISBN: {{ item.isbn }}
                                                </span>
                                                <span v-if="item.permite_preventa" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider text-cyan-400 bg-cyan-500/10 border border-cyan-500/20 px-2 py-0.5 rounded-md">
                                                    ⚡ Preventa (10% OFF)
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selector de Cantidad -->
                                    <div class="pt-2 flex flex-wrap items-center gap-4">
                                        <div class="bg-[#0d0d0f] border border-white/10 rounded-xl px-3 py-1.5 flex items-center gap-3">
                                            <button
                                                @click="actualizarCantidad(item.libro_id, item.cantidad - 1)"
                                                :disabled="item.cantidad <= 1"
                                                class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-white flex items-center justify-center font-bold text-sm transition-all active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                                            >−</button>
                                            <span class="w-6 text-center text-sm font-bold font-mono text-white">{{ item.cantidad }}</span>
                                            <button
                                                @click="actualizarCantidad(item.libro_id, item.cantidad + 1)"
                                                :disabled="item.cantidad >= getLimiteItem(item)"
                                                class="w-7 h-7 rounded-lg bg-white/5 hover:bg-white/10 text-white flex items-center justify-center font-bold text-sm transition-all active:scale-90 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                                            >+</button>
                                        </div>

                                        <button
                                            @click="quitar(item.libro_id)"
                                            class="text-xs font-semibold text-zinc-400 hover:text-rose-400 transition-colors flex items-center gap-1.5 cursor-pointer group"
                                        >
                                            <svg class="w-4 h-4 text-zinc-500 group-hover:text-rose-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span>Quitar</span>
                                        </button>
                                    </div>

                                    <div v-if="item.cantidad >= getLimiteItem(item)" class="mt-2 text-[11px] font-semibold text-amber-400 bg-amber-400/10 border border-amber-400/20 px-2.5 py-1 rounded-lg inline-flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>Límite de unidades máximo por producto alcanzado.</span>
                                    </div>
                                </div>

                                <!-- Precio Unitario -->
                                <div class="shrink-0 text-left sm:text-right flex flex-col justify-center self-stretch pt-2 sm:pt-0 border-t sm:border-t-0 border-white/5">
                                    <div class="space-y-1">
                                        <div class="text-xl font-bold font-mono text-white" :class="{ 'text-cyan-400': item.permite_preventa }">
                                            {{ formatPrecio(item.precio) }}
                                        </div>
                                        <div v-if="item.permite_preventa" class="text-xs font-mono text-zinc-500 line-through">
                                            {{ formatPrecio(item.precio_original || item.precio) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen del Pedido -->
                    <div class="lg:col-span-1">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-2xl sticky top-28 space-y-6">
                            <div class="flex items-center justify-between border-b border-white/5 pb-4">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                                    Resumen del Pedido
                                </h3>
                                <span class="text-xs font-semibold text-zinc-400 bg-white/5 border border-white/10 px-2.5 py-0.5 rounded-full">
                                    {{ items.count }} item{{ items.count !== 1 ? 's' : '' }}
                                </span>
                            </div>

                            <!-- Desglose de totales -->
                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-zinc-400 font-medium">Subtotal</span>
                                    <span class="font-mono font-bold text-white text-sm">{{ formatPrecio(items.subtotal || items.total) }}</span>
                                </div>
                                
                                <div v-if="items.descuento_suscripcion > 0" class="flex justify-between items-center text-xs text-emerald-400 font-semibold bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1.5 rounded-lg">
                                    <span class="flex items-center gap-1.5">
                                        <span>⭐</span>
                                        <span>Descuento Suscriptor (5%)</span>
                                    </span>
                                    <span class="font-mono font-bold">-{{ formatPrecio(items.descuento_suscripcion) }}</span>
                                </div>

                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-zinc-400 font-medium">Envío</span>
                                    <span class="text-zinc-500 font-medium text-[11px]">Calculado en el checkout</span>
                                </div>

                                <div class="border-t border-white/5 pt-4 flex justify-between items-baseline">
                                    <span class="text-sm font-bold uppercase tracking-wider text-white">Total</span>
                                    <span class="text-2xl sm:text-3xl font-bold font-mono text-white">{{ formatPrecio(items.total) }}</span>
                                </div>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="space-y-3 pt-2">
                                <button
                                    @click="irACheckout"
                                    class="w-full py-4 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                                >
                                    <span>Finalizar Compra</span>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </button>

                                <Link
                                    :href="route('catalogo.index')"
                                    class="w-full py-3 bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 font-semibold text-xs uppercase tracking-wider rounded-xl transition-all text-center block"
                                >
                                    ← Seguir Comprando
                                </Link>
                            </div>

                            <!-- Bloque de Beneficios / Confianza -->
                            <div class="border-t border-white/5 pt-4 space-y-2.5">
                                <div class="flex items-center gap-2.5 text-xs text-zinc-400 font-medium">
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Retiro presencial gratis en sucursal</span>
                                </div>
                                <div class="flex items-center gap-2.5 text-xs text-zinc-400 font-medium">
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Envíos seguros a todo el país</span>
                                </div>
                                <div class="flex items-center gap-2.5 text-xs text-zinc-400 font-medium">
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    <span>Compra 100% protegida y verificada</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-carrito,
.page-carrito * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>

