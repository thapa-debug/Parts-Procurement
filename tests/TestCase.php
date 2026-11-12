<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to run any test unless it is genuinely isolated: the
     * `testing` environment on an in-memory sqlite database.
     *
     * Why: tests use RefreshDatabase, which drops and recreates every
     * table. If phpunit.xml's environment is ignored -- most easily by a
     * cached config (`php artisan config:cache` / `optimize`), which makes
     * Laravel skip env vars entirely -- the suite would run against the
     * real local database and wipe it (this happened: a cached config
     * pointed the suite at the dev MySQL database). Checked here, when the
     * application is created, because RefreshDatabase runs later in
     * setUp() -- a check after that would come too late.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $problems = [];

        if ($app->configurationIsCached()) {
            $problems[] = 'the configuration is cached (bootstrap/cache/config.php), so phpunit.xml\'s test settings are ignored -- run `php artisan config:clear`';
        }

        if (! $app->environment('testing')) {
            $problems[] = "the environment is '{$app->environment()}', not 'testing'";
        }

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $problems[] = "the database is connection '{$connection}' (database '{$database}'), not in-memory sqlite";
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Refusing to run tests -- they would touch a real database and RefreshDatabase would wipe it:\n- "
                .implode("\n- ", $problems)
            );
        }

        return $app;
    }
}
