<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted } from 'vue';

const props = defineProps({
    items: Array,
    subtotal: Number,
    descuento_suscripcion: Number,
    total: Number,
    saldo_actual: Number,
    sucursales: Array,
    sucursal_principal_id: Number,
});

const tipoEnvio           = ref('retiro');
const sucursalId          = ref('');
const medioPago           = ref('Tarjeta');
const metodoPagoExcedente = ref(null);
const direccionInput      = ref('');
const direccionFormatted  = ref('');
const addressSelected     = ref(false);
const piso                = ref('');
const depto               = ref('');
const cp                  = ref('');
const latitud             = ref(null);
const longitud            = ref(null);
const comentario          = ref('');
const procesando          = ref(false);
const inputRef            = ref(null);

const provincia = ref('Santa Fe');
const localidad = ref('');

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

const guestNombre   = ref('');
const guestApellido = ref('');
const guestDni      = ref('');
const guestEmail    = ref('');
const guestTelefono = ref('');

const sugerencias        = ref([]);
const mostrarSugerencias = ref(false);
const buscandoDireccion  = ref(false);
let debounceTimer = null;
let ultimaConsultaId = 0;

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const esEnvioLocal = computed(() => {
    if (tipoEnvio.value !== 'domicilio') return true;
    return provincia.value === 'Santa Fe' && localidadesSantaFe.includes(localidad.value);
});

const costoEnvio = computed(() => {
    if (tipoEnvio.value === 'domicilio' && !esEnvioLocal.value) {
        return 50000;
    }
    return 0;
});

const totalFinal = computed(() => {
    const base = (props.subtotal ?? props.total) - (props.descuento_suscripcion ?? 0);
    return Math.max(0, base) + costoEnvio.value;
});

const direccionHabilitada = computed(() => {
    if (provincia.value === 'Santa Fe') return !!localidad.value;
    return !!provincia.value;
});

