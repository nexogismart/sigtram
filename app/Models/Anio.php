<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $cdgo_anio
 * @property int|string $de_anio
 * @property string $dc_anio
 * @property int $cdgo_cmpnia
 */
class Anio extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_anio';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'de_anio',
        'dc_anio',
        'cdgo_cmpnia',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'a_anios';
    }
}
