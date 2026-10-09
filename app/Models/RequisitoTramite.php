<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoTramite extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_rqto_trmte';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_docmnto_trmte',
        'cdgo_rqto_subtrmte',
        'de_rqto_trmte',
        'dc_rqto_trmte',
        'nu_orde',
        'cdgo_srvcio',
        'prcio_trmte',
        'in_ctgria',
        'in_adjntar',
        'in_web',
        'in_oblgtrio',
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
        return $this->schemaPrefix() . 'h_rqtos_trmtes';
    }

    public function documentoTupa(): BelongsTo
    {
        return $this->belongsTo(DocumentoTramiteTupa::class, 'cdgo_docmnto_trmte', 'cdgo_docmnto_trmte');
    }
}
