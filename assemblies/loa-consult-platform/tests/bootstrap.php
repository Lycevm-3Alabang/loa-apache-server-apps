<?php

/*
 * PHPUnit bootstrap (referenced from phpunit.xml.dist).
 *
 * The docker-compose container exports DB_* as real environment variables.
 * Laravel's Env adapter chain reads $_SERVER before $_ENV, so even
 * force="true" <env> entries cannot reliably win (PHPUnit does not overwrite
 * an existing $_SERVER entry) — without this pin, RefreshDatabase tests run
 * against the loa_consult APP database instead of loa_consult_test.
 * Everything test-critical is therefore pinned HERE, before autoload,
 * making runs identical on any machine. Same precedent as
 * assemblies/loa-auth-platform/tests/bootstrap.php.
 */

$forced = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'mysql',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'loa_consult_test',
    'DB_USERNAME' => 'loa',
    'DB_PASSWORD' => 'loa-secret',
    'QUEUE_CONNECTION' => 'sync',
    'JWT_SECRET' => 'test-jwt-secret-do-not-use-prod',
    'ENCRYPTION_KEY' => 'base64:aQ0GFg4Sb84QdlaGQc5wiS17VFPCWOvKQZJ+/bUCRYE=',
    'TENANT_SLUG' => 'loa',
    'REFRESH_COOKIE' => 'loa_connect_refresh',
    'REFRESH_COOKIE_SECURE' => 'false',
];

foreach ($forced as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv("$name=$value");
}

require __DIR__.'/../vendor/autoload.php';
