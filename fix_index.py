import re

with open('resources/js/Pages/Ventas/Index.vue', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update estadoOpciones
new_estado_opciones = """const estadoOpciones = [
    { value: 'pendiente_pago',     label: 'Pendiente de pago',  tipos: ['online', 'presencial'] },
    { value: 'esperando_traslado', label: 'Esperando traslado', tipos: ['online'] },
    { value: 'en_preparacion',     label: 'En preparación',     tipos: ['online'] },
    { value: 'listo_para_retiro',  label: 'Listo para retirar', tipos: ['online'] },
    { value: 'acumulado',          label: 'Acumulado',          tipos: ['online'] },
    { value: 'enviado',            label: 'Enviado',            tipos: ['online'] },
    { value: 'finalizado',         label: 'Finalizado',         tipos: ['online', 'presencial'] },
    { value: 'cancelado',          label: 'Cancelado',          tipos: ['online', 'presencial'] },
];"""

content = re.sub(r'const estadoOpciones = \[.*?\];', new_estado_opciones, content, flags=re.DOTALL)

# 2. Remove estadoEnvioOpciones
content = re.sub(r'const estadoEnvioOpciones = \[.*?\];\n', '', content, flags=re.DOTALL)

# 3. Update estadoOpcionesFiltradas to lock options based on tipo_envio
new_filtradas = """const estadoOpcionesFiltradas = computed(() => {
    if (!selectedVenta.value) return estadoOpciones;
    return estadoOpciones.filter(e => {
        if (!e.tipos.includes(selectedVenta.value.tipo)) return false;
        
        // Reglas logísticas
        if (selectedVenta.value.tipo_envio === 'retiro') {
            if (['en_preparacion', 'acumulado', 'enviado'].includes(e.value)) return false;
        } else if (selectedVenta.value.tipo_envio === 'domicilio') {
            if (['listo_para_retiro', 'acumulado'].includes(e.value)) return false;
        } else if (selectedVenta.value.tipo_envio === 'acumulacion') {
            if (['listo_para_retiro', 'en_preparacion', 'enviado'].includes(e.value)) return false;
        }
        return true;
    });
});"""

content = re.sub(r'const estadoOpcionesFiltradas = computed.*?\}\);', new_filtradas, content, flags=re.DOTALL)

# 4. Update estadoColores
new_colores = """const estadoColores = {
    pendiente_pago:     'bg-yellow-500/20 text-yellow-400',
    esperando_traslado: 'bg-purple-500/20 text-purple-400',
    en_preparacion:     'bg-blue-500/20 text-blue-400',
    listo_para_retiro:  'bg-emerald-500/20 text-emerald-400',
    acumulado:          'bg-orange-500/20 text-orange-400',
    enviado:            'bg-indigo-500/20 text-indigo-400',
    finalizado:         'bg-green-500/20 text-green-400',
    cancelado:          'bg-red-500/20 text-red-400',
};"""

content = re.sub(r'const estadoColores = \{.*?\};', new_colores, content, flags=re.DOTALL)

# 5. Remove estadoEnvioColores
content = re.sub(r'const estadoEnvioColores = \{.*?\};\n', '', content, flags=re.DOTALL)

# 6. useForm update
content = content.replace("const estadoForm = useForm({ estado: '', estado_envio: '' });", "const estadoForm = useForm({ estado: '' });")

# 7. viewVenta function
content = content.replace("estadoForm.estado_envio = venta.estado_envio;", "")

# 8. cambiarEstado
content = content.replace("estadoForm.estado === selectedVenta.value.estado && estadoForm.estado_envio === selectedVenta.value.estado_envio", "estadoForm.estado === selectedVenta.value.estado")

# 9. HTML badges
# The regex removes the Envío badge
content = re.sub(
    r'<span class="text-\[8px\] font-black uppercase tracking-widest px-2 py-1 rounded" :class="estadoEnvioColores\[venta\.estado_envio\].*?</span>',
    '',
    content,
    flags=re.DOTALL
)

# Also remove "Pago: " prefix from the main badge since there is only one now
content = content.replace("Pago: {{ estadoOpciones.find(e => e.value === venta.estado)?.label || venta.estado }}", "{{ estadoOpciones.find(e => e.value === venta.estado)?.label || venta.estado }}")

# 10. Edit Modal inputs
# Remove the estado logístico select div
content = re.sub(
    r'<div class="flex-1 w-full">\s*<label class="text-\[8px\] font-black uppercase tracking-widest text-white/40 mb-1 block">Estado Logístico</label>\s*<select v-model="estadoForm\.estado_envio".*?</select>\s*</div>',
    '',
    content,
    flags=re.DOTALL
)

# 11. Modal button
content = content.replace("estadoForm.estado === selectedVenta.estado && estadoForm.estado_envio === selectedVenta.estado_envio", "estadoForm.estado === selectedVenta.estado")

with open('resources/js/Pages/Ventas/Index.vue', 'w', encoding='utf-8') as f:
    f.write(content)
