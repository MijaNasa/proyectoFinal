<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
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
            'cliente_id' => 'required|exists:clientes,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'tipo' => 'required|in:online,presencial',
            'items' => 'required|array|min:1',
            'items.*.libro_id' => 'required|exists:libros,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio' => 'required|numeric|min:0',
            'medio_pago' => 'required|string', // E.g., 'Efectivo', 'Tarjeta', 'Cuenta Corriente'
        ];
    }
}
