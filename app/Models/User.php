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

    public function esAdmin(): bool
    {
        if (!$this->empleado) return false;

        if ($this->empleado->relationLoaded('cargos')) {
            return $this->empleado->cargos->contains('nombre', 'ADMIN');
        }

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->where('nombre', 'ADMIN')
            ->exists();
    }

    public function esGerente(): bool
    {
        if (!$this->empleado) return false;

        if ($this->empleado->relationLoaded('cargos')) {
            return $this->empleado->cargos->contains('nombre', 'GERENTE');
        }

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->where('nombre', 'GERENTE')
            ->exists();
    }

    public function hasPermiso(string $codigo): bool
    {
        if ($this->esAdmin()) return true;
        if (!$this->empleado) return false;

        if ($this->empleado->relationLoaded('cargos')) {
            return $this->empleado->cargos->contains(
                fn($cargo) => $cargo->relationLoaded('permisos')
                    && $cargo->permisos->contains('codigo', $codigo)
            );
        }

        return $this->empleado->cargos()
            ->wherePivotNull('fecha_hasta')
            ->whereHas('permisos', fn($q) => $q->where('codigo', $codigo)->where('activo', true))
            ->exists();
    }

    public function getPermisosActivos(): array
    {
        if ($this->esAdmin()) {
            return Permiso::where('activo', true)->pluck('codigo')->toArray();
        }

        if (!$this->empleado) return [];

        if ($this->empleado->relationLoaded('cargos')) {
            return $this->empleado->cargos
                ->flatMap(fn($cargo) => $cargo->relationLoaded('permisos')
                    ? $cargo->permisos->pluck('codigo')
                    : collect())
                ->unique()
                ->values()
                ->toArray();
        }

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
