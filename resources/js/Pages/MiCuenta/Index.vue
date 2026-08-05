<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { decodeLabel } from '@/composables/useDecodeLabel';

const props = defineProps({
    pedidos: Object,
    usuario: Object,
});

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

const uploadForm = useForm({
    comprobante: null,
});

const uploadComprobante = (pedidoId) => {
    uploadForm.post(route('mi-cuenta.comprobante', pedidoId), {
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            darkSwal.fire({
                title: 'Comprobante Enviado',
                text: 'El comprobante ha sido subido con éxito y está pendiente de verificación.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        },
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
    listo_para_retirar: { label: 'Listo para retiro',  bgDot: 'bg-emerald-400' },
    acumulado:          { label: 'Acumulado',          bgDot: 'bg-amber-400' },
    enviado:            { label: 'Enviado',             bgDot: 'bg-indigo-400' },
    entregado:          { label: 'Entregado',           bgDot: 'bg-emerald-400' },
    retirado:           { label: 'Retirado',            bgDot: 'bg-emerald-400' },
    completada:         { label: 'Completada',          bgDot: 'bg-emerald-400' },
    finalizado:         { label: 'Finalizado',          bgDot: 'bg-emerald-400' },
    cancelado:          { label: 'Cancelado',           bgDot: 'bg-rose-500' },
};

const getTipoEnvioLabel = (tipo) => {
    if (tipo === 'retiro') return 'Retiro en sucursal';
    if (tipo === 'acumulacion') return 'Acumulación en sucursal';
    if (tipo === 'correo_sucursal') return 'Envío a Sucursal Correo Argentino';
    return 'Envío a domicilio';
};

const tieneAcumulados = computed(() => {
    if (!props.pedidos?.data) return false;
    return props.pedidos.data.some(p => p.estado === 'acumulado');
});

const solicitarEnvioAcumulados = () => {
    darkSwal.fire({
        title: 'Próximamente',
        text: 'La funcionalidad para solicitar el envío de pedidos acumulados estará disponible pronto.',
        icon: 'info',
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
                            <p class="text-zinc-400 text-xs font-medium">Tenés pedidos guardados en sucursal. Solicitá el envío para recibirlos todos juntos pagando un solo envío.</p>
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
                                        <span>{{ getTipoEnvioLabel(pedido.tipo_envio) }}</span>
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
                            <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-400 border-b border-white/5 pb-2">
                                Detalle del pedido ({{ pedido.items?.length || 0 }} {{ (pedido.items?.length === 1) ? 'producto' : 'productos' }})
                            </h4>

                            <!-- Items List -->
                            <div class="space-y-2">
                                <div v-for="(item, i) in pedido.items" :key="i" class="flex justify-between items-center text-sm py-1.5 border-b border-white/5 last:border-0">
                                    <span class="text-zinc-300 font-medium truncate">
                                        {{ item.titulo }}
                                        <span class="text-zinc-500 text-xs font-bold ml-1">x{{ item.cantidad }}</span>
                                    </span>
                                    <span class="font-bold font-mono text-zinc-400 ml-4 shrink-0">{{ formatPrecio(item.subtotal) }}</span>
                                </div>
                            </div>

                            <!-- Banner Listo para retirar -->
                            <div v-if="pedido.estado === 'listo_para_retirar' && ['retiro', 'acumulacion'].includes(pedido.tipo_envio)" class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl flex items-start gap-3 mt-4">
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
                                
                                <div v-if="pedido.comprobante_path" class="flex items-center gap-3">
                                    <span class="text-emerald-400">✅</span>
                                    <p class="text-xs text-zinc-300 font-medium">Comprobante enviado. Esperando verificación.</p>
                                    <div class="ml-auto flex items-center gap-3">
                                        <a :href="route('mi-cuenta.comprobante.ver', pedido.id)" target="_blank" class="text-xs font-semibold text-white hover:underline">Ver adjunto</a>
                                        <button @click.stop="deleteComprobante(pedido.id)" class="text-zinc-500 hover:text-rose-400 transition-colors" title="Eliminar comprobante">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <form v-else @submit.prevent="uploadComprobante(pedido.id)" class="flex flex-col sm:flex-row items-center gap-3">
                                    <input 
                                        type="file" 
                                        accept="image/*"
                                        @input="uploadForm.comprobante = $event.target.files[0]"
                                        class="w-full text-xs text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-white/10 file:text-white hover:file:bg-white/20 cursor-pointer"
                                        required
                                    >
                                    <button 
                                        type="submit"
                                        :disabled="uploadForm.processing || !uploadForm.comprobante"
                                        class="w-full sm:w-auto px-5 py-2 bg-white hover:bg-zinc-200 text-black text-xs font-bold uppercase rounded-xl transition-all shadow-md active:scale-95 disabled:opacity-50 shrink-0"
                                    >
                                        {{ uploadForm.processing ? 'Enviando...' : 'Enviar comprobante' }}
                                    </button>
                                </form>
                                <p v-if="uploadForm.errors.comprobante" class="text-rose-400 text-xs font-semibold mt-1">{{ uploadForm.errors.comprobante }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="pedidos.links?.length > 3" class="mt-8 flex justify-center gap-2">
                    <Link
                        v-for="link in pedidos.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="px-4 py-2 rounded-xl border border-white/5 transition-all text-xs font-semibold"
                        :class="{'bg-white text-black border-white shadow-md': link.active, 'text-zinc-500 hover:text-white bg-white/5': !link.active && link.url, 'text-zinc-600 cursor-not-allowed': !link.url}"
                    >{{ decodeLabel(link.label) }}</Link>
                </div>
                </div>
            </div>

        </div>
    </PublicLayout>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap');

.page-micuenta,
.page-micuenta * {
    font-family: 'Montserrat', sans-serif !important;
}
</style>
