<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans database/migrations to extract detailed schema information:
 * columns, foreign keys, indexes, pivot tables, timestamps, soft deletes.
 */
class DatabaseScanner
{
    /**
     * Scan migrations and return database metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = database_path('migrations');

        if (! is_dir($path)) {
            return ['database' => ['tables' => [], 'pivot_tables' => [], 'total_tables' => 0]];
        }

        $files = File::files($path);
        $schema = $this->parseMigrations($files);

        return [
            'database' => [
                'tables' => $schema['tables'],
                'foreign_keys' => $schema['foreign_keys'],
                'pivot_tables' => $schema['pivot_tables'],
                'has_timestamps' => $schema['has_timestamps'],
                'has_soft_deletes' => $schema['has_soft_deletes'],
                'total_tables' => count($schema['tables']),
                'total_foreign_keys' => count($schema['foreign_keys']),
            ],
        ];
    }

    /**
     * @param array<int, \Symfony\Component\Finder\SplFileInfo> $files
     * @return array<string, mixed>
     */
    private function parseMigrations(array $files): array
    {
        $tables = [];
        $foreignKeys = [];
        $pivotTables = [];
        $hasTimestamps = false;
        $hasSoftDeletes = false;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $file->getContents();

            // Detect table creation
            $tableName = $this->detectTableName($contents);
            if ($tableName === null) {
                continue;
            }

            $columns = $this->detectColumns($contents);
            $tableForeignKeys = $this->detectForeignKeys($contents);
            $isPivot = $this->isPivotTable($tableName, $columns, $tableForeignKeys);

            if ($isPivot) {
                $pivotTables[] = $tableName;
            }

            $tables[$tableName] = [
                'name' => $tableName,
                'columns' => $columns,
                'foreign_keys' => $tableForeignKeys,
                'is_pivot' => $isPivot,
                'has_timestamps' => $this->hasTimestamps($contents),
                'has_soft_deletes' => $this->hasSoftDeletes($contents),
            ];

            if (! $hasTimestamps && $this->hasTimestamps($contents)) {
                $hasTimestamps = true;
            }

            if (! $hasSoftDeletes && $this->hasSoftDeletes($contents)) {
                $hasSoftDeletes = true;
            }

            foreach ($tableForeignKeys as $fk) {
                $foreignKeys[] = $fk;
            }
        }

        return [
            'tables' => $tables,
            'foreign_keys' => $foreignKeys,
            'pivot_tables' => $pivotTables,
            'has_timestamps' => $hasTimestamps,
            'has_soft_deletes' => $hasSoftDeletes,
        ];
    }

    private function detectTableName(string $contents): ?string
    {
        $patterns = [
            '/Schema::create\([\'"]([\w_]+)[\'"]/',
            '/Schema::table\([\'"]([\w_]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function detectColumns(string $contents): array
    {
        $columns = [];

        if (preg_match_all('/\$table->(\w+)\([\'"]([^\'"]+)[\'"]\)/', $contents, $matches)) {
            foreach ($matches[1] as $i => $type) {
                if (! in_array($type, ['id', 'timestamps', 'softDeletes', 'rememberToken', 'morphs', 'nullableMorphs', 'foreign', 'dropForeign', 'dropColumn', 'dropSoftDeletes', 'dropTimestamps'])) {
                    $columns[] = ['name' => $matches[2][$i], 'type' => $type];
                }
            }
        }

        return $columns;
    }

    private function detectForeignKeys(string $contents): array
    {
        $keys = [];

        if (preg_match_all('/\$table->foreign\([\'"]([\w_]+)[\'"]\)/', $contents, $matches)) {
            foreach ($matches[1] as $column) {
                $keys[] = ['column' => $column, 'type' => 'foreign'];
            }
        }

        // Also detect constrained()
        if (preg_match_all('/->constrained\(\)/', $contents, $matches)) {
            // constrained() without arguments uses naming convention
            for ($i = 0; $i < count($matches[0]); $i++) {
                $keys[] = ['column' => 'inferred', 'type' => 'foreign_constrained'];
            }
        }

        return $keys;
    }

    private function isPivotTable(string $tableName, array $columns, array $foreignKeys): bool
    {
        if (count($foreignKeys) >= 2) {
            return true;
        }

        // Convention-based: table has two foreign key columns ending in _id
        $columnNames = array_column($columns, 'name');
        $fkColumns = array_filter($columnNames, function ($name) {
            return str_ends_with($name, '_id');
        });

        return count($fkColumns) >= 2;
    }

    private function hasTimestamps(string $contents): bool
    {
        return (bool) preg_match('/\$table->timestamps\(\)/', $contents);
    }

    private function hasSoftDeletes(string $contents): bool
    {
        return (bool) preg_match('/\$table->softDeletes\(\)/', $contents);
    }
}