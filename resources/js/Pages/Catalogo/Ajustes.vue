<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';
import { calculateSimilarity, normalizeText, detectPotentialDuplicate } from '@/composables/useSmartSearch';

const props = defineProps({
    autores: Array,
    categorias: Array,
    idiomas: Array,
    formatos: Array
});

const currentTab = ref('autores');
const searchQuery = ref('');

const tabs = [
    { id: 'autores', name: 'Autores' },
    { id: 'categorias', name: 'Categorías' },
    { id: 'formatos', name: 'Formatos' },
    { id: 'idiomas', name: 'Idiomas' }
];

const selectTab = (tabId) => {
    currentTab.value = tabId;
    searchQuery.value = '';
};

// Helper para obtener el texto representativo de un elemento
const getItemLabel = (item, type = currentTab.value) => {
    if (!item) return '';
    if (type === 'autores') {
        const parts = [item.nombre, item.apellido].filter(Boolean);
        return parts.join(' ');
    }
    if (type === 'proveedores') {
        return item.nombre_empresa || '';
    }
    return item.nombre || '';
};

// Buscador Inteligente con ordenamiento por relevancia y similitud
const filteredItems = computed(() => {
    let list = [];
    if (currentTab.value === 'autores') list = props.autores || [];
    else if (currentTab.value === 'categorias') list = props.categorias || [];
    else if (currentTab.value === 'formatos') list = props.formatos || [];
    else if (currentTab.value === 'idiomas') list = props.idiomas || [];

    const query = searchQuery.value.trim();
    if (!query) return list;

    const normQ = normalizeText(query);

    const scored = list.map(item => {
        let score = 0;
        if (currentTab.value === 'autores') {
            const fullName = `${item.nombre || ''} ${item.apellido || ''}`.trim();
            const revName = `${item.apellido || ''} ${item.nombre || ''}`.trim();
            const s1 = calculateSimilarity(query, fullName);
            const s2 = calculateSimilarity(query, revName);
            const sNombre = calculateSimilarity(query, item.nombre || '');
            const sApellido = calculateSimilarity(query, item.apellido || '');
            score = Math.max(s1, s2, sNombre, sApellido);

            if (normalizeText(fullName).includes(normQ) || normalizeText(revName).includes(normQ)) {
                score = Math.max(score, 88);
            }
        } else {
            const label = getItemLabel(item, currentTab.value);
            score = calculateSimilarity(query, label);
            if (normalizeText(label).includes(normQ)) {
                score = Math.max(score, 88);
            }
        }

        return { item, score };
    });

    const filtered = scored.filter(entry => entry.score >= 45);
    filtered.sort((a, b) => b.score - a.score);
    return filtered.map(entry => entry.item);
});

// Indica si el buscador está mostrando sugerencias por aproximación
const isFuzzySearchActive = computed(() => {
    const q = searchQuery.value.trim();
    if (!q || filteredItems.value.length === 0) return false;
    const normQ = normalizeText(q);
    const topItem = filteredItems.value[0];
    const topLabel = normalizeText(getItemLabel(topItem, currentTab.value));
    return !topLabel.includes(normQ);
});

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-ajustes',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

// Edit/Create logic
const showEditModal = ref(false);
const isCreating = ref(false);
const editingType = ref('');
const editingId = ref(null);

const editForm = useForm({
    nombre: '',
    apellido: '',
    codigo: '',
    old_nombre: '',
    
    // Campos Proveedor
    nombre_empresa: '',
    telefono: '',
    email: '',
    direccion: ''
});

const openCreateModal = () => {
    editingType.value = currentTab.value;
    editingId.value = null;
    isCreating.value = true;
    
    editForm.nombre = '';
    editForm.apellido = '';
    editForm.codigo = '';
    editForm.old_nombre = '';
    editForm.nombre_empresa = '';
    editForm.telefono = '';
    editForm.email = '';
    editForm.direccion = '';
    
    editForm.clearErrors();
    
    showEditModal.value = true;
};

const openEditModal = (item) => {
    editingType.value = currentTab.value;
    editingId.value = item.id;
    isCreating.value = false;

    editForm.nombre = item.nombre || '';
    editForm.apellido = item.apellido || '';
    editForm.codigo = item.codigo || '';
    editForm.old_nombre = item.nombre || '';
    editForm.nombre_empresa = item.nombre_empresa || '';
    editForm.telefono = item.telefono || '';
    editForm.email = item.email || '';
    editForm.direccion = item.direccion || '';

    editForm.clearErrors();

    showEditModal.value = true;
};

