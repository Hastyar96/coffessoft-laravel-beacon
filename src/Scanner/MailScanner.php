<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans Mail classes.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class MailScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $path = app_path('Mail');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $buildMethodExists = $this->hasBuildMethod($parsed['methods']);

            $items[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? '',
                'path' => $file['relative_path'],
                'has_build_method' => $buildMethodExists,
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods'] ?? []),
                'traits' => $parsed['traits'] ?? [],
                'view' => $this->extractView($contents),
            ];
        }

        return ['mail' => ['count' => count($items), 'items' => $items]];
    }

    private function hasBuildMethod(array $methods): bool
    {
        foreach ($methods as $m) {
            if ($m['name'] === 'build') return true;
        }
        return false;
    }

    private function extractView(string $contents): ?string
    {
        if (preg_match('/\$this->view\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        if (preg_match('/\$this->html\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        if (preg_match('/->view\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        return null;
    }
}