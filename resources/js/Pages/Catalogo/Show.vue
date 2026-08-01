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
        <div class="page-catalogo max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-16">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                <!-- Left: Product Image -->
                <div class="lg:col-span-5 flex justify-center">
                    <div class="w-full max-w-md">
                        <div class="relative rounded-2xl overflow-hidden bg-[#131316] border border-white/5 shadow-2xl">
                            <img
                                :src="libro.master?.portada_url"
                                :alt="libro.master?.titulo"
                                @error="$event.target.src = '/images/no-cover.png'"
                                class="w-full aspect-[2/3] object-cover"
                            >
                        </div>
                    </div>
                </div>

                <!-- Right: Title, Price, Action Row & Delivery -->
                <div class="lg:col-span-7 space-y-10 text-white">
                    <!-- Title above Price -->
                    <div class="space-y-1">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400 block">
                            {{ libro.master?.categoria?.nombre ?? 'MANGA' }} {{ libro.master?.proveedor ? '• ' + libro.master.proveedor.nombre_empresa : '' }}
                        </span>
                        <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                            {{ libro.master?.titulo }} {{ libro.numero_tomo ? libro.numero_tomo : '' }}
                            <span v-if="esPreventa" class="text-zinc-400 font-semibold text-lg ml-2">(Preventa)</span>
                        </h1>
                    </div>

                    <!-- Pricing Section -->
                    <div class="space-y-4">
                        <div class="flex items-baseline gap-3">
                            <span v-if="esPreventa" class="text-lg font-bold text-zinc-500 font-mono line-through">
                                {{ getPrecioOriginal(libro) }}
                            </span>
                            <span class="text-3xl sm:text-4xl font-bold text-white font-mono tracking-tight">
                                {{ getPrecioFinal(libro) }}
                            </span>
                        </div>

                        <!-- Preventa 10% Discount Note -->
                        <div v-if="esPreventa" class="text-xs font-semibold text-emerald-400">
                            ⚡ 10% de descuento especial por Preventa aplicado
                        </div>

                        <!-- Installments Info -->
                        <div class="flex items-center gap-2 text-xs font-semibold text-zinc-300">
                            <svg class="w-4 h-4 text-zinc-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span>12 cuotas de <strong class="text-white font-mono">{{ getCuotaMensual(libro) }}</strong></span>
                        </div>

                        <!-- Modal Ver Medios de Pago Trigger -->
                        <div class="pt-1">
                            <button
                                @click="showMediosPagoModal = true"
                                class="px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-xl text-xs font-semibold border border-white/10 transition-all"
                            >
                                VER MEDIOS DE PAGO
                            </button>
                        </div>
                    </div>

                    <!-- Quantity & Add to Cart Action Row -->
                    <div class="flex items-center gap-4 py-1">
                        <!-- Quantity Selector -->
                        <div v-if="getStockStatus(libro) !== 'sin_stock'" class="flex items-center border border-white/10 bg-[#0d0d0f] rounded-xl overflow-hidden shrink-0">
                            <button
                                @click="cantidad = Math.max(1, cantidad - 1)"
                                class="px-3.5 py-2.5 text-zinc-400 hover:text-white hover:bg-white/5 transition-colors font-bold text-xs"
                            >−</button>
                            <span class="px-4 py-2 text-xs font-bold text-white min-w-[2rem] text-center font-mono">{{ cantidad }}</span>
                            <button
                                @click="cantidad = cantidad + 1"
                                class="px-3.5 py-2.5 text-zinc-400 hover:text-white hover:bg-white/5 transition-colors font-bold text-xs"
                            >+</button>
                        </div>

                        <!-- Add to Cart Button -->
                        <button
                            @click="agregarAlCarrito"
                            :disabled="getStockStatus(libro) === 'sin_stock'"
                            class="py-3 px-8 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50 text-center"
                        >
                            {{ getStockStatus(libro) === 'sin_stock' ? 'AGOTADO' : 'AGREGAR AL CARRITO' }}
                        </button>
                    </div>

                    <!-- Shipping Calculator Box & Calculation Results -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-white">Medios de envío</h4>

                        <!-- Input form before calculation -->
                        <div v-if="!mostrandoResultadosCp" class="flex flex-wrap items-center gap-3">
                            <input
                                v-model="codigoPostal"
                                @keyup.enter="calcularEnvio"
                                type="text"
                                placeholder="Tu código postal"
                                class="bg-[#0d0d0f] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-zinc-500 focus:outline-none focus:border-white/30 font-medium w-44"
                            >
                            <button
                                @click="calcularEnvio"
                                class="px-5 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95"
                            >
                                CALCULAR
                            </button>
                        </div>

                        <!-- Results display when CP entered -->
                        <div v-else class="space-y-4">
                            <div class="flex items-center justify-between border-b border-white/5 pb-3">
                                <span class="text-xs font-semibold text-zinc-300">
                                    Entregas para el CP: <strong class="text-white font-mono">{{ cpCalculado }}</strong>
                                </span>
                                <button
                                    @click="cambiarCp"
                                    class="px-3 py-1 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs font-semibold rounded-xl border border-white/10 transition-all"
                                >
                                    CAMBIAR CP
                                </button>
                            </div>

                            <div class="p-3 bg-[#0d0d0f] rounded-xl border border-white/5 text-xs text-zinc-400 font-medium">
                                ¡Gracias por elegir <strong>PuroComic</strong>! Recordá ingresar tus datos reales en la compra para acumular puntos.
                            </div>

                            <!-- ENVÍO A DOMICILIO -->
                            <div class="space-y-2">
                                <h5 class="text-xs font-bold uppercase tracking-wider text-zinc-400">ENVÍO A DOMICILIO</h5>
                                <div class="bg-[#0d0d0f] border border-white/5 rounded-xl p-3.5 space-y-2 text-xs">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-white">Correo Argentino Clásico - Envío a domicilio</p>
                                            <p class="text-xs text-zinc-400 mt-0.5">Llega en 3 a 5 días hábiles</p>
                                        </div>
                                        <span class="font-bold text-white font-mono">{{ envioDomicilioClasico }}</span>
                                    </div>
                                    <div class="flex justify-between items-start border-t border-white/5 pt-2">
                                        <div>
                                            <p class="font-bold text-white">Correo Argentino Expreso - Envío a domicilio</p>
                                            <p class="text-xs text-zinc-400 mt-0.5">Llega en 1 a 2 días hábiles</p>
                                        </div>
                                        <span class="font-bold text-white font-mono">$12.619,31</span>
                                    </div>
                                </div>
                            </div>

                            <!-- RETIRAR POR -->
                            <div class="space-y-2">
                                <h5 class="text-xs font-bold uppercase tracking-wider text-zinc-400">RETIRAR POR</h5>
                                <div class="bg-[#0d0d0f] border border-white/5 rounded-xl p-3.5 space-y-2 text-xs">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-white">Punto de retiro - Sucursal Correo Argentino</p>
                                            <p class="text-xs text-zinc-400 mt-0.5">Retirás en 2 a 4 días hábiles</p>
                                        </div>
                                        <span class="font-bold text-white font-mono">{{ envioSucursalCorreo }}</span>
                                    </div>
                                    <div class="flex justify-between items-start border-t border-white/5 pt-2">
                                        <div>
                                            <p class="font-bold text-white">Retiro en el local - Sucursal Principal PuroComic</p>
                                            <p class="text-xs text-zinc-400 mt-0.5">Retiro inmediato en sucursal</p>
                                        </div>
                                        <span class="font-bold text-emerald-400">Gratis</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description / Synopsis -->
                    <div class="space-y-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-white">DESCRIPCIÓN</h3>
                        <div class="text-xs text-zinc-300 leading-relaxed space-y-2">
                            <p v-if="libro.synopsis">{{ libro.synopsis }}</p>
                            <p v-else>
                                Disfruta del tomo {{ libro.numero_tomo ?? '01' }} de {{ libro.master?.titulo }}. Editado originalmente por {{ libro.master?.proveedor?.nombre_empresa ?? 'la editorial' }}. Incluye páginas a color y sobrecubierta de alta calidad.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact Related Products Section ("Productos similares") -->
            <div v-if="relacionados && relacionados.length > 0" class="mt-12 space-y-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-white">Productos similares</h3>
                    <div class="h-px flex-1 bg-white/5"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
                    <div
                        v-for="rel in relacionados"
                        :key="rel.id"
                        class="bg-[#131316] hover:bg-[#18181c] border border-white/5 hover:border-white/10 rounded-2xl overflow-hidden shadow-xl flex flex-col justify-between group transition-all"
                    >
                        <!-- Cover Image -->
                        <Link :href="route('catalogo.show', rel.id)" class="relative aspect-[2/3] overflow-hidden bg-[#0d0d0f] block">
                            <img
                                :src="rel.master?.portada_url"
                                :alt="rel.master?.titulo"
                                @error="$event.target.src = '/images/no-cover.png'"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            >
                        </Link>

                        <!-- Card Details -->
                        <div class="p-3.5 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-white group-hover:text-zinc-200 transition-colors line-clamp-2 h-9 leading-tight">
                                    {{ rel.master?.titulo }} {{ rel.numero_tomo ? ' ' + rel.numero_tomo : '' }}
                                </h4>

                                <div class="mt-2 text-xs font-bold font-mono text-white">
                                    {{ getPrecioFinal(rel) }}
                                </div>
                            </div>

                            <!-- Action Buttons: DETALLES | COMPRAR -->
                            <div class="grid grid-cols-2 gap-1.5 mt-3 pt-2 border-t border-white/5">
                                <Link
                                    :href="route('catalogo.show', rel.id)"
                                    class="py-1.5 px-1.5 bg-white/5 hover:bg-white/10 text-white rounded-xl text-xs font-semibold text-center transition-colors"
                                >
                                    DETALLES
                                </Link>

                                <button
                                    @click="agregarTomoDirecto(rel.id)"
                                    class="py-1.5 px-1.5 bg-white hover:bg-zinc-200 text-black font-bold rounded-xl text-xs text-center transition-colors"
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
        <Teleport to="body">
            <div v-if="showMediosPagoModal" class="page-catalogo">
                <div class="fixed inset-0 z-[100] bg-black/90 backdrop-blur-md" @click="showMediosPagoModal = false" />
                <div class="fixed inset-0 z-[110] flex items-center justify-center p-4 pointer-events-none">
                    <div class="relative w-full max-w-md bg-[#0d0d0f] border border-white/10 rounded-2xl overflow-y-auto max-h-[85vh] shadow-2xl pointer-events-auto">
                        <div class="bg-[#131316] p-6 border-b border-white/5 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Medios de Pago Aceptados</h3>
                            <button @click="showMediosPagoModal = false" class="text-zinc-400 hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4 text-xs text-zinc-300">
                            <div class="p-4 bg-[#131316] rounded-2xl border border-white/5 space-y-1">
                                <p class="font-bold text-white text-xs">💳 Tarjetas de Crédito y Débito</p>
                                <p class="text-zinc-400 font-medium">Hasta 12 cuotas sin interés con Visa, MasterCard, American Express, Naranja.</p>
                            </div>

                            <div class="p-4 bg-[#131316] rounded-2xl border border-white/5 space-y-1">
                                <p class="font-bold text-white text-xs">💵 Transferencia o Depósito Bancario</p>
                                <p class="text-zinc-400 font-medium">Aceptamos transferencias desde cualquier banco o billetera virtual (Mercado Pago, MODO, etc.).</p>
                            </div>

                            <div class="p-4 bg-[#131316] rounded-2xl border border-white/5 space-y-1">
                                <p class="font-bold text-white text-xs">🏪 Efectivo en Sucursal / Rapipago / Pago Fácil</p>
                                <p class="text-zinc-400 font-medium">Pago inmediato al retirar en el local o en puntos de cobro.</p>
                            </div>
                        </div>

                        <div class="p-6 border-t border-white/5 bg-[#131316]">
                            <button
                                @click="showMediosPagoModal = false"
                                class="w-full py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95"
                            >
                                Entendido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-catalogo,
.page-catalogo * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
