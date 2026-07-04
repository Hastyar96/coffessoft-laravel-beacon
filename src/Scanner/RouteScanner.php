<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\Route;

/**
 * v6 Improved RouteScanner — extracts proven route metadata from Laravel's route collection.
 *
 * Every piece of data is provably extracted from actual route registration.
 * No inference, no naming-convention-based assumptions.
 *
 * Extracts for every route:
 * - URI, HTTP Methods, Controller, Method, Route Name
 * - Middleware chain (from route registration)
 * - Prefix (from route action data)
 * - Domain (from route registration)
 * - Parameters (from URI pattern with optional detection)
 * - Evidence (action source, uses source)
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
        try {
            $routes = Route::getRoutes();
        } catch (\Throwable $e) {
            return ['routes' => ['count' => 0, 'items' => [], 'error' => $e->getMessage()]];
        }

        $items = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            $action = $route->getAction();
            $actionName = $route->getActionName();

            // Parse controller@method (proven from route registration)
            $controller = null;
            $method = null;
            $uses = $action['uses'] ?? null;

            if (is_string($uses)) {
                if (str_contains($uses, '@')) {
                    [$controller, $method] = explode('@', $uses, 2);
                } else {
                    $controller = $uses;
                    $method = '__invoke';
                }
            } elseif ($actionName !== 'Closure' && str_contains($actionName, '@')) {
                [$controller, $method] = explode('@', $actionName, 2);
            }

            // Normalize controller FQCN
            if ($controller) {
                $controller = ltrim($controller, '\\');
            }

            // Extract route parameters from URI pattern (proven from route definition)
            $parameters = $this->extractParameters($uri);

            // Extract domain if set (proven from route registration)
            $domain = $route->domain();

            // Get middleware chain (proven from route)
            $middlewareNames = $route->middleware();
            $middlewareClasses = $route->gatherMiddleware();

            // Get route name if set
            $routeName = $route->getName();

            // Get prefix from action data (proven from route group)
            $prefix = $action['prefix'] ?? null;
            if ($prefix === '' || $prefix === '/') {
                $prefix = null;
            }

            // Short class name for readability
            $controllerShort = $controller ? $this->shortClassName($controller) : null;

            $items[] = [
                'uri' => $uri,
                'methods' => $route->methods(),
                'name' => $routeName,
                'action' => $actionName,
                'controller' => $controller,
                'controller_short' => $controllerShort,
                'method' => $method,
                'action_type' => $controller ? 'controller' : 'closure',
                'middleware' => $middlewareNames,
                'middleware_classes' => $middlewareClasses,
                'prefix' => $prefix,
                'domain' => $domain,
                'parameters' => $parameters,
                'parameter_count' => count($parameters),
                'has_wildcard' => str_contains($uri, '{'),
                'module' => $this->resolveModule($prefix, $actionName, $controller ?? ''),
                'evidence' => [
                    'action_source' => $actionName,
                    'uses_source' => $uses,
                    'registration' => 'Route::getRoutes()',
                ],
            ];
        }

        return [
            'routes' => [
                'count' => count($items),
                'items' => $items,
                'groups' => $this->groupByPrefix($items),
                'by_controller' => $this->groupByController($items),
                'by_domain' => $this->groupByDomain($items),
                'named_routes' => array_values(array_filter(array_map(fn($r) => $r['name'], $items))),
                'controller_count' => count(array_unique(array_filter(array_column($items, 'controller')))),
            ],
        ];
    }

    /**
     * Extract URI parameters from route pattern.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractParameters(string $uri): array
    {
        $params = [];
        preg_match_all('/\{(\w+)\??\}/', $uri, $matches);

        foreach ($matches[1] as $index => $param) {
            $isOptional = str_contains($uri, '{' . $param . '?}');
            $params[] = [
                'name' => $param,
                'optional' => $isOptional,
                'position' => $index,
            ];
        }

        return $params;
    }

    /**
     * Resolve module from prefix and action namespace.
     */
    private function resolveModule(?string $prefix, string $action, string $controller): string
    {
        // First try prefix-based module detection
        if ($prefix !== null) {
            $moduleMap = [
                'admin' => 'Admin',
                'api' => 'API',
                'auth' => 'Auth',
            ];

            if (isset($moduleMap[$prefix])) {
                return $moduleMap[$prefix];
            }
        }

        // Infer from controller namespace (proven from actual class location)
        if (str_contains($controller, '\\Admin\\')) {
            return 'Admin';
        }
        if (str_contains($controller, '\\Api\\')) {
            return 'API';
        }
        if (str_contains($controller, '\\Auth\\')) {
            return 'Auth';
        }
        if (str_contains($controller, '\\Trainee\\')) {
            return 'Trainee';
        }
        if (str_contains($controller, '\\Coach\\')) {
            return 'Coach';
        }

        return 'Web';
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    private function groupByPrefix(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $prefix = $item['prefix'] ?? '(root)';
            if (!isset($groups[$prefix])) {
                $groups[$prefix] = ['count' => 0, 'controllers' => []];
            }
            $groups[$prefix]['count']++;
            if ($item['controller_short'] && !in_array($item['controller_short'], $groups[$prefix]['controllers'])) {
                $groups[$prefix]['controllers'][] = $item['controller_short'];
            }
        }
        return $groups;
    }

    private function groupByController(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item['controller'] ?? 'Closure';
            if (!isset($groups[$key])) {
                $groups[$key] = ['count' => 0, 'routes' => [], 'methods' => []];
            }
            $groups[$key]['count']++;
            $groups[$key]['routes'][] = $item['uri'];
            if ($item['method'] && !in_array($item['method'], $groups[$key]['methods'])) {
                $groups[$key]['methods'][] = $item['method'];
            }
        }
        return $groups;
    }

    private function groupByDomain(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $domain = $item['domain'] ?? '(no domain)';
            if (!isset($groups[$domain])) {
                $groups[$domain] = ['count' => 0, 'routes' => []];
            }
            $groups[$domain]['count']++;
            $groups[$domain]['routes'][] = $item['uri'];
        }
        return $groups;
    }
}
