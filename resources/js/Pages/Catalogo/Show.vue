<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    libro: Object,
    relacionados: Array,
});

const cantidad = ref(1);
const codigoPostal = ref('');
const cpCalculado = ref('');
const mostrandoResultadosCp = ref(false);
const showMediosPagoModal = ref(false);

const esPreventa = computed(() => !!props.libro?.permite_preventa);

const getPrecioVentaNum = (libro) => {
    if (libro.precio_actual) {
        let precio = libro.precio_actual.precio_venta;
        if (libro.permite_preventa) {
            precio = precio * 0.90;
        }
        return precio;
    }
    return 0;
};

const getPrecioFinal = (libro) => {
    const num = getPrecioVentaNum(libro);
    if (num > 0) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(num);
    }
    return 'Consultar';
};

const getPrecioOriginal = (libro) => {
    if (libro.precio_actual) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(libro.precio_actual.precio_venta);
    }
    return '';
};

const getCuotaMensual = (libro) => {
    const num = getPrecioVentaNum(libro);
    if (num > 0) {
        return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format((num * 1.15) / 12);
    }
    return '';
};

const getStockTotal = (libro) => {
    return libro.stocks?.reduce((sum, s) => sum + (s.cantidad_disponible ?? 0), 0) ?? 0;
};

const getStockStatus = (libro) => {
    const total = getStockTotal(libro);
    if (total === 0) return 'sin_stock';
    if (total < 5)  return 'pocos';
    return 'disponible';
};

const agregarAlCarrito = () => {
    router.post(route('carrito.agregar'), {
        libro_id: props.libro.id,
        cantidad: cantidad.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            cantidad.value = 1;
        },
    });
};

const agregarTomoDirecto = (tomoId) => {
    router.post(route('carrito.agregar'), {
        libro_id: tomoId,
        cantidad: 1,
    }, { preserveScroll: true, preserveState: true });
};

const calcularEnvio = () => {
    if (!codigoPostal.value.trim()) return;
    cpCalculado.value = codigoPostal.value.trim();
    mostrandoResultadosCp.value = true;
};

const cambiarCp = () => {
    mostrandoResultadosCp.value = false;
    codigoPostal.value = '';
};

const envioDomicilioClasico = computed(() => {
    const val = getPrecioVentaNum(props.libro);
    return val >= 100000 ? 'Gratis' : '$9.177,02';
});

const envioSucursalCorreo = computed(() => {
    const val = getPrecioVentaNum(props.libro);
    return val >= 80000 ? 'Gratis' : '$6.264,56';
});
</script>

