<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: String, // 'success' | 'pending' | 'failure'
    venta:  Object,
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

const uploadComprobante = () => {
    uploadForm.post(route('mi-cuenta.comprobante', props.venta.id), {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
        },
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
                    <div v-if="venta.metodo_pago === 'Transferencia' && venta.estado === 'pendiente_pago'" class="pt-4 border-t border-white/5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-300 mb-3">
                            ¿Ya realizaste la transferencia? Subí tu comprobante
                        </h4>
                        
                        <div v-if="venta.comprobante_path" class="flex items-center gap-3 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                            <span class="text-emerald-400 text-lg">✅</span>
                            <p class="text-xs font-semibold text-zinc-200">Comprobante enviado exitosamente. Pendiente de verificación.</p>
                            <div class="ml-auto flex items-center gap-3 shrink-0">
                                <a :href="route('mi-cuenta.comprobante.ver', venta.id)" target="_blank" class="text-xs font-bold text-emerald-400 uppercase hover:underline">Ver</a>
                                <button @click="deleteComprobante" class="text-zinc-400 hover:text-rose-400 transition-colors" title="Eliminar comprobante">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                        
                        <form v-else @submit.prevent="uploadComprobante" class="flex flex-col sm:flex-row items-center gap-3 p-4 bg-[#0d0d0f] border border-white/10 rounded-xl">
                            <input 
                                type="file" 
                                accept="image/*"
                                @input="uploadForm.comprobante = $event.target.files[0]"
                                class="w-full text-xs text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:uppercase file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer"
                                required
                            >
                            <button 
                                type="submit"
                                :disabled="uploadForm.processing || !uploadForm.comprobante"
                                class="w-full sm:w-auto px-6 py-2.5 bg-white hover:bg-zinc-200 text-black font-bold text-xs uppercase tracking-wider rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-40 cursor-pointer"
                            >
                                {{ uploadForm.processing ? 'Enviando...' : 'Enviar' }}
                            </button>
                        </form>
                        <p v-if="uploadForm.errors.comprobante" class="text-rose-400 text-xs mt-2 font-semibold">{{ uploadForm.errors.comprobante }}</p>
                    </div>
                </div>

                <!-- Invitación a Registrarse (Solo para compras de invitados sin sesión activa) -->
                <div v-if="!$page.props.auth?.user && venta" class="bg-[#131316] border border-white/10 rounded-2xl p-6 sm:p-8 shadow-xl text-left">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center shrink-0 text-xl">
                            🎁
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-white">
                                ¿Querés hacer seguimiento a tus pedidos?
                            </h3>
                            <p class="text-xs text-zinc-400 leading-relaxed font-medium">
                                Vinculamos esta compra a tu DNI <strong v-if="venta.guest_dni" class="text-white font-mono">{{ venta.guest_dni }}</strong><span v-else>ingresado</span>. Podés registrarte con tu DNI en cualquier momento para ingresar a tu cuenta y ver el seguimiento en tiempo real.
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
