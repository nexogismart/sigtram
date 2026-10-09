<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = config('database.connections.pgsql.schema') ? config('database.connections.pgsql.schema') . '.' : '';
        // Asumiendo que la tabla pertenece al schema dinámico
        Schema::table($schema . 'r_trmtes_dcmntos', function (Blueprint $table) {
            $table->index(['cdgo_crgo_en', 'cdgo_crgo_sa', 'in_esta', 'cdgo_estdo_trmte'], 'idx_bandeja_seguimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = config('database.connections.pgsql.schema') ? config('database.connections.pgsql.schema') . '.' : '';
        Schema::table($schema . 'r_trmtes_dcmntos', function (Blueprint $table) {
            $table->dropIndex('idx_bandeja_seguimiento');
        });
    }
};
