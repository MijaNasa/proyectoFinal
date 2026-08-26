<?php

namespace App\Http\Controllers;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Idioma;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogoAjustesController extends Controller
{
    private function checkAdmin(Request $request)
    {
        if (!$request->user() || !$request->user()->esAdmin()) {
            abort(403, 'Acceso denegado: solo administradores pueden acceder.');
        }
    }

    public function index(Request $request)
    {
        $this->checkAdmin($request);

        $autores = Autor::withCount('libroMasters')->orderBy('apellido')->get();
        $categorias = Categoria::withCount('libroMasters')->orderBy('nombre')->get();
        $idiomas = Idioma::withCount('libroMasters')->orderBy('nombre')->get();

        $formatos = \App\Models\LibroMaster::whereNotNull('formato')
            ->where('formato', '!=', '')
            ->select('formato as nombre')
            ->selectRaw('count(*) as libro_masters_count')
            ->groupBy('formato')
            ->orderBy('formato')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->nombre,
                    'nombre' => $item->nombre,
                    'libro_masters_count' => (int)$item->libro_masters_count,
                ];
            })
            ->values();

        return inertia('Catalogo/Ajustes', [
            'autores'    => $autores,
            'categorias' => $categorias,
            'idiomas'    => $idiomas,
            'formatos'   => $formatos,
        ]);
    }

    public function store(Request $request, string $type)
    {
        // El cajero/vendedor también debería poder crear autores/editoriales desde la pantalla de libros,
        // así que relajamos la restricción de admin para el store si viene desde la creación de libros.
        // Si no, podríamos usar checkAdmin. Lo dejamos abierto para usuarios autenticados.

        $model = null;

        $messages = [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Este nombre ya se encuentra registrado.',
            'apellido.required' => 'El apellido es obligatorio.',
            'nombre_empresa.required' => 'El nombre de empresa es obligatorio.',
            'nombre_empresa.unique' => 'Este nombre de empresa ya se encuentra registrado.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del correo no es válido.',
            'email.unique' => 'Este email ya está en uso.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Este código ya está en uso.',
        ];

        switch ($type) {
            case 'autores':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                    'apellido' => 'required|string|max:100',
                ], $messages);
                $model = Autor::withTrashed()
                    ->where('nombre', $validated['nombre'])
                    ->where('apellido', $validated['apellido'])
                    ->first();
                
                if ($model && $model->trashed()) {
                    $model->restore();
                } elseif (!$model) {
                    $model = Autor::create($validated);
                }
                break;

            case 'categorias':
                $validated = $request->validate([
                    'nombre' => [
                        'required', 'string', 'max:100',
                        Rule::unique('categorias')->whereNull('deleted_at')
                    ],
                ], $messages);
                $model = Categoria::withTrashed()->where('nombre', $validated['nombre'])->first();
                
                if ($model && $model->trashed()) {
                    $model->restore();
                } elseif (!$model) {
                    $model = Categoria::create($validated);
                }
                break;

            case 'proveedores':
                $validated = $request->validate([
                    'nombre_empresa' => [
                        'required', 'string', 'max:150',
                        Rule::unique('proveedores')->whereNull('deleted_at')
                    ],
                    'telefono' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:150',
                ], $messages);
                $model = \App\Models\Proveedor::withTrashed()->where('nombre_empresa', $validated['nombre_empresa'])->first();
                
                if ($model && $model->trashed()) {
                    $model->restore();
                    $model->update($validated);
                } elseif (!$model) {
                    $model = \App\Models\Proveedor::create($validated);
                }
                break;

            case 'idiomas':
                $validated = $request->validate([
                    'nombre' => [
                        'required', 'string', 'max:100',
                        Rule::unique('idiomas')->whereNull('deleted_at')
                    ],
                ], $messages);
                
                $model = Idioma::withTrashed()->where('nombre', $validated['nombre'])->first();
                
                if ($model && $model->trashed()) {
                    $model->restore();
                } elseif (!$model) {
                    $codigo = substr(strtoupper($validated['nombre']), 0, 3);
                    $originalCodigo = $codigo;
                    $counter = 1;
                    
                    // Aseguramos que el código generado no choque ni siquiera con los eliminados
                    while (Idioma::withTrashed()->where('codigo', $codigo)->exists()) {
                        $codigo = substr($originalCodigo, 0, 2) . $counter;
                        $counter++;
                    }
                    
                    $validated['codigo'] = $codigo;
                    $model = Idioma::create($validated);
                }
                break;

            case 'formatos':
                $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);
                break;

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('message', 'Registro creado con éxito.');
        }

        if ($request->wantsJson() || $request->acceptsJson()) {
            return response()->json(['success' => true, 'model' => $model]);
        }

        return redirect()->back()->with('message', 'Registro creado con éxito.');
    }

    public function update(Request $request, string $type, $id)
    {
        $this->checkAdmin($request);

        $messages = [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Este nombre ya se encuentra registrado.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El formato del correo no es válido.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Este código ya está en uso.',
        ];

        switch ($type) {
            case 'autores':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                    'apellido' => 'required|string|max:100',
                ], $messages);
                $model = Autor::findOrFail($id);
                $model->update($validated);
                break;

            case 'categorias':
                $validated = $request->validate([
                    'nombre' => [
                        'required', 'string', 'max:100',
                        Rule::unique('categorias')->ignore($id)->whereNull('deleted_at')
                    ],
                ], $messages);
                $model = Categoria::findOrFail($id);
                $model->update($validated);
                break;

            case 'proveedores':
                $validated = $request->validate([
                    'nombre_empresa' => [
                        'required', 'string', 'max:150',
                        Rule::unique('proveedores')->ignore($id)->whereNull('deleted_at')
                    ],
                    'telefono' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:150',
                ], $messages);
                $model = \App\Models\Proveedor::findOrFail($id);
                $model->update($validated);
                break;

            case 'idiomas':
                $validated = $request->validate([
                    'nombre' => [
                        'required', 'string', 'max:100',
                        Rule::unique('idiomas')->ignore($id)->whereNull('deleted_at')
                    ],
                    'codigo' => [
                        'nullable', 'string', 'max:10',
                        Rule::unique('idiomas')->ignore($id)->whereNull('deleted_at')
                    ],
                ], $messages);
                $model = Idioma::findOrFail($id);
                $model->update(array_filter($validated, fn($val) => !is_null($val)));
                break;

            case 'formatos':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                ], $messages);
                $newNombre = trim($validated['nombre']);
                $oldNombre = $request->input('old_nombre', $id);
                if ($oldNombre && $oldNombre !== $newNombre) {
                    \App\Models\LibroMaster::where('formato', $oldNombre)
                        ->update(['formato' => $newNombre]);
                }
                return redirect()->back()->with('message', 'Formato actualizado con éxito.');

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        return redirect()->back()->with('message', 'Registro actualizado con éxito.');
    }

    public function destroy(Request $request, string $type, $id)
    {
        $this->checkAdmin($request);

        switch ($type) {
            case 'autores':
                $model = Autor::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->back()->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'categorias':
                $model = Categoria::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->back()->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'proveedores':
                $model = \App\Models\Proveedor::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->back()->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'idiomas':
                $model = Idioma::findOrFail($id);
                if ($model->libroMasters()->exists()) {
                    return redirect()->back()->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
                }
                $model->delete();
                break;

            case 'formatos':
                $nombre = urldecode((string)$id);
                $count = \App\Models\LibroMaster::where('formato', $nombre)->count();
                if ($count > 0) {
                    return redirect()->back()->with('error', 'No se puede eliminar el formato porque tiene ' . $count . ' obra(s) asociada(s).');
                }
                break;

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        return redirect()->back()->with('message', 'Registro eliminado con éxito.');
    }
}
