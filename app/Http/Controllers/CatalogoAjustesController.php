<?php

namespace App\Http\Controllers;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Idioma;
use App\Models\Formato;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogoAjustesController extends Controller
{
    private function checkAdmin(Request $request)
    {
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'Acceso denegado: solo administradores pueden acceder.');
        }
    }

    private function removeAccents(string $value): string
    {
        $normalized = mb_strtolower(trim(Str::ascii($value)), 'UTF-8');
        // Normalizar también 'ñ' a 'n' para comparaciones fonéticas en español
        return str_replace(['ñ', 'Ñ'], 'n', $normalized);
    }

    private function calculateSimilarityPercent(string $a, string $b): float
    {
        $normA = $this->removeAccents($a);
        $normB = $this->removeAccents($b);

        if ($normA === $normB) {
            return 100.0;
        }

        if (empty($normA) || empty($normB)) {
            return 0.0;
        }

        if (str_contains($normA, $normB) || str_contains($normB, $normA)) {
            $minLen = min(strlen($normA), strlen($normB));
            $maxLen = max(strlen($normA), strlen($normB));
            return 80.0 + (20.0 * ($minLen / $maxLen));
        }

        similar_text($normA, $normB, $percent);

        $lev = levenshtein($normA, $normB);
        $maxLen = max(strlen($normA), strlen($normB));
        $levScore = max(0, (1 - ($lev / $maxLen)) * 100);

        if ($lev <= 1 && $maxLen >= 4) {
            return max($percent, 90.0);
        }
        if ($lev <= 2 && $maxLen >= 6) {
            return max($percent, 82.0);
        }

        return max($percent, $levScore);
    }

    private function findSimilarModel($collection, callable $getNameCallback, string $candidate, float $threshold = 85.0)
    {
        $bestMatch = null;
        $highestScore = 0.0;

        foreach ($collection as $item) {
            $name = $getNameCallback($item);
            $score = $this->calculateSimilarityPercent($candidate, $name);
            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = ['item' => $item, 'score' => round($score), 'name' => $name];
            }
        }

        if ($highestScore >= $threshold && $bestMatch) {
            return $bestMatch;
        }

        return null;
    }

    public function index(Request $request)
    {
        $this->checkAdmin($request);

        $autores = Autor::withCount('libroMasters')->orderBy('apellido')->get();
        $categorias = Categoria::withCount('libroMasters')->orderBy('nombre')->get();
        $idiomas = Idioma::withCount('libroMasters')->orderBy('nombre')->get();
        $formatos = Formato::withCount('libroMasters')->orderBy('nombre')->get();

        return inertia('Catalogo/Ajustes', [
            'autores'    => $autores,
            'categorias' => $categorias,
            'idiomas'    => $idiomas,
            'formatos'   => $formatos,
        ]);
    }

    public function store(Request $request, string $type)
    {
        $model = null;
        $forzar = $request->boolean('forzar');

        $messages = [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'nombre_empresa.required' => 'El nombre de empresa es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del correo no es válido.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'codigo.required' => 'El código es obligatorio.',
        ];

        switch ($type) {
            case 'autores':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                    'apellido' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);
                $normApellido = $this->removeAccents($validated['apellido']);

                $model = Autor::withTrashed()->get()->first(function ($a) use ($normNombre, $normApellido) {
                    return $this->removeAccents($a->nombre) === $normNombre && $this->removeAccents($a->apellido) === $normApellido;
                });

                if ($model && $model->trashed()) {
                    $model->restore();
                    $model->update($validated);
                } elseif ($model) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Este autor ya se encuentra registrado (registrado como: {$model->nombre} {$model->apellido})."
                    ]);
                } else {
                    if (!$forzar) {
                        $candidate = "{$validated['nombre']} {$validated['apellido']}";
                        $allAutores = Autor::all();
                        $similar = $this->findSimilarModel($allAutores, fn($a) => "{$a->nombre} {$a->apellido}", $candidate, 85.0);
                        if ($similar) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'nombre' => "Ya existe un autor muy similar registrado: '{$similar['name']}' ({$similar['score']}% de coincidencia). Si realmente deseas registrarlo, confirma la creación."
                            ]);
                        }
                    }
                    $model = Autor::create($validated);
                }
                break;

            case 'categorias':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);

                $model = Categoria::withTrashed()->get()->first(function ($c) use ($normNombre) {
                    return $this->removeAccents($c->nombre) === $normNombre;
                });

                if ($model && $model->trashed()) {
                    $model->restore();
                    $model->update($validated);
                } elseif ($model) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Esta categoría ya se encuentra registrada (registrada como: {$model->nombre})."
                    ]);
                } else {
                    if (!$forzar) {
                        $allCats = Categoria::all();
                        $similar = $this->findSimilarModel($allCats, fn($c) => $c->nombre, $validated['nombre'], 85.0);
                        if ($similar) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'nombre' => "Ya existe una categoría muy similar registrada: '{$similar['name']}' ({$similar['score']}% de coincidencia). Si realmente deseas registrarla, confirma la creación."
                            ]);
                        }
                    }
                    $model = Categoria::create($validated);
                }
                break;

            case 'proveedores':
                $validated = $request->validate([
                    'nombre_empresa' => 'required|string|max:150',
                    'telefono' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:150',
                ], $messages);

                $normEmpresa = $this->removeAccents($validated['nombre_empresa']);

                $model = \App\Models\Proveedor::withTrashed()->get()->first(function ($p) use ($normEmpresa) {
                    return $this->removeAccents($p->nombre_empresa) === $normEmpresa;
                });

                if ($model && $model->trashed()) {
                    $model->restore();
                    $model->update($validated);
                } elseif ($model) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre_empresa' => "Este proveedor ya se encuentra registrado (registrado como: {$model->nombre_empresa})."
                    ]);
                } else {
                    if (!$forzar) {
                        $allProv = \App\Models\Proveedor::all();
                        $similar = $this->findSimilarModel($allProv, fn($p) => $p->nombre_empresa, $validated['nombre_empresa'], 85.0);
                        if ($similar) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'nombre_empresa' => "Ya existe un proveedor muy similar registrado: '{$similar['name']}' ({$similar['score']}% de coincidencia). Si realmente deseas registrarlo, confirma la creación."
                            ]);
                        }
                    }
                    $model = \App\Models\Proveedor::create($validated);
                }
                break;

            case 'idiomas':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);

                $model = Idioma::withTrashed()->get()->first(function ($i) use ($normNombre) {
                    return $this->removeAccents($i->nombre) === $normNombre;
                });

                if ($model && $model->trashed()) {
                    $model->restore();
                    $model->update($validated);
                } elseif ($model) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Este idioma ya se encuentra registrado (registrado como: {$model->nombre})."
                    ]);
                } else {
                    if (!$forzar) {
                        $allIdiomas = Idioma::all();
                        $similar = $this->findSimilarModel($allIdiomas, fn($i) => $i->nombre, $validated['nombre'], 85.0);
                        if ($similar) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'nombre' => "Ya existe un idioma muy similar registrado: '{$similar['name']}' ({$similar['score']}% de coincidencia). Si realmente deseas registrarlo, confirma la creación."
                            ]);
                        }
                    }

                    $codigo = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->removeAccents($validated['nombre'])), 0, 3));
                    if (empty($codigo)) $codigo = 'IDI';
                    $originalCodigo = $codigo;
                    $counter = 1;

                    while (Idioma::withTrashed()->where('codigo', $codigo)->exists()) {
                        $codigo = substr($originalCodigo, 0, 2) . $counter;
                        $counter++;
                    }

                    $validated['codigo'] = $codigo;
                    $model = Idioma::create($validated);
                }
                break;

            case 'formatos':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);

                $model = Formato::withTrashed()->get()->first(function ($f) use ($normNombre) {
                    return $this->removeAccents($f->nombre) === $normNombre;
                });

                if ($model && $model->trashed()) {
                    $model->restore();
                    $model->update(['nombre' => trim($validated['nombre'])]);
                } elseif ($model) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Este formato ya se encuentra registrado (registrado como: {$model->nombre})."
                    ]);
                } else {
                    if (!$forzar) {
                        $allFormatos = Formato::all();
                        $similar = $this->findSimilarModel($allFormatos, fn($f) => $f->nombre, $validated['nombre'], 85.0);
                        if ($similar) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'nombre' => "Ya existe un formato muy similar registrado: '{$similar['name']}' ({$similar['score']}% de coincidencia). Si realmente deseas registrarlo, confirma la creación."
                            ]);
                        }
                    }

                    $model = Formato::create(['nombre' => trim($validated['nombre'])]);
                }
                break;

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('catalogo.ajustes.index')->with('message', 'Registro creado con éxito.');
        }

        if ($request->wantsJson() || $request->acceptsJson()) {
            return response()->json(['success' => true, 'model' => $model]);
        }

        return redirect()->route('catalogo.ajustes.index')->with('message', 'Registro creado con éxito.');
    }

    public function update(Request $request, string $type, $id)
    {
        $this->checkAdmin($request);

        $messages = [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del correo no es válido.',
            'codigo.required' => 'El código es obligatorio.',
        ];

        switch ($type) {
            case 'autores':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                    'apellido' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);
                $normApellido = $this->removeAccents($validated['apellido']);

                $existing = Autor::withTrashed()->where('id', '!=', $id)->get()->first(function ($a) use ($normNombre, $normApellido) {
                    return $this->removeAccents($a->nombre) === $normNombre && $this->removeAccents($a->apellido) === $normApellido;
                });

                if ($existing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Ya existe otro autor registrado con ese nombre sin tilde (registrado como: {$existing->nombre} {$existing->apellido})."
                    ]);
                }

                $model = Autor::findOrFail($id);
                $model->update($validated);
                break;

            case 'categorias':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);

                $existing = Categoria::withTrashed()->where('id', '!=', $id)->get()->first(function ($c) use ($normNombre) {
                    return $this->removeAccents($c->nombre) === $normNombre;
                });

                if ($existing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Ya existe otra categoría registrada con ese nombre sin tilde (registrada como: {$existing->nombre})."
                    ]);
                }

                $model = Categoria::findOrFail($id);
                $model->update($validated);
                break;

            case 'proveedores':
                $validated = $request->validate([
                    'nombre_empresa' => 'required|string|max:150',
                    'telefono' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:150',
                ], $messages);

                $normEmpresa = $this->removeAccents($validated['nombre_empresa']);

                $existing = \App\Models\Proveedor::withTrashed()->where('id', '!=', $id)->get()->first(function ($p) use ($normEmpresa) {
                    return $this->removeAccents($p->nombre_empresa) === $normEmpresa;
                });

                if ($existing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre_empresa' => "Ya existe otro proveedor registrado con ese nombre sin tilde (registrado como: {$existing->nombre_empresa})."
                    ]);
                }

                $model = \App\Models\Proveedor::findOrFail($id);
                $model->update($validated);
                break;

            case 'idiomas':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                    'codigo' => 'nullable|string|max:10',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);

                $existing = Idioma::withTrashed()->where('id', '!=', $id)->get()->first(function ($i) use ($normNombre) {
                    return $this->removeAccents($i->nombre) === $normNombre;
                });

                if ($existing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Ya existe otro idioma registrado con ese nombre sin tilde (registrado como: {$existing->nombre})."
                    ]);
                }

                $model = Idioma::findOrFail($id);
                $model->update(array_filter($validated, fn($val) => !is_null($val)));
                break;

            case 'formatos':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);

                $normNombre = $this->removeAccents($validated['nombre']);

                $existing = Formato::withTrashed()->where('id', '!=', $id)->get()->first(function ($f) use ($normNombre) {
                    return $this->removeAccents($f->nombre) === $normNombre;
                });

                if ($existing) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'nombre' => "Ya existe otro formato registrado con ese nombre sin tilde (registrado como: {$existing->nombre})."
                    ]);
                }

                $model = Formato::find($id);
                $newNombre = trim($validated['nombre']);

                if (!$model) {
                    $oldNombre = $request->input('old_nombre', $id);
                    $model = Formato::where('nombre', $oldNombre)->first();
                }

                if ($model) {
                    $oldNombre = $model->nombre;
                    $model->update(['nombre' => $newNombre]);
                } else {
                    $oldNombre = $request->input('old_nombre', $id);
                    $model = Formato::create(['nombre' => $newNombre]);
                }

                if ($oldNombre && $oldNombre !== $newNombre) {
                    \App\Models\LibroMaster::where('formato', $oldNombre)
                        ->update(['formato' => $newNombre]);
                }
                return redirect()->route('catalogo.ajustes.index')->with('message', 'Formato actualizado con éxito.');

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        return redirect()->route('catalogo.ajustes.index')->with('message', 'Registro actualizado con éxito.');
    }

    public function destroy(Request $request, string $type, $id)
    {
        $this->checkAdmin($request);

        switch ($type) {
            case 'autores':
                $model = Autor::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->route('catalogo.ajustes.index')->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'categorias':
                $model = Categoria::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->route('catalogo.ajustes.index')->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'proveedores':
                $model = \App\Models\Proveedor::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->route('catalogo.ajustes.index')->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'idiomas':
                $model = Idioma::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->route('catalogo.ajustes.index')->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'formatos':
                $model = Formato::find($id);
                $nombre = $model ? $model->nombre : urldecode((string)$id);
                $count = \App\Models\LibroMaster::where('formato', $nombre)->count();
                if ($count > 0) {
                    return redirect()->route('catalogo.ajustes.index')->with('error', 'No se puede eliminar el formato porque tiene ' . $count . ' obra(s) asociada(s).');
                }
                if ($model) {
                    $model->delete();
                }
                break;

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        return redirect()->route('catalogo.ajustes.index')->with('message', 'Registro eliminado con éxito.');
    }
}
