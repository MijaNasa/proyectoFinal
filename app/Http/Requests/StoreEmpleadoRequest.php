<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmpleadoRequest extends FormRequest
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
            'email' => 'required|email|max:255|unique:users,email' . ($this->empleado ? ',' . $this->empleado->user_id : ''),
            'apellido' => 'nullable|string|max:255',
            'dni' => 'nullable|string|max:20|unique:users,dni' . ($this->empleado ? ',' . $this->empleado->user_id : ''),
            'telefono' => 'nullable|string|max:50',

            // Empleado Data
            'legajo' => 'required|string|max:20|unique:empleados,legajo' . ($this->empleado ? ',' . $this->empleado->id : ''),
            'sucursal_id' => 'required|exists:sucursales,id',
            'fecha_ingreso' => 'required|date',
            'fecha_egreso' => 'nullable|date|after_or_equal:fecha_ingreso',
        ];
    }
}
