<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    pedidos: Object,
    acumuladosListos: { type: Array, default: () => [] },
    pagos: Array,
    usuario: Object,
    saldoCuenta: [Number, String],
});

const mostrarPagos = ref(false);

const darkSwal = Swal.mixin({
    background: '#131316',
    color: '#ffffff',
    buttonsStyling: false,
    customClass: {
        popup: 'border border-white/10 rounded-2xl p-6 shadow-2xl bg-[#131316] page-micuenta',
        title: 'text-xl font-bold text-white tracking-tight',
        htmlContainer: 'text-sm text-zinc-300 font-medium mt-2 leading-relaxed',
        confirmButton: 'px-6 py-3 rounded-xl bg-white hover:bg-zinc-200 text-black font-bold text-sm transition-all shadow-md active:scale-95 mx-1 cursor-pointer',
        cancelButton: 'px-6 py-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-sm border border-white/10 transition-all active:scale-95 mx-1 cursor-pointer',
        actions: 'mt-6 flex items-center justify-end gap-2'
    }
});

const tab = ref('perfil');
const expandedPedidos = ref([]);

const togglePedidoExpand = (id) => {
    const idx = expandedPedidos.value.indexOf(id);
    if (idx > -1) {
        expandedPedidos.value.splice(idx, 1);
    } else {
        expandedPedidos.value.push(id);
    }
};

const expandedPagos = ref([]);

const togglePagoExpand = (id) => {
    const idx = expandedPagos.value.indexOf(id);
    if (idx > -1) {
        expandedPagos.value.splice(idx, 1);
    } else {
        expandedPagos.value.push(id);
    }
};

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const formatFecha = (fecha) => {
    if (!fecha) return '';
    const datePart = String(fecha).split('T')[0].split(' ')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    const d = new Date(fecha);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
};

const showCurrentPassword = ref(false);
const showNewPassword     = ref(false);
const showConfirmPassword = ref(false);

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

const activeUploadPedidoId = ref(null);

const uploadForm = useForm({
    comprobante: null,
});

const handleFileChange = (event, pedidoId) => {
    uploadForm.clearErrors('comprobante');
    const file = event.target.files[0];
    if (!file) return;

    const MAX_SIZE_MB = 7;
    const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;

    if (file.size > MAX_SIZE_BYTES) {
        event.target.value = '';
        uploadForm.comprobante = null;
        activeUploadPedidoId.value = null;
        uploadForm.setError('comprobante', `El archivo es demasiado grande (${(file.size / (1024 * 1024)).toFixed(1)} MB). El límite máximo permitido es de ${MAX_SIZE_MB} MB. Por favor elegí una captura o foto más liviana.`);
        return;
    }

    activeUploadPedidoId.value = pedidoId;
    uploadForm.comprobante = file;
};

