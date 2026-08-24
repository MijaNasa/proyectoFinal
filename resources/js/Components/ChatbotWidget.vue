<script setup>
import { ref, nextTick } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const MAX_CARACTERES = 200;

const page = usePage();
const estaLogueado = () => !!page.props.auth?.user;

const abierto = ref(false);
const cargando = ref(false);
const input = ref('');
const cuerpoRef = ref(null);
const limiteAlcanzado = ref(false);

const mensajes = ref([
    { role: 'assistant', content: '¡Hola! 👋 Contame para quién es el libro que buscás y qué le gusta leer, y te recomiendo algo de nuestro catálogo.' },
]);

const sugerencias = [
    'Es un regalo para alguien que recién empieza a leer manga',
    'Busco algo de terror o suspenso, ya leo bastante',
    'Quiero algo de acción/shonen para un adolescente',
];

const usarSugerencia = (s) => {
    input.value = s;
};

const scrollAbajo = () => {
    nextTick(() => {
        if (cuerpoRef.value) cuerpoRef.value.scrollTop = cuerpoRef.value.scrollHeight;
    });
};

const toggle = () => {
    abierto.value = !abierto.value;
    if (abierto.value) scrollAbajo();
};

const formatearMensaje = (texto) => {
    if (!texto) return '';
    let html = texto
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Parse Markdown links [Texto](/catalogo/123)
    html = html.replace(
        /\[([^\]]+)\]\(([^)]+)\)/g,
        '<a href="$2" class="underline text-red-400 font-bold hover:text-white transition-colors">$1</a>'
    );

    // Parse Bold **texto**
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

    return html;
};

const enviar = async () => {
    const texto = input.value.trim();
    if (!texto || cargando.value || limiteAlcanzado.value) return;

    mensajes.value.push({ role: 'user', content: texto });
    input.value = '';
    cargando.value = true;
    scrollAbajo();

    try {
        const res = await window.axios.post(route('chatbot.responder'), {
            mensajes: mensajes.value,
        });
        mensajes.value.push({ role: 'assistant', content: res.data.reply });
        if (res.data.limite_alcanzado) limiteAlcanzado.value = true;
    } catch (e) {
        mensajes.value.push({ role: 'assistant', content: 'Tuve un problema para responder. ¿Podés intentar de nuevo en un momento?' });
    } finally {
        cargando.value = false;
        scrollAbajo();
    }
};
</script>

<template>
    <div class="fixed bottom-24 right-5 z-[150] flex flex-col items-end">

        <!-- Panel de chat -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div v-if="abierto" class="mb-3 w-[90vw] max-w-sm h-[70vh] max-h-[520px] bg-[#131316] border border-white/10 rounded-2xl shadow-2xl flex flex-col overflow-hidden">
                <!-- Header -->
                <div class="bg-[#1A1A1A] border-b border-white/10 px-4 py-3 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-brand-red flex items-center justify-center rounded-lg shadow-[0_0_10px_rgba(230,25,25,0.4)]">
                            <span class="text-xs">🤖</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-white uppercase tracking-wide">Asistente IA PuroComic</span>
                            <span class="text-[9px] text-emerald-400 font-semibold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> En línea
                            </span>
                        </div>
                    </div>
                    <button @click="toggle" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Banner aviso para invitados (no logueados) -->
                <div v-if="!estaLogueado()" class="bg-amber-500/10 border-b border-amber-500/20 px-3 py-1.5 text-[10px] text-amber-300 flex items-center justify-between shrink-0">
                    <span>Modo invitado (5 respuestas/12h).</span>
                    <Link :href="route('login')" class="font-bold underline text-white hover:text-amber-200">Iniciar sesión</Link>
                </div>

                <!-- Mensajes -->
                <div ref="cuerpoRef" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    <div
                        v-for="(m, i) in mensajes" :key="i"
                        class="flex"
                        :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-xs leading-relaxed whitespace-pre-line"
                            :class="m.role === 'user'
                                ? 'bg-brand-red text-white rounded-br-sm'
                                : 'bg-white/5 border border-white/10 text-white/90 rounded-bl-sm'"
                            v-html="formatearMensaje(m.content)"
                        ></div>
                    </div>
                    <div v-if="cargando" class="flex justify-start">
                        <div class="bg-white/5 border border-white/10 rounded-2xl rounded-bl-sm px-3.5 py-2.5 text-xs text-white/40 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-brand-red animate-ping"></span>
                            Pensando recomendaciones...
                        </div>
                    </div>

                    <!-- Tip + sugerencias: solo antes del primer mensaje -->
                    <div v-if="mensajes.length === 1" class="space-y-2 pt-1">
                        <p class="text-[10px] text-white/40 leading-relaxed px-0.5">
                            💡 Tip: contame qué te gusta o a quién se lo vas a regalar — así te recomiendo directo de nuestro catálogo.
                        </p>
                        <div class="flex flex-col gap-1.5">
                            <button
                                v-for="s in sugerencias" :key="s"
                                type="button"
                                @click="usarSugerencia(s)"
                                class="text-left text-[11px] text-white/70 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl px-3 py-2 transition-colors"
                            >
                                {{ s }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <form @submit.prevent="enviar" class="border-t border-white/10 p-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <input
                            v-model="input"
                            type="text"
                            :maxlength="MAX_CARACTERES"
                            placeholder="Escribí tu mensaje..."
                            :disabled="cargando || limiteAlcanzado"
                            class="flex-1 bg-[#0A0A0A] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 disabled:opacity-50"
                        />
                        <button
                            type="submit"
                            :disabled="cargando || limiteAlcanzado || !input.trim()"
                            class="bg-brand-red hover:bg-brand-red/80 disabled:opacity-30 disabled:cursor-not-allowed text-white w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </button>
                    </div>
                    <div v-if="!limiteAlcanzado" class="text-right text-[10px] text-white/20 mt-1 pr-1">{{ input.length }}/{{ MAX_CARACTERES }}</div>
                </form>
            </div>
        </transition>

        <!-- Botón flotante -->
        <button
            @click="toggle"
            class="relative w-14 h-14 bg-brand-red hover:bg-brand-red/80 text-white rounded-full shadow-[0_0_20px_rgba(230,25,25,0.4)] flex items-center justify-center transition-all active:scale-95"
            title="Asistente de recomendaciones IA"
        >
            <span v-if="!abierto" class="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-400 rounded-full border-2 border-[#0A0A0A] animate-pulse"></span>
            <svg v-if="!abierto" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
            <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</template>