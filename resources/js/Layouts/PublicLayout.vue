<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const isMenuOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-[#0A0A0A] text-white font-sans selection:bg-brand-red selection:text-white">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 bg-[#0A0A0A]/80 backdrop-blur-md border-b border-white/5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20">
                    <div class="flex items-center">
                        <Link :href="route('catalogo.index')" class="flex items-center gap-2 group">
                            <div class="w-10 h-10 bg-brand-red flex items-center justify-center rounded-lg rotate-3 group-hover:rotate-6 transition-transform shadow-[0_0_20px_rgba(230,25,25,0.3)]">
                                <span class="text-2xl font-black italic">P</span>
                            </div>
                            <span class="text-xl font-black uppercase tracking-tighter group-hover:text-brand-red transition-colors">Puro<span class="text-brand-red">Comic</span> <span class="font-light italic text-white/40 ml-1">Store</span></span>
                        </Link>
                    </div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <Link :href="route('catalogo.index')" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">Catálogo</Link>
                        <a href="#" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">Novedades</a>
                        <a href="#" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">Nosotros</a>
                        <Link :href="route('login')" class="btn-primary py-2 px-6 rounded-full text-xs shadow-none border border-brand-red/50 hover:bg-brand-red">Admin</Link>
                    </div>

                    <!-- Mobile Toggle -->
                    <div class="md:hidden flex items-center">
                        <button @click="isMenuOpen = !isMenuOpen" class="text-white">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="!isMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <transition name="fade">
                <div v-if="isMenuOpen" class="md:hidden bg-[#0F0F0F] border-b border-white/5 p-4 space-y-4">
                    <Link :href="route('catalogo.index')" class="block text-lg font-bold uppercase text-white/60 hover:text-brand-red">Catálogo</Link>
                    <a href="#" class="block text-lg font-bold uppercase text-white/60 hover:text-brand-red">Novedades</a>
                    <a href="#" class="block text-lg font-bold uppercase text-white/60 hover:text-brand-red">Nosotros</a>
                    <Link :href="route('login')" class="block btn-primary text-center">Admin Access</Link>
                </div>
            </transition>
        </nav>

        <!-- Main Content -->
        <main>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-black border-t border-white/5 py-20 mt-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-12">
                <div class="col-span-1 md:col-span-2">
                    <Link :href="route('catalogo.index')" class="flex items-center gap-2 mb-6">
                        <div class="w-8 h-8 bg-brand-red flex items-center justify-center rounded transform -rotate-6">
                            <span class="text-xl font-black italic">P</span>
                        </div>
                        <span class="text-lg font-black uppercase tracking-tighter text-white">Puro<span class="text-brand-red">Comic</span></span>
                    </Link>
                    <p class="text-white/40 max-w-sm font-medium leading-relaxed">
                        Explora nuestra exclusiva colección de historietas y libros. Un sistema que evoluciona junto a tu pasión por el noveno arte.
                    </p>
                </div>
                <div>
                    <h4 class="text-brand-red font-black uppercase tracking-widest text-xs mb-6">Explorar</h4>
                    <ul class="space-y-4">
                        <li><Link :href="route('catalogo.index')" class="text-white/60 hover:text-white transition-colors text-sm">Ver Catálogo</Link></li>
                        <li><a href="#" class="text-white/60 hover:text-white transition-colors text-sm">Autores</a></li>
                        <li><a href="#" class="text-white/60 hover:text-white transition-colors text-sm">Editoriales</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-brand-red font-black uppercase tracking-widest text-xs mb-6">Soporte</h4>
                    <ul class="space-y-4">
                        <li><a href="#" class="text-white/60 hover:text-white transition-colors text-sm">Contacto</a></li>
                        <li><a href="#" class="text-white/60 hover:text-white transition-colors text-sm">Preguntas Frecuentes</a></li>
                        <li><Link :href="route('login')" class="text-white/60 hover:text-white transition-colors text-sm">Panel de Control</Link></li>
                    </ul>
                </div>
            </div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-20 pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center text-[10px] uppercase tracking-widest text-white/20 font-black">
                <p>&copy; 2026 PuroComic ERP. Todos los derechos reservados.</p>
                <div class="flex gap-8 mt-4 md:mt-0">
                    <a href="#" class="hover:text-white transition-colors">Privacidad</a>
                    <a href="#" class="hover:text-white transition-colors">Términos</a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
