<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAutorRequest extends FormRequest
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
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'biografia' => 'nullable|string',
            'pais_id' => 'nullable|exists:paises,id',
            'fecha_nacimiento' => 'nullable|date',
            'activo' => 'boolean'
        ];
    }
}
