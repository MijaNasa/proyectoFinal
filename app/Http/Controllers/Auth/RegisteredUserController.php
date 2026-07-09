<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.email' => 'Ingresá un correo electrónico válido.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Ya existe una cuenta registrada con ese correo electrónico.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $tipoCliente = TipoCliente::where('codigo', 'PART')->first();
        if ($tipoCliente) {
            $user->cliente()->create([
                'tipo_cliente_id' => $tipoCliente->id,
                'saldo_actual'    => 0,
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('catalogo.index', absolute: false));
    }
}
