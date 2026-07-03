<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Builder;

use Coffesoft\LaravelBeacon\Context\Context;
use Coffesoft\LaravelBeacon\Intelligence\ModuleDetector;
use Coffesoft\LaravelBeacon\Scanner\ConfigScanner;
use Coffesoft\LaravelBeacon\Scanner\ControllerScanner;
use Coffesoft\LaravelBeacon\Scanner\DatabaseScanner;
use Coffesoft\LaravelBeacon\Scanner\MigrationScanner;
use Coffesoft\LaravelBeacon\Scanner\ModelScanner;
use Coffesoft\LaravelBeacon\Scanner\RouteScanner;
use Coffesoft\LaravelBeacon\Scanner\StatisticsScanner;

/**
 * Orchestrates all scanners and builds a clean Context object.
 */
class ContextBuilder
{
    public function __construct(
        private readonly ModelScanner $modelScanner,
        private readonly ControllerScanner $controllerScanner,
        private readonly RouteScanner $routeScanner,
        private readonly MigrationScanner $migrationScanner,
        private readonly DatabaseScanner $databaseScanner,
        private readonly StatisticsScanner $statisticsScanner,
        private readonly ConfigScanner $configScanner,
        private readonly ModuleDetector $moduleDetector,
    ) {
    }

    /**
     * Build a fully populated Context object.
     */
    public function build(): Context
    {
        $context = new Context();

        $context->merge([
            'framework' => [
                'name' => 'Laravel',
                'version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
        ]);

        $context->merge($this->modelScanner->scan());
        $context->merge($this->controllerScanner->scan());
        $context->merge($this->routeScanner->scan());
        $context->merge($this->migrationScanner->scan());
        $context->merge($this->databaseScanner->scan());
        $context->merge($this->statisticsScanner->scan());
        $context->merge($this->configScanner->scan());

        $modules = $this->moduleDetector->detect($context->all());
        $context->merge($modules);

        $context->set('generated_at', now()->toIso8601String());

        return $context;
    }
}