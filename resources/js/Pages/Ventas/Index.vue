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
];;



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
    cancelado:          'bg-red-400',
};



const estadoForm = useForm({ estado: '', direccion_envio: null, latitud: null, longitud: null, tracking_code: null });

const onSeleccionarDireccionVenta = (f) => {
    // GeoJSON: coordinates viene como [lon, lat]
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

// Automatización para abrir la terminal directo desde el Dashboard
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open') === 'pos') {
        openPos();
    }
});

const posForm = useForm({
    cliente_id: '',
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
    items: [] // {libro_id, cantidad, precio, titulo}
});

// --- Buscador de clientes (AJAX) ---
const clienteSearch = ref('');
const clienteSeleccionado = ref(null);
const showClienteDropdown = ref(false);
const clientesResults = ref([]);
const clienteTimer = ref(null);

const buscarClientes = (q) => {
    clearTimeout(clienteTimer.value);
    if (q.length < 1) { clientesResults.value = []; return; }
    clienteTimer.value = setTimeout(async () => {
        const res = await fetch(route('ventas.search-clientes') + '?q=' + encodeURIComponent(q));
        clientesResults.value = await res.json();
    }, 300);
};

const seleccionarCliente = (cliente) => {
    clienteSeleccionado.value = cliente;
    posForm.cliente_id = cliente.id;
    clienteSearch.value = `${cliente.user?.name} ${cliente.user?.apellido || ''}`.trim();
    showClienteDropdown.value = false;
    clientesResults.value = [];
};

const limpiarCliente = () => {
    clienteSeleccionado.value = null;
    posForm.cliente_id = '';
    posForm.motivo_pendiente = null;
    posForm.es_excepcional = false;
    posForm.metodo_pago_excedente = null;
    clienteSearch.value = '';
    clientesResults.value = [];
};

