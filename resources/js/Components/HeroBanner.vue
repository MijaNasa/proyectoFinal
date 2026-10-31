<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    // Preventas activas: se usan sus portadas en el primer banner
    preventas: { type: Array, default: () => [] },
});

const portada = (isbn) => `/images/portadas/${isbn}.jpg`;

const portadasPreventa = computed(() => {
    const urls = props.preventas.map(p => p.portada_url).filter(Boolean).slice(0, 3);
    return urls.length ? urls : [portada('5555554'), portada('978444788589'), portada('9784447715833')];
});

// Cada banner: textos, botón y hasta 3 portadas a la derecha.
// Para cambiar o agregar promociones, se edita esta lista.
const slides = computed(() => [
    {
        kicker: 'Preventas · 10% OFF',
        titulo: 'Reservá los próximos tomos antes que nadie',
        texto: 'Asegurá tu ejemplar con 10% de descuento y te avisamos cuando llega.',
        boton: 'Ver preventas',
        href: route('catalogo.index', { preventa: 1 }),
        fondo: 'from-[#7f1d1d] via-[#3b0a0a] to-[#0d0d0f]',
        portadas: portadasPreventa.value,
    },
    {
        kicker: 'Envío gratis',
        titulo: 'Envío gratis a domicilio desde $100.000',
        texto: 'Y a sucursal de correo desde $80.000. Repartimos en Rosario con moto propia.',
        boton: 'Ver catálogo',
        href: route('catalogo.index', { solo_stock: 1 }),
        fondo: 'from-[#1e3a8a] via-[#0f172a] to-[#0d0d0f]',
        portadas: [portada('9789878190014'), portada('9789878190021'), portada('9789878190038')],
    },
    {
        kicker: 'Suscripciones',
        titulo: 'Seguí tu colección y ahorrá 5% en cada tomo',
        texto: 'Pedí la suscripción a tu serie en el local y recibí el descuento en cada número nuevo.',
        boton: 'Ver colecciones',
        href: route('catalogo.index'),
        fondo: 'from-[#14532d] via-[#052e16] to-[#0d0d0f]',
        portadas: [portada('9786071311009'), portada('9786071311016'), portada('9786071311023')],
    },
    {
        kicker: 'Clásicos del cómic',
        titulo: 'Batman, Watchmen y Spider-Man en ediciones de colección',
        texto: 'Las grandes historias del cómic americano, en tapa dura y rústica.',
        boton: 'Ver cómics',
        href: route('catalogo.index', { search: 'Batman' }),
        fondo: 'from-[#581c87] via-[#1e0a2e] to-[#0d0d0f]',
        portadas: [portada('9789877249904'), portada('9788419279010'), portada('9789877245012')],
    },
]);

const actual = ref(0);
const total = computed(() => slides.value.length);
let timer = null;

const ir = (i) => { actual.value = (i + total.value) % total.value; };
const siguiente = () => ir(actual.value + 1);
const anterior = () => ir(actual.value - 1);

const iniciar = () => { detener(); timer = setInterval(siguiente, 6000); };
const detener = () => { if (timer) clearInterval(timer); timer = null; };

// Deslizar con el dedo en el celular
let touchX = null;
const onTouchStart = (e) => { touchX = e.touches[0].clientX; detener(); };
const onTouchEnd = (e) => {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 40) (dx < 0 ? siguiente() : anterior());
    touchX = null;
    iniciar();
};

onMounted(iniciar);
onBeforeUnmount(detener);
</script>

<template>
    <section
        class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6"
        @mouseenter="detener"
        @mouseleave="iniciar"
    >
        <div
            class="relative overflow-hidden rounded-3xl border border-white/5 h-[300px] sm:h-[280px] lg:h-[300px]"
            @touchstart.passive="onTouchStart"
            @touchend="onTouchEnd"
        >
            <!-- Pista de slides -->
            <div
                class="flex h-full transition-transform duration-700 ease-out"
                :style="{ transform: `translateX(-${actual * 100}%)` }"
            >
                <div
                    v-for="(s, i) in slides"
                    :key="i"
                    class="relative w-full h-full shrink-0 bg-gradient-to-r"
                    :class="s.fondo"
                    :aria-hidden="i !== actual"
                >
                    <div class="h-full flex items-center gap-6 px-6 sm:px-10 lg:px-14">
                        <!-- Texto -->
                        <div class="flex-1 min-w-0 space-y-3 z-10">
                            <span class="inline-block text-[10px] sm:text-xs font-bold uppercase tracking-[0.2em] text-white/70">
                                {{ s.kicker }}
                            </span>
                            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold uppercase leading-tight text-white max-w-xl">
                                {{ s.titulo }}
                            </h2>
                            <p class="text-xs sm:text-sm text-white/70 max-w-md">
                                {{ s.texto }}
                            </p>
                            <Link
                                :href="s.href"
                                class="inline-flex items-center gap-2 mt-2 px-5 py-2.5 bg-white text-black font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-zinc-200 transition-colors shadow-lg"
                                :tabindex="i === actual ? 0 : -1"
                            >
                                {{ s.boton }}
                                <span aria-hidden="true">→</span>
                            </Link>
                        </div>

                        <!-- Portadas en abanico -->
                        <div class="hidden sm:flex relative w-[260px] lg:w-[320px] h-full items-center justify-center shrink-0">
                            <img
                                v-for="(img, j) in s.portadas"
                                :key="j"
                                :src="img"
                                alt=""
                                loading="lazy"
                                class="absolute h-[78%] aspect-[2/3] object-cover rounded-xl shadow-2xl border border-white/10"
                                :class="[
                                    j === 0 ? 'z-30 rotate-0' : '',
                                    j === 1 ? 'z-20 -rotate-6 -translate-x-20 lg:-translate-x-24 opacity-90' : '',
                                    j === 2 ? 'z-10 rotate-6 translate-x-20 lg:translate-x-24 opacity-90' : '',
                                ]"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flechas -->
            <button
                type="button"
                @click="anterior"
                class="absolute left-3 top-1/2 -translate-y-1/2 z-40 w-9 h-9 rounded-full bg-black/40 hover:bg-black/70 text-white flex items-center justify-center transition-colors cursor-pointer"
                aria-label="Promoción anterior"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button
                type="button"
                @click="siguiente"
                class="absolute right-3 top-1/2 -translate-y-1/2 z-40 w-9 h-9 rounded-full bg-black/40 hover:bg-black/70 text-white flex items-center justify-center transition-colors cursor-pointer"
                aria-label="Promoción siguiente"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>

            <!-- Puntos -->
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-40 flex gap-2">
                <button
                    v-for="(s, i) in slides"
                    :key="'dot' + i"
                    type="button"
                    @click="ir(i)"
                    class="h-2 rounded-full transition-all cursor-pointer"
                    :class="i === actual ? 'w-6 bg-white' : 'w-2 bg-white/40 hover:bg-white/70'"
                    :aria-label="`Ir a la promoción ${i + 1}`"
                ></button>
            </div>
        </div>
    </section>
</template>
