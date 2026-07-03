<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Contracts;

use Coffesoft\LaravelBeacon\Context\Context;

/**
 * Contract for exporting context data to a specific format.
 */
interface Exporter
{
    /**
     * Export the given context.
     */
    public function export(Context $context): void;
}