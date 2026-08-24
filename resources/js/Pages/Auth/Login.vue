<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar Sesión | PuroComic" />

    <PublicLayout>
        <div class="page-auth">
            <!-- Hero Header -->
            <div class="relative overflow-hidden py-12 sm:py-16 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                        <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        Acceso a tu Cuenta
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-bold tracking-tight uppercase leading-none text-white">
                        Iniciar <span class="text-zinc-400 italic">Sesión</span>
                    </h1>
                    <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-xl mx-auto">
                        Ingresá tus credenciales para administrar tus compras y acceder a tu perfil.
                    </p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">

                    <div v-if="status" class="rounded-xl bg-emerald-500/10 border border-emerald-500/20 p-3.5 text-xs font-semibold text-emerald-400 flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ status }}</span>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <!-- Email -->
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

                        <!-- Contraseña -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                Contraseña
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.password"
                                    placeholder="••••••••"
                                    required
                                    autocomplete="current-password"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400 hover:text-white transition-colors focus:outline-none cursor-pointer"
                                    tabindex="-1"
                                >
                                    <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.047 10.047 0 013.682-.913c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.password" />
                        </div>

                        <!-- Recordarme y Olvidé contraseña -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center cursor-pointer select-none">
                                <Checkbox name="remember" v-model:checked="form.remember" />
                                <span class="ms-2 text-xs font-semibold text-zinc-400 hover:text-white transition-colors">Recordarme</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-semibold text-zinc-400 hover:text-white transition-colors"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        </div>

                        <!-- Botón Submit -->
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
                                <span>{{ form.processing ? 'Ingresando...' : 'Iniciar Sesión' }}</span>
                            </button>
                        </div>

                        <!-- Footer Link -->
                        <div class="text-center pt-2 border-t border-white/5">
                            <span class="text-xs text-zinc-400 font-medium">¿No tenés una cuenta? </span>
                            <Link
                                :href="route('register')"
                                class="text-xs font-bold text-white hover:underline transition-colors ml-1"
                            >
                                Crear cuenta
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
