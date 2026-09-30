<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Job Monitoring Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the job monitoring system is active.
    | When disabled, no job tracking occurs and event listeners are not active.
    |
    */

    'enabled' => env('JOB_MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Store Job Payload
    |--------------------------------------------------------------------------
    |
    | Determines whether to store the full job payload in the database.
    | This is essential for retry functionality and debugging, but can
    | increase database storage requirements.
    |
    */

    'store_payload' => env('JOB_MONITORING_STORE_PAYLOAD', true),

    /*
    |--------------------------------------------------------------------------
    | Redact Sensitive Keys
    |--------------------------------------------------------------------------
    |
    | Array of keys to redact from job payloads before storing.
    | Any key containing these strings will be replaced with '***REDACTED***'.
    |
    */

    'redact_keys' => [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'private_key',
        'privatekey',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Jobs
    |--------------------------------------------------------------------------
    |
    | Array of job classes that should not be tracked by the monitoring system.
    | Jobs listed here will be completely ignored.
    |
    */

    'exclude_jobs' => [
        // Example: App\Jobs\SomeNoisyJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Telemetry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance telemetry tracking including memory and CPU usage.
    |
    */

    'telemetry' => [
        'enabled' => env('JOB_MONITORING_TELEMETRY', true),
        'sample_rate' => env('JOB_MONITORING_TELEMETRY_SAMPLE_RATE', 1.0),
        'capture_cpu' => env('JOB_MONITORING_TELEMETRY_CPU', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to keep job run records before they can be pruned.
    |
    */

    'retention_days' => env('JOB_MONITORING_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Queue Depth Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for determining queue health status based on pending job count.
    |
    */

    'queue_depth_thresholds' => [
        'healthy' => 10,
        'warning' => 50,
        'critical' => 100,
    ],
];
