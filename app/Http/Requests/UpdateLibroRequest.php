<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLibroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isbn' => 'nullable|string|max:20|unique:libros,isbn,' . $this->libro->id,
            'master_id' => 'required|exists:libro_masters,id',
            'numero_tomo' => 'nullable|integer|min:0',
            'año_edicion' => 'nullable|integer|min:1000|max:2100',
            'cantidad_paginas' => 'nullable|integer|min:1',
            'activo' => 'boolean',
            'permite_preventa' => 'boolean',
            'precio_compra' => 'nullable|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
        ];
    }
}
