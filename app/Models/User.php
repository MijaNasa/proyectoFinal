<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'apellido',
        'dni',
        'telefono',
        'calle',
        'numero',
        'piso',
        'departamento',
        'codigo_postal',
        'ciudad_id',
        'fecha_nacimiento',
        'activo',
    ];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class);
    }

    public function empleado()
    {
        return $this->hasOne(Empleado::class);
    }

    // Devuelve true si el usuario tiene el cargo ADMIN activo
    public function esAdmin(): bool
    {
        if (!$this->relationLoaded('empleado')) {
            $this->load('empleado.cargos');
        }
        if (!$this->empleado) return false;

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->where('nombre', 'ADMIN')
            ->exists();
    }

    // Devuelve true si tiene cargo GERENTE activo
    public function esGerente(): bool
    {
        if (!$this->empleado) return false;

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->where('nombre', 'GERENTE')
            ->exists();
    }

    // Verifica si el usuario tiene un permiso específico (por código)
    public function hasPermiso(string $codigo): bool
    {
        if ($this->esAdmin()) return true;
        if (!$this->empleado) return false;

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->whereHas('permisos', fn($q) => $q->where('codigo', $codigo)->where('activo', true))
            ->exists();
    }

    // Retorna array con los códigos de permisos activos del usuario
    public function getPermisosActivos(): array
    {
        if ($this->esAdmin()) {
            return Permiso::where('activo', true)->pluck('codigo')->toArray();
        }

        if (!$this->empleado) return [];

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->with(['permisos' => fn($q) => $q->where('activo', true)])
            ->get()
            ->flatMap(fn($cargo) => $cargo->permisos->pluck('codigo'))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
