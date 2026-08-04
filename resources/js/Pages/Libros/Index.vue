<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
    obras: Array,
    autores: Array,
    categorias: Array,
    proveedores: Array,
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

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-catalogo',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const formatTomoDisplay = (num) => {
    if (!num || String(num).trim() === '') return 'Único';
    const clean = String(num).trim();
    if (/^tomo\b/i.test(clean)) return clean;
    if (clean.toLowerCase() === 'único' || clean.toLowerCase() === 'unico') return 'Tomo Único';
    return `Tomo ${clean}`;
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
    proveedor_id: '',
    idioma_id: '',
    formato: '',
    synopsis: '',
    activo: true,
});

const formatosLocal = ref(['Tankobon', 'B6', 'A5', 'Kanzenban', 'Omnibus', 'Pocket', 'Novela Ligera', 'Otro']);

const autoresLocal = ref(props.autores ? [...props.autores] : []);
const categoriasLocal = ref(props.categorias ? [...props.categorias] : []);
const proveedoresLocal = ref(props.proveedores ? [...props.proveedores] : []);
const idiomasLocal = ref(props.idiomas ? [...props.idiomas] : []);

watch(() => props.autores, (newVal) => { autoresLocal.value = newVal ? [...newVal] : []; }, { deep: true });
watch(() => props.categorias, (newVal) => { categoriasLocal.value = newVal ? [...newVal] : []; }, { deep: true });
watch(() => props.proveedores, (newVal) => { proveedoresLocal.value = newVal ? [...newVal] : []; }, { deep: true });
watch(() => props.idiomas, (newVal) => { idiomasLocal.value = newVal ? [...newVal] : []; }, { deep: true });

const mappedProveedores = computed(() => {
    return proveedoresLocal.value.map(p => ({ id: p.id, nombre: p.nombre_empresa }));
});

const openObraModal = (obra = null) => {
    if (obra) {
        isEditingObra.value = true;
        obraForm.id = obra.id;
        obraForm.titulo = obra.titulo;
        obraForm.autor_id = obra.autor_id || '';
        obraForm.categoria_id = obra.categoria_id || '';
        obraForm.proveedor_id = obra.proveedor_id || '';
        obraForm.idioma_id = obra.idioma_id || '';
        obraForm.formato = obra.formato || '';
        obraForm.synopsis = obra.synopsis || '';
        obraForm.activo = !!obra.activo;

        if (obra.formato && !formatosLocal.value.includes(obra.formato)) {
            formatosLocal.value.unshift(obra.formato);
        }
    } else {
        isEditingObra.value = false;
        obraForm.titulo = '';
        obraForm.autor_id = '';
        obraForm.categoria_id = '';
        obraForm.proveedor_id = '';
        obraForm.idioma_id = '';
        obraForm.formato = '';
        obraForm.synopsis = '';
        obraForm.activo = true;
        obraForm.portada = null;
    }
    obraForm.clearErrors();
    showObraModal.value = true;
};

const agregarAutor = () => {
    darkSwal.fire({
        title: 'Agregar nuevo autor',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Nombre *</label>
                    <input id="swal-autor-nombre" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Eiichiro">
                </div>
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Apellido *</label>
                    <input id="swal-autor-apellido" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Oda">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const nombreInput = popup ? popup.querySelector('#swal-autor-nombre') : null;
            const apellidoInput = popup ? popup.querySelector('#swal-autor-apellido') : null;
            const nombre = nombreInput ? nombreInput.value.trim() : '';
            const apellido = apellidoInput ? apellidoInput.value.trim() : '';
            if (!nombre || !apellido) {
                Swal.showValidationMessage('Nombre y Apellido son obligatorios');
                return false;
            }
            try {
                const res = await window.axios.post('/catalogo/ajustes/autores', { nombre, apellido }, {
                    headers: { 'Accept': 'application/json' }
                });
                const createdItem = res.data.model || res.data.data;
                if (!createdItem) throw new Error('No se pudo guardar el autor.');
                return createdItem;
            } catch (err) {
                const msg = err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('\n') : (err.response?.data?.message || err.message || 'Error al guardar');
                Swal.showValidationMessage(msg);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const createdItem = result.value;
            autoresLocal.value.push(createdItem);
            obraForm.autor_id = createdItem.id;
            darkSwal.fire({ title: 'Autor Creado', icon: 'success', timer: 1500, showConfirmButton: false });
            router.reload({
                only: ['autores'],
                onSuccess: () => {
                    obraForm.autor_id = createdItem.id;
                }
            });
        }
    });
};

