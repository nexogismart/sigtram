<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsrioNatural extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'cdgo_usrios';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_usrios',
        'cdgo_estdo_cvil',
        'cdgo_dcmntos_iden',
        'ap_pate',
        'ap_mate',
        'no_usrio',
        'nu_edad',
        'in_sexo',
        'co_usua_modi',
        'ts_usua_modi',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'h_usrios_ntral';
    }

    public function usrio(): BelongsTo
    {
        return $this->belongsTo(PUsrrio::class, 'cdgo_usrios', 'cdgo_usrios');
    }
}
