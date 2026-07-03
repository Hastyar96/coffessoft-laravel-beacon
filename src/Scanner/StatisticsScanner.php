<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Illuminate\Support\Facades\File;

/**
 * Scans the project to count various Laravel components:
 * models, controllers, routes, migrations, seeders, factories, etc.
 */
class StatisticsScanner
{
    /**
     * Scan project directories and count components.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $stats = [];

        // Core directories
        $stats['models'] = $this->countPhpFiles(app_path('Models'));
        $stats['controllers'] = $this->countPhpFiles(app_path('Http/Controllers'));
        $stats['migrations'] = $this->countPhpFiles(database_path('migrations'));
        $stats['seeders'] = $this->countPhpFiles(database_path('seeders'));
        $stats['factories'] = $this->countPhpFiles(database_path('factories'));

        // Laravel service classes
        $stats['policies'] = $this->countPhpFiles(app_path('Policies'));
        $stats['events'] = $this->countPhpFiles(app_path('Events'));
        $stats['listeners'] = $this->countPhpFiles(app_path('Listeners'));
        $stats['jobs'] = $this->countPhpFiles(app_path('Jobs'));
        $stats['notifications'] = $this->countPhpFiles(app_path('Notifications'));
        $stats['mail'] = $this->countPhpFiles(app_path('Mail'));

        // Console commands
        $stats['commands'] = $this->countPhpFiles(app_path('Console/Commands'));

        // PHP 8+ Enums
        $stats['enums'] = $this->countPhpFiles(app_path('Enums'));

        // Traits
        $stats['traits'] = $this->countPhpFiles(app_path('Traits'));

        // Middleware
        $stats['middleware'] = $this->countPhpFiles(app_path('Http/Middleware'));

        // Requests
        $stats['requests'] = $this->countPhpFiles(app_path('Http/Requests'));

        // Providers
        $stats['providers'] = $this->countPhpFiles(app_path('Providers'));

        return [
            'statistics' => $stats,
        ];
    }

    private function countPhpFiles(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $count = 0;
        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $count++;
            }
        }

        return $count;
    }
}