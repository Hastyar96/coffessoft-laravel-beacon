<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Intelligence\AiRefactorPlanner;
use Illuminate\Console\Command;

class BeaconFixPlanCommand extends Command
{
    protected $signature = 'beacon:fix-plan
                            {--output= : Output directory (default: storage/app/beacon)}
                            {--format=both : Output format: md, json, both}';

    protected $description = 'Generate a full AI refactoring plan with priorities and execution order';

    public function __construct(
        private readonly ContextBuilder $builder,
        private readonly AiRefactorPlanner $planner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->info(' 📋 Beacon Fix Plan — AI Refactoring Plan');
        $this->line(' ─────────────────────────────────────────');
        $this->newLine();

        $this->warn(' Scanning project...');
        $context = $this->builder->build();
        $data = $context->all();
        $this->info(' ✓ Project scanned');

        $this->newLine();
        $this->warn(' Analyzing and prioritizing...');
        $result = $this->planner->plan($data);
        $plan = $result['refactor_plan'];
        $this->info(' ✓ Plan generated');
        $this->newLine();

        // Summary
        $this->table(
            ['Priority', 'Count'],
            [
                ['🔴 Critical/High', $plan['risks']['high']],
                ['🟡 Medium', $plan['risks']['medium']],
                ['🔵 Low', $plan['risks']['low']],
            ]
        );
        $this->newLine();

        // Show execution plan
        foreach ($plan['execution_plan'] as $step) {
            $icon = match ($step['phase']) { 1 => '🔴', 2 => '🟡', default => '🔵' };
            $this->line(" {$icon} Step {$step['step']} ({$step['phase_name']}, {$step['effort']}): {$step['title']}");
        }
        $this->newLine();

        // Export
        $outputDir = $this->option('output') ?? storage_path('app/beacon');
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        $format = $this->option('format');

        if ($format === 'md' || $format === 'both') {
            $mdPath = $outputDir . '/refactor-plan.md';
            file_put_contents($mdPath, $plan['markdown'] ?? '');
            $this->line("   ✓ refactor-plan.md");
        }
        if ($format === 'json' || $format === 'both') {
            $jsonPath = $outputDir . '/refactor-plan.json';
            file_put_contents($jsonPath, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line("   ✓ refactor-plan.json");
        }

        $this->newLine();
        $this->info(' ✅ Plan ready!');

        return self::SUCCESS;
    }
}