<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans for Livewire components, views, properties, events, and computed values.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class LivewireScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $components = $this->findComponents();

        return [
            'livewire' => [
                'present' => !empty($components),
                'components' => $components,
            ],
        ];
    }

    private function findComponents(): array
    {
        $paths = [
            app_path('Livewire'),
            app_path('Http/Livewire'),
        ];

        $components = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $this->reader->read($file['pathname']);
                if ($contents === '') continue;
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                // Verify it extends a Livewire component
                $isLivewire = str_contains($contents, 'extends Component')
                    || str_contains($parsed['parent'] ?? '', 'Component')
                    || str_contains($contents, 'Livewire\\Component');

                if (!$isLivewire) continue;

                // Detect view template
                $view = null;
                if (preg_match('/protected\s+\$view\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                    $view = $m[1];
                } elseif (preg_match('/public\s+function\s+render\s*\(\s*\)/', $contents)) {
                    if (preg_match('/function\s+render\s*\(\s*\)\s*\{(.*?)\}/s', $contents, $m)) {
                        if (preg_match('/view\(\s*[\'"]([^\'"]+)[\'"]/', $m[1], $v)) {
                            $view = $v[1];
                        } elseif (preg_match('/\$this->view\s*=\s*[\'"]([^\'"]+)[\'"]/', $m[1], $v)) {
                            $view = $v[1];
                        }
                    }
                }

                // Extract properties
                $properties = [];
                foreach ($parsed['properties'] ?? [] as $prop) {
                    $properties[] = [
                        'name' => $prop['name'],
                        'visibility' => $prop['visibility'],
                        'type' => $prop['type'],
                    ];
                }

                // Extract events dispatched (listens for, emits)
                $listeners = [];
                if (preg_match('/protected\s+\$listeners\s*=\s*\[([^\]]*)\]/s', $contents, $m)) {
                    preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $evts);
                    $listeners = $evts[1] ?? [];
                }

                $emits = [];
                if (preg_match_all('/\$this->emit\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                    $emits = $m[1];
                }

                // Detect computed properties (getXProperty methods)
                $computeds = [];
                foreach ($parsed['methods'] ?? [] as $method) {
                    if (preg_match('/^get(\w+)Property$/', $method['name'], $m)) {
                        $computeds[] = lcfirst($m[1]);
                    }
                }

                $components[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? '',
                    'path' => $file['relative_path'],
                    'view' => $view,
                    'properties' => $properties,
                    'computed' => $computeds,
                    'listeners' => $listeners,
                    'emits' => $emits,
                    'traits' => $parsed['traits'] ?? [],
                    'interfaces' => $parsed['interfaces'] ?? [],
                    'methods' => array_map(fn($m) => $m['name'], $parsed['methods'] ?? []),
                    'is_full_page' => $view !== null,
                ];
            }
        }

        return $components;
    }
}