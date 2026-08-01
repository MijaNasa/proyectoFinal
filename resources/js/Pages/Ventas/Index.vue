<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';
import DireccionAutocomplete from '@/Components/DireccionAutocomplete.vue';

const props = defineProps({
    ventas: Object,
    sucursales: Array,
    stats: Object,
    filters: Object
});

const page = usePage();
const puedeEditarEstado = computed(() =>
    page.props.auth?.esAdmin || page.props.auth?.esGerente ||
    page.props.auth?.permisos?.includes('ventas.acceder')
);

const estadoOpciones = [
    { value: 'en_preventa',        label: 'Esperando preventa', tipos: ['online'] },
    { value: 'pendiente_pago',     label: 'Pendiente de pago',  tipos: ['online', 'presencial'] },
    { value: 'esperando_traslado', label: 'Esperando traslado entre sucursales', tipos: ['online'] },
    { value: 'en_preparacion',     label: 'Envío en preparación',     tipos: ['online'] },
    { value: 'listo_para_retiro',  label: 'Listo para retirar', tipos: ['online'] },
    { value: 'acumulado',          label: 'Acumulado',          tipos: ['online'] },
    { value: 'enviado',            label: 'Enviado',            tipos: ['online'] },
    { value: 'finalizado',         label: 'Finalizado',         tipos: ['online', 'presencial'] },
    { value: 'cancelado',          label: 'Cancelado',          tipos: ['online', 'presencial'] },
];

const estadoOpcionesFiltradas = computed(() => {
    if (!selectedVenta.value) return estadoOpciones;
    return estadoOpciones.filter(e => {
        if (!e.tipos.includes(selectedVenta.value.tipo)) return false;
        return true;
    });
});

const estadoDots = {
    en_preventa:        'bg-fuchsia-400',
    pendiente_pago:     'bg-amber-400',
    esperando_traslado: 'bg-purple-400',
    en_preparacion:     'bg-blue-400',
    listo_para_retiro:  'bg-emerald-400',
    acumulado:          'bg-orange-400',
    enviado:            'bg-indigo-400',
    finalizado:         'bg-emerald-400',
    cancelado:          'bg-rose-500',
};

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-ventas',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const estadoForm = useForm({ estado: '', direccion_envio: null, latitud: null, longitud: null, tracking_code: null });

const onSeleccionarDireccionVenta = (f) => {
    const [lon, lat] = f.geometry?.coordinates ?? [];
    estadoForm.latitud  = lat ?? null;
    estadoForm.longitud = lon ?? null;
};

const search = ref(props.filters.search || '');
const showPosModal = ref(false);

const showEstadoDropdown = ref(false);
const selectedEstados = ref(props.filters.estados || []);

const toggleEstadoDropdown = () => {
    showEstadoDropdown.value = !showEstadoDropdown.value;
};

const clearEstados = () => {
    selectedEstados.value = [];
    showEstadoDropdown.value = false;
    handleSearch();
};

const closeDropdownsOnClickOutside = (event) => {
    const container = document.getElementById('estado-filter-dropdown-container');
    if (container && !container.contains(event.target)) {
        showEstadoDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdownsOnClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdownsOnClickOutside);
});

const showDetailModal = ref(false);
const selectedVenta = ref(null);
const expandedVentas = ref([]);

const urlParams = new URLSearchParams(window.location.search);
const currentTab = ref(urlParams.get('tab') || 'activas');

const toggleExpand = (id) => {
    if (expandedVentas.value.includes(id)) {
        expandedVentas.value = expandedVentas.value.filter(v => v !== id);
    } else {
        expandedVentas.value.push(id);
    }
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        handleSearch();
    }, 300);
});

const posForm = useForm({
    cliente_id: '',
    sucursal_id: page.props.auth.empleado?.sucursal_id || '',
    tipo: 'presencial',
    medio_pago: 'Efectivo',
    requiere_envio: false,
    destinatario_envio: '',
    telefono_envio: '',
    calle_numero_envio: '',
    piso_depto_envio: '',
    motivo_pendiente: null,
    origen: 'presencial',
    es_excepcional: false,
    acumular_pedido: false,
    guardar_pendiente: false,
    tipo_envio: 'retiro',
    metodo_pago_excedente: null,
    items: []
});

// --- Buscador de clientes (AJAX) ---
const clienteSearch = ref('');
const clienteSeleccionado = ref(null);
const clientesResults = ref([]);
const showClienteDropdown = ref(false);
let clienteSearchTimer = null;

const buscarClientes = (query) => {
    clearTimeout(clienteSearchTimer);
    if (!query || query.length < 1) {
        clientesResults.value = [];
        return;
    }
    clienteSearchTimer = setTimeout(async () => {
        try {
            const res = await window.axios.get(route('clientes.buscar', { q: query }));
            clientesResults.value = res.data;
        } catch (e) {
            console.error('Error al buscar clientes:', e);
        }
    }, 250);
};

const seleccionarCliente = (c) => {
    clienteSeleccionado.value = c;
    posForm.cliente_id = c.id;
    clienteSearch.value = `${c.user?.name || ''} ${c.user?.apellido || ''}`.trim();
    showClienteDropdown.value = false;
};

const limpiarCliente = () => {
    clienteSeleccionado.value = null;
    posForm.cliente_id = '';
    clienteSearch.value = '';
    clientesResults.value = [];
};

