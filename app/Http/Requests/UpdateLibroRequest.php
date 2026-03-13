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
            'editorial_id' => 'required|exists:editoriales,id',
            'idioma_id' => 'required|exists:idiomas,id',
            'año_edicion' => 'nullable|integer|min:1000|max:2100',
            'cantidad_paginas' => 'nullable|integer|min:1',
            'synopsis' => 'nullable|string',
            'activo' => 'boolean',
            'precio_compra' => 'nullable|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
        ];
    }
}
