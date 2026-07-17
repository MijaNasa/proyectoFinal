<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: ''
    },
    options: {
        type: Array,
        required: true
    },
    labelKey: {
        type: [String, Function],
        default: 'nombre'
    },
    valueKey: {
        type: String,
        default: 'id'
    },
    placeholder: {
        type: String,
        default: 'Buscar...'
    },
    required: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const isOpen = ref(false);
const wrapperRef = ref(null);

// Get the display label for an option
const getLabel = (option) => {
    if (typeof option === 'string' || typeof option === 'number') return option;
    if (typeof props.labelKey === 'function') {
        return props.labelKey(option);
    }
    return option[props.labelKey];
};

// Find the currently selected option to show its label when closed
const selectedOption = computed(() => {
    return props.options.find(opt => {
        if (typeof opt === 'string' || typeof opt === 'number') return opt === props.modelValue;
        return opt[props.valueKey] === props.modelValue;
    });
});

// Update the search box when the modelValue changes externally
watch(() => props.modelValue, (newVal) => {
    if (newVal) {
        const opt = props.options.find(o => {
            if (typeof o === 'string' || typeof o === 'number') return o === newVal;
            return o[props.valueKey] === newVal;
        });
        if (opt) search.value = getLabel(opt);
    } else {
        search.value = '';
    }
}, { immediate: true });

const filteredOptions = computed(() => {
    if (!search.value) return props.options;
    
    // If the search exactly matches the selected item, show all options 
    // (meaning they clicked it and it populated the input, now they want to see the list again)
    if (selectedOption.value && getLabel(selectedOption.value) === search.value) {
        return props.options;
    }
    
    const term = search.value.toLowerCase();
    return props.options.filter(opt => {
        const label = getLabel(opt) || '';
        return label.toLowerCase().includes(term);
    });
});

// Get the value for an option
const getOptValue = (opt) => {
    return (typeof opt === 'string' || typeof opt === 'number') ? opt : opt[props.valueKey];
};

const selectOption = (opt) => {
    emit('update:modelValue', getOptValue(opt));
    search.value = getLabel(opt);
    isOpen.value = false;
};

// Handle clicks outside to close the dropdown
const handleClickOutside = (event) => {
    if (wrapperRef.value && !wrapperRef.value.contains(event.target)) {
        isOpen.value = false;
        
        if (selectedOption.value && search.value === getLabel(selectedOption.value)) {
            // Already selected and text matches, do nothing
            return;
        }

        // Auto-select exact match if they typed it perfectly but didn't click
        const exactMatch = props.options.find(opt => getLabel(opt).toLowerCase() === search.value.toLowerCase());
        if (exactMatch) {
            selectOption(exactMatch);
            return;
        }

        // If no exact match, revert to previous selection if exists, else clear
        if (selectedOption.value) {
            search.value = getLabel(selectedOption.value);
        } else {
            search.value = '';
            emit('update:modelValue', '');
        }
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div class="relative w-full" ref="wrapperRef">
        <input 
            type="text" 
            v-model="search"
            @focus="isOpen = true"
            @click="isOpen = true; search = ''" 
            :placeholder="placeholder"
            class="input-field w-full text-xs font-bold bg-brand-black cursor-pointer pr-10"
        />
        <!-- Arrow Icon -->
        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-white/40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </div>
        
        <!-- Dropdown Menu -->
        <div v-show="isOpen" class="absolute z-50 w-full mt-1 bg-[#1A1A1A] border border-white/10 rounded-xl shadow-2xl max-h-60 overflow-y-auto overflow-x-hidden">
            <div v-if="filteredOptions.length === 0" class="p-3 text-xs text-white/50 text-center italic">
                No se encontraron coincidencias
            </div>
            <ul v-else class="py-1">
                <li 
                    v-for="opt in filteredOptions" 
                    :key="getOptValue(opt)"
                    @click="selectOption(opt)"
                    class="px-4 py-2 text-xs font-bold hover:bg-brand-red hover:text-white cursor-pointer transition-colors"
                    :class="getOptValue(opt) === modelValue ? 'bg-white/10 text-brand-red' : 'text-white/80'"
                >
                    {{ getLabel(opt) }}
                </li>
            </ul>
        </div>
    </div>
</template>
