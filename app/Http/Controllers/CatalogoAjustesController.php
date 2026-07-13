<?php

namespace App\Http\Controllers;

use App\Models\Autor;
use App\Models\Categoria;
use App\Models\Editorial;
use App\Models\Idioma;
use Illuminate\Http\Request;

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
        $editoriales = Editorial::withCount('libroMasters')->orderBy('nombre')->get();
        $idiomas = Idioma::withCount('libroMasters')->orderBy('nombre')->get();

        return inertia('Catalogo/Ajustes', [
            'autores' => $autores,
            'categorias' => $categorias,
            'editoriales' => $editoriales,
            'idiomas' => $idiomas,
        ]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->checkAdmin($request);

        switch ($type) {
            case 'autores':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100',
                    'apellido' => 'required|string|max:100',
                ]);
                $model = Autor::findOrFail($id);
                $model->update($validated);
                break;

            case 'categorias':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100|unique:categorias,nombre,' . $id,
                ]);
                $model = Categoria::findOrFail($id);
                $model->update($validated);
                break;

            case 'editoriales':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:150',
                    'email' => 'required|email|max:150',
                ]);
                $model = Editorial::findOrFail($id);
                $model->update($validated);
                break;

            case 'idiomas':
                $validated = $request->validate([
                    'nombre' => 'required|string|max:100|unique:idiomas,nombre,' . $id,
                    'codigo' => 'required|string|max:10|unique:idiomas,codigo,' . $id,
                ]);
                $model = Idioma::findOrFail($id);
                $model->update($validated);
                break;

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        return redirect()->back()->with('message', 'Registro actualizado con éxito.');
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $this->checkAdmin($request);

        switch ($type) {
            case 'autores':
                $model = Autor::findOrFail($id);
                break;

            case 'categorias':
                $model = Categoria::findOrFail($id);
                break;

            case 'editoriales':
                $model = Editorial::findOrFail($id);
                break;

            case 'idiomas':
                $model = Idioma::findOrFail($id);
                break;

            default:
                abort(400, 'Tipo de ajuste no válido.');
        }

        // Backend double-check for safety: block deletion if it has associated works
        if ($model->libroMasters()->exists()) {
            return redirect()->back()->with('error', 'No se puede eliminar el registro porque tiene obras asociadas.');
        }

        $model->delete();

        return redirect()->back()->with('message', 'Registro eliminado con éxito.');
    }
}
