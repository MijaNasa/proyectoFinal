<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Perfil de Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-profile">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">PERFIL DE USUARIO</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-profile">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-profile,
.page-profile * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
