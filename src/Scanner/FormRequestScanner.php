<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans Form Request classes for rules, authorize, dependencies.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class FormRequestScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $path = app_path('Http/Requests');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $name = $this->reader->extractClassName($contents);
            if ($name === null) continue;

            $includesAuthorize = str_contains($contents, 'public function authorize');
            $includesRules = str_contains($contents, 'public function rules');

            $rules = null;
            if (preg_match('/function\s+rules\s*\(\s*\)\s*\{(.*?)\}/s', $contents, $m)) {
                if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $m[1], $ruleMatches)) {
                    $rules = array_combine($ruleMatches[1], $ruleMatches[2]);
                }
            }

            $items[] = [
                'name' => $name,
                'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Http\\Requests',
                'path' => $file['relative_path'],
                'has_authorize' => $includesAuthorize,
                'has_rules' => $includesRules,
            ];
        }

        return ['form_requests' => ['count' => count($items), 'items' => $items]];
    }
}