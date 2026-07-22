<script setup>
import { ref, watch, onUnmounted } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: String, default: '' },
    contexto: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);

const sugerencias        = ref([]);
const mostrarSugerencias = ref(false);
const buscando           = ref(false);
let debounceTimer = null;
let ultimaConsultaId = 0;

const formatear = (f) => {
    const p = f.properties;
    const calle = p.street || p.name || '';
    const conAltura = p.housenumber ? `${calle} ${p.housenumber}` : calle;
    return [conAltura, p.city, p.state].filter(Boolean).join(', ');
};

const buscar = async (query) => {
    if (query.trim().length < 3 || props.disabled) {
        sugerencias.value = [];
        mostrarSugerencias.value = false;
        return;
    }

    const consultaId = ++ultimaConsultaId;
    buscando.value = true;
    try {
        const params = new URLSearchParams({
            q: [query, props.contexto].filter(Boolean).join(', '),
            limit: '8',
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
        if (consultaId === ultimaConsultaId) buscando.value = false;
    }
};

const onInput = (e) => {
    const val = e.target.value;
    emit('update:modelValue', val);
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => buscar(val), 400);
};

const seleccionar = (f) => {
    emit('update:modelValue', formatear(f));
    emit('select', f);
    sugerencias.value        = [];
    mostrarSugerencias.value = false;
};

watch(() => props.contexto, () => {
    sugerencias.value        = [];
    mostrarSugerencias.value = false;
});

watch(() => props.disabled, (val) => {
    if (val) {
        sugerencias.value        = [];
        mostrarSugerencias.value = false;
    }
});

onUnmounted(() => clearTimeout(debounceTimer));
</script>

<template>
    <div class="relative">
        <input
            :value="modelValue"
            @input="onInput"
            @focus="mostrarSugerencias = sugerencias.length > 0"
            @blur="setTimeout(() => mostrarSugerencias = false, 150)"
            type="text"
            autocomplete="off"
            :disabled="disabled"
            v-bind="$attrs"
        />
        <ul
            v-if="mostrarSugerencias && sugerencias.length"
            class="absolute z-20 mt-1 w-full bg-[#1a1a1a] border border-white/10 rounded-xl shadow-2xl overflow-hidden max-h-56 overflow-y-auto"
        >
            <li
                v-for="(f, idx) in sugerencias"
                :key="idx"
                @mousedown.prevent="seleccionar(f)"
                class="px-4 py-2.5 text-xs text-white/60 hover:bg-white/5 hover:text-white cursor-pointer border-t border-white/5 first:border-t-0"
            >
                {{ formatear(f) }}
            </li>
        </ul>
    </div>
</template>