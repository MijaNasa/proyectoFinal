<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-lg border-white/10 bg-black/40 text-sm font-bold text-white shadow-sm transition-all focus:border-brand-red focus:ring-brand-red placeholder:text-white/20 uppercase"
        v-model="model"
        ref="input"
    />
</template>
