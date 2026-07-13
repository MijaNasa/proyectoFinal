<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    obras: Array,
    autores: Array,
    categorias: Array,
    editoriales: Array,
    idiomas: Array,
    sucursales: Array,
    filters: Object
});

const search = ref('');

const filteredObras = computed(() => {
    if (!search.value) return props.obras;
    
    const term = search.value.toLowerCase();
    return props.obras.filter(obra => {
        const matchesObra = 
            (obra.titulo && obra.titulo.toLowerCase().includes(term)) ||
            (obra.autor && (obra.autor.nombre.toLowerCase().includes(term) || obra.autor.apellido.toLowerCase().includes(term)));
            
        const matchesTomo = obra.libros && obra.libros.some(l => 
            (l.isbn && l.isbn.toLowerCase().includes(term))
        );
        
        return matchesObra || matchesTomo;
    });
});

const expandedMasters = ref([]);

const toggleMaster = (masterId) => {
    const index = expandedMasters.value.indexOf(masterId);
    if (index > -1) {
        expandedMasters.value.splice(index, 1);
    } else {
        expandedMasters.value.push(masterId);
    }
};

// --- LOGICA DE OBRA (LibroMaster) ---
const showObraModal = ref(false);
const isEditingObra = ref(false);

const obraForm = useForm({
    id: null,
    titulo: '',
    portada: null,
    autor_id: '',
    categoria_id: '',
    editorial_id: '',
    idioma_id: '',
    formato: '',
    synopsis: '',
    activo: true,
});

const formatosLocal = ref(['Tankobon', 'B6', 'A5', 'Kanzenban', 'Omnibus', 'Pocket', 'Novela Ligera', 'Otro']);

const openObraModal = (obra = null) => {
    if (obra) {
        isEditingObra.value = true;
        obraForm.id = obra.id;
        obraForm.titulo = obra.titulo;
        obraForm.autor_id = obra.autor_id || '';
        obraForm.categoria_id = obra.categoria_id || '';
        obraForm.editorial_id = obra.editorial_id || '';
        obraForm.idioma_id = obra.idioma_id || '';
        obraForm.formato = obra.formato || '';
        obraForm.synopsis = obra.synopsis || '';
        obraForm.activo = !!obra.activo;

        if (obra.formato && !formatosLocal.value.includes(obra.formato)) {
            formatosLocal.value.unshift(obra.formato);
        }
    } else {
        isEditingObra.value = false;
        obraForm.reset();
    }
    showObraModal.value = true;
};

