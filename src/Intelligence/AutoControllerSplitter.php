<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * v5.0 Auto Controller Splitter
 *
 * Detects controllers with >10 methods OR >300 lines.
 * Suggests exact split structure by mapping methods to logical sub-controllers.
 * Uses model names, method naming patterns, and responsibility grouping.
 *
 * Output: split-suggestions.json — NEVER auto-applies changes.
 */
class AutoControllerSplitter
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $suggestions = [];
        $modelNames = array_map(fn($m) => $m['name'], $data['models']['items'] ?? []);

        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $methods = $ctrl['methods'] ?? [];
            $methodCount = count($methods);

            // Estimate lines from path if available
            $lineCount = $this->estimateLines($ctrl['path'] ?? '');
            $isLarge = $methodCount > 10 || $lineCount > 300;

            if (!$isLarge) continue;

            $split = $this->buildSplitPlan($ctrl['name'], $methods, $modelNames, $lineCount);

            if (count($split['new_controllers']) > 0) {
                $suggestions[] = $split;
            }
        }

        return [
            'controller_splits' => [
                'count' => count($suggestions),
                'suggestions' => $suggestions,
                'confidence' => 80,
            ],
        ];
    }

    private function buildSplitPlan(string $ctrlName, array $methods, array $modelNames, int $lineCount): array
    {
        $groups = [];
        $used = [];

        // Group methods by model name match
        foreach ($methods as $m) {
            foreach ($modelNames as $model) {
                $modelLower = strtolower($model);
                if (str_contains(strtolower($m), $modelLower) && !in_array($m, $used)) {
                    $groups[$model][] = $m;
                    $used[] = $m;
                    break;
                }
            }
        }

        // Group remaining by prefix (e.g. "export" from "exportCSV", "exportPDF")
        foreach ($methods as $m) {
            if (in_array($m, $used)) continue;
            // Extract meaningful prefix (e.g. "get", "set", "create", "find")
            if (preg_match('/^(get|set|create|find|search|list|show|store|update|delete|export|import|process|handle|calculate|validate|notify|send|generate)(\w+)?/i', $m, $parts)) {
                $key = ucfirst(strtolower($parts[1]));
                $groups[$key][] = $m;
                $used[] = $m;
            }
        }

        // Remaining methods go to "General"
        $remaining = array_diff($methods, $used);
        foreach ($remaining as $m) {
            $groups['General'][] = $m;
        }

        // Build new controller definitions
        $newControllers = [];
        foreach ($groups as $name => $groupMethods) {
            if (count($groupMethods) < 1) continue;

            // Derive controller name
            if ($name === 'General') {
                $ctrlBase = preg_replace('/Controller$/', '', $ctrlName);
                $newName = "{$ctrlBase}GeneralController";
            } else {
                $newName = "{$name}Controller";
            }

            $newControllers[] = [
                'name' => $newName,
                'methods' => $groupMethods,
                'method_count' => count($groupMethods),
            ];
        }

        return [
            'original_controller' => $ctrlName,
            'original_methods' => count($methods),
            'original_lines' => $lineCount,
            'recommendation' => count($newControllers) >= 2 ? 'split' : (count($methods) > 10 ? 'split' : 'refactor'),
            'new_controllers' => $newControllers,
            'total_split_controllers' => count($newControllers),
            'avg_methods_per_controller' => count($methods) > 0 ? round(count($methods) / max(count($newControllers), 1), 1) : 0,
            'confidence' => 80,
        ];
    }

    private function estimateLines(string $path): int
    {
        $paths = [
            app_path('Http/Controllers/' . ltrim($path, '/')),
            base_path(ltrim($path, '/')),
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                return count(file($p));
            }
        }
        return 0;
    }
}