<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRutaRepartoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repartidor_id' => 'nullable|exists:empleados,id',
        ];
    }

    public function messages(): array
    {
        return [
            'repartidor_id.required' => 'Debes seleccionar un repartidor.',
        ];
    }
}
