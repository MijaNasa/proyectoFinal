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
            'nombre'        => 'required|string|max:255',
            'fecha'         => 'required|date',
            'repartidor_id' => 'required|exists:empleados,id',
        ];
    }
}
