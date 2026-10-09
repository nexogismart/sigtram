<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoTramiteTupa extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_docmnto_trmte';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_srvcio_area',
        'cdgo_area',
        'cdgo_tupa',
        'de_docmnto_trmte',
        'dc_docmnto_trmte',
        'in_sile_admi',
        'nu_tiem_sile',
        'no_url_trmtes',
        'ts_usua_modi',
        'co_usua_modi',
        'in_esta',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'a_docmntos_trmtes';
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'cdgo_area', 'cdgo_area');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioArea::class, 'cdgo_srvcio_area', 'cdgo_srvcio_area');
    }

    public function requisitos(): HasMany
    {
        return $this->hasMany(RequisitoTramite::class, 'cdgo_docmnto_trmte', 'cdgo_docmnto_trmte');
    }
}
