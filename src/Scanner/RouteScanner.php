<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\Route;

/**
 * Scans all registered Laravel routes and groups them by prefix.
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

            $items[] = [
                'uri' => $uri,
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
                'prefix' => $prefix,
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
        return 'web';
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
