<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Cache;

/**
 * Incremental scan cache for large Laravel projects.
 *
 * Stores file hashes in .cache/beacon/manifest.json.
 * Only re-scans files whose content hash has changed.
 * On first run, creates baseline silently — no false positives.
 */
class ScanCache
{
    private string $cacheDir;

    private string $manifestPath;

    private string $snapshotPath;

    /** @var array<string, string> file_path => md5_hash */
    private array $manifest = [];

    private ?int $previousScanTime = null;

    public function __construct()
    {
        $this->cacheDir = base_path('.cache/beacon');
        $this->manifestPath = $this->cacheDir . '/manifest.json';
        $this->snapshotPath = $this->cacheDir . '/snapshot.json';
        $this->loadManifest();
    }

    /**
     * Detect which files have changed since the last scan.
     *
     * On first scan, all files are returned as "unchanged" (baseline creation).
     * Subsequent scans only return truly changed files.
     *
     * @param array<string> $files List of file paths to check
     * @return array{array<string>, array<string>} [changed_files, unchanged_files]
     */
    public function detectChanges(array $files): array
    {
        $changed = [];
        $unchanged = [];
        $isFirstScan = $this->isFirstScan();

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $hash = $this->hashFile($file);
            $relativePath = $this->relativePath($file);

            if ($isFirstScan) {
                // First scan: build baseline, mark everything as unchanged
                $this->manifest[$relativePath] = $hash;
                $unchanged[] = $file;
            } else {
                if (!isset($this->manifest[$relativePath])) {
                    $changed[] = $file; // New file
                } elseif ($this->manifest[$relativePath] !== $hash) {
                    $changed[] = $file; // Modified file
                } else {
                    $unchanged[] = $file; // Unchanged file
                }
            }
        }

        if ($isFirstScan) {
            $this->saveManifest();
            $this->recordSnapshot();
        }

        return [$changed, $unchanged];
    }

    /**
     * Record file hashes after a scan.
     */
    public function recordScan(array $files): void
    {
        foreach ($files as $file) {
            if (!file_exists($file)) continue;
            $relativePath = $this->relativePath($file);
            $this->manifest[$relativePath] = $this->hashFile($file);
        }

        $this->saveManifest();
        $this->recordSnapshot();
    }

    /**
     * Get cached scan results for unchanged files.
     *
     * @param array<string> $files Unchanged files
     * @return array<string, mixed>
     */
    public function getCachedForFiles(array $files): array
    {
        $data = [];

        foreach ($files as $file) {
            $relativePath = $this->relativePath($file);
            $cachePath = $this->cacheDir . '/files/' . $this->pathToCacheKey($relativePath) . '.json';

            if (file_exists($cachePath)) {
                $cached = json_decode(file_get_contents($cachePath), true);
                if (is_array($cached)) {
                    $data[] = $cached;
                }
            }
        }

        return $data;
    }

    /**
     * Cache scan results for a file.
     */
    public function cacheFileData(string $filePath, array $data): void
    {
        $relativePath = $this->relativePath($filePath);
        $cacheFile = $this->cacheDir . '/files/' . $this->pathToCacheKey($relativePath) . '.json';

        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode($data));

        $this->manifest[$relativePath] = $this->hashFile($filePath);
        $this->saveManifest();
    }

    /**
     * Get all cached file keys.
     *
     * @return array<string>
     */
    public function getCachedPaths(): array
    {
        return array_keys($this->manifest);
    }

    /**
     * Check if this is the first scan (no cache exists).
     */
    public function isFirstScan(): bool
    {
        return empty($this->manifest) && !file_exists($this->manifestPath);
    }

    /**
     * Get the timestamp of the previous scan.
     */
    public function getPreviousScanTime(): ?int
    {
        return $this->previousScanTime;
    }

    /**
     * Get cache statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $cacheSize = 0;
        $cacheFiles = 0;

        $filesDir = $this->cacheDir . '/files';
        if (is_dir($filesDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filesDir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $cacheSize += $file->getSize();
                    $cacheFiles++;
                }
            }
        }

        return [
            'cached_files' => count($this->manifest),
            'cache_file_count' => $cacheFiles,
            'cache_size_bytes' => $cacheSize,
            'cache_dir' => $this->cacheDir,
            'first_scan' => $this->isFirstScan(),
            'previous_scan' => $this->previousScanTime,
        ];
    }

    /**
     * Clear the entire cache.
     */
    public function clear(): void
    {
        if (is_dir($this->cacheDir)) {
            $this->removeDirectory($this->cacheDir);
        }
        $this->manifest = [];
        $this->previousScanTime = null;
    }

    private function loadManifest(): void
    {
        if (file_exists($this->manifestPath)) {
            $data = json_decode(file_get_contents($this->manifestPath), true);
            if (is_array($data)) {
                // Handle both flat format and versioned format
                if (isset($data['files'])) {
                    $this->manifest = $data['files'];
                    $this->previousScanTime = $data['scanned_at'] ?? null;
                } else {
                    $this->manifest = $data;
                    $this->previousScanTime = $this->loadSnapshotTime();
                }
            }
        }
    }

    private function saveManifest(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $data = [
            'version' => '2',
            'scanned_at' => time(),
            'files' => $this->manifest,
        ];

        file_put_contents($this->manifestPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function recordSnapshot(): void
    {
        $snapshot = [
            'scanned_at' => time(),
            'file_count' => count($this->manifest),
            'files' => array_keys($this->manifest),
        ];
        file_put_contents($this->snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT));
        $this->previousScanTime = time();
    }

    private function loadSnapshotTime(): ?int
    {
        if (file_exists($this->snapshotPath)) {
            $snapshot = json_decode(file_get_contents($this->snapshotPath), true);
            return $snapshot['scanned_at'] ?? null;
        }
        return null;
    }

    private function hashFile(string $path): string
    {
        return md5_file($path) ?: '';
    }

    private function relativePath(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base) + 1);
        }
        return $path;
    }

    private function pathToCacheKey(string $path): string
    {
        return str_replace(['/', '\\', '.'], ['_', '_', '_'], $path);
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}