const uploadComprobante = (pedidoId) => {
    uploadForm.post(route('mi-cuenta.comprobante', pedidoId), {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            activeUploadPedidoId.value = null;
            darkSwal.fire({
                title: 'Comprobante Enviado',
                text: 'El comprobante ha sido subido con éxito y está pendiente de verificación.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
        onError: (errs) => {
            if (errs.comprobante) return;
            uploadForm.setError('comprobante', 'No se pudo subir el archivo. Verificá el tamaño e intentá nuevamente.');
        }
    });
};

const deleteComprobante = (pedidoId) => {
    darkSwal.fire({
        title: '¿Eliminar comprobante?',
        text: '¿Estás seguro de que querés eliminar el comprobante adjunto?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('mi-cuenta.comprobante.delete', pedidoId), {
                preserveScroll: true,
                onSuccess: () => {
                    darkSwal.fire({
                        title: 'Eliminado',
                        text: 'El comprobante ha sido removido.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
};

const submitPassword = () => {
    passwordForm.put(route('mi-cuenta.password'), {
        onSuccess: () => {
            passwordForm.reset();
            darkSwal.fire({
                title: 'Contraseña Actualizada',
                text: 'Tu contraseña ha sido cambiada con éxito.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
    });
};

const estadoConfig = {
    pendiente_pago:     { label: 'Pendiente de pago',  bgDot: 'bg-amber-400' },
    en_preventa:        { label: 'Esperando preventa', bgDot: 'bg-fuchsia-400' },
    en_preparacion:     { label: 'En preparación',     bgDot: 'bg-sky-400' },
    esperando_traslado: { label: 'Esperando traslado', bgDot: 'bg-purple-400' },
    listo_para_retiro:  { label: 'Listo para retiro',  bgDot: 'bg-emerald-400' },
    acumulado:          { label: 'Acumulado',          bgDot: 'bg-amber-400' },
    enviado:            { label: 'Enviado',             bgDot: 'bg-indigo-400' },
    entregado:          { label: 'Entregado',           bgDot: 'bg-emerald-400' },
    retirado:           { label: 'Retirado',            bgDot: 'bg-emerald-400' },
    completada:         { label: 'Completada',          bgDot: 'bg-emerald-400' },
    finalizado:         { label: 'Finalizado',          bgDot: 'bg-emerald-400' },
    cancelado:          { label: 'Cancelado',           bgDot: 'bg-rose-500' },
};

const getTipoEnvioLabel = (tipo, tipoVenta) => {
    if (tipoVenta === 'presencial') return 'Compra en local';
    if (tipo === 'retiro') return 'Retiro en sucursal';
    if (tipo === 'acumulacion') return 'Acumulación en sucursal';
    if (tipo === 'correo_sucursal') return 'Envío a Sucursal Correo Argentino';
    return 'Envío a domicilio';
};

// Se calcula en el backend con TODOS los pedidos, no solo los de la pagina actual
const tieneAcumulados = computed(() => props.acumuladosListos.length > 0);
const totalLibrosAcumulados = computed(() =>
    props.acumuladosListos.reduce((t, p) => t + p.items.reduce((s, i) => s + Number(i.cantidad || 0), 0), 0)
);

// --- Solicitar envío de acumulados: mismo formulario de dirección que el
// checkout (provincia/localidad/autocompletado de Photon/CP), para respetar
// que un cliente pueda pedir el envío a otra provincia y que quede marcado
// como Correo Nacional igual que en una compra nueva, no solo "domicilio".
const showConsolidarModal = ref(false);

const provincias = [
    'Buenos Aires', 'Catamarca', 'Chaco', 'Chubut', 'Ciudad Autónoma de Buenos Aires',
    'Córdoba', 'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja',
    'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan', 'San Luis',
    'Santa Cruz', 'Santa Fe', 'Santiago del Estero', 'Tierra del Fuego', 'Tucumán'
];

// Gran Rosario: zona de reparto local sin recargo de envío
const localidadesSantaFe = [
    'Rosario', 'Funes', 'Roldán', 'Pérez', 'Granadero Baigorria',
    'Villa Gobernador Gálvez', 'San Lorenzo', 'Capitán Bermúdez',
    'Puerto General San Martín', 'Fray Luis Beltrán',
];

const provincia = ref('Santa Fe');
const localidad = ref('');
const otraLocalidadSantaFe = ref('');
const direccionInput = ref('');
const direccionFormatted = ref('');
const addressSelected = ref(false);
const piso = ref('');
const depto = ref('');
const cp = ref('');
const medioPagoConsolidar = ref('Tarjeta');
const latitudConsolidar = ref(null);
const longitudConsolidar = ref(null);
const comentarioConsolidar = ref('');

const sugerencias = ref([]);
const mostrarSugerencias = ref(false);
const buscandoDireccion = ref(false);
let debounceTimerConsolidar = null;
let ultimaConsultaIdConsolidar = 0;

const localidadEfectiva = computed(() => {
    if (provincia.value === 'Santa Fe') {
        if (localidad.value === '__otra__') return otraLocalidadSantaFe.value.trim();
        return localidad.value.trim();
    }
    return localidad.value.trim();
});

const esEnvioLocal = computed(() => {
    return provincia.value === 'Santa Fe' && localidadesSantaFe.includes(localidad.value);
});

const direccionHabilitada = computed(() => {
    if (provincia.value === 'Santa Fe') {
        if (localidad.value === '__otra__') return !!otraLocalidadSantaFe.value.trim();
        return !!localidad.value;
    }
    return !!provincia.value;
});

const normalizar = (str) => (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(new RegExp('[̀-ͯ]', 'g'), '');

const coincideLocalidad = (f) => {
    const loc = localidadEfectiva.value;
    if (!loc) return true;
    const p = f.properties;
    const objetivo = normalizar(loc);
    const candidatos = [p.city, p.district, p.county].filter(Boolean).map(normalizar);
    return candidatos.some(c => c.includes(objetivo) || objetivo.includes(c));
};

const buscarDirecciones = async (query) => {
    if (query.trim().length < 3 || !direccionHabilitada.value) {
        sugerencias.value = [];
        mostrarSugerencias.value = false;
        return;
    }

    const consultaId = ++ultimaConsultaIdConsolidar;
    buscandoDireccion.value = true;
    try {
        const contexto = [query, localidadEfectiva.value, provincia.value, 'Argentina'].filter(Boolean).join(', ');
        const params = new URLSearchParams({
            q: contexto,
            limit: '8',
            lat: '-32.9468',
            lon: '-60.6393',
        });
        const res = await fetch(`https://photon.komoot.io/api/?${params.toString()}`);
        const data = await res.json();
        if (consultaId !== ultimaConsultaIdConsolidar) return;
        const features = data.features || [];
        const filtradas = features.filter(coincideLocalidad);
        sugerencias.value = filtradas.length > 0 ? filtradas : features;
        mostrarSugerencias.value = sugerencias.value.length > 0;
    } catch {
        sugerencias.value = [];
        mostrarSugerencias.value = false;
    } finally {
        if (consultaId === ultimaConsultaIdConsolidar) buscandoDireccion.value = false;
    }
};

const formatearSugerencia = (f) => {
    const p = f.properties;
    const calle = p.street || p.name || '';
    const conAltura = p.housenumber ? `${calle} ${p.housenumber}` : calle;
    return [conAltura, p.city, p.state].filter(Boolean).join(', ');
};

const seleccionarSugerencia = (f) => {
    const p = f.properties;
    const texto = formatearSugerencia(f);
    direccionInput.value = texto;
    direccionFormatted.value = texto;
    addressSelected.value = true;
    sugerencias.value = [];
    mostrarSugerencias.value = false;

    const [lon, lat] = f.geometry?.coordinates ?? [];
    latitudConsolidar.value = lat ?? null;
    longitudConsolidar.value = lon ?? null;

    if (provincia.value !== 'Santa Fe') {
        localidad.value = p.city || p.district || p.county || provincia.value;
    } else if (localidad.value === '__otra__' && !otraLocalidadSantaFe.value.trim()) {
        otraLocalidadSantaFe.value = p.city || p.district || p.county || '';
    }
    if (p.postcode) {
        cp.value = p.postcode;
    }
};

const resetearCamposDireccion = () => {
    direccionInput.value = '';
    direccionFormatted.value = '';
    addressSelected.value = false;
    latitudConsolidar.value = null;
    longitudConsolidar.value = null;
    sugerencias.value = [];
    mostrarSugerencias.value = false;
};

watch(provincia, () => {
    localidad.value = '';
    otraLocalidadSantaFe.value = '';
    cp.value = '';
    resetearCamposDireccion();
});

watch(localidad, (val) => {
    if (val !== '__otra__') otraLocalidadSantaFe.value = '';
    resetearCamposDireccion();
});

watch(otraLocalidadSantaFe, resetearCamposDireccion);

watch(direccionInput, (val) => {
    if (addressSelected.value && val !== direccionFormatted.value) {
        addressSelected.value = false;
        direccionFormatted.value = '';
        latitudConsolidar.value = null;
        longitudConsolidar.value = null;
    }
    clearTimeout(debounceTimerConsolidar);
    if (!addressSelected.value) {
        debounceTimerConsolidar = setTimeout(() => buscarDirecciones(val), 400);
    }
});

onUnmounted(() => clearTimeout(debounceTimerConsolidar));

const puedeSolicitarEnvio = computed(() => {
    const direccionOk = direccionInput.value.trim().length > 0
        && !!provincia.value
        && !!localidadEfectiva.value
        && cp.value.trim().length > 0;
    if (!direccionOk) return false;
    return esEnvioLocal.value || !!medioPagoConsolidar.value;
});

const consolidarForm = useForm({
    modo: 'domicilio',
    direccion_envio: '',
    latitud: null,
    longitud: null,
    medio_pago: null,
});

const solicitarEnvioAcumulados = () => {
    provincia.value = 'Santa Fe';
    localidad.value = '';
    otraLocalidadSantaFe.value = '';
    piso.value = '';
    depto.value = '';
    cp.value = '';
    comentarioConsolidar.value = '';
    medioPagoConsolidar.value = 'Tarjeta';
    resetearCamposDireccion();
    showConsolidarModal.value = true;
};

const submitConsolidar = () => {
    if (!puedeSolicitarEnvio.value) return;

    let direccion = direccionFormatted.value || direccionInput.value.trim();
    if (piso.value.trim())  direccion += `, Piso ${piso.value.trim()}`;
    if (depto.value.trim()) direccion += `, Depto ${depto.value.trim()}`;
    if (cp.value.trim())    direccion += `, CP ${cp.value.trim()}`;
    if (localidadEfectiva.value) direccion += `, ${localidadEfectiva.value}`;
    if (provincia.value)    direccion += `, ${provincia.value}`;
    if (comentarioConsolidar.value.trim()) direccion += ` | Obs: ${comentarioConsolidar.value.trim()}`;

    consolidarForm.modo            = esEnvioLocal.value ? 'domicilio' : 'correo_nacional';
    consolidarForm.direccion_envio = direccion;
    consolidarForm.latitud         = latitudConsolidar.value;
    consolidarForm.longitud        = longitudConsolidar.value;
    consolidarForm.medio_pago      = esEnvioLocal.value ? null : medioPagoConsolidar.value;

    consolidarForm.post(route('mi-cuenta.consolidar-acumulados'), {
        preserveScroll: true,
        onSuccess: () => {
            showConsolidarModal.value = false;
            const mensaje = usePage().props.flash?.message || 'Solicitaste el envío de tus pedidos acumulados.';
            consolidarForm.reset();
            darkSwal.fire({
                title: '¡Listo!',
                text: mensaje,
                icon: 'success',
            });
        },
    });
};
</script>

<template>
    <Head title="Mi Cuenta" />

    <PublicLayout>
        <div class="page-micuenta max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="max-w-4xl mx-auto space-y-8">

            <!-- Header Profile Info Card -->
            <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center flex-shrink-0">
                    <span class="text-xl font-bold text-white uppercase">{{ usuario.name?.charAt(0) }}</span>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight uppercase">
                        {{ usuario.name }} {{ usuario.apellido }}
                    </h1>
                    <p class="text-xs text-zinc-400 font-medium mt-0.5">Cliente desde {{ formatFecha(usuario.created_at) }}</p>
                </div>
            </div>

            <!-- Cuenta Corriente: saldo a favor o deuda -->
            <div v-if="saldoCuenta !== null && saldoCuenta !== undefined && Number(saldoCuenta) !== 0"
                 class="bg-[#131316] border rounded-2xl p-5 shadow-xl flex items-center justify-between"
                 :class="Number(saldoCuenta) < 0 ? 'border-rose-500/20' : 'border-emerald-500/20'">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        {{ Number(saldoCuenta) < 0 ? 'Deuda en cuenta corriente' : 'Saldo a favor' }}
                    </p>
                    <p class="text-[11px] text-zinc-500 mt-0.5">Incluye compras en local abonadas con cuenta corriente.</p>
                </div>
                <p class="text-xl font-bold font-mono" :class="Number(saldoCuenta) < 0 ? 'text-rose-400' : 'text-emerald-400'">
                    {{ formatPrecio(Math.abs(Number(saldoCuenta))) }}
                </p>
            </div>

            <!-- Tabs Container -->
            <div class="bg-[#131316] border border-white/5 rounded-2xl p-2 shadow-xl">
                <div class="flex items-center gap-2">
                    <button
                        @click="tab = 'perfil'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all"
                        :class="tab === 'perfil' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                    >
                        MI PERFIL
                    </button>
                    <button
                        @click="tab = 'pedidos'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2"
                        :class="tab === 'pedidos' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                    >
                        <span>MIS PEDIDOS</span>
                        <span v-if="pedidos.total" class="text-xs px-2 py-0.5 rounded-xl font-bold font-mono" :class="tab === 'pedidos' ? 'bg-black/10 text-black' : 'bg-white/10 text-white'">
                            {{ pedidos.total }}
                        </span>
                    </button>
                    <button
                        v-if="pagos && pagos.length > 0"
                        @click="tab = 'pagos'"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2"
                        :class="tab === 'pagos' ? 'bg-white text-black shadow-md' : 'text-zinc-400 hover:text-white bg-transparent'"
                    >
                        <span>MIS PAGOS</span>
                        <span class="text-xs px-2 py-0.5 rounded-xl font-bold font-mono" :class="tab === 'pagos' ? 'bg-black/10 text-black' : 'bg-white/10 text-white'">
                            {{ pagos.length }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Tab: Mi Perfil -->
            <div v-if="tab === 'perfil'" class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                <!-- Info Personales -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-4">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-3">Datos Personales</h2>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-1">
                            <span class="text-zinc-400 font-medium">Nombre completo:</span>
                            <span class="font-bold text-white">{{ usuario.name }} {{ usuario.apellido }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-t border-white/5 pt-3">
                            <span class="text-zinc-400 font-medium">Email:</span>
                            <span class="font-bold text-white">{{ usuario.email }}</span>
                        </div>
                    </div>
                </div>

                <!-- Cambiar contraseña -->
                <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl space-y-4">
                    <h2 class="text-sm font-bold text-white uppercase tracking-wider border-b border-white/5 pb-3">Cambiar Contraseña</h2>

                    <form @submit.prevent="submitPassword" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Contraseña actual *</label>
                            <div class="relative">
                                <input
                                    v-model="passwordForm.current_password"
                                    :type="showCurrentPassword ? 'text' : 'password'"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 pr-10 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                    :class="{ 'border-rose-500': passwordForm.errors.current_password }"
                                />
                                <button type="button" @click="showCurrentPassword = !showCurrentPassword" class="absolute right-3 top-3 text-zinc-500 hover:text-white transition-colors">
                                    <svg v-if="!showCurrentPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.current_password" class="text-rose-400 text-xs font-semibold mt-1">{{ passwordForm.errors.current_password }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Nueva contraseña *</label>
                            <div class="relative">
                                <input
                                    v-model="passwordForm.password"
                                    :type="showNewPassword ? 'text' : 'password'"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 pr-10 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                    :class="{ 'border-rose-500': passwordForm.errors.password }"
                                />
                                <button type="button" @click="showNewPassword = !showNewPassword" class="absolute right-3 top-3 text-zinc-500 hover:text-white transition-colors">
                                    <svg v-if="!showNewPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.password" class="text-rose-400 text-xs font-semibold mt-1">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-zinc-400 mb-1">Confirmar nueva contraseña *</label>
                            <div class="relative">
                                <input
                                    v-model="passwordForm.password_confirmation"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 pr-10 text-sm text-white font-medium focus:outline-none focus:border-white/30"
                                    :class="{ 'border-rose-500': passwordForm.errors.password_confirmation }"
                                />
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute right-3 top-3 text-zinc-500 hover:text-white transition-colors">
                                    <svg v-if="!showConfirmPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.password_confirmation" class="text-rose-400 text-xs font-semibold mt-1">{{ passwordForm.errors.password_confirmation }}</p>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50"
                            >
                                {{ passwordForm.processing ? 'Guardando...' : 'Cambiar Contraseña' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab: Mis Pedidos -->
            <div v-if="tab === 'pedidos'" class="space-y-6">

                <div v-if="!pedidos.data.length" class="bg-[#131316] border border-white/5 rounded-2xl p-12 text-center text-zinc-500 italic space-y-4">
                    <p>Todavía no hiciste ningún pedido. Explorá el catálogo y hacé tu primera compra.</p>
                    <Link :href="route('catalogo.index')" class="inline-block px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 not-italic">
                        Ver Catálogo
                    </Link>
                </div>

                <div v-else class="space-y-4">

                    <!-- Banner de Acumulados -->
                    <div v-if="tieneAcumulados" class="bg-[#131316] border border-amber-500/20 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-1">Pedidos Acumulados</h3>
                            <p class="text-zinc-400 text-xs font-medium">
                                Tenés {{ acumuladosListos.length }} {{ acumuladosListos.length === 1 ? 'pedido listo' : 'pedidos listos' }} para enviar. Solicitá el envío para recibirlos todos juntos pagando un solo envío.
                            </p>
                        </div>
                        <button @click="solicitarEnvioAcumulados" class="px-5 py-2.5 bg-amber-400 hover:bg-amber-300 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 whitespace-nowrap">
                            Solicitar Envío
                        </button>
                    </div>

                    <div
                        v-for="pedido in pedidos.data"
                        :key="pedido.id"
                        class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl hover:border-white/10 transition-all"
                        :class="{ 'opacity-70': pedido.estado === 'cancelado' }"
                    >
                        <!-- Card Header (Clickable to Expand / Collapse) -->
                        <div
                            @click="togglePedidoExpand(pedido.id)"
                            class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 cursor-pointer hover:bg-white/[0.02] transition-colors select-none"
                        >
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-zinc-400 shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>

                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-white font-mono font-bold text-base tracking-tight">
                                            #TK-{{ String(pedido.id).padStart(6, '0') }}
                                        </span>
                                        <span v-if="pedido.es_cobro_envio" class="text-[10px] font-bold uppercase tracking-wider text-indigo-300 bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 rounded-md">Envío de pedidos acumulados</span>
                                        <span v-else-if="pedido.envio_conjunto_id" class="text-[10px] font-bold uppercase tracking-wider text-zinc-300 bg-white/5 border border-white/10 px-2 py-0.5 rounded-md">Incluido en envío #TK-{{ String(pedido.envio_conjunto_id).padStart(6, '0') }}</span>

                                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="estadoConfig[pedido.estado]?.bgDot || 'bg-zinc-500'"></span>
                                            <span>{{ estadoConfig[pedido.estado]?.label ?? pedido.estado }}</span>
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-zinc-400">
                                        <span>{{ formatFecha(pedido.fecha) }}</span>
                                        <span v-if="pedido.sucursal_nombre" class="text-zinc-600">•</span>
                                        <span v-if="pedido.sucursal_nombre">{{ pedido.sucursal_nombre }}</span>
                                        <span class="text-zinc-600">•</span>
                                        <span>{{ getTipoEnvioLabel(pedido.tipo_envio, pedido.tipo) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Total Price + Chevron Arrow -->
                            <div class="flex items-center justify-between md:justify-end gap-5 border-t md:border-t-0 border-white/5 pt-3 md:pt-0">
                                <p class="text-base sm:text-lg font-bold text-white font-mono tracking-tight">
                                    {{ formatPrecio(pedido.total) }}
                                </p>

                                <div
                                    class="w-8 h-8 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-zinc-400 transition-transform duration-200"
                                    :class="{ 'rotate-180 bg-white/10 text-white': expandedPedidos.includes(pedido.id) }"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Collapsible Order Details Content -->
                        <div v-if="expandedPedidos.includes(pedido.id)" class="border-t border-white/5 bg-[#0d0d0f] p-5 space-y-4">
                            <!-- Cobro del envio de acumulados: muestra que pedidos incluye -->
                            <template v-if="pedido.es_cobro_envio">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-white/5 pb-2">
                                    Envío por Correo Nacional de {{ pedido.pedidos_incluidos.length }} {{ pedido.pedidos_incluidos.length === 1 ? 'pedido' : 'pedidos' }}
                                </h4>
                                <div class="flex justify-between items-center text-sm py-1.5">
                                    <span class="text-zinc-300 font-medium">Costo del envío</span>
                                    <span class="font-bold font-mono text-zinc-400 ml-4 shrink-0">{{ formatPrecio(pedido.total) }}</span>
                                </div>
                                <div v-if="pedido.pedidos_incluidos.length" class="flex flex-wrap gap-2">
                                    <span v-for="id in pedido.pedidos_incluidos" :key="id" class="px-3 py-1 rounded-xl bg-white/5 border border-white/10 text-xs font-mono font-bold text-zinc-300">#TK-{{ String(id).padStart(6, '0') }}</span>
                                </div>
                                <p v-if="pedido.estado === 'pendiente_pago'" class="text-xs text-zinc-500">Cuando se confirme el pago del envío, despachamos todos estos pedidos juntos.</p>
                            </template>

                            <h4 v-else class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-white/5 pb-2">
                                Detalle del pedido ({{ pedido.items?.length || 0 }} {{ (pedido.items?.length === 1) ? 'producto' : 'productos' }})
                            </h4>

                            <!-- Items List -->
                            <div v-if="!pedido.es_cobro_envio" class="space-y-2">
                                <div v-for="(item, i) in pedido.items" :key="i" class="flex justify-between items-center text-sm py-1.5 border-b border-white/5 last:border-0">
                                    <span class="text-zinc-300 font-medium truncate">
                                        {{ item.titulo }}
                                        <span class="text-zinc-500 text-xs font-bold ml-1">x{{ item.cantidad }}</span>
                                    </span>
                                    <span class="font-bold font-mono text-zinc-400 ml-4 shrink-0">{{ formatPrecio(item.subtotal) }}</span>
                                </div>
                            </div>

                            <!-- Banner Listo para retirar -->
                            <div v-if="pedido.estado === 'listo_para_retiro' && ['retiro', 'acumulacion'].includes(pedido.tipo_envio)" class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-start gap-3 mt-4">
                                <span class="text-xl">🎉</span>
                                <div>
                                    <p class="text-emerald-400 font-bold text-xs uppercase tracking-wider">¡Tu pedido está listo!</p>
                                    <p class="text-zinc-300 text-xs mt-1 font-medium leading-relaxed">
                                        Ya podés pasar a retirar tus libros por la sucursal <strong>{{ pedido.sucursal_nombre }}</strong>.
                                    </p>
                                </div>
                            </div>

                            <!-- Banner Tracking Code para Envíos de Correo -->
                            <div v-if="['correo_nacional', 'correo_sucursal'].includes(pedido.tipo_envio) && pedido.tracking_code" class="p-4 bg-indigo-500/10 border border-indigo-500/20 rounded-2xl flex items-start gap-3 mt-4">
                                <span class="text-xl">📦</span>
                                <div>
                                    <p class="text-indigo-400 font-bold text-xs uppercase tracking-wider">Código de Seguimiento / Tracking</p>
                                    <p class="text-zinc-200 text-xs mt-1 font-mono font-bold tracking-wider">
                                        {{ pedido.tracking_code }}
                                    </p>
                                    <p v-if="pedido.direccion_envio" class="text-zinc-400 text-xs mt-0.5 font-medium">
                                        Destino: {{ pedido.direccion_envio }}
                                    </p>
                                </div>
                            </div>

                            <!-- Subir Comprobante (Transferencia Pendiente) -->
                            <div v-if="pedido.estado === 'pendiente_pago' && pedido.metodo_pago === 'Transferencia'" class="p-4 bg-[#131316] border border-white/5 rounded-2xl mt-4 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-white">Comprobante de Transferencia</h4>
                                
                                <!-- Si ya tiene comprobante subido y no se ha seleccionado un nuevo archivo para este pedido -->
                                <div v-if="pedido.comprobante_path && (activeUploadPedidoId !== pedido.id || !uploadForm.comprobante)" class="flex flex-col sm:flex-row items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5">
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 font-extrabold text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            Enviado
                                        </span>
                                        <p class="text-xs text-zinc-300 font-medium">Comprobante en verificación.</p>
                                    </div>
                                    <div class="ml-auto flex items-center gap-3">
                                        <a :href="route('mi-cuenta.comprobante.ver', pedido.id)" target="_blank" class="text-xs font-semibold text-white hover:underline">Ver adjunto</a>
                                        <button @click.stop="deleteComprobante(pedido.id)" class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-white/5 rounded-lg transition-colors" title="Eliminar comprobante">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Formulario si no hay comprobante o si eligió nuevo archivo -->
                                <form v-else @submit.prevent="uploadComprobante(pedido.id)" class="flex flex-col sm:flex-row items-center gap-3">
                                    <div class="flex-1 w-full min-w-0">
                                        <input 
                                            type="file" 
                                            accept="image/*,.pdf"
                                            @change="handleFileChange($event, pedido.id)"
                                            class="w-full text-xs text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer"
                                        >
                                        <p v-if="pedido.comprobante_path" class="text-[11px] text-amber-400 mt-1 font-medium">
                                            Seleccionaste un nuevo archivo para reemplazar el anterior.
                                        </p>
                                    </div>
                                    
                                    <button 
                                        v-if="uploadForm.comprobante && activeUploadPedidoId === pedido.id"
                                        type="submit"
                                        :disabled="uploadForm.processing"
                                        class="w-full sm:w-auto px-5 py-2 bg-white hover:bg-zinc-200 text-black text-xs font-bold uppercase rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50 shrink-0 cursor-pointer"
                                    >
                                        {{ uploadForm.processing ? 'Enviando...' : 'Enviar comprobante' }}
                                    </button>
                                </form>
                                <p v-if="uploadForm.errors.comprobante" class="text-rose-400 text-xs font-semibold mt-1">{{ uploadForm.errors.comprobante }}</p>
                            </div>

                            <!-- Acciones del pedido: Descargar Comprobante PDF -->
                            <div class="pt-3 border-t border-white/5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <span class="text-xs text-zinc-500 font-medium">Constancia de reserva y compra</span>
                                <a 
                                    :href="route('pedidos.comprobante-pdf', pedido.id)" 
                                    target="_blank" 
                                    class="px-4 py-2 bg-white/10 hover:bg-white/15 text-white font-semibold text-xs rounded-xl border border-white/10 transition-all flex items-center gap-2 cursor-pointer shadow-sm"
                                >
                                    <svg class="w-4 h-4 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>Descargar Comprobante PDF</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="pedidos.links?.length > 3" class="mt-8 flex justify-center gap-2">
                    <Link
                        v-for="link in pedidos.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        preserve-state
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                    >{{ decodeLabel(link.label) }}</Link>
                </div>
                </div>

            <!-- Tab: Mis Pagos -->
            <div v-if="tab === 'pagos'" class="space-y-4">
                <div
                    v-for="pago in pagos"
                    :key="pago.id"
                    class="bg-[#131316] border border-white/5 rounded-2xl overflow-hidden shadow-xl hover:border-white/10 transition-all"
                >
                    <!-- Card Header (Clickable to Expand / Collapse) -->
                    <div
                        @click="togglePagoExpand(pago.id)"
                        class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-5 cursor-pointer hover:bg-white/[0.02] transition-colors select-none"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-zinc-400 shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>

                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-white font-mono font-bold text-base tracking-tight">
                                        #PAGO-{{ String(pago.id).padStart(6, '0') }}
                                    </span>

                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white/[0.03] border border-white/5 text-xs font-semibold text-zinc-300">
                                        <span class="w-2 h-2 rounded-full shrink-0 bg-emerald-400"></span>
                                        <span>{{ pago.metodo_pago }}</span>
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-zinc-400">
                                    <span>{{ formatFecha(pago.fecha) }}</span>
                                    <template v-if="pago.descripcion">
                                        <span class="text-zinc-600">•</span>
                                        <span class="truncate max-w-[220px]">{{ pago.descripcion }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Monto + Chevron Arrow -->
                        <div class="flex items-center justify-between md:justify-end gap-5 border-t md:border-t-0 border-white/5 pt-3 md:pt-0">
                            <p class="text-base sm:text-lg font-bold text-emerald-400 font-mono tracking-tight">
                                {{ formatPrecio(pago.monto) }}
                            </p>

                            <div
                                class="w-8 h-8 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-zinc-400 transition-transform duration-200"
                                :class="{ 'rotate-180 bg-white/10 text-white': expandedPagos.includes(pago.id) }"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible Payment Details Content -->
                    <div v-if="expandedPagos.includes(pago.id)" class="border-t border-white/5 bg-[#0d0d0f] p-5 space-y-3">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-zinc-400 font-medium">Método de pago:</span>
                            <span class="font-bold text-white">{{ pago.metodo_pago }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs border-t border-white/5 pt-3">
                            <span class="text-zinc-400 font-medium">Fecha:</span>
                            <span class="font-bold text-white">{{ formatFecha(pago.fecha) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs border-t border-white/5 pt-3">
                            <span class="text-zinc-400 font-medium">Monto:</span>
                            <span class="font-bold text-emerald-400 font-mono">{{ formatPrecio(pago.monto) }}</span>
                        </div>
                        <div v-if="pago.descripcion" class="border-t border-white/5 pt-3">
                            <span class="text-zinc-400 font-medium text-xs block mb-1">Observación:</span>
                            <p class="text-white text-xs font-medium leading-relaxed">{{ pago.descripcion }}</p>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        </div>

        <Teleport to="body">
            <div v-if="showConsolidarModal">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showConsolidarModal = false"></div>
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-lg bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">

                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">
                                Solicitar envío de pedidos acumulados
                            </h3>
                            <button @click="showConsolidarModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitConsolidar" class="p-6 space-y-4">
                            <p class="text-xs text-zinc-400 leading-relaxed">
                                Te enviamos todos tus pedidos acumulados juntos, pagando un solo envío.
                            </p>

                            <!-- Resumen de los pedidos que se incluyen en este envio -->
                            <div class="bg-[#131316] border border-white/5 rounded-xl p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-white">Pedidos incluidos en este envío</h4>
                                    <span class="text-[11px] font-semibold text-zinc-400">
                                        {{ acumuladosListos.length }} {{ acumuladosListos.length === 1 ? 'pedido' : 'pedidos' }} · {{ totalLibrosAcumulados }} {{ totalLibrosAcumulados === 1 ? 'libro' : 'libros' }}
                                    </span>
                                </div>
                                <div class="max-h-48 overflow-y-auto divide-y divide-white/5">
                                    <div v-for="p in acumuladosListos" :key="p.id" class="py-2 first:pt-0 last:pb-0">
                                        <div class="flex justify-between text-[11px] font-semibold text-zinc-300">
                                            <span>Pedido #{{ p.id }}</span>
                                            <span class="text-zinc-500">{{ formatFecha(p.fecha) }}</span>
                                        </div>
                                        <ul class="mt-1 space-y-0.5">
                                            <li v-for="(item, i) in p.items" :key="i" class="text-xs text-zinc-400 flex justify-between gap-3">
                                                <span class="truncate">{{ item.titulo }}</span>
                                                <span class="text-zinc-500 shrink-0">x{{ item.cantidad }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Provincia *</label>
                                    <select v-model="provincia" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-white/30 font-semibold uppercase tracking-wider">
                                        <option value="" disabled>-- Provincia --</option>
                                        <option v-for="p in provincias" :key="p" :value="p">{{ p }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Localidad *</label>
                                    <div v-if="provincia === 'Santa Fe'" class="space-y-2">
                                        <select v-model="localidad" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-white/30 font-semibold uppercase tracking-wider">
                                            <option value="" disabled>-- Seleccioná tu Localidad --</option>
                                            <optgroup label="Gran Rosario (Reparto local - Sin cargo)">
                                                <option v-for="l in localidadesSantaFe" :key="l" :value="l">{{ l }}</option>
                                            </optgroup>
                                            <optgroup label="Resto de la Provincia">
                                                <option value="__otra__">📦 Otra localidad de Santa Fe (Correo Nacional)</option>
                                            </optgroup>
                                        </select>
                                        <input v-if="localidad === '__otra__'" v-model="otraLocalidadSantaFe" type="text" placeholder="Escribí tu localidad" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium">
                                    </div>
                                    <input v-else v-model="localidad" type="text" placeholder="Ej: Córdoba Capital, Mendoza..." class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium">
                                </div>
                            </div>

                            <!-- Banners informativos según zona de envío -->
                            <div v-if="provincia === 'Santa Fe' && localidad && localidad !== '__otra__'" class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-xs font-semibold flex items-center gap-2">
                                <span class="text-base shrink-0">🛵</span>
                                <span>Zona Gran Rosario: <strong>reparto local sin cargo de envío</strong>.</span>
                            </div>
                            <div v-else-if="(provincia === 'Santa Fe' && localidad === '__otra__') || (provincia && provincia !== 'Santa Fe')" class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-indigo-300 text-xs font-medium flex items-center gap-2">
                                <span class="text-base shrink-0">📦</span>
                                <span>Se despacha mediante <strong>Correo Nacional</strong> a domicilio.</span>
                            </div>

                            <!-- Autocomplete de calle -->
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Calle y Número *</label>
                                <div class="relative">
                                    <input
                                        v-model="direccionInput"
                                        type="text"
                                        :disabled="!direccionHabilitada"
                                        :placeholder="direccionHabilitada ? 'Buscá tu dirección...' : (provincia === 'Santa Fe' ? (localidad === '__otra__' ? 'Escribí primero el nombre de tu localidad' : 'Elegí primero la localidad') : 'Elegí primero la provincia')"
                                        autocomplete="off"
                                        @focus="mostrarSugerencias = sugerencias.length > 0"
                                        @blur="setTimeout(() => mostrarSugerencias = false, 150)"
                                        class="w-full bg-[#131316] border rounded-xl px-4 py-2.5 pr-10 text-xs text-white placeholder-zinc-500 focus:outline-none transition-all disabled:opacity-40 disabled:cursor-not-allowed font-medium"
                                        :class="addressSelected ? 'border-emerald-500/60 focus:border-emerald-500' : 'border-white/10 focus:border-white/30'"
                                    />
                                    <svg v-if="addressSelected" class="absolute right-3 top-3 w-4 h-4 text-emerald-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <svg v-else-if="buscandoDireccion" class="absolute right-3 top-3 w-4 h-4 text-zinc-400 animate-spin pointer-events-none" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <ul v-if="mostrarSugerencias && sugerencias.length" class="absolute z-20 mt-1 w-full bg-[#131316] border border-white/10 rounded-xl shadow-2xl overflow-hidden">
                                        <li v-for="(f, idx) in sugerencias" :key="idx" @mousedown.prevent="seleccionarSugerencia(f)" class="px-4 py-2.5 text-xs text-zinc-300 hover:bg-white/5 hover:text-white cursor-pointer border-t border-white/5 first:border-t-0 font-medium">
                                            {{ formatearSugerencia(f) }}
                                        </li>
                                    </ul>
                                </div>
                                <p v-if="!addressSelected && direccionInput.length > 2" class="text-amber-400 text-[10px] mt-1.5 font-semibold uppercase tracking-wider">
                                    Seleccioná una dirección de la lista emergente
                                </p>
                            </div>

                            <!-- Piso, Depto, CP -->
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">Piso <span class="text-zinc-500 normal-case">(opc)</span></label>
                                    <input v-model="piso" type="text" placeholder="Ej: 3" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">Depto <span class="text-zinc-500 normal-case">(opc)</span></label>
                                    <input v-model="depto" type="text" placeholder="Ej: B" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">CP <span class="text-brand-red">*</span></label>
                                    <input v-model="cp" type="text" placeholder="Ej: 2000" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">Comentarios y Referencias</label>
                                <input v-model="comentarioConsolidar" type="text" placeholder="Ej: Tocar timbre fuerte. Dejar en portería" class="w-full bg-[#131316] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium">
                            </div>

                            <!-- Cobro del envio fuera del Gran Rosario -->
                            <div v-if="!esEnvioLocal" class="space-y-3 pt-2 border-t border-white/5">
                                <p class="text-xs text-amber-400 font-semibold">
                                    El envío por Correo Nacional tiene un costo de {{ formatPrecio(50000) }}, a pagar aparte.
                                </p>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">¿Cómo pagás el envío? *</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <button type="button" @click="medioPagoConsolidar = 'Tarjeta'"
                                        class="px-4 py-2.5 rounded-xl text-xs font-bold border transition-all"
                                        :class="medioPagoConsolidar === 'Tarjeta' ? 'bg-white text-black border-white' : 'bg-[#131316] text-zinc-300 border-white/10 hover:border-white/20'">
                                        Mercado Pago
                                    </button>
                                    <button type="button" @click="medioPagoConsolidar = 'Transferencia'"
                                        class="px-4 py-2.5 rounded-xl text-xs font-bold border transition-all"
                                        :class="medioPagoConsolidar === 'Transferencia' ? 'bg-white text-black border-white' : 'bg-[#131316] text-zinc-300 border-white/10 hover:border-white/20'">
                                        Transferencia
                                    </button>
                                </div>
                                <p v-if="medioPagoConsolidar === 'Transferencia'" class="text-zinc-400 text-[11px] leading-relaxed">
                                    Después de confirmar, vas a poder subir el comprobante desde "Mis Pedidos".
                                </p>
                            </div>

                            <p v-if="consolidarForm.errors.direccion_envio" class="text-rose-400 text-xs font-semibold mt-1">{{ consolidarForm.errors.direccion_envio }}</p>

                            <div class="mt-6 flex justify-end gap-3 border-t border-white/5 pt-4 bg-[#131316] -mx-6 -mb-6 p-6">
                                <button type="button" @click="showConsolidarModal = false" class="px-5 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 font-semibold text-xs rounded-xl border border-white/10 transition-all">Cancelar</button>
                                <button type="submit" :disabled="consolidarForm.processing || !puedeSolicitarEnvio" class="px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50">
                                    <span>{{ consolidarForm.processing ? 'Enviando...' : (!esEnvioLocal && medioPagoConsolidar === 'Tarjeta' ? 'Pagar envío con Mercado Pago' : 'Confirmar envío') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-micuenta,
.page-micuenta * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
