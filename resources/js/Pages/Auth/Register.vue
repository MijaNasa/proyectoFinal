<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const form = useForm({
    name: '',
    email: '',
    telefono: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const touched = ref({});

const markTouched = (field) => {
    touched.value[field] = true;
};

// Client-side validations
const isEmailValid = computed(() => {
    if (!form.email) return true;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(form.email);
});

const passwordsMatch = computed(() => {
    if (!form.password_confirmation) return true;
    return form.password === form.password_confirmation;
});

const clientErrors = computed(() => {
    const errors = {};
    if (touched.value.name && !form.name.trim()) {
        errors.name = 'El nombre es obligatorio.';
    }
    if (touched.value.email) {
        if (!form.email.trim()) {
            errors.email = 'El correo electrónico es obligatorio.';
        } else if (!isEmailValid.value) {
            errors.email = 'Ingresa un correo electrónico válido.';
        }
    }
    if (touched.value.password && !form.password) {
        errors.password = 'La contraseña es obligatoria.';
    } else if (touched.value.password && form.password.length < 8) {
        errors.password = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (touched.value.password_confirmation && !passwordsMatch.value) {
        errors.password_confirmation = 'Las contraseñas no coinciden.';
    }
    return errors;
});

const submit = () => {
    ['name', 'email', 'password', 'password_confirmation'].forEach(f => touched.value[f] = true);
    
    if (Object.keys(clientErrors.value).length > 0) {
        return;
    }

    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <PublicLayout>
        <Head title="Crear cuenta | PuroComic" />

        <!-- Page Title & Breadcrumbs Banner -->
        <div class="bg-white/[0.02] border-b border-white/10 py-8 mb-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-white tracking-tight uppercase">Crear cuenta</h1>
                    <div class="h-1 w-16 bg-brand-red mt-2 rounded"></div>
                </div>
                <nav class="text-xs font-bold uppercase tracking-wider text-white/50 space-x-2">
                    <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Inicio</Link>
                    <span>-</span>
                    <span class="text-white">Crear cuenta</span>
                </nav>
            </div>
        </div>

        <!-- Main Form Container -->
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="text-center pb-4 border-b border-slate-800">
                    <p class="text-xs font-medium text-white/70">
                        Comprá más rápido y llevá el control de tus pedidos, <span class="font-bold text-white">¡en un solo lugar!</span>
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Nombre -->
                    <div>
                        <InputLabel for="name" value="NOMBRE" class="text-xs font-bold text-white/70 uppercase tracking-wider" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1.5 block w-full bg-slate-800/80 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all"
                            v-model="form.name"
                            placeholder="ej.: María Perez"
                            required
                            autofocus
                            autocomplete="name"
                            @blur="markTouched('name')"
                        />
                        <InputError class="mt-2 text-xs" :message="form.errors.name || clientErrors.name" />
                    </div>

                    <!-- Email -->
                    <div>
                        <InputLabel for="email" value="EMAIL" class="text-xs font-bold text-white/70 uppercase tracking-wider" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1.5 block w-full bg-slate-800/80 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all"
                            v-model="form.email"
                            placeholder="ej.: tunombre@email.com"
                            required
                            autocomplete="email"
                            autocapitalize="none"
                            @input="form.email = form.email.toLowerCase()"
                            @blur="markTouched('email')"
                        />
                        <InputError class="mt-2 text-xs" :message="form.errors.email || clientErrors.email" />
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <InputLabel for="telefono" value="TELÉFONO" class="text-xs font-bold text-white/70 uppercase tracking-wider" />
                        <TextInput
                            id="telefono"
                            type="text"
                            class="mt-1.5 block w-full bg-slate-800/80 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all"
                            v-model="form.telefono"
                            placeholder="ej.: 1123445567"
                            autocomplete="tel"
                            @blur="markTouched('telefono')"
                        />
                        <InputError class="mt-2 text-xs" :message="form.errors.telefono" />
                    </div>

                    <!-- Contraseña -->
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
                                autocomplete="new-password"
                                @blur="markTouched('password')"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white transition-colors"
                                tabindex="-1"
                            >
                                <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.959 8.959 0 013.682-.793c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21f-3-3m-3-3l-3-3m-2-2l-3-3" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2 text-xs" :message="form.errors.password || clientErrors.password" />
                    </div>

                    <!-- Confirmar Contraseña -->
                    <div>
                        <InputLabel for="password_confirmation" value="CONFIRMAR CONTRASEÑA" class="text-xs font-bold text-white/70 uppercase tracking-wider" />
                        <div class="relative mt-1.5">
                            <TextInput
                                id="password_confirmation"
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                                class="block w-full bg-slate-800/80 border border-slate-700 rounded-lg px-4 py-2.5 pr-10 text-sm text-white placeholder-slate-500 focus:border-brand-red focus:ring-1 focus:ring-brand-red transition-all"
                                v-model="form.password_confirmation"
                                placeholder="••••••••"
                                required
                                autocomplete="new-password"
                                @blur="markTouched('password_confirmation')"
                            />
                            <button
                                type="button"
                                @click="showPasswordConfirmation = !showPasswordConfirmation"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white transition-colors"
                                tabindex="-1"
                            >
                                <svg v-if="!showPasswordConfirmation" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.959 8.959 0 013.682-.793c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21f-3-3m-3-3l-3-3m-2-2l-3-3" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2 text-xs" :message="form.errors.password_confirmation || clientErrors.password_confirmation" />
                    </div>

                    <!-- Botón Submit -->
                    <div class="pt-4">
                        <PrimaryButton
                            class="w-full justify-center py-3 text-xs font-extrabold uppercase tracking-widest bg-brand-red hover:bg-brand-red/90 text-white rounded-lg transition-all shadow-lg"
                            :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                            :disabled="form.processing"
                        >
                            CREAR CUENTA
                        </PrimaryButton>
                    </div>

                    <!-- Footer Link -->
                    <div class="text-center pt-2">
                        <span class="text-xs text-white/60 font-medium">¿Ya tenés una cuenta? </span>
                        <Link
                            :href="route('login')"
                            class="text-xs font-bold text-brand-red hover:underline transition-colors"
                        >
                            Iniciá sesión
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
