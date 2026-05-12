<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const isMenuOpen = ref(false);
const page = usePage();
const carritoCount = computed(() => page.props.carritoCount ?? 0);
const user = computed(() => page.props.auth?.user ?? null);

const toast = ref(null);
let toastTimer = null;

const showToast = (msg, type) => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { msg, type };
    toastTimer = setTimeout(() => toast.value = null, 3500);
};

watch(() => page.props.flash, (flash) => {
    if (flash?.success) showToast(flash.success, 'success');
    else if (flash?.warning) showToast(flash.warning, 'warning');
    else if (flash?.error) showToast(flash.error, 'error');
}, { deep: true });
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
                    <div class="hidden md:flex items-center space-x-6">
                        <Link :href="route('catalogo.index')" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">Catálogo</Link>
                        <Link :href="route('nosotros')" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">Nosotros</Link>

                        <!-- Carrito -->
                        <Link :href="route('carrito.index')" class="relative p-2 text-white/60 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span v-if="carritoCount > 0" class="absolute -top-1 -right-1 bg-brand-red text-white text-[10px] font-black rounded-full w-5 h-5 flex items-center justify-center">
                                {{ carritoCount > 9 ? '9+' : carritoCount }}
                            </span>
                        </Link>

                        <!-- Sesión -->
                        <template v-if="user">
                            <Link :href="route('mi-cuenta.index')" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">
                                Mi Cuenta
                            </Link>
                        </template>
                        <template v-else>
                            <Link :href="route('login')" class="text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white transition-colors">Iniciar Sesión</Link>
                            <Link :href="route('register')" class="btn-primary py-2 px-6 rounded-full text-xs shadow-none border border-brand-red/50 hover:bg-brand-red">Registrarse</Link>
                        </template>
                    </div>

                    <!-- Mobile Toggle -->
                    <div class="md:hidden flex items-center gap-4">
                        <!-- Carrito mobile -->
                        <Link :href="route('carrito.index')" class="relative p-1 text-white/60 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span v-if="carritoCount > 0" class="absolute -top-1 -right-1 bg-brand-red text-white text-[10px] font-black rounded-full w-4 h-4 flex items-center justify-center">
                                {{ carritoCount }}
                            </span>
                        </Link>
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
                    <Link :href="route('nosotros')" class="block text-lg font-bold uppercase text-white/60 hover:text-brand-red">Nosotros</Link>
                    <template v-if="user">
                        <Link :href="route('mi-cuenta.index')" class="block text-lg font-bold uppercase text-white/60 hover:text-brand-red">Mi Cuenta</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="block text-lg font-bold uppercase text-white/60 hover:text-brand-red">Iniciar Sesión</Link>
                        <Link :href="route('register')" class="block btn-primary text-center">Registrarse</Link>
                    </template>
                </div>
            </transition>
        </nav>

        <!-- Main Content -->
        <main>
            <slot />
        </main>

        <!-- Toast -->
        <transition name="toast">
            <div
                v-if="toast"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-4 rounded-2xl shadow-2xl text-sm font-black uppercase tracking-widest"
                :class="{
                    'bg-green-500/90 text-white':  toast.type === 'success',
                    'bg-yellow-500/90 text-black': toast.type === 'warning',
                    'bg-brand-red/90 text-white':  toast.type === 'error',
                }"
            >
                <svg v-if="toast.type === 'success'" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <svg v-else-if="toast.type === 'warning'" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                {{ toast.msg }}
            </div>
        </transition>

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
                        <li><Link :href="route('nosotros')" class="text-white/60 hover:text-white transition-colors text-sm">Nosotros</Link></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-brand-red font-black uppercase tracking-widest text-xs mb-6">Mi Cuenta</h4>
                    <ul class="space-y-4">
                        <li v-if="!user"><Link :href="route('login')" class="text-white/60 hover:text-white transition-colors text-sm">Iniciar Sesión</Link></li>
                        <li v-if="!user"><Link :href="route('register')" class="text-white/60 hover:text-white transition-colors text-sm">Registrarse</Link></li>
                        <li v-if="user"><Link :href="route('mi-cuenta.index')" class="text-white/60 hover:text-white transition-colors text-sm">Mi Cuenta</Link></li>
                        <li><Link :href="route('carrito.index')" class="text-white/60 hover:text-white transition-colors text-sm">Mi Carrito</Link></li>
                    </ul>
                </div>
            </div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-20 pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center text-[10px] uppercase tracking-widest text-white/20 font-black">
                <p>&copy; 2026 PuroComic. Todos los derechos reservados.</p>
                <div class="flex gap-8 mt-4 md:mt-0">
                    <Link :href="route('login')" class="hover:text-white transition-colors">Panel de Control</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.toast-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.toast-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.toast-enter-from  { opacity: 0; transform: translateY(12px); }
.toast-leave-to    { opacity: 0; transform: translateY(12px); }
</style>
