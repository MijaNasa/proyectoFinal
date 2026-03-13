<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Bienvenido | Puro Comic ERP" />
    
    <div class="relative min-h-screen flex items-center justify-center bg-brand-black overflow-hidden font-outfit">
        <!-- Background Elements -->
        <div class="absolute inset-0 z-0">
            <!-- Comic Dots Pattern -->
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 20px 20px;"></div>
            
            <!-- Red Glows -->
            <div class="absolute top-[-20%] left-[-10%] w-[60%] h-[60%] bg-brand-red/20 rounded-full blur-[150px] animate-pulse"></div>
            <div class="absolute bottom-[-20%] right-[-10%] w-[60%] h-[60%] bg-brand-red/10 rounded-full blur-[150px]"></div>
        </div>

        <!-- Main Content -->
        <div class="relative z-10 w-full max-w-5xl px-8 flex flex-col items-center text-center">
            <div class="mb-12 transform hover:scale-110 transition-transform duration-700 cursor-default">
                <ApplicationLogo class="scale-[2.5]" />
            </div>

            <h1 class="text-6xl md:text-8xl font-black uppercase tracking-tighter text-white mb-6 leading-none italic">
                SISTEMA <span class="text-brand-red not-italic">ERP</span>
            </h1>
            
            <p class="text-xs md:text-sm font-black uppercase tracking-[0.6em] text-white/30 mb-20 max-w-md mx-auto leading-relaxed border-y border-white/5 py-4">
                Administración Central de Operaciones & Distribución
            </p>

            <div v-if="canLogin" class="flex flex-col sm:flex-row gap-6 w-full max-w-md">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="btn-primary w-full py-5 text-lg font-black italic tracking-widest shadow-[0_0_40px_rgba(230,25,25,0.4)] animate-pulse hover:animate-none"
                >
                    ACCEDER AL PANEL
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="btn-primary w-full py-5 text-xl font-black italic tracking-[0.2em] shadow-[0_10px_40px_rgba(230,25,25,0.3)] hover:shadow-[0_15px_60px_rgba(230,25,25,0.5)] transition-all"
                    >
                        INICIAR SESIÓN
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="w-full py-5 border-2 border-white/10 text-white/40 font-black uppercase text-xs tracking-[0.4em] rounded-xl hover:border-brand-red hover:text-white transition-all flex items-center justify-center gap-2"
                    >
                        DAR DE ALTA CUENTA
                    </Link>
                </template>
            </div>

            <!-- Footer Decor -->
            <div class="mt-32 flex items-center gap-6 opacity-20">
                <div class="h-px w-20 bg-white"></div>
                <span class="text-[8px] font-black uppercase tracking-[1em]">Rosario • Funes Online</span>
                <div class="h-px w-20 bg-white"></div>
            </div>
        </div>

        <!-- Floating Badge -->
        <div class="absolute bottom-10 right-10 bg-white/5 backdrop-blur-md border border-white/10 p-4 rounded-2xl hidden lg:block transform rotate-6 hover:rotate-0 transition-transform cursor-help group">
            <div class="text-[8px] font-black uppercase text-brand-red mb-1">Status del Sistema</div>
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-ping"></div>
                <div class="text-[10px] font-black text-white uppercase tracking-widest">Servidor Activo</div>
            </div>
            <div class="absolute -top-2 -left-2 bg-brand-red text-white text-[7px] font-black px-2 py-0.5 rounded italic shadow-xl group-hover:scale-125 transition-transform">
                V1.2
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-outfit {
    font-family: 'Outfit', sans-serif;
}
</style>
