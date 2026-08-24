<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const form = useForm({
    name: '',
    apellido: '',
    dni: '',
    telefono: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const attemptedSubmit = ref(false);

// Client-side validations
const isEmailValid = computed(() => {
    if (!form.email) return false;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(form.email);
});

const clientErrors = computed(() => {
    const errors = {};
    if (!attemptedSubmit.value) return errors;

    if (!form.name || !form.name.trim()) {
        errors.name = 'El nombre es obligatorio.';
    }
    if (!form.dni || !form.dni.trim()) {
        errors.dni = 'El DNI es obligatorio para asociar tus pedidos.';
    }
    if (!form.email || !form.email.trim()) {
        errors.email = 'El correo electrónico es obligatorio.';
    } else if (!isEmailValid.value) {
        errors.email = 'Ingresá un correo electrónico válido.';
    }
    if (!form.password) {
        errors.password = 'La contraseña es obligatoria.';
    } else if (form.password.length < 8) {
        errors.password = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!form.password_confirmation) {
        errors.password_confirmation = 'La confirmación de la contraseña es obligatoria.';
    } else if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'Las contraseñas ingresadas no coinciden.';
    }
    return errors;
});

const submit = () => {
    attemptedSubmit.value = true;
    
    if (Object.keys(clientErrors.value).length > 0) {
        return;
    }

    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear Cuenta | PuroComic" />

    <PublicLayout>
        <div class="page-auth">
            <!-- Hero Header -->
            <div class="relative overflow-hidden py-12 sm:py-16 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                        <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 0112 0v1H3v-1z" />
                        </svg>
                        Registro de Usuario
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-bold tracking-tight uppercase leading-none text-white">
                        Crear <span class="text-zinc-400 italic">Cuenta</span>
                    </h1>
                    <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-xl mx-auto">
                        Registrate con tu DNI para vincular todas tus compras anteriores y acceder al seguimiento en tiempo real de tus pedidos.
                    </p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                    
                    <!-- DNI Linking Notice Card -->
                    <div class="bg-[#0d0d0f] border border-white/10 rounded-xl p-4 flex items-start gap-3 text-xs text-zinc-300 font-medium">
                        <span class="text-base shrink-0">💡</span>
                        <span>
                            <strong>¿Compraste como invitado?</strong> Ingresá tu <strong>DNI</strong> exacto al registrarte y tus pedidos realizados anteriormente se asociarán automáticamente a tu nueva cuenta.
                        </span>
                    </div>

                    <form novalidate @submit.prevent="submit" class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Nombre -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                    Nombre *
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.name"
                                    placeholder="Ej: Juan"
                                    autofocus
                                    autocomplete="given-name"
                                />
                                <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.name || clientErrors.name" />
                            </div>

                            <!-- Apellido -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                    Apellido
                                </label>
                                <input
                                    id="apellido"
                                    type="text"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.apellido"
                                    placeholder="Ej: Pérez"
                                    autocomplete="family-name"
                                />
                                <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.apellido" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- DNI -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                    DNI / Documento *
                                </label>
                                <input
                                    id="dni"
                                    type="text"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.dni"
                                    placeholder="Ej: 12345678"
                                />
                                <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.dni || clientErrors.dni" />
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                    Teléfono Móvil
                                </label>
                                <input
                                    id="telefono"
                                    type="text"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.telefono"
                                    placeholder="Ej: 3415551234"
                                    autocomplete="tel"
                                />
                                <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.telefono" />
                            </div>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                Correo Electrónico *
                            </label>
                            <input
                                id="email"
                                type="email"
                                class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                v-model="form.email"
                                placeholder="Ej: tu@email.com"
                                autocomplete="email"
                                autocapitalize="none"
                                @input="form.email = form.email.toLowerCase()"
                            />
                            <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.email || clientErrors.email" />
                        </div>

                        <!-- Contraseña -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                Contraseña *
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.password"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-white transition-colors cursor-pointer"
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
                            <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.password || clientErrors.password" />
                        </div>

                        <!-- Confirmar Contraseña -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                Confirmar Contraseña *
                            </label>
                            <div class="relative">
                                <input
                                    id="password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                    v-model="form.password_confirmation"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                />
                                <button
                                    type="button"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-white transition-colors cursor-pointer"
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
                            <InputError class="mt-1.5 text-xs text-rose-400 font-semibold" :message="form.errors.password_confirmation || clientErrors.password_confirmation" />
                        </div>

                        <!-- Botón Submit -->
                        <div class="pt-4">
                            <button
                                type="submit"
                                class="w-full py-4 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                :disabled="form.processing"
                            >
                                <svg v-if="form.processing" class="animate-spin w-4 h-4 text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span>{{ form.processing ? 'Creando Cuenta...' : 'Crear Mi Cuenta' }}</span>
                            </button>
                        </div>

                        <!-- Footer Link -->
                        <div class="text-center pt-2 border-t border-white/5">
                            <span class="text-xs text-zinc-400 font-medium">¿Ya tenés una cuenta? </span>
                            <Link
                                :href="route('login')"
                                class="text-xs font-bold text-white hover:underline transition-colors ml-1"
                            >
                                Iniciá Sesión
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
