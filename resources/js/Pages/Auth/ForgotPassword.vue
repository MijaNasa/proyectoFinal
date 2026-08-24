<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
    },
});

const statusTraducido = computed(() => {
    if (!props.status) return '';
    if (props.status === 'We have emailed your password reset link.') {
        return 'Te hemos enviado por correo electrónico el enlace para restablecer tu contraseña.';
    }
    return props.status;
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Recuperar Contraseña | PuroComic" />

    <PublicLayout>
        <div class="page-auth">
            <!-- Hero Header -->
            <div class="relative overflow-hidden py-12 sm:py-16 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                        <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        Recuperación de Acceso
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-bold tracking-tight uppercase leading-none text-white">
                        Recuperar <span class="text-zinc-400 italic">Contraseña</span>
                    </h1>
                    <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-xl mx-auto">
                        Ingresá tu correo electrónico para enviarte un enlace de restablecimiento.
                    </p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">

                    <div
                        v-if="status"
                        class="rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-3.5 text-xs font-semibold text-emerald-400 flex items-start gap-2"
                    >
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ statusTraducido }}</span>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                Correo Electrónico
                            </label>

                            <input
                                id="email"
                                type="email"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                v-model="form.email"
                                placeholder="Ej: usuario@purocomic.com"
                                required
                                autofocus
                                autocomplete="username"
                            />

                            <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.email" />
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="w-full py-4 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                :disabled="form.processing"
                            >
                                <svg v-if="form.processing" class="animate-spin w-4 h-4 text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span>{{ form.processing ? 'Enviando...' : 'Enviar Instrucciones' }}</span>
                            </button>
                        </div>

                        <div class="text-center pt-2 border-t border-white/5">
                            <Link
                                :href="route('login')"
                                class="text-xs font-semibold text-zinc-400 hover:text-white transition-colors inline-flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                <span>Volver a Iniciar Sesión</span>
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-auth,
.page-auth * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
