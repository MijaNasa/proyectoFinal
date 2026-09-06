<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;

class StoreLibroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'isbn' => filled($this->isbn) ? trim($this->isbn) : null,
            'numero_tomo' => filled($this->numero_tomo) ? $this->numero_tomo : null,
            'año_edicion' => filled($this->año_edicion) ? $this->año_edicion : null,
            'cantidad_paginas' => filled($this->cantidad_paginas) ? $this->cantidad_paginas : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'isbn' => 'nullable|string|max:20|unique:libros,isbn' . ($this->libro ? ',' . $this->libro->id : ''),
            'master_id' => 'required|exists:libro_masters,id',
            'numero_tomo' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('libros', 'numero_tomo')->where(fn ($query) => $query->where('master_id', $this->master_id))
            ],
            'año_edicion' => 'nullable|integer|min:1000|max:2100',
            'cantidad_paginas' => 'nullable|integer|min:1',
            'activo' => 'sometimes|boolean',
            'permite_preventa' => 'boolean',
            'portada'          => $this->hasFile('portada') ? 'image|mimes:jpg,jpeg,png,gif,webp|max:5120' : 'nullable',
            'precio_compra'   => 'nullable|numeric|min:0',
            'precio_venta'    => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_tomo.unique' => 'Esta variante o número de tomo ya se encuentra cargada para esta obra.',
        ];
    }
}
