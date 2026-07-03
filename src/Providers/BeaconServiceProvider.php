<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Providers;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Console\BeaconExportCommand;
use Coffesoft\LaravelBeacon\Console\BeaconScanCommand;
use Coffesoft\LaravelBeacon\Exporter\JsonExporter;
use Coffesoft\LaravelBeacon\Exporter\MarkdownExporter;
use Coffesoft\LaravelBeacon\Intelligence\ModuleDetector;
use Coffesoft\LaravelBeacon\Scanner\ConfigScanner;
use Coffesoft\LaravelBeacon\Scanner\ControllerScanner;
use Coffesoft\LaravelBeacon\Scanner\DatabaseScanner;
use Coffesoft\LaravelBeacon\Scanner\MigrationScanner;
use Coffesoft\LaravelBeacon\Scanner\ModelScanner;
use Coffesoft\LaravelBeacon\Scanner\RouteScanner;
use Coffesoft\LaravelBeacon\Scanner\StatisticsScanner;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Laravel Beacon service provider.
 */
class BeaconServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beacon')
            ->hasConfigFile('beacon')
            ->hasCommand(BeaconScanCommand::class)
            ->hasCommand(BeaconExportCommand::class);
    }

    /**
     * Register package bindings.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(ModelScanner::class);
        $this->app->singleton(ControllerScanner::class);
        $this->app->singleton(RouteScanner::class);
        $this->app->singleton(MigrationScanner::class);
        $this->app->singleton(DatabaseScanner::class);
        $this->app->singleton(StatisticsScanner::class);
        $this->app->singleton(ConfigScanner::class);
        $this->app->singleton(ModuleDetector::class);

        $this->app->singleton(ContextBuilder::class, function ($app) {
            return new ContextBuilder(
                $app->make(ModelScanner::class),
                $app->make(ControllerScanner::class),
                $app->make(RouteScanner::class),
                $app->make(MigrationScanner::class),
                $app->make(DatabaseScanner::class),
                $app->make(StatisticsScanner::class),
                $app->make(ConfigScanner::class),
                $app->make(ModuleDetector::class),
            );
        });

        $this->app->singleton(MarkdownExporter::class);
        $this->app->singleton(JsonExporter::class);
    }
}