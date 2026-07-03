<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Analyzes scanned project data to detect logical modules.
 */
class ModuleDetector
{
    /**
     * Detect modules from scanned project data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $modules = [];
        $routes = $data['routes']['groups'] ?? [];
        $controllers = $data['controllers']['items'] ?? [];

        $knownModules = [
            'admin' => ['label' => 'Admin Panel', 'prefixes' => ['admin']],
            'coach' => ['label' => 'Coach (Mamosta)', 'prefixes' => ['coach', 'coach-workflow']],
            'trainee' => ['label' => 'Trainee', 'prefixes' => ['trainee', 'trainee-auth']],
            'public' => ['label' => 'Public', 'prefixes' => ['public', 'web']],
            'api' => ['label' => 'API', 'prefixes' => ['api']],
        ];

        foreach ($knownModules as $key => $config) {
            $routeCount = 0;

            foreach ($config['prefixes'] as $prefix) {
                $routeCount += $routes[$prefix] ?? 0;
            }

            if ($routeCount > 0) {
                $modules[$key] = [
                    'key' => $key,
                    'label' => $config['label'],
                    'route_count' => $routeCount,
                    'detected' => true,
                ];
            }
        }

        $controllerGroups = [];

        foreach ($controllers as $c) {
            $g = $c['group'] ?? 'root';

            if ($g !== 'root' && ! isset($controllerGroups[$g])) {
                $controllerGroups[$g] = 0;
            }

            if ($g !== 'root') {
                $controllerGroups[$g]++;
            }
        }

        return [
            'modules' => $modules,
            'controller_groups' => $controllerGroups,
            'total_modules' => count($modules),
        ];
    }
}