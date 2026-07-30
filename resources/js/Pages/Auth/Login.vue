<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
    <PublicLayout>
        <Head title="Iniciar Sesión | PuroComic" />

        <!-- Page Title & Breadcrumbs Banner -->
        <div class="bg-white/[0.02] border-b border-white/10 py-8 mb-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-white tracking-tight uppercase">Iniciar Sesión</h1>
                    <div class="h-1 w-16 bg-brand-red mt-2 rounded"></div>
                </div>
                <nav class="text-xs font-bold uppercase tracking-wider text-white/50 space-x-2">
                    <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Inicio</Link>
                    <span>-</span>
                    <span class="text-white">Iniciar Sesión</span>
                </nav>
            </div>
        </div>

        <!-- Main Form Container -->
        <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 pb-20">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="text-center pb-4 border-b border-slate-800">
                    <p class="text-xs font-medium text-white/70">
                        Ingresá con tu correo y contraseña para acceder a tus pedidos
                    </p>
                </div>

                <div v-if="status" class="mb-4 rounded-lg bg-green-500/10 border border-green-500/30 p-3 text-xs font-medium text-green-400">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <InputLabel for="email" value="EMAIL" class="text-xs font-bold text-white/70 uppercase tracking-wider" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1.5 block w-full bg-slate-800/80 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all"
                            v-model="form.email"
                            placeholder="ej.: usuario@purocomic.com"
                            required
                            autofocus
                            autocomplete="username"
                        />

                        <InputError class="mt-2 text-xs" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="CONTRASEÑA" class="text-xs font-bold text-white/70 uppercase tracking-wider" />

                        <div class="relative mt-1.5">
                            <TextInput
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                class="block w-full bg-slate-800/80 border border-slate-700 rounded-lg px-4 py-2.5 pr-10 text-sm text-white placeholder-slate-500 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all"
                                v-model="form.password"
                                placeholder="••••••••"
                                required
                                autocomplete="current-password"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-white transition-colors focus:outline-none"
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

                        <InputError class="mt-2 text-xs" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center cursor-pointer select-none">
                            <Checkbox name="remember" v-model:checked="form.remember" />
                            <span class="ms-2 text-xs font-medium text-white/70 hover:text-white transition-colors">Recordarme</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs font-medium text-white/60 hover:text-brand-red focus:outline-none transition-colors"
                        >
                            ¿Olvidaste tu contraseña?
                        </Link>
                    </div>

                    <div class="pt-2">
                        <PrimaryButton
                            class="w-full justify-center py-3 text-xs font-extrabold uppercase tracking-widest bg-brand-red hover:bg-brand-red/90 text-white rounded-lg transition-all shadow-lg"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            <span>INICIAR SESIÓN</span>
                        </PrimaryButton>
                    </div>

                    <div class="text-center pt-2">
                        <span class="text-xs text-white/60 font-medium">¿No tenés una cuenta? </span>
                        <Link
                            :href="route('register')"
                            class="text-xs font-bold text-brand-red hover:underline transition-colors"
                        >
                            Crear cuenta
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
