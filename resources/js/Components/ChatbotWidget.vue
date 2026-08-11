<script setup>
import { ref, nextTick } from 'vue';

const abierto = ref(false);
const cargando = ref(false);
const input = ref('');
const cuerpoRef = ref(null);

const mensajes = ref([
    { role: 'assistant', content: '¡Hola! 👋 Contame para quién es el libro que buscás y qué le gusta leer, y te recomiendo algo de nuestro catálogo.' },
]);

const scrollAbajo = () => {
    nextTick(() => {
        if (cuerpoRef.value) cuerpoRef.value.scrollTop = cuerpoRef.value.scrollHeight;
    });
};

const toggle = () => {
    abierto.value = !abierto.value;
    if (abierto.value) scrollAbajo();
};

const enviar = async () => {
    const texto = input.value.trim();
    if (!texto || cargando.value) return;

    mensajes.value.push({ role: 'user', content: texto });
    input.value = '';
    cargando.value = true;
    scrollAbajo();

    try {
        const res = await window.axios.post(route('chatbot.responder'), {
            mensajes: mensajes.value,
        });
        mensajes.value.push({ role: 'assistant', content: res.data.reply });
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
                            <span class="text-xs">📚</span>
                        </div>
                        <span class="text-sm font-bold text-white uppercase tracking-wide">Asistente PuroComic</span>
                    </div>
                    <button @click="toggle" class="text-white/40 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
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
                        >
                            {{ m.content }}
                        </div>
                    </div>
                    <div v-if="cargando" class="flex justify-start">
                        <div class="bg-white/5 border border-white/10 rounded-2xl rounded-bl-sm px-3.5 py-2.5 text-xs text-white/40">
                            Pensando...
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <form @submit.prevent="enviar" class="border-t border-white/10 p-3 flex items-center gap-2 shrink-0">
                    <input
                        v-model="input"
                        type="text"
                        placeholder="Escribí tu mensaje..."
                        :disabled="cargando"
                        class="flex-1 bg-[#0A0A0A] border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-white/30 focus:outline-none focus:border-brand-red/50 disabled:opacity-50"
                    />
                    <button
                        type="submit"
                        :disabled="cargando || !input.trim()"
                        class="bg-brand-red hover:bg-brand-red/80 disabled:opacity-30 disabled:cursor-not-allowed text-white w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </form>
            </div>
        </transition>

        <!-- Botón flotante -->
        <button
            @click="toggle"
            class="w-14 h-14 bg-brand-red hover:bg-brand-red/80 text-white rounded-full shadow-[0_0_20px_rgba(230,25,25,0.4)] flex items-center justify-center transition-all active:scale-95"
        >
            <svg v-if="!abierto" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
            <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
</template>