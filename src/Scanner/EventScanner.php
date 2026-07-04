<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans for Event classes, listeners, and dispatch points.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class EventScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $events = $this->findEventDefinitions();
        $listeners = $this->findListeners();
        $dispatchPoints = $this->findDispatchPoints();

        return [
            'events' => [
                'count' => count($events),
                'definitions' => $events,
                'listeners' => $listeners,
                'dispatch_points' => $dispatchPoints,
            ],
        ];
    }

    private function findEventDefinitions(): array
    {
        $path = app_path('Events');
        if (!is_dir($path)) return [];

        $events = [];

        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $shouldDispatch = str_contains($contents, 'ShouldDispatch') || str_contains($contents, 'Dispatchable');

            $events[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? '',
                'path' => $file['relative_path'],
                'should_dispatch' => $shouldDispatch,
                'traits' => $parsed['traits'] ?? [],
                'properties' => array_map(fn($p) => $p['name'], $parsed['properties'] ?? []),
                'constructor_deps' => $this->extractConstructorDeps($parsed['methods'] ?? []),
            ];
        }

        return $events;
    }

    private function findListeners(): array
    {
        $path = app_path('Listeners');
        if (!is_dir($path)) return [];

        $listeners = [];

        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $handleMethod = null;
            foreach ($parsed['methods'] ?? [] as $m) {
                if ($m['name'] === 'handle') {
                    $handleMethod = $m;
                    break;
                }
            }

            $listener = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? '',
                'path' => $file['relative_path'],
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods'] ?? []),
                'traits' => $parsed['traits'] ?? [],
            ];

            if ($handleMethod && !empty($handleMethod['params'])) {
                $firstParam = $handleMethod['params'][0];
                $listener['listens_to'] = $firstParam['type'] ?? null;
            }

            $listeners[] = $listener;
        }

        return $listeners;
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

                // Pattern 1: Event::dispatch()
                if (preg_match_all('/(\w+Event)::dispatch\(/', $contents, $m)) {
                    foreach ($m[1] as $event) {
                        $dispatches[] = ['class' => $event, 'method' => 'static_dispatch', 'line' => 0];
                    }
                }

                // Pattern 2: event(new EventClass(...))
                if (preg_match_all('/event\(\s*new\s+(\w+Event)/', $contents, $m)) {
                    foreach ($m[1] as $event) {
                        $dispatches[] = ['class' => $event, 'method' => 'helper_event', 'line' => 0];
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
}