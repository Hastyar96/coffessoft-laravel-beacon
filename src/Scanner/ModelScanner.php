<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans app/Models recursively and extracts comprehensive model metadata:
 * fillable, guarded, casts, hidden, appends, relationships, traits, scopes, accessors, mutators.
 */
class ModelScanner
{
    /**
     * Scan models and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = app_path('Models');

        if (! is_dir($path)) {
            return ['models' => ['count' => 0, 'items' => []]];
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
                'namespace' => $namespace ?? 'App\\Models',
                'path' => $file->getRelativePathname(),
                'fillable' => $this->extractArray('fillable', $contents),
                'guarded' => $this->extractArray('guarded', $contents),
                'casts' => $this->extractCasts($contents),
                'hidden' => $this->extractArray('hidden', $contents),
                'appends' => $this->extractArray('appends', $contents),
                'relations' => $this->extractRelations($contents),
                'traits' => $this->extractTraits($contents),
                'scopes' => $this->extractScopes($contents),
                'accessors' => $this->extractAccessors($contents),
                'mutators' => $this->extractMutators($contents),
            ];
        }

        return [
            'models' => [
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

    private function extractArray(string $property, string $contents): array
    {
        $pattern = '/protected\s+\$' . preg_quote($property, '/') . '\s*=\s*\[([^\]]*)\]/s';
        if (preg_match($pattern, $contents, $matches)) {
            $items = explode(',', $matches[1]);
            $result = [];
            foreach ($items as $item) {
                $item = trim($item);
                $item = trim($item, "'\"");
                if ($item !== '') {
                    $result[] = $item;
                }
            }
            return $result;
        }
        return [];
    }

    private function extractCasts(string $contents): array
    {
        $casts = [];

        if (preg_match('/protected\s+\$casts\s*=\s*\[([^\]]*)\]/s', $contents, $matches)) {
            $items = explode(',', $matches[1]);
            foreach ($items as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                // Match 'field' => 'type'
                if (preg_match('/[\'"]([\w_]+)[\'"]\s*=>\s*[\'"]([\w_]+)[\'"]/', $item, $m)) {
                    $casts[$m[1]] = $m[2];
                }
            }
        }

        return $casts;
    }

    private function extractRelations(string $contents): array
    {
        $relations = [];
        $patterns = [
            'hasMany' => '/function\s+\w+\(\).*?return\s+\$this->hasMany\(/s',
            'belongsTo' => '/function\s+\w+\(\).*?return\s+\$this->belongsTo\(/s',
            'hasOne' => '/function\s+\w+\(\).*?return\s+\$this->hasOne\(/s',
            'belongsToMany' => '/function\s+\w+\(\).*?return\s+\$this->belongsToMany\(/s',
            'morphMany' => '/function\s+\w+\(\).*?return\s+\$this->morphMany\(/s',
            'morphTo' => '/function\s+\w+\(\).*?return\s+\$this->morphTo\(/s',
            'morphOne' => '/function\s+\w+\(\).*?return\s+\$this->morphOne\(/s',
        ];

        foreach ($patterns as $type => $pattern) {
            preg_match_all($pattern, $contents, $matches);
            $count = count($matches[0]);
            if ($count > 0) {
                $relations[$type] = $count;
            }
        }

        return $relations;
    }

    private function extractTraits(string $contents): array
    {
        $traits = [];

        if (preg_match('/^use\s+([^;]+);/m', $contents, $matches)) {
            $parts = explode(',', $matches[1]);
            foreach ($parts as $part) {
                $part = trim($part);
                // Remove leading backslash for imported traits
                $part = ltrim($part, '\\');
                if ($part !== '' && $part !== 'HasFactory' && $part !== 'Notifiable' && $part !== 'SoftDeletes') {
                    $traits[] = $part;
                }
            }
        }

        return $traits;
    }

    private function extractScopes(string $contents): array
    {
        $scopes = [];

        if (preg_match_all('/public\s+function\s+scope(\w+)\s*\(/s', $contents, $matches)) {
            foreach ($matches[1] as $scope) {
                $scopes[] = lcfirst($scope);
            }
        }

        return $scopes;
    }

    private function extractAccessors(string $contents): array
    {
        $accessors = [];

        if (preg_match_all('/public\s+function\s+get(\w+)Attribute\s*\(\s*\)/s', $contents, $matches)) {
            foreach ($matches[1] as $attr) {
                $accessors[] = lcfirst($attr);
            }
        }

        return $accessors;
    }

    private function extractMutators(string $contents): array
    {
        $mutators = [];

        if (preg_match_all('/public\s+function\s+set(\w+)Attribute\s*\(/s', $contents, $matches)) {
            foreach ($matches[1] as $attr) {
                $mutators[] = lcfirst($attr);
            }
        }

        return $mutators;
    }
}