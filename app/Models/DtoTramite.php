<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $cdgo_dto_trmte
 * @property int $cdgo_usrios
 * @property int|null $cdgo_docmnto_trmte
 * @property int $cdgo_tpo_trmte
 * @property int $cdgo_anio
 * @property int $cdgo_area
 * @property int $cdgo_mdlo
 * @property int $cdgo_cmpnia
 * @property int|null $cdgo_usrios_crreo
 * @property string $nu_expe_todo
 * @property int $nu_expe_trmte
 * @property string $nu_docu_trmte
 * @property string $in_docu_trmte
 * @property int $nu_foli_trmte
 * @property mixed $fe_ingr_trmte
 * @property mixed $fe_limi_trmte
 * @property string $de_obse_trmte
 * @property string $in_sile_admi
 * @property string $in_encu_tram
 * @property string $de_obse_todo
 * @property int $co_usua_modi
 * @property int $co_usua_ingr
 * @property mixed $ts_usua_modi
 */
class DtoTramite extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_dto_trmte';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_usrios',
        'cdgo_docmnto_trmte',
        'cdgo_tpo_trmte',
        'cdgo_anio',
        'cdgo_area',
        'cdgo_mdlo',
        'cdgo_cmpnia',
        'cdgo_usrios_crreo',
        'nu_expe_todo',
        'nu_expe_trmte',
        'nu_docu_trmte',
        'in_docu_trmte',
        'nu_foli_trmte',
        'fe_ingr_trmte',
        'fe_limi_trmte',
        'de_obse_trmte',
        'in_sile_admi',
        'in_encu_tram',
        'de_obse_todo',
        'co_usua_modi',
        'co_usua_ingr',
        'ts_usua_modi',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'p_dto_trmtes';
    }

    public function anio(): BelongsTo
    {
        return $this->belongsTo(Anio::class, 'cdgo_anio', 'cdgo_anio');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'cdgo_area', 'cdgo_area');
    }

    public function documentoTupa(): BelongsTo
    {
        return $this->belongsTo(DocumentoTramiteTupa::class, 'cdgo_docmnto_trmte', 'cdgo_docmnto_trmte');
    }

    public function requisitosDto(): HasMany
    {
        return $this->hasMany(DtoRequisito::class, 'cdgo_dto_trmte', 'cdgo_dto_trmte');
    }

    public function usrio(): BelongsTo
    {
        return $this->belongsTo(PUsrrio::class, 'cdgo_usrios', 'cdgo_usrios');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TramiteDocumento::class, 'cdgo_dto_trmte', 'cdgo_dto_trmte');
    }

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'cdgo_cmpnia', 'cdgo_cmpnia');
    }
}
