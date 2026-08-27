<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('formatos')) {
            Schema::create('formatos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Sembrar formatos por defecto y existentes en libro_masters
        $defaults = ['Tankobon', 'B6', 'A5', 'Kanzenban', 'Omnibus', 'Pocket', 'Novela Ligera', 'Otro'];
        $existing = DB::table('libro_masters')
            ->whereNotNull('formato')
            ->where('formato', '!=', '')
            ->pluck('formato')
            ->toArray();

        $todos = array_unique(array_merge($defaults, $existing));
        $now = now();
        foreach ($todos as $fmt) {
            $nombre = trim($fmt);
            if ($nombre !== '' && !DB::table('formatos')->where('nombre', $nombre)->exists()) {
                DB::table('formatos')->insert([
                    'nombre' => $nombre,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('formatos');
    }
};
