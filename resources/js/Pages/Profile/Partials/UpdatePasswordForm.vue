<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="space-y-4">
        <header class="border-b border-white/5 pb-3">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">
                Actualizar Contraseña
            </h2>

            <p class="mt-1 text-xs text-zinc-400 font-medium">
                Asegurate de que tu cuenta esté utilizando una contraseña larga y aleatoria para mantener la seguridad.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-4 pt-2">
            <div>
                <label for="current_password" class="block text-xs font-semibold text-zinc-400 mb-1">Contraseña Actual *</label>

                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1 text-xs font-semibold text-rose-400"
                />
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-zinc-400 mb-1">Nueva Contraseña *</label>

                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-1 text-xs font-semibold text-rose-400" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-zinc-400 mb-1">Confirmar Nueva Contraseña *</label>

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1 text-xs font-semibold text-rose-400"
                />
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50"
                >
                    Cambiar Contraseña
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs font-semibold text-emerald-400"
                    >
                        ✓ Contraseña actualizada con éxito.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