const agregarAutor = () => {
    Swal.fire({
        title: 'Agregar Nuevo Autor',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Nombre *</label>
                    <input id="swal-autor-nombre" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="text" placeholder="Ej: Eiichiro">
                </div>
                <div>
                    <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Apellido *</label>
                    <input id="swal-autor-apellido" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="text" placeholder="Ej: Oda">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF',
        preConfirm: () => {
            const nombre = document.getElementById('swal-autor-nombre').value.trim();
            const apellido = document.getElementById('swal-autor-apellido').value.trim();
            if (!nombre || !apellido) {
                Swal.showValidationMessage('Nombre y Apellido son obligatorios');
                return false;
            }
            return { nombre, apellido };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.axios.post(route('autores.store'), result.value)
                .then(() => {
                    router.reload({ 
                        only: ['autores'],
                        onSuccess: () => {
                            Swal.fire({ title: 'Autor Creado', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
                            const newObj = props.autores.find(x => x.nombre === result.value.nombre && x.apellido === result.value.apellido);
                            if (newObj) obraForm.autor_id = newObj.id;
                        }
                    });
                })
                .catch(err => {
                    Swal.fire({ title: 'Error', text: err.response?.data?.message || 'Error al guardar', icon: 'error', background: '#1A1A1A', color: '#FFF' });
                });
        }
    });
};

const agregarCategoria = () => {
    Swal.fire({
        title: 'Agregar Nueva Categoría',
        html: `
            <div class="text-left">
                <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Nombre *</label>
                <input id="swal-cat-nombre" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="text" placeholder="Ej: Shonen">
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF',
        preConfirm: () => {
            const nombre = document.getElementById('swal-cat-nombre').value.trim();
            if (!nombre) {
                Swal.showValidationMessage('El nombre es obligatorio');
                return false;
            }
            return { nombre };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.axios.post(route('categorias.store'), result.value)
                .then(() => {
                    router.reload({ 
                        only: ['categorias'],
                        onSuccess: () => {
                            Swal.fire({ title: 'Categoría Creada', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
                            const newObj = props.categorias.find(x => x.nombre === result.value.nombre);
                            if (newObj) obraForm.categoria_id = newObj.id;
                        }
                    });
                })
                .catch(err => {
                    Swal.fire({ title: 'Error', text: err.response?.data?.message || 'Error al guardar', icon: 'error', background: '#1A1A1A', color: '#FFF' });
                });
        }
    });
};

const agregarEditorial = () => {
    Swal.fire({
        title: 'Agregar Nueva Editorial',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Nombre *</label>
                    <input id="swal-ed-nombre" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="text" placeholder="Ej: Ivrea">
                </div>
                <div>
                    <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Email de Contacto *</label>
                    <input id="swal-ed-email" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="email" placeholder="Ej: contacto@editorial.com">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF',
        preConfirm: () => {
            const nombre = document.getElementById('swal-ed-nombre').value.trim();
            const email = document.getElementById('swal-ed-email').value.trim();
            if (!nombre || !email) {
                Swal.showValidationMessage('Nombre y Email de contacto son obligatorios');
                return false;
            }
            return { nombre, email };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.axios.post(route('editoriales.store'), result.value)
                .then(() => {
                    router.reload({ 
                        only: ['editoriales'],
                        onSuccess: () => {
                            Swal.fire({ title: 'Editorial Creada', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
                            const newObj = props.editoriales.find(x => x.nombre === result.value.nombre);
                            if (newObj) obraForm.editorial_id = newObj.id;
                        }
                    });
                })
                .catch(err => {
                    Swal.fire({ title: 'Error', text: err.response?.data?.message || 'Error al guardar', icon: 'error', background: '#1A1A1A', color: '#FFF' });
                });
        }
    });
};

const agregarIdioma = () => {
    Swal.fire({
        title: 'Agregar Nuevo Idioma',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Nombre *</label>
                    <input id="swal-id-nombre" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="text" placeholder="Ej: Japonés">
                </div>
                <div>
                    <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Código (3 letras) *</label>
                    <input id="swal-id-codigo" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1 uppercase" type="text" maxlength="10" placeholder="Ej: JAP">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF',
        preConfirm: () => {
            const nombre = document.getElementById('swal-id-nombre').value.trim();
            const codigo = document.getElementById('swal-id-codigo').value.trim().toUpperCase();
            if (!nombre || !codigo) {
                Swal.showValidationMessage('Nombre y Código son obligatorios');
                return false;
            }
            return { nombre, codigo };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.axios.post(route('idiomas.store'), result.value)
                .then(() => {
                    router.reload({ 
                        only: ['idiomas'],
                        onSuccess: () => {
                            Swal.fire({ title: 'Idioma Creado', icon: 'success', timer: 1500, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
                            const newObj = props.idiomas.find(x => x.nombre === result.value.nombre);
                            if (newObj) obraForm.idioma_id = newObj.id;
                        }
                    });
                })
                .catch(err => {
                    Swal.fire({ title: 'Error', text: err.response?.data?.message || 'Error al guardar', icon: 'error', background: '#1A1A1A', color: '#FFF' });
                });
        }
    });
};

const agregarFormato = () => {
    Swal.fire({
        title: 'Agregar Nuevo Formato',
        html: `
            <div class="text-left">
                <label class="text-[10px] uppercase font-black tracking-widest text-white/40">Nombre del Formato *</label>
                <input id="swal-formato-nombre" class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-sm text-white mt-1" type="text" placeholder="Ej: A4, Deluxe...">
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Agregar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF',
        preConfirm: () => {
            const nombre = document.getElementById('swal-formato-nombre').value.trim();
            if (!nombre) {
                Swal.showValidationMessage('El nombre es obligatorio');
                return false;
            }
            return nombre;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const nuevo = result.value;
            if (!formatosLocal.value.includes(nuevo)) {
                formatosLocal.value.unshift(nuevo);
            }
            obraForm.formato = nuevo;
            Swal.fire({ title: 'Formato Añadido', icon: 'success', timer: 1000, showConfirmButton: false, background: '#1A1A1A', color: '#FFF' });
        }
    });
};

const submitObra = () => {
    if (isEditingObra.value) {
        obraForm.put(route('libro-masters.update', obraForm.id), {
            onSuccess: () => {
                showObraModal.value = false;
                Swal.fire({ title: '¡Éxito!', text: 'Obra actualizada correctamente', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
            }
        });
    } else {
        obraForm.post(route('libro-masters.store'), {
            onSuccess: () => {
                showObraModal.value = false;
                obraForm.reset();
                Swal.fire({ title: '¡Éxito!', text: 'Nueva obra registrada correctamente', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
            }
        });
    }
};

const deleteObra = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esto eliminará la obra y TODOS sus tomos asociados. Es irreversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            obraForm.delete(route('libro-masters.destroy', id));
        }
    });
};


// --- LOGICA DE TOMO (Libro) ---
const showTomoModal = ref(false);
const isEditingTomo = ref(false);
const currentStocks = ref({});

const tomoForm = useForm({
    id: null,
    isbn: '',
    master_id: '',
    numero_tomo: '',
    año_edicion: '',
    cantidad_paginas: '',
    activo: true,
    permite_preventa: false,
    precio_compra: 0,
    precio_venta: 0,
});

const openTomoModal = (tomo = null, masterId = null) => {
    if (tomo) {
        isEditingTomo.value = true;
        tomoForm.id = tomo.id;
        tomoForm.isbn = tomo.isbn || '';
        tomoForm.master_id = tomo.master_id;
        tomoForm.numero_tomo = tomo.numero_tomo || '';
        tomoForm.año_edicion = tomo.año_edicion || '';
        tomoForm.cantidad_paginas = tomo.cantidad_paginas || '';
        tomoForm.activo = !!tomo.activo;
        tomoForm.permite_preventa = !!tomo.permite_preventa;
        
        const currentPrice = tomo.precios?.find(p => p.activo);
        tomoForm.precio_compra = currentPrice ? currentPrice.precio_compra : 0;
        tomoForm.precio_venta = currentPrice ? currentPrice.precio_venta : 0;
        
        const stockData = {};
        if (tomo.stocks) {
            tomo.stocks.forEach(st => {
                stockData[st.sucursal_id] = st.cantidad_disponible;
            });
        }
        currentStocks.value = stockData;
    } else {
        isEditingTomo.value = false;
        currentStocks.value = {};
        tomoForm.reset();
        tomoForm.master_id = masterId; // Pre-seleccionar la obra
    }
    showTomoModal.value = true;
};

const submitTomo = () => {
    if (isEditingTomo.value) {
        tomoForm.put(route('libros.update', tomoForm.id), {
            onSuccess: () => {
                showTomoModal.value = false;
                Swal.fire({ title: '¡Éxito!', text: 'Tomo actualizado correctamente', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
            }
        });
    } else {
        tomoForm.post(route('libros.store'), {
            onSuccess: () => {
                showTomoModal.value = false;
                tomoForm.reset();
                Swal.fire({ title: '¡Éxito!', text: 'Nuevo tomo registrado correctamente', icon: 'success', background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919' });
            }
        });
    }
};

const deleteTomo = (id) => {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esto eliminará este tomo específico. La obra maestra se mantendrá.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            tomoForm.delete(route('libros.destroy', id));
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};
</script>

<template>
    <Head title="Catálogo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Catálogo <span class="text-brand-red italic">Principal</span>
                </h2>
                <button @click="openObraModal()" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Nueva Obra
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="card mb-8">
                    <div class="flex items-center gap-4">
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por título de obra, autor o ISBN..." 
                            class="input-field flex-1"
                        >
                    </div>
                </div>

                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-brand-red/10 border-b border-brand-red/20">
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Obra (Franquicia)</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red">Autor</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-center">Cantidad de Tomos</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-center">Editorial</th>
                                <th class="p-4 font-bold uppercase text-xs tracking-wider text-brand-red text-right">Acciones Obra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="obra in filteredObras" :key="obra.id">
                                <!-- Master Row -->
                                <tr @click="toggleMaster(obra.id)" class="hover:bg-white/[0.05] transition-colors cursor-pointer group-row">
                                    <td class="p-4">
                                        <div class="font-black text-xl leading-tight uppercase text-white">{{ obra.titulo }}</div>
                                        <div class="text-[10px] text-white/40 uppercase tracking-widest mt-1">{{ obra.categoria ? obra.categoria.nombre : '' }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-sm text-white/60 italic font-medium">{{ obra.autor ? obra.autor.nombre + ' ' + obra.autor.apellido : 'S/A' }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="bg-brand-red/20 text-brand-red px-3 py-1 rounded-full text-xs font-black">{{ obra.libros ? obra.libros.length : 0 }}</span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="text-sm font-bold text-white/80 uppercase">{{ obra.editorial ? obra.editorial.nombre : 'S/E' }}</span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="flex justify-end gap-2 items-center">
                                            <button @click.stop="openObraModal(obra)" class="p-2 text-white/40 hover:text-white transition-colors" title="Editar Obra">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                            </button>
                                            <button @click.stop="deleteObra(obra.id)" class="p-2 text-brand-red/40 hover:text-brand-red transition-colors" title="Eliminar Obra">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                            </button>
                                            <svg xmlns="http://www.w3.org/2000/svg" 
                                                 class="h-6 w-6 ml-2 text-white/30 transition-transform duration-300"
                                                 :class="{'rotate-180 text-brand-red': expandedMasters.includes(obra.id)}" 
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Detail Row -->
                                <tr v-show="expandedMasters.includes(obra.id)" class="bg-black/40">
                                    <td colspan="5" class="p-0 border-b border-brand-red/10">
                                        <div class="p-4 pl-12 border-l-2 border-brand-red/50 relative">
                                            
                                            <div class="flex justify-between items-end mb-4">
                                                <h4 class="text-xs font-black text-white/40 uppercase tracking-widest">Tomos Registrados</h4>
                                                <button @click.stop="openTomoModal(null, obra.id)" class="text-xs bg-white/5 hover:bg-brand-red/20 text-white hover:text-brand-red transition-colors px-3 py-1 rounded font-bold flex items-center gap-1 border border-white/10 hover:border-brand-red/50">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                    Añadir Tomo
                                                </button>
                                            </div>

                                            <table class="w-full text-left border-collapse" v-if="obra.libros && obra.libros.length > 0">
                                                <thead>
                                                    <tr class="text-[10px] text-white/40 uppercase tracking-widest border-b border-white/5">
                                                        <th class="pb-2">Tomo N°</th>
                                                        <th class="pb-2">ISBN</th>
                                                        <th class="pb-2 text-right">Precio</th>
                                                        <th class="pb-2 text-right">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="libro in obra.libros" :key="libro.id" class="hover:bg-white/[0.02] border-b border-white/5 last:border-0">
                                                        <td class="py-3 pr-4">
                                                            <div class="text-[12px] font-black text-brand-red uppercase tracking-widest">{{ libro.numero_tomo ? 'Tomo ' + libro.numero_tomo : 'Único' }}</div>
                                                        </td>
                                                        <td class="py-3 pr-4">
                                                            <span class="font-mono text-xs bg-white/5 px-2 py-1 rounded border border-white/10 text-white/70">{{ libro.isbn || 'SIN ISBN' }}</span>
                                                            <div class="text-[9px] text-white/30 mt-1 uppercase">Año: {{ libro.año_edicion || 'N/A' }}</div>
                                                        </td>
                                                        <td class="py-3 pr-4 text-right">
                                                            <div v-if="libro.precios && libro.precios.find(p => p.activo)" class="text-base font-black text-white">
                                                                {{ formatCurrency(libro.precios.find(p => p.activo).precio_venta) }}
                                                            </div>
                                                            <div v-else class="text-[10px] font-black uppercase text-brand-red opacity-50 italic">Sin Precio</div>
                                                        </td>
                                                        <td class="py-3 text-right">
                                                            <div class="flex justify-end gap-1">
                                                                <button @click.stop="openTomoModal(libro, obra.id)" class="p-1.5 text-white/40 hover:text-white hover:bg-white/10 rounded transition-colors" title="Editar Tomo">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                                                </button>
                                                                <button @click.stop="deleteTomo(libro.id)" class="p-1.5 text-brand-red/40 hover:text-brand-red hover:bg-brand-red/10 rounded transition-colors" title="Eliminar Tomo">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div v-else class="text-white/30 text-xs italic py-4 text-center border-t border-white/5">
                                                Esta obra aún no tiene tomos registrados.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="filteredObras.length === 0">
                                <td colspan="5" class="p-12 text-center text-white/30 italic">No se encontraron obras registradas en el catálogo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Modal OBRA -->
        <template v-if="showObraModal">
        <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm" @click="showObraModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-start justify-center p-4">
            <div class="relative w-full max-w-xl card p-0 border-brand-red shadow-2xl overflow-hidden transform transition-all group my-8">
                <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-xl font-black uppercase tracking-tighter relative"> {{ isEditingObra ? 'Editar' : 'Nueva' }} <span class="italic text-white">Obra</span></h3>
                    <button @click="showObraModal = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submitObra" class="p-6">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-brand-red mb-1">Título de la Obra</label>
                            <input v-model="obraForm.titulo" type="text" class="input-field w-full font-bold text-lg" placeholder="Ej: Dragon Ball" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Autor</label>
                            <div class="flex gap-2">
                                <select v-model="obraForm.autor_id" class="input-field flex-1 text-xs font-bold bg-brand-black" required>
                                    <option value="" disabled>-- Seleccionar Autor --</option>
                                    <option v-for="a in autores" :key="a.id" :value="a.id">{{ a.nombre }} {{ a.apellido || '' }}</option>
                                </select>
                                <button type="button" @click="agregarAutor" class="px-4 bg-white/5 hover:bg-brand-red text-white hover:text-white border border-white/10 hover:border-transparent transition-all rounded-xl font-black text-sm" title="Crear Autor">+</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Categoría</label>
                            <div class="flex gap-2">
                                <select v-model="obraForm.categoria_id" class="input-field flex-1 text-xs font-bold bg-brand-black" required>
                                    <option value="" disabled>-- Seleccionar Categoría --</option>
                                    <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                                <button type="button" @click="agregarCategoria" class="px-4 bg-white/5 hover:bg-brand-red text-white hover:text-white border border-white/10 hover:border-transparent transition-all rounded-xl font-black text-sm" title="Crear Categoría">+</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Editorial</label>
                            <div class="flex gap-2">
                                <select v-model="obraForm.editorial_id" class="input-field flex-1 text-xs font-bold bg-brand-black" required>
                                    <option value="" disabled>-- Seleccionar Editorial --</option>
                                    <option v-for="e in editoriales" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                                </select>
                                <button type="button" @click="agregarEditorial" class="px-4 bg-white/5 hover:bg-brand-red text-white hover:text-white border border-white/10 hover:border-transparent transition-all rounded-xl font-black text-sm" title="Crear Editorial">+</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Idioma</label>
                            <div class="flex gap-2">
                                <select v-model="obraForm.idioma_id" class="input-field flex-1 text-xs font-bold bg-brand-black" required>
                                    <option value="" disabled>-- Seleccionar Idioma --</option>
                                    <option v-for="i in idiomas" :key="i.id" :value="i.id">{{ i.nombre }}</option>
                                </select>
                                <button type="button" @click="agregarIdioma" class="px-4 bg-white/5 hover:bg-brand-red text-white hover:text-white border border-white/10 hover:border-transparent transition-all rounded-xl font-black text-sm" title="Crear Idioma">+</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Formato *</label>
                            <div class="flex gap-2">
                                <select 
                                    v-model="obraForm.formato" 
                                    class="input-field flex-1 text-xs font-bold"
                                    required
                                >
                                    <option value="" disabled>-- Selecciona Formato --</option>
                                    <option v-for="fmt in formatosLocal" :key="fmt" :value="fmt">{{ fmt }}</option>
                                </select>
                                <button type="button" @click="agregarFormato" class="px-4 bg-white/5 hover:bg-brand-red text-white hover:text-white border border-white/10 hover:border-transparent transition-all rounded-xl font-black text-sm" title="Crear Formato">+</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Sinopsis</label>
                            <textarea v-model="obraForm.synopsis" class="input-field w-full h-24 resize-none" placeholder="Breve descripción..."></textarea>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 p-3 rounded border border-white/5 mt-2">
                            <input type="checkbox" v-model="obraForm.activo" id="obra_activa" class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                            <label for="obra_activa" class="text-sm font-bold uppercase tracking-widest text-white/80 cursor-pointer">Obra Activa en Catálogo</label>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-white/10 pt-6">
                        <button type="button" @click="showObraModal = false" class="px-6 py-2 rounded-lg font-bold text-white/50 hover:bg-white/5 transition-colors uppercase text-xs">Cancelar</button>
                        <button type="submit" :disabled="obraForm.processing" class="btn-primary px-10 relative overflow-hidden group">
                           <span class="relative z-10">{{ obraForm.processing ? 'PROCESANDO...' : (isEditingObra ? 'ACTUALIZAR' : 'GUARDAR OBRA') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
        </template>


        <!-- Modal TOMO -->
        <template v-if="showTomoModal">
        <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-sm" @click="showTomoModal = false"></div>
        <div class="fixed inset-0 z-[101] overflow-y-auto">
            <div class="flex min-h-full items-start justify-center p-4">
            <div class="relative w-full max-w-2xl card p-0 border-brand-red shadow-2xl overflow-hidden transform transition-all group my-8">
                <div class="bg-gradient-to-r from-brand-red to-black p-4 flex justify-between items-center relative overflow-hidden">
                    <h3 class="text-xl font-black uppercase tracking-tighter relative"> {{ isEditingTomo ? 'Editar' : 'Añadir' }} <span class="italic text-white">Tomo</span></h3>
                    <button @click="showTomoModal = false" class="text-white/80 hover:text-white transition-colors relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <form @submit.prevent="submitTomo" class="p-6">
                    <!-- Master Id oculto o bloqueado ya que se asigna automáticamente -->
                    <input type="hidden" v-model="tomoForm.master_id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Volumen / Tomo N°</label>
                            <input v-model="tomoForm.numero_tomo" type="number" min="0" class="input-field w-full" placeholder="Ej: 1, 2, 3...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">ISBN</label>
                            <input v-model="tomoForm.isbn" type="text" class="input-field w-full font-mono" placeholder="Ej: 978-...">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Año</label>
                                <input v-model="tomoForm.año_edicion" type="number" class="input-field w-full" placeholder="2024">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-white/50 mb-1">Páginas</label>
                                <input v-model="tomoForm.cantidad_paginas" type="number" class="input-field w-full">
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 bg-white/5 p-3 rounded border border-white/5">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" v-model="tomoForm.activo" id="tomo_activo" class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                <label for="tomo_activo" class="text-sm font-bold uppercase tracking-widest text-white/80 cursor-pointer">Tomo Activo</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" v-model="tomoForm.permite_preventa" id="tomo_preventa" class="rounded border-white/20 bg-brand-black text-brand-red focus:ring-brand-red">
                                <label for="tomo_preventa" class="text-sm font-bold uppercase tracking-widest text-white/80 cursor-pointer">Habilitar Preventa</label>
                            </div>
                        </div>

                        <div v-if="!isEditingTomo" class="md:col-span-2 grid grid-cols-1 p-4 bg-brand-red/5 border border-brand-red/20 rounded-lg">
                            <div>
                                <label class="block text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-2">Precio Venta ($)</label>
                                <input v-model="tomoForm.precio_venta" type="number" step="0.01" class="input-field w-full text-right font-black text-xl" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div v-if="isEditingTomo" class="mt-6 p-4 bg-white/[0.03] border border-white/10 rounded-lg">
                        <label class="block text-xs font-black uppercase tracking-[0.2em] text-white/40 mb-3">Stock Actual</label>
                        <div class="space-y-2">
                            <div v-for="sucursal in sucursales" :key="sucursal.id" class="flex items-center justify-between gap-4 p-2 bg-black/20 rounded">
                                <span class="text-sm text-white/60 font-bold">{{ sucursal.nombre }}</span>
                                <span class="text-brand-red font-black text-lg">{{ currentStocks[sucursal.id] || 0 }} <span class="text-[10px] text-white/30 uppercase">uds</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-white/10 pt-6">
                        <button type="button" @click="showTomoModal = false" class="px-6 py-2 rounded-lg font-bold text-white/50 hover:bg-white/5 transition-colors uppercase text-xs">Cancelar</button>
                        <button type="submit" :disabled="tomoForm.processing" class="btn-primary px-10 relative overflow-hidden group">
                           <span class="relative z-10">{{ tomoForm.processing ? 'PROCESANDO...' : (isEditingTomo ? 'ACTUALIZAR' : 'REGISTRAR TOMO') }}</span>
                           <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                        </button>
                    </div>
                </form>
            </div>
            </div>
        </div>
        </template>

    </AuthenticatedLayout>
</template>
