<?php

namespace Database\Seeders;

use RuntimeException;

/**
 * For seeders that create fictional data: stop before writing anything in production,
 * even when someone runs the seeder class directly with --force.
 */
trait GuardsAgainstProduction
{
    protected function refuseInProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(static::class.' creates fictional data and must not run in production.');
        }
    }
}