const agregarCategoria = () => {
    darkSwal.fire({
        title: 'Agregar nueva categoría',
        html: `
            <div class="text-left">
                <label class="text-xs font-semibold text-zinc-400 block mb-1">Nombre *</label>
                <input id="swal-cat-nombre" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Shonen">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const nombreInput = popup ? popup.querySelector('#swal-cat-nombre') : null;
            const nombre = nombreInput ? nombreInput.value.trim() : '';
            if (!nombre) {
                Swal.showValidationMessage('El nombre es obligatorio');
                return false;
            }
            try {
                const res = await window.axios.post('/catalogo/ajustes/categorias', { nombre }, {
                    headers: { 'Accept': 'application/json' }
                });
                const createdItem = res.data.model || res.data.data;
                if (!createdItem) throw new Error('No se pudo guardar la categoría.');
                return createdItem;
            } catch (err) {
                const msg = err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('\n') : (err.response?.data?.message || err.message || 'Error al guardar');
                Swal.showValidationMessage(msg);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const createdItem = result.value;
            categoriasLocal.value.push(createdItem);
            obraForm.categoria_id = createdItem.id;
            darkSwal.fire({ title: 'Categoría Creada', icon: 'success', timer: 1500, showConfirmButton: false });
            router.reload({
                only: ['categorias'],
                onSuccess: () => {
                    obraForm.categoria_id = createdItem.id;
                }
            });
        }
    });
};

const agregarProveedor = () => {
    darkSwal.fire({
        title: 'Agregar nuevo proveedor',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Nombre de Empresa *</label>
                    <input id="swal-prov-nombre_empresa" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Ivrea">
                </div>
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Email de Contacto</label>
                    <input id="swal-prov-email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="email" placeholder="Ej: contacto@proveedor.com">
                </div>
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Teléfono</label>
                    <input id="swal-prov-telefono" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: 1122334455">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const empInput = popup ? popup.querySelector('#swal-prov-nombre_empresa') : null;
            const emailInput = popup ? popup.querySelector('#swal-prov-email') : null;
            const telInput = popup ? popup.querySelector('#swal-prov-telefono') : null;
            
            const nombre_empresa = empInput ? empInput.value.trim() : '';
            const email = emailInput ? emailInput.value.trim() : '';
            const telefono = telInput ? telInput.value.trim() : '';
            
            if (!nombre_empresa) {
                Swal.showValidationMessage('El nombre de la empresa es obligatorio');
                return false;
            }
            try {
                const res = await window.axios.post('/catalogo/ajustes/proveedores', { nombre_empresa, email, telefono }, {
                    headers: { 'Accept': 'application/json' }
                });
                const createdItem = res.data.model || res.data.data;
                if (!createdItem) throw new Error('No se pudo guardar el proveedor.');
                return createdItem;
            } catch (err) {
                const msg = err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('\n') : (err.response?.data?.message || err.message || 'Error al guardar');
                Swal.showValidationMessage(msg);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const createdItem = result.value;
            proveedoresLocal.value.push(createdItem);
            obraForm.proveedor_id = createdItem.id;
            darkSwal.fire({ title: 'Proveedor Creado', icon: 'success', timer: 1500, showConfirmButton: false });
            router.reload({
                only: ['proveedores'],
                onSuccess: () => {
                    obraForm.proveedor_id = createdItem.id;
                }
            });
        }
    });
};

const agregarIdioma = () => {
    darkSwal.fire({
        title: 'Agregar nuevo idioma',
        html: `
            <div class="text-left">
                <label class="text-xs font-semibold text-zinc-400 block mb-1">Nombre *</label>
                <input id="swal-idioma-nombre" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Japonés">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const nombreInput = popup ? popup.querySelector('#swal-idioma-nombre') : null;
            const nombre = nombreInput ? nombreInput.value.trim() : '';
            if (!nombre) {
                Swal.showValidationMessage('El nombre es obligatorio');
                return false;
            }
            try {
                const res = await window.axios.post('/catalogo/ajustes/idiomas', { nombre }, {
                    headers: { 'Accept': 'application/json' }
                });
                const createdItem = res.data.model || res.data.data;
                if (!createdItem) throw new Error('No se pudo guardar el idioma.');
                return createdItem;
            } catch (err) {
                const msg = err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join('\n') : (err.response?.data?.message || err.message || 'Error al guardar');
                Swal.showValidationMessage(msg);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const createdItem = result.value;
            idiomasLocal.value.push(createdItem);
            obraForm.idioma_id = createdItem.id;
            darkSwal.fire({ title: 'Idioma Creado', icon: 'success', timer: 1500, showConfirmButton: false });
            router.reload({
                only: ['idiomas'],
                onSuccess: () => {
                    obraForm.idioma_id = createdItem.id;
                }
            });
        }
    });
};

