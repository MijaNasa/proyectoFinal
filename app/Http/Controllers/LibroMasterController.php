<?php

namespace App\Http\Controllers;

use App\Models\LibroMaster;
use App\Http\Requests\StoreLibroMasterRequest;
use App\Http\Requests\UpdateLibroMasterRequest;
use Illuminate\Http\Request;

class LibroMasterController extends Controller
{
    private function processRelations(&$data)
    {
        if (!is_numeric($data['autor_id'])) {
            $autorStr = trim($data['autor_id']);
            $parts = explode(' ', $autorStr);
            $apellido = count($parts) > 1 ? array_pop($parts) : null;
            $nombre = implode(' ', $parts);

            $autor = \App\Models\Autor::where('nombre', $nombre)
                ->where('apellido', $apellido)
                ->first();

            if (!$autor) {
                $autor = \App\Models\Autor::create(['nombre' => $nombre, 'apellido' => $apellido]);
            }
            $data['autor_id'] = $autor->id;
        }

        if (!is_numeric($data['categoria_id'])) {
            $catStr = trim($data['categoria_id']);
            $categoria = \App\Models\Categoria::where('nombre', $catStr)->first();
            if (!$categoria) {
                $categoria = \App\Models\Categoria::create(['nombre' => $catStr]);
            }
            $data['categoria_id'] = $categoria->id;
        }

        if (!empty($data['proveedor_id']) && !is_numeric($data['proveedor_id'])) {
            $provStr = trim($data['proveedor_id']);
            $proveedor = \App\Models\Proveedor::where('nombre_empresa', $provStr)->first();
            if (!$proveedor) {
                $proveedor = \App\Models\Proveedor::create(['nombre_empresa' => $provStr]);
            }
            $data['proveedor_id'] = $proveedor->id;
        }

        if (!empty($data['idioma_id']) && !is_numeric($data['idioma_id'])) {
            $idStr = trim($data['idioma_id']);
            $idioma = \App\Models\Idioma::where('nombre', $idStr)->first();
            if (!$idioma) {
                // Generar un código dummy para el idioma (ej: si es "Inglés", el código podría ser "ING")
                $codigo = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $idStr), 0, 3));
                // Asegurarse de que el código sea único o intentar un fallback
                if (\App\Models\Idioma::where('codigo', $codigo)->exists()) {
                    $codigo = substr(md5(uniqid()), 0, 5);
                }
                $idioma = \App\Models\Idioma::create(['nombre' => $idStr, 'codigo' => $codigo]);
            }
            $data['idioma_id'] = $idioma->id;
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LibroMaster::query()
            ->with(['autor:id,nombre,apellido', 'categoria:id,nombre', 'proveedor:id,nombre_empresa', 'idioma:id,nombre'])
            ->select(['id', 'titulo', 'portada', 'autor_id', 'categoria_id', 'proveedor_id', 'idioma_id', 'formato', 'synopsis', 'activo']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhereHas('autor', fn($sq) => $sq->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%"));
            });
        }

        $librosMaster = $query->latest()->paginate(20)->withQueryString();

        return inertia('LibroMasters/Index', [
            'librosMaster' => $librosMaster,
            'autores' => \App\Models\Autor::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'categorias' => \App\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => \App\Models\Proveedor::orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
            'idiomas' => \App\Models\Idioma::orderBy('nombre')->get(['id', 'nombre']),
            'filters' => $request->only(['search'])
        ]);
    }

    public function store(StoreLibroMasterRequest $request)
    {
        $data = $request->validated();
        $this->processRelations($data);

        // Validar unicidad compuesta (Título + Formato + Idioma + Proveedor)
        $exists = LibroMaster::where('titulo', $data['titulo'])
            ->where('formato', $data['formato'])
            ->where('idioma_id', $data['idioma_id'])
            ->where('proveedor_id', $data['proveedor_id'])
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'titulo' => 'Ya existe una obra con exactamente el mismo título, formato, idioma y proveedor.'
            ]);
        }

        if ($request->hasFile('portada')) {
            $data['portada'] = $request->file('portada')->store('portadas', 'public');
        }

        LibroMaster::create($data);

        return redirect()->route('libros.index')
            ->with('message', 'Obra maestra (título) creada con éxito');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLibroMasterRequest $request, LibroMaster $libroMaster)
    {
        $data = $request->validated();
        $this->processRelations($data);

        // Validar unicidad compuesta (Título + Formato + Idioma + Proveedor) excluyendo el actual
        $exists = LibroMaster::where('titulo', $data['titulo'])
            ->where('formato', $data['formato'])
            ->where('idioma_id', $data['idioma_id'])
            ->where('proveedor_id', $data['proveedor_id'])
            ->where('id', '!=', $libroMaster->id)
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'titulo' => 'Ya existe otra obra con exactamente el mismo título, formato, idioma y proveedor.'
            ]);
        }

        if ($request->hasFile('portada')) {
            // Eliminar imagen anterior si existe
            if ($libroMaster->portada && \Illuminate\Support\Facades\Storage::disk('public')->exists($libroMaster->portada)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($libroMaster->portada);
            }
            $data['portada'] = $request->file('portada')->store('portadas', 'public');
        }

        $libroMaster->update($data);

        return redirect()->route('libros.index')
            ->with('message', 'Obra maestra actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LibroMaster $libroMaster)
    {
        $libroMaster->delete();

        return redirect()->route('libros.index')
            ->with('message', 'Obra maestra eliminada con éxito');
    }
}
