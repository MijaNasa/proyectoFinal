<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    libroMaster: Object,
});

const cantidades = ref({});

const getPrecioFormatted = (precio) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(precio);
};

const getStockTotal = (variante) => {
    return variante.stocks?.reduce((sum, s) => sum + s.cantidad_disponible, 0) ?? 0;
};

const getStockStatus = (variante) => {
    const total = getStockTotal(variante);
    if (total === 0) return 'sin_stock';
    if (total < 5)  return 'pocos';
    return 'disponible';
};

const MAX_POR_COMPRA = 5;

const getLimite = (variante) => Math.min(MAX_POR_COMPRA, getStockTotal(variante));

const getCantidad = (varianteId) => cantidades.value[varianteId] ?? 1;

const agregarAlCarrito = (variante) => {
    router.post(route('carrito.agregar'), {
        libro_id: variante.id,
        cantidad: getCantidad(variante.id),
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            cantidades.value[variante.id] = 1;
        },
    });
};
</script>

<template>
    <Head :title="libroMaster.titulo" />

    <PublicLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Breadcrumbs -->
            <nav class="flex mb-8 text-[10px] font-black uppercase tracking-widest text-white/30">
                <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Catálogo</Link>
                <span class="mx-3">/</span>
                <span class="text-white/60">{{ libroMaster.categoria?.nombre }}</span>
                <span class="mx-3">/</span>
                <span class="text-brand-red">{{ libroMaster.titulo }}</span>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <!-- Book Image -->
                <div class="lg:col-span-3">
                    <div class="sticky top-24">
                        <div class="relative group">
                            <div class="absolute inset-0 bg-brand-red/20 blur-[60px] opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <img
                                :src="libroMaster.portada_url"
                                :alt="libroMaster.titulo"
                                class="relative w-full aspect-[2/3] object-cover rounded-2xl border border-white/10 shadow-2xl transition-transform duration-700 group-hover:scale-[1.02]"
                            >
                        </div>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="lg:col-span-9">
                    <div class="space-y-8">
                        <section>
                            <span class="px-2 py-0.5 bg-white/5 border border-white/10 rounded-full text-[10px] font-black uppercase tracking-widest text-white/60 mb-4 inline-block">
                                {{ libroMaster.categoria?.nombre }}
                            </span>
                            <h1 class="text-3xl md:text-5xl font-black uppercase tracking-tighter leading-none text-white mb-2">
                                {{ libroMaster.titulo }}
                            </h1>
                            <p class="text-lg font-black italic text-brand-red/80 tracking-tight" v-if="libroMaster.titulo_original">
                                {{ libroMaster.titulo_original }}
                            </p>
                            <div class="mt-4 flex items-center gap-3">
                                <div class="w-9 h-9 bg-white/5 rounded-full flex items-center justify-center font-black italic border border-white/10 text-brand-red text-sm">
                                    {{ libroMaster.autor?.nombre?.charAt(0) }}{{ libroMaster.autor?.apellido?.charAt(0) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/30">Escrito por</span>
                                    <span class="text-sm font-bold uppercase tracking-tight text-white/80">{{ libroMaster.autor?.apellido }}, {{ libroMaster.autor?.nombre }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- Synopsis -->
                        <section v-if="libroMaster.libros?.[0]?.synopsis">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-3 underline decoration-2 underline-offset-4">Sinopsis</h3>
                            <div class="text-white/60 text-sm leading-relaxed font-medium line-clamp-4">
                                {{ libroMaster.libros[0].synopsis }}
                            </div>
                        </section>

                        <!-- Editions -->
                        <section class="space-y-4">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-4 underline decoration-2 underline-offset-4">Ediciones Disponibles</h3>

                            <div
                                v-for="variante in libroMaster.libros"
                                :key="variante.id"
                                class="bg-white/[0.03] border border-white/10 rounded-xl p-5 transition-all hover:bg-white/[0.06] hover:border-brand-red/30"
                            >
                                <!-- Header edición -->
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-base font-black text-white uppercase tracking-tighter">{{ variante.editorial?.nombre }}</span>
                                            <span class="px-2 py-0.5 bg-white/10 rounded text-[10px] font-black uppercase tracking-widest text-white/40">{{ variante.año_edicion }}</span>
                                            <span v-if="variante.serie" class="px-2 py-0.5 bg-brand-red/20 border border-brand-red/30 rounded text-[10px] font-black uppercase tracking-widest text-brand-red/80">
                                                {{ variante.serie.nombre }} #{{ variante.numero_tomo }}
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-white/30">
                                            {{ variante.idioma?.nombre }} · {{ variante.cantidad_paginas }} págs · ISBN: {{ variante.isbn }}
                                        </p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="text-2xl font-black text-brand-red italic">
                                            {{ variante.precio_actual ? getPrecioFormatted(variante.precio_actual.precio_venta) : 'Consultar' }}
                                        </div>
                                        <span
                                            :class="{
                                                'text-green-400': getStockStatus(variante) === 'disponible',
                                                'text-yellow-400': getStockStatus(variante) === 'pocos',
                                                'text-red-400': getStockStatus(variante) === 'sin_stock',
                                            }"
                                            class="text-[10px] font-black uppercase tracking-widest"
                                        >
                                            {{ getStockStatus(variante) === 'disponible' ? 'En stock' : getStockStatus(variante) === 'pocos' ? 'Quedan pocos' : 'Sin stock' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Stock por sucursal -->
                                <div class="mt-4 pt-4 border-t border-white/5">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-white/20 mb-2 block">Disponibilidad por Sucursal</span>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <div v-for="stock in variante.stocks" :key="stock.id" class="p-3 bg-black/40 rounded-lg border border-white/5 flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full flex-shrink-0" :class="stock.cantidad_disponible > 0 ? 'bg-green-400' : 'bg-white/10'"></div>
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-tighter text-white/40 line-clamp-1">{{ stock.sucursal?.nombre }}</div>
                                                <div class="text-[10px] font-bold" :class="stock.cantidad_disponible > 0 ? 'text-green-400' : 'text-white/20'">
                                                    {{ stock.cantidad_disponible > 0 ? 'Disponible' : 'No disponible' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Agregar al carrito -->
                                <div class="mt-4 pt-4 border-t border-white/5 flex items-center gap-3">
                                    <!-- Selector de cantidad -->
                                    <div class="flex items-center gap-2 bg-white/5 border border-white/10 rounded-lg px-3 py-2">
                                        <button
                                            @click="cantidades[variante.id] = Math.max(1, getCantidad(variante.id) - 1)"
                                            :disabled="getStockStatus(variante) === 'sin_stock' || getCantidad(variante.id) <= 1"
                                            class="font-black text-lg leading-none w-6 text-center transition-colors"
                                            :class="getCantidad(variante.id) <= 1 ? 'text-white/20 cursor-not-allowed' : 'text-white/50 hover:text-white'"
                                        >−</button>
                                        <span class="text-sm font-black text-white w-6 text-center">{{ getCantidad(variante.id) }}</span>
                                        <button
                                            @click="cantidades[variante.id] = Math.min(getLimite(variante), getCantidad(variante.id) + 1)"
                                            :disabled="getStockStatus(variante) === 'sin_stock' || getCantidad(variante.id) >= getLimite(variante)"
                                            class="font-black text-lg leading-none w-6 text-center transition-colors"
                                            :class="getCantidad(variante.id) >= getLimite(variante) ? 'text-white/20 cursor-not-allowed' : 'text-white/50 hover:text-white'"
                                        >+</button>
                                    </div>
                                    <transition name="fade-msg">
                                        <span
                                            v-if="getCantidad(variante.id) >= getLimite(variante)"
                                            class="text-[10px] font-black uppercase tracking-widest text-yellow-400"
                                        >
                                            {{ getLimite(variante) === getStockTotal(variante) ? 'Stock máximo' : 'Límite por compra' }}
                                        </span>
                                    </transition>

                                    <button
                                        @click="agregarAlCarrito(variante)"
                                        :disabled="getStockStatus(variante) === 'sin_stock'"
                                        class="flex-1 py-3 rounded-xl font-black text-sm uppercase tracking-widest transition-all"
                                        :class="getStockStatus(variante) === 'sin_stock'
                                            ? 'bg-white/5 text-white/20 cursor-not-allowed border border-white/5'
                                            : 'bg-brand-red hover:bg-brand-red/80 text-white'"
                                    >
                                        {{ getStockStatus(variante) === 'sin_stock' ? 'Sin Stock' : 'Agregar al Carrito' }}
                                    </button>
                                </div>
                            </div>
                        </section>

                        <div class="pt-4">
                            <Link :href="route('catalogo.index')" class="btn-primary w-full py-5 rounded-2xl flex items-center justify-center gap-3 text-sm tracking-widest group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Volver al Catálogo
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.fade-msg-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-msg-enter-from   { opacity: 0; transform: translateX(-4px); }
</style>
