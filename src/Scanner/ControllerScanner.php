<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans app/Http/Controllers recursively and extracts detailed metadata:
 * methods, CRUD type, middleware, validation classes, resource controllers.
 */
class ControllerScanner
{
    /**
     * Scan controllers and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = app_path('Http/Controllers');

        if (! is_dir($path)) {
            return ['controllers' => ['count' => 0, 'items' => []]];
        }

        $files = File::allFiles($path);
        $items = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $file->getContents();

            $name = $this->extractClassName($contents);
            $namespace = $this->extractNamespace($contents);

            if ($name === null) {
                continue;
            }

            $methods = $this->extractMethods($contents);

            $items[] = [
                'name' => $name,
                'namespace' => $namespace ?? 'App\\Http\\Controllers',
                'path' => $file->getRelativePathname(),
                'methods' => $methods,
                'group' => $this->detectGroup($file->getRelativePathname()),
                'is_crud' => $this->isCrudController($methods),
                'middleware' => $this->detectMiddleware($contents),
                'validation_classes' => $this->detectValidation($contents),
                'is_resource' => $this->isResourceController($name),
            ];
        }

        return [
            'controllers' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractMethods(string $contents): array
    {
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $contents, $matches);
        $methods = [];

        foreach ($matches[1] as $method) {
            if (! in_array($method, ['__construct', '__invoke', '__call'])) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    private function detectGroup(string $relativePath): string
    {
        $dir = dirname($relativePath);
        return ($dir === '.') ? 'root' : $dir;
    }

    private function isCrudController(array $methods): bool
    {
        $crudPatterns = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $found = 0;

        foreach ($crudPatterns as $pattern) {
            if (in_array($pattern, $methods)) {
                $found++;
            }
        }

        // At least 3 of the 7 CRUD methods = likely CRUD controller
        return $found >= 3;
    }

    private function detectMiddleware(string $contents): array
    {
        $middleware = [];

        // Controller constructor middleware
        if (preg_match_all('/\$this->middleware\([\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            foreach ($matches[1] as $m) {
                if (! in_array($m, $middleware)) {
                    $middleware[] = $m;
                }
            }
        }

        return $middleware;
    }

    private function detectValidation(string $contents): array
    {
        $validations = [];

        // Detect $this->validate(...)
        if (preg_match_all('/\$this->validate\(/', $contents, $matches)) {
            $validations[] = 'inline_validation';
        }

        // Detect StoreXxxRequest / UpdateXxxRequest type hints
        if (preg_match_all('/(\w+Request)\s+\$/', $contents, $matches)) {
            foreach ($matches[1] as $v) {
                if (! in_array($v, $validations)) {
                    $validations[] = $v;
                }
            }
        }

        return $validations;
    }

    private function isResourceController(string $name): bool
    {
        // Resource controllers often end with Controller and have "Resource" in their logic
        // Check naming conventions
        $lower = strtolower($name);
        return str_ends_with($lower, 'controller') && ! str_contains($lower, 'auth');
    }
}