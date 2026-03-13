<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSucursalRequest extends FormRequest
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
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:10|unique:sucursales,codigo,' . $this->sucursal->id,
            'ciudad_id' => 'required|exists:ciudades,id',
            'calle' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'piso' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:20',
            'codigo_postal' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'es_deposito_central' => 'boolean',
            'activo' => 'boolean'
        ];
    }
}
