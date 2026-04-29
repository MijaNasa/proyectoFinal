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
                Swal.fire({ title: '¡Actualizado!', text: 'Cargo actualizado con éxito', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
            },
        });
    } else {
        form.post(route('cargos.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                Swal.fire({ title: '¡Creado!', text: 'Cargo creado con éxito', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
            },
        });
    }
};

const deleteCargo = (cargo) => {
    Swal.fire({
        title: `¿Desactivar cargo ${cargo.nombre}?`,
        text: 'Los empleados con este cargo perderán esos accesos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, desactivar',
        background: '#1A1A1A', color: '#FFF',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('cargos.destroy', cargo.id));
        }
    });
};

// Colores por cargo
const colorCargo = (nombre) => {
    const map = { ADMIN: 'bg-brand-red/20 text-brand-red border-brand-red/30', GERENTE: 'bg-blue-500/20 text-blue-400 border-blue-500/30', VENDEDOR: 'bg-green-500/20 text-green-400 border-green-500/30', DEPOSITO: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/30' };
    return map[nombre] || 'bg-white/10 text-white/60 border-white/10';
};
</script>

<template>
    <Head title="Cargos y Accesos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Gestión de <span class="text-brand-red italic">Cargos y Accesos</span>
                </h2>
                <button v-if="esAdmin" @click="openModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nuevo Cargo
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Tarjetas de cargos -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="cargo in cargos" :key="cargo.id" class="card border border-white/5 hover:border-white/10 transition-all">
                        <!-- Header del cargo -->
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border" :class="colorCargo(cargo.nombre)">
                                    {{ cargo.nombre }}
                                </span>
                                <p class="text-white/40 text-xs mt-2 italic">{{ cargo.descripcion || 'Sin descripción' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-black text-white/20 uppercase tracking-widest">
                                    {{ cargo.empleados_activos_count }} empleado{{ cargo.empleados_activos_count !== 1 ? 's' : '' }}
                                </span>
                                <button v-if="esAdmin" @click="openModal(cargo)" class="p-1.5 text-white/30 hover:text-brand-red transition-colors bg-white/5 rounded">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                    </svg>
                                </button>
                                <button v-if="esAdmin && cargo.nombre !== 'ADMIN'" @click="deleteCargo(cargo)" class="p-1.5 text-white/30 hover:text-brand-red transition-colors bg-white/5 rounded">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Permisos del cargo -->
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/20 mb-2">Permisos habilitados</p>
                            <div v-if="cargo.permisos.length" class="flex flex-wrap gap-1.5">
                                <span v-for="p in cargo.permisos" :key="p.id" class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-white/5 text-white/50 border border-white/5">
                                    {{ p.nombre }}
                                </span>
                            </div>
                            <p v-else class="text-[10px] text-white/20 italic">Sin permisos asignados</p>
                        </div>
                    </div>
                </div>

                <div v-if="!cargos.length" class="card text-center py-20 text-white/20 text-sm font-black uppercase tracking-widest italic">
                    No hay cargos definidos en el sistema
                </div>
            </div>
        </div>

        <!-- Modal Cargo -->
        <div v-if="showModal && esAdmin" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/95 backdrop-blur-md" @click="showModal = false"></div>
            <div class="relative w-full max-w-3xl card p-0 border border-brand-red/50 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="bg-brand-red p-6 flex justify-between items-center">
                    <h3 class="text-2xl font-black uppercase tracking-tighter">
                        {{ isEditing ? 'Editar' : 'Nuevo' }} <span class="text-white">Cargo</span>
                    </h3>
                    <button @click="showModal = false" class="text-white hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submit" class="p-8 space-y-6 overflow-y-auto flex-1">
                    <!-- Datos básicos -->
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Nombre del Cargo</label>
                            <input v-model="form.nombre" type="text" class="input-field w-full font-black uppercase" :class="{'border-brand-red': form.errors.nombre}" placeholder="Ej: VENDEDOR">
                            <p v-if="form.errors.nombre" class="text-brand-red text-[10px] mt-1">{{ form.errors.nombre }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-white/40 mb-2 tracking-widest">Descripción</label>
                            <input v-model="form.descripcion" type="text" class="input-field w-full" placeholder="Descripción breve...">
                        </div>
                    </div>

                    <!-- Permisos agrupados por módulo -->
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.3em] text-brand-red mb-4 border-b border-brand-red/20 pb-1">Permisos del Cargo</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="(permisosGrupo, modulo) in permisosPorModulo" :key="modulo" class="bg-white/[0.03] rounded-lg p-4 border border-white/5">
                                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 mb-3">{{ modulo }}</p>
                                <label v-for="p in permisosGrupo" :key="p.id" class="flex items-center gap-2 cursor-pointer group mb-2">
                                    <input type="checkbox" :value="p.id" v-model="form.permiso_ids" class="w-4 h-4 accent-brand-red">
                                    <span class="text-xs font-bold text-white/60 group-hover:text-white transition-colors uppercase">{{ p.nombre }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 border-t border-white/10 pt-6">
                        <button type="button" @click="showModal = false" class="px-8 py-3 font-black text-white/30 hover:text-white transition-colors uppercase text-[10px] tracking-[0.3em]">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="btn-primary px-16">
                            <span class="font-black italic tracking-widest">{{ form.processing ? 'Guardando...' : (isEditing ? 'ACTUALIZAR' : 'CREAR CARGO') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