const crearClienteRapido = () => {
    darkSwal.fire({
        title: 'Crear Cliente Rápido',
        html: `
            <div class="space-y-4 text-left">
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Nombre *</label>
                    <input id="swal-cli-nombre" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Juan">
                </div>
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Apellido *</label>
                    <input id="swal-cli-apellido" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: Pérez">
                </div>
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">Email *</label>
                    <input id="swal-cli-email" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="email" placeholder="Ej: cliente@email.com">
                </div>
                <div>
                    <label class="text-xs font-semibold text-zinc-400 block mb-1">DNI / CUIT *</label>
                    <input id="swal-cli-dni" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30" type="text" placeholder="Ej: 38123456">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Crear Cliente',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusConfirm: false,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const nombre = popup.querySelector('#swal-cli-nombre')?.value.trim();
            const apellido = popup.querySelector('#swal-cli-apellido')?.value.trim();
            const email = popup.querySelector('#swal-cli-email')?.value.trim();
            const dni = popup.querySelector('#swal-cli-dni')?.value.trim();

            if (!nombre || !apellido || !email || !dni) {
                Swal.showValidationMessage('Todos los campos son obligatorios');
                return false;
            }

            try {
                const res = await window.axios.post(route('clientes.store-rapido'), {
                    nombre, apellido, email, dni
                }, { headers: { 'Accept': 'application/json' } });

                return res.data.cliente;
            } catch (err) {
                let msg = 'Error al crear cliente';
                if (err.response?.data?.errors) {
                    const errors = Object.values(err.response.data.errors).flat();
                    if (errors.length > 1) {
                        msg = '<div class="text-left space-y-1"><div>• ' + errors.join('</div><div>• ') + '</div></div>';
                    } else {
                        msg = errors[0];
                    }
                } else if (err.response?.data?.message) {
                    msg = err.response.data.message;
                }
                Swal.showValidationMessage(msg);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            seleccionarCliente(result.value);
            darkSwal.fire({ title: '¡Cliente Creado!', text: 'Seleccionado automáticamente.', icon: 'success', timer: 1200, showConfirmButton: false });
        }
    });
};

// --- Buscador de libros para POS ---
const libroSearch = ref('');
const librosResults = ref([]);
const showLibroDropdown = ref(false);
const libroSeleccionado = ref(null);
const cantidadSeleccionada = ref(1);
let libroSearchTimer = null;

const buscarLibros = (query) => {
    clearTimeout(libroSearchTimer);
    if (!query || query.length < 1) {
        librosResults.value = [];
        return;
    }
    libroSearchTimer = setTimeout(async () => {
        try {
            const res = await window.axios.get(route('ventas.search-libros'), {
                params: { q: query, sucursal_id: posForm.sucursal_id }
            });
            librosResults.value = res.data;
        } catch (e) {
            console.error('Error al buscar libros:', e);
        }
    }, 200);
};

const seleccionarLibroParaAgregar = (libro) => {
    libroSeleccionado.value = libro;
    cantidadSeleccionada.value = 1;
    showLibroDropdown.value = false;
    libroSearch.value = '';
    librosResults.value = [];
};

const incrementarSeleccion = () => {
    if (!libroSeleccionado.value) return;
    const stock = libroSeleccionado.value.stock_disponible || 0;
    if (stock <= 0 && libroSeleccionado.value.permite_preventa) {
        cantidadSeleccionada.value++;
        return;
    }
    if (cantidadSeleccionada.value < stock) {
        cantidadSeleccionada.value++;
    } else {
        darkSwal.fire({
            title: 'Límite de stock',
            text: `Solo hay ${stock} unidad(es) disponible(s).`,
            icon: 'warning'
        });
    }
};

const validarCantidadParaAgregar = () => {
    if (!libroSeleccionado.value) return;
    const stock = libroSeleccionado.value.stock_disponible || 0;
    if (stock <= 0 && libroSeleccionado.value.permite_preventa) {
        if (cantidadSeleccionada.value < 1) cantidadSeleccionada.value = 1;
        return;
    }
    if (cantidadSeleccionada.value > stock) {
        cantidadSeleccionada.value = stock > 0 ? stock : 1;
        darkSwal.fire({
            title: 'Límite de stock',
            text: `Máximo disponible: ${stock} unidad(es).`,
            icon: 'warning'
        });
    }
    if (cantidadSeleccionada.value < 1) cantidadSeleccionada.value = 1;
};

const confirmarAgregarAlCarrito = () => {
    if (!libroSeleccionado.value) return;
    const l = libroSeleccionado.value;
    const cant = cantidadSeleccionada.value;

    const existingIdx = posForm.items.findIndex(i => i.libro_id === l.id);
    const precioUnitario = l.precio_actual ? parseFloat(l.precio_actual.precio_venta) : 0;
    const tituloCompleto = l.master?.titulo + (l.numero_tomo ? ' - Tomo ' + l.numero_tomo : '');

    if (existingIdx !== -1) {
        const nuevaCant = posForm.items[existingIdx].cantidad + cant;
        const stock = l.stock_disponible || 0;
        if (stock <= 0 && l.permite_preventa) {
            posForm.items[existingIdx].cantidad = nuevaCant;
        } else if (nuevaCant > stock) {
            posForm.items[existingIdx].cantidad = stock;
            darkSwal.fire({
                title: 'Stock insuficiente',
                text: `Se ajustó la cantidad al máximo disponible (${stock}).`,
                icon: 'warning'
            });
        } else {
            posForm.items[existingIdx].cantidad = nuevaCant;
        }
    } else {
        posForm.items.push({
            libro_id: l.id,
            cantidad: cant,
            precio: precioUnitario,
            titulo: tituloCompleto,
            stock_disponible: l.stock_disponible,
            permite_preventa: l.permite_preventa
        });
    }

    libroSeleccionado.value = null;
    cantidadSeleccionada.value = 1;
};

const simularEscaneo = () => {
    darkSwal.fire({
        title: 'Lector de Código de Barras',
        text: 'Escanear el código ISBN con la pistola lectora o ingresarlo manualmente:',
        input: 'text',
        inputPlaceholder: 'Ej: 9789500765432',
        showCancelButton: true,
        confirmButtonText: 'Buscar y Agregar',
        cancelButtonText: 'Cancelar',
        preConfirm: async (isbn) => {
            if (!isbn || !isbn.trim()) {
                Swal.showValidationMessage('Ingresá un código ISBN');
                return false;
            }
            try {
                const res = await window.axios.get(route('libros.buscar'), {
                    params: { q: isbn.trim(), sucursal_id: posForm.sucursal_id }
                });
                if (!res.data || res.data.length === 0) {
                    Swal.showValidationMessage(`No se encontró ningún producto con ISBN ${isbn}`);
                    return false;
                }
                return res.data[0];
            } catch (e) {
                Swal.showValidationMessage('Error al buscar el producto');
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const libroEncontrado = result.value;
            if (libroEncontrado.stock_disponible <= 0 && !libroEncontrado.permite_preventa) {
                darkSwal.fire({
                    title: 'Producto Agotado',
                    text: `El producto "${libroEncontrado.master?.titulo}" no tiene stock disponible.`,
                    icon: 'error'
                });
                return;
            }
            seleccionarLibroParaAgregar(libroEncontrado);
            confirmarAgregarAlCarrito();
        }
    });
};

const incrementarItemCarrito = (item) => {
    const stock = item.stock_disponible || 0;
    if (stock <= 0 && item.permite_preventa) {
        item.cantidad++;
        return;
    }
    if (item.cantidad < stock) {
        item.cantidad++;
    } else {
        darkSwal.fire({
            title: 'Límite de stock',
            text: `Solo hay ${stock} unidad(es) disponible(s).`,
            icon: 'warning'
        });
    }
};

const validarItemCarrito = (item) => {
    const stock = item.stock_disponible || 0;
    if (stock <= 0 && item.permite_preventa) {
        if (item.cantidad < 1) item.cantidad = 1;
        return;
    }
    if (item.cantidad > stock) {
        item.cantidad = stock > 0 ? stock : 1;
        darkSwal.fire({
            title: 'Límite de stock',
            text: `Ajustado al máximo disponible: ${stock}.`,
            icon: 'warning'
        });
    }
    if (item.cantidad < 1) item.cantidad = 1;
};

const removeItem = (index) => {
    posForm.items.splice(index, 1);
};

const subtotalPos = computed(() => {
    return posForm.items.reduce((acc, item) => acc + (item.cantidad * item.precio), 0);
});

const openPos = () => {
    posForm.reset();
    limpiarCliente();
    libroSeleccionado.value = null;
    showPosModal.value = true;
};

// Si el admin cambia de sucursal a mitad de una venta, el carrito quedaria
// validado contra el stock de la sucursal anterior: lo vaciamos para evitar
// vender algo que no hay en la sucursal nueva.
watch(() => posForm.sucursal_id, (nuevo, anterior) => {
    if (anterior === undefined) return;
    posForm.items = [];
    libroSeleccionado.value = null;
    librosResults.value = [];
});

const submitVenta = () => {
    if (posForm.items.length === 0) return;

    posForm.post(route('ventas.store'), {
        onSuccess: () => {
            showPosModal.value = false;
            posForm.reset();
            limpiarCliente();
            darkSwal.fire({ title: '¡Venta Registrada!', text: 'Operación completada con éxito.', icon: 'success', timer: 1500, showConfirmButton: false });
        }
    });
};

const handleSearch = () => {
    router.get(route('ventas.index'), {
        search: search.value,
        tab: currentTab.value,
        estados: selectedEstados.value
    }, { preserveState: true, replace: true });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val || 0);
};

const eliminarCanceladas = () => {
    darkSwal.fire({
        title: '¿Eliminar TODAS las ventas canceladas?',
        text: 'Esta acción borrará permanentemente todas las ventas en estado cancelado. No se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar todas',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('ventas.destroy-canceladas'), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminadas',
                        text: 'El historial fue limpiado con éxito.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const switchTab = (tab) => {
    currentTab.value = tab;
    selectedEstados.value = [];
    handleSearch();
};

const viewVenta = (venta) => {
    selectedVenta.value = venta;
    estadoForm.estado = venta.estado;
    estadoForm.direccion_envio = venta.direccion_envio || '';
    estadoForm.latitud = venta.latitud || null;
    estadoForm.longitud = venta.longitud || null;
    estadoForm.tracking_code = venta.tracking_code || '';
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    const urlParams = new URLSearchParams(window.location.search);
    const returnClient = urlParams.get('return_client');
    if (returnClient) {
        router.get(route('clientes.show', returnClient));
    }
};

const cambiarEstado = async () => {
    if (estadoForm.estado === selectedVenta.value.estado && estadoForm.direccion_envio === (selectedVenta.value.direccion_envio || '') && estadoForm.tracking_code === (selectedVenta.value.tracking_code || '')) return;

    if (estadoForm.estado === 'cancelado' && selectedVenta.value.estado !== 'cancelado') {
        const { isConfirmed } = await darkSwal.fire({
            title: '¿Confirmar cancelación?',
            text: 'Esta acción anulará la venta y devolverá el stock de todos los artículos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No, mantener',
        });
        
        if (!isConfirmed) {
            estadoForm.estado = selectedVenta.value.estado;
            return;
        }
    }

    estadoForm.patch(route('ventas.estado', selectedVenta.value.id), {
        onSuccess: () => {
            showDetailModal.value = false;
            router.reload({ preserveScroll: true });
        }
    });
};

const confirmarPago = async () => {
    let mensaje = 'Esto registrará el ingreso del dinero y pasará el pedido a "En Preparación".';
    if (selectedVenta.value.tipo_envio === 'retiro' || selectedVenta.value.tipo_envio === 'acumulacion') {
        mensaje = 'Esto registrará el ingreso del dinero y confirmará el pago del pedido.';
    }

    const { isConfirmed } = await darkSwal.fire({
        title: '¿Confirmar Pago?',
        text: mensaje,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
    });
    
    if (!isConfirmed) return;

    estadoForm.post(route('ventas.confirmar-pago', selectedVenta.value.id), {
        onSuccess: () => {
            showDetailModal.value = false;
            router.reload({ preserveScroll: true });
        }
    });
};

const isFormModified = computed(() => {
    if (!selectedVenta.value) return false;
    return estadoForm.estado !== selectedVenta.value.estado ||
           estadoForm.direccion_envio !== (selectedVenta.value.direccion_envio || '') ||
           estadoForm.tracking_code !== (selectedVenta.value.tracking_code || '');
});

const formatTicketDate = (fechaStr) => {
    if (!fechaStr) return '';
    const d = new Date(fechaStr);
    const day = d.getDate();
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const month = months[d.getMonth()];
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day} ${month} ${year}, ${hours}:${minutes} hs`;
};

const getClienteNombre = (venta) => {
    if (!venta) return 'Cliente Mostrador';
    if (venta.cliente?.user) {
        return `${venta.cliente.user.name} ${venta.cliente.user.apellido || ''}`.trim();
    }
    if (venta.tipo === 'online' && venta.user) {
        return `${venta.user.name} ${venta.user.apellido || ''}`.trim();
    }
    return 'Cliente Mostrador';
};

const formatSucursalName = (name) => {
    if (!name) return 'N/A';
    return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('open') === 'pos') {
            openPos();
        }
        if (urlParams.get('view') && props.ventas?.data) {
            const ventaToView = props.ventas.data.find(v => v.id == urlParams.get('view'));
            if (ventaToView) {
                viewVenta(ventaToView);
            }
        }
    }
});
</script>

