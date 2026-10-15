<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $cdgo_cmpnia
 * @property string $de_cmpnia
 * @property string|null $dc_cmpnia
 * @property string|null $de_drccion
 * @property string|null $de_tlfno
 * @property string $in_esta
 */
class Compania extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_cmpnia';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'de_cmpnia',
        'dc_cmpnia',
        'de_drccion',
        'de_tlfno',
        'in_esta',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'a_cmpnias';
    }
}
