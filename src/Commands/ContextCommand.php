<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Commands;

use Coffesoft\LaravelBeacon\Analyzers\AnalyzerManager;
use Coffesoft\LaravelBeacon\Analyzers\EnvironmentAnalyzer;
use Coffesoft\LaravelBeacon\Context\ContextEngine;
use Coffesoft\LaravelBeacon\Exporters\ExporterManager;
use Coffesoft\LaravelBeacon\Exporters\MarkdownExporter;
use Coffesoft\LaravelBeacon\Scanners\ComposerScanner;
use Coffesoft\LaravelBeacon\Scanners\ControllerScanner;
use Coffesoft\LaravelBeacon\Scanners\FrameworkScanner;
use Coffesoft\LaravelBeacon\Scanners\LaravelScanner;
use Coffesoft\LaravelBeacon\Scanners\MigrationScanner;
use Coffesoft\LaravelBeacon\Scanners\ModelScanner;
use Coffesoft\LaravelBeacon\Scanners\RouteScanner;
use Coffesoft\LaravelBeacon\Scanners\ScannerManager;
use Illuminate\Console\Command;

/**
 * Artisan command to generate AI context for the Laravel project.
 */
class ContextCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'beacon:context';

    /**
     * The console command description.
     */
    protected $description = 'Generate AI context for the Laravel project.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Laravel Beacon');
        $this->components->twoColumnDetail('Scanning project...', '<fg=yellow>running</>');

        $scannerManager = new ScannerManager([
            new FrameworkScanner(),
            new LaravelScanner(),
            new ComposerScanner(),
            new ModelScanner(),
            new ControllerScanner(),
            new RouteScanner(),
            new MigrationScanner(),
        ]);

        $this->components->twoColumnDetail('Running analyzers...', '<fg=yellow>running</>');

        $analyzerManager = new AnalyzerManager([
            new EnvironmentAnalyzer(),
        ]);

        $this->components->twoColumnDetail('Exporting context...', '<fg=yellow>running</>');

        $exporterManager = new ExporterManager([
            new MarkdownExporter(),
        ]);

        $engine = new ContextEngine(
            $scannerManager,
            $analyzerManager,
            $exporterManager,
        );

        $engine->generate();

        $this->components->twoColumnDetail('Done.', '<fg=green>done</>');
        $this->components->success('Context generated successfully.');
        $this->line('storage/app/beacon/context.md');

        return self::SUCCESS;
    }
}