// Cadena que el usuario está escribiendo para verificar duplicados
const inputParaVerificar = computed(() => {
    if (editingType.value === 'autores') {
        const n = (editForm.nombre || '').trim();
        const a = (editForm.apellido || '').trim();
        return [n, a].filter(Boolean).join(' ');
    }
    if (editingType.value === 'proveedores') {
        return (editForm.nombre_empresa || '').trim();
    }
    return (editForm.nombre || '').trim();
});

// Detección reactiva de duplicados en tiempo real
const detectedDuplicate = computed(() => {
    if (!isCreating.value || !showEditModal.value) return { hasDuplicate: false, topMatch: null, score: 0 };
    const query = inputParaVerificar.value;
    if (!query || query.length < 2) return { hasDuplicate: false, topMatch: null, score: 0 };

    let currentList = [];
    if (editingType.value === 'autores') currentList = props.autores || [];
    else if (editingType.value === 'categorias') currentList = props.categorias || [];
    else if (editingType.value === 'formatos') currentList = props.formatos || [];
    else if (editingType.value === 'idiomas') currentList = props.idiomas || [];

    if (!currentList.length) return { hasDuplicate: false, topMatch: null, score: 0 };

    return detectPotentialDuplicate(
        query,
        currentList,
        (item) => getItemLabel(item, editingType.value),
        68
    );
});

const usarRegistroExistente = (matchedItem) => {
    showEditModal.value = false;
    const label = getItemLabel(matchedItem, editingType.value);
    searchQuery.value = label;
    darkSwal.fire({
        title: 'Registro Existente',
        text: `Se seleccionó '${label}'. El registro ya se encuentra en el sistema, evitando duplicados.`,
        icon: 'info',
        timer: 2000,
        showConfirmButton: false
    });
};

const getCleanPayload = (forzar = false) => {
    const payload = { forzar };
    if (editingType.value === 'autores') {
        payload.nombre = (editForm.nombre || '').trim();
        payload.apellido = (editForm.apellido || '').trim();
    } else if (editingType.value === 'proveedores') {
        payload.nombre_empresa = (editForm.nombre_empresa || '').trim();
        payload.email = (editForm.email || '').trim();
        payload.telefono = (editForm.telefono || '').trim();
        payload.direccion = (editForm.direccion || '').trim();
    } else {
        payload.nombre = (editForm.nombre || '').trim();
    }
    return payload;
};

