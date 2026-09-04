<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: String, // 'success' | 'pending' | 'failure'
    venta:  Object,
    whatsappNumber: String,
});

const whatsappUrl = computed(() => {
    if (!props.venta) return null;
    const phone = props.whatsappNumber || '5493414245566';
    const cleanPhone = phone.replace(/\D/g, '');
    const clientName = props.venta.cliente_nombre ? ` de ${props.venta.cliente_nombre}` : '';
    const text = encodeURIComponent(`¡Hola Puro Cómic! Te adjunto el comprobante de transferencia para mi pedido #${props.venta.id}${clientName} por ${formatPrecio(props.venta.total)}.`);
    return `https://wa.me/${cleanPhone}?text=${text}`;
});

const uploadForm = useForm({
    comprobante: null,
});

const deleteComprobante = () => {
    if (confirm('¿Estás seguro de que querés eliminar el comprobante?')) {
        router.delete(route('mi-cuenta.comprobante.delete', props.venta.id), {
            preserveScroll: true
        });
    }
};

const handleFileChange = (event) => {
    uploadForm.clearErrors('comprobante');
    const file = event.target.files[0];
    if (!file) return;

    const MAX_SIZE_MB = 7;
    const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;

    if (file.size > MAX_SIZE_BYTES) {
        event.target.value = '';
        uploadForm.comprobante = null;
        uploadForm.setError('comprobante', `El archivo es demasiado grande (${(file.size / (1024 * 1024)).toFixed(1)} MB). El límite máximo permitido es de ${MAX_SIZE_MB} MB. Por favor elegí una captura o foto más liviana.`);
        return;
    }

    uploadForm.comprobante = file;
};

const uploadComprobante = () => {
    uploadForm.post(route('mi-cuenta.comprobante', props.venta.id), {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
        },
        onError: (errs) => {
            if (errs.comprobante) return;
            uploadForm.setError('comprobante', 'No se pudo subir el archivo. Verificá el tamaño e intentá nuevamente.');
        }
    });
};

const formatPrecio = (valor) =>
    new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);

const requierePagoManual = computed(() =>
    props.venta?.estado === 'pendiente_pago' &&
    ['Efectivo', 'Transferencia'].includes(props.venta?.metodo_pago)
);

const config = computed(() => {
    if (props.status === 'success' && requierePagoManual.value) {
        return props.venta.metodo_pago === 'Transferencia'
            ? {
                icon:     '🏦',
                iconBg:   'bg-amber-400/10 border-amber-400/20 text-amber-400',
                title:    'Pedido Registrado',
                subtitle: 'Falta confirmar tu pago: realiza la transferencia bancaria y adjuntá el comprobante.',
                color:    'text-amber-400',
            }
            : {
                icon:     '⏳',
                iconBg:   'bg-amber-400/10 border-amber-400/20 text-amber-400',
                title:    'Pedido Registrado',
                subtitle: 'Tu stock quedó reservado por 12hs. Acercate a la sucursal a abonar en efectivo.',
                color:    'text-amber-400',
            };
    }

    return ({
        success: {
            icon:     '✓',
            iconBg:   'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
            title:    '¡Pago Aprobado!',
            subtitle: 'Tu pedido fue confirmado correctamente y ya está en proceso de preparación.',
            color:    'text-emerald-400',
        },
        pending: {
            icon:     '⏳',
            iconBg:   'bg-amber-400/10 border-amber-400/20 text-amber-400',
            title:    'Pago Pendiente',
            subtitle: 'Tu pago está siendo procesado por la pasarela. Te avisaremos apenas se confirme.',
            color:    'text-amber-400',
        },
        failure: {
            icon:     '✕',
            iconBg:   'bg-rose-500/10 border-rose-500/20 text-rose-400',
            title:    'Pago No Procesado',
            subtitle: 'Ocurrió un inconveniente al procesar tu pago. Podés reintentarlo nuevamente.',
            color:    'text-rose-400',
        },
    }[props.status] ?? {});
});
</script>

