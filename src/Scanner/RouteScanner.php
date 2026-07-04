<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

/**
 * Static RouteScanner — extracts route metadata from route files.
 *
 * Parses the project's route files using token-based analysis.
 * No Application booting. No class autoloading. No ReflectionClass.
 */
class RouteScanner
{
    /**
     * Scan route files and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $routePath = base_path('routes');
        if (!is_dir($routePath)) {
            return ['routes' => ['count' => 0, 'items' => []]];
        }

        $items = [];

        foreach (new \DirectoryIterator($routePath) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = @file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            $parsedRoutes = $this->parseRouteFile($contents, $file->getFilename());
            $items = array_merge($items, $parsedRoutes);
        }

        usort($items, fn($a, $b) => ($a['uri'] ?? '') <=> ($b['uri'] ?? ''));

        return [
            'routes' => [
                'count' => count($items),
                'items' => $items,
                'groups' => $this->groupByPrefix($items),
                'by_controller' => $this->groupByController($items),
                'controller_count' => count(array_unique(array_filter(array_column($items, 'controller')))),
            ],
        ];
    }

    /**
     * Parse a route file and extract route definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRouteFile(string $contents, string $filename): array
    {
        $routes = [];

        // Strip comments for cleaner parsing
        $clean = preg_replace('/\/\/.*$|\/\*[\s\S]*?\*\//m', '', $contents);

        // Extract prefix from Route::group(['prefix' => ...]) or Route::prefix(...)->group(...)
        $prefix = null;
        if (preg_match('/Route::prefix\([\'"]([^\'"]+)[\'"]\)/', $clean, $m)) {
            $prefix = '/' . trim($m[1], '/');
        } elseif (preg_match('/Route::group\(.*?[\'"]prefix[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $clean, $m)) {
            $prefix = '/' . trim($m[1], '/');
        }

        // Extract middleware from groups
        $groupMiddleware = [];
        if (preg_match('/Route::group\(.*?[\'"]middleware[\'"]\s*=>\s*\[([^\]]*)\]/s', $clean, $m)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $midMatches);
            $groupMiddleware = $midMatches[1] ?? [];
        }

        // Extract name prefix from groups
        $namePrefix = '';
        if (preg_match('/Route::group\(.*?[\'"]as[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $clean, $m)) {
            $namePrefix = $m[1];
        } elseif (preg_match('/Route::name\([\'"]([^\'"]+)[\'"]\)->group/', $clean, $m)) {
            $namePrefix = $m[1];
        }

        // Route::get, Route::post, Route::put, Route::patch, Route::delete, Route::any, Route::match
        $patterns = [
            'Route::get' => ['GET'],
            'Route::post' => ['POST'],
            'Route::put' => ['PUT'],
            'Route::patch' => ['PATCH'],
            'Route::delete' => ['DELETE'],
            'Route::options' => ['OPTIONS'],
            'Route::any' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'Route::match' => null, // Requires special handling
        ];

        foreach ($patterns as $methodName => $methods) {
            if ($methodName === 'Route::match') {
                // Route::match(['GET', 'POST'], 'uri', ...)
                if (preg_match_all('/Route::match\s*\(\s*\[([^\]]+)\]\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[?\s*[\'"]([^\'"]+)[\'"]\s*@\s*([a-zA-Z_]+)\s*\]?\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        preg_match_all('/[\'"]([A-Z]+)[\'"]/', $match[1], $methodMatches);
                        $matchMethods = $methodMatches[1] ?? ['GET'];
                        $routes[] = $this->makeRoute($matchMethods, $match[2], $match[3] . '@' . $match[4], $filename, $prefix, $groupMiddleware, $namePrefix);
                    }
                }
                if (preg_match_all('/Route::match\s*\(\s*\[([^\]]+)\]\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?:\[(.*?)\]|[\'"]([a-zA-Z0-9_\\\\]+)[\'"])\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        preg_match_all('/[\'"]([A-Z]+)[\'"]/', $match[1], $methodMatches);
                        $matchMethods = $methodMatches[1] ?? ['GET'];
                        $action = $match[3] ?? ($match[4] ?? '');
                        if (!empty($action) && !str_starts_with($action, '[')) {
                            $routes[] = $this->makeRoute($matchMethods, $match[2], $action, $filename, $prefix, $groupMiddleware, $namePrefix);
                        }
                    }
                }
                continue;
            }

            // Pattern 1: Route::method('uri', [Controller::class, 'method'])
            if (preg_match_all('/' . preg_quote($methodName, '/') . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[?\s*[\'"]([^\'"]+)[\'"]\s*@\s*([a-zA-Z_]+)\s*\]?\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $routes[] = $this->makeRoute($methods, $match[1], $match[2] . '@' . $match[3], $filename, $prefix, $groupMiddleware, $namePrefix);
                }
            }

            // Pattern 2: Route::method('uri', [Controller::class, 'method'])->name('name')
            if (preg_match_all('/' . preg_quote($methodName, '/') . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[?\s*[\'"]([a-zA-Z0-9_\\\\]+)[\'"]\s*@\s*([a-zA-Z_]+)\s*\]?\s*\)\s*->\s*name\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $routes[] = $this->makeRoute($methods, $match[1], $match[2] . '@' . $match[3], $filename, $prefix, $groupMiddleware, $namePrefix, $match[4]);
                }
            }

            // Pattern 3: Route::method('uri', 'Controller@method')
            if (preg_match_all('/' . preg_quote($methodName, '/') . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_\\\\]+@[a-zA-Z_]+)[\'"]\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $routes[] = $this->makeRoute($methods, $match[1], $match[2], $filename, $prefix, $groupMiddleware, $namePrefix);
                }
            }

            // Pattern 4: Route::method('uri', 'Controller@method')->name('name')
            if (preg_match_all('/' . preg_quote($methodName, '/') . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_\\\\]+@[a-zA-Z_]+)[\'"]\s*\)\s*->\s*name\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $routes[] = $this->makeRoute($methods, $match[1], $match[2], $filename, $prefix, $groupMiddleware, $namePrefix, $match[3]);
                }
            }

            // Pattern 5: Route::method('uri', InvokableController::class)
            if (preg_match_all('/' . preg_quote($methodName, '/') . '\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_\\\\]+)[\'"]\s*\)/', $clean, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (str_contains($match[2], '@')) continue; // Already handled above
                    $routes[] = $this->makeRoute($methods, $match[1], $match[2] . '@__invoke', $filename, $prefix, $groupMiddleware, $namePrefix);
                }
            }
        }

        // Remove duplicates (keep last occurrence which has more chain methods like ->name())
        $seen = [];
        $unique = [];
        foreach (array_reverse($routes) as $route) {
            $key = implode('|', $route['methods']) . '|' . ($route['uri'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $route;
            }
        }

        return array_reverse($unique);
    }

    private function makeRoute(
        array $methods,
        string $uri,
        string $action,
        string $filename = '',
        ?string $prefix = null,
        array $groupMiddleware = [],
        string $namePrefix = '',
        ?string $name = null
    ): array {
        $uri = '/' . ltrim($uri, '/');

        // Apply prefix
        if ($prefix !== null) {
            $uri = $prefix . $uri;
        }

        // Normalize URI
        $uri = '/' . ltrim($uri, '/');
        $uri = preg_replace('#/{2,}#', '/', $uri);

        // Parse controller@method
        $controller = null;
        $method = null;
        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            $controller = ltrim($controller, '\\');
        } else {
            $controller = $action;
        }

        // Short class name
        $controllerShort = $controller ? $this->shortClassName($controller) : null;

        // Extract parameters from URI
        $parameters = $this->extractParameters($uri);

        // Build full name
        $fullName = null;
        if ($name) {
            $fullName = $namePrefix . $name;
        }

        return [
            'uri' => $uri,
            'methods' => $methods,
            'name' => $fullName,
            'action' => $action,
            'controller' => $controller,
            'controller_short' => $controllerShort,
            'method' => $method,
            'action_type' => $controller ? 'controller' : 'closure',
            'middleware' => $groupMiddleware,
            'prefix' => $prefix,
            'domain' => null,
            'parameters' => $parameters,
            'parameter_count' => count($parameters),
            'has_wildcard' => str_contains($uri, '{'),
            'module' => $this->resolveModule($prefix, $action, $controller ?? ''),
            'evidence' => [
                'source_file' => $filename,
            ],
        ];
    }

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

    private function resolveModule(?string $prefix, string $action, string $controller): string
    {
        if ($prefix !== null) {
            $prefix = trim($prefix, '/');
            $moduleMap = [
                'admin' => 'Admin',
                'api' => 'API',
                'auth' => 'Auth',
            ];
            if (isset($moduleMap[$prefix])) {
                return $moduleMap[$prefix];
            }
        }

        if (str_contains($controller, '\\Admin\\')) return 'Admin';
        if (str_contains($controller, '\\Api\\')) return 'API';
        if (str_contains($controller, '\\Auth\\')) return 'Auth';

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
                $groups[$prefix] = ['total' => 0, 'controllers' => []];
            }
            $groups[$prefix]['total']++;
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
}