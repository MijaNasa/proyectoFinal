<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:100|unique:cargos,nombre,' . $this->route('cargo')->id,
            'descripcion' => 'nullable|string|max:500',
            'activo'      => 'boolean',
            'permiso_ids' => 'nullable|array',
            'permiso_ids.*' => 'exists:permisos,id',
        ];
    }
}
