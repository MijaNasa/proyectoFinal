<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
    mensajesIniciales: {
        type: Array,
        default: () => [],
    },
});

const abierto = ref(false);
const mensajes = ref(props.mensajesIniciales.map(m => ({ role: m.role, content: m.content })));
const nuevoMensaje = ref('');
const enviando = ref(false);
const contenedorMensajes = ref(null);

const toggleChat = () => {
    abierto.value = !abierto.value;
    if (abierto.value) scrollAlFinal();
};

const scrollAlFinal = async () => {
    await nextTick();
    if (contenedorMensajes.value) {
        contenedorMensajes.value.scrollTop = contenedorMensajes.value.scrollHeight;
    }
};

const enviarMensaje = async () => {
    const texto = nuevoMensaje.value.trim();
    if (!texto || enviando.value) return;

    mensajes.value.push({ role: 'user', content: texto });
    nuevoMensaje.value = '';
    enviando.value = true;
    scrollAlFinal();

    try {
        const { data } = await window.axios.post(route('mi-cuenta.chatbot.send'), {
            mensaje: texto,
        });
        mensajes.value.push({ role: 'assistant', content: data.respuesta });
    } catch (error) {
        const mensajeError = error?.response?.status === 429
            ? 'Alcanzaste el límite de mensajes por hora. Probá de nuevo más tarde.'
            : 'Ocurrió un error, por favor intentá de nuevo en unos minutos.';
        mensajes.value.push({ role: 'assistant', content: mensajeError });
    } finally {
        enviando.value = false;
        scrollAlFinal();
    }
};
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50">
        <button
            v-if="!abierto"
            @click="toggleChat"
            class="w-14 h-14 rounded-full bg-white text-black flex items-center justify-center shadow-2xl hover:bg-zinc-200 transition-all active:scale-95 text-2xl"
            aria-label="Abrir chat de ayuda"
        >💬</button>

        <div
            v-else
            class="w-80 sm:w-96 h-[500px] bg-[#131316] border border-white/10 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
                <span class="text-sm font-bold text-white">Asistente PuroComic</span>
                <button @click="toggleChat" class="text-zinc-400 hover:text-white text-lg leading-none" aria-label="Cerrar chat">×</button>
            </div>

            <div ref="contenedorMensajes" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
                <div v-if="mensajes.length === 0" class="text-xs text-zinc-500 text-center mt-8">
                    Preguntame sobre el catálogo, tus pedidos o cómo funciona la preventa.
                </div>
                <div
                    v-for="(m, idx) in mensajes"
                    :key="idx"
                    class="flex"
                    :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[80%] rounded-xl px-3 py-2 text-sm whitespace-pre-wrap"
                        :class="m.role === 'user' ? 'bg-white text-black' : 'bg-white/10 text-zinc-100'"
                    >{{ m.content }}</div>
                </div>
                <div v-if="enviando" class="flex justify-start">
                    <div class="max-w-[80%] rounded-xl px-3 py-2 text-sm bg-white/10 text-zinc-400 italic">
                        Escribiendo...
                    </div>
                </div>
            </div>

            <form @submit.prevent="enviarMensaje" class="flex items-center gap-2 p-3 border-t border-white/10">
                <input
                    v-model="nuevoMensaje"
                    type="text"
                    placeholder="Escribí tu consulta..."
                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-white/30"
                    :disabled="enviando"
                />
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-white text-black text-sm font-bold hover:bg-zinc-200 transition-all disabled:opacity-40"
                    :disabled="enviando || !nuevoMensaje.trim()"
                >Enviar</button>
            </form>
        </div>
    </div>
</template>
