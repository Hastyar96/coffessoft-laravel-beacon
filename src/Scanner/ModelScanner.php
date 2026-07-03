<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans app/Models recursively and extracts class metadata.
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

            $relations = $this->extractRelations($contents);

            $items[] = [
                'name' => $name,
                'namespace' => $namespace ?? 'App\\Models',
                'path' => $file->getRelativePathname(),
                'relations' => $relations,
                'fillable' => $this->extractFillable($contents),
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

    private function extractFillable(string $contents): array
    {
        if (preg_match('/protected\s+\$fillable\s*=\s*\[([^\]]*)\]/s', $contents, $matches)) {
            $items = explode(',', $matches[1]);
            $fillable = [];

            foreach ($items as $item) {
                $item = trim($item);
                $item = trim($item, "'\"");
                if ($item !== '') {
                    $fillable[] = $item;
                }
            }

            return $fillable;
        }

        return [];
    }
}