<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Mapa al login del sigtram antiguo: {@see \App\Http\Controllers\AuthController}
 * Tabla: esquema.sistema + p_lgin (columnas de_lgin, de_pass con SHA1).
 *
 * @property int $cdgo_lgin
 * @property string $de_lgin
 * @property string $de_pass
 * @property string $in_esta
 * @property int $cdgo_prfle
 * @property int $cdgo_usrios
 * @property int|null $cdgo_cmpnia
 * @property-read \App\Models\PUsrrio|null $usrio
 */
#[Fillable([
    'cdgo_lgin',
    'de_lgin',
    'de_pass',
    'in_esta',
    'cdgo_prfle',
    'cdgo_usrios',
])]
#[Hidden(['de_pass'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $primaryKey = 'cdgo_lgin';

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'p_lgin';
    }

    public function getAuthIdentifierName(): string
    {
        return 'cdgo_lgin';
    }

    public function getAuthPassword(): string
    {
        $v = $this->getAttributes()['de_pass'] ?? null;

        return is_scalar($v) ? (string) $v : '';
    }

    public function usrio(): BelongsTo
    {
        return $this->belongsTo(PUsrrio::class, 'cdgo_usrios', 'cdgo_usrios');
    }

    /**
     * Nombre para mostrar (equiv. a no_crto en el JOIN del PHP antiguo).
     */
    public function nombreMostrar(): string
    {
        $this->loadMissing('usrio');

        $usrio = $this->usrio;
        $n = $usrio instanceof PUsrrio ? $usrio->no_crto : null;
        if (is_string($n) && $n !== '') {
            return $n;
        }

        return (string) ($this->de_lgin ?? '');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
