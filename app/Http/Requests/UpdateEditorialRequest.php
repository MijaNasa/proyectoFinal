<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEditorialRequest extends FormRequest
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
            'nombre' => 'required|string|max:150',
            'razon_social' => 'nullable|string|max:150',
            'tipo' => 'nullable|string|max:50',
            'pais_id' => 'nullable|exists:paises,id',
            'calle' => 'nullable|string|max:150',
            'numero' => 'nullable|string|max:20',
            'piso' => 'nullable|string|max:10',
            'departamento' => 'nullable|string|max:10',
            'codigo_postal' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
            'activo' => 'boolean'
        ];
    }
}
