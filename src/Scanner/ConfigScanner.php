<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

/**
 * Scans Laravel configuration to detect active drivers and settings.
 */
class ConfigScanner
{
    /**
     * Scan configuration and return driver metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        return [
            'configuration' => [
                'queue' => [
                    'driver' => config('queue.default'),
                    'connections' => array_keys(config('queue.connections', [])),
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'stores' => array_keys(config('cache.stores', [])),
                ],
                'session' => [
                    'driver' => config('session.driver'),
                    'lifetime' => config('session.lifetime'),
                ],
                'filesystem' => [
                    'default_disk' => config('filesystems.default'),
                    'disks' => array_keys(config('filesystems.disks', [])),
                ],
                'broadcasting' => [
                    'driver' => config('broadcasting.default'),
                    'connections' => array_keys(config('broadcasting.connections', [])),
                ],
                'mail' => [
                    'driver' => config('mail.default'),
                    'mailers' => array_keys(config('mail.mailers', [])),
                ],
                'app' => [
                    'name' => config('app.name'),
                    'env' => config('app.env'),
                    'debug' => config('app.debug'),
                    'url' => config('app.url'),
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale'),
                ],
            ],
        ];
    }
}