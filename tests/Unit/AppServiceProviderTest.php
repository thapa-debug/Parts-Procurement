<?php

use Illuminate\Foundation\Console\ServeCommand;

it('passes TMP/TEMP/TMPDIR through to the spawned php artisan serve process on Windows', function () {
    // AppServiceProvider::boot() already ran during test bootstrap; this
    // locks in that the mutation happened and isn't accidentally reverted.
    // See AppServiceProvider::fixServeCommandTemporaryDirectoryOnWindows()
    // for why: without these, `php artisan serve`'s spawned worker process
    // can't resolve a writable temp directory, so any file upload (e.g.
    // the vendor quote-photo form) crashes.
    expect(ServeCommand::$passthroughVariables)
        ->toContain('TMP')
        ->toContain('TEMP')
        ->toContain('TMPDIR');
})->skip(PHP_OS_FAMILY !== 'Windows', 'Windows-only temp-directory passthrough fix');
