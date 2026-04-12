<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    libroMaster: Object
});

const getPrecioFormatted = (precio) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(precio);
};
</script>

<template>
    <Head :title="libroMaster.titulo" />

    <PublicLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <!-- Breadcrumbs -->
            <nav class="flex mb-12 text-[10px] font-black uppercase tracking-widest text-white/30">
                <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Catálogo</Link>
                <span class="mx-3">/</span>
                <span class="text-white/60">{{ libroMaster.categoria?.nombre }}</span>
                <span class="mx-3">/</span>
                <span class="text-brand-red">{{ libroMaster.titulo }}</span>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-20">
                <!-- Book Image -->
                <div class="lg:col-span-5">
                    <div class="sticky top-32">
                        <div class="relative group">
                            <div class="absolute inset-0 bg-brand-red/20 blur-[100px] opacity-20 group-hover:opacity-40 transition-opacity"></div>
                            <img 
                                :src="libroMaster.portada_url" 
                                :alt="libroMaster.titulo" 
                                class="relative w-full aspect-[2/3] object-cover rounded-[2rem] border border-white/10 shadow-2xl transition-transform duration-700 group-hover:scale-[1.02]"
                            >
                        </div>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="lg:col-span-7">
                    <div class="space-y-12">
                        <section>
                            <span class="px-3 py-1 bg-white/5 border border-white/10 rounded-full text-[10px] font-black uppercase tracking-widest text-white/60 mb-6 inline-block">
                                {{ libroMaster.categoria?.nombre }}
                            </span>
                            <h1 class="text-5xl md:text-7xl font-black uppercase tracking-tighter leading-none text-white mb-4">
                                {{ libroMaster.titulo }}
                            </h1>
                            <p class="text-2xl font-black italic text-brand-red/80 tracking-tight" v-if="libroMaster.titulo_original">
                                {{ libroMaster.titulo_original }}
                            </p>
                            <div class="mt-8 flex items-center gap-4">
                                <div class="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center font-black italic border border-white/10 text-brand-red">
                                    {{ libroMaster.autor?.nombre?.charAt(0) }}{{ libroMaster.autor?.apellido?.charAt(0) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white/30">Escrito por</span>
                                    <span class="text-xl font-bold uppercase tracking-tight text-white/80">{{ libroMaster.autor?.apellido }}, {{ libroMaster.autor?.nombre }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- Synopsis -->
                        <section v-if="libroMaster.libros?.[0]?.synopsis">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">Sinopsis</h3>
                            <div class="text-white/60 text-lg leading-relaxed font-medium space-y-4 prose prose-invert max-w-none">
                                {{ libroMaster.libros[0].synopsis }}
                            </div>
                        </section>

                        <!-- Variants / Editions -->
                        <section class="space-y-8">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">Ediciones Disponibles</h3>
                            
                            <div v-for="variante in libroMaster.libros" :key="variante.id" class="bg-white/[0.03] border border-white/10 rounded-2xl p-8 transition-all hover:bg-white/[0.06] hover:border-brand-red/50 group">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="text-2xl font-black text-white uppercase tracking-tighter">{{ variante.editorial?.nombre }}</span>
                                            <span class="px-2 py-0.5 bg-white/10 rounded text-[10px] font-black uppercase tracking-widest text-white/40">{{ variante.año_edicion }}</span>
                                        </div>
                                        <p class="text-xs font-bold uppercase tracking-widest text-white/30">
                                            {{ variante.idioma?.nombre }} • {{ variante.cantidad_paginas }} Páginas • ISBN: {{ variante.isbn }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-3xl font-black text-brand-red italic mb-2">
                                            {{ variante.precio_actual ? getPrecioFormatted(variante.precio_actual.precio_venta) : 'Consultar' }}
                                        </div>
                                        <span v-if="variante.stocks?.reduce((acc, s) => acc + s.cantidad_disponible, 0) > 0" class="text-[10px] font-black uppercase tracking-widest text-green-500">
                                            En Stock
                                        </span>
                                        <span v-else class="text-[10px] font-black uppercase tracking-widest text-brand-red">
                                            Agotado
                                        </span>
                                    </div>
                                </div>

                                <!-- Stock per branch -->
                                <div class="mt-8 pt-8 border-t border-white/5">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-white/20 mb-4 block">Disponibilidad por Sucursal</span>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div v-for="stock in variante.stocks" :key="stock.id" class="p-3 bg-black/40 rounded-lg border border-white/5">
                                            <div class="text-[10px] font-black uppercase tracking-tighter text-white/40 mb-1 line-clamp-1">{{ stock.sucursal?.nombre }}</div>
                                            <div class="text-sm font-black" :class="stock.cantidad_disponible > 0 ? 'text-white' : 'text-white/20'">
                                                {{ stock.cantidad_disponible }} <span class="text-[8px] opacity-40">Uds</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="pt-12">
                            <Link :href="route('catalogo.index')" class="btn-primary w-full py-6 rounded-2xl flex items-center justify-center gap-3 text-sm tracking-widest group">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                                Volver al Catálogo
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