<template>
    <Head :title="(libro.master?.titulo ?? '') + (libro.numero_tomo ? ' ' + libro.numero_tomo : '') + (esPreventa ? ' (Preventa)' : '') + ' - PuroComic'" />

    <PublicLayout>
        <!-- Top Breadcrumb Bar -->
        <div class="bg-white/[0.02] border-b border-white/10 py-4 mb-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                <nav class="text-xs font-bold uppercase tracking-wider text-white/50 space-x-2">
                    <Link :href="route('catalogo.index')" class="hover:text-white transition-colors">Inicio</Link>
                    <span>-</span>
                    <span class="text-white/70">{{ libro.master?.categoria?.nombre ?? 'MANGAS' }}</span>
                    <span>-</span>
                    <span v-if="libro.master?.proveedor" class="text-white/70">{{ libro.master.proveedor.nombre_empresa }}</span>
                    <span v-if="libro.master?.proveedor">-</span>
                    <span class="text-white truncate max-w-[250px] inline-block align-bottom">{{ libro.master?.titulo }}</span>
                </nav>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                <!-- Left: Product Image -->
                <div class="lg:col-span-5 flex justify-center">
                    <div class="w-full max-w-md">
                        <div class="relative rounded-2xl overflow-hidden bg-slate-900 border border-slate-800 shadow-2xl">
                            <img
                                :src="libro.master?.portada_url"
                                :alt="libro.master?.titulo"
                                @error="$event.target.src = '/images/no-cover.png'"
                                class="w-full aspect-[2/3] object-cover"
                            >
                            <span v-if="esPreventa" class="absolute top-3 right-3 bg-brand-red text-white text-xs font-black uppercase px-3 py-1 rounded shadow-lg">
                                Preventa
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Title, Price, Action Row & Delivery -->
                <div class="lg:col-span-7 space-y-6 text-white">
                    <!-- Title above Price -->
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block mb-1">
                            {{ libro.master?.categoria?.nombre ?? 'MANGA' }} {{ libro.master?.proveedor ? '• ' + libro.master.proveedor.nombre_empresa : '' }}
                        </span>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                            {{ libro.master?.titulo }} {{ libro.numero_tomo ? libro.numero_tomo : '' }}
                            <span v-if="esPreventa" class="text-slate-400 font-semibold text-lg ml-2">(Preventa)</span>
                        </h1>
                        <div class="h-1 w-20 bg-brand-red mt-2.5 rounded"></div>
                    </div>

                    <!-- Pricing Section -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-baseline gap-3">
                            <span v-if="esPreventa" class="text-lg font-bold text-slate-400 line-through">
                                {{ getPrecioOriginal(libro) }}
                            </span>
                            <span class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                                {{ getPrecioFinal(libro) }}
                            </span>
                        </div>

                        <!-- Preventa 10% Discount Note (ONLY if preventa enabled) -->
                        <div v-if="esPreventa" class="space-y-1 text-xs font-semibold text-slate-300">
                            <p class="text-white font-bold">
                                10% de descuento especial por Preventa aplicado
                            </p>
                        </div>

                        <!-- Installments Info (Neutral white/slate) -->
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                            <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span>12 cuotas de <strong>{{ getCuotaMensual(libro) }}</strong></span>
                        </div>

                        <!-- Modal Ver Medios de Pago Trigger -->
                        <div class="pt-1">
                            <button
                                @click="showMediosPagoModal = true"
                                class="px-3.5 py-1.5 border border-slate-600 text-slate-300 hover:border-white hover:text-white transition-colors text-[11px] font-bold uppercase tracking-wider rounded-md"
                            >
                                VER MEDIOS DE PAGO
                            </button>
                        </div>

                        <!-- Free Shipping Notice (Neutral white/slate) -->
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-300 pt-1">
                            <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            <span>Envío gratis superando los $80.000,00</span>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Quantity & Add to Cart Action Row (Pastel Red Button & Compact Size) -->
                    <div class="flex items-center gap-4 py-1">
                        <!-- Quantity Selector -->
                        <div class="flex items-center border border-slate-700 bg-slate-900 rounded-lg overflow-hidden shrink-0">
                            <button
                                @click="cantidad = Math.max(1, cantidad - 1)"
                                class="px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 transition-colors font-bold text-xs"
                            >−</button>
                            <span class="px-3 py-1.5 text-xs font-extrabold text-white min-w-[2rem] text-center">{{ cantidad }}</span>
                            <button
                                @click="cantidad = cantidad + 1"
                                class="px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 transition-colors font-bold text-xs"
                            >+</button>
                        </div>

                        <!-- Compact Add to Cart Button (Pastel Red) -->
                        <button
                            @click="agregarAlCarrito"
                            :disabled="getStockStatus(libro) === 'sin_stock'"
                            class="py-2.5 px-6 bg-[#D9434E] hover:bg-[#C23641] text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-all shadow-md text-center"
                        >
                            {{ getStockStatus(libro) === 'sin_stock' ? 'AGOTADO' : 'AGREGAR AL CARRITO' }}
                        </button>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Shipping Calculator Box & Calculation Results -->
                    <div class="space-y-3">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-300">Medios de envío</h4>

                        <!-- Input form before calculation -->
                        <div v-if="!mostrandoResultadosCp" class="flex flex-wrap items-center gap-3">
                            <input
                                v-model="codigoPostal"
                                @keyup.enter="calcularEnvio"
                                type="text"
                                placeholder="Tu código postal"
                                class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 focus:border-slate-500 focus:outline-none w-44"
                            >
                            <button
                                @click="calcularEnvio"
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors"
                            >
                                CALCULAR
                            </button>
                        </div>

                        <!-- Results display when CP entered -->
                        <div v-else class="bg-slate-900/90 border border-slate-800 rounded-xl p-4 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                <span class="text-xs font-bold text-slate-300">
                                    Entregas para el CP: <strong class="text-white">{{ cpCalculado }}</strong>
                                </span>
                                <button
                                    @click="cambiarCp"
                                    class="px-2.5 py-1 border border-slate-600 text-[10px] font-bold uppercase text-slate-300 hover:text-white rounded hover:border-slate-400 transition-colors"
                                >
                                    CAMBIAR CP
                                </button>
                            </div>

                            <div class="p-3 bg-slate-950/60 rounded-lg border border-slate-800 text-xs text-slate-300 font-medium">
                                ¡Gracias por elegir <strong>PuroComic</strong>! Recordá ingresar tus datos reales en la compra para acumular puntos.
                            </div>

                            <!-- ENVÍO A DOMICILIO -->
                            <div class="space-y-2">
                                <h5 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">ENVÍO A DOMICILIO</h5>
                                <div class="bg-slate-950/40 border border-slate-800 rounded-lg p-3 space-y-2 text-xs">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-white">Correo Argentino Clásico - Envío a domicilio</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Llega en 3 a 5 días hábiles</p>
                                        </div>
                                        <span class="font-extrabold text-white">{{ envioDomicilioClasico }}</span>
                                    </div>
                                    <div class="flex justify-between items-start border-t border-slate-800/80 pt-2">
                                        <div>
                                            <p class="font-bold text-white">Correo Argentino Expreso - Envío a domicilio</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Llega en 1 a 2 días hábiles</p>
                                        </div>
                                        <span class="font-extrabold text-white">$12.619,31</span>
                                    </div>
                                </div>
                            </div>

                            <!-- RETIRAR POR -->
                            <div class="space-y-2">
                                <h5 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">RETIRAR POR</h5>
                                <div class="bg-slate-950/40 border border-slate-800 rounded-lg p-3 space-y-2 text-xs">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-white">Punto de retiro - Sucursal Correo Argentino</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Retirás en 2 a 4 días hábiles</p>
                                        </div>
                                        <span class="font-extrabold text-white">{{ envioSucursalCorreo }}</span>
                                    </div>
                                    <div class="flex justify-between items-start border-t border-slate-800/80 pt-2">
                                        <div>
                                            <p class="font-bold text-white">Retiro en el local - Sucursal Principal PuroComic</p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Retiro inmediato en sucursal</p>
                                        </div>
                                        <span class="font-bold text-green-400">Gratis</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Description / Synopsis -->
                    <div class="space-y-2 pt-1">
                        <h3 class="text-xs font-extrabold uppercase tracking-widest text-slate-300">DESCRIPCIÓN</h3>
                        <div class="text-xs text-slate-300 leading-relaxed space-y-2">
                            <p v-if="libro.synopsis">{{ libro.synopsis }}</p>
                            <p v-else>
                                Disfruta del tomo {{ libro.numero_tomo ?? '01' }} de {{ libro.master?.titulo }}. Editado originalmente por {{ libro.master?.proveedor?.nombre_empresa ?? 'la editorial' }}. Incluye páginas a color y sobrecubierta de alta calidad.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact Related Products Section ("Productos similares") -->
            <div v-if="relacionados && relacionados.length > 0" class="mt-16 border-t border-slate-800 pt-10">
                <div class="mb-6">
                    <h3 class="text-lg font-extrabold text-white tracking-tight">Productos similares</h3>
                    <div class="h-1 w-12 bg-brand-red mt-1.5 rounded"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <div
                        v-for="rel in relacionados"
                        :key="rel.id"
                        class="bg-[#0F172A] border border-slate-800/90 rounded-lg overflow-hidden shadow flex flex-col justify-between group hover:border-slate-700 transition-all"
                    >
                        <!-- Cover Image -->
                        <Link :href="route('catalogo.show', rel.id)" class="relative aspect-[2/3] overflow-hidden bg-black/40 block">
                            <img
                                :src="rel.master?.portada_url"
                                :alt="rel.master?.titulo"
                                @error="$event.target.src = '/images/no-cover.png'"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            >
                        </Link>

                        <!-- Card Details -->
                        <div class="p-3 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-[11px] font-bold text-white group-hover:text-slate-200 transition-colors line-clamp-2 h-8 leading-tight">
                                    {{ rel.master?.titulo }} {{ rel.numero_tomo ? ' ' + rel.numero_tomo : '' }}
                                </h4>

                                <div class="mt-2 text-xs font-black text-white">
                                    {{ getPrecioFinal(rel) }}
                                </div>
                            </div>

                            <!-- Action Buttons: DETALLES | COMPRAR -->
                            <div class="grid grid-cols-2 gap-1.5 mt-3 pt-2 border-t border-slate-800/80">
                                <Link
                                    :href="route('catalogo.show', rel.id)"
                                    class="py-1 px-1 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded text-[10px] font-bold uppercase text-center transition-colors"
                                >
                                    DETALLES
                                </Link>

                                <button
                                    @click="agregarTomoDirecto(rel.id)"
                                    class="py-1 px-1 bg-[#D9434E] hover:bg-[#C23641] text-white rounded text-[10px] font-bold uppercase transition-colors"
                                >
                                    COMPRAR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods Modal -->
        <transition name="fade">
            <div v-if="showMediosPagoModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
                <div class="bg-[#0F172A] border border-slate-700 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <h3 class="text-sm font-extrabold text-white uppercase tracking-wider">Medios de Pago Aceptados</h3>
                        <button @click="showMediosPagoModal = false" class="text-slate-400 hover:text-white text-lg">✕</button>
                    </div>

                    <div class="space-y-3 text-xs text-slate-300">
                        <div class="p-3 bg-slate-900/90 rounded-lg border border-slate-800 space-y-1">
                            <p class="font-bold text-white text-xs">💳 Tarjetas de Crédito y Débito</p>
                            <p>Hasta 12 cuotas sin interés con Visa, MasterCard, American Express, Naranja.</p>
                        </div>

                        <div class="p-3 bg-slate-900/90 rounded-lg border border-slate-800 space-y-1">
                            <p class="font-bold text-white text-xs">💵 Transferencia o Depósito Bancario</p>
                            <p class="text-slate-300">Aceptamos transferencias desde cualquier banco o billetera virtual (Mercado Pago, MODO, etc.).</p>
                        </div>

                        <div class="p-3 bg-slate-900/90 rounded-lg border border-slate-800 space-y-1">
                            <p class="font-bold text-white text-xs">🏪 Efectivo en Sucursal / Rapipago / Pago Fácil</p>
                            <p>Pago inmediato al retirar en el local o en puntos de cobro.</p>
                        </div>
                    </div>

                    <button
                        @click="showMediosPagoModal = false"
                        class="w-full py-2.5 bg-slate-800 border border-slate-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-slate-700 transition-colors"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </transition>
    </PublicLayout>
</template>
