<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cargo extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_crgo';

    protected $keyType = 'int';

    public $timestamps = false;

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'p_crgos';
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'cdgo_area', 'cdgo_area');
    }
}
