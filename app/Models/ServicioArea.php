<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicioArea extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_srvcio_area';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'de_srvcio',
        'dc_srvcio',
        'in_esta',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'a_srvcio_areas';
    }

    public function documentosTupa(): HasMany
    {
        return $this->hasMany(DocumentoTramiteTupa::class, 'cdgo_srvcio_area', 'cdgo_srvcio_area');
    }
}
