<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'cdgo_area';

    protected $keyType = 'string';

    public $timestamps = false;

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'a_areas';
    }

    public function subgerencias(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Area::class, 'cdgo_area_prim', 'cdgo_area');
    }

    public function gerencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Area::class, 'cdgo_area_prim', 'cdgo_area');
    }
}