<template>
    <Head title="Confirmación de Compra | PuroComic" />

    <PublicLayout>
        <div class="page-confirmacion">
            <!-- Hero Header / Status -->
            <div class="relative overflow-hidden py-12 sm:py-16 bg-gradient-to-b from-white/[0.04] to-transparent border-b border-white/5">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
                    <div
                        class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl border flex items-center justify-center mx-auto text-3xl sm:text-4xl font-bold shadow-2xl backdrop-blur-sm"
                        :class="config.iconBg"
                    >
                        {{ config.icon }}
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-bold tracking-tight uppercase leading-none text-white">
                        {{ config.title }}
                    </h1>
                    <p class="text-zinc-400 text-xs sm:text-sm font-medium max-w-lg mx-auto leading-relaxed">
                        {{ config.subtitle }}
                    </p>
                </div>
            </div>

            <!-- Content Container -->
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-6">

                <!-- Detalle del pedido -->
                <div v-if="venta" class="bg-[#131316] border border-white/5 rounded-2xl p-6 sm:p-8 shadow-xl space-y-4">
                    <div class="flex items-center justify-between border-b border-white/5 pb-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                            Detalle del Pedido
                        </h3>
                        <span class="text-xs font-mono text-zinc-400 bg-white/5 border border-white/10 px-2.5 py-0.5 rounded-full">
                            ID: #{{ venta.id }}
                        </span>
                    </div>

                    <div class="space-y-2.5 text-xs sm:text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-zinc-400 font-medium">Método de Pago</span>
                            <span class="font-bold text-white uppercase">{{ venta.metodo_pago || '—' }}</span>
                        </div>
                        <div v-if="venta.tipo_envio" class="flex justify-between items-center">
                            <span class="text-zinc-400 font-medium">Modalidad de Entrega</span>
                            <span class="font-bold text-white capitalize">
                                {{ venta.tipo_envio === 'retiro' ? 'Retiro en sucursal (Nuestra Tienda)' : (venta.tipo_envio === 'acumulacion' ? 'Acumulación de envío' : (venta.tipo_envio === 'correo_sucursal' ? 'Envío a Sucursal Correo Argentino' : 'Envío a domicilio')) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-baseline pt-3 border-t border-white/5">
                            <span class="text-sm font-bold uppercase tracking-wider text-white">Total Abonado</span>
                            <span class="text-2xl font-bold font-mono text-white">{{ formatPrecio(venta.total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Datos Bancarios para Transferencia -->
                <div v-if="venta && venta.metodo_pago === 'Transferencia'" class="bg-[#131316] border border-cyan-500/20 rounded-2xl p-6 sm:p-8 shadow-xl space-y-4 text-left">
                    <div class="flex items-center gap-3 border-b border-white/5 pb-3">
                        <span class="text-2xl">🏦</span>
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-cyan-400">Datos Bancarios para Transferencia</h3>
                            <p class="text-zinc-400 text-xs mt-0.5">Transferí el monto total para procesar el despacho de tu pedido.</p>
                        </div>
                    </div>

                    <div class="bg-[#0d0d0f] border border-white/10 rounded-xl p-4 space-y-3 text-xs sm:text-sm font-mono">
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-white/5 pb-2">
                            <span class="text-zinc-500 uppercase text-[10px] font-bold font-sans">CBU</span>
                            <span class="font-bold text-white select-all">0000003100010000000000</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-white/5 pb-2">
                            <span class="text-zinc-500 uppercase text-[10px] font-bold font-sans">Alias</span>
                            <span class="font-bold text-white select-all">puro.comic</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-white/5 pb-2">
                            <span class="text-zinc-500 uppercase text-[10px] font-bold font-sans">Titular</span>
                            <span class="font-bold text-white">Puro Cómic</span>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
                            <span class="text-zinc-500 uppercase text-[10px] font-bold font-sans">Banco</span>
                            <span class="font-bold text-white">Banco Ficticio</span>
                        </div>
                    </div>

                    <!-- Subir Comprobante Inmediato -->
                    <div v-if="venta.metodo_pago === 'Transferencia' && venta.estado === 'pendiente_pago'" class="pt-4 border-t border-white/5 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-300">
                            Comprobante de Transferencia
                        </h4>
                        
                        <!-- Si el comprobante ya fue subido y no se ha seleccionado un nuevo archivo -->
                        <div v-if="venta.comprobante_path && !uploadForm.comprobante" class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 font-extrabold text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    Enviado
                                </span>
                                <p class="text-xs text-zinc-200 font-medium">Comprobante en verificación.</p>
                            </div>
                            <div class="ml-auto flex items-center gap-3 shrink-0">
                                <a :href="route('mi-cuenta.comprobante.ver', venta.id)" target="_blank" class="text-xs font-bold text-emerald-400 uppercase hover:underline">Ver</a>
                                <button @click="deleteComprobante" class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-white/5 rounded-lg transition-colors" title="Eliminar comprobante">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Formulario si no hay comprobante o si seleccionó un nuevo archivo -->
                        <form v-else @submit.prevent="uploadComprobante" class="flex flex-col sm:flex-row items-center gap-3 p-4 bg-[#0d0d0f] border border-white/10 rounded-xl">
                            <div class="flex-1 w-full min-w-0">
                                <input 
                                    type="file" 
                                    accept="image/*,.pdf"
                                    @change="handleFileChange"
                                    class="w-full text-xs text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:uppercase file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer"
                                >
                                <p v-if="venta.comprobante_path" class="text-[11px] text-amber-400 mt-1 font-medium">
                                    Seleccionaste un nuevo archivo para reemplazar el anterior.
                                </p>
                            </div>
                            <button 
                                v-if="uploadForm.comprobante"
                                type="submit"
                                :disabled="uploadForm.processing"
                                class="w-full sm:w-auto px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-40 cursor-pointer shrink-0"
                            >
                                {{ uploadForm.processing ? 'Enviando...' : 'Enviar' }}
                            </button>
                        </form>
                        <p v-if="uploadForm.errors.comprobante" class="text-rose-400 text-xs mt-2 font-semibold">{{ uploadForm.errors.comprobante }}</p>

                        <!-- WhatsApp contingency button -->
                        <div class="pt-4 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between gap-3 bg-emerald-500/5 p-4 rounded-xl border border-emerald-500/20">
                            <div class="flex items-center gap-3 text-left">
                                <span class="text-2xl">💬</span>
                                <div>
                                    <p class="text-xs font-bold text-emerald-400">¿Preferís enviarlo por WhatsApp o se te cerró la ventana?</p>
                                    <p class="text-[11px] text-zinc-400">Podés enviar el comprobante directamente a nuestro chat oficial indicando tu número de pedido.</p>
                                </div>
                            </div>
                            <a 
                                v-if="whatsappUrl"
                                :href="whatsappUrl" 
                                target="_blank" 
                                class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md flex items-center justify-center gap-2 shrink-0 cursor-pointer active:scale-95"
                            >
                                <span>Enviar por WhatsApp</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Botón de Descarga del Comprobante PDF -->
                <div v-if="venta" class="flex justify-center">
                    <a 
                        :href="route('pedidos.comprobante-pdf', venta.id)" 
                        target="_blank" 
                        class="w-full py-3.5 px-6 bg-white/10 hover:bg-white/15 text-white font-bold text-xs uppercase tracking-wider rounded-2xl border border-white/10 transition-all flex items-center justify-center gap-2.5 shadow-md cursor-pointer active:scale-95"
                    >
                        <svg class="w-4 h-4 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Descargar Comprobante / Resumen del Pedido (PDF)</span>
                    </a>
                </div>

                <!-- Información de Acceso por DNI (Solo para compras de invitados sin sesión activa) -->
                <div v-if="!$page.props.auth?.user && venta" class="bg-[#131316] border border-white/10 rounded-2xl p-6 sm:p-8 shadow-xl text-left">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center shrink-0 text-xl">
                            🔑
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                                ¿Se te cerró esta pestaña o querés hacer seguimiento?
                            </h3>
                            <p class="text-xs text-zinc-400 leading-relaxed font-medium">
                                Tu cuenta fue generada automáticamente con tu DNI <strong v-if="venta.guest_dni" class="text-white font-mono">{{ venta.guest_dni }}</strong>. Si cerrás el navegador, podés entrar en cualquier momento a <Link :href="route('login')" class="text-white underline font-semibold hover:text-zinc-300">Iniciar Sesión</Link> con tu correo y tu <strong>DNI como contraseña</strong> para ver tus pedidos o cargar tu comprobante.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center pt-4">
                    <template v-if="status === 'success' || status === 'pending'">
                        <Link
                            :href="$page.props.auth?.user ? route('mi-cuenta.index') : route('register')"
                            class="px-8 py-3.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg active:scale-95 text-center cursor-pointer"
                        >
                            {{ $page.props.auth?.user ? 'Ver Mis Pedidos' : 'Registrarme / Ver Mis Pedidos' }}
                        </Link>
                        <Link
                            :href="route('catalogo.index')"
                            class="px-8 py-3.5 bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 font-semibold text-xs uppercase tracking-wider rounded-xl transition-all text-center cursor-pointer"
                        >
                            Seguir Comprando
                        </Link>
                    </template>

                    <template v-if="status === 'failure'">
                        <Link
                            :href="route('carrito.index')"
                            class="px-8 py-3.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-lg active:scale-95 text-center cursor-pointer"
                        >
                            Volver al Carrito
                        </Link>
                        <Link
                            :href="route('catalogo.index')"
                            class="px-8 py-3.5 bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 font-semibold text-xs uppercase tracking-wider rounded-xl transition-all text-center cursor-pointer"
                        >
                            Ver Catálogo
                        </Link>
                    </template>
                </div>

            </div>
        </div>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-confirmacion,
.page-confirmacion * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
