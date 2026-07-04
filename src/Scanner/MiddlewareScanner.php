<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans Middleware classes.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class MiddlewareScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $paths = [
            app_path('Http/Middleware'),
        ];

        $items = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $this->reader->read($file['pathname']);
                if ($contents === '') continue;
                $name = $this->reader->extractClassName($contents);
                if ($name === null) continue;

                $methods = $this->reader->extractPublicMethods($contents);

                $items[] = [
                    'name' => $name,
                    'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Http\\Middleware',
                    'path' => $file['relative_path'],
                    'methods' => $methods,
                ];
            }
        }

        return ['middleware' => ['count' => count($items), 'items' => $items]];
    }
}