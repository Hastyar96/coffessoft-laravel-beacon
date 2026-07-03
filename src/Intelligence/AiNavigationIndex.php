<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates ai-navigation.json — allows AI to instantly answer where
 * authentication, payments, admin, API, business logic, validation,
 * uploads, notifications, queues, schedules, and policies live.
 */
class AiNavigationIndex
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $index = [];

        // Authentication
        $index['authentication'] = $this->locateAuthentication($data);

        // Payments
        $index['payments'] = $this->locatePayments($data);

        // Admin
        $index['admin'] = $this->locateAdmin($data);

        // API
        $index['api'] = $this->locateApi($data);

        // Business Logic
        $index['business_logic'] = $this->locateBusinessLogic($data);

        // Validation
        $index['validation'] = $this->locateValidation($data);

        // File Uploads / Storage
        $index['uploads'] = $this->locateUploads($data);

        // Notifications
        $index['notifications'] = $this->locateNotifications($data);

        // Queues
        $index['queues'] = $this->locateQueues($data);

        // Scheduled Tasks
        $index['schedules'] = $this->locateSchedules($data);

        // Policies / Authorization
        $index['policies'] = $this->locatePolicies($data);

        // Events
        $index['events'] = $this->locateEvents($data);

        // Database
        $index['database'] = $this->locateDatabase($data);

        // Tests
        $index['tests'] = $this->locateTests($data);

        // Frontend / Views
        $index['frontend'] = $this->locateFrontend($data);

        return [
            'ai_navigation' => [
                'index' => $index,
                'confidence' => 85,
            ],
        ];
    }

    private function locateAuthentication(array $data): array
    {
        $result = [
            'description' => 'User authentication and authorization flow',
            'files' => [],
            'routes' => [],
            'controllers' => [],
        ];

        // Auth routes
        foreach ($data['routes']['items'] ?? [] as $route) {
            $uri = $route['uri'] ?? '';
            if (str_contains($uri, 'login') || str_contains($uri, 'register') || str_contains($uri, 'password') ||
                str_contains($uri, 'logout') || str_contains($uri, 'verify-email') || str_contains($uri, 'forgot')) {
                $result['routes'][] = $uri;
                if ($route['action']) {
                    $parts = explode('@', $route['action']);
                    $ctrlName = substr(strrchr($parts[0], '\\') ?: $parts[0], 1);
                    $result['controllers'][] = $ctrlName;
                }
            }
        }

        // Auth controllers
        $authCtrlPath = app_path('Http/Controllers/Auth');
        if (is_dir($authCtrlPath)) {
            $result['directories'][] = 'app/Http/Controllers/Auth/';
        }

        // Sanctum/Passport/JWT
        $authConfig = $data['api']['authentication'] ?? [];
        if ($authConfig['sanctum'] ?? false) $result['packages'][] = 'laravel/sanctum';
        if ($authConfig['passport'] ?? false) $result['packages'][] = 'laravel/passport';
        if ($authConfig['jwt'] ?? false) $result['packages'][] = 'tymon/jwt-auth';

        $result['config'] = ['config/auth.php'];
        $result['providers'] = $authConfig['providers'] ?? [];

        return $result;
    }

    private function locatePayments(array $data): array
    {
        $result = ['description' => 'Payment processing', 'files' => [], 'packages' => []];

        // Check packages
        foreach ($data['packages']['items'] ?? [] as $pkg) {
            if ($pkg['category'] === 'payments') {
                $result['packages'][] = $pkg['name'];
                $result['description'] = "Payment processing via {$pkg['name']}";
            }
        }

        // Check services for payment-related names
        foreach ($data['services']['items'] ?? [] as $svc) {
            $name = $svc['name'];
            if (str_contains($name, 'Payment') || str_contains($name, 'Invoice') || str_contains($name, 'Billing')) {
                $result['files'][] = $svc['path'];
            }
        }

        // Check for payment controllers
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if (str_contains($ctrl['name'], 'Payment') || str_contains($ctrl['name'], 'Invoice')) {
                $result['controllers'][] = $ctrl['name'];
            }
        }

        return $result;
    }

    private function locateAdmin(array $data): array
    {
        $result = ['description' => 'Administrative interface', 'files' => [], 'routes' => []];

        foreach ($data['routes']['items'] ?? [] as $route) {
            if (str_starts_with($route['uri'] ?? '', 'admin')) {
                $result['routes'][] = $route['uri'];
            }
        }

        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if (($ctrl['group'] ?? '') === 'Admin' || str_contains($ctrl['namespace'] ?? '', '\\Admin\\')) {
                $result['files'][] = $ctrl['path'];
            }
        }

        return $result;
    }

    private function locateApi(array $data): array
    {
        $result = ['description' => 'REST API endpoints', 'files' => [], 'routes' => []];

        foreach ($data['routes']['items'] ?? [] as $route) {
            if (str_starts_with($route['uri'] ?? '', 'api')) {
                $result['routes'][] = $route['uri'];
            }
        }

        foreach ($data['api']['controllers'] ?? [] as $ctrl) {
            $result['files'][] = $ctrl['path'] ?? '';
        }

        foreach ($data['api']['resources'] ?? [] as $res) {
            $result['resources'][] = $res['path'] ?? '';
        }

        $result['auth'] = $data['api']['authentication'] ?? [];

        return $result;
    }

    private function locateBusinessLogic(array $data): array
    {
        $result = ['description' => 'Core business logic layer', 'files' => [], 'services' => []];

        foreach ($data['services']['items'] ?? [] as $svc) {
            $result['services'][] = [
                'name' => $svc['name'],
                'path' => $svc['path'],
                'type' => $svc['type'] ?? 'service',
            ];
            $result['files'][] = $svc['path'];
        }

        foreach ($data['repositories']['items'] ?? [] as $repo) {
            $result['repositories'][] = [
                'name' => $repo['name'],
                'path' => $repo['path'],
            ];
            $result['files'][] = $repo['path'];
        }

        // Actions
        $actionsPath = app_path('Actions');
        if (is_dir($actionsPath)) {
            $result['directories'][] = 'app/Actions/';
        }

        return $result;
    }

    private function locateValidation(array $data): array
    {
        $result = ['description' => 'Request validation layer', 'files' => []];

        foreach ($data['form_requests']['items'] ?? [] as $req) {
            $result['files'][] = $req['path'];
        }

        $result['count'] = $data['form_requests']['count'] ?? 0;

        // Check for custom validation rules
        $rulesPath = app_path('Rules');
        if (is_dir($rulesPath)) {
            $result['directories'][] = 'app/Rules/';
        }

        return $result;
    }

    private function locateUploads(array $data): array
    {
        $result = ['description' => 'File upload and storage handling', 'files' => [], 'disks' => []];

        foreach ($data['storage']['disks'] ?? [] as $disk) {
            if ($disk['driver'] !== 'local') {
                $result['disks'][] = $disk;
            }
        }

        foreach ($data['storage']['upload_paths'] ?? [] as $upload) {
            $result['files'][] = $upload['file'];
        }

        foreach ($data['storage']['public_links'] ?? [] as $link) {
            $result['symlinks'][] = $link;
        }

        return $result;
    }

    private function locateNotifications(array $data): array
    {
        $result = ['description' => 'Notification system', 'files' => [], 'channels' => []];

        foreach ($data['notifications']['items'] ?? [] as $notif) {
            $result['files'][] = $notif['path'];
            foreach ($notif['channels'] ?? [] as $ch) {
                $result['channels'][$ch] = ($result['channels'][$ch] ?? 0) + 1;
            }
        }

        return $result;
    }

    private function locateQueues(array $data): array
    {
        $result = ['description' => 'Queue and job processing', 'files' => [], 'jobs' => []];

        foreach ($data['jobs']['items'] ?? [] as $job) {
            $result['jobs'][] = [
                'name' => $job['name'],
                'path' => $job['path'],
                'queued' => $job['queued'],
            ];
            $result['files'][] = $job['path'];
        }

        $result['config'] = $data['queue'] ?? [];

        return $result;
    }

    private function locateSchedules(array $data): array
    {
        $result = ['description' => 'Scheduled/cron tasks', 'files' => [], 'commands' => []];

        // Check Kernel
        $kernelPath = 'app/Console/Kernel.php';
        if (file_exists(app_path('Console/Kernel.php'))) {
            $result['files'][] = $kernelPath;
        }

        // Commands
        $commandsPath = app_path('Console/Commands');
        if (is_dir($commandsPath)) {
            $result['directories'][] = 'app/Console/Commands/';
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandsPath));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $result['commands'][] = $file->getFilename();
                }
            }
        }

        return $result;
    }

    private function locatePolicies(array $data): array
    {
        $result = ['description' => 'Authorization policies', 'files' => [], 'policies' => []];

        foreach ($data['policies']['items'] ?? [] as $policy) {
            $result['policies'][] = [
                'name' => $policy['name'],
                'model' => $policy['model'],
                'abilities' => $policy['abilities'] ?? [],
            ];
            $result['files'][] = $policy['path'];
        }

        return $result;
    }

    private function locateEvents(array $data): array
    {
        $result = ['description' => 'Event system', 'files' => [], 'events' => []];

        foreach ($data['events']['items'] ?? [] as $event) {
            $result['events'][] = $event['name'];
            $result['files'][] = $event['path'];
        }

        foreach ($data['events']['listeners'] ?? [] as $listener) {
            $result['listeners'][] = $listener['name'];
            $result['files'][] = $listener['path'];
        }

        return $result;
    }

    private function locateDatabase(array $data): array
    {
        $result = ['description' => 'Database schema and access', 'files' => [], 'tables' => []];

        foreach ($data['database']['tables'] ?? [] as $table) {
            $result['tables'][] = $table['name'];
        }

        $migrationsPath = database_path('migrations');
        if (is_dir($migrationsPath)) {
            $result['directories'][] = 'database/migrations/';
        }

        $factoriesPath = database_path('factories');
        if (is_dir($factoriesPath)) {
            $result['directories'][] = 'database/factories/';
        }

        $seedersPath = database_path('seeders');
        if (is_dir($seedersPath)) {
            $result['directories'][] = 'database/seeders/';
        }

        return $result;
    }

    private function locateTests(array $data): array
    {
        $result = ['description' => 'Test files', 'directories' => []];

        $testPath = base_path('tests');
        if (is_dir($testPath)) {
            $result['directories'][] = 'tests/';
            $result['directories'][] = 'tests/Feature/';
            $result['directories'][] = 'tests/Unit/';
        }

        return $result;
    }

    private function locateFrontend(array $data): array
    {
        $result = ['description' => 'Frontend views and assets', 'files' => [], 'directories' => []];

        $viewsPath = resource_path('views');
        if (is_dir($viewsPath)) {
            $result['directories'][] = 'resources/views/';
        }

        foreach ($data['blade']['layouts'] ?? [] as $layout) {
            $result['layouts'][] = $layout['name'];
        }

        foreach ($data['blade']['components'] ?? [] as $comp) {
            $result['blade_components'][] = $comp['name'];
        }

        foreach ($data['livewire']['components'] ?? [] as $lw) {
            $result['livewire_components'][] = $lw['name'];
        }

        // Frontend assets
        $cssPath = resource_path('css');
        if (is_dir($cssPath)) $result['directories'][] = 'resources/css/';
        $jsPath = resource_path('js');
        if (is_dir($jsPath)) $result['directories'][] = 'resources/js/';

        return $result;
    }
}