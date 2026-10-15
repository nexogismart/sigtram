<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class WorkspaceNavigation
{
    public static function areaAliases(): array
    {
        $aliases = [];
        foreach (config('workspace.areas', []) as $key => $canonical) {
            foreach ([$canonical, ...config('workspace.area_aliases.' . $key, [])] as $label) {
                $aliases[Str::lower(Str::ascii(trim($label)))] = $canonical;
            }
        }
        return $aliases;
    }

    public static function canonicalArea(string $area): string
    {
        return self::areaAliases()[Str::lower(Str::ascii(trim($area)))] ?? trim($area);
    }

    /** Decorates only the already-authorized menu; never queries or adds permissions. */
    public static function decorate(array $menu): array
    {
        $sources = config('workspace.sources', []);
        $byName = [];
        foreach ($sources as $source) {
            $byName[Str::lower(Str::ascii($source[2]))] = $source;
        }

        foreach ($menu as &$group) {
            $name = Str::lower(Str::ascii($group['label']));
            $source = isset($group['id'])
                ? ($sources[$group['id']] ?? null)
                : ($byName[$name] ?? null);
            $source ??= config('workspace.named_sources.' . $name);
            $sourceId = isset($group['id']) ? (int) $group['id'] : null;
            if ($sourceId === null && $source) {
                $sourceId = array_search($source, $sources, true);
            }
            $governance = config('workspace.governance.' . $sourceId, [])
                ?: config('workspace.named_governance.' . $name, []);
            // Unmapped/new menus stay visible in an explicit catch-all.
            $meta = [
                'space' => $source[0] ?? 'otros',
                'service' => $source[1] ?? $group['label'],
                'badge' => $source[3] ?? '',
                'active' => !in_array($sourceId, config('workspace.inactive_sources', []), true),
                'owners' => [], 'sharedWith' => [], 'audience' => 'Acceso individual asignado',
            ];
            $meta = array_replace($meta, $governance);
            $meta = self::resolveAreas($meta);
            $group['workspace'] = $meta;
            foreach ($group['children'] ?? [] as $index => $entry) {
                $entryMeta = array_replace($meta, config('workspace.entry_overrides.' . ($entry['id'] ?? ''), []));
                // A child override cannot reactivate a source declared inactive.
                $entryMeta['active'] = $meta['active'];
                $group['children'][$index]['workspace'] = self::resolveAreas($entryMeta) + [
                    'actionLabel' => config('workspace.actions.' . ($entry['id'] ?? ''), $entry['label']),
                ];
            }
        }
        unset($group);

        return $menu;
    }

    private static function resolveAreas(array $meta): array
    {
        foreach (['owners', 'sharedWith'] as $field) {
            $meta[$field] = array_map(fn (string $area): string => config('workspace.areas.' . $area, $area), $meta[$field] ?? []);
        }

        return $meta;
    }
}
