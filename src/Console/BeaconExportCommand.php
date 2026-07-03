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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beacon:export
        {--format=md : Output format: md or json}
        {--output= : Custom output path (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export project context to file (md or json)';

    /**
     * @var ContextBuilder
     */
    private ContextBuilder $contextBuilder;

    /**
     * @var MarkdownExporter
     */
    private MarkdownExporter $markdownExporter;

    /**
     * @var JsonExporter
     */
    private JsonExporter $jsonExporter;

    public function __construct(
        ContextBuilder $contextBuilder,
        MarkdownExporter $markdownExporter,
        JsonExporter $jsonExporter
    ) {
        parent::__construct();
        $this->contextBuilder = $contextBuilder;
        $this->markdownExporter = $markdownExporter;
        $this->jsonExporter = $jsonExporter;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $format = $this->option('format');
        $customOutput = $this->option('output');

        $this->info('Laravel Beacon — Export');
        $this->line('');

        $context = $this->contextBuilder->build();

        $outputDir = $customOutput ? dirname($customOutput) : config('beacon.output_directory', 'storage/app/beacon');
        $outputDir = base_path($outputDir);

        if ($format === 'json') {
            $outputPath = $customOutput ?? $outputDir . '/context.json';
            $this->jsonExporter->export($context, $outputPath);
        } else {
            $outputPath = $customOutput ?? $outputDir . '/context.md';
            $this->markdownExporter->export($context, $outputPath);
        }

        $this->line(' Exported to: ' . $outputPath);
        $this->info('Context exported successfully.');

        return 0;
    }
}