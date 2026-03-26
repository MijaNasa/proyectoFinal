<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSerieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'       => 'required|string|max:255',
            'descripcion'  => 'nullable|string',
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'activo'       => 'boolean',
        ];
    }
}
