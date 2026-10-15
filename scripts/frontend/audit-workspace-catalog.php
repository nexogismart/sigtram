<?php

// Read-only audit of the full navigation catalogue, independent of any signed-in user.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\WorkspaceNavigation;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    $schema = rtrim(config('auth.sigtram_sistema_schema', 'sistema'), '.').'.';
    $roles = DB::table($schema.'a_rles')->orderBy('cdgo_rle')->get([
        'cdgo_rle', 'cdgo_sub_rle', 'dc_rle', 'in_esta',
    ]);
    $areas = DB::table($schema.'a_areas')->whereNotNull('de_area')->pluck('de_area');
    $areaKeys = $areas->map(fn ($area) => WorkspaceNavigation::canonicalArea($area))->unique()->all();
    $failures = [];
    $groups = [];
    foreach ($roles->filter(fn ($role) => !(int) $role->cdgo_sub_rle) as $parent) {
        $children = $roles->filter(fn ($role) => (int) $role->cdgo_sub_rle === (int) $parent->cdgo_rle);
        $group = WorkspaceNavigation::decorate([[
            'id' => (int) $parent->cdgo_rle, 'label' => $parent->dc_rle,
            'children' => $children->map(fn ($role) => ['id' => (int) $role->cdgo_rle, 'label' => $role->dc_rle])->values()->all(),
        ]])[0];
        $group['databaseActive'] = (string) $parent->in_esta === '1';
        if ($group['databaseActive'] && $group['workspace']['active']) {
            if ($group['workspace']['space'] === 'otros' || !$group['workspace']['owners']) {
                $failures[] = ['id' => $group['id'], 'issue' => 'missing_classification'];
            }
            foreach ($group['workspace']['owners'] as $owner) {
                if ($owner !== config('workspace.areas.todas') && !in_array(WorkspaceNavigation::canonicalArea($owner), $areaKeys, true)) {
                    $failures[] = ['id' => $group['id'], 'issue' => 'owner_not_matched_to_database_area', 'owner' => $owner];
                }
            }
        }
        $groups[] = $group;
    }
    $rootIds = array_column($groups, 'id');
    foreach ($roles as $role) {
        if ((int) $role->cdgo_sub_rle && !in_array((int) $role->cdgo_sub_rle, $rootIds, true)) {
            $failures[] = ['id' => (int) $role->cdgo_rle, 'issue' => 'unresolved_parent'];
        }
    }
    $report = [
        'generatedAt' => now()->toIso8601String(), 'readOnly' => true,
        'modules' => count($groups), 'roles' => $roles->count(),
        'visibleModules' => count(array_filter($groups, fn ($group) => $group['databaseActive'] && $group['workspace']['active'])),
        'failures' => $failures, 'groups' => $groups,
    ];
    file_put_contents(base_path('docs/frontend/auditoria-catalogo-workspace.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(array_diff_key($report, ['groups' => true]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally {
    DB::rollBack();
}
exit(empty($failures) ? 0 : 1);
