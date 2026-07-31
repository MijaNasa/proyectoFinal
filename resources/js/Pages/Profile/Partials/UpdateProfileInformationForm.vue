<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section class="space-y-4">
        <header class="border-b border-white/5 pb-3">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">
                Información del Perfil
            </h2>

            <p class="mt-1 text-xs text-zinc-400 font-medium">
                Actualizá la información de tu cuenta y dirección de correo electrónico.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="space-y-4 pt-2"
        >
            <div>
                <label for="name" class="block text-xs font-semibold text-zinc-400 mb-1">Nombre *</label>

                <input
                    id="name"
                    type="text"
                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-1 text-xs font-semibold text-rose-400" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-zinc-400 mb-1">Correo Electrónico *</label>

                <input
                    id="email"
                    type="email"
                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-1 text-xs font-semibold text-rose-400" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-xs text-zinc-400 font-medium">
                    Tu dirección de correo electrónico no ha sido verificada.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-xs font-semibold text-white underline hover:text-zinc-200 transition-colors ml-1"
                    >
                        Hacé clic aquí para re-enviar el correo de verificación.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-xs font-semibold text-emerald-400"
                >
                    Se ha enviado un nuevo enlace de verificación a tu correo electrónico.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50"
                >
                    Guardar
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
                        ✓ Cambios guardados con éxito.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
