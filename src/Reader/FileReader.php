<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Reader;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Stream-based file reader for efficient source code scanning.
 * Uses ONLY native PHP functions - no Laravel facades, no container resolution.
 * Reads files only when needed, keeps memory usage low.
 *
 * All returned "file objects" are plain arrays: ['pathname' => string, 'relative_path' => string]
 * No SplFileInfo, no Symfony Finder objects.
 */
class FileReader
{
    /**
     * Get all PHP files from a directory recursively.
     *
     * Each entry is ['pathname' => '/absolute/path', 'relative_path' => 'relative/path', 'filename' => 'file.php']
     *
     * @return array<int, array<string, string>>
     */
    public function getPhpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $result = [];
        $basePath = rtrim(realpath($path), '/');

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $splFileInfo) {
                if ($splFileInfo->isFile() && $splFileInfo->getExtension() === 'php') {
                    $pathname = $splFileInfo->getPathname();
                    $filename = $splFileInfo->getFilename();
                    $relativePath = str_replace($basePath . '/', '', $pathname);
                    $result[] = [
                        'pathname' => $pathname,
                        'relative_path' => $relativePath,
                        'filename' => $filename,
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $result;
    }

    /**
     * Get PHP files from a directory (non-recursive, single level).
     *
     * @return array<int, array<string, string>>
     */
    public function getPhpFilesFlat(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $result = [];

        try {
            $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $splFileInfo) {
                if ($splFileInfo->isFile() && $splFileInfo->getExtension() === 'php') {
                    $pathname = $splFileInfo->getPathname();
                    $filename = $splFileInfo->getFilename();
                    $result[] = [
                        'pathname' => $pathname,
                        'relative_path' => $filename,
                        'filename' => $filename,
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $result;
    }

    /**
     * Read file contents safely.
     */
    public function read(string $path): string
    {
        if (! file_exists($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return $contents !== false ? $contents : '';
    }

    /**
     * Extract class name from PHP contents.
     */
    public function extractClassName(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract namespace from PHP contents.
     */
    public function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract use statements.
     */
    public function extractUses(string $contents): array
    {
        $uses = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $contents, $matches)) {
            foreach ($matches[1] as $use) {
                $uses[] = trim($use);
            }
        }
        return $uses;
    }

    /**
     * Extract public methods.
     */
    public function extractPublicMethods(string $contents): array
    {
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $contents, $matches);
        $methods = [];
        foreach ($matches[1] as $m) {
            if (! in_array($m, ['__construct', '__invoke', '__call'])) {
                $methods[] = $m;
            }
        }
        return $methods;
    }

    /**
     * Extract constructor parameters (type-hinted dependencies).
     */
    public function extractConstructorParams(string $contents): array
    {
        $params = [];

        if (preg_match('/function\s+__construct\s*\(([^)]*)\)/s', $contents, $matches)) {
            $args = explode(',', $matches[1]);
            foreach ($args as $arg) {
                $arg = trim($arg);
                if ($arg === '') continue;

                if (preg_match('/(\w+(?:\\\\\w+)*)\s+\$(\w+)/', $arg, $m)) {
                    $params[] = [
                        'type' => $m[1],
                        'name' => $m[2],
                    ];
                }
            }
        }

        return $params;
    }
}