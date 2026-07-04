<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans for Job classes and their dispatch points.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class JobScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $jobs = $this->findJobDefinitions();
        $dispatchPoints = $this->findDispatchPoints();

        return [
            'jobs' => [
                'count' => count($jobs),
                'definitions' => $jobs,
                'dispatch_points' => $dispatchPoints,
            ],
        ];
    }

    private function findJobDefinitions(): array
    {
        $path = app_path('Jobs');
        if (!is_dir($path)) return [];

        $jobs = [];

        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $implementsShouldQueue = str_contains($contents, 'ShouldQueue');

            $queue = null;
            if (preg_match('/public\s+\$queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                $queue = $m[1];
            }

            $connection = null;
            if (preg_match('/public\s+\$connection\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                $connection = $m[1];
            }

            $middleware = [];
            if (preg_match_all('/public\s+function\s+middleware\s*\(\s*\)/', $contents, $m)) {
                $middleware[] = 'custom_middleware_method';
            }

            $jobs[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? '',
                'path' => $file['relative_path'],
                'queue' => $queue,
                'connection' => $connection,
                'should_queue' => $implementsShouldQueue,
                'middleware' => $middleware,
                'traits' => $parsed['traits'] ?? [],
                'constructor_deps' => $this->extractConstructorDeps($parsed['methods'] ?? []),
                'properties' => array_map(fn($p) => $p['name'], $parsed['properties'] ?? []),
            ];
        }

        return $jobs;
    }

    private function findDispatchPoints(): array
    {
        $dispatchPoints = [];
        $scanDirs = [
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Console/Commands'),
        ];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach ($this->reader->getPhpFiles($dir) as $file) {
                $contents = $this->reader->read($file['pathname']);
                if ($contents === '') continue;
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                $dispatches = [];

                // Pattern 1: JobClass::dispatch()
                if (preg_match_all('/(\w+Job)::dispatch\(/', $contents, $m)) {
                    foreach ($m[1] as $job) {
                        $dispatches[] = ['class' => $job, 'type' => 'static_dispatch', 'line' => $this->findLine($contents, "$job::dispatch")];
                    }
                }

                // Pattern 2: dispatch(new JobClass(...))
                if (preg_match_all('/dispatch\(\s*new\s+(\w+Job)/', $contents, $m)) {
                    foreach ($m[1] as $job) {
                        $dispatches[] = ['class' => $job, 'type' => 'helper_dispatch', 'line' => $this->findLine($contents, "dispatch(new $job")];
                    }
                }

                // Pattern 3: Bus::dispatch(new JobClass)
                if (preg_match_all('/Bus::dispatch\(\s*new\s+(\w+Job)/', $contents, $m)) {
                    foreach ($m[1] as $job) {
                        $dispatches[] = ['class' => $job, 'type' => 'bus_dispatch', 'line' => $this->findLine($contents, "Bus::dispatch(new $job")];
                    }
                }

                if (!empty($dispatches)) {
                    $dispatchPoints[] = [
                        'class' => $parsed['class_name'],
                        'namespace' => $parsed['namespace'],
                        'path' => $file['relative_path'],
                        'dispatches' => $dispatches,
                    ];
                }
            }
        }

        return $dispatchPoints;
    }

    private function extractConstructorDeps(array $methods): array
    {
        foreach ($methods as $m) {
            if ($m['name'] === '__construct') {
                return array_map(fn($p) => $p['type'] ?? 'unknown', $m['params'] ?? []);
            }
        }
        return [];
    }

    private function findLine(string $contents, string $search): int
    {
        $pos = strpos($contents, $search);
        if ($pos === false) return 0;
        return substr_count(substr($contents, 0, $pos), "\n") + 1;
    }
}