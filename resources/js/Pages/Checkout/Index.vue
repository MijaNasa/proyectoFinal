<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted } from 'vue';

const props = defineProps({
    items: Array,
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

const totalFinal = computed(() => props.total + costoEnvio.value);

const buscarDirecciones = async (query) => {
    if (query.trim().length < 3) {
        sugerencias.value = [];
        mostrarSugerencias.value = false;
        return;
    }

    const consultaId = ++ultimaConsultaId;
    buscandoDireccion.value = true;
    try {
        const params = new URLSearchParams({
            q: query,
            limit: '5',
            lat: '-32.9468',
            lon: '-60.6393',
        });
        const res = await fetch(`https://photon.komoot.io/api/?${params.toString()}`);
        const data = await res.json();
        if (consultaId !== ultimaConsultaId) return;
        sugerencias.value = data.features || [];
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
    const texto = formatearSugerencia(f);
    direccionInput.value     = texto;
    direccionFormatted.value = texto;
    addressSelected.value    = true;
    sugerencias.value        = [];
    mostrarSugerencias.value = false;
};

watch(tipoEnvio, (val) => {
    direccionInput.value     = '';
    direccionFormatted.value = '';
    addressSelected.value    = false;
    piso.value  = '';
    depto.value = '';
    cp.value    = '';
    comentario.value = '';
    provincia.value = 'Santa Fe';
    localidad.value = '';
    sugerencias.value = [];
    mostrarSugerencias.value = false;
    if (val === 'domicilio') {
        sucursalId.value = props.sucursal_principal_id;
    } else {
        sucursalId.value = '';
    }

    // Si cambia a domicilio o acumulación, remover efectivo
    if (['domicilio', 'acumulacion'].includes(val)) {
        if (medioPago.value === 'Efectivo') {
            medioPago.value = 'Tarjeta';
        }
    }
});

watch(direccionInput, (val) => {
    if (addressSelected.value && val !== direccionFormatted.value) {
        addressSelected.value    = false;
        direccionFormatted.value = '';
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

const puedeEnviar = computed(() => {
    let baseValido = !!sucursalId.value;
    if (tipoEnvio.value === 'domicilio') {
        baseValido = baseValido && addressSelected.value && provincia.value && localidad.value;
    }
    
    if (!isAuthenticated.value) {
        baseValido = baseValido && guestNombre.value.trim() && guestDni.value.trim() && guestEmail.value.trim() && guestTelefono.value.trim();
    }
    
    return baseValido;
});

const confirmar = () => {
    if (!puedeEnviar.value || procesando.value) return;
    procesando.value = true;

    let direccion = direccionFormatted.value;
    if (piso.value.trim())  direccion += `, Piso ${piso.value.trim()}`;
    if (depto.value.trim()) direccion += `, Depto ${depto.value.trim()}`;
    if (cp.value.trim())    direccion += `, CP ${cp.value.trim()}`;
    direccion += `, ${localidad.value}, ${provincia.value}`;
    if (comentario.value.trim()) direccion += ` | Obs: ${comentario.value.trim()}`;

    if (tipoEnvio.value === 'domicilio' && !cp.value.trim()) {
        toast.value = { msg: 'El Código Postal es obligatorio para envíos a domicilio.', type: 'error' };
        setTimeout(() => toast.value = null, 4000);
        return;
    }

    router.post(route('checkout.store'), {
        tipo_envio:            (tipoEnvio.value === 'domicilio' && !esEnvioLocal.value) ? 'correo_nacional' : tipoEnvio.value,
        sucursal_id:           sucursalId.value,
        direccion_envio:       tipoEnvio.value === 'domicilio' ? direccion : null,
        medio_pago:            medioPago.value,
        guest_nombre:          isAuthenticated.value ? null : guestNombre.value,
        guest_apellido:        isAuthenticated.value ? null : guestApellido.value,
        guest_dni:             isAuthenticated.value ? null : guestDni.value,
        guest_email:           isAuthenticated.value ? null : guestEmail.value,
        guest_telefono:        isAuthenticated.value ? null : guestTelefono.value,
    }, {
        onError: () => { procesando.value = false; },
    });
};
</script>

<template>
    <Head title="Checkout" />

    <PublicLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-5xl font-black uppercase tracking-tighter mb-12">
                Finalizar <span class="text-brand-red italic">Compra</span>
            </h1>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-12">

                <!-- Formulario -->
                <div class="lg:col-span-3 space-y-8">

                    <!-- Datos de Contacto (Solo para Invitados) -->
                    <div v-if="!isAuthenticated" class="bg-white/[0.03] border border-white/10 rounded-2xl p-8">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red underline decoration-2 underline-offset-4">
                                Datos de Contacto
                            </h2>
                            <Link :href="route('login', { redirect: '/checkout' })" class="text-xs text-white/50 hover:text-white transition-colors underline underline-offset-4">
                                ¿Ya tenés cuenta? Ingresá
                            </Link>
                        </div>
                        
                        <p class="text-xs text-white/40 mb-6">Completá tus datos para procesar el pedido. Podrás registrarte más adelante usando este mismo DNI y Correo.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Nombre *</label>
                                <input v-model="guestNombre" type="text" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors" placeholder="Ej: Juan">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Apellido</label>
                                <input v-model="guestApellido" type="text" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors" placeholder="Ej: Pérez">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">DNI / Documento *</label>
                                <input v-model="guestDni" type="text" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors" placeholder="Ej: 12345678">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Teléfono Móvil *</label>
                                <input v-model="guestTelefono" type="text" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors" placeholder="Ej: 3415551234">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Correo Electrónico *</label>
                                <input v-model="guestEmail" type="email" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors" placeholder="Ej: tu@email.com">
                            </div>
                        </div>
                    </div>

                    <!-- Tipo de entrega -->
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8">
                        <h2 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">
                            Tipo de Entrega
                        </h2>

                        <div class="space-y-3">
                            <label
                                class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                :class="tipoEnvio === 'retiro' ? 'border-brand-red bg-brand-red/5' : 'border-white/10 hover:border-white/20'"
                            >
                                <input type="radio" v-model="tipoEnvio" value="retiro" class="mt-1 accent-red-600" />
                                <div>
                                    <p class="font-black uppercase tracking-wider text-sm">Retiro en sucursal</p>
                                    <p class="text-white/40 text-xs mt-1">Retirás tu pedido en nuestra tienda. Sin costo adicional.</p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                :class="tipoEnvio === 'domicilio' ? 'border-brand-red bg-brand-red/5' : 'border-white/10 hover:border-white/20'"
                            >
                                <input type="radio" v-model="tipoEnvio" value="domicilio" class="mt-1 accent-red-600" />
                                <div class="flex-1">
                                    <p class="font-black uppercase tracking-wider text-sm">Envío a domicilio</p>
                                    <p class="text-white/40 text-xs mt-1">Coordinaremos el envío a tu dirección.</p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                :class="tipoEnvio === 'acumulacion' ? 'border-brand-red bg-brand-red/5' : 'border-white/10 hover:border-white/20'"
                            >
                                <input type="radio" v-model="tipoEnvio" value="acumulacion" class="mt-1 accent-red-600" />
                                <div class="flex-1">
                                    <p class="font-black uppercase tracking-wider text-sm">Acumulación de envío</p>
                                    <p class="text-white/40 text-xs mt-1">Acumulá tu pedido en sucursal para coordinar un solo envío más tarde.</p>
                                </div>
                            </label>
                        </div>

                        <!-- Selector de Sucursal -->
                        <div class="mt-6" v-if="tipoEnvio !== 'domicilio'">
                            <label class="block text-xs font-black uppercase tracking-widest text-white/40 mb-2">
                                Sucursal para Retiro / Acumulación *
                            </label>
                            <select
                                v-model="sucursalId"
                                class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors font-black uppercase tracking-wider"
                            >
                                <option value="" disabled>-- Selecciona una sucursal --</option>
                                <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">
                                    📍 {{ suc.nombre }}
                                </option>
                            </select>
                        </div>
                        
                        <div v-if="sucursalId && !sucursales.find(s => s.id === sucursalId)?.tiene_stock_local" class="mt-3 p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl flex items-start gap-3">
                                <span class="text-xl">🚚</span>
                                <div>
                                    <p class="text-yellow-400 font-bold text-xs uppercase tracking-wider">Requiere traslados internos</p>
                                    <p class="text-white/60 text-[10px] mt-1 font-medium leading-relaxed">
                                        Esta sucursal no cuenta con todos los libros de tu pedido físicamente en este momento. Los trasladaremos desde otras sucursales. El pedido demorará unos días extra, te avisaremos cuando esté unificado y listo.
                                    </p>
                                </div>
                            </div>

                        <!-- Dirección (solo si domicilio) -->
                        <transition name="fade">
                            <div v-if="tipoEnvio === 'domicilio'" class="mt-6 space-y-4">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-black uppercase tracking-widest text-white/40 mb-2">
                                            Provincia *
                                        </label>
                                        <select v-model="provincia" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors font-black uppercase tracking-wider">
                                            <option value="" disabled>-- Provincia --</option>
                                            <option v-for="p in provincias" :key="p" :value="p">{{ p }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black uppercase tracking-widest text-white/40 mb-2">
                                            Localidad *
                                        </label>
                                        <select v-if="provincia === 'Santa Fe'" v-model="localidad" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors font-black uppercase tracking-wider">
                                            <option value="" disabled>-- Localidad --</option>
                                            <option v-for="l in localidadesSantaFe" :key="l" :value="l">{{ l }}</option>
                                        </select>
                                        <input v-else type="text" v-model="localidad" placeholder="Ej: Córdoba Capital" class="w-full bg-black border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-red transition-colors font-black uppercase tracking-wider" />
                                    </div>
                                </div>

                                <div v-if="provincia && localidad && !esEnvioLocal" class="p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-yellow-500 text-xs font-bold mt-2">
                                    ⚠️ El envío a {{ localidad }} se realiza por Correo Nacional con un recargo de {{ formatPrecio(50000) }}.
                                </div>

                                <!-- Autocomplete -->
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-white/40 mb-2">
                                        Calle y Número
                                    </label>
                                    <div class="relative">
                                        <input
                                            ref="inputRef"
                                            v-model="direccionInput"
                                            type="text"
                                            placeholder="Buscá tu dirección..."
                                            autocomplete="off"
                                            @focus="mostrarSugerencias = sugerencias.length > 0"
                                            @blur="setTimeout(() => mostrarSugerencias = false, 150)"
                                            class="w-full bg-white/5 border rounded-xl px-4 py-3 pr-10 text-sm text-white placeholder-white/20 focus:outline-none transition-colors"
                                            :class="addressSelected
                                                ? 'border-green-500/60 focus:border-green-500'
                                                : 'border-white/10 focus:border-brand-red'"
                                        />
                                        <svg
                                            v-if="addressSelected"
                                            class="absolute right-3 top-3.5 w-4 h-4 text-green-400 pointer-events-none"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <svg
                                            v-else-if="buscandoDireccion"
                                            class="absolute right-3 top-3.5 w-4 h-4 text-white/30 animate-spin pointer-events-none"
                                            fill="none" viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>

                                        <ul
                                            v-if="mostrarSugerencias && sugerencias.length"
                                            class="absolute z-20 mt-1 w-full bg-[#1a1a1a] border border-white/10 rounded-xl shadow-2xl overflow-hidden"
                                        >
                                            <li
                                                v-for="(f, idx) in sugerencias"
                                                :key="idx"
                                                @mousedown.prevent="seleccionarSugerencia(f)"
                                                class="px-4 py-2.5 text-xs text-white/60 hover:bg-white/5 hover:text-white cursor-pointer border-t border-white/5 first:border-t-0"
                                            >
                                                {{ formatearSugerencia(f) }}
                                            </li>
                                        </ul>
                                    </div>
                                    <p v-if="!addressSelected && direccionInput.length > 2" class="text-yellow-400/70 text-[10px] mt-1.5 font-bold uppercase tracking-wider">
                                        Seleccioná una dirección de la lista
                                    </p>
                                </div>

                                <!-- Extras: Piso, Depto, CP y Comentarios -->
                                <transition name="fade">
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1.5">
                                                    Piso <span class="text-white/20">(opc)</span>
                                                </label>
                                                <input
                                                    v-model="piso"
                                                    type="text"
                                                    placeholder="Ej: 3"
                                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red transition-colors"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1.5">
                                                    Depto <span class="text-white/20">(opc)</span>
                                                </label>
                                                <input
                                                    v-model="depto"
                                                    type="text"
                                                    placeholder="Ej: B"
                                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red transition-colors"
                                                />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1.5">
                                                    CP <span class="text-brand-red">*</span>
                                                </label>
                                                <input
                                                    v-model="cp"
                                                    type="text"
                                                    placeholder="Ej: 2000"
                                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red transition-colors"
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-widest text-white/30 mb-1.5">
                                                Comentarios y Referencias <span class="text-white/20">(opcional)</span>
                                            </label>
                                            <input
                                                v-model="comentario"
                                                type="text"
                                                placeholder="Ej: Tocar timbre fuerte. Dejar en portería."
                                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/20 focus:outline-none focus:border-brand-red transition-colors"
                                            />
                                        </div>
                                    </div>
                                </transition>

                            </div>
                        </transition>
                    </div>

                    <!-- Método de pago -->
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8">
                        <h2 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">
                            Método de Pago
                        </h2>

                        <div class="space-y-3">
                            <label
                                class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                :class="medioPago === 'Tarjeta' ? 'border-brand-red bg-brand-red/5' : 'border-white/10 hover:border-white/20'"
                            >
                                <input type="radio" v-model="medioPago" value="Tarjeta" class="mt-1 accent-red-600" />
                                <div>
                                    <p class="font-black uppercase tracking-wider text-sm">💳 Tarjeta (Crédito / Débito)</p>
                                    <p class="text-white/40 text-xs mt-1">Aboná online de manera segura con Mercado Pago.</p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                                :class="medioPago === 'Transferencia' ? 'border-brand-red bg-brand-red/5' : 'border-white/10 hover:border-white/20'"
                            >
                                <input type="radio" v-model="medioPago" value="Transferencia" class="mt-1 accent-red-600" />
                                <div>
                                    <p class="font-black uppercase tracking-wider text-sm">📱 Transferencia</p>
                                    <p class="text-white/40 text-xs mt-1">Transferencia bancaria directa.</p>
                                </div>
                            </label>

                            <label
                                v-if="tipoEnvio === 'retiro'"
                                class="flex items-start gap-4 p-4 rounded-xl border transition-all"
                                :class="[
                                    medioPago === 'Efectivo' ? 'border-brand-red bg-brand-red/5' : 'border-white/10',
                                    (!sucursales.find(s => s.id === sucursalId)?.tiene_stock_local) ? 'opacity-50 cursor-not-allowed bg-black/50' : 'cursor-pointer hover:border-white/20'
                                ]"
                            >
                                <input
                                    type="radio"
                                    v-model="medioPago"
                                    value="Efectivo"
                                    class="mt-1 accent-red-600"
                                    :disabled="!sucursales.find(s => s.id === sucursalId)?.tiene_stock_local"
                                />
                                <div>
                                    <p class="font-black uppercase tracking-wider text-sm" :class="{ 'text-white/40': !sucursales.find(s => s.id === sucursalId)?.tiene_stock_local }">💵 Efectivo Cash</p>
                                    <p v-if="!sucursales.find(s => s.id === sucursalId)?.tiene_stock_local" class="text-red-400 font-bold text-xs mt-1 leading-tight">
                                        Para productos que requieren traslado entre sucursales, es necesario confirmar la compra mediante pago online.
                                    </p>
                                    <p v-else class="text-white/40 text-xs mt-1">Abonás en efectivo presencialmente al retirar en la sucursal.</p>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-4 p-4 rounded-xl border transition-all"
                                :class="[
                                    medioPago === 'Cuenta Corriente' ? 'border-brand-red bg-brand-red/5' : 'border-white/10',
                                    (saldo_actual < total) ? 'opacity-50 cursor-not-allowed bg-black/50' : 'cursor-pointer hover:border-white/20'
                                ]"
                            >
                                <input
                                    type="radio"
                                    v-model="medioPago"
                                    value="Cuenta Corriente"
                                    class="mt-1 accent-red-600"
                                    :disabled="saldo_actual < total"
                                />
                                <div class="flex-1">
                                    <p class="font-black uppercase tracking-wider text-sm" :class="{ 'text-white/40': saldo_actual < total }">🏛️ Cuenta Corriente</p>
                                    <p class="text-white/40 text-xs mt-1">
                                        Usá tu saldo actual a favor: <span class="text-green-400 font-bold font-mono">{{ formatPrecio(saldo_actual) }}</span>.
                                    </p>
                                    <p v-if="saldo_actual < total" class="text-red-400 font-bold text-xs mt-1 uppercase tracking-widest">
                                        Saldo insuficiente
                                    </p>
                                </div>
                            </label>
                        </div>

                        <!-- Advertencia 48h Efectivo -->
                        <transition name="fade">
                            <div v-if="medioPago === 'Efectivo'" class="mt-6 bg-brand-red/10 border border-brand-red/20 p-4 rounded-xl flex items-start gap-3">
                                <span class="text-xl">⏳</span>
                                <div>
                                    <h4 class="text-brand-red font-black text-xs uppercase tracking-widest mb-1">Tu reserva expira en 48hs</h4>
                                    <p class="text-white/70 text-[10px] font-medium leading-relaxed">
                                        El stock quedará reservado inmediatamente para tu pedido. Tenés un límite estricto de 48 horas hábiles para acercarte a la sucursal a abonar en efectivo. <strong class="text-white">Si el plazo expira, tu pedido será cancelado automáticamente.</strong>
                                    </p>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- Error flash -->
                    <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3 text-sm font-bold text-red-400 space-y-1">
                        <div v-for="(err, key) in $page.props.errors" :key="key">
                            ⚠️ {{ err }}
                        </div>
                    </div>

                    <div v-if="$page.props.flash?.error" class="bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3 text-sm font-bold text-red-400">
                        ⚠️ {{ $page.props.flash.error }}
                    </div>

                    <!-- Info de contacto -->
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6">
                        <p class="text-xs font-black uppercase tracking-widest text-white/30 mb-1">
                            {{ (medioPago === 'Tarjeta' || (medioPago === 'Cuenta Corriente' && (saldo_actual - total) < 0 && (metodoPagoExcedente === 'Tarjeta'))) ? 'Mercado Pago Seguro' : 'Confirmación de Compra' }}
                        </p>
                        <p class="text-white/50 text-sm">
                            {{ (medioPago === 'Tarjeta' || (medioPago === 'Cuenta Corriente' && (saldo_actual - total) < 0 && (metodoPagoExcedente === 'Tarjeta'))) ? 'Al confirmar, te redirigimos a la pasarela de pagos segura de Mercado Pago.' : 'Confirmá tu compra para registrarla en nuestro sistema. El pedido se procesará de inmediato.' }}
                        </p>
                        <div class="flex items-center gap-2 mt-4">
                            <svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span class="text-xs font-bold text-green-400 uppercase tracking-widest">Pago 100% seguro</span>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="lg:col-span-2">
                    <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-8 sticky top-28">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-brand-red mb-6 underline decoration-2 underline-offset-4">
                            Tu Pedido
                        </h3>

                        <div class="space-y-4 mb-8 max-h-64 overflow-y-auto pr-1">
                            <div
                                v-for="item in items"
                                :key="item.libro_id"
                                class="flex items-start gap-3"
                            >
                                <img
                                    :src="item.portada_url"
                                    :alt="item.titulo"
                                    class="w-10 flex-shrink-0 aspect-[2/3] object-cover rounded-md border border-white/10"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-black uppercase tracking-tight leading-tight line-clamp-2">{{ item.titulo }}</p>
                                    <p class="text-[10px] text-white/30 mt-1">x{{ item.cantidad }}</p>
                                </div>
                                <p class="text-sm font-black text-white flex-shrink-0">
                                    {{ formatPrecio(item.precio * item.cantidad) }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-white/10 pt-4 flex justify-between mb-2">
                            <span class="font-black uppercase tracking-widest text-sm text-white/50">Subtotal</span>
                            <span class="font-black text-white/50">{{ formatPrecio(total) }}</span>
                        </div>
                        
                        <div v-if="costoEnvio > 0" class="flex justify-between mb-4">
                            <span class="font-black uppercase tracking-widest text-sm text-white/50">Costo de Envío</span>
                            <span class="font-black text-white/50">+{{ formatPrecio(costoEnvio) }}</span>
                        </div>

                        <div class="border-t border-white/10 pt-4 flex justify-between mb-8">
                            <span class="font-black uppercase tracking-widest text-sm">Total</span>
                            <span class="text-2xl font-black text-brand-red italic">{{ formatPrecio(totalFinal) }}</span>
                        </div>

                        <button
                            @click="confirmar"
                            :disabled="!puedeEnviar || procesando"
                            class="w-full bg-brand-red hover:bg-brand-red/80 disabled:opacity-40 disabled:cursor-not-allowed text-white font-black text-sm uppercase tracking-widest py-4 rounded-xl transition-all flex items-center justify-center gap-2"
                        >
                            <svg v-if="procesando" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
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
                            class="mt-4 block text-center text-[10px] font-black uppercase tracking-widest text-white/20 hover:text-white transition-colors py-2"
                        >
                            ← Volver al carrito
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
