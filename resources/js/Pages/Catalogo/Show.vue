<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    libro: Object,
    relacionados: Array,
});

const cantidad = ref(1);

const getPrecio = (libro) => {
    if (libro.precio_actual) {
        let precio = libro.precio_actual.precio_venta;
        if (libro.permite_preventa) {
            precio = precio * 0.90;
        }
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(precio);
    }
    return 'Consultar';
};

const getPrecioOriginal = (libro) => {
    if (libro.precio_actual) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(libro.precio_actual.precio_venta);
    }
    return '';
};

const getStockTotal = (libro) => {
    return libro.stocks?.reduce((sum, s) => sum + (s.cantidad_disponible ?? 0), 0) ?? 0;
};

const getStockStatus = (libro) => {
    const total = getStockTotal(libro);
    if (total === 0) return 'sin_stock';
    if (total < 5)  return 'pocos';
    return 'disponible';
};

const agregarAlCarrito = () => {
    router.post(route('carrito.agregar'), {
        libro_id: props.libro.id,
        cantidad: cantidad.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            cantidad.value = 1;
        },
    });
};
</script>

<template>
    <Head :title="libro.master?.titulo + (libro.numero_tomo ? ' - Tomo ' + libro.numero_tomo : '')" />

    <PublicLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Breadcrumbs -->
            <nav class="flex mb-8 text-[10px] font-black uppercase tracking-widest text-white/30">
                <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Catálogo</Link>
                <span class="mx-3">/</span>
                <span class="text-white/60">{{ libro.master?.categoria?.nombre }}</span>
                <span class="mx-3">/</span>
                <span class="text-brand-red line-clamp-1">{{ libro.master?.titulo }}</span>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <!-- Book Image -->
                <div class="lg:col-span-4">
                    <div class="sticky top-24">
                        <div class="relative group">
                            <div class="absolute inset-0 bg-brand-red/20 blur-[60px] opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <img
                                :src="libro.master?.portada_url"
                                :alt="libro.master?.titulo"
                                class="relative w-full aspect-[2/3] object-cover rounded-2xl border border-white/10 shadow-2xl transition-transform duration-700 group-hover:scale-[1.02]"
                            >
                        </div>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="lg:col-span-8">
                    <div class="space-y-8">
                        <section>
                            <span class="px-2 py-0.5 bg-white/5 border border-white/10 rounded-full text-[10px] font-black uppercase tracking-widest text-white/60 mb-4 inline-block">
                                {{ libro.master?.categoria?.nombre }}
                            </span>
                            <h1 class="text-3xl md:text-5xl font-black uppercase tracking-tighter leading-none text-white mb-2">
                                {{ libro.master?.titulo }} {{ libro.numero_tomo ? ' - Tomo ' + libro.numero_tomo : '' }}
                            </h1>
                            <p class="text-lg font-black italic text-brand-red/80 tracking-tight" v-if="libro.master?.titulo_original">
                                {{ libro.master?.titulo_original }}
                            </p>
                            
                            <div class="mt-6 flex flex-wrap gap-4 text-xs font-bold uppercase tracking-widest text-white/40">
                                <span v-if="libro.master?.proveedor">Editorial: {{ libro.master.proveedor.nombre_empresa }}</span>
                                <span v-if="libro.master?.idioma">{{ libro.master.idioma.nombre }}</span>
                                <span v-if="libro.año_edicion">{{ libro.año_edicion }}</span>
                                <span v-if="libro.cantidad_paginas">{{ libro.cantidad_paginas }} págs</span>
                                <span v-if="libro.isbn">ISBN: {{ libro.isbn }}</span>
                            </div>

                            <div class="mt-8 flex items-center gap-4 border-t border-white/10 pt-8">
                                <div class="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center font-black italic border border-white/10 text-brand-red text-lg">
                                    {{ libro.master?.autor?.nombre?.charAt(0) }}{{ libro.master?.autor?.apellido?.charAt(0) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/30">Escrito por</span>
                                    <span class="text-base font-bold uppercase tracking-tight text-white/80">{{ libro.master?.autor?.apellido }}, {{ libro.master?.autor?.nombre }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- Synopsis -->
                        <section v-if="libro.synopsis">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-3 underline decoration-2 underline-offset-4">Sinopsis</h3>
                            <div class="text-white/60 text-sm leading-relaxed font-medium">
                                {{ libro.synopsis }}
                            </div>
                        </section>

                        <!-- Pricing and Add to Cart -->
                        <section class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 mt-8">
                            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 mb-6">
                                <div>
                                    <div class="text-sm font-black uppercase tracking-widest text-white/40 mb-1">Precio</div>
                                    <div class="text-4xl font-black text-brand-red italic flex flex-col items-start">
                                        <span v-if="libro.permite_preventa" class="text-lg font-black text-white/40 line-through leading-none">{{ getPrecioOriginal(libro) }}</span>
                                        <span>{{ getPrecio(libro) }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span
                                        :class="{
                                            'text-green-400': getStockStatus(libro) === 'disponible',
                                            'text-yellow-400': getStockStatus(libro) === 'pocos',
                                            'text-red-400': getStockStatus(libro) === 'sin_stock',
                                        }"
                                        class="text-[12px] font-black uppercase tracking-widest bg-white/5 px-3 py-1 rounded-full border border-white/5"
                                    >
                                        {{ getStockStatus(libro) === 'disponible' ? 'En stock' : getStockStatus(libro) === 'pocos' ? 'Quedan pocos (' + getStockTotal(libro) + ')' : 'Sin stock' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <!-- Selector de cantidad -->
                                <div class="flex items-center gap-2 bg-black/40 border border-white/10 rounded-xl px-4 py-3">
                                    <button
                                        @click="cantidad = Math.max(1, cantidad - 1)"
                                        :disabled="getStockStatus(libro) === 'sin_stock' || cantidad <= 1"
                                        class="font-black text-xl leading-none w-8 text-center transition-colors"
                                        :class="cantidad <= 1 ? 'text-white/20 cursor-not-allowed' : 'text-white/50 hover:text-white'"
                                    >−</button>
                                    <span class="text-lg font-black text-white w-8 text-center">{{ cantidad }}</span>
                                    <button
                                        @click="cantidad = Math.min(getStockTotal(libro), cantidad + 1)"
                                        :disabled="getStockStatus(libro) === 'sin_stock' || cantidad >= getStockTotal(libro)"
                                        class="font-black text-xl leading-none w-8 text-center transition-colors"
                                        :class="cantidad >= getStockTotal(libro) ? 'text-white/20 cursor-not-allowed' : 'text-white/50 hover:text-white'"
                                    >+</button>
                                </div>

                                <button
                                    @click="agregarAlCarrito"
                                    :disabled="getStockStatus(libro) === 'sin_stock'"
                                    class="flex-1 py-4 rounded-xl font-black text-sm md:text-base uppercase tracking-widest transition-all"
                                    :class="getStockStatus(libro) === 'sin_stock'
                                        ? 'bg-white/5 text-white/20 cursor-not-allowed border border-white/5'
                                        : 'bg-brand-red hover:bg-brand-red/80 text-white shadow-lg shadow-brand-red/20'"
                                >
                                    {{ getStockStatus(libro) === 'sin_stock' ? 'Agotado' : 'Agregar al Carrito' }}
                                </button>
                            </div>
                        </section>

                        <div class="pt-4">
                            <Link :href="route('catalogo.index')" class="btn-primary w-full py-5 rounded-2xl flex items-center justify-center gap-3 text-sm tracking-widest group bg-white/5 hover:bg-white/10 border-white/10 text-white/60 hover:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Volver al Catálogo
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Books Section -->
            <div v-if="relacionados && relacionados.length > 0" class="mt-24 border-t border-white/10 pt-16">
                <h3 class="text-lg font-black uppercase tracking-[0.2em] text-brand-red mb-8">También te puede interesar</h3>
                
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <Link
                        v-for="rel in relacionados"
                        :key="rel.id"
                        :href="route('catalogo.show', rel.id)"
                        class="group"
                    >
                        <div class="relative aspect-[2/3] overflow-hidden rounded-xl bg-white/5 border border-white/10 transition-all duration-300 group-hover:border-brand-red group-hover:-translate-y-1">
                            <img
                                :src="rel.master?.portada_url"
                                :alt="rel.master?.titulo"
                                class="w-full h-full object-cover transition-all duration-500 group-hover:scale-105"
                            >
                        </div>
                        <div class="mt-3">
                            <h4 class="font-black uppercase tracking-tighter text-xs leading-tight transition-colors line-clamp-2 group-hover:text-brand-red text-white">
                                {{ rel.master?.titulo }} {{ rel.numero_tomo ? '- Tomo ' + rel.numero_tomo : '' }}
                            </h4>
                            <div class="text-brand-red font-bold text-sm mt-1 flex flex-col">
                                <span v-if="rel.permite_preventa" class="text-[10px] font-black text-white/40 line-through leading-none mb-1">{{ getPrecioOriginal(rel) }}</span>
                                <span>{{ getPrecio(rel) }}</span>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
