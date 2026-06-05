<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but in-memory sqlite.
     *
     * On 2026-06-05 a cached bootstrap/cache/config.php leaked the
     * production mysql connection into the test process and RefreshDatabase
     * wiped the live database. This guard runs before any trait (e.g.
     * RefreshDatabase) gets a chance to migrate.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            self::fail(
                "Tests must run on in-memory sqlite, got [{$connection}] database [{$database}]. " .
                'A cached config (bootstrap/cache/config.php) is probably overriding phpunit.xml — ' .
                'run `php artisan config:clear` first.'
            );
        }

        return parent::setUpTraits();
    }
}
