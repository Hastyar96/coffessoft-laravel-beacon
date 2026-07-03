<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Intelligence\CodeFixEngine;
use Illuminate\Console\Command;

class BeaconSuggestFixCommand extends Command
{
    protected $signature = 'beacon:suggest-fix
                            {--output= : Output directory (default: storage/app/beacon)}
                            {--format=both : Output format: md, json, both}
                            {--min-severity=info : Minimum severity level (info, warning, high)}';

    protected $description = 'Generate code fix suggestions from review output';

    public function __construct(
        private readonly ContextBuilder $builder,
        private readonly CodeFixEngine $fixEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->info(' 🔧 Beacon Suggest Fix — Code Improvement Suggestions');
        $this->line(' ──────────────────────────────────────────────────');
        $this->newLine();

        $this->warn(' Scanning project...');
        $context = $this->builder->build();
        $data = $context->all();
        $this->info(' ✓ Project scanned');

        $this->newLine();
        $this->warn(' Generating fix suggestions...');
        $result = $this->fixEngine->generate($data);
        $fixes = $result['fix_suggestions']['fixes'] ?? [];
        $this->info(' ✓ Suggestions generated');
        $this->newLine();

        $minSeverity = $this->option('min-severity');
        $severityLevels = ['info' => 0, 'warning' => 1, 'high' => 2, 'critical' => 3];
        $minLevel = $severityLevels[$minSeverity] ?? 0;
        $filtered = array_filter($fixes, fn($f) => ($severityLevels[$f['severity']] ?? 0) >= $minLevel);

        // Summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Suggestions', count($filtered)],
                ['Controller Splits', count(array_filter($filtered, fn($f) => $f['type'] === 'controller_split'))],
                ['Code Fixes', count(array_filter($filtered, fn($f) => $f['type'] !== 'controller_split'))],
            ]
        );
        $this->newLine();

        // Show suggestions
        foreach ($filtered as $fix) {
            $icon = match ($fix['severity']) {
                'critical' => '🔴', 'high' => '🟠',
                'warning' => '🟡', default => '🔵',
            };
            $this->line(" {$icon} [{$fix['severity']}] {$fix['title']}");
            $this->line("    Effort: {$fix['estimated_effort']} | Confidence: {$fix['confidence']}% | Action: {$fix['recommended_action']}");
            if (!empty($fix['new_files_suggested'])) {
                $this->line('    New files: ' . implode(', ', $fix['new_files_suggested']));
            }
        }

        if (empty($filtered)) {
            $this->info(' 🎉 No fix suggestions at this severity level!');
        }
        $this->newLine();

        // Export
        $outputDir = $this->option('output') ?? storage_path('app/beacon');
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        $format = $this->option('format');

        if ($format === 'md' || $format === 'both') {
            $mdPath = $outputDir . '/fix-suggestions.md';
            file_put_contents($mdPath, $result['fix_suggestions']['markdown'] ?? '');
            $this->line("   ✓ fix-suggestions.md");
        }
        if ($format === 'json' || $format === 'both') {
            $jsonPath = $outputDir . '/fix-suggestions.json';
            file_put_contents($jsonPath, json_encode(['fixes' => array_values($filtered)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line("   ✓ fix-suggestions.json");
        }

        return self::SUCCESS;
    }
}