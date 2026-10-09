<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PUsrrio extends Model
{
    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $primaryKey = 'cdgo_usrios';

    protected $fillable = [
        'no_crto',
        'no_crto_sstma',
        'de_dire',
        'nu_tele',
        'co_ubig',
        'de_mail',
        'nu_docu',
        'de_ocup_rubr',
        'in_tipo_pers',
        'nu_ruc',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'p_usrios';
    }

    public function natural(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UsrioNatural::class, 'cdgo_usrios', 'cdgo_usrios');
    }

    public function juridico(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UsrioJuridico::class, 'cdgo_usrios', 'cdgo_usrios');
    }
}
