<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\Route;

/**
 * Scans all registered Laravel routes and groups them by prefix, domain, and module.
 */
class RouteScanner
{
    /**
     * Scan routes and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $routes = Route::getRoutes();
        $items = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            $prefix = $this->resolvePrefix($uri);
            $action = $route->getActionName();

            $items[] = [
                'uri' => $uri,
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'action' => $action,
                'middleware' => $route->middleware(),
                'prefix' => $prefix,
                'module' => $this->resolveModule($prefix, $action),
            ];
        }

        return [
            'routes' => [
                'count' => count($items),
                'items' => $items,
                'groups' => $this->groupByPrefix($items),
            ],
        ];
    }

    private function resolvePrefix(string $uri): string
    {
        if (str_starts_with($uri, 'admin')) {
            return 'admin';
        }
        if (str_starts_with($uri, 'my')) {
            return 'trainee';
        }
        if (str_starts_with($uri, 'trainee')) {
            return 'trainee-auth';
        }
        if (str_starts_with($uri, 'coach')) {
            return 'coach';
        }
        if (str_starts_with($uri, 'site')) {
            return 'public';
        }
        if (str_starts_with($uri, 'api')) {
            return 'api';
        }
        if (str_starts_with($uri, 'yarezan') || str_starts_with($uri, 'course') || str_starts_with($uri, 'food') || str_starts_with($uri, 'hormon') || str_starts_with($uri, 'supplement')) {
            return 'coach-workflow';
        }
        if (str_starts_with($uri, 'dashboard')) {
            return 'dashboard';
        }
        if (str_starts_with($uri, 'auth') || str_starts_with($uri, 'login') || str_starts_with($uri, 'register') || str_starts_with($uri, 'password')) {
            return 'auth';
        }
        return 'web';
    }

    private function resolveModule(string $prefix, string $action): string
    {
        // Map prefix to module
        $moduleMap = [
            'admin' => 'Admin',
            'trainee' => 'Trainee',
            'trainee-auth' => 'Trainee',
            'coach' => 'Coach',
            'coach-workflow' => 'Coach',
            'public' => 'Public',
            'api' => 'API',
            'dashboard' => 'Dashboard',
            'auth' => 'Auth',
        ];

        if (isset($moduleMap[$prefix])) {
            return $moduleMap[$prefix];
        }

        // Try to infer from action namespace
        if (str_contains($action, '\\Admin\\')) {
            return 'Admin';
        }
        if (str_contains($action, '\\Api\\')) {
            return 'API';
        }
        if (str_contains($action, '\\Auth\\')) {
            return 'Auth';
        }
        if (str_contains($action, '\\Trainee\\')) {
            return 'Trainee';
        }

        return 'Web';
    }

    private function groupByPrefix(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $prefix = $item['prefix'];
            if (! isset($groups[$prefix])) {
                $groups[$prefix] = 0;
            }
            $groups[$prefix]++;
        }
        return $groups;
    }
}