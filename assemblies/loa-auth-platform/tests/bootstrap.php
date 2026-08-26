<?php

/*
 * PHPUnit bootstrap (referenced from phpunit.xml.dist).
 *
 * The docker-compose container exports APP_ENV / DB_* as real environment
 * variables. DotEnv's adapter chain reads $_SERVER before $_ENV, so even
 * force="true" <env> entries cannot reliably win (PHPUnit does not overwrite
 * an existing $_SERVER entry). Everything test-critical is therefore pinned
 * HERE, before autoload, making runs identical on any machine.
 *
 * APP_ENV pins to 'local' deliberately:
 * - nothing in app/tests branches on 'testing' (verified);
 * - under 'testing' Laravel skips CSRF verification entirely
 *   (VerifyCsrfToken::runningUnitTests), which would disable the exact
 *   behaviour CsrfExpiryTest asserts (web-ui.md §4.0);
 * - the legacy-origin seed migration detects test runs via its sqlite
 *   :memory: connection guard instead of the env name.
 */

$forced = [
    'APP_ENV' => 'local',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'JWT_SECRET' => 'test-secret-key-for-testing-only-32chars',
    'CACHE_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'SESSION_DRIVER' => 'array',
    'APP_URL' => 'http://localhost',
];

foreach ($forced as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv("$name=$value");
}

require __DIR__.'/../vendor/autoload.php';
