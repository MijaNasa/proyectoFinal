<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'sucursal_id'      => 'nullable|exists:sucursales,id',
            'tipo'             => 'required|in:online,presencial',
            'items'            => 'required|array|min:1',
            'items.*.libro_id' => 'required|exists:libros,id',
            'items.*.cantidad' => 'required|integer|min:1|max:9999',
            'medio_pago'       => 'required|in:Efectivo,Tarjeta,Transferencia,Cuenta Corriente',
            'requiere_envio'   => 'nullable|boolean',
            'destinatario_envio'=> 'nullable|required_if:tipo_envio,domicilio|string|max:255',
            'telefono_envio'   => 'nullable|required_if:tipo_envio,domicilio|string|max:50',
            'calle_numero_envio'=> 'nullable|required_if:tipo_envio,domicilio|string|max:255',
            'piso_depto_envio' => 'nullable|string|max:255',
            'origen'           => 'nullable|string|in:presencial,online,whatsapp',
            'es_excepcional'   => 'nullable|boolean',
            'motivo_pendiente' => 'nullable|string',
            'monto_sena'       => 'nullable|numeric|min:0',
            'acumular_pedido'  => 'nullable|boolean',
            'guardar_pendiente'=> 'nullable|boolean',
            'tipo_envio'       => 'nullable|string|in:retiro,domicilio,acumulacion',
            'usar_saldo_favor' => 'nullable|boolean',
            'metodo_pago_excedente' => 'nullable|string|in:Efectivo,Tarjeta,Transferencia',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $ids = collect($this->items ?? [])->pluck('libro_id');
            if ($ids->count() !== $ids->unique()->count()) {
                $v->errors()->add('items', 'No se puede agregar el mismo libro más de una vez. Ajustá la cantidad.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Debe seleccionar un cliente para pagar con Cuenta Corriente.',
        ];
    }
}
