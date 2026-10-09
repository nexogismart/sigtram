<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtoRequisito extends Model
{
    public $incrementing = false;

    // Use composite key behavior by disabling default ID primary key
    protected $primaryKey = null;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_dto_trmte',
        'cdgo_rqto_trmte',
        'in_pres',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'r_dtos_rqtos';
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(DtoTramite::class, 'cdgo_dto_trmte', 'cdgo_dto_trmte');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoTramite::class, 'cdgo_rqto_trmte', 'cdgo_rqto_trmte');
    }
}
