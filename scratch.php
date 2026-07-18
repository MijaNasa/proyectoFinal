<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
auth()->login(App\Models\User::first());
$ctrl = new App\Http\Controllers\LogisticaController();
$response = $ctrl->index(request());
$props = (fn() => $this->props)->call($response);
echo json_encode($props['trasladosAEnviar'], JSON_PRETTY_PRINT);