const agregarFormato = () => {
    darkSwal.fire({
        title: 'Agregar nuevo formato',
        html: `
            <div class="text-left">
                <label class="text-xs font-semibold text-zinc-400 block mb-1">Nombre del Formato *</label>
                <input id="swal-formato-nombre" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Hardcover">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: () => {
            const val = Swal.getPopup().querySelector('#swal-formato-nombre').value.trim();
            if (!val) {
                Swal.showValidationMessage('El nombre del formato es obligatorio');
                return false;
            }
            return val;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const nuevoFormato = result.value;
            if (!formatosLocal.value.includes(nuevoFormato)) {
                formatosLocal.value.push(nuevoFormato);
            }
            obraForm.formato = nuevoFormato;
            darkSwal.fire({ title: 'Formato Añadido', icon: 'success', timer: 1200, showConfirmButton: false });
        }
    });
};

const submitObra = () => {
    if (isEditingObra.value) {
        obraForm.put(route('obras.update', obraForm.id), {
            preserveScroll: true,
            onSuccess: () => {
                showObraModal.value = false;
                darkSwal.fire({ title: '¡Éxito!', text: 'Producto actualizado correctamente', icon: 'success', timer: 1500, showConfirmButton: false });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                darkSwal.fire({ title: 'Error de validación', text: firstError || 'Verifique los campos requeridos', icon: 'error' });
            }
        });
    } else {
        obraForm.post(route('obras.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showObraModal.value = false;
                obraForm.reset();
                darkSwal.fire({ title: '¡Éxito!', text: 'Producto creado correctamente', icon: 'success', timer: 1500, showConfirmButton: false });
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                darkSwal.fire({ title: 'Error de validación', text: firstError || 'Verifique los campos requeridos', icon: 'error' });
            }
        });
    }
};

const deleteObra = (id) => {
    darkSwal.fire({
        title: '¿Eliminar producto?',
        text: "Esto eliminará el producto y todos sus tomos/variantes asociados.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            obraForm.delete(route('obras.destroy', id));
        }
    });
};

// --- LOGICA DE TOMO (Libro) ---
const showTomoModal = ref(false);
const isEditingTomo = ref(false);
const currentStocks = ref({});

const tomoForm = useForm({
    id: null,
    master_id: null,
    isbn: '',
    numero_tomo: '',
    año_edicion: '',
    cantidad_paginas: '',
    activo: true,
    permite_preventa: false,
    precio_venta: '',
    precio_compra: '',
});

const openTomoModal = (tomo = null, masterId = null) => {
    tomoForm.clearErrors();
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
        tomoForm.precio_compra = currentPrice ? currentPrice.precio_compra : '';
        tomoForm.precio_venta = currentPrice ? currentPrice.precio_venta : '';
        
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
        tomoForm.master_id = masterId;
    }
    showTomoModal.value = true;
};

const submitTomo = () => {
    tomoForm.transform((data) => ({
        ...data,
        isbn: data.isbn && data.isbn.trim() !== '' ? data.isbn.trim() : null,
        numero_tomo: data.numero_tomo && data.numero_tomo.trim() !== '' ? data.numero_tomo.trim() : null,
        año_edicion: data.año_edicion || null,
        cantidad_paginas: data.cantidad_paginas || null,
    }));

    if (isEditingTomo.value) {
        tomoForm.put(route('libros.update', tomoForm.id), {
            onSuccess: () => {
                showTomoModal.value = false;
                darkSwal.fire({ title: '¡Éxito!', text: 'Tomo actualizado correctamente', icon: 'success', timer: 1500, showConfirmButton: false });
            }
        });
    } else {
        tomoForm.post(route('libros.store'), {
            onSuccess: () => {
                showTomoModal.value = false;
                tomoForm.reset();
                darkSwal.fire({ title: '¡Éxito!', text: 'Nuevo tomo registrado correctamente', icon: 'success', timer: 1500, showConfirmButton: false });
            }
        });
    }
};

const deleteTomo = (id) => {
    darkSwal.fire({
        title: '¿Eliminar tomo?',
        text: "Esto eliminará únicamente el tomo específico, la serie se mantendrá",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            tomoForm.delete(route('libros.destroy', id));
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value);
};

const quickEditPrice = async (libro) => {
    document.body.style.cursor = 'wait';
    let historialHtml = '';
    try {
        const res = await fetch(route('precios.historial', libro.id));
        const historial = await res.json();
        
        if (historial && historial.length > 0) {
            historialHtml = historial.map(h => `
                <div class="flex justify-between items-center py-2 border-b border-white/5 last:border-0 text-xs">
                    <div>
                        <span class="font-bold text-white">${formatCurrency(h.precio_venta)}</span>
                        ${h.activo ? '<span class="ml-2 text-[10px] font-semibold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">Actual</span>' : ''}
                        ${h.motivo ? `<p class="text-[11px] text-zinc-400 mt-0.5">${h.motivo}</p>` : ''}
                    </div>
                    <span class="text-[11px] text-zinc-400 font-medium">${new Date(h.fecha_desde).toLocaleDateString('es-AR')}</span>
                </div>
            `).join('');
        } else {
            historialHtml = '<p class="text-zinc-500 text-xs italic text-center py-2">Sin historial registrado</p>';
        }
    } catch (e) {
        console.error("Error al cargar historial", e);
        historialHtml = '<p class="text-rose-400 text-xs italic text-center py-2">Error al cargar historial</p>';
    } finally {
        document.body.style.cursor = 'default';
    }

    const currentPrice = libro.precios?.find(p => p.activo);
    const precioVenta = currentPrice ? parseFloat(currentPrice.precio_venta).toFixed(2) : '';

    darkSwal.fire({
        title: 'Actualizar Precio',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Nuevo Precio de Venta *</label>
                    <div class="relative mt-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-zinc-400 text-base font-bold">$</span>
                        <input id="swal-quick-precio" class="w-full bg-[#131316] border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-base text-white font-bold focus:outline-none focus:border-white/30" type="number" step="0.01" min="0" value="${precioVenta}" placeholder="0.00">
                    </div>
                </div>

                <div class="border-t border-white/10 pt-4">
                    <label class="block text-xs font-semibold text-zinc-400 mb-2">Historial de Precios</label>
                    <div class="max-h-40 overflow-y-auto space-y-1 bg-[#131316] p-3 rounded-xl border border-white/5 pr-2">
                        ${historialHtml}
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const val = Swal.getPopup().querySelector('#swal-quick-precio').value;
            if (!val || val <= 0) {
                Swal.showValidationMessage('El precio debe ser mayor a 0');
                return false;
            }
            return parseFloat(val).toFixed(2);
        }
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('precios.store', libro.id), {
                precio_venta: result.value,
                motivo: 'Actualización rápida desde catálogo'
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({ title: '¡Éxito!', text: 'Precio actualizado correctamente.', icon: 'success', timer: 1200, showConfirmButton: false });
                },
                onError: (errors) => {
                    darkSwal.fire({ title: 'Error', text: Object.values(errors).join('\n') || 'Error al guardar el precio', icon: 'error' });
                }
            });
        }
    });
};

const deshabilitarPreventasMassive = () => {
    darkSwal.fire({
        title: '¿Deshabilitar Preventas?',
        text: "Esto desactivará la preventa de todos los tomos activos inmediatamente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, deshabilitar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.post(route('libros.deshabilitar-preventas'), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({ title: '¡Éxito!', text: 'Todas las preventas han sido deshabilitadas.', icon: 'success', timer: 2000, showConfirmButton: false });
                }
            });
        }
    });
};

const formatSucursalName = (name) => {
    if (!name) return '';
    return name.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
};

const toggleTomoActivo = (libro) => {
    const currentPriceObj = libro.precios?.find(p => p.activo);
    const precioVenta = currentPriceObj ? currentPriceObj.precio_venta : 0;
    const precioCompra = currentPriceObj ? currentPriceObj.precio_compra : 0;

    router.put(route('libros.update', libro.id), {
        master_id: libro.master_id,
        isbn: libro.isbn,
        numero_tomo: libro.numero_tomo,
        año_edicion: libro.año_edicion,
        cantidad_paginas: libro.cantidad_paginas,
        activo: !libro.activo,
        permite_preventa: libro.permite_preventa,
        precio_venta: precioVenta,
        precio_compra: precioCompra,
        only_active_toggle: true,
    }, {
        preserveScroll: true
    });
};

// Formularios y estados para el Aumento Masivo
const showBulkModal = ref(false);
const opcionesMasivasLocal = ref(null);
const loadingOpciones = ref(false);

const openBulkModal = async () => {
    showBulkModal.value = true;
    if (!opcionesMasivasLocal.value) {
        loadingOpciones.value = true;
        try {
            const res = await fetch(route('precios.opciones-masivas'));
            opcionesMasivasLocal.value = await res.json();
        } catch (e) {
            console.error("Error al cargar opciones masivas", e);
            darkSwal.fire({
                title: 'Error',
                text: 'No se pudieron obtener las opciones del catálogo.',
                icon: 'error'
            });
        } finally {
            loadingOpciones.value = false;
        }
    }
};

const bulkForm = useForm({
    criterio: 'proveedor_formato',
    serie: '',
    proveedor: '',
    formato: '',
    libro_id: '',
    categoria_id: '',
    nuevo_precio: ''
});

const searchSerieQuery = ref('');
const showSerieDropdown = ref(false);

const searchProveedorQuery = ref('');
const showProveedorDropdown = ref(false);

const searchLibroQuery = ref('');
const showLibroDropdown = ref(false);

const seriesFiltradas = computed(() => {
    if (!opcionesMasivasLocal.value?.series) return [];
    if (!searchSerieQuery.value) return opcionesMasivasLocal.value.series;
    const q = searchSerieQuery.value.toLowerCase();
    return opcionesMasivasLocal.value.series.filter(s => s && s.toLowerCase().includes(q));
});

const proveedoresFiltrados = computed(() => {
    if (!opcionesMasivasLocal.value?.proveedores_formatos) return [];
    const lista = Object.keys(opcionesMasivasLocal.value.proveedores_formatos);
    if (!searchProveedorQuery.value) return lista;
    const q = searchProveedorQuery.value.toLowerCase();
    return lista.filter(p => p && p.toLowerCase().includes(q));
});

const formatosDisponibles = computed(() => {
    if (!bulkForm.proveedor || !opcionesMasivasLocal.value?.proveedores_formatos) return [];
    return opcionesMasivasLocal.value.proveedores_formatos[bulkForm.proveedor] || [];
});

const librosFiltrados = computed(() => {
    if (!opcionesMasivasLocal.value?.libros) return [];
    if (!searchLibroQuery.value) return opcionesMasivasLocal.value.libros;
    const q = searchLibroQuery.value.toLowerCase();
    return opcionesMasivasLocal.value.libros.filter(l => 
        (l.titulo && l.titulo.toLowerCase().includes(q)) || 
        (l.isbn && l.isbn.toLowerCase().includes(q))
    );
});

watch(() => bulkForm.criterio, () => {
    bulkForm.categoria_id = '';
    bulkForm.serie = '';
    bulkForm.proveedor = '';
    bulkForm.formato = '';
    bulkForm.libro_id = '';
    searchSerieQuery.value = '';
    searchProveedorQuery.value = '';
    searchLibroQuery.value = '';
});

watch(() => bulkForm.proveedor, () => {
    bulkForm.formato = '';
});

const submitBulk = () => {
    if (bulkForm.criterio === 'proveedor_formato') {
        bulkForm.proveedor = bulkForm.proveedor || searchProveedorQuery.value;
    } else if (bulkForm.criterio === 'serie') {
        bulkForm.serie = bulkForm.serie || searchSerieQuery.value;
    }

    if (bulkForm.criterio === 'categoria' && !bulkForm.categoria_id) {
        darkSwal.fire({ title: 'Atención', text: 'Seleccioná una categoría', icon: 'warning' });
        return;
    }
    if (bulkForm.criterio === 'proveedor_formato' && !bulkForm.proveedor) {
        darkSwal.fire({ title: 'Atención', text: 'Seleccioná al menos un proveedor', icon: 'warning' });
        return;
    }
    if (bulkForm.criterio === 'serie' && !bulkForm.serie) {
        darkSwal.fire({ title: 'Atención', text: 'Seleccioná un producto o serie', icon: 'warning' });
        return;
    }
    if (bulkForm.criterio === 'libro_individual' && !bulkForm.libro_id) {
        darkSwal.fire({ title: 'Atención', text: 'Seleccioná un producto individual', icon: 'warning' });
        return;
    }
    if (!bulkForm.nuevo_precio || bulkForm.nuevo_precio <= 0) {
        darkSwal.fire({ title: 'Atención', text: 'Ingresá un precio válido mayor a 0', icon: 'warning' });
        return;
    }

    bulkForm.post(route('precios.bulk'), {
        onSuccess: () => {
            showBulkModal.value = false;
            bulkForm.reset();
            darkSwal.fire({
                title: '¡Actualización Exitosa!',
                text: 'Precios masivos aplicados en el catálogo',
                icon: 'success',
            });
        },
        onError: (errores) => {
            console.error(errores);
            darkSwal.fire({
                title: 'Error de servidor',
                text: 'Revisá los datos ingresados',
                icon: 'error',
            });
        }
    });
};
</script>

<template>
    <Head title="Catálogo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-catalogo">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">CATÁLOGO DE PRODUCTOS</h2>
                </div>
                <div class="flex items-center gap-3">
                    <button 
                        @click="deshabilitarPreventasMassive" 
                        class="px-4 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 font-semibold text-xs border border-amber-500/20 transition-all flex items-center gap-2 active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        <span>Deshabilitar Preventas</span>
                    </button>
                    <button 
                        v-if="$page.props.auth.esAdmin" 
                        @click="openBulkModal" 
                        class="px-4 py-2 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 font-semibold text-xs border border-blue-500/20 transition-all flex items-center gap-2 active:scale-95"
                    >
                        <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Aumento Masivo</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 page-catalogo">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Search & Add Bar -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por título de producto, autor o ISBN" 
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                        >
                    </div>
                    <div class="flex items-center gap-4">
                        <button 
                            @click="openObraModal()" 
                            class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Nuevo Producto</span>
                        </button>
                    </div>
                </div>

                <!-- Products Table Card -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                    <th class="p-4">Producto / Título</th>
                                    <th class="p-4 text-center">Autor</th>
                                    <th class="p-4 text-center">Ítems / Tomos</th>
                                    <th class="p-4 text-center">Proveedor</th>
                                    <th class="p-4 text-center w-36">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <template v-for="obra in filteredObras" :key="obra.id">
                                    <tr 
                                        @click="toggleMaster(obra.id)" 
                                        class="hover:bg-white/[0.02] transition-colors cursor-pointer group"
                                        :class="expandedMasters.includes(obra.id) ? 'bg-black/20' : ''"
                                    >
                                        <td class="p-4">
                                            <div class="font-bold text-base text-white tracking-tight">{{ obra.titulo }}</div>
                                            <div class="text-xs text-zinc-400 font-medium mt-1 flex items-center gap-2">
                                                <template v-for="(tag, idx) in [obra.categoria?.nombre, obra.formato, obra.idioma?.nombre].filter(Boolean)" :key="idx">
                                                    <span v-if="idx > 0" class="w-1 h-1 rounded-full bg-white/20"></span>
                                                    <span>{{ tag }}</span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="text-xs text-zinc-300 font-medium">{{ obra.autor ? (obra.autor.nombre + (obra.autor.apellido ? ' ' + obra.autor.apellido : '')) : '-' }}</div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="bg-white/5 text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-semibold border border-white/5">{{ obra.libros ? obra.libros.length : 0 }}</span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="text-xs font-semibold text-zinc-300 uppercase">{{ obra.proveedor ? obra.proveedor.nombre_empresa : '-' }}</span>
                                        </td>
                                        <td class="p-4 text-center w-36">
                                            <div class="flex justify-center gap-1 items-center">
                                                <button @click.stop="openObraModal(obra)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Editar Obra">
                                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                                </button>
                                                <button @click.stop="deleteObra(obra.id)" class="p-2 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" title="Eliminar Obra">
                                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                </button>
                                                <svg 
                                                    class="w-5 h-5 ml-1 text-zinc-500 transition-transform duration-300"
                                                    :class="{'rotate-180 text-white': expandedMasters.includes(obra.id)}" 
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Detail Row -->
                                    <tr v-show="expandedMasters.includes(obra.id)" class="bg-[#0d0d0f]/60">
                                        <td colspan="5" class="p-0 border-b border-white/5">
                                            <div class="p-4 pl-12 border-l-2 border-white/20 relative">
                                                <div class="flex justify-end items-center mb-3">
                                                    <button @click.stop="openTomoModal(null, obra.id)" class="text-xs bg-white/5 hover:bg-white/10 text-white transition-all px-3 py-1.5 rounded-xl font-semibold flex items-center gap-1.5 border border-white/10">
                                                        <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                                        Añadir Ítem / Tomo
                                                    </button>
                                                </div>

                                                <table class="w-full text-left border-collapse" v-if="obra.libros && obra.libros.length > 0">
                                                    <thead>
                                                        <tr class="text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                                            <th class="pb-2">N° Tomo / Variante</th>
                                                            <th class="pb-2">ISBN / Código</th>
                                                            <th class="pb-2 text-center">Precio</th>
                                                            <th class="pb-2 text-center w-36">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="libro in obra.libros" :key="libro.id" class="hover:bg-white/[0.02] border-b border-white/5 last:border-0 transition-opacity" :class="[!libro.activo ? 'opacity-40' : '']">
                                                            <td class="py-3 pr-4">
                                                                <div class="text-xs font-bold text-white tracking-tight">{{ formatTomoDisplay(libro.numero_tomo) }}</div>
                                                            </td>
                                                            <td class="py-3 pr-4">
                                                                <span class="font-mono text-xs text-zinc-400">{{ libro.isbn || '-' }}</span>
                                                            </td>
                                                            <td class="py-3 px-4 text-center">
                                                                <div v-if="libro.precios && libro.precios.find(p => p.activo)" class="text-sm font-bold text-white">
                                                                    {{ formatCurrency(libro.precios.find(p => p.activo).precio_venta) }}
                                                                </div>
                                                                <div v-else class="text-xs font-semibold text-rose-400 opacity-60 italic">Sin Precio</div>
                                                            </td>
                                                            <td class="py-3 text-center w-36">
                                                                <div class="flex justify-center items-center gap-1">
                                                                    <!-- Visibility Switch -->
                                                                    <button 
                                                                        @click.stop="toggleTomoActivo(libro)" 
                                                                        class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none align-middle mr-2"
                                                                        :class="libro.activo ? 'bg-emerald-500' : 'bg-zinc-700'"
                                                                        :title="libro.activo ? 'Ítem visible (Click para ocultar)' : 'Ítem oculto (Click para mostrar)'"
                                                                    >
                                                                        <span 
                                                                            class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                                            :class="libro.activo ? 'translate-x-3' : 'translate-x-0'"
                                                                        />
                                                                    </button>
                                                                    <button @click.stop="quickEditPrice(libro)" class="p-1.5 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Actualizar Precio">
                                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                    </button>
                                                                    <button @click.stop="openTomoModal(libro, obra.id)" class="p-1.5 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Editar Ítem">
                                                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>
                                                                    </button>
                                                                    <!-- Conditional Trash Button -->
                                                                    <button 
                                                                        v-if="libro.tiene_historial"
                                                                        class="p-1.5 text-zinc-700 cursor-not-allowed" 
                                                                        title="No se puede eliminar porque tiene historial de movimientos"
                                                                        @click.stop
                                                                    >
                                                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                                    </button>
                                                                    <button 
                                                                        v-else
                                                                        @click.stop="deleteTomo(libro.id)" 
                                                                        class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-xl transition-all" 
                                                                        title="Eliminar Ítem"
                                                                    >
                                                                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <div v-else class="text-zinc-500 text-xs italic py-4 text-center border-t border-white/5">
                                                    Este producto aún no tiene ítems/unidades registradas.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="filteredObras.length === 0">
                                    <td colspan="5" class="p-12 text-center text-zinc-500 italic">No se encontraron productos registrados en el catálogo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal PRODUCTO MÁSTER -->
        <Teleport to="body">
            <template v-if="showObraModal">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showObraModal = false"></div>
                <div class="fixed inset-0 z-[101] overflow-y-auto page-catalogo">
                    <div class="flex min-h-full items-start justify-center p-4">
                        <div class="relative w-full max-w-xl bg-[#0d0d0f] border border-white/10 shadow-2xl overflow-hidden rounded-2xl my-8">
                            <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider"> 
                                    {{ isEditingObra ? 'Editar' : 'Nuevo' }} Producto
                                </h3>
                                <button @click="showObraModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            
                            <form @submit.prevent="submitObra" class="p-6">
                                <div class="grid grid-cols-1 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Nombre / Título del Producto *</label>
                                        <input v-model="obraForm.titulo" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-white/30 font-medium" placeholder="Nombre del producto" required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Autor</label>
                                        <div class="flex gap-2">
                                            <SearchableSelect v-model="obraForm.autor_id" :options="autoresLocal" :labelKey="(a) => a.nombre + (a.apellido ? ' ' + a.apellido : '')" placeholder="Seleccionar autor" :required="false" />
                                            <button type="button" @click="agregarAutor" class="py-2.5 px-4 bg-white/5 hover:bg-white/10 text-white border border-white/10 rounded-xl font-bold text-sm transition-all">+</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Categoría *</label>
                                        <div class="flex gap-2">
                                            <SearchableSelect v-model="obraForm.categoria_id" :options="categoriasLocal" placeholder="Seleccionar categoría" :required="false" />
                                            <button type="button" @click="agregarCategoria" class="py-2.5 px-4 bg-white/5 hover:bg-white/10 text-white border border-white/10 rounded-xl font-bold text-sm transition-all">+</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Proveedor / Marca</label>
                                        <div class="flex gap-2">
                                            <SearchableSelect v-model="obraForm.proveedor_id" :options="mappedProveedores" placeholder="Seleccionar proveedor" :required="false" />
                                            <button type="button" @click="agregarProveedor" class="py-2.5 px-4 bg-white/5 hover:bg-white/10 text-white border border-white/10 rounded-xl font-bold text-sm transition-all">+</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Idioma</label>
                                        <div class="flex gap-2">
                                            <SearchableSelect v-model="obraForm.idioma_id" :options="idiomasLocal" placeholder="Seleccionar idioma" :required="false" />
                                            <button type="button" @click="agregarIdioma" class="py-2.5 px-4 bg-white/5 hover:bg-white/10 text-white border border-white/10 rounded-xl font-bold text-sm transition-all">+</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Formato</label>
                                        <div class="flex gap-2">
                                            <SearchableSelect v-model="obraForm.formato" :options="formatosLocal" placeholder="Seleccionar formato" :required="false" />
                                            <button type="button" @click="agregarFormato" class="py-2.5 px-4 bg-white/5 hover:bg-white/10 text-white border border-white/10 rounded-xl font-bold text-sm transition-all">+</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Descripción / Detalles</label>
                                        <textarea v-model="obraForm.synopsis" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-white/30 font-medium h-24 resize-none" placeholder="Descripción del producto"></textarea>
                                    </div>
                                    <div class="flex items-center gap-3 mt-2">
                                        <input type="checkbox" v-model="obraForm.activo" id="obra_activa" class="rounded border-white/20 bg-[#131316] text-emerald-500 focus:ring-emerald-500 h-4 w-4">
                                        <label for="obra_activa" class="text-xs font-semibold text-white cursor-pointer select-none">Producto Activo en Catálogo</label>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                    <button type="button" @click="showObraModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                    <button type="submit" :disabled="obraForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                       <span>{{ obraForm.processing ? 'PROCESANDO...' : (isEditingObra ? 'ACTUALIZAR' : 'GUARDAR PRODUCTO') }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </Teleport>

        <!-- Modal TOMO / ÍTEM -->
        <Teleport to="body">
            <template v-if="showTomoModal">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showTomoModal = false"></div>
                <div class="fixed inset-0 z-[101] overflow-y-auto page-catalogo">
                    <div class="flex min-h-full items-start justify-center p-4">
                        <div class="relative w-full max-w-xl bg-[#0d0d0f] border border-white/10 shadow-2xl overflow-hidden rounded-2xl my-8">
                            <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                    {{ isEditingTomo ? 'Editar' : 'Añadir' }} Ítem / Tomo
                                </h3>
                                <button @click="showTomoModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            
                            <form @submit.prevent="submitTomo" class="p-6 space-y-4">
                                <input type="hidden" v-model="tomoForm.master_id">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Volumen / N° Tomo / Variante</label>
                                        <input v-model="tomoForm.numero_tomo" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="Número o variante">
                                        <div v-if="tomoForm.errors.numero_tomo" class="mt-2 text-rose-400 text-xs font-semibold">
                                            {{ tomoForm.errors.numero_tomo }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">ISBN / Código de Barras</label>
                                        <input v-model="tomoForm.isbn" type="text" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-mono focus:outline-none focus:border-white/30" placeholder="ISBN o Código EAN">
                                        <div v-if="tomoForm.errors.isbn" class="mt-2 text-rose-400 text-xs font-semibold">
                                            {{ tomoForm.errors.isbn }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">Año Edición</label>
                                        <input v-model="tomoForm.año_edicion" type="number" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="Año">
                                        <div v-if="tomoForm.errors.año_edicion" class="mt-2 text-rose-400 text-xs font-semibold">
                                            {{ tomoForm.errors.año_edicion }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">N° Páginas</label>
                                        <input v-model="tomoForm.cantidad_paginas" type="number" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" placeholder="Cantidad">
                                        <div v-if="tomoForm.errors.cantidad_paginas" class="mt-2 text-rose-400 text-xs font-semibold">
                                            {{ tomoForm.errors.cantidad_paginas }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <label class="flex items-center gap-3 cursor-pointer select-none">
                                        <input type="checkbox" v-model="tomoForm.activo" class="rounded border-white/20 bg-[#131316] text-emerald-500 focus:ring-emerald-500 h-4 w-4">
                                        <span class="text-xs font-semibold text-white">Tomo Activo</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer select-none">
                                        <input type="checkbox" v-model="tomoForm.permite_preventa" class="rounded border-white/20 bg-[#131316] text-emerald-500 focus:ring-emerald-500 h-4 w-4">
                                        <span class="text-xs font-semibold text-white">Habilitar Preventa</span>
                                    </label>
                                </div>

                                <div v-if="!isEditingTomo" class="bg-white/[0.02] border border-white/5 p-4 rounded-xl">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Precio de Venta Inicial *</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-zinc-400 font-bold">$</span>
                                        <input v-model="tomoForm.precio_venta" type="number" step="0.01" min="0" @focus="$event.target.select()" @focusin="tomoForm.precio_venta == 0 ? (tomoForm.precio_venta = '') : null" class="w-full bg-[#131316] border border-white/10 rounded-xl pl-8 pr-4 py-2.5 text-base text-white font-bold text-right focus:outline-none focus:border-white/30" placeholder="0.00" required />
                                    </div>
                                    <div v-if="tomoForm.errors.precio_venta" class="mt-2 text-rose-400 text-xs font-semibold">
                                        {{ tomoForm.errors.precio_venta }}
                                    </div>
                                </div>

                                <div v-if="isEditingTomo" class="bg-white/[0.02] border border-white/5 p-4 rounded-xl space-y-2">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-2">Stock Disponible por Sucursal</label>
                                    <div class="grid grid-cols-1 gap-2">
                                        <div v-for="sucursal in sucursales" :key="sucursal.id" class="flex items-center justify-between px-3 py-2 bg-[#131316] border border-white/5 rounded-xl">
                                            <span class="text-xs font-semibold text-zinc-300">{{ formatSucursalName(sucursal.nombre) }}</span>
                                            <span class="text-white font-bold text-xs bg-white/5 px-2.5 py-1 rounded-lg border border-white/5">{{ currentStocks[sucursal.id] || 0 }} <span class="text-[10px] text-zinc-500 font-normal">uds</span></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                    <button type="button" @click="showTomoModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                    <button type="submit" :disabled="tomoForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                       <span>{{ tomoForm.processing ? 'PROCESANDO...' : (isEditingTomo ? 'ACTUALIZAR TOMO' : 'REGISTRAR TOMO') }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </Teleport>

        <!-- Modal Aumento Masivo -->
        <Teleport to="body">
            <template v-if="showBulkModal">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showBulkModal = false"></div>
                <div class="fixed inset-0 z-[101] overflow-y-auto page-catalogo">
                    <div class="flex min-h-full items-start justify-center p-4">
                        <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 shadow-2xl overflow-hidden rounded-2xl my-8">
                            <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Aumento Masivo</h3>
                                <button @click="showBulkModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            
                            <div v-if="loadingOpciones" class="p-12 text-center flex flex-col items-center justify-center gap-3">
                                <div class="w-8 h-8 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                <p class="text-xs font-semibold text-zinc-400">Cargando opciones del catálogo</p>
                            </div>
                            <form v-else @submit.prevent="submitBulk" class="p-6 space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Criterio de Aumento</label>
                                    <select v-model="bulkForm.criterio" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30">
                                        <option value="proveedor_formato">Por Proveedor y Formato</option>
                                        <option value="serie">Por Producto / Serie</option>
                                        <option value="libro_individual">Por Ítem / Producto Individual</option>
                                    </select>
                                </div>

                                <div v-if="bulkForm.criterio === 'proveedor_formato'" class="grid grid-cols-2 gap-4 bg-white/[0.02] p-4 rounded-xl border border-white/5">
                                    <div class="relative">
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">1. Proveedor *</label>
                                        <div class="relative">
                                            <input v-model="searchProveedorQuery" @focus="showProveedorDropdown = true" type="text" placeholder="Buscar" class="w-full bg-[#131316] border border-white/10 rounded-xl pl-4 pr-10 py-2.5 text-sm text-white focus:outline-none focus:border-white/30 font-medium relative z-50" />
                                            <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none z-[51]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                        
                                        <div v-if="showProveedorDropdown" class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                            <div v-for="e in proveedoresFiltrados" :key="e" @mousedown.prevent="bulkForm.proveedor = e; searchProveedorQuery = e; showProveedorDropdown = false" class="px-4 py-2.5 text-xs text-zinc-300 cursor-pointer hover:bg-white/10 hover:text-white transition-colors border-b border-white/5 last:border-0 font-medium" :class="bulkForm.proveedor === e ? 'bg-white/10 text-white font-bold' : ''">
                                                {{ e }}
                                            </div>
                                            <div v-if="proveedoresFiltrados.length === 0" class="px-4 py-3 text-xs text-zinc-500 italic text-center">No hay resultados</div>
                                        </div>
                                        <div v-if="showProveedorDropdown" class="fixed inset-0 z-40" @click="showProveedorDropdown = false"></div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-zinc-400 mb-1">2. Formato</label>
                                        <select v-model="bulkForm.formato" :disabled="!bulkForm.proveedor" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-white/30 font-medium transition-opacity" :class="!bulkForm.proveedor ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''">
                                            <option value="">Todos los formatos</option>
                                            <option v-for="f in formatosDisponibles" :key="f" :value="f">{{ f }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div v-if="bulkForm.criterio === 'serie'" class="relative bg-white/[0.02] p-4 rounded-xl border border-white/5">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Seleccionar Producto / Serie *</label>
                                    <div class="relative">
                                        <input v-model="searchSerieQuery" @focus="showSerieDropdown = true" type="text" placeholder="Buscar producto o serie" class="w-full bg-[#131316] border border-white/10 rounded-xl pl-4 pr-10 py-2.5 text-sm text-white focus:outline-none focus:border-white/30 font-medium relative z-50" />
                                        <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none z-[51]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                    
                                    <div v-if="showSerieDropdown" class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                        <div v-for="s in seriesFiltradas" :key="s" @mousedown.prevent="bulkForm.serie = s; searchSerieQuery = s; showSerieDropdown = false" class="px-4 py-2.5 text-xs text-zinc-300 cursor-pointer hover:bg-white/10 hover:text-white transition-colors border-b border-white/5 last:border-0 font-medium" :class="bulkForm.serie === s ? 'bg-white/10 text-white font-bold' : ''">
                                            {{ s }}
                                        </div>
                                        <div v-if="seriesFiltradas.length === 0" class="px-4 py-3 text-xs text-zinc-500 italic text-center">No hay resultados</div>
                                    </div>
                                    <div v-if="showSerieDropdown" class="fixed inset-0 z-40" @click="showSerieDropdown = false"></div>
                                </div>

                                <div v-if="bulkForm.criterio === 'libro_individual'" class="relative bg-white/[0.02] p-4 rounded-xl border border-white/5">
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Seleccionar Ítem Individual *</label>
                                    <div class="relative">
                                        <input v-model="searchLibroQuery" @focus="showLibroDropdown = true" type="text" placeholder="Buscar ítem por título o ISBN" class="w-full bg-[#131316] border border-white/10 rounded-xl pl-4 pr-10 py-2.5 text-sm text-white focus:outline-none focus:border-white/30 font-medium relative z-50" />
                                        <svg class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none z-[51]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                    
                                    <div v-if="showLibroDropdown" class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-xl max-h-48 overflow-y-auto shadow-2xl">
                                        <div v-for="l in librosFiltrados" :key="l.id" @mousedown.prevent="bulkForm.libro_id = l.id; searchLibroQuery = l.titulo; showLibroDropdown = false" class="px-4 py-2.5 text-xs text-zinc-300 cursor-pointer hover:bg-white/10 hover:text-white transition-colors border-b border-white/5 last:border-0 font-medium" :class="bulkForm.libro_id === l.id ? 'bg-white/10 text-white font-bold' : ''">
                                            {{ l.titulo }}
                                        </div>
                                        <div v-if="librosFiltrados.length === 0" class="px-4 py-3 text-xs text-zinc-500 italic text-center">No hay resultados</div>
                                    </div>
                                    <div v-if="showLibroDropdown" class="fixed inset-0 z-40" @click="showLibroDropdown = false"></div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-400 mb-1">Nuevo Precio *</label>
                                    <div class="relative mt-1">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-zinc-400 text-sm font-bold">$</span>
                                        <input v-model="bulkForm.nuevo_precio" type="number" step="0.01" min="0" class="w-full bg-[#131316] border border-white/10 rounded-xl pl-8 pr-4 py-2.5 text-sm text-white font-bold focus:outline-none focus:border-white/30" placeholder="0.00" required />
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                    <button type="button" @click="showBulkModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                    <button type="submit" :disabled="bulkForm.processing" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95">
                                        <span>{{ bulkForm.processing ? 'APLICANDO...' : 'APLICAR A TODOS' }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </Teleport>

    </AuthenticatedLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-catalogo,
.page-catalogo * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
