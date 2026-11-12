<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to run any test unless it is genuinely isolated (see
     * isolationProblems()).
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

        $connection = $app['config']->get('database.default');

        $problems = static::isolationProblems(
            configurationIsCached: $app->configurationIsCached(),
            environment: $app->environment(),
            connection: $connection,
            database: $app['config']->get("database.connections.{$connection}.database"),
        );

        if ($problems !== []) {
            throw new RuntimeException(
                "Refusing to run tests -- they would touch a real database and RefreshDatabase would wipe it:\n- "
                .implode("\n- ", $problems)
            );
        }

        return $app;
    }

    /**
     * Everything that makes this run unsafe, or [] if it is isolated: the
     * `testing` environment, no cached config, and a throwaway database --
     * in-memory sqlite (local runs, phpunit.xml) or a database whose name
     * ends in `_test` (CI's dedicated MySQL database, e.g.
     * `parts_procurement_test`). A real dev/production database name never
     * ends in `_test`, so it is refused.
     *
     * @return list<string>
     */
    public static function isolationProblems(bool $configurationIsCached, string $environment, ?string $connection, ?string $database): array
    {
        $problems = [];

        if ($configurationIsCached) {
            $problems[] = 'the configuration is cached (bootstrap/cache/config.php), so phpunit.xml\'s test settings are ignored -- run `php artisan config:clear`';
        }

        if ($environment !== 'testing') {
            $problems[] = "the environment is '{$environment}', not 'testing'";
        }

        $inMemorySqlite = $connection === 'sqlite' && $database === ':memory:';
        $dedicatedTestDatabase = $connection !== 'sqlite' && is_string($database) && str_ends_with($database, '_test');

        if (! $inMemorySqlite && ! $dedicatedTestDatabase) {
            $problems[] = "the database is connection '{$connection}' (database '{$database}'), not in-memory sqlite or a dedicated *_test database";
        }

        return $problems;
    }
}
