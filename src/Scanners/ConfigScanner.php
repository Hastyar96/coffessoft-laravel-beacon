<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanners;

use Coffesoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Config_files.
 *
 * TODO: Scan config_files and return metadata.
 */
class ConfigScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan config_files directory and return metadata.

        return [
            'config_files' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