const crearClienteRapido = async () => {
    showClienteDropdown.value = false;
    const dniSugerido = clienteSearch.value.replace(/\D/g, '');

    const { value: formValues } = await Swal.fire({
        title: 'ALTA DE CLIENTE',
        html: `
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Nombre *</label>
                    <input id="swal-nombre" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="text" autocomplete="off">
                </div>
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Apellido</label>
                    <input id="swal-apellido" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="text" autocomplete="off">
                </div>
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">DNI / Documento *</label>
                    <input id="swal-dni" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="number" value="${dniSugerido}">
                </div>
                <div class="text-left">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Teléfono móvil</label>
                    <input id="swal-telefono" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="text" autocomplete="off">
                </div>
                <div class="text-left col-span-2">
                    <label class="text-[9px] uppercase font-black text-white/50 tracking-widest">Email de Contacto *</label>
                    <input id="swal-email" class="w-full bg-black/40 border border-white/10 rounded p-2 text-white mt-1 text-xs" type="email" placeholder="ejemplo@correo.com">
                </div>
            </div>
        `,
        width: 600,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'CONFIRMAR REGISTRO',
        cancelButtonText: 'CANCELAR',
        background: '#1A1A1A', color: '#FFF',
        confirmButtonColor: '#E61919',
        preConfirm: () => {
            const name = document.getElementById('swal-nombre').value;
            const dni = document.getElementById('swal-dni').value;
            const email = document.getElementById('swal-email').value;

            if (!name || !dni || !email) {
                Swal.showValidationMessage('Nombre, DNI y Email son obligatorios');
                return false;
            }
            return { 
                name, 
                apellido: document.getElementById('swal-apellido').value,
                dni, 
                telefono: document.getElementById('swal-telefono').value,
                email 
            }
        }
    });

    if (formValues) {
        Swal.showLoading();
        try {
            // Enviamos todos los campos que requiere tu BD real
            await window.axios.post(route('clientes.store'), {
                name: formValues.name,
                apellido: formValues.apellido,
                dni: formValues.dni,
                telefono: formValues.telefono,
                email: formValues.email,
                tipo_cliente_id: 1, // Se envía 1 por defecto (generalmente es Consumidor Final)
                estado_abono: 'Activo',
                saldo_actual: 0,
                password: formValues.dni // Muchos backends requieren password, enviamos el DNI como clave por defecto
            });

            // Buscamos al cliente recién creado para traer su ID y seleccionarlo
            const res = await fetch(route('ventas.search-clientes') + '?q=' + formValues.dni);
            const clientes = await res.json();
            
            if (clientes.length > 0) {
                seleccionarCliente(clientes[0]);
                Swal.fire({
                    title: '¡Cliente Guardado!',
                    icon: 'success',
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                    background: '#1A1A1A', color: '#FFF'
                });
            }
        } catch (error) {
            // Si el backend lo rechaza, capturamos el mensaje real (ej: "El email ya está en uso")
            let msjError = 'Error de conexión o el cliente ya existe.';
            if (error.response && error.response.data && error.response.data.errors) {
                msjError = Object.values(error.response.data.errors)[0][0]; 
            }
            Swal.fire({
                title: 'Error de validación', 
                text: msjError, 
                icon: 'error',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    }
};

// --- Buscador de libros (AJAX) ---
const libroSearch = ref('');
const showLibroDropdown = ref(false);
const itemCantidad = ref(1);
const librosResults = ref([]);
const libroTimer = ref(null);

const buscarLibros = (q) => {
    clearTimeout(libroTimer.value);
    if (q.length < 1) { librosResults.value = []; return; }
    libroTimer.value = setTimeout(async () => {
        const params = new URLSearchParams({ q });
        const sucursalId = page.props.auth.empleado?.sucursal_id;
        if (sucursalId) params.append('sucursal_id', sucursalId);
        const res = await fetch(route('ventas.search-libros') + '?' + params.toString());
        librosResults.value = await res.json();
    }, 300);
};

// Variables para el paso intermedio (antes del carrito)
const libroSeleccionado = ref(null);
const cantidadSeleccionada = ref(1);

const seleccionarLibroParaAgregar = (libro) => {
    if (!libro) return;
    if (!libro.precio_actual) {
        Swal.fire({
            title: 'Sin Precio',
            text: 'Este libro no tiene precio de venta activo.',
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        return;
    }
    // Lo pone en la zona de espera en lugar de ir directo al carrito
    libroSeleccionado.value = libro;
    cantidadSeleccionada.value = 1;
    libroSearch.value = '';
    showLibroDropdown.value = false;
    librosResults.value = [];
};

const validarCantidadParaAgregar = () => {
    if (!libroSeleccionado.value.permite_preventa && cantidadSeleccionada.value > libroSeleccionado.value.stock_disponible) {
        cantidadSeleccionada.value = libroSeleccionado.value.stock_disponible;
        Swal.fire({
            title: 'Stock Limitado',
            text: `Solo hay ${libroSeleccionado.value.stock_disponible} unidades disponibles de este título.`,
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
            toast: true, position: 'top-end', timer: 3000, showConfirmButton: false
        });
    }
};

const incrementarSeleccion = () => {
    cantidadSeleccionada.value++;
    validarCantidadParaAgregar();
};

const validarItemCarrito = (item) => {
    if (!item.permite_preventa && item.cantidad > item.stock_disponible) {
        item.cantidad = item.stock_disponible;
        Swal.fire({
            title: 'Stock Limitado',
            text: `Solo hay ${item.stock_disponible} unidades disponibles.`,
            icon: 'warning',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919',
            toast: true, position: 'top-end', timer: 3000, showConfirmButton: false
        });
    }
};

const incrementarItemCarrito = (item) => {
    item.cantidad++;
    validarItemCarrito(item);
};

const confirmarAgregarAlCarrito = () => {
    if (!libroSeleccionado.value) return;
    const libro = libroSeleccionado.value;
    const existing = posForm.items.find(i => i.libro_id == libro.id);
    
    const totalFuturo = (existing ? existing.cantidad : 0) + cantidadSeleccionada.value;
    if (!libro.permite_preventa && totalFuturo > libro.stock_disponible) {
        Swal.fire({
            title: 'Stock Superado',
            text: `No puedes agregar esta cantidad. Solo quedan ${libro.stock_disponible} unidades en total y ya tienes ${existing ? existing.cantidad : 0} en el carrito.`,
            icon: 'error',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
        });
        return;
    }
    
    if (existing) {
        existing.cantidad += cantidadSeleccionada.value;
    } else {
        posForm.items.push({
            libro_id: libro.id,
            titulo: (libro.master?.titulo || 'Libro ' + libro.id) + (libro.numero_tomo ? ' - Tomo ' + libro.numero_tomo : ''),
            cantidad: cantidadSeleccionada.value,
            precio: libro.precio_actual.precio_venta,
            stock_disponible: libro.stock_disponible,
            permite_preventa: libro.permite_preventa
        });
    }
    // Limpia la zona de espera
    libroSeleccionado.value = null;
    cantidadSeleccionada.value = 1;
};

const simularEscaneo = async () => {
    const { value: isbn } = await Swal.fire({
        title: 'ESCANEAR CÓDIGO',
        input: 'text',
        inputLabel: 'Ingrese el ISBN o código de barras',
        inputPlaceholder: 'Ej: 978...',
        showCancelButton: true,
        confirmButtonText: 'BUSCAR',
        cancelButtonText: 'CANCELAR',
        background: '#1A1A1A', color: '#FFF',
        confirmButtonColor: '#E61919'
    });

    if (isbn) {
        Swal.showLoading();
        try {
            // Usa la misma API de búsqueda pero forzando el ISBN ingresado
            const params = new URLSearchParams({ q: isbn });
            const sucursalId = page.props.auth.empleado?.sucursal_id;
            if (sucursalId) params.append('sucursal_id', sucursalId);
            
            const res = await fetch(route('ventas.search-libros') + '?' + params.toString());
            const data = await res.json();
            
            if (data && data.length > 0) {
                Swal.close();
                const l = data[0];
                if (l.stock_disponible <= 0 && !l.permite_preventa) {
                    Swal.fire({
                        title: 'Sin Stock',
                        text: 'El código escaneado corresponde a un libro agotado y sin preventa.',
                        icon: 'warning',
                        background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                    });
                } else {
                    seleccionarLibroParaAgregar(l);
                }
            } else {
                Swal.fire({
                    title: 'No encontrado o No disponible',
                    text: 'El código escaneado no existe o no se puede vender (agotado y sin preventa).',
                    icon: 'warning',
                    background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
                });
            }
        } catch (e) {
            Swal.fire('Error', 'Hubo un problema de conexión', 'error');
        }
    }
};

const removeItem = (index) => {
    posForm.items.splice(index, 1);
};

const subtotalPos = computed(() => {
    return posForm.items.reduce((acc, item) => acc + (item.cantidad * item.precio), 0);
});

// Watchers para el POS Checkout
watch(() => posForm.tipo_envio, (val) => {
    if (val === 'acumulacion') {
        posForm.acumular_pedido = true;
    } else {
        posForm.acumular_pedido = false;
    }
});

watch(() => posForm.medio_pago, (val) => {
    if (val === 'Cuenta Corriente') {
        posForm.guardar_pendiente = false;
    }
});

watch(() => posForm.origen, (val) => {
    if (val === 'presencial') {
        posForm.guardar_pendiente = false;
        posForm.tipo_envio = 'retiro';
        posForm.acumular_pedido = false;
        posForm.requiere_envio = false;
    }
});

const submitVenta = () => {
    if (posForm.items.length === 0) {
        Swal.fire('Error', 'Debe agregar al menos un item', 'error');
        return;
    }

    posForm.post(route('ventas.store'), {
        onSuccess: () => {
            showPosModal.value = false;
            posForm.reset();
            Swal.fire({
                title: '¡Venta Exitosa!',
                text: 'La transacción ha sido procesada y el stock actualizado.',
                icon: 'success',
                background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
            });
        }
    });
};

const formatCurrency = (value) => {
    const formatted = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0);
    return '$' + formatted;
};

const openPos = () => {
    posForm.reset();
    posForm.motivo_pendiente = null;
    posForm.es_excepcional = false;
    posForm.metodo_pago_excedente = null;
    posForm.acumular_pedido = false;
    posForm.guardar_pendiente = false;
    posForm.tipo_envio = 'retiro';
    posForm.origen = 'presencial';
    clienteSearch.value = '';
    clienteSeleccionado.value = null;
    libroSearch.value = '';
    itemCantidad.value = 1;
    showPosModal.value = true;
};

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('nueva') === '1') {
        openPos();
    }
});

const handleSearch = () => {
    router.get(route('ventas.index'), {
        search: search.value,
        tab: currentTab.value,
        estados: selectedEstados.value.length > 0 ? selectedEstados.value : null
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const eliminarCanceladas = () => {
    Swal.fire({
        title: '¿Eliminar historial?',
        text: 'Esta acción borrará definitivamente todas las ventas canceladas. No se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E61919',
        cancelButtonColor: '#333',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('ventas.canceladas.destroyAll'), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Eliminadas',
                        text: 'El historial fue limpiado.',
                        icon: 'success',
                        background: '#1A1A1A', color: '#FFF'
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
        const { isConfirmed } = await Swal.fire({
            title: '¿Confirmar cancelación?',
            text: 'Esta acción anulará la venta y devolverá el stock de todos los artículos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No, mantener',
            background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#E61919'
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

    const { isConfirmed } = await Swal.fire({
        title: '¿Confirmar Pago?',
        text: mensaje,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
        background: '#1A1A1A', color: '#FFF', confirmButtonColor: '#25D366'
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

// Automatización segura para abrir la terminal directo desde el Dashboard o Detalles
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
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h2 class="text-3xl font-black leading-tight text-white tracking-tighter uppercase">
                    Terminal de <span class="text-brand-red not-italic">Ventas</span>
                </h2>
                <button @click="openPos()" class="btn-primary flex items-center gap-2 group relative overflow-hidden">
                    <span class="relative z-10 font-black not-italic">NUEVA OPERACIÓN (POS)</span>
                    <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
                </button>
            </div>
        </template>

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Estadísticas Rápidas -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="card p-6 border-white/5">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30 mb-1">Ventas Hoy</div>
                        <div class="text-2xl font-black">{{ stats.ventas_hoy }}</div>
                    </div>
                    <div class="card p-6 border-white/5">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30 mb-1">Recaudación (Hoy)</div>
                        <div class="text-2xl font-black text-white">{{ formatCurrency(stats.recaudacion) }}</div>
                    </div>
                    <div class="card p-6 border-white/5">
                        <div class="text-[8px] font-black uppercase tracking-[0.3em] text-white/30 mb-1">Ticket Promedio</div>
                        <div class="text-2xl font-black text-white">{{ formatCurrency(stats.promedio_ticket) }}</div>
                    </div>
                </div>

                <!-- Lista de Ventas -->
                <div class="card mb-6 p-4 flex items-center border-white/5">
                    <div class="relative w-full md:w-96">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-white/40">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Buscar por cliente o #TK..." 
                            class="input-field w-full pl-10 text-xs font-bold bg-black/40 border-white/10"
                        >
                    </div>
                </div>

                <!-- Tabs (Activas / Finalizadas / Canceladas) -->
                <div class="flex justify-between items-center border-b border-white/10 mb-4">
                    <div class="flex gap-1">
                        <button
                            @click="switchTab('activas')"
                            class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                            :class="currentTab === 'activas' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                        >
                            Ventas Activas ({{ stats.total_activas }})
                        </button>
                        <button
                            @click="switchTab('finalizadas')"
                            class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                            :class="currentTab === 'finalizadas' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                        >
                            Ventas Finalizadas ({{ stats.total_finalizadas }})
                        </button>
                        <button
                            @click="switchTab('canceladas')"
                            class="px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 transition-all"
                            :class="currentTab === 'canceladas' ? 'border-brand-red text-white' : 'border-transparent text-white/30 hover:text-white'"
                        >
                            Canceladas ({{ stats.total_canceladas }})
                        </button>
                    </div>
                    
                    <div class="flex items-center gap-4 pb-2">
                        <button 
                            v-if="currentTab === 'canceladas' && ventas.data.length > 0"
                            @click="eliminarCanceladas"
                            class="text-[10px] font-black text-red-400 hover:text-red-300 uppercase tracking-widest transition-colors mr-4"
                        >
                            Eliminar Todas
                        </button>
                        
                        <!-- Dropdown de Estados -->
                        <div v-if="currentTab === 'activas'" class="relative" id="estado-filter-dropdown-container">
                            <button 
                                @click.stop="toggleEstadoDropdown"
                                class="flex items-center gap-2 px-4 py-2 text-[10px] font-black uppercase tracking-[0.15em] bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg text-white/70 hover:text-white transition-all shadow-inner"
                            >
                                <span>Estado ({{ selectedEstados.length || 'Todos' }})</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 transition-transform duration-200" :class="{'rotate-180': showEstadoDropdown}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div 
                                v-if="showEstadoDropdown"
                                class="absolute right-0 mt-2 w-64 bg-zinc-950 border border-white/10 rounded-lg shadow-2xl z-50 py-2 max-h-80 overflow-y-auto"
                            >
                                <div class="px-3 py-1.5 border-b border-white/5 flex justify-between items-center mb-1">
                                    <span class="text-[9px] font-black uppercase tracking-wider text-white/40">Filtrar por Estado</span>
                                    <button 
                                        v-if="selectedEstados.length > 0"
                                        @click="clearEstados"
                                        class="text-[9px] font-black text-brand-red hover:underline uppercase tracking-wider"
                                    >
                                        Limpiar
                                    </button>
                                </div>
                                <div class="divide-y divide-white/5">
                                    <label 
                                        v-for="opcion in estadoOpciones.filter(e => e.value !== 'finalizado' && e.value !== 'cancelado')" 
                                        :key="opcion.value"
                                        class="flex items-center px-4 py-2 hover:bg-white/5 cursor-pointer select-none"
                                    >
                                        <input 
                                            type="checkbox" 
                                            :value="opcion.value" 
                                            v-model="selectedEstados" 
                                            @change="handleSearch"
                                            class="rounded border-white/10 bg-white/5 text-brand-red focus:ring-0 focus:ring-offset-0 h-4 w-4"
                                        >
                                        <span class="ml-3 text-xs font-bold text-white/70 tracking-wide">
                                            {{ opcion.label }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-0 overflow-hidden border-white/5" :class="{'opacity-70': currentTab === 'canceladas' || currentTab === 'finalizadas'}">
                    <table class="w-full text-left border-collapse table-fixed">
                        <thead>
                            <tr class="bg-white/[0.02] text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/5">
                                <th class="p-4 text-left w-[18%]">Ticket</th>
                                <th class="p-4 text-left w-[25%]">Cliente</th>
                                <th class="p-4 text-left w-[15%]">Sucursal</th>
                                <th class="p-4 text-left w-[17%]">Estado</th>
                                <th class="p-4 text-right w-[15%]">Monto Total</th>
                                <th class="p-4 text-center w-[10%]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <template v-for="venta in ventas.data" :key="venta.id">
                                <tr class="hover:bg-white/[0.01] transition-colors group">
                                    <td class="p-4 text-left">
                                        <div class="text-sm font-black transition-colors" :class="venta.estado === 'cancelado' ? 'text-white/40 line-through' : 'text-white group-hover:text-brand-red'">#TK-{{ String(venta.id).padStart(6, '0') }}</div>
                                        <div class="text-[10px] text-white/30 font-mono mt-1">{{ formatTicketDate(venta.fecha) }}</div>
                                    </td>
                                    <td class="p-4 text-left">
                                        <Link v-if="venta.cliente_id" :href="route('clientes.show', venta.cliente_id)" @click.stop class="text-sm font-bold text-white/90 hover:text-brand-red transition-colors block leading-relaxed capitalize">
                                            {{ getClienteNombre(venta) }}
                                        </Link>
                                        <div v-else class="text-sm font-bold text-white/90 leading-relaxed capitalize">
                                            {{ getClienteNombre(venta) }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-left">
                                        <span class="text-sm font-bold text-white/90">{{ venta.sucursal?.nombre || 'General' }}</span>
                                    </td>
                                    <td class="p-4 text-left">
                                        <div class="flex flex-col gap-1 items-start">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/[0.04] border border-white/10 text-xs font-bold text-white/90">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="estadoDots[venta.estado] || 'bg-gray-400'"></span>
                                                {{ estadoOpciones.find(e => e.value === venta.estado)?.label || venta.estado }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="text-base font-black text-white">{{ formatCurrency(venta.total) }}</div>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <a v-if="venta.comprobante_path" :href="route('mi-cuenta.comprobante.ver', venta.id)" @click.stop target="_blank" class="p-2 text-white/20 hover:text-white transition-colors" title="Ver comprobante del cliente">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                            </a>
                                            <button @click.stop="viewVenta(venta)" class="p-2 text-white/30 hover:text-white transition-colors" title="Ver detalle">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>
                                            <Link :href="route('ventas.show', venta.id)" @click.stop class="p-2 text-white/30 hover:text-white transition-colors" title="Comprobante">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-8 flex justify-center gap-2">
                    <Link v-for="link in ventas.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 rounded text-[10px] font-black uppercase transition-all" :class="link.active ? 'bg-brand-red text-white' : 'text-white/20 hover:text-white'">{{ decodeLabel(link.label) }}</Link>
                </div>
            </div>

        <!-- POS Terminal Modal -->
        <div v-if="showPosModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-6">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="showPosModal = false"></div>
            
            <div class="relative w-full h-full md:h-[90vh] md:w-[95vw] lg:w-[85vw] bg-[#141414] border border-white/10 rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden transform transition-all duration-300">
                
                <!-- Left Section: Item Selection & Cart Panel -->
                <div class="flex-1 p-6 md:p-8 flex flex-col overflow-y-auto space-y-6">
                    <!-- Top Header -->
                    <div class="flex justify-between items-center border-b border-white/5 pb-4">
                        <h3 class="text-2xl font-black tracking-tight uppercase text-white">Terminal <span class="text-brand-red">POS</span></h3>
                        <div class="flex items-center gap-3">
                            <div v-if="$page.props.auth.empleado?.sucursal" class="text-xs font-bold text-white/80 bg-white/5 px-3 py-1.5 rounded-md border border-white/10 uppercase tracking-wider">
                                📍 {{ $page.props.auth.empleado.sucursal.nombre }}
                            </div>
                            <button @click="showPosModal = false" class="text-white/40 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/5" title="Cerrar modal">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Client Selection Field -->
                    <div>
                        <label class="text-xs font-bold uppercase tracking-wider text-white/50 block mb-2">Cliente</label>
                        <div class="relative">
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-white/40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </span>
                                    <input v-model="clienteSearch" @input="buscarClientes(clienteSearch)" @focus="showClienteDropdown = true" type="text" placeholder="Buscar por nombre o DNI..." class="input-field w-full bg-black/40 text-sm font-bold text-white placeholder-white/30 border-white/10 focus:border-brand-red/50 py-2.5 pl-10 pr-3.5">
                                </div>
                                <button v-if="clienteSeleccionado" @click="limpiarCliente" class="px-3.5 text-white/40 hover:text-brand-red transition-colors bg-white/5 rounded-lg text-sm font-bold" title="Limpiar">X</button>
                                <button v-else @click="crearClienteRapido" type="button" class="px-4 bg-brand-red/10 text-brand-red hover:bg-brand-red hover:text-white transition-colors rounded-lg text-xs font-bold tracking-wider whitespace-nowrap border border-brand-red/30 py-2" title="Crear Cliente Rápido">+ NUEVO</button>
                            </div>
                            <p v-if="!clienteSeleccionado" class="text-xs text-white/30 mt-1.5 font-medium">Sin selección = Consumidor Final</p>
                            <p v-else class="text-xs text-emerald-400/90 mt-1.5 font-medium">Saldo actual en cuenta: {{ formatCurrency(clienteSeleccionado.saldo_actual) }}</p>
                            
                            <div v-if="showClienteDropdown && clienteSearch.length >= 1 && clientesResults.length === 0" class="absolute z-50 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-lg overflow-hidden shadow-xl p-4 text-center">
                                <p class="text-xs text-white/50 uppercase font-bold mb-3">No se encontraron clientes</p>
                                <button @click="crearClienteRapido" type="button" class="bg-brand-red text-white py-2 px-4 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-red-600 transition-colors">CREAR NUEVO CLIENTE</button>
                            </div>

                            <div v-if="showClienteDropdown && clientesResults.length" class="absolute z-50 w-full mt-1 bg-[#1a1a1a] border border-white/10 rounded-lg overflow-hidden shadow-xl">
                                <div v-for="c in clientesResults" :key="c.id" @mousedown.prevent="seleccionarCliente(c)" class="px-4 py-3 cursor-pointer hover:bg-white/5 hover:text-brand-red transition-colors border-b border-white/5 last:border-0">
                                    <div class="text-sm font-bold text-white capitalize">{{ c.user?.name }} {{ c.user?.apellido }}</div>
                                    <div class="text-xs text-white/50 font-mono mt-0.5">DNI: {{ c.user?.dni }} | Saldo: {{ formatCurrency(c.saldo_actual) }}</div>
                                </div>
                            </div>
                            <div v-if="showClienteDropdown" class="fixed inset-0 z-40" @click="showClienteDropdown = false"></div>
                        </div>
                    </div>

                    <!-- FUSED Product Search & Cart Container (Single Panel) -->
                    <div class="bg-black/40 border border-white/10 rounded-xl p-6 flex flex-col flex-1 gap-4">
                        <!-- Product Search Header -->
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3">
                            <label class="text-xs font-bold uppercase tracking-wider text-white/70">Agregar Productos</label>
                            <button type="button" @click="simularEscaneo" class="px-3.5 py-2 bg-white/5 hover:bg-white/10 text-white text-xs font-bold uppercase tracking-wider rounded-lg border border-white/10 transition-colors flex items-center gap-2">
                                📷 ESCANEAR
                            </button>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-white/40">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input v-model="libroSearch" @input="buscarLibros(libroSearch)" @focus="showLibroDropdown = true" type="text" placeholder="Buscar por título o ISBN..." class="input-field w-full bg-black/60 text-sm font-bold text-white border-white/10 focus:border-brand-red/50 py-2.5 pl-10 pr-3.5" :disabled="!$page.props.auth.empleado?.sucursal_id" :title="!$page.props.auth.empleado?.sucursal_id ? 'No tienes una sucursal asignada' : ''" :class="{'opacity-50 cursor-not-allowed': !$page.props.auth.empleado?.sucursal_id}">
                            
                            <div v-if="showLibroDropdown && librosResults.length" class="absolute top-full left-0 z-[60] w-full mt-1 bg-[#1c1c1c] border border-white/15 rounded-lg overflow-hidden shadow-2xl">
                                <div v-for="l in librosResults" :key="l.id" 
                                     @mousedown.prevent="(l.stock_disponible > 0 || l.permite_preventa) && seleccionarLibroParaAgregar(l)" 
                                     :class="(l.stock_disponible <= 0 && !l.permite_preventa) ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-white/5 hover:text-brand-red'" 
                                     class="px-4 py-3 transition-colors border-b border-white/5 last:border-0 flex justify-between items-center">
                                    <div>
                                        <div class="text-sm font-bold text-white">
                                            {{ l.master?.titulo }} 
                                            <span v-if="l.numero_tomo" class="text-brand-red ml-1">- Tomo {{ l.numero_tomo }}</span>
                                        </div>
                                        <div class="text-xs text-white/50 font-mono mt-1">
                                            ISBN: {{ l.isbn }} | {{ l.precio_actual ? formatCurrency(l.precio_actual.precio_venta) : 'Sin precio' }} - 
                                            <span :class="l.stock_disponible <= 0 ? 'text-red-400' : 'text-emerald-400/80'">
                                                Stock: {{ l.stock_disponible ?? '?' }}
                                                <span v-if="l.stock_disponible <= 0 && l.permite_preventa" class="ml-1 text-[9px] uppercase tracking-wider text-amber-400 bg-amber-400/10 px-1 py-0.5 rounded border border-amber-400/20">(Preventa)</span>
                                                <span v-if="l.stock_disponible <= 0 && !l.permite_preventa" class="ml-1 text-[9px] uppercase tracking-wider text-red-400 bg-red-400/10 px-1 py-0.5 rounded border border-red-400/20">(Agotado)</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="showLibroDropdown" class="fixed inset-0 z-50" @click="showLibroDropdown = false"></div>
                        </div>

                        <!-- Product Ready to Add Bar (Neutral grey background, subtle text, 4px left red accent line, attached to search) -->
                        <div v-if="libroSeleccionado" class="mt-1 p-3.5 bg-white/[0.04] border-y border-r border-white/10 border-l-4 border-l-brand-red rounded-r-xl flex flex-col sm:flex-row justify-between items-center gap-4 transition-all">
                            <div>
                                <div class="text-xs text-white/50 font-normal">Producto seleccionado:</div>
                                <div class="text-sm font-bold text-white mt-0.5">
                                    {{ libroSeleccionado.master?.titulo }}
                                    <span v-if="libroSeleccionado.numero_tomo" class="text-brand-red ml-1">- Tomo {{ libroSeleccionado.numero_tomo }}</span>
                                </div>
                                <div class="text-xs text-white/60 font-mono mt-0.5">Precio Unitario: {{ formatCurrency(libroSeleccionado.precio_actual?.precio_venta) }}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center bg-black/40 rounded-lg border border-white/10 overflow-hidden h-10">
                                    <button type="button" @click="cantidadSeleccionada > 1 ? cantidadSeleccionada-- : null" class="px-3.5 text-white/40 hover:text-white hover:bg-white/5 transition-colors font-bold text-base">-</button>
                                    <input type="number" v-model="cantidadSeleccionada" @change="validarCantidadParaAgregar" min="1" class="w-12 bg-transparent text-center text-sm font-bold p-0 border-0 focus:ring-0 text-white">
                                    <button type="button" @click="incrementarSeleccion" class="px-3.5 text-white/40 hover:text-white hover:bg-white/5 transition-colors font-bold text-base">+</button>
                                </div>
                                <button type="button" @click="confirmarAgregarAlCarrito" class="py-2.5 px-5 bg-brand-red hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider rounded-lg transition-colors">
                                    AGREGAR
                                </button>
                                <button type="button" @click="libroSeleccionado = null" class="p-2 text-white/40 hover:text-white transition-colors" title="Cancelar">
                                    ✕
                                </button>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="h-px bg-white/5 my-1"></div>

                        <!-- Cart Items List (Internal Scroll max-h-[38vh] overflow-y-auto) -->
                        <div class="flex-1 space-y-2.5 overflow-y-auto max-h-[38vh] pr-1">
                            <div v-for="(item, idx) in posForm.items" :key="idx" class="flex justify-between items-center p-3.5 bg-white/[0.02] border border-white/5 rounded-xl hover:border-white/20 transition-all group">
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-white group-hover:text-brand-red transition-colors">{{ item.titulo }}</div>
                                    <div class="flex items-center gap-3 mt-1.5">
                                        <div class="flex items-center bg-black/40 rounded-md border border-white/10 overflow-hidden h-7">
                                            <button type="button" @click="item.cantidad > 1 ? item.cantidad-- : removeItem(idx)" class="px-2.5 text-white/40 hover:text-white transition-colors font-bold text-xs">-</button>
                                            <input type="number" v-model="item.cantidad" @change="validarItemCarrito(item)" min="1" class="w-10 bg-transparent text-center text-xs font-bold p-0 border-0 focus:ring-0 text-white h-full">
                                            <button type="button" @click="incrementarItemCarrito(item)" class="px-2.5 text-white/40 hover:text-white transition-colors font-bold text-xs">+</button>
                                        </div>
                                        <div class="text-xs text-white/50 font-mono">x {{ formatCurrency(item.precio) }}</div>
                                    </div>
                                </div>
                                <div class="text-base font-bold text-white mr-5">
                                    {{ formatCurrency(item.cantidad * item.precio) }}
                                </div>
                                <button @click="removeItem(idx)" class="text-white/30 hover:text-brand-red transition-colors p-1.5" title="Eliminar ítem">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>

                            <div v-if="posForm.items.length === 0" class="h-36 flex items-center justify-center text-white/20 text-sm font-bold tracking-wider uppercase">
                                El carrito está vacío
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Section: Summary & Checkout -->
                <div class="w-full md:w-[380px] bg-black/90 p-6 md:p-8 flex flex-col justify-between overflow-y-auto border-l border-white/10">
                    <div>
                        <h4 class="text-sm font-bold uppercase tracking-widest text-white/80 mb-6 text-center">CHECKOUT</h4>
                        
                        <div class="space-y-6">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-white/50 block mb-2">Método de Cobro</label>
                                <select v-model="posForm.medio_pago" class="input-field w-full bg-[#181818] text-sm font-bold uppercase tracking-wider border-white/10 text-white focus:border-brand-red/50 py-2.5 px-3">
                                    <option value="Efectivo">💵 Efectivo Cash</option>
                                    <option value="Tarjeta">💳 Tarjeta / Posnet</option>
                                    <option value="Transferencia">📱 Transferencia</option>
                                    <option value="Cuenta Corriente">🏛️ Cuenta Corriente</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 space-y-5 pt-6 border-t border-white/10">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-white/60 uppercase tracking-wider">Total:</span>
                                <span class="text-3xl font-black text-white tracking-tight">{{ formatCurrency(subtotalPos) }}</span>
                            </div>

                            <div v-if="posForm.medio_pago === 'Cuenta Corriente' && clienteSeleccionado" class="flex justify-between items-center border-t border-white/10 pt-3">
                                <span class="text-sm text-white/60 font-medium">Saldo restante en cuenta:</span>
                                <span class="text-xl font-bold tracking-tight" :class="(clienteSeleccionado.saldo_actual - subtotalPos) < 0 ? 'text-brand-red' : 'text-emerald-400/90'">
                                    {{ formatCurrency(clienteSeleccionado.saldo_actual - subtotalPos) }}
                                </span>
                            </div>

                            <div v-if="posForm.medio_pago === 'Cuenta Corriente' && clienteSeleccionado && (clienteSeleccionado.saldo_actual - subtotalPos) < 0" class="mt-3 bg-white/5 border border-white/10 p-4 rounded-xl space-y-2.5">
                                <label class="text-xs font-medium text-white/80 block">
                                    El saldo restante ({{ formatCurrency(Math.abs(clienteSeleccionado.saldo_actual - subtotalPos)) }}) dejará la cuenta en negativo. ¿Desea abonar el excedente ahora?
                                </label>
                                <select v-model="posForm.metodo_pago_excedente" class="input-field w-full text-xs font-bold uppercase bg-black/60 text-white border border-white/15 focus:border-brand-red/50">
                                    <option :value="null">Dejar como deuda en Cuenta Corriente</option>
                                    <option value="Efectivo">💵 Pagar excedente en Efectivo</option>
                                    <option value="Tarjeta">💳 Pagar excedente con Tarjeta / Posnet</option>
                                    <option value="Transferencia">📱 Pagar excedente con Transferencia</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2.5 mt-4">
                            <button @click="submitVenta" :disabled="posForm.processing || posForm.items.length === 0" class="w-full py-3.5 bg-brand-red hover:bg-red-700 text-white font-bold text-sm uppercase tracking-wider rounded-xl transition-all shadow-none disabled:opacity-40">
                               {{ posForm.processing ? 'Sincronizando...' : 'Confirmar Pago' }}
                            </button>
                            <button @click="showPosModal = false" class="text-xs font-bold uppercase text-white/40 hover:text-white transition-colors tracking-wider py-2 text-center">Cancelar Operación</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Sales Detail Modal -->
        <div v-if="showDetailModal && selectedVenta" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/70 backdrop-blur-md" @click="closeDetailModal"></div>
            <div class="relative w-full max-w-3xl card p-0 border border-white/10 overflow-hidden shadow-2xl bg-[#141414]">
                <div class="bg-black/90 py-5 px-8 flex justify-between items-center border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-black uppercase tracking-tight text-white not-italic">
                            Detalle de Ticket <span class="text-brand-red font-mono font-black text-2xl ml-1">#TK-{{ String(selectedVenta.id).padStart(6, '0') }}</span>
                        </h3>
                        <span v-if="selectedVenta.motivo_pendiente === 'Acumulación'" class="text-xs font-bold uppercase tracking-widest px-2.5 py-1 rounded bg-[#25D366]/20 text-white border border-[#25D366]">Acumulado</span>
                    </div>
                    <div class="text-right flex items-center gap-3">
                        <div class="text-sm font-bold text-white/90">{{ formatTicketDate(selectedVenta.fecha) }}</div>
                        <span class="text-xs font-bold uppercase tracking-widest px-2.5 py-1 rounded border border-white/20 bg-white/[0.08] text-white/95 not-italic">
                            {{ selectedVenta.tipo }}
                        </span>
                    </div>
                </div>

                <div class="p-8">
                    <div class="grid grid-cols-3 gap-6 mb-8 pb-8 border-b border-white/10">
                        <div class="space-y-1.5 text-left">
                            <div class="text-xs font-bold uppercase tracking-wider text-white/50">CLIENTE</div>
                            <Link v-if="selectedVenta.cliente_id" :href="route('clientes.show', selectedVenta.cliente_id)" class="text-base text-white font-bold block hover:text-brand-red transition-colors capitalize">
                                {{ getClienteNombre(selectedVenta) }}
                            </Link>
                            <div v-else class="text-base text-white font-bold capitalize">
                                {{ getClienteNombre(selectedVenta) }}
                            </div>
                        </div>
                        <div class="space-y-1.5 text-center">
                            <div class="text-xs font-bold uppercase tracking-wider text-white/50">SUCURSAL</div>
                            <div class="text-base text-white font-bold">{{ formatSucursalName(selectedVenta.sucursal?.nombre) }}</div>
                        </div>
                        <div class="space-y-1.5 text-right">
                            <div class="text-xs font-bold uppercase tracking-wider text-white/50">MEDIO DE PAGO</div>
                            <div class="text-base text-white font-bold capitalize">{{ selectedVenta.metodo_pago || 'No especificado' }}</div>
                        </div>
                    </div>

                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs font-bold uppercase tracking-wider text-white/50 border-b border-white/10">
                                <th class="py-3 px-3">PRODUCTO</th>
                                <th class="py-3 px-3 text-center w-24">CANT.</th>
                                <th class="py-3 px-3 text-right w-32">P. UNIT</th>
                                <th class="py-3 px-3 text-right w-36">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="item in selectedVenta.detalles" :key="item.id" class="group hover:bg-white/[0.03] transition-colors">
                                <td class="py-3.5 px-3">
                                    <div class="text-base text-white font-bold group-hover:text-brand-red transition-colors">{{ item.libro?.master?.titulo }} - Tomo {{ item.libro?.numero_tomo || 'Único' }}</div>
                                    <div class="text-xs text-white/50 font-mono mt-0.5">ISBN: {{ item.libro?.isbn }}</div>
                                </td>
                                <td class="py-3.5 px-3 text-center text-base text-white font-bold">{{ item.cantidad }}</td>
                                <td class="py-3.5 px-3 text-right text-base text-white/80 font-bold">{{ formatCurrency(item.precio_unitario) }}</td>
                                <td class="py-3.5 px-3 text-right text-base text-white font-bold">{{ formatCurrency(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-white/10">
                                <td colspan="3" class="py-4 px-3 text-right text-xs font-bold uppercase tracking-wider text-white/50">Total de la Compra</td>
                                <td class="py-4 px-3 text-right text-2xl font-black text-white whitespace-nowrap">{{ formatCurrency(selectedVenta.total) }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div v-if="selectedVenta.estado === 'pendiente_pago' && (selectedVenta.transacciones?.filter(t => t.tipo === 'ingreso').reduce((sum, t) => sum + parseFloat(t.monto), 0) || 0) > 0" class="flex justify-between items-center bg-white/[0.02] p-6 border border-white/5 rounded-xl mt-4">
                        <div class="text-xs font-bold uppercase tracking-normal text-white/40">Abonado (Saldo a favor/Parcial)</div>
                        <div class="text-2xl font-bold text-brand-red">
                            {{ formatCurrency(selectedVenta.transacciones?.filter(t => t.tipo === 'ingreso').reduce((sum, t) => sum + parseFloat(t.monto), 0) || 0) }}
                        </div>
                    </div>

                    <div v-if="selectedVenta.estado === 'pendiente_pago'" class="bg-brand-red/10 border border-brand-red/20 p-6 rounded-xl mt-4 space-y-4">
                        <!-- Resta a pagar -->
                        <div class="flex justify-between items-center">
                            <div class="text-xs font-bold uppercase tracking-normal text-white">Resta a pagar</div>
                            <div class="text-3xl font-bold text-white">
                                {{ formatCurrency(selectedVenta.total - (selectedVenta.transacciones?.filter(t => t.tipo === 'ingreso').reduce((sum, t) => sum + parseFloat(t.monto), 0) || 0)) }}
                            </div>
                        </div>

                        <!-- Confirmación de Pago si es Online -->
                        <div v-if="selectedVenta.tipo === 'online'" class="pt-4 border-t border-brand-red/20 flex flex-col gap-3">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                                <div class="text-sm font-black text-white">¿El pago ya impactó?</div>
                                <button @click="confirmarPago" :disabled="estadoForm.processing" class="btn-primary text-xs px-6 py-2 w-full sm:w-auto">
                                    CONFIRMAR PAGO
                                </button>
                            </div>
                            <div v-if="selectedVenta.comprobante_path" class="pt-2 border-t border-brand-red/10 flex justify-between items-center">
                                <span class="text-xs text-white/70">✅ El cliente subió un comprobante.</span>
                                <a :href="route('mi-cuenta.comprobante.ver', selectedVenta.id)" target="_blank" class="text-xs font-bold text-brand-red uppercase tracking-widest hover:underline">Ver adjunto</a>
                            </div>
                        </div>
                    </div>

                    <!-- Botón rápido para Logística (Esperando Traslado) -->
                    <div v-if="selectedVenta.estado === 'esperando_traslado'" class="mt-4 p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl flex flex-col gap-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-white/50 mb-1">Logística y Traslados</div>
                                <div class="text-sm font-bold text-white">Esta venta requiere un traslado de stock.</div>
                            </div>
                            <Link :href="route('logistica.index')" class="px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors shadow-lg shadow-blue-500/20 whitespace-nowrap">
                                IR A LOGÍSTICA
                            </Link>
                        </div>
                    </div>

                    <!-- Barra Inferior Unificada (Footer Actions) -->
                    <div class="mt-8 pt-6 border-t border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div v-if="puedeEditarEstado" class="flex flex-wrap items-center gap-3">
                            <div class="w-auto min-w-[220px]">
                                <label class="text-xs font-bold uppercase tracking-wider text-white/50 mb-1.5 block">Estado de la Venta</label>
                                <select v-model="estadoForm.estado" class="input-field w-full text-xs font-bold uppercase bg-black/60 border-white/15 px-3.5 py-2 rounded-lg text-white" title="Estado de la Venta">
                                    <option v-for="e in estadoOpcionesFiltradas" :key="e.value" :value="e.value">{{ e.label }}</option>
                                </select>
                            </div>
                            <div v-if="estadoForm.estado === 'en_preparacion' || estadoForm.estado === 'enviado'" class="w-auto min-w-[200px]">
                                <label class="text-xs font-bold uppercase tracking-wider text-brand-red mb-1.5 block">Dirección de Envío</label>
                                <DireccionAutocomplete v-model="estadoForm.direccion_envio" @select="onSeleccionarDireccionVenta" placeholder="Ej: San Martín 123, Rosario" class="input-field w-full text-xs font-bold uppercase bg-black/60 border-brand-red/30 focus:border-brand-red py-2 px-3" />
                            </div>
                            <div v-if="selectedVenta.tipo_envio === 'correo_nacional'" class="w-auto min-w-[200px]">
                                <label class="text-xs font-bold uppercase tracking-wider text-brand-red mb-1.5 block">Código de Seguimiento</label>
                                <input type="text" v-model="estadoForm.tracking_code" placeholder="Ej: SD321876451AR" class="input-field w-full text-xs font-bold uppercase bg-black/60 border-brand-red/30 focus:border-brand-red py-2 px-3" />
                            </div>
                        </div>
                        <div v-else></div>

                        <div class="flex items-center gap-3">
                            <button 
                                v-if="isFormModified" 
                                type="button" 
                                @click="cambiarEstado" 
                                :disabled="estadoForm.processing"
                                class="px-8 py-2.5 rounded-xl bg-brand-red text-white font-bold hover:bg-red-600 transition-all text-xs tracking-wider cursor-pointer disabled:opacity-50"
                            >
                                {{ estadoForm.processing ? 'GUARDANDO...' : 'GUARDAR CAMBIOS' }}
                            </button>
                            <button 
                                type="button" 
                                @click="closeDetailModal" 
                                class="px-8 py-2.5 rounded-xl border border-white/20 hover:border-white text-white/70 hover:text-white transition-all text-xs font-bold tracking-wider bg-transparent cursor-pointer"
                            >
                                Cerrar Detalle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
