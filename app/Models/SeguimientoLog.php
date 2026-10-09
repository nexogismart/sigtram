<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoLog extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_sgmto_log';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_lgin',
        'de_sgto_log',
        'fe_sgto_log',
        'de_ip',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'p_sgmto_log';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cdgo_lgin', 'cdgo_lgin');
    }
}
