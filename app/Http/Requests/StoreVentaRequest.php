<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id'       => ['nullable', 'exists:clientes,id', Rule::requiredIf($this->medio_pago === 'Cuenta Corriente')],
            'sucursal_id'      => 'required|exists:sucursales,id',
            'tipo'             => 'required|in:online,presencial',
            'items'            => 'required|array|min:1',
            'items.*.libro_id' => 'required|exists:libros,id',
            'items.*.cantidad' => 'required|integer|min:1|max:9999',
            'medio_pago'       => 'required|in:Efectivo,Tarjeta,Transferencia,Cuenta Corriente',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Debe seleccionar un cliente para pagar con Cuenta Corriente.',
        ];
    }
}
