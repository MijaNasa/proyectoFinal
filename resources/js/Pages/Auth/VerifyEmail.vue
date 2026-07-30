<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verificación de Correo" />

        <div class="mb-8 text-center">
            <h2 class="text-2xl font-bold text-white tracking-tight">Verifica tu Correo</h2>
            <p class="text-xs text-white/60 mt-1.5 leading-relaxed">
                ¡Gracias por registrarte! Antes de comenzar, ¿podrías verificar tu dirección de correo electrónico haciendo clic en el enlace que te acabamos de enviar? Si no recibiste el correo, con gusto te enviaremos otro.
            </p>
        </div>

        <div
            class="mb-6 rounded-lg bg-green-500/10 border border-green-500/30 p-3.5 text-xs font-medium text-green-400 flex items-start gap-2"
            v-if="verificationLinkSent"
        >
            <svg class="w-4 h-4 text-green-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Se ha enviado un nuevo enlace de verificación a la dirección de correo proporcionada durante el registro.</span>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-3 text-sm font-semibold tracking-normal"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Reenviar Correo de Verificación</span>
                </PrimaryButton>
            </div>

            <div class="text-center border-t border-white/10 pt-5 mt-6">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-xs font-semibold text-white/60 hover:text-brand-red transition-colors focus:outline-none"
                >
                    Cerrar sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
