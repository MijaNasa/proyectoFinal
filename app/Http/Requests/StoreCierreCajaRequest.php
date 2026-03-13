<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCierreCajaRequest extends FormRequest
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
            'sucursal_id' => 'required|exists:sucursales,id',
            'fecha' => 'required|date',
            'monto_esperado' => 'required|numeric',
            'monto_real' => 'required|numeric',
            'observaciones' => 'nullable|string',
        ];
    }
}
