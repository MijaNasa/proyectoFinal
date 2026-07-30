<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Libro;

try {
    echo "Creating Test 1...\n";
    $l1 = Libro::create([
        'master_id' => 11,
        'numero_tomo' => 'Test1',
        'isbn' => null,
        'activo' => 1,
        'permite_preventa' => 0
    ]);
    echo "Test 1 ID: " . $l1->id . " | ISBN: " . var_export($l1->isbn, true) . "\n";

    echo "Creating Test 2...\n";
    $l2 = Libro::create([
        'master_id' => 11,
        'numero_tomo' => 'Test2',
        'isbn' => null,
        'activo' => 1,
        'permite_preventa' => 0
    ]);
    echo "Test 2 ID: " . $l2->id . " | ISBN: " . var_export($l2->isbn, true) . "\n";
    echo "SUCCESS!\n";
} catch (\Exception $e) {
    echo "EXCEPTION THROWN:\n";
    echo $e->getMessage() . "\n";
}
