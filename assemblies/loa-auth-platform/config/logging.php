<?php

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Logger;

return [

    'defaults' => [
        'stack' => env('APP_LOG_CHANNEL', 'stack'),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => array_filter([
                env('SEQ_URL') ? 'seq' : null,
                'single',
            ]),
        ],

        'seq' => [
            'driver' => 'monolog',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('SEQ_HOST', 'seq'),
                'port' => env('SEQ_SYSLOG_PORT', 5341),
                'facility' => env('LOG_SYSLOG_FACILITY', Logger::USER),
            ],
            'formatter' => \Monolog\Formatter\LineFormatter::class,
            'formatter_with' => [
                'format' => '%channel%.%level_name% %message% %context% %extra%',
            ],
            'level' => env('LOG_LEVEL', 'debug'),
            'bubble' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => \Monolog\Handler\StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'stdout' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => \Monolog\Handler\StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stdout',
            ],
        ],

        'emergency' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stdout',
            ],
        ],
    ],

];
