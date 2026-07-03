<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Exporter;

use Coffesoft\LaravelBeacon\Context\Context;
use Illuminate\Support\Facades\File;

/**
 * Exports Context to structured JSON format.
 */
class JsonExporter
{
    /**
     * Export context to JSON file.
     */
    public function export(Context $context, string $outputPath): string
    {
        $json = json_encode($context->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $json);

        return $outputPath;
    }
}