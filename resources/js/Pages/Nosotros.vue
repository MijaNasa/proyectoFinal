<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Nosotros | PuroComic" />
    <PublicLayout>
        <div class="page-nosotros">
            <!-- Hero -->
            <div class="relative overflow-hidden py-16 sm:py-24 bg-gradient-to-b from-white/[0.04] to-transparent">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 class="text-5xl md:text-7xl font-bold tracking-tight uppercase leading-none mb-4 text-white">
                        Nuestra <span class="text-zinc-400 italic">Historia</span>
                    </h1>
                    <p class="text-zinc-400 text-sm font-medium max-w-xl mx-auto">
                        Más de 30 años conectando lectores con historias que transforman.
                    </p>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="max-w-4xl mx-auto space-y-12">

                    <!-- Historia -->
                    <section class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center bg-[#131316] border border-white/5 rounded-2xl p-8 shadow-xl">
                        <div class="space-y-4">
                            <h2 class="text-2xl font-bold uppercase tracking-tight text-white">Quiénes Somos</h2>
                            <p class="text-zinc-300 text-xs font-medium leading-relaxed">
                                Somos una librería especializada en historietas, manga y literatura de autor con más de 30 años de trayectoria en Santa Fe, Argentina.
                                Nacimos con la pasión de acercar el noveno arte a todos, y hoy seguimos fieles a ese propósito con dos sucursales en Rosario y Funes.
                            </p>
                        </div>
                        <div class="aspect-square rounded-2xl bg-[#0d0d0f] border border-white/5 flex items-center justify-center">
                            <div class="w-20 h-20 bg-white text-black flex items-center justify-center rounded-2xl shadow-lg">
                                <span class="text-4xl font-bold">P</span>
                            </div>
                        </div>
                    </section>

                    <!-- Datos -->
                    <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div
                            v-for="dato in [
                                { numero: '+30', label: 'Años de historia' },
                                { numero: '2', label: 'Sucursales' },
                                { numero: '+1000', label: 'Títulos disponibles' },
                                { numero: '100%', label: 'Pasión por la lectura' },
                            ]"
                            :key="dato.label"
                            class="bg-[#131316] border border-white/5 rounded-2xl p-6 text-center shadow-xl"
                        >
                            <div class="text-3xl font-bold font-mono text-white mb-1">{{ dato.numero }}</div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ dato.label }}</div>
                        </div>
                    </section>

                    <!-- Sucursales -->
                    <section class="space-y-4">
                        <h2 class="text-xl font-bold uppercase tracking-tight text-white">Nuestras Sucursales</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 hover:border-white/10 transition-all shadow-xl space-y-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Sucursal Principal</span>
                                <h3 class="text-xl font-bold uppercase text-white">Rosario</h3>
                                <div class="space-y-1 text-xs text-zinc-300 font-medium">
                                    <p>Lunes a Viernes: 9:00 — 19:00</p>
                                    <p>Sábados: 10:00 — 14:00</p>
                                </div>
                            </div>
                            <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 hover:border-white/10 transition-all shadow-xl space-y-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Sucursal</span>
                                <h3 class="text-xl font-bold uppercase text-white">Funes</h3>
                                <div class="space-y-1 text-xs text-zinc-300 font-medium">
                                    <p>Lunes a Viernes: 9:00 — 19:00</p>
                                    <p>Sábados: 10:00 — 14:00</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- CTA -->
                    <section class="text-center py-12 bg-[#131316] border border-white/5 rounded-2xl shadow-xl space-y-4">
                        <h3 class="text-2xl font-bold uppercase tracking-tight text-white">¿Listo para explorar?</h3>
                        <Link
                            :href="route('catalogo.index')"
                            class="inline-block px-8 py-3 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95"
                        >
                            Ver Catálogo
                        </Link>
                    </section>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-nosotros,
.page-nosotros * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
