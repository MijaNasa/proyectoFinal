<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa' => 'required|string|max:255',
            'nombre_contacto' => 'nullable|string|max:255',
            'telefono'        => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'direccion'       => 'nullable|string|max:255',
            'activo'          => 'boolean',
        ];
    }
}
