<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanners;

use Coffesoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Livewire.
 *
 * TODO: Scan livewire and return metadata.
 */
class LivewireScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan livewire directory and return metadata.

        return [
            'livewire' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
