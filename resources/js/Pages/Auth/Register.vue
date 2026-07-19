<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    apellido: '',
    dni: '',
    telefono: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registro de Usuario" />

        <div class="mb-10 text-center">
            <h2 class="text-xl font-black uppercase tracking-tighter text-white">Registro de <span class="text-brand-red italic">Usuario</span></h2>
            <p class="text-[8px] font-black uppercase tracking-[0.3em] text-white/20 mt-1">Crea una nueva cuenta para operar</p>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="name" value="Nombre *" />

                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="given-name"
                    />

                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="apellido" value="Apellido" />

                    <TextInput
                        id="apellido"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.apellido"
                        autocomplete="family-name"
                    />

                    <InputError class="mt-2" :message="form.errors.apellido" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <InputLabel for="dni" value="DNI / Documento *" />

                    <TextInput
                        id="dni"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.dni"
                        required
                    />

                    <InputError class="mt-2" :message="form.errors.dni" />
                </div>
                <div>
                    <InputLabel for="telefono" value="Teléfono Móvil *" />

                    <TextInput
                        id="telefono"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.telefono"
                        required
                    />

                    <InputError class="mt-2" :message="form.errors.telefono" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email de Contacto *" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="email"
                    autocapitalize="none"
                    autocorrect="off"
                    spellcheck="false"
                    @input="form.email = form.email.toLowerCase()"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <InputLabel for="password" value="Contraseña *" />

                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                    />

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        value="Repetir Contraseña *"
                    />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />

                    <InputError
                        class="mt-2"
                        :message="form.errors.password_confirmation"
                    />
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    :href="route('login')"
                    class="rounded-md text-[10px] font-black uppercase tracking-widest text-white/40 hover:text-brand-red focus:outline-none transition-colors"
                >
                    ¿Ya tienes cuenta? Inicia sesión
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    REGISTRARSE
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
