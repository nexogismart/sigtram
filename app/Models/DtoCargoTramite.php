<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtoCargoTramite extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_dto_trmte',
        'cdgo_crgo_trmte',
        'cdgo_crgo',
        'fe_dto_crgo_incio',
        'nu_orde',
        'in_esta',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'r_dtos_crgos_trmtes';
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(DtoTramite::class, 'cdgo_dto_trmte', 'cdgo_dto_trmte');
    }

    public function cargoTramite(): BelongsTo
    {
        return $this->belongsTo(CargoTramite::class, 'cdgo_crgo_trmte', 'cdgo_crgo_trmte');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cdgo_crgo', 'cdgo_crgo');
    }
}
