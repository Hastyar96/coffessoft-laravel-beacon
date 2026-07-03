<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Illuminate\Console\Command;

/**
 * Artisan command to scan the Laravel project.
 *
 * Usage: php artisan beacon:scan
 */
class BeaconScanCommand extends Command
{
    protected $signature = 'beacon:scan';
    protected $description = 'Scan the Laravel project and analyze its structure';

    public function __construct(
        private readonly ContextBuilder $contextBuilder,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Laravel Beacon — Scan');

        $context = $this->contextBuilder->build();

        $this->components->twoColumnDetail('Models', (string) $context->get('models.count', 0));
        $this->components->twoColumnDetail('Controllers', (string) $context->get('controllers.count', 0));
        $this->components->twoColumnDetail('Routes', (string) $context->get('routes.count', 0));
        $this->components->twoColumnDetail('Migrations', (string) $context->get('migrations.count', 0));
        $this->components->twoColumnDetail('Modules', (string) $context->get('modules.total_modules', 0));

        $this->components->success('Scan complete. Run php artisan beacon:export to generate context files.');

        return self::SUCCESS;
    }
}