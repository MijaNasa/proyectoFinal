<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLibroMasterRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'portada' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
            'autor_id' => ['required'],
            'categoria_id' => ['required'],
            'proveedor_id' => ['required'],
            'idioma_id' => ['required'],
            'formato' => ['required', 'string', 'max:255'],
            'synopsis' => ['nullable', 'string'],
            'activo' => ['boolean']
        ];
    }
}
