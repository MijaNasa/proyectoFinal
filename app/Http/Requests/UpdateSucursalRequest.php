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
            'ciudad_nombre' => 'required|string|max:255',
            'calle' => 'required|string|max:255',
            'numero' => 'nullable|string|max:20',
            'piso' => 'nullable|string|max:20',
            'departamento' => 'nullable|string|max:20',
            'codigo_postal' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'activo' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la sucursal es obligatorio.',
            'ciudad_nombre.required' => 'La ciudad es obligatoria.',
            'calle.required' => 'La calle es obligatoria.',
            'email.email' => 'El formato del correo electrónico no es válido.',
        ];
    }
}
