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
            'name'     => 'required|string|max:255',
            'apellido' => 'nullable|string|max:255',
            'dni'      => 'required|string|max:50',
            'telefono' => 'nullable|string|max:50',
            'email'    => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'name.required'      => 'El nombre es obligatorio.',
            'dni.required'       => 'El DNI es obligatorio.',
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.email'        => 'Ingresá un correo electrónico válido.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas ingresadas no coinciden.',
        ]);

        $existingUser = User::where('dni', $request->dni)->first();
        if ($existingUser) {
            // Verificar si es un usuario fantasma/invitado (su contraseña coincide con su DNI o no está definida)
            $isGhostUser = !$existingUser->password || Hash::check($existingUser->dni, $existingUser->password);
            if ($isGhostUser) {
                // Actualizar usuario existente con su contraseña real elegida y vincularlo
                $existingUser->update([
                    'name'     => $request->name,
                    'apellido' => $request->apellido ?? $existingUser->apellido,
                    'telefono' => $request->telefono ?? $existingUser->telefono,
                    'email'    => $request->email,
                    'password' => Hash::make($request->password),
                ]);
                $user = $existingUser;
            } else {
                return back()->withErrors(['dni' => 'Este DNI ya cuenta con un usuario registrado. Por favor iniciá sesión.']);
            }
        } else {
            if (User::where('email', $request->email)->exists()) {
                return back()->withErrors(['email' => 'Este correo electrónico ya se encuentra registrado. Por favor iniciá sesión.']);
            }

            $user = User::create([
                'name'     => $request->name,
                'apellido' => $request->apellido,
                'dni'      => $request->dni,
                'telefono' => $request->telefono,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $tipoCliente = TipoCliente::where('codigo', 'PART')->first();
            if ($tipoCliente) {
                $user->cliente()->create([
                    'tipo_cliente_id' => $tipoCliente->id,
                    'saldo_actual'    => 0,
                ]);
            }
        }

        event(new Registered($user));

        Auth::login($user);

        $request->session()->forget('url.intended');

        return redirect('/catalogo');
    }
}
