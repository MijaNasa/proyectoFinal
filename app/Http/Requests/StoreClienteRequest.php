<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
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
            // User Data
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->cliente ? ',' . $this->cliente->user_id : ''),
            'apellido' => 'nullable|string|max:255',
            'dni' => 'nullable|string|max:20|unique:users,dni' . ($this->cliente ? ',' . $this->cliente->user_id : ''),
            'telefono' => 'nullable|string|max:50',
            
            // Cliente Data
            'tipo_cliente_id' => 'required|exists:tipos_clientes,id',
            'estado_abono' => 'nullable|string|max:50',
            'saldo_actual' => 'nullable|numeric',
        ];
    }
}
