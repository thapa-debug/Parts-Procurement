<?php

use Tests\TestCase;

// The guard that stops the suite from ever running against a real database
// (tests/TestCase.php). Pure inputs -- no database is touched here.

it('allows local runs on in-memory sqlite', function () {
    expect(TestCase::isolationProblems(false, 'testing', 'sqlite', ':memory:'))->toBe([]);
});

it('allows CI\'s dedicated MySQL test database', function () {
    expect(TestCase::isolationProblems(false, 'testing', 'mysql', 'parts_procurement_test'))->toBe([]);
});

it('refuses a real database -- the dev or production one', function (string $connection, string $database) {
    expect(TestCase::isolationProblems(false, 'testing', $connection, $database))
        ->toHaveCount(1)
        ->{0}->toContain("database '{$database}'");
})->with([
    'the dev MySQL database' => ['mysql', 'parts_procurement'],
    'a database merely containing "test"' => ['mysql', 'test_parts_procurement'],
    'a sqlite file' => ['sqlite', 'database/database.sqlite'],
]);

it('refuses a cached config, whatever else it says', function () {
    expect(TestCase::isolationProblems(true, 'testing', 'sqlite', ':memory:'))
        ->toHaveCount(1)
        ->{0}->toContain('config:clear');
});

it('refuses any environment but testing', function () {
    expect(TestCase::isolationProblems(false, 'local', 'sqlite', ':memory:'))
        ->toHaveCount(1)
        ->{0}->toContain("'local'");
});

it('reports every problem at once -- the exact state that wiped the dev database', function () {
    // Cached local config: local env, dev MySQL database.
    expect(TestCase::isolationProblems(true, 'local', 'mysql', 'parts_procurement'))->toHaveCount(3);
});
