<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Nosotros | PuroComic" />

    <PublicLayout>
        <!-- Hero -->
        <div class="relative overflow-hidden py-24 bg-gradient-to-b from-brand-red/10 to-transparent">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-6xl md:text-8xl font-black uppercase tracking-tighter leading-none mb-6">
                    Nuestra <span class="text-brand-red italic">Historia</span>
                </h1>
                <p class="text-white/40 text-lg max-w-2xl mx-auto font-medium">
                    Más de 30 años conectando lectores con historias que transforman.
                </p>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 space-y-20">

            <!-- Historia -->
            <section class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tighter mb-6">Quiénes <span class="text-brand-red italic">somos</span></h2>
                    <p class="text-white/60 leading-relaxed font-medium">
                        Somos una librería especializada en historietas, manga y literatura de autor con más de 30 años de trayectoria en Santa Fe, Argentina.
                        Nacimos con la pasión de acercar el noveno arte a todos, y hoy seguimos fieles a ese propósito con dos sucursales en Rosario y Funes.
                    </p>
                </div>
                <div class="aspect-square rounded-3xl bg-white/[0.03] border border-white/10 flex items-center justify-center">
                    <div class="w-24 h-24 bg-brand-red flex items-center justify-center rounded-2xl rotate-6">
                        <span class="text-5xl font-black italic text-white">P</span>
                    </div>
                </div>
            </section>

            <!-- Datos -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div v-for="dato in [
                    { numero: '+30', label: 'Años de historia' },
                    { numero: '2', label: 'Sucursales' },
                    { numero: '+1000', label: 'Títulos disponibles' },
                    { numero: '100%', label: 'Pasión por la lectura' },
                ]" :key="dato.label" class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 text-center">
                    <div class="text-4xl font-black text-brand-red italic mb-2">{{ dato.numero }}</div>
                    <div class="text-xs font-black uppercase tracking-widest text-white/30">{{ dato.label }}</div>
                </div>
            </section>

            <!-- Sucursales -->
            <section>
                <h2 class="text-4xl font-black uppercase tracking-tighter mb-10">Nuestras <span class="text-brand-red italic">Sucursales</span></h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8 hover:border-brand-red/30 transition-all">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-brand-red mb-4 block">Sucursal Principal</span>
                        <h3 class="text-2xl font-black uppercase tracking-tighter mb-3">Rosario</h3>
                        <div class="space-y-2 text-sm text-white/40 font-medium">
                            <p>Lunes a Viernes: 9:00 — 19:00</p>
                            <p>Sábados: 10:00 — 14:00</p>
                        </div>
                    </div>
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8 hover:border-brand-red/30 transition-all">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-brand-red mb-4 block">Sucursal</span>
                        <h3 class="text-2xl font-black uppercase tracking-tighter mb-3">Funes</h3>
                        <div class="space-y-2 text-sm text-white/40 font-medium">
                            <p>Lunes a Viernes: 9:00 — 19:00</p>
                            <p>Sábados: 10:00 — 14:00</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="text-center py-12 border-t border-white/5">
                <h3 class="text-3xl font-black uppercase tracking-tighter mb-6">¿Listo para explorar?</h3>
                <Link :href="route('catalogo.index')" class="btn-primary py-4 px-10 rounded-full text-sm font-black uppercase tracking-widest inline-block">
                    Ver Catálogo
                </Link>
            </section>
        </div>
    </PublicLayout>
</template>