const normalizar = (str) => (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(new RegExp('[̀-ͯ]', 'g'), '');

const coincideLocalidad = (f) => {
    if (!localidad.value) return true;
    const p = f.properties;
    const objetivo = normalizar(localidad.value);
    const candidatos = [p.city, p.district, p.county].filter(Boolean).map(normalizar);
    return candidatos.some(c => c.includes(objetivo) || objetivo.includes(c));
};

const buscarDirecciones = async (query) => {
    if (query.trim().length < 3 || !direccionHabilitada.value) {
        sugerencias.value = [];
        mostrarSugerencias.value = false;
        return;
    }

    const consultaId = ++ultimaConsultaId;
    buscandoDireccion.value = true;
    try {
        const contexto = [query, localidad.value, provincia.value, 'Argentina'].filter(Boolean).join(', ');
        const params = new URLSearchParams({
            q: contexto,
            limit: '8',
            lat: '-32.9468',
            lon: '-60.6393',
        });
        const res = await fetch(`https://photon.komoot.io/api/?${params.toString()}`);
        const data = await res.json();
        if (consultaId !== ultimaConsultaId) return;
        const features = data.features || [];
        const filtradas = features.filter(coincideLocalidad);
        sugerencias.value = filtradas.length > 0 ? filtradas : features;
        mostrarSugerencias.value = sugerencias.value.length > 0;
    } catch {
        sugerencias.value = [];
        mostrarSugerencias.value = false;
    } finally {
        if (consultaId === ultimaConsultaId) buscandoDireccion.value = false;
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
    direccionInput.value     = texto;
    direccionFormatted.value = texto;
    addressSelected.value    = true;
    sugerencias.value        = [];
    mostrarSugerencias.value = false;

    const [lon, lat] = f.geometry?.coordinates ?? [];
    latitud.value  = lat ?? null;
    longitud.value = lon ?? null;

    if (provincia.value !== 'Santa Fe') {
        localidad.value = p.city || p.district || p.county || provincia.value;
    }
    if (p.postcode) {
        cp.value = p.postcode;
    }
};

watch(tipoEnvio, (val) => {
    direccionInput.value     = '';
    direccionFormatted.value = '';
    addressSelected.value    = false;
    piso.value  = '';
    depto.value = '';
    cp.value    = '';
    latitud.value  = null;
    longitud.value = null;
    comentario.value = '';
    provincia.value = 'Santa Fe';
    localidad.value = '';
    sugerencias.value = [];
    mostrarSugerencias.value = false;
    if (['domicilio', 'correo_sucursal'].includes(val)) {
        sucursalId.value = props.sucursal_principal_id || props.sucursales?.[0]?.id || '';
    } else {
        sucursalId.value = props.sucursales?.[0]?.id || '';
    }

    if (['domicilio', 'correo_sucursal', 'acumulacion'].includes(val)) {
        if (medioPago.value === 'Efectivo') {
            medioPago.value = 'Tarjeta';
        }
    }
});

watch(provincia, () => {
    localidad.value           = '';
    direccionInput.value      = '';
    direccionFormatted.value  = '';
    addressSelected.value     = false;
    cp.value                  = '';
    latitud.value             = null;
    longitud.value            = null;
    sugerencias.value         = [];
    mostrarSugerencias.value  = false;
});

watch(localidad, () => {
    if (provincia.value !== 'Santa Fe') return;
    direccionInput.value     = '';
    direccionFormatted.value = '';
    addressSelected.value    = false;
    latitud.value            = null;
    longitud.value           = null;
    sugerencias.value        = [];
    mostrarSugerencias.value = false;
});

watch(direccionInput, (val) => {
    if (addressSelected.value && val !== direccionFormatted.value) {
        addressSelected.value    = false;
        direccionFormatted.value = '';
        latitud.value             = null;
        longitud.value            = null;
    }
    clearTimeout(debounceTimer);
    if (!addressSelected.value) {
        debounceTimer = setTimeout(() => buscarDirecciones(val), 400);
    }
});

onUnmounted(() => {
    clearTimeout(debounceTimer);
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

const sucursalCorreoInput = ref('');

const puedeEnviar = computed(() => {
    if (!isAuthenticated.value) {
        const guestValido = guestNombre.value.trim() && guestDni.value.trim() && guestEmail.value.trim() && guestTelefono.value.trim();
        if (!guestValido) return false;
    }

    if (tipoEnvio.value === 'retiro' || tipoEnvio.value === 'acumulacion') {
        return !!sucursalId.value;
    }

    if (tipoEnvio.value === 'domicilio') {
        const calleOk = direccionInput.value.trim().length > 0;
        const provinciaOk = !!provincia.value;
        const localidadOk = !!localidad.value;
        const cpOk = cp.value.trim().length > 0;
        return calleOk && provinciaOk && localidadOk && cpOk;
    }

    if (tipoEnvio.value === 'correo_sucursal') {
        const provinciaOk = !!provincia.value;
        const localidadOk = !!localidad.value;
        const sucursalOk = sucursalCorreoInput.value.trim().length > 0;
        const cpOk = cp.value.trim().length > 0;
        return provinciaOk && localidadOk && sucursalOk && cpOk;
    }

    return false;
});

const confirmar = () => {
    if (!puedeEnviar.value || procesando.value) return;
    procesando.value = true;

    let targetSucursalId = sucursalId.value;
    if (!targetSucursalId || ['domicilio', 'correo_sucursal'].includes(tipoEnvio.value)) {
        targetSucursalId = props.sucursal_principal_id || props.sucursales?.[0]?.id;
    }

    let direccion = '';
    if (tipoEnvio.value === 'domicilio') {
        direccion = direccionFormatted.value || direccionInput.value.trim();
        if (piso.value.trim())  direccion += `, Piso ${piso.value.trim()}`;
        if (depto.value.trim()) direccion += `, Depto ${depto.value.trim()}`;
        if (cp.value.trim())    direccion += `, CP ${cp.value.trim()}`;
        if (localidad.value)    direccion += `, ${localidad.value}`;
        if (provincia.value)    direccion += `, ${provincia.value}`;
        if (comentario.value.trim()) direccion += ` | Obs: ${comentario.value.trim()}`;

        if (!direccionInput.value.trim()) {
            alert('La calle y número de la dirección son obligatorios.');
            procesando.value = false;
            return;
        }
        if (!cp.value.trim()) {
            alert('El Código Postal es obligatorio para envíos a domicilio.');
            procesando.value = false;
            return;
        }
    } else if (tipoEnvio.value === 'correo_sucursal') {
        direccion = `Sucursal Correo Argentino: ${sucursalCorreoInput.value.trim()}`;
        if (cp.value.trim()) direccion += `, CP ${cp.value.trim()}`;
        if (localidad.value) direccion += `, ${localidad.value}`;
        if (provincia.value) direccion += `, ${provincia.value}`;
        if (comentario.value.trim()) direccion += ` | Obs: ${comentario.value.trim()}`;

        if (!cp.value.trim() || !sucursalCorreoInput.value.trim()) {
            alert('Completá el Código Postal y la sucursal de Correo Argentino de destino.');
            procesando.value = false;
            return;
        }
    }

    router.post(route('checkout.store'), {
        tipo_envio:            (tipoEnvio.value === 'domicilio' && !esEnvioLocal.value) ? 'correo_nacional' : tipoEnvio.value,
        sucursal_id:           targetSucursalId,
        direccion_envio:       (tipoEnvio.value === 'domicilio' || tipoEnvio.value === 'correo_sucursal') ? direccion : null,
        latitud:               tipoEnvio.value === 'domicilio' ? latitud.value : null,
        longitud:              tipoEnvio.value === 'domicilio' ? longitud.value : null,
        medio_pago:            medioPago.value,
        guest_nombre:          isAuthenticated.value ? null : guestNombre.value,
        guest_apellido:        isAuthenticated.value ? null : guestApellido.value,
        guest_dni:             isAuthenticated.value ? null : guestDni.value,
        guest_email:           isAuthenticated.value ? null : guestEmail.value,
        guest_telefono:        isAuthenticated.value ? null : guestTelefono.value,
    }, {
        onError: () => { procesando.value = false; },
        onFinish: () => { procesando.value = false; },
    });
};
</script>

<template>
    <Head title="Checkout | PuroComic" />

    <PublicLayout>
        <div class="page-checkout">
            <!-- Hero Header -->
            <div class="relative overflow-hidden py-12 sm:py-16 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-semibold uppercase tracking-wider text-zinc-400">
                        <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Checkout Seguro
                    </div>
                    <h1 class="text-4xl sm:text-6xl font-bold tracking-tight uppercase leading-none text-white">
                        Finalizar <span class="text-zinc-400 italic">Compra</span>
                    </h1>
                    <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-xl mx-auto">
                        Completá tus datos de entrega y seleccioná tu método de pago preferido para confirmar tu pedido.
                    </p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 sm:gap-12 items-start">

                    <!-- Formulario Principal -->
                    <div class="lg:col-span-3 space-y-6">

                        <!-- Datos de Contacto (Solo para Invitados) -->
                        <div v-if="!isAuthenticated" class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
                            <div class="flex items-center justify-between border-b border-white/5 pb-4">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    Datos de Contacto
                                </h2>
                                <Link :href="route('login', { redirect: '/checkout' })" class="text-xs font-semibold text-zinc-400 hover:text-white transition-colors underline underline-offset-4">
                                    ¿Ya tenés cuenta? Ingresá
                                </Link>
                            </div>
                            
                            <div class="bg-[#0d0d0f] border border-white/10 rounded-xl p-3.5 flex items-start gap-3 text-xs text-zinc-300 font-medium">
                                <span class="text-base shrink-0">💡</span>
                                <span>
                                    <strong>Vinculación automática por DNI:</strong> Registraremos tu pedido con tu <strong>DNI</strong>. Al registrar tu cuenta o crear tu contraseña usando tu DNI, accederás automáticamente a todo tu historial de compras.
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Nombre *</label>
                                    <input v-model="guestNombre" type="text" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium" placeholder="Ej: Juan">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Apellido</label>
                                    <input v-model="guestApellido" type="text" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium" placeholder="Ej: Pérez">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">DNI / Documento *</label>
                                    <input v-model="guestDni" type="text" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium" placeholder="Ej: 12345678">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Teléfono Móvil *</label>
                                    <input v-model="guestTelefono" type="text" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium" placeholder="Ej: 3415551234">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Correo Electrónico *</label>
                                    <input v-model="guestEmail" type="email" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium" placeholder="Ej: tu@email.com">
                                </div>
                            </div>
                        </div>

                        <!-- Tipo de Entrega -->
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-white/5 pb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Tipo de Entrega
                            </h2>

                            <div class="space-y-3">
                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                    :class="tipoEnvio === 'retiro' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f] hover:border-white/20'"
                                >
                                    <input type="radio" v-model="tipoEnvio" value="retiro" class="mt-1 accent-white" />
                                    <div>
                                        <p class="font-bold text-sm text-white">Retiro en Sucursal (Nuestra Tienda)</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Retirás tu pedido en nuestras sucursales físicas. Sin costo adicional.</p>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                    :class="tipoEnvio === 'domicilio' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f] hover:border-white/20'"
                                >
                                    <input type="radio" v-model="tipoEnvio" value="domicilio" class="mt-1 accent-white" />
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-white">Envío a Domicilio</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Coordinaremos el envío directo a tu dirección (Reparto local o Correo Nacional).</p>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                    :class="tipoEnvio === 'correo_sucursal' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f] hover:border-white/20'"
                                >
                                    <input type="radio" v-model="tipoEnvio" value="correo_sucursal" class="mt-1 accent-white" />
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-white">Envío a Sucursal de Correo (Correo Argentino)</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Retirás en la sucursal de Correo Argentino que elijas.</p>
                                    </div>
                                </label>

                                <label
                                    v-if="$page.props.auth?.user"
                                    class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                    :class="tipoEnvio === 'acumulacion' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f] hover:border-white/20'"
                                >
                                    <input type="radio" v-model="tipoEnvio" value="acumulacion" class="mt-1 accent-white" />
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-white">Acumulación de Envío</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Acumulá tu pedido en sucursal para coordinar un único envío posterior.</p>
                                    </div>
                                </label>

                                <div
                                    v-else
                                    class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-[#0d0d0f]/60 opacity-80"
                                >
                                    <div class="mt-0.5 text-base">📦</div>
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-zinc-300">Acumulación de Envío <span class="text-zinc-500 font-normal text-xs">(Solo clientes registrados)</span></p>
                                        <p class="text-zinc-400 text-xs mt-0.5 leading-relaxed">
                                            Guardá tus pedidos en sucursal y coordiná un único envío posterior. 
                                            <Link :href="route('login')" class="text-blue-400 hover:text-blue-300 underline font-semibold ml-1">
                                                Ingresá a tu cuenta
                                            </Link> 
                                            para utilizar la acumulación.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Selector de Sucursal -->
                            <div class="mt-6 pt-4 border-t border-white/5" v-if="tipoEnvio === 'retiro' || tipoEnvio === 'acumulacion'">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                    Sucursal para Retiro / Acumulación *
                                </label>
                                <select
                                    v-model="sucursalId"
                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-white/30 transition-all font-semibold uppercase tracking-wider"
                                >
                                    <option value="" disabled>-- Seleccioná una sucursal --</option>
                                    <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">
                                        📍 {{ suc.nombre }}
                                    </option>
                                </select>
                            </div>
                            
                            <div v-if="(tipoEnvio === 'retiro' || tipoEnvio === 'acumulacion') && sucursalId && !sucursales.find(s => s.id === sucursalId)?.tiene_stock_local" class="mt-3 p-4 bg-amber-400/10 border border-amber-400/20 rounded-xl flex items-start gap-3">
                                <span class="text-lg shrink-0">🚚</span>
                                <div>
                                    <p class="text-amber-400 font-bold text-xs uppercase tracking-wider">Requiere traslados internos</p>
                                    <p class="text-zinc-300 text-xs mt-1 font-medium leading-relaxed">
                                        Esta sucursal no cuenta con todos los libros de tu pedido físicamente en este momento. Los trasladaremos desde otras sucursales. El pedido demorará unos días extra, te avisaremos cuando esté unificado y listo.
                                    </p>
                                </div>
                            </div>

                            <!-- Formulario de Sucursal de Correo Argentino -->
                            <transition name="fade">
                                <div v-if="tipoEnvio === 'correo_sucursal'" class="mt-6 space-y-4 pt-4 border-t border-white/5">
                                    <div class="p-4 bg-amber-400/10 border border-amber-400/20 rounded-xl text-amber-400 text-xs font-semibold">
                                        📦 Envío a Sucursal de Correo Argentino con recargo de {{ formatPrecio(50000) }}. Retirás con tu DNI en la sucursal de correo elegida.
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                                Provincia *
                                            </label>
                                            <select v-model="provincia" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-white/30 transition-all font-semibold uppercase tracking-wider">
                                                <option value="" disabled>-- Provincia --</option>
                                                <option v-for="p in provincias" :key="p" :value="p">{{ p }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                                Localidad *
                                            </label>
                                            <select v-if="provincia === 'Santa Fe'" v-model="localidad" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-white/30 transition-all font-semibold uppercase tracking-wider">
                                                <option value="" disabled>-- Localidad --</option>
                                                <option v-for="l in localidadesSantaFe" :key="l" :value="l">{{ l }}</option>
                                            </select>
                                            <input v-else v-model="localidad" type="text" placeholder="Ej: Córdoba Capital, Mendoza..." class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-2">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                                                    Dirección de Sucursal *
                                                </label>
                                                <a href="https://www.correoargentino.com.ar/formularios/sucursales" target="_blank" class="text-[11px] font-semibold text-blue-400 hover:underline flex items-center gap-1">
                                                    <span>🔍 Buscar Sucursal</span>
                                                </a>
                                            </div>
                                            <input v-model="sucursalCorreoInput" type="text" placeholder="Ej: Sucursal Central / San Martín 750" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                                Código Postal *
                                            </label>
                                            <input v-model="cp" type="text" placeholder="Ej: 2000" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium uppercase">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                            Observaciones para el Correo (Opcional)
                                        </label>
                                        <input v-model="comentario" type="text" placeholder="Ej: Retira Juan Pérez con DNI..." class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium">
                                    </div>
                                </div>
                            </transition>

                            <!-- Dirección (solo si domicilio) -->
                            <transition name="fade">
                                <div v-if="tipoEnvio === 'domicilio'" class="mt-6 space-y-4 pt-4 border-t border-white/5">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                                Provincia *
                                            </label>
                                            <select v-model="provincia" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-white/30 transition-all font-semibold uppercase tracking-wider">
                                                <option value="" disabled>-- Provincia --</option>
                                                <option v-for="p in provincias" :key="p" :value="p">{{ p }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                                Localidad *
                                            </label>
                                            <select v-if="provincia === 'Santa Fe'" v-model="localidad" class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-white/30 transition-all font-semibold uppercase tracking-wider">
                                                <option value="" disabled>-- Localidad --</option>
                                                <option v-for="l in localidadesSantaFe" :key="l" :value="l">{{ l }}</option>
                                            </select>
                                            <input v-else v-model="localidad" type="text" placeholder="Ej: Córdoba Capital, Mendoza..." class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium">
                                        </div>
                                    </div>

                                    <!-- Autocomplete -->
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                                            Calle y Número *
                                        </label>
                                        <div class="relative">
                                            <input
                                                ref="inputRef"
                                                v-model="direccionInput"
                                                type="text"
                                                :disabled="!direccionHabilitada"
                                                :placeholder="direccionHabilitada ? 'Buscá tu dirección...' : (provincia === 'Santa Fe' ? 'Elegí primero la localidad' : 'Elegí primero la provincia')"
                                                autocomplete="off"
                                                @focus="mostrarSugerencias = sugerencias.length > 0"
                                                @blur="setTimeout(() => mostrarSugerencias = false, 150)"
                                                class="w-full bg-[#0d0d0f] border rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-zinc-500 focus:outline-none transition-all disabled:opacity-40 disabled:cursor-not-allowed font-medium"
                                                :class="addressSelected
                                                    ? 'border-emerald-500/60 focus:border-emerald-500'
                                                    : 'border-white/10 focus:border-white/30'"
                                            />
                                            <svg
                                                v-if="addressSelected"
                                                class="absolute right-3 top-3.5 w-4 h-4 text-emerald-400 pointer-events-none"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <svg
                                                v-else-if="buscandoDireccion"
                                                class="absolute right-3 top-3.5 w-4 h-4 text-zinc-400 animate-spin pointer-events-none"
                                                fill="none" viewBox="0 0 24 24"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>

                                            <ul
                                                v-if="mostrarSugerencias && sugerencias.length"
                                                class="absolute z-20 mt-1 w-full bg-[#131316] border border-white/10 rounded-xl shadow-2xl overflow-hidden"
                                            >
                                                <li
                                                    v-for="(f, idx) in sugerencias"
                                                    :key="idx"
                                                    @mousedown.prevent="seleccionarSugerencia(f)"
                                                    class="px-4 py-2.5 text-xs text-zinc-300 hover:bg-white/5 hover:text-white cursor-pointer border-t border-white/5 first:border-t-0 font-medium"
                                                >
                                                    {{ formatearSugerencia(f) }}
                                                </li>
                                            </ul>
                                        </div>
                                        <p v-if="!addressSelected && direccionInput.length > 2" class="text-amber-400 text-[10px] mt-1.5 font-semibold uppercase tracking-wider">
                                            Seleccioná una dirección de la lista emergente
                                        </p>
                                        <p v-if="provincia !== 'Santa Fe' && addressSelected && localidad" class="text-zinc-400 text-[10px] mt-1.5 font-semibold uppercase tracking-wider">
                                            Localidad: {{ localidad }}
                                        </p>
                                    </div>

                                    <!-- Extras: Piso, Depto, CP y Comentarios -->
                                    <transition name="fade">
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                                                        Piso <span class="text-zinc-500 normal-case">(opc)</span>
                                                    </label>
                                                    <input
                                                        v-model="piso"
                                                        type="text"
                                                        placeholder="Ej: 3"
                                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                                                        Depto <span class="text-zinc-500 normal-case">(opc)</span>
                                                    </label>
                                                    <input
                                                        v-model="depto"
                                                        type="text"
                                                        placeholder="Ej: B"
                                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                                                        CP <span class="text-brand-red">*</span>
                                                    </label>
                                                    <input
                                                        v-model="cp"
                                                        type="text"
                                                        placeholder="Ej: 2000"
                                                        class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">
                                                    Comentarios y Referencias
                                                </label>
                                                <input
                                                    v-model="comentario"
                                                    type="text"
                                                    placeholder="Ej: Tocar timbre fuerte. Dejar en portería"
                                                    class="w-full bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 transition-all font-medium"
                                                />
                                            </div>
                                        </div>
                                    </transition>

                                </div>
                            </transition>
                        </div>

                        <!-- Método de Pago -->
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-white/5 pb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-brand-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Método de Pago
                            </h2>

                            <div class="space-y-3">
                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                    :class="medioPago === 'Tarjeta' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f] hover:border-white/20'"
                                >
                                    <input type="radio" v-model="medioPago" value="Tarjeta" class="mt-1 accent-white" />
                                    <div>
                                        <p class="font-bold text-sm text-white">💳 Tarjeta (Crédito / Débito)</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Aboná online de manera segura a través de Mercado Pago.</p>
                                    </div>
                                </label>

                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                    :class="medioPago === 'Transferencia' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f] hover:border-white/20'"
                                >
                                    <input type="radio" v-model="medioPago" value="Transferencia" class="mt-1 accent-white" />
                                    <div>
                                        <p class="font-bold text-sm text-white">📱 Transferencia Directa</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Transferencia bancaria directa CBU/CVU.</p>
                                    </div>
                                </label>

                                <label
                                    v-if="tipoEnvio === 'retiro'"
                                    class="flex items-start gap-4 p-4 rounded-xl border transition-all"
                                    :class="[
                                        medioPago === 'Efectivo' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f]',
                                        (!sucursales.find(s => s.id === sucursalId)?.tiene_stock_local) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-white/20'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        v-model="medioPago"
                                        value="Efectivo"
                                        class="mt-1 accent-white"
                                        :disabled="!sucursales.find(s => s.id === sucursalId)?.tiene_stock_local"
                                    />
                                    <div>
                                        <p class="font-bold text-sm text-white" :class="{ 'text-zinc-400': !sucursales.find(s => s.id === sucursalId)?.tiene_stock_local }">💵 Efectivo Presencial</p>
                                        <p v-if="!sucursales.find(s => s.id === sucursalId)?.tiene_stock_local" class="text-rose-400 font-semibold text-xs mt-1 leading-tight">
                                            Para productos que requieren traslado entre sucursales, es necesario confirmar la compra mediante pago online.
                                        </p>
                                        <p v-else class="text-zinc-400 text-xs mt-0.5">Abonás en efectivo en el mostrador al retirar en sucursal.</p>
                                    </div>
                                </label>

                                <label
                                    v-if="$page.props.auth?.user"
                                    class="flex items-start gap-4 p-4 rounded-xl border transition-all"
                                    :class="[
                                        medioPago === 'Cuenta Corriente' ? 'border-white/30 bg-white/5 shadow-md' : 'border-white/10 bg-[#0d0d0f]',
                                        (saldo_actual < total) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-white/20'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        v-model="medioPago"
                                        value="Cuenta Corriente"
                                        class="mt-1 accent-white"
                                        :disabled="saldo_actual < total"
                                    />
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-white" :class="{ 'text-zinc-400': saldo_actual < total }">🏛️ Cuenta Corriente</p>
                                        <p class="text-zinc-400 text-xs mt-0.5">
                                            Usá tu saldo a favor: <span class="text-emerald-400 font-bold font-mono">{{ formatPrecio(saldo_actual) }}</span>.
                                        </p>
                                        <p v-if="saldo_actual < total" class="text-rose-400 font-bold text-xs mt-1 uppercase tracking-wider">
                                            Saldo insuficiente
                                        </p>
                                    </div>
                                </label>

                                <div
                                    v-else
                                    class="flex items-start gap-4 p-4 rounded-xl border border-white/10 bg-[#0d0d0f]/60 opacity-80"
                                >
                                    <div class="mt-0.5 text-base">🏛️</div>
                                    <div class="flex-1">
                                        <p class="font-bold text-sm text-zinc-300">Cuenta Corriente</p>
                                        <p class="text-zinc-400 text-xs mt-0.5 leading-relaxed">
                                            ¿Tenés saldo a favor? 
                                            <Link :href="route('login')" class="text-blue-400 hover:text-blue-300 underline font-semibold ml-1">
                                                Ingresá a tu cuenta
                                            </Link> 
                                            para utilizarlo en tu compra.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Advertencia 12h Efectivo -->
                            <transition name="fade">
                                <div v-if="medioPago === 'Efectivo'" class="mt-4 bg-amber-400/10 border border-amber-400/20 p-4 rounded-xl flex items-start gap-3">
                                    <span class="text-lg shrink-0">⏳</span>
                                    <div>
                                        <h4 class="text-amber-400 font-bold text-xs uppercase tracking-wider mb-1">Tu reserva expira en 12hs</h4>
                                        <p class="text-zinc-300 text-xs font-medium leading-relaxed">
                                            El stock quedará reservado inmediatamente para tu pedido. Tenés un plazo estricto de 12 horas para acercarte a la sucursal a abonar en efectivo. <strong class="text-white">Si el plazo expira, la reserva se cancelará automáticamente.</strong>
                                        </p>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Errores Flash -->
                        <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="bg-rose-500/10 border border-rose-500/30 rounded-xl px-4 py-3 text-xs font-bold text-rose-400 space-y-1">
                            <div v-for="(err, key) in $page.props.errors" :key="key">
                                ⚠️ {{ err }}
                            </div>
                        </div>

                        <div v-if="$page.props.flash?.error" class="bg-rose-500/10 border border-rose-500/30 rounded-xl px-4 py-3 text-xs font-bold text-rose-400">
                            ⚠️ {{ $page.props.flash.error }}
                        </div>

                        <!-- Info de confirmación y seguridad -->
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">
                                {{ (medioPago === 'Tarjeta' || (medioPago === 'Cuenta Corriente' && (saldo_actual - total) < 0 && (metodoPagoExcedente === 'Tarjeta'))) ? 'Pasarela de Mercado Pago' : 'Confirmación de Compra' }}
                            </p>
                            <p class="text-zinc-400 text-xs leading-relaxed">
                                {{ (medioPago === 'Tarjeta' || (medioPago === 'Cuenta Corriente' && (saldo_actual - total) < 0 && (metodoPagoExcedente === 'Tarjeta'))) ? 'Al hacer clic en confirmar, serás redirigido a la pasarela oficial de Mercado Pago para procesar tu transacción.' : 'Al hacer clic en confirmar, se registrará el pedido en nuestro sistema y comenzará el procesamiento de despacho.' }}
                            </p>
                            <div class="flex items-center gap-2 mt-4">
                                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Transacción 100% Protegida</span>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen del Pedido (Sidebar Fijo) -->
                    <div class="lg:col-span-2">
                        <div class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-2xl sticky top-28 space-y-6">
                            <div class="flex items-center justify-between border-b border-white/5 pb-4">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                                    Tu Pedido
                                </h3>
                                <span class="text-xs font-semibold text-zinc-400 bg-white/5 border border-white/10 px-2.5 py-0.5 rounded-full">
                                    {{ items.length }} ítem{{ items.length !== 1 ? 's' : '' }}
                                </span>
                            </div>

                            <!-- Lista compacta de items -->
                            <div class="space-y-4 max-h-72 overflow-y-auto pr-1">
                                <div
                                    v-for="item in items"
                                    :key="item.libro_id"
                                    class="flex items-center gap-3 bg-[#0d0d0f] border border-white/5 p-2.5 rounded-xl"
                                >
                                    <img
                                        :src="item.portada_url"
                                        :alt="item.titulo"
                                        @error="$event.target.src = '/images/no-cover.png'"
                                        class="w-10 aspect-[2/3] object-cover rounded-lg border border-white/10 shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-white leading-tight line-clamp-2">{{ item.titulo }}</p>
                                        <p class="text-[10px] font-mono text-zinc-400 mt-0.5">Cant: {{ item.cantidad }}</p>
                                    </div>
                                    <p class="text-sm font-bold font-mono text-white shrink-0">
                                        {{ formatPrecio(item.precio * item.cantidad) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Desglose de Totales -->
                            <div class="space-y-3 pt-2">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-zinc-400 font-medium">Subtotal</span>
                                    <span class="font-mono font-bold text-white text-sm">{{ formatPrecio(subtotal || total) }}</span>
                                </div>

                                <div v-if="descuento_suscripcion > 0" class="flex justify-between items-center text-xs text-emerald-400 font-semibold bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1.5 rounded-lg">
                                    <span class="flex items-center gap-1.5">
                                        <span>⭐</span>
                                        <span>Descuento Suscriptor (5%)</span>
                                    </span>
                                    <span class="font-mono font-bold">-{{ formatPrecio(descuento_suscripcion) }}</span>
                                </div>
                                
                                <div v-if="costoEnvio > 0" class="flex justify-between items-center text-xs">
                                    <span class="text-zinc-400 font-medium">Costo de Envío</span>
                                    <span class="font-mono font-bold text-white text-sm">+{{ formatPrecio(costoEnvio) }}</span>
                                </div>

                                <div class="border-t border-white/5 pt-4 flex justify-between items-baseline">
                                    <span class="text-sm font-bold uppercase tracking-wider text-white">Total Final</span>
                                    <span class="text-2xl sm:text-3xl font-bold font-mono text-white">{{ formatPrecio(totalFinal) }}</span>
                                </div>
                            </div>

                            <!-- Botón de Confirmación -->
                            <div class="space-y-3 pt-2">
                                <button
                                    @click="confirmar"
                                    :disabled="!puedeEnviar || procesando"
                                    class="w-full py-4 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="procesando" class="animate-spin w-4 h-4 text-black" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <span>
                                        {{
                                            procesando 
                                                ? 'Procesando...' 
                                                : ((medioPago === 'Tarjeta' || (medioPago === 'Cuenta Corriente' && (saldo_actual - total) < 0 && (metodoPagoExcedente === 'Tarjeta')))
                                                    ? 'Pagar con Mercado Pago'
                                                    : 'Confirmar Compra')
                                        }}
                                    </span>
                                </button>

                                <Link
                                    :href="route('carrito.index')"
                                    class="w-full py-3 bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 font-semibold text-xs uppercase tracking-wider rounded-xl transition-all text-center block"
                                >
                                    ← Volver al Carrito
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-checkout,
.page-checkout * {
    font-family: 'Montserrat', sans-serif !important;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