<template>
    <Head title="Ventas & Facturación" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full page-ventas">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight uppercase">TERMINAL DE VENTAS</h2>
                </div>
                <button 
                    @click="openPos()" 
                    class="px-5 py-2.5 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-xs transition-all shadow-md active:scale-95 flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span>Nueva Operación (POS)</span>
                </button>
            </div>
        </template>

        <div class="py-8 page-ventas">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Rapid Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Ventas Hoy</div>
                        <div class="text-2xl font-bold text-white tracking-tight">{{ stats.ventas_hoy }}</div>
                    </div>
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Recaudación (Hoy)</div>
                        <div class="text-2xl font-bold text-white tracking-tight">{{ formatCurrency(stats.recaudacion) }}</div>
                    </div>
                    <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                        <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Ticket Promedio</div>
                        <div class="text-2xl font-bold text-white tracking-tight">{{ formatCurrency(stats.promedio_ticket) }}</div>
                    </div>
                </div>

                <!-- Search Filter Container -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-4 flex items-center shadow-xl">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por cliente o #TK..." 
                            class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium"
                        >
                    </div>
                </div>

                <!-- Tabs (Activas / Finalizadas / Canceladas) -->
                <div class="flex justify-between items-center border-b border-white/5 pb-1">
                    <div class="flex gap-3">
                        <button
                            @click="switchTab('activas')"
                            class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-all"
                            :class="currentTab === 'activas' ? 'border-white text-white font-bold' : 'border-transparent text-zinc-400 hover:text-white font-medium'"
                        >
                            Ventas Activas ({{ stats.total_activas }})
                        </button>
                        <button
                            @click="switchTab('finalizadas')"
                            class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-all"
                            :class="currentTab === 'finalizadas' ? 'border-white text-white font-bold' : 'border-transparent text-zinc-400 hover:text-white font-medium'"
                        >
                            Ventas Finalizadas ({{ stats.total_finalizadas }})
                        </button>
                        <button
                            @click="switchTab('canceladas')"
                            class="px-5 py-2.5 text-xs font-semibold uppercase tracking-wider border-b-2 transition-all"
                            :class="currentTab === 'canceladas' ? 'border-white text-white font-bold' : 'border-transparent text-zinc-400 hover:text-white font-medium'"
                        >
                            Canceladas ({{ stats.total_canceladas }})
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-3 pb-1">
                        <button 
                            v-if="currentTab === 'canceladas' && ventas.data.length > 0"
                            @click="eliminarCanceladas"
                            class="px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 font-semibold text-xs border border-rose-500/20 transition-all flex items-center gap-2 active:scale-95"
                        >
                            <span>Eliminar Todas</span>
                        </button>
                        
                        <!-- Dropdown de Estados -->
                        <div v-if="currentTab === 'activas'" class="relative" id="estado-filter-dropdown-container">
                            <button 
                                @click.stop="toggleEstadoDropdown"
                                class="flex items-center gap-2 px-4 py-2 text-xs font-semibold bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-zinc-300 hover:text-white transition-all"
                            >
                                <span>Estado ({{ selectedEstados.length || 'Todos' }})</span>
                                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{'rotate-180': showEstadoDropdown}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div 
                                v-if="showEstadoDropdown"
                                class="absolute right-0 mt-2 w-64 bg-[#0d0d0f] border border-white/10 rounded-2xl shadow-2xl z-50 py-2 max-h-80 overflow-y-auto"
                            >
                                <div class="px-4 py-2 border-b border-white/5 flex justify-between items-center mb-1">
                                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Filtrar por Estado</span>
                                    <button 
                                        v-if="selectedEstados.length > 0"
                                        @click="clearEstados"
                                        class="text-xs font-semibold text-rose-400 hover:underline uppercase tracking-wider"
                                    >
                                        Limpiar
                                    </button>
                                </div>
                                <div class="divide-y divide-white/5">
                                    <label 
                                        v-for="opcion in estadoOpciones.filter(e => e.value !== 'finalizado' && e.value !== 'cancelado')" 
                                        :key="opcion.value"
                                        class="flex items-center px-4 py-2.5 hover:bg-white/5 cursor-pointer select-none"
                                    >
                                        <input 
                                            type="checkbox" 
                                            :value="opcion.value" 
                                            v-model="selectedEstados" 
                                            @change="handleSearch"
                                            class="rounded border-white/10 bg-[#131316] text-emerald-500 focus:ring-0 h-4 w-4"
                                        >
                                        <span class="ml-3 text-xs font-semibold text-zinc-300">
                                            {{ opcion.label }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Table Card -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl" :class="{'opacity-70': currentTab === 'canceladas' || currentTab === 'finalizadas'}">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-fixed">
                            <thead>
                                <tr class="bg-white/[0.02] text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5">
                                    <th class="p-4 text-left w-[18%]">Ticket</th>
                                    <th class="p-4 text-left w-[25%]">Cliente</th>
                                    <th class="p-4 text-left w-[15%]">Sucursal</th>
                                    <th class="p-4 text-left w-[17%]">Estado</th>
                                    <th class="p-4 text-right w-[15%]">Monto Total</th>
                                    <th class="p-4 text-center w-[10%]">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm">
                                <template v-for="venta in ventas.data" :key="venta.id">
                                    <tr class="hover:bg-white/[0.02] transition-colors group">
                                        <td class="p-4 text-left">
                                            <button @click.stop="viewVenta(venta)" class="text-sm font-bold transition-colors hover:text-brand-red text-left block" :class="venta.estado === 'cancelado' ? 'text-zinc-500 line-through hover:text-zinc-400' : 'text-white'">#TK-{{ String(venta.id).padStart(6, '0') }}</button>
                                            <div class="text-xs text-zinc-500 font-mono mt-0.5">{{ formatTicketDate(venta.fecha) }}</div>
                                        </td>
                                        <td class="p-4 text-left">
                                            <Link v-if="venta.cliente_id" :href="route('clientes.show', venta.cliente_id)" @click.stop class="text-sm font-bold text-white hover:text-blue-400 transition-colors block truncate capitalize">
                                                {{ getClienteNombre(venta) }}
                                            </Link>
                                            <div v-else class="text-sm font-bold text-white truncate capitalize">
                                                {{ getClienteNombre(venta) }}
                                            </div>
                                        </td>
                                        <td class="p-4 text-left">
                                            <span class="text-xs font-semibold text-zinc-300">{{ venta.sucursal?.nombre || 'General' }}</span>
                                        </td>
                                        <td class="p-4 text-left">
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                                <span class="w-2 h-2 rounded-full shrink-0" :class="estadoDots[venta.estado] || 'bg-zinc-500'"></span>
                                                <span>{{ estadoOpciones.find(e => e.value === venta.estado)?.label || (venta.estado ? venta.estado.charAt(0).toUpperCase() + venta.estado.slice(1) : '') }}</span>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <div class="text-sm font-bold text-white">{{ formatCurrency(venta.total) }}</div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <a v-if="venta.comprobante_path" :href="route('mi-cuenta.comprobante.ver', venta.id)" @click.stop target="_blank" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Ver comprobante del cliente">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                                </a>
                                                <button @click.stop="viewVenta(venta)" class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Ver detalle">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                </button>
                                                <Link :href="route('ventas.show', venta.id)" @click.stop class="p-2 text-zinc-400 hover:text-white hover:bg-white/5 rounded-xl transition-all" title="Comprobante">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginación -->
                <div class="flex justify-center gap-2 mt-6" v-if="ventas.links && ventas.links.length > 3">
                    <Link 
                        v-for="link in ventas.links" 
                        :key="link.label" 
                        :href="link.url || '#'" 
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold" 
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}" 
                        v-html="decodeLabel(link.label)"
                    ></Link>
                </div>
            </div>
        </div>

        <!-- POS Terminal Modal -->
        <Teleport to="body">
            <div v-if="showPosModal" class="page-ventas">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showPosModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 md:p-6 pointer-events-none">
                    <div class="relative w-full h-full md:h-[90vh] md:w-[95vw] lg:w-[85vw] bg-[#0d0d0f] border border-white/10 rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden pointer-events-auto">
                        
                        <!-- Left Section: Item Selection & Cart Panel -->
                        <div class="flex-1 p-6 md:p-8 flex flex-col overflow-y-auto space-y-5">
                            <!-- Top Header -->
                            <div class="flex justify-between items-center border-b border-white/5 pb-4">
                                <h3 class="text-xl font-bold tracking-tight uppercase text-white">Terminal POS</h3>
                                <div class="flex items-center gap-3">
                                    <select
                                        v-if="$page.props.auth.esAdmin"
                                        v-model="posForm.sucursal_id"
                                        class="text-xs font-semibold text-zinc-300 bg-white/5 px-3 py-1.5 rounded-xl border border-white/10 uppercase tracking-wider focus:outline-none focus:border-white/30"
                                        title="Sucursal desde la que estás vendiendo"
                                    >
                                        <option value="" disabled>📍 Elegí sucursal</option>
                                        <option v-for="s in props.sucursales" :key="s.id" :value="s.id">📍 {{ s.nombre }}</option>
                                    </select>
                                    <div v-else-if="$page.props.auth.empleado?.sucursal" class="text-xs font-semibold text-zinc-300 bg-white/5 px-3 py-1.5 rounded-xl border border-white/10 uppercase tracking-wider">
                                        📍 {{ $page.props.auth.empleado.sucursal.nombre }}
                                    </div>
                                    <button @click="showPosModal = false" class="text-zinc-400 hover:text-white transition-colors p-1.5 rounded-xl hover:bg-white/5" title="Cerrar modal">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Client Selection Field -->
                            <div>
                                <label class="text-xs font-semibold text-zinc-400 block mb-1">Cliente</label>
                                <div class="relative">
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </span>
                                            <input 
                                                v-model="clienteSearch" 
                                                @input="buscarClientes(clienteSearch)" 
                                                @focus="showClienteDropdown = true" 
                                                type="text" 
                                                placeholder="Buscar por nombre, DNI o email..." 
                                                class="w-full bg-[#131316] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium"
                                            >
                                        </div>
                                        <button v-if="clienteSeleccionado" @click="limpiarCliente" class="px-3.5 text-zinc-400 hover:text-white transition-colors bg-white/5 rounded-xl text-xs font-bold" title="Limpiar">✕</button>
                                        <button v-else @click="crearClienteRapido" type="button" class="px-4 bg-white/10 hover:bg-white/20 text-white transition-colors rounded-xl text-xs font-semibold tracking-wider whitespace-nowrap border border-white/10 py-2.5" title="Crear Cliente Rápido">+ NUEVO</button>
                                    </div>
                                    <p v-if="!clienteSeleccionado" class="text-xs text-zinc-500 mt-1.5 font-medium">Sin selección = Consumidor Final</p>
                                    <p v-else class="text-xs text-emerald-400 mt-1.5 font-medium">Saldo actual en cuenta: {{ formatCurrency(clienteSeleccionado.saldo_actual) }}</p>
                                    
                                    <div v-if="showClienteDropdown && clienteSearch.length >= 1 && clientesResults.length === 0" class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl p-4 text-center">
                                        <p class="text-xs text-zinc-400 uppercase font-semibold mb-3">No se encontraron clientes</p>
                                        <button @click="crearClienteRapido" type="button" class="bg-white text-black py-2 px-4 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-zinc-200 transition-colors">CREAR NUEVO CLIENTE</button>
                                    </div>

                                    <div v-if="showClienteDropdown && clientesResults.length" class="absolute z-50 w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                                        <div v-for="c in clientesResults" :key="c.id" @mousedown.prevent="seleccionarCliente(c)" class="px-4 py-3 cursor-pointer hover:bg-white/5 transition-colors border-b border-white/5 last:border-0">
                                            <div class="text-sm font-bold text-white capitalize">{{ c.user?.name }} {{ c.user?.apellido }}</div>
                                            <div class="text-xs text-zinc-400 font-mono mt-0.5">DNI: {{ c.user?.dni }} | Saldo: {{ formatCurrency(c.saldo_actual) }}</div>
                                        </div>
                                    </div>
                                    <div v-if="showClienteDropdown" class="fixed inset-0 z-40" @click="showClienteDropdown = false"></div>
                                </div>
                            </div>

                            <!-- Cart Panel Container -->
                            <div class="bg-[#131316] border border-white/5 rounded-2xl p-5 flex flex-col flex-1 gap-4">
                                <!-- Product Search Header -->
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                                    <label class="text-xs font-semibold text-zinc-400">Agregar Productos</label>
                                    <button type="button" @click="simularEscaneo" class="px-3.5 py-1.5 bg-white/5 hover:bg-white/10 text-white text-xs font-semibold uppercase tracking-wider rounded-xl border border-white/10 transition-colors flex items-center gap-2">
                                        📷 ESCANEAR
                                    </button>
                                </div>
                                
                                <!-- Search Input -->
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-zinc-500">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </span>
                                    <input 
                                        v-model="libroSearch" 
                                        @input="buscarLibros(libroSearch)" 
                                        @focus="showLibroDropdown = true" 
                                        type="text" 
                                        placeholder="Buscar por título o ISBN..." 
                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium" 
                                        :disabled="!posForm.sucursal_id"
                                        :title="!posForm.sucursal_id ? 'Elegí una sucursal para operar' : ''"
                                        :class="{'opacity-50 cursor-not-allowed': !posForm.sucursal_id}"
                                    >
                                    
                                    <div v-if="showLibroDropdown && librosResults.length" class="absolute top-full left-0 z-[60] w-full mt-1 bg-[#131316] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                                        <div v-for="l in librosResults" :key="l.id" 
                                             @mousedown.prevent="(l.stock_disponible > 0 || l.permite_preventa) && seleccionarLibroParaAgregar(l)" 
                                             :class="(l.stock_disponible <= 0 && !l.permite_preventa) ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-white/5'" 
                                             class="px-4 py-3 transition-colors border-b border-white/5 last:border-0 flex justify-between items-center">
                                            <div>
                                                <div class="text-sm font-bold text-white">
                                                    {{ l.master?.titulo }} 
                                                    <span v-if="l.numero_tomo" class="text-zinc-400 font-semibold ml-1">- Tomo {{ l.numero_tomo }}</span>
                                                </div>
                                                <div class="text-xs text-zinc-400 font-mono mt-1">
                                                    ISBN: {{ l.isbn }} | {{ l.precio_actual ? formatCurrency(l.precio_actual.precio_venta) : 'Sin precio' }} - 
                                                    <span :class="l.stock_disponible <= 0 ? 'text-rose-400' : 'text-emerald-400'">
                                                        Stock: {{ l.stock_disponible ?? '?' }}
                                                        <span v-if="l.stock_disponible <= 0 && l.permite_preventa" class="ml-1 text-[10px] uppercase font-bold text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded-md border border-amber-400/20">(Preventa)</span>
                                                        <span v-if="l.stock_disponible <= 0 && !l.permite_preventa" class="ml-1 text-[10px] uppercase font-bold text-rose-400 bg-rose-400/10 px-1.5 py-0.5 rounded-md border border-rose-400/20">(Agotado)</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="showLibroDropdown" class="fixed inset-0 z-50" @click="showLibroDropdown = false"></div>
                                </div>

                                <!-- Product Ready to Add Bar -->
                                <div v-if="libroSeleccionado" class="p-3.5 bg-white/[0.03] border border-white/10 rounded-xl flex flex-col sm:flex-row justify-between items-center gap-4 transition-all">
                                    <div>
                                        <div class="text-xs text-zinc-400 font-normal">Producto seleccionado:</div>
                                        <div class="text-sm font-bold text-white mt-0.5">
                                            {{ libroSeleccionado.master?.titulo }}
                                            <span v-if="libroSeleccionado.numero_tomo" class="text-zinc-300 ml-1">- Tomo {{ libroSeleccionado.numero_tomo }}</span>
                                        </div>
                                        <div class="text-xs text-zinc-400 font-mono mt-0.5">Precio Unitario: {{ formatCurrency(libroSeleccionado.precio_actual?.precio_venta) }}</div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center bg-[#0d0d0f] rounded-xl border border-white/10 overflow-hidden h-10">
                                            <button type="button" @click="cantidadSeleccionada > 1 ? cantidadSeleccionada-- : null" class="px-3.5 text-zinc-400 hover:text-white transition-colors font-bold text-base">-</button>
                                            <input type="number" v-model="cantidadSeleccionada" @change="validarCantidadParaAgregar" min="1" class="w-12 bg-transparent text-center text-sm font-bold p-0 border-0 focus:ring-0 text-white">
                                            <button type="button" @click="incrementarSeleccion" class="px-3.5 text-zinc-400 hover:text-white transition-colors font-bold text-base">+</button>
                                        </div>
                                        <button type="button" @click="confirmarAgregarAlCarrito" class="py-2.5 px-5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95">
                                            AGREGAR
                                        </button>
                                        <button type="button" @click="libroSeleccionado = null" class="p-2 text-zinc-400 hover:text-white transition-colors" title="Cancelar">
                                            ✕
                                        </button>
                                    </div>
                                </div>

                                <!-- Cart Items List -->
                                <div class="flex-1 space-y-2.5 overflow-y-auto max-h-[38vh] pr-1">
                                    <div v-for="(item, idx) in posForm.items" :key="idx" class="flex justify-between items-center p-3.5 bg-white/[0.02] border border-white/5 rounded-xl hover:border-white/20 transition-all group">
                                        <div class="flex-1">
                                            <div class="text-sm font-bold text-white tracking-tight">{{ item.titulo }}</div>
                                            <div class="flex items-center gap-3 mt-1.5">
                                                <div class="flex items-center bg-[#0d0d0f] rounded-lg border border-white/10 overflow-hidden h-7">
                                                    <button type="button" @click="item.cantidad > 1 ? item.cantidad-- : removeItem(idx)" class="px-2.5 text-zinc-400 hover:text-white transition-colors font-bold text-xs">-</button>
                                                    <input type="number" v-model="item.cantidad" @change="validarItemCarrito(item)" min="1" class="w-10 bg-transparent text-center text-xs font-bold p-0 border-0 focus:ring-0 text-white h-full">
                                                    <button type="button" @click="incrementarItemCarrito(item)" class="px-2.5 text-zinc-400 hover:text-white transition-colors font-bold text-xs">+</button>
                                                </div>
                                                <div class="text-xs text-zinc-400 font-mono">x {{ formatCurrency(item.precio) }}</div>
                                            </div>
                                        </div>
                                        <div class="text-base font-bold text-white mr-5">
                                            {{ formatCurrency(item.cantidad * item.precio) }}
                                        </div>
                                        <button @click="removeItem(idx)" class="text-zinc-500 hover:text-rose-400 transition-colors p-1.5" title="Eliminar ítem">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>

                                    <div v-if="posForm.items.length === 0" class="h-36 flex items-center justify-center text-zinc-500 text-sm font-semibold italic">
                                        El carrito está vacío
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Section: Summary & Checkout -->
                        <div class="w-full md:w-[380px] bg-[#131316] p-6 md:p-8 flex flex-col justify-between overflow-y-auto border-l border-white/5">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400 mb-6 text-center">RESUMEN DE COBRO</h4>
                                
                                <div class="space-y-6">
                                    <div>
                                        <label class="text-xs font-semibold text-zinc-400 block mb-2">Método de Cobro</label>
                                        <select v-model="posForm.medio_pago" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white font-medium focus:outline-none focus:border-white/30">
                                            <option value="Efectivo">💵 Efectivo Cash</option>
                                            <option value="Tarjeta">💳 Tarjeta / Posnet</option>
                                            <option value="Transferencia">📱 Transferencia</option>
                                            <option value="Cuenta Corriente">🏛️ Cuenta Corriente</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 space-y-5 pt-6 border-t border-white/5">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-semibold text-zinc-400 uppercase tracking-wider">Total:</span>
                                        <span class="text-3xl font-bold text-white tracking-tight">{{ formatCurrency(subtotalPos) }}</span>
                                    </div>

                                    <div v-if="posForm.medio_pago === 'Cuenta Corriente' && clienteSeleccionado" class="flex justify-between items-center border-t border-white/5 pt-3">
                                        <span class="text-xs text-zinc-400 font-medium">Saldo restante en cuenta:</span>
                                        <span class="text-lg font-bold tracking-tight" :class="(clienteSeleccionado.saldo_actual - subtotalPos) < 0 ? 'text-rose-400' : 'text-emerald-400'">
                                            {{ formatCurrency(clienteSeleccionado.saldo_actual - subtotalPos) }}
                                        </span>
                                    </div>

                                    <div v-if="posForm.medio_pago === 'Cuenta Corriente' && clienteSeleccionado && (clienteSeleccionado.saldo_actual - subtotalPos) < 0" class="mt-3 bg-white/[0.02] border border-white/5 p-4 rounded-xl space-y-2.5">
                                        <label class="text-xs font-medium text-zinc-300 block leading-relaxed">
                                            El saldo restante ({{ formatCurrency(Math.abs(clienteSeleccionado.saldo_actual - subtotalPos)) }}) dejará la cuenta en negativo. ¿Desea abonar el excedente ahora?
                                        </label>
                                        <select v-model="posForm.metodo_pago_excedente" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30">
                                            <option :value="null">Dejar como deuda en Cuenta Corriente</option>
                                            <option value="Efectivo">💵 Pagar excedente en Efectivo</option>
                                            <option value="Tarjeta">💳 Pagar excedente con Tarjeta / Posnet</option>
                                            <option value="Transferencia">📱 Pagar excedente con Transferencia</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2.5 mt-4">
                                    <button @click="submitVenta" :disabled="posForm.processing || posForm.items.length === 0 || !posForm.sucursal_id" class="w-full py-3.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-30">
                                       {{ posForm.processing ? 'Sincronizando...' : 'Confirmar Pago' }}
                                    </button>
                                    <button @click="showPosModal = false" class="text-xs font-semibold text-zinc-500 hover:text-white transition-colors tracking-wider py-2 text-center">Cancelar Operación</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Sales Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetailModal && selectedVenta" class="page-ventas">
                <div class="fixed inset-0 z-[110] bg-black/90 backdrop-blur-md" @click="closeDetailModal"></div>
                <div class="fixed inset-0 z-[120] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-3xl bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                    Detalle de Ticket <span class="text-white font-mono font-bold text-lg ml-1">#TK-{{ String(selectedVenta.id).padStart(6, '0') }}</span>
                                </h3>
                                <span v-if="selectedVenta.motivo_pendiente === 'Acumulación'" class="text-xs font-semibold text-emerald-400 px-2.5 py-0.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">Acumulado</span>
                            </div>
                            <div class="text-right flex items-center gap-3">
                                <div class="text-xs font-semibold text-zinc-400">{{ formatTicketDate(selectedVenta.fecha) }}</div>
                                <span class="text-xs font-semibold text-zinc-300 px-3 py-1 rounded-xl bg-white/5 border border-white/5">
                                    {{ selectedVenta.tipo }}
                                </span>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-3 gap-6 pb-6 border-b border-white/5">
                                <div class="space-y-1 text-left">
                                    <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">CLIENTE</div>
                                    <Link v-if="selectedVenta.cliente_id" :href="route('clientes.show', selectedVenta.cliente_id)" class="text-sm text-white font-bold block hover:text-blue-400 transition-colors capitalize">
                                        {{ getClienteNombre(selectedVenta) }}
                                    </Link>
                                    <div v-else class="text-sm text-white font-bold capitalize">
                                        {{ getClienteNombre(selectedVenta) }}
                                    </div>
                                </div>
                                <div class="space-y-1 text-center">
                                    <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">SUCURSAL</div>
                                    <div class="text-sm text-white font-bold">{{ formatSucursalName(selectedVenta.sucursal?.nombre) }}</div>
                                </div>
                                <div class="space-y-1 text-right">
                                    <div class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">MEDIO DE PAGO</div>
                                    <div class="text-sm text-white font-bold capitalize">{{ selectedVenta.metodo_pago || 'No especificado' }}</div>
                                </div>
                            </div>

                            <div class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="text-xs font-semibold uppercase tracking-wider text-zinc-400 border-b border-white/5 bg-white/[0.02]">
                                            <th class="p-4">PRODUCTO</th>
                                            <th class="p-4 text-center w-24">CANT.</th>
                                            <th class="p-4 text-right w-32">P. UNIT</th>
                                            <th class="p-4 text-right w-36">SUBTOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5 text-sm">
                                        <tr v-for="item in selectedVenta.detalles" :key="item.id" class="hover:bg-white/[0.02] transition-colors">
                                            <td class="p-4">
                                                <div class="text-sm text-white font-bold">{{ item.libro?.master?.titulo }} - Tomo {{ item.libro?.numero_tomo || 'Único' }}</div>
                                                <div class="text-xs text-zinc-500 font-mono mt-0.5">ISBN: {{ item.libro?.isbn }}</div>
                                            </td>
                                            <td class="p-4 text-center text-sm text-white font-bold">{{ item.cantidad }}</td>
                                            <td class="p-4 text-right text-sm text-zinc-300 font-medium">{{ formatCurrency(item.precio_unitario) }}</td>
                                            <td class="p-4 text-right text-sm text-white font-bold">{{ formatCurrency(item.subtotal) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-white/5 bg-white/[0.02]">
                                            <td colspan="3" class="p-4 text-right text-xs font-semibold uppercase tracking-wider text-zinc-400">Total de la Compra</td>
                                            <td class="p-4 text-right text-xl font-bold text-white whitespace-nowrap">{{ formatCurrency(selectedVenta.total) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div v-if="selectedVenta.estado === 'pendiente_pago' && (selectedVenta.transacciones?.filter(t => t.tipo === 'ingreso').reduce((sum, t) => sum + parseFloat(t.monto), 0) || 0) > 0" class="flex justify-between items-center bg-[#131316] p-4 border border-white/5 rounded-xl">
                                <div class="text-xs font-semibold text-zinc-400">Abonado (Saldo a favor/Parcial)</div>
                                <div class="text-lg font-bold text-emerald-400">
                                    {{ formatCurrency(selectedVenta.transacciones?.filter(t => t.tipo === 'ingreso').reduce((sum, t) => sum + parseFloat(t.monto), 0) || 0) }}
                                </div>
                            </div>

                            <div v-if="selectedVenta.estado === 'pendiente_pago'" class="bg-[#131316] border border-white/10 p-5 rounded-2xl space-y-4">
                                <div class="flex justify-between items-center">
                                    <div class="text-xs font-semibold text-zinc-300">Resta a pagar</div>
                                    <div class="text-2xl font-bold text-white">
                                        {{ formatCurrency(selectedVenta.total - (selectedVenta.transacciones?.filter(t => t.tipo === 'ingreso').reduce((sum, t) => sum + parseFloat(t.monto), 0) || 0)) }}
                                    </div>
                                </div>

                                <div v-if="selectedVenta.tipo === 'online'" class="pt-4 border-t border-white/5 flex flex-col gap-3">
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                                        <div class="text-xs font-semibold text-white">¿El pago ya impactó?</div>
                                        <button @click="confirmarPago" :disabled="estadoForm.processing" class="px-5 py-2 bg-white hover:bg-zinc-200 text-black text-xs font-bold rounded-xl transition-all shadow-md active:scale-95">
                                            CONFIRMAR PAGO
                                        </button>
                                    </div>
                                    <div v-if="selectedVenta.comprobante_path" class="pt-2 border-t border-white/5 flex justify-between items-center">
                                        <span class="text-xs text-zinc-400">✅ El cliente subió un comprobante.</span>
                                        <a :href="route('mi-cuenta.comprobante.ver', selectedVenta.id)" target="_blank" class="text-xs font-semibold text-blue-400 hover:underline">Ver adjunto</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions Bar -->
                            <div class="pt-4 border-t border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div v-if="puedeEditarEstado" class="flex flex-wrap items-center gap-3">
                                    <div class="w-auto min-w-[220px]">
                                        <label class="text-xs font-semibold text-zinc-400 mb-1 block">Estado de la Venta</label>
                                        <select v-model="estadoForm.estado" class="w-full bg-[#131316] border border-white/10 rounded-xl px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30" title="Estado de la Venta">
                                            <option v-for="e in estadoOpcionesFiltradas" :key="e.value" :value="e.value">{{ e.label }}</option>
                                        </select>
                                    </div>
                                    <div v-if="estadoForm.estado === 'en_preparacion' || estadoForm.estado === 'enviado'" class="w-auto min-w-[200px]">
                                        <label class="text-xs font-semibold text-zinc-400 mb-1 block">Dirección de Envío</label>
                                        <DireccionAutocomplete v-model="estadoForm.direccion_envio" @select="onSeleccionarDireccionVenta" placeholder="Ej: San Martín 123, Rosario" class="w-full bg-[#131316] border border-white/10 rounded-xl px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30" />
                                    </div>
                                    <div v-if="selectedVenta.tipo_envio === 'correo_nacional'" class="w-auto min-w-[200px]">
                                        <label class="text-xs font-semibold text-zinc-400 mb-1 block">Código de Seguimiento</label>
                                        <input type="text" v-model="estadoForm.tracking_code" placeholder="Ej: SD321876451AR" class="w-full bg-[#131316] border border-white/10 rounded-xl px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:border-white/30" />
                                    </div>
                                </div>
                                <div v-else></div>

                                <div class="flex items-center gap-3">
                                    <button 
                                        v-if="isFormModified" 
                                        type="button" 
                                        @click="cambiarEstado" 
                                        :disabled="estadoForm.processing"
                                        class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50"
                                    >
                                        {{ estadoForm.processing ? 'GUARDANDO...' : 'GUARDAR CAMBIOS' }}
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="closeDetailModal" 
                                        class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all"
                                    >
                                        Cerrar Detalle
                                    </button>
                                </div>
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

.page-ventas,
.page-ventas * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
