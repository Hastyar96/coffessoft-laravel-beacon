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
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beacon:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan the Laravel project and analyze its structure';

    /**
     * @var ContextBuilder
     */
    private ContextBuilder $contextBuilder;

    public function __construct(ContextBuilder $contextBuilder)
    {
        parent::__construct();
        $this->contextBuilder = $contextBuilder;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Laravel Beacon — Scan');
        $this->line('');

        $context = $this->contextBuilder->build();

        $this->line(' Models:      ' . $context->get('models.count', 0));
        $this->line(' Controllers: ' . $context->get('controllers.count', 0));
        $this->line(' Routes:      ' . $context->get('routes.count', 0));
        $this->line(' Migrations:  ' . $context->get('migrations.count', 0));
        $this->line(' Modules:     ' . $context->get('modules.total_modules', 0));
        $this->line('');

        $this->info('Scan complete. Run php artisan beacon:export to generate context files.');

        return 0;
    }
}