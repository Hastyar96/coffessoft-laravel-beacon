<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans app/Http/Controllers recursively and extracts method metadata.
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

            $items[] = [
                'name' => $name,
                'namespace' => $namespace ?? 'App\\Http\\Controllers',
                'path' => $file->getRelativePathname(),
                'methods' => $this->extractMethods($contents),
                'group' => $this->detectGroup($file->getRelativePathname()),
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
        preg_match_all(
            '/public\s+function\s+(\w+)\s*\(/',
            $contents,
            $matches
        );

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

        if ($dir === '.') {
            return 'root';
        }

        return $dir;
    }
}