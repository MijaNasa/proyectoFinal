<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <PublicLayout>
        <Head title="Recuperar Contraseña | PuroComic" />

        <!-- Page Title & Breadcrumbs Banner -->
        <div class="bg-white/[0.02] border-b border-white/10 py-8 mb-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-white tracking-tight uppercase">Recuperar Contraseña</h1>
                    <div class="h-1 w-16 bg-brand-red mt-2 rounded"></div>
                </div>
                <nav class="text-xs font-bold uppercase tracking-wider text-white/50 space-x-2">
                    <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Inicio</Link>
                    <span>-</span>
                    <span class="text-white">Recuperar Contraseña</span>
                </nav>
            </div>
        </div>

        <!-- Main Form Container -->
        <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8 pb-20">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="text-center pb-4 border-b border-slate-800">
                    <p class="text-xs font-medium text-white/70 leading-relaxed">
                        Ingresá tu correo electrónico registrado y te enviaremos las instrucciones para restablecer tu contraseña.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="mb-4 rounded-lg bg-green-500/10 border border-green-500/30 p-3 text-xs font-medium text-green-400 flex items-start gap-2"
                >
                    <svg class="w-4 h-4 text-green-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ status }}</span>
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

                    <div class="pt-2">
                        <PrimaryButton
                            class="w-full justify-center py-3 text-xs font-extrabold uppercase tracking-widest bg-brand-red hover:bg-brand-red/90 text-white rounded-lg transition-all shadow-lg"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            ENVIAR INSTRUCCIONES
                        </PrimaryButton>
                    </div>

                    <div class="text-center pt-2">
                        <Link
                            :href="route('login')"
                            class="text-xs font-bold text-slate-400 hover:text-white transition-colors flex items-center justify-center gap-1.5"
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
    </PublicLayout>
</template>
