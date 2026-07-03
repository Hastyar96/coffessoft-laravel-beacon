<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Exporter\JsonExporter;
use Coffesoft\LaravelBeacon\Exporter\MarkdownExporter;
use Illuminate\Console\Command;

/**
 * Artisan command to export the context to file.
 *
 * Usage:
 *   php artisan beacon:export --format=md
 *   php artisan beacon:export --format=json
 */
class BeaconExportCommand extends Command
{
    protected $signature = 'beacon:export
        {--format=md : Output format: md or json}
        {--output= : Custom output path (optional)}';

    protected $description = 'Export project context to file (md or json)';

    public function __construct(
        private readonly ContextBuilder $contextBuilder,
        private readonly MarkdownExporter $markdownExporter,
        private readonly JsonExporter $jsonExporter,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $format = $this->option('format');
        $customOutput = $this->option('output');

        $this->components->info('Laravel Beacon — Export');

        $context = $this->contextBuilder->build();
        $outputDir = $customOutput ? dirname($customOutput) : config('beacon.output_directory', 'storage/app/beacon');
        $outputDir = base_path($outputDir);

        $outputPath = match ($format) {
            'json' => $customOutput ?? $outputDir . '/context.json',
            default => $customOutput ?? $outputDir . '/context.md',
        };

        $this->components->task('Exporting to ' . $format, function () use ($format, $context, $outputPath) {
            match ($format) {
                'json' => $this->jsonExporter->export($context, $outputPath),
                default => $this->markdownExporter->export($context, $outputPath),
            };
        });

        $this->line($outputPath);
        $this->components->success('Context exported successfully.');

        return self::SUCCESS;
    }
}