<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    empleados: Object,
    sucursales: Array,
    cargos: Array,
    filters: Object
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-empleados',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const triggerDatePicker = (e) => {
    if (e.target && typeof e.target.showPicker === 'function') {
        e.target.showPicker();
    }
};

const search = ref(props.filters.search || '');

const form = useForm({
    id: null,
    name: '',
    apellido: '',
    email: '',
    dni: '',
    telefono: '',
    legajo: '',
    sucursal_id: '',
    cargo_id: '',
    fecha_ingreso: new Date().toISOString().substr(0, 10),
    fecha_egreso: null
});

const isEditing = ref(false);
const showModal = ref(false);

const openModal = (empleado = null) => {
    if (empleado) {
        isEditing.value = true;
        form.id = empleado.id;
        form.name = empleado.user.name;
        form.apellido = empleado.user.apellido || '';
        form.email = empleado.user.email;
        form.dni = empleado.user.dni || '';
        form.telefono = empleado.user.telefono || '';
        form.legajo = empleado.legajo;
        form.sucursal_id = empleado.sucursal_id;
        form.cargo_id = empleado.cargos?.length ? empleado.cargos[0].id : '';
        form.fecha_ingreso = empleado.fecha_ingreso;
        form.fecha_egreso = empleado.fecha_egreso;
    } else {
        isEditing.value = false;
        form.reset();
        form.fecha_ingreso = new Date().toISOString().substr(0, 10);
    }
    showModal.value = true;
};

