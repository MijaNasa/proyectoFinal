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
        if (empty($data['autor_id'])) {
            $data['autor_id'] = null;
        } else if (!is_numeric($data['autor_id'])) {
            $autorStr = trim((string)$data['autor_id']);
            if ($autorStr !== '') {
                $parts = explode(' ', $autorStr);
                $apellido = count($parts) > 1 ? array_pop($parts) : null;
                $nombre = implode(' ', $parts);
                if (empty($nombre)) {
                    $nombre = $apellido;
                    $apellido = null;
                }

                if (!empty($nombre)) {
                    $autor = \App\Models\Autor::where('nombre', $nombre)
                        ->where('apellido', $apellido)
                        ->first();

                    if (!$autor) {
                        $autor = \App\Models\Autor::create(['nombre' => $nombre, 'apellido' => $apellido]);
                    }
                    $data['autor_id'] = $autor->id;
                } else {
                    $data['autor_id'] = null;
                }
            } else {
                $data['autor_id'] = null;
            }
        }

        if (empty($data['categoria_id'])) {
            $data['categoria_id'] = null;
        } else if (!is_numeric($data['categoria_id'])) {
            $catStr = trim((string)$data['categoria_id']);
            if ($catStr !== '') {
                $categoria = \App\Models\Categoria::where('nombre', $catStr)->first();
                if (!$categoria) {
                    $categoria = \App\Models\Categoria::create(['nombre' => $catStr]);
                }
                $data['categoria_id'] = $categoria->id;
            } else {
                $data['categoria_id'] = null;
            }
        }

        if (empty($data['proveedor_id'])) {
            $data['proveedor_id'] = null;
        } else if (!is_numeric($data['proveedor_id'])) {
            $provStr = trim((string)$data['proveedor_id']);
            if ($provStr !== '') {
                $proveedor = \App\Models\Proveedor::where('nombre_empresa', $provStr)->first();
                if (!$proveedor) {
                    $proveedor = \App\Models\Proveedor::create(['nombre_empresa' => $provStr]);
                }
                $data['proveedor_id'] = $proveedor->id;
            } else {
                $data['proveedor_id'] = null;
            }
        }

        if (empty($data['idioma_id'])) {
            $data['idioma_id'] = null;
        } else if (!is_numeric($data['idioma_id'])) {
            $idStr = trim((string)$data['idioma_id']);
            if ($idStr !== '') {
                $idioma = \App\Models\Idioma::where('nombre', $idStr)->first();
                if (!$idioma) {
                    $codigo = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $idStr), 0, 3));
                    if (\App\Models\Idioma::where('codigo', $codigo)->exists()) {
                        $codigo = substr(md5(uniqid()), 0, 5);
                    }
                    $idioma = \App\Models\Idioma::create(['nombre' => $idStr, 'codigo' => $codigo]);
                }
                $data['idioma_id'] = $idioma->id;
            } else {
                $data['idioma_id'] = null;
            }
        }

        if (empty($data['formato'])) {
            $data['formato'] = null;
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
            $like = '%' . mb_strtolower($request->search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(titulo) LIKE ?', [$like])
                  ->orWhereHas('autor', fn($sq) => $sq->whereRaw('LOWER(nombre) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(apellido) LIKE ?', [$like]));
            });
        }

        $librosMaster = $query->latest()->paginate(20)->withQueryString();

        return inertia('LibroMasters/Index', [
            'librosMaster' => $librosMaster,
            'autores' => \App\Models\Autor::orderBy('apellido')->get(['id', 'nombre', 'apellido']),
            'categorias' => \App\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => \App\Models\Proveedor::where('activo', true)->orderBy('nombre_empresa')->get(['id', 'nombre_empresa']),
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
            ->where('formato', $data['formato'] ?? null)
            ->where('idioma_id', $data['idioma_id'] ?? null)
            ->where('proveedor_id', $data['proveedor_id'] ?? null)
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'titulo' => 'Ya existe un producto con exactamente el mismo título, formato, idioma y proveedor.'
            ]);
        }

        if ($request->hasFile('portada')) {
            $data['portada'] = $request->file('portada')->store('portadas', 'public');
        }

        LibroMaster::create($data);

        return redirect()->route('libros.index')
            ->with('message', 'Producto máster creado con éxito');
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
            ->where('formato', $data['formato'] ?? null)
            ->where('idioma_id', $data['idioma_id'] ?? null)
            ->where('proveedor_id', $data['proveedor_id'] ?? null)
            ->where('id', '!=', $libroMaster->id)
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'titulo' => 'Ya existe otro producto con exactamente el mismo título, formato, idioma y proveedor.'
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
