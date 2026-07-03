<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanners;

use Coffesoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Jobs.
 *
 * TODO: Scan jobs and return metadata.
 */
class JobScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan jobs directory and return metadata.

        return [
            'jobs' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