const executeSubmit = (forzar = false) => {
    const payload = getCleanPayload(forzar);

    // Validación básica previa
    if (editingType.value === 'autores') {
        if (!payload.nombre || !payload.apellido) {
            darkSwal.fire({
                title: 'Campos obligatorios',
                text: 'Debes ingresar tanto el nombre como el apellido del autor.',
                icon: 'warning'
            });
            return;
        }
    } else if (editingType.value === 'proveedores') {
        if (!payload.nombre_empresa) {
            darkSwal.fire({
                title: 'Campo obligatorio',
                text: 'El nombre de empresa es obligatorio.',
                icon: 'warning'
            });
            return;
        }
    } else {
        if (!payload.nombre) {
            darkSwal.fire({
                title: 'Campo obligatorio',
                text: `El nombre de ${itemName.value.toLowerCase()} es obligatorio.`,
                icon: 'warning'
            });
            return;
        }
    }

    if (isCreating.value) {
        editForm.transform(() => payload).post(route('catalogo.ajustes.store', { type: editingType.value }), {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false;
                editForm.reset();
                darkSwal.fire({
                    title: '¡Creado!',
                    text: `${itemName.value} registrado con éxito.`,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError && (firstError.includes('confirma la creación') || firstError.includes('similar'))) {
                    darkSwal.fire({
                        title: '¿Confirmar creación?',
                        text: firstError,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, crear de todas formas',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeSubmit(true);
                        }
                    });
                } else {
                    darkSwal.fire({
                        title: 'Atención',
                        text: firstError || 'No se pudo completar el registro. Verifica los campos.',
                        icon: 'warning'
                    });
                }
            }
        });
    } else {
        editForm.transform(() => payload).put(route('catalogo.ajustes.update', { type: editingType.value, id: editingId.value }), {
            preserveScroll: true,
            onSuccess: () => {
                showEditModal.value = false;
                editForm.reset();
                darkSwal.fire({
                    title: '¡Actualizado!',
                    text: `${itemName.value} modificado con éxito.`,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                darkSwal.fire({
                    title: 'Error de validación',
                    text: firstError || 'Verifique los campos requeridos.',
                    icon: 'error'
                });
            }
        });
    }
};

const submitEdit = () => {
    if (isCreating.value && detectedDuplicate.value.hasDuplicate && detectedDuplicate.value.isCritical) {
        const item = detectedDuplicate.value.topMatch;
        const nombreExistente = detectedDuplicate.value.matchedLabel;
        const score = detectedDuplicate.value.score;

        darkSwal.fire({
            title: '¿Crear de todas formas?',
            text: `Detectamos que ya existe un registro muy similar: "${nombreExistente}" (${score}% de coincidencia). Para no duplicar datos, ¿deseas usar el existente o crearlo de todas formas?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear de todas formas',
            cancelButtonText: 'Usar existente',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                executeSubmit(true);
            } else if (result.dismiss === Swal.DismissReason.cancel && item) {
                usarRegistroExistente(item);
            }
        });
        return;
    }

    executeSubmit(false);
};

// Safe Delete Logic
const confirmDelete = (item) => {
    const type = currentTab.value;
    const count = item.libro_masters_count || 0;

    if (count > 0) {
        darkSwal.fire({
            title: 'No se puede eliminar',
            text: `Este registro tiene ${count} obra(s) asociada(s). Debes desvincularla(s) del catálogo antes de poder eliminarlo por seguridad.`,
            icon: 'error',
        });
        return;
    }

    darkSwal.fire({
        title: '¿Estás seguro?',
        text: 'Esta acción es irreversible.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('catalogo.ajustes.destroy', { type, id: item.id }), {
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminado',
                        text: 'El registro ha sido eliminado con éxito.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const itemName = computed(() => {
    if (editingType.value === 'autores') return 'Autor';
    if (editingType.value === 'categorias') return 'Categoría';
    if (editingType.value === 'formatos') return 'Formato';
    if (editingType.value === 'proveedores') return 'Proveedor';
    if (editingType.value === 'idiomas') return 'Idioma';
    return 'Metadato';
});
</script>

<template>
    <Head title="Características del Catálogo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-ajustes">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CARACTERÍSTICAS DEL CATÁLOGO</h2>
                </div>
            </div>
        </template>

        <div class="py-8 page-ajustes">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Tab Selection Bar -->
                <div class="flex border-b border-white/5 gap-3 pb-1">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="selectTab(tab.id)"
                        class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-all"
                        :class="currentTab === tab.id 
                            ? 'border-white text-white font-bold' 
                            : 'border-transparent text-zinc-400 hover:text-white font-medium'"
                    >
                        {{ tab.name }}
                    </button>
                </div>

                <!-- Search Filter Bar -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Buscar por nombre..."
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <button 
                            @click="openCreateModal" 
                            class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Nuevo</span>
                        </button>
                    </div>
                </div>

                <!-- Sugerencia inteligente de búsqueda aproximada -->
                <div v-if="searchQuery.trim() && filteredItems.length > 0 && isFuzzySearchActive" class="flex items-center gap-2 text-xs text-amber-300 bg-amber-500/10 px-4 py-2.5 rounded-xl border border-amber-500/20 shadow-sm animate-fadeIn">
                    <span class="text-base">💡</span>
                    <span>Búsqueda inteligente: Mostrando coincidencias aproximadas para <strong class="text-white font-bold">"{{ searchQuery }}"</strong>.</span>
                </div>

                <!-- Table Section -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    <th class="p-4" v-if="currentTab === 'autores'">Autor</th>
                                    <th class="p-4" v-else-if="currentTab === 'categorias'">Categoría</th>
                                    <th class="p-4" v-else-if="currentTab === 'formatos'">Formato</th>
                                    <th class="p-4" v-else-if="currentTab === 'proveedores'">Proveedor</th>
                                    <th class="p-4" v-else-if="currentTab === 'idiomas'">Idioma</th>

                                    <th class="p-4" v-if="currentTab === 'proveedores'">Email</th>
                                    <th class="p-4" v-if="currentTab === 'proveedores'">Teléfono</th>

                                    <th class="p-4 text-center">Obras Asociadas</th>
                                    <th class="p-4 text-center w-36">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <tr v-if="filteredItems.length === 0">
                                    <td colspan="4" class="p-12 text-center text-zinc-500 italic">
                                        No se encontraron registros
                                    </td>
                                </tr>
                                <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-white/[0.02] transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-white tracking-tight" v-if="currentTab === 'autores'">
                                            {{ item.apellido }}, {{ item.nombre }}
                                        </div>
                                        <div class="font-bold text-white tracking-tight" v-else-if="currentTab === 'proveedores'">
                                            {{ item.nombre_empresa }}
                                        </div>
                                        <div class="font-bold text-white tracking-tight" v-else>
                                            {{ item.nombre }}
                                        </div>
                                    </td>

                                    <td class="p-4 text-zinc-400 font-mono text-xs" v-if="currentTab === 'proveedores'">
                                        {{ item.email || 'N/A' }}
                                    </td>
                                    <td class="p-4 text-zinc-400 font-mono text-xs" v-if="currentTab === 'proveedores'">
                                        {{ item.telefono || 'N/A' }}
                                    </td>

                                    <td class="p-4 text-center">
                                        <span class="bg-white/5 text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-semibold border border-white/5">
                                            {{ item.libro_masters_count || 0 }}
                                        </span>
                                    </td>

                                    <td class="p-4 text-center w-36">
                                        <div class="flex justify-center gap-1 items-center">
                                            <button 
                                                @click="openEditModal(item)"
                                                class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all"
                                                title="Editar"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                            </button>
                                            <button 
                                                @click="confirmDelete(item)"
                                                class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all"
                                                title="Eliminar"
                                            >
                                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit / Create Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="page-ajustes">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showEditModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="w-full max-w-md bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                {{ isCreating ? 'Crear' : 'Editar' }} {{ itemName }}
                            </h3>
                            <button @click="showEditModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                            <!-- Auto fields based on Type -->
                            <div v-if="editingType === 'autores'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre *</label>
                                    <input v-model="editForm.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Apellido *</label>
                                    <input v-model="editForm.apellido" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.apellido" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.apellido }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'categorias'">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre Categoría *</label>
                                    <input v-model="editForm.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'proveedores'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre de Empresa *</label>
                                    <input v-model="editForm.nombre_empresa" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre_empresa" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre_empresa }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Email *</label>
                                    <input v-model="editForm.email" type="email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.email" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.email }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Teléfono</label>
                                    <input v-model="editForm.telefono" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                    <span v-if="editForm.errors.telefono" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.telefono }}</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Dirección</label>
                                    <DireccionAutocomplete v-model="editForm.direccion" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" />
                                    <span v-if="editForm.errors.direccion" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.direccion }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'formatos'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre del Formato *</label>
                                    <input v-model="editForm.nombre" type="text" placeholder="ej. Tankoubon (11.5x17 cm)" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                            </div>

                            <div v-if="editingType === 'idiomas'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre Idioma *</label>
                                    <input v-model="editForm.nombre" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" required />
                                    <span v-if="editForm.errors.nombre" class="text-rose-400 text-xs mt-1 block font-semibold">{{ editForm.errors.nombre }}</span>
                                </div>
                            </div>

                            <!-- Sugerencia reactiva de duplicados -->
                            <div v-if="isCreating && detectedDuplicate.hasDuplicate" class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-200 text-xs space-y-2 animate-fadeIn transition-all">
                                <div class="flex items-center gap-2 font-bold text-amber-300">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span>Posible duplicado detectado</span>
                                    <span class="ml-auto text-[10px] px-2 py-0.5 rounded-full font-extrabold bg-amber-400/20 text-amber-300">
                                        {{ detectedDuplicate.score }}% de coincidencia
                                    </span>
                                </div>
                                <p class="text-zinc-300 leading-relaxed text-[11px]">
                                    Ya existe un registro similar: <strong class="text-white font-bold">"{{ detectedDuplicate.matchedLabel }}"</strong>. Te sugerimos seleccionarlo para evitar duplicar registros en el catálogo.
                                </p>
                                <div class="flex items-center gap-2 pt-1">
                                    <button 
                                        type="button" 
                                        @click="usarRegistroExistente(detectedDuplicate.topMatch)"
                                        class="px-3 py-1.5 bg-amber-400 hover:bg-amber-300 text-black font-bold text-[11px] rounded-lg transition-all shadow-sm active:scale-95 flex items-center gap-1.5 cursor-pointer"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        <span>Usar este registro existente</span>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showEditModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                   <span>{{ editForm.processing ? 'PROCESANDO...' : 'GUARDAR' }}</span>
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

.page-ajustes,
.page-ajustes * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
