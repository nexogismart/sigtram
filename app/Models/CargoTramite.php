<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoTramite extends Model
{
    public $incrementing = true;

    protected $primaryKey = 'cdgo_crgo_trmte';

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'cdgo_docmnto_trmte',
        'cdgo_crgo',
        'nu_orde',
        'in_esta',
    ];

    protected function schemaPrefix(): string
    {
        return rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function getTable(): string
    {
        return $this->schemaPrefix() . 'h_crgos_trmtes';
    }

    public function documentoTupa(): BelongsTo
    {
        return $this->belongsTo(DocumentoTramiteTupa::class, 'cdgo_docmnto_trmte', 'cdgo_docmnto_trmte');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cdgo_crgo', 'cdgo_crgo');
    }
}
