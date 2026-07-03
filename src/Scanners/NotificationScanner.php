<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanners;

use Coffesoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Notifications.
 *
 * TODO: Scan notifications and return metadata.
 */
class NotificationScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan notifications directory and return metadata.

        return [
            'notifications' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
