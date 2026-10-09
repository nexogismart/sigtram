<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsrioJuridico extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'cdgo_usrios';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_usrios',
        'rz_soci',
        'co_usua_modi',
        'ts_usua_modi',
        'in_esta',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'h_usrios_jrid';
    }

    public function usrio(): BelongsTo
    {
        return $this->belongsTo(PUsrrio::class, 'cdgo_usrios', 'cdgo_usrios');
    }
}
