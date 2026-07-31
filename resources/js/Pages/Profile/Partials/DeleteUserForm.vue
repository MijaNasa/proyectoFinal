<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { nextTick, ref, computed } from 'vue';

const page = usePage();
const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

// Solo bloquear si es empleado sin rol de admin.
// Los admins pueden eliminar su propia cuenta aunque tengan registro de empleado.
const esEmpleado = computed(() => !!page.props.auth?.empleado && !page.props.auth?.esAdmin);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header class="border-b border-white/5 pb-3">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">
                Eliminar mi cuenta
            </h2>

            <p class="mt-1 text-xs text-zinc-400 font-medium">
                Una vez eliminada la cuenta, todos sus recursos y datos asociados se borrarán de forma permanente.
            </p>
        </header>

        <!-- Aviso para empleados: no pueden autoeliminarse -->
        <div v-if="esEmpleado" class="flex items-start gap-3 bg-amber-500/10 border border-amber-500/20 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <p class="text-xs text-amber-400 font-medium">
                Tu cuenta está vinculada a un perfil de empleado activo. Para eliminarla, comunicate con un administrador del sistema.
            </p>
        </div>

        <button
            v-else
            @click="confirmUserDeletion"
            class="px-5 py-2.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 cursor-pointer"
        >
            Eliminar mi cuenta
        </button>

        <Teleport to="body">
            <div v-if="confirmingUserDeletion" class="page-profile">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="closeModal" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-md bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-hidden shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                ¿Estás seguro de eliminar tu cuenta?
                            </h3>
                            <button @click="closeModal" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="deleteUser" class="p-6 space-y-4">
                            <p class="text-xs text-zinc-400 font-medium leading-relaxed">
                                Una vez eliminada tu cuenta, todos sus datos serán borrados permanentemente. Por favor, ingresá tu contraseña para confirmar.
                            </p>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-zinc-400 mb-1">Contraseña *</label>

                                <input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="form.password"
                                    type="password"
                                    class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                    placeholder="Tu contraseña actual..."
                                    @keyup.enter="deleteUser"
                                />

                                <InputError :message="form.errors.password" class="mt-1 text-xs font-semibold text-rose-400" />
                            </div>

                            <div class="flex justify-end gap-3 border-t border-white/5 pt-4 mt-6">
                                <button
                                    type="button"
                                    @click="closeModal"
                                    class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50"
                                >
                                    Confirmar eliminación
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </section>
</template>
