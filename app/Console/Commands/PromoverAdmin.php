<?php

namespace App\Console\Commands;

use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoverAdmin extends Command
{
    protected $signature   = 'admin:promover {email}';
    protected $description = 'Promueve un usuario existente a administrador del sistema';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No existe un usuario con email: {$email}");
            return self::FAILURE;
        }

        $cargo = Cargo::where('nombre', 'ADMIN')->first();

        if (!$cargo) {
            $this->error('No existe el cargo ADMIN en la base de datos. Ejecutá primero los seeders.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $cargo) {
            $empleado = $user->empleado;

            if (!$empleado) {
                $sucursal = Sucursal::where('es_deposito_central', true)->first()
                    ?? Sucursal::where('activo', true)->first();

                $empleado = $user->empleado()->create([
                    'legajo'      => 'ADM-' . $user->id,
                    'sucursal_id' => $sucursal->id,
                ]);
            }

            $yaEsAdmin = $empleado->cargos()
                ->where('nombre', 'ADMIN')
                ->wherePivotNull('fecha_hasta')
                ->exists();

            if (!$yaEsAdmin) {
                $empleado->cargos()->attach($cargo->id, [
                    'fecha_desde' => now(),
                    'fecha_hasta' => null,
                ]);
            }
        });

        $this->info("✓ {$user->name} ({$email}) ahora es ADMIN.");
        return self::SUCCESS;
    }
}
