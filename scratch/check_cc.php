<?php
require 'c:/laragon/www/sigtram3/vendor/autoload.php';
$app = require_once 'c:/laragon/www/sigtram3/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $r = DB::select('SELECT schema_name FROM information_schema.schemata');
    foreach($r as $s) {
        if (strpos($s->schema_name, 'sigtram') !== false) {
            echo "Schema found: " . $s->schema_name . "\n";
            $schema = $s->schema_name;
        }
    }
    
    if (isset($schema)) {
        $t = DB::table($schema . '.a_tpo_trmtes')->get(['cdgo_tpo_trmte', 'de_tpo_trmte', 'in_cc']);
        foreach($t as $v) {
            if ($v->in_cc == '1') {
                echo "[CC YES] " . $v->de_tpo_trmte . "\n";
            } else {
                echo "[CC NO] " . $v->de_tpo_trmte . "\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
