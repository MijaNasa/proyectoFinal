<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    cargos: Array,
    permisos: Array,
});

const page = usePage();
const esAdmin = computed(() => page.props.auth.esAdmin);

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-cargos',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

// Agrupar permisos por módulo para mostrarlos como checkboxes
const permisosPorModulo = computed(() => {
    const grupos = {};
    props.permisos.forEach(p => {
        if (!grupos[p.modulo]) grupos[p.modulo] = [];
        grupos[p.modulo].push(p);
    });
    return grupos;
});

const showModal = ref(false);
const isEditing = ref(false);

const form = useForm({
    id: null,
    nombre: '',
    descripcion: '',
    activo: true,
    permiso_ids: [],
});

const openModal = (cargo = null) => {
    if (cargo) {
        isEditing.value = true;
        form.id = cargo.id;
        form.nombre = cargo.nombre;
        form.descripcion = cargo.descripcion || '';
        form.activo = cargo.activo;
        form.permiso_ids = cargo.permisos.map(p => p.id);
    } else {
        isEditing.value = false;
        form.reset();
        form.permiso_ids = [];
    }
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('cargos.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                darkSwal.fire({ title: '¡Actualizado!', text: 'Cargo actualizado con éxito.', icon: 'success', timer: 1500, showConfirmButton: false });
            },
        });
    } else {
        form.post(route('cargos.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                darkSwal.fire({ title: '¡Creado!', text: 'Cargo creado con éxito.', icon: 'success', timer: 1500, showConfirmButton: false });
            },
        });
    }
};

const deleteCargo = (cargo) => {
    darkSwal.fire({
        title: `¿Desactivar cargo ${cargo.nombre}?`,
        text: 'Los empleados con este cargo perderán esos accesos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('cargos.destroy', cargo.id), {
                onSuccess: () => {
                    darkSwal.fire({ title: 'Desactivado', icon: 'success', timer: 1500, showConfirmButton: false });
                }
            });
        }
    });
};

// Colores por cargo
const colorCargo = (nombre) => {
    const map = { 
        ADMIN: 'bg-rose-400', 
        GERENTE: 'bg-sky-400', 
        VENDEDOR: 'bg-emerald-400' 
    };
    return map[nombre] || 'bg-zinc-400';
};
</script>

<template>
    <Head title="Cargos y Accesos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-cargos">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CARGOS Y ACCESOS</h2>
                </div>
                <button 
                    v-if="esAdmin" 
                    @click="openModal()" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Nuevo Cargo</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-cargos">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Tarjetas de cargos -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="cargo in cargos" :key="cargo.id" class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-4 hover:border-white/10 transition-all">
                        <!-- Header del cargo -->
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                    <span class="w-2 h-2 rounded-full shrink-0" :class="colorCargo(cargo.nombre)"></span>
                                    <span>{{ cargo.nombre }}</span>
                                </span>
                                <p class="text-xs font-medium text-zinc-400 mt-2">{{ cargo.descripcion || 'Sin descripción' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                    <span class="w-2 h-2 rounded-full shrink-0 bg-emerald-400"></span>
                                    <span>{{ cargo.empleados_activos_count }} empleado{{ cargo.empleados_activos_count !== 1 ? 's' : '' }}</span>
                                </span>
                                <button 
                                    v-if="esAdmin" 
                                    @click="openModal(cargo)" 
                                    class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all"
                                    title="Editar Cargo"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                <button 
                                    v-if="esAdmin && cargo.nombre !== 'ADMIN'" 
                                    @click="deleteCargo(cargo)" 
                                    class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                    title="Desactivar Cargo"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Permisos del cargo -->
                        <div class="border-t border-white/5 pt-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Permisos habilitados</p>
                            <div v-if="cargo.permisos.length" class="flex flex-wrap gap-1.5">
                                <span v-for="p in cargo.permisos" :key="p.id" class="px-2.5 py-1 rounded-xl text-xs font-semibold bg-white/5 text-zinc-300 border border-white/5">
                                    {{ p.nombre }}
                                </span>
                            </div>
                            <p v-else class="text-xs text-zinc-500 italic">Sin permisos asignados</p>
                        </div>
                    </div>
                </div>

                <div v-if="!cargos.length" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic">
                    No hay cargos definidos en el sistema.
                </div>
            </div>
        </div>

        <!-- Modal Cargo -->
        <Teleport to="body">
            <div v-if="showModal && esAdmin" class="page-cargos">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-3xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-hidden shadow-2xl pointer-events-auto flex flex-col max-h-[85vh]">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center shrink-0">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'EDITAR' : 'NUEVO' }} CARGO
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-6 overflow-y-auto flex-1">
                            <!-- Datos básicos -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre del Cargo *</label>
                                    <input v-model="form.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.nombre}" placeholder="Ej: VENDEDOR">
                                    <p v-if="form.errors.nombre" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.nombre }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Descripción</label>
                                    <input v-model="form.descripcion" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="Descripción breve...">
                                </div>
                            </div>

                            <!-- Permisos agrupados por módulo -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-wider text-white border-b border-white/5 pb-2">Permisos del Cargo</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-for="(permisosGrupo, modulo) in permisosPorModulo" :key="modulo" class="bg-[#131316] rounded-2xl p-4 border border-white/5 space-y-2">
                                        <p class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-2">{{ modulo }}</p>
                                        <label v-for="p in permisosGrupo" :key="p.id" class="flex items-center gap-3 cursor-pointer group">
                                            <input type="checkbox" :value="p.id" v-model="form.permiso_ids" class="rounded border-white/10 bg-[#0d0d0f] text-white focus:ring-0 h-4 w-4">
                                            <span class="text-xs font-medium text-zinc-300 group-hover:text-white transition-colors">{{ p.nombre }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6 shrink-0">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ form.processing ? 'GUARDANDO...' : (isEditing ? 'ACTUALIZAR' : 'CREAR CARGO') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-cargos,
.page-cargos * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
