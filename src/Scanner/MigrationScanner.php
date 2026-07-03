<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans database/migrations and extracts table/schema metadata.
 */
class MigrationScanner
{
    /**
     * Scan migrations and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = database_path('migrations');

        if (! is_dir($path)) {
            return ['migrations' => ['count' => 0, 'items' => []]];
        }

        $files = File::allFiles($path);
        $items = [];
        $tables = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilename();
            $contents = $file->getContents();

            $class = $this->extractClass($contents);
            $timestamp = $this->extractTimestamp($filename);
            $table = $this->detectTable($contents);
            $operation = $this->detectOperation($contents);

            if ($table !== null) {
                $tables[] = $table;
            }

            $items[] = [
                'filename' => $filename,
                'class' => $class,
                'timestamp' => $timestamp,
                'table' => $table,
                'operation' => $operation,
                'columns' => $this->detectColumns($contents),
            ];
        }

        return [
            'migrations' => [
                'count' => count($items),
                'items' => $items,
                'tables' => array_unique($tables),
            ],
        ];
    }

    private function extractClass(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractTimestamp(string $filename): string
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $filename, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function detectTable(string $contents): ?string
    {
        $patterns = [
            '/Schema::create\([\'"](\w+)[\'"]/',
            '/Schema::table\([\'"](\w+)[\'"]/',
            '/Schema::drop\([\'"](\w+)[\'"]/',
            '/Schema::dropIfExists\([\'"](\w+)[\'"]/',
            '/create\s+table\s*\([\'"](\w+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function detectOperation(string $contents): string
    {
        if (preg_match('/Schema::create\(/', $contents)) return 'create';
        if (preg_match('/Schema::table\(/', $contents)) return 'update';
        if (preg_match('/Schema::drop/', $contents)) return 'drop';
        return 'unknown';
    }

    private function detectColumns(string $contents): array
    {
        $columns = [];
        // Detect $table->type('column') patterns
        if (preg_match_all('/\$table->(\w+)\([\'"]([^\'"]+)[\'"]\)/', $contents, $matches)) {
            foreach ($matches[1] as $i => $type) {
                if (! in_array($type, ['id', 'timestamps', 'softDeletes', 'rememberToken', 'morphs', 'nullableMorphs'])) {
                    $columns[] = ['name' => $matches[2][$i], 'type' => $type];
                }
            }
        }
        return $columns;
    }
}
