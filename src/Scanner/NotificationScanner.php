<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans Notification classes.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class NotificationScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $path = app_path('Notifications');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $channels = [];
            if (preg_match('/public\s+function\s+via\s*\(\s*/', $contents)) {
                if (preg_match('/function\s+via\s*\([^)]*\)\s*\{(.*?)\}/s', $contents, $m)) {
                    if (preg_match_all('/[\'"]([^\'"]channel[\'"]|mail|database|broadcast|nexmo|vonage|slack)[\'"]/', $m[1], $channelMatches)) {
                        $channels = $channelMatches[1];
                    }
                }
            }

            $items[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? '',
                'path' => $file['relative_path'],
                'channels' => $channels,
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods'] ?? []),
                'traits' => $parsed['traits'] ?? [],
            ];
        }

        return ['notifications' => ['count' => count($items), 'items' => $items]];
    }
}