<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipos_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('tipos_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->string('modulo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tipo_cliente_id')->constrained('tipos_clientes');
            $table->decimal('saldo_actual', 12, 2)->default(0);
            $table->json('preferencias')->nullable();
            $table->string('estado_abono')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('legajo')->unique();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_egreso')->nullable();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empleados_cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->foreignId('cargo_id')->constrained('cargos')->onDelete('cascade');
            $table->date('fecha_desde')->useCurrent();
            $table->date('fecha_hasta')->nullable();
            $table->timestamps();
        });

        Schema::create('cargos_permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos')->onDelete('cascade');
            $table->foreignId('permiso_id')->constrained('permisos')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargos_permisos');
        Schema::dropIfExists('empleados_cargos');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('tipos_clientes');
        Schema::dropIfExists('tipos_documentos');
    }
};