const submit = () => {
    const onError = () => {
        darkSwal.fire({
            title: 'Revisá los datos',
            text: 'Hay campos con errores, corregilos para continuar.',
            icon: 'error',
        });
    };

    if (isEditing.value) {
        form.put(route('empleados.update', form.id), {
            onSuccess: () => {
                showModal.value = false;
                darkSwal.fire({
                    title: '¡Actualizado!',
                    text: 'Ficha del empleado actualizada correctamente.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            onError
        });
    } else {
        form.post(route('empleados.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
                darkSwal.fire({
                    title: '¡Registrado!',
                    text: 'Nuevo empleado incorporado con éxito.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            onError
        });
    }
};

const deleteEmpleado = (id) => {
    darkSwal.fire({
        title: '¿Confirmar baja?',
        text: "Se desactivarán los accesos del usuario y se marcará la baja en el sistema.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar baja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('empleados.destroy', id), {
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Baja confirmada',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const handleSearch = () => {
    router.get(route('empleados.index'), { search: search.value }, {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
};

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 300);
});

// --- Gestión de Accesos (Cargos por empleado) ---
const showAccesosModal = ref(false);
const empleadoSeleccionado = ref(null);

const asignarForm = useForm({ cargo_id: '' });
const desasignarForm = useForm({});
const resetPasswordForm = useForm({});

const openAccesosModal = (empleado) => {
    empleadoSeleccionado.value = empleado;
    asignarForm.reset();
    showAccesosModal.value = true;
};

const sincronizarSeleccionado = () => {
    const actualizado = props.empleados.data.find(e => e.id === empleadoSeleccionado.value?.id);
    if (actualizado) empleadoSeleccionado.value = actualizado;
};

const asignarCargo = () => {
    if (!asignarForm.cargo_id) return;
    asignarForm.post(route('empleados.asignar-cargo', empleadoSeleccionado.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            asignarForm.reset();
            sincronizarSeleccionado();
            darkSwal.fire({ title: 'Cargo asignado', icon: 'success', timer: 1500, showConfirmButton: false });
        },
        onError: () => {
            darkSwal.fire({ title: 'No se pudo asignar', text: asignarForm.errors.cargo_id || 'Revisá los datos e intentá de nuevo.', icon: 'error' });
        },
    });
};

const desasignarCargo = (cargo) => {
    darkSwal.fire({
        title: `¿Quitar cargo ${cargo.nombre}?`,
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            desasignarForm.delete(route('empleados.desasignar-cargo', { empleado: empleadoSeleccionado.value.id, cargo: cargo.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    sincronizarSeleccionado();
                    darkSwal.fire({ title: 'Cargo removido', icon: 'success', timer: 1500, showConfirmButton: false });
                },
            });
        }
    });
};

const resetearPassword = () => {
    darkSwal.fire({
        title: '¿Restablecer contraseña?',
        text: `Se generará una nueva contraseña para ${empleadoSeleccionado.value.user.name}.`,
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonText: 'Sí, restablecer',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            resetPasswordForm.post(route('empleados.resetear-password', empleadoSeleccionado.value.id), {
                preserveScroll: true,
                onSuccess: () => {
                    const nuevaPassword = usePage().props.flash?.nuevaPassword;
                    darkSwal.fire({
                        title: 'Contraseña restablecida',
                        text: `Nueva contraseña: ${nuevaPassword} — comunicásela al empleado, no queda registrada en ningún otro lado.`,
                        icon: 'success',
                    });
                },
            });
        }
    });
};

const sucursalPrincipal = computed(() => {
    return props.sucursales?.find(s => s.es_principal) || props.sucursales?.[0];
});

const isRepartidorCargo = computed(() => {
    if (!form.cargo_id) return false;
    const c = props.cargos?.find(item => item.id === Number(form.cargo_id));
    return c?.nombre === 'REPARTIDOR';
});

watch(() => form.cargo_id, (newCargoId) => {
    if (!newCargoId) return;
    const c = props.cargos?.find(item => item.id === Number(newCargoId));
    if (c?.nombre === 'REPARTIDOR' && sucursalPrincipal.value) {
        form.sucursal_id = sucursalPrincipal.value.id;
    }
});

const isAsignandoRepartidor = computed(() => {
    if (!asignarForm.cargo_id) return false;
    const c = props.cargos?.find(item => item.id === Number(asignarForm.cargo_id));
    return c?.nombre === 'REPARTIDOR';
});

const colorCargo = (nombre) => {
    const map = { 
        ADMIN: 'bg-rose-400', 
        GERENTE: 'bg-sky-400', 
        VENDEDOR: 'bg-emerald-400',
        REPARTIDOR: 'bg-amber-400'
    };
    return map[nombre] || 'bg-zinc-400';
};
</script>

<template>
    <Head title="Personal (Empleados)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-empleados">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">RECURSOS HUMANOS</h2>
                </div>
                <button 
                    v-if="$page.props.auth.esAdmin" 
                    @click="openModal()" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Alta de Empleado</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-empleados">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Search Bar Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex items-center shadow-xl">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            @keyup.enter="handleSearch"
                            type="text" 
                            placeholder="Buscar por nombre o DNI..." 
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium transition-all"
                        >
                    </div>
                </div>

                <!-- Tabla Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4">Empleado / DNI</th>
                                    <th class="p-4">Sucursal Asignada</th>
                                    <th class="p-4">Cargos / Accesos</th>
                                    <th class="p-4">Ingreso / Estado</th>
                                    <th class="p-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-for="emp in empleados.data" :key="emp.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="p-4">
                                        <div class="flex flex-col justify-center">
                                            <div class="font-bold text-white tracking-tight capitalize group-hover:text-zinc-200 transition-colors">{{ emp.user.name }} {{ emp.user.apellido }}</div>
                                            <div class="text-xs text-zinc-400 font-mono font-medium mt-0.5">DNI: {{ emp.user.dni || 'S/D' }}</div>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-xs font-bold text-white">
                                            {{ emp.sucursal?.nombre }}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="c in emp.cargos" :key="c.id" class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                                <span class="w-2 h-2 rounded-full shrink-0" :class="colorCargo(c.nombre)"></span>
                                                <span>{{ c.nombre }}</span>
                                            </span>
                                            <span v-if="!emp.cargos?.length" class="text-xs text-zinc-500 italic">Sin cargo</span>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                                <span class="w-2 h-2 rounded-full shrink-0" :class="emp.fecha_egreso ? 'bg-rose-400' : 'bg-emerald-400'"></span>
                                                <span>{{ emp.fecha_egreso ? 'Baja: ' + emp.fecha_egreso : 'Activo' }}</span>
                                            </span>
                                        </div>
                                        <div class="text-xs text-zinc-500 font-medium mt-1">Desde: {{ emp.fecha_ingreso }}</div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button @click="openAccesosModal(emp)" title="Gestionar Accesos" class="p-2 text-zinc-400 hover:text-sky-400 hover:bg-sky-500/10 rounded-xl transition-all">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </button>
                                            <button @click="openModal(emp)" title="Editar Empleado" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button @click="deleteEmpleado(emp.id)" title="Dar de baja" class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="empleados.data.length === 0">
                                    <td colspan="5" class="p-12 text-center text-zinc-500 italic">
                                        No hay registros de empleados bajo estos criterios
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div v-if="empleados.links && empleados.links.length > 3" class="flex justify-center gap-2 mt-6">
                    <Link v-for="link in empleados.links" :key="link.label" :href="link.url || '#'" class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" v-html="decodeLabel(link.label)"></Link>
                </div>
            </div>
        </div>

        <!-- Modal Editar / Alta Empleado -->
        <Teleport to="body">
            <div v-if="showModal" class="page-empleados">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-2xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isEditing ? 'EDITAR' : 'NUEVO' }} EMPLEADO
                            </h3>
                            <button @click="showModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submit" class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
                            <!-- Datos Personales -->
                            <div class="space-y-4">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-white border-b border-white/5 pb-2">Datos Personales</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre *</label>
                                        <input v-model="form.name" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.name}">
                                        <p v-if="form.errors.name" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Apellido *</label>
                                        <input v-model="form.apellido" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.apellido}">
                                        <p v-if="form.errors.apellido" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.apellido }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">DNI</label>
                                        <input v-model="form.dni" @input="form.dni = form.dni.replace(/\D/g, '')" type="text" inputmode="numeric" maxlength="8" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.dni}">
                                        <p v-if="form.errors.dni" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.dni }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Email Corporativo *</label>
                                        <input v-model="form.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.email}">
                                        <p v-if="form.errors.email" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.email }}</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono Móvil</label>
                                        <input v-model="form.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.telefono}">
                                        <p v-if="form.errors.telefono" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.telefono }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Datos de Empresa -->
                            <div class="space-y-4 pt-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-white border-b border-white/5 pb-2">Datos de la Empresa</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Cargo Principal</label>
                                        <select v-model="form.cargo_id" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" :class="{'border-rose-500': form.errors.cargo_id}">
                                            <option value="" class="bg-[#131316] text-zinc-400">Sin cargo asignado</option>
                                            <option v-for="c in cargos" :key="c.id" :value="c.id" class="bg-[#131316] text-white">{{ c.nombre }}</option>
                                        </select>
                                        <p v-if="form.errors.cargo_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.cargo_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Sucursal Destino *</label>
                                        <select v-model="form.sucursal_id" :disabled="isRepartidorCargo" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30" :class="{'border-rose-500': form.errors.sucursal_id, 'opacity-60 cursor-not-allowed bg-[#131316]/50': isRepartidorCargo, 'cursor-pointer': !isRepartidorCargo}">
                                            <option value="" disabled class="bg-[#131316] text-zinc-400">Seleccionar Sucursal</option>
                                            <option v-for="s in sucursales" :key="s.id" :value="s.id" class="bg-[#131316] text-white">
                                                {{ s.nombre }}{{ s.es_principal ? ' (Central)' : '' }}
                                            </option>
                                        </select>
                                        <p v-if="isRepartidorCargo" class="text-amber-400/90 text-[11px] font-medium mt-1.5 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Los repartidores operan exclusivamente desde la Sucursal Central.
                                        </p>
                                        <p v-if="form.errors.sucursal_id" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.sucursal_id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha Ingreso *</label>
                                        <input v-model="form.fecha_ingreso" type="date" @click="triggerDatePicker" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" :class="{'border-rose-500': form.errors.fecha_ingreso}">
                                        <p v-if="form.errors.fecha_ingreso" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.fecha_ingreso }}</p>
                                    </div>
                                    <div v-if="isEditing" class="md:col-span-2">
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Fecha Baja</label>
                                        <input v-model="form.fecha_egreso" type="date" @click="triggerDatePicker" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer" :class="{'border-rose-500': form.errors.fecha_egreso}">
                                        <p v-if="form.errors.fecha_egreso" class="text-rose-400 text-xs font-semibold mt-1">{{ form.errors.fecha_egreso }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="Object.keys(form.errors).length" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-400 font-semibold">
                                Revisá los campos marcados antes de continuar.
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ form.processing ? 'GUARDANDO...' : (isEditing ? 'ACTUALIZAR DATOS' : 'DAR DE ALTA') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Gestión de Accesos -->
        <Teleport to="body">
            <div v-if="showAccesosModal" class="page-empleados">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showAccesosModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Gestión de Accesos</h3>
                                <p class="text-xs text-zinc-400 font-medium mt-0.5">
                                    {{ empleadoSeleccionado?.user?.name }} {{ empleadoSeleccionado?.user?.apellido }} — DNI: {{ empleadoSeleccionado?.user?.dni || 'S/D' }}
                                </p>
                            </div>
                            <button @click="showAccesosModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Cargos actuales -->
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-3">Cargos Activos</p>
                                <div v-if="empleadoSeleccionado?.cargos?.length" class="flex flex-wrap gap-2">
                                    <div v-for="c in empleadoSeleccionado.cargos" :key="c.id" class="flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                        <span class="w-2 h-2 rounded-full shrink-0" :class="colorCargo(c.nombre)"></span>
                                        <span>{{ c.nombre }}</span>
                                        <button @click="desasignarCargo(c)" class="hover:text-white transition-colors opacity-70 hover:opacity-100 ml-1">✕</button>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-zinc-500 italic">Este empleado no tiene cargos asignados</p>
                            </div>

                            <!-- Asignar nuevo cargo -->
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Asignar Cargo</p>
                                <div class="flex gap-2">
                                    <select v-model="asignarForm.cargo_id" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs font-semibold text-white focus:outline-none focus:border-white/30 cursor-pointer">
                                        <option value="" disabled class="bg-[#131316] text-zinc-400">Seleccionar cargo...</option>
                                        <option v-for="c in cargos" :key="c.id" :value="c.id"
                                            :disabled="empleadoSeleccionado?.cargos?.some(ec => ec.id === c.id)" class="bg-[#131316] text-white">
                                            {{ c.nombre }}
                                        </option>
                                    </select>
                                    <button @click="asignarCargo" :disabled="!asignarForm.cargo_id || asignarForm.processing" class="px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50 shrink-0">
                                        Asignar
                                    </button>
                                </div>
                                <p v-if="isAsignandoRepartidor && empleadoSeleccionado?.sucursal_id !== sucursalPrincipal?.id" class="text-amber-400/90 text-[11px] font-medium mt-2 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Al asignar REPARTIDOR, la sucursal del empleado se asignará a la Sucursal Central.
                                </p>
                                <p v-if="asignarForm.errors.cargo_id" class="text-rose-400 text-xs font-semibold mt-1">{{ asignarForm.errors.cargo_id }}</p>
                            </div>

                            <!-- Restablecer contraseña -->
                            <div class="border-t border-white/5 pt-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Credenciales</p>
                                <button @click="resetearPassword" :disabled="resetPasswordForm.processing" class="w-full py-2.5 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs border border-white/10 transition-all">
                                    Restablecer Contraseña
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-empleados,
.page-empleados * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
