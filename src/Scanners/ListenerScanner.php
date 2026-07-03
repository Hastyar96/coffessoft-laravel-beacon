<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanners;

use Coffesoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Listeners.
 *
 * TODO: Scan listeners and return metadata.
 */
class ListenerScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan listeners directory and return metadata.

        return [
            'listeners' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
