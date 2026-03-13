<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
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
            'libro_id' => 'required|exists:libros,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'cantidad_disponible' => 'required|integer|min:0',
            'cantidad_reservada' => 'nullable|integer|min:0',
            'ubicacion_text' => 'nullable|string|max:100',
            'activo' => 'boolean'
        ];
    }
}
