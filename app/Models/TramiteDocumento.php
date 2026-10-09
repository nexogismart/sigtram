<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TramiteDocumento extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_trmte_dcmnto';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_crgo_sa',
        'cdgo_crgo_en',
        'cdgo_dto_trmte',
        'cdgo_estdo_trmte',
        'fe_rcbdo_trmte',
        'de_obse',
        'in_esta',
        'de_urls_imag',
        'cdgo_lgin',
        'nu_foli_anex',
        'fe_cta_vrtal',
        'nu_anxo',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'r_trmtes_dcmntos';
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(DtoTramite::class, 'cdgo_dto_trmte', 'cdgo_dto_trmte');
    }

    public function cargoEn(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cdgo_crgo_en', 'cdgo_crgo');
    }

    public function cargoSa(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cdgo_crgo_sa', 'cdgo_crgo');
    }
}
