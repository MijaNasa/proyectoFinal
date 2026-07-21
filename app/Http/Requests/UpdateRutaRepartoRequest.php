<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRutaRepartoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repartidor_id' => 'nullable|exists:empleados,id',
            'activa'        => 'boolean',
        ];
    }
}
