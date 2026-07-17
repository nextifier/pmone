<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
        'redis:analytics' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 600,
            'nice' => 0,
        ],
        'supervisor-analytics' => [
            'connection' => 'redis',
            'queue' => ['analytics'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 5,
            'timeout' => 600,
            'nice' => 0,
        ],
        'supervisor-pdf' => [
            'connection' => 'redis',
            'queue' => ['pdf-batch'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 120,
            'nice' => 10,
        ],
        'supervisor-tickets' => [
            'connection' => 'redis',
            'queue' => ['tickets'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        /*
         * Long admin-triggered batches (bulk attendee generation). Runs on the
         * redis-long connection, whose retry_after (1900) exceeds this timeout so
         * a slow batch is never handed to a second worker mid-run. Deliberately
         * capped at one process and niced: it must never crowd out ticket
         * checkout on an event day.
         */
        'supervisor-bulk' => [
            'connection' => 'redis-long',
            'queue' => ['bulk'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 1,
            'timeout' => 1800,
            'nice' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Process Ceilings
    |--------------------------------------------------------------------------
    |
    | These caps are a MEMORY BUDGET, not a throughput dial. Every supervisor's
    | maxProcesses adds to one shared total, and the app server also has to hold
    | PHP-FPM, PostgreSQL and Redis. Sizing (measured on the 4GB production box):
    |
    |   3915 MB  total
    |   - 250    OS + nginx
    |   - 400    PostgreSQL
    |   -  50    Redis
    |   - 1460   PHP-FPM (pm.max_children 20 x 73 MB measured)
    |   - 500    page cache PostgreSQL needs to stay off disk
    |   ------
    |   ~1255 MB left for every Horizon worker combined, i.e. ~10 workers.
    |
    | The ceilings previously summed to 26 processes. Nothing had gone wrong yet
    | only because the queues sit idle; the shape that breaks is an event day,
    | where ticket checkout is a POST (so Cloudflare cannot cache it and it lands
    | on PHP-FPM) at the same moment the tickets queue floods. Both then compete
    | for the same RAM, and the OOM killer picks the biggest process - PostgreSQL.
    |
    | Tickets keeps the largest share deliberately: on an event day it is the
    | queue that must not fall behind. Raise these only alongside more RAM, and
    | re-measure rather than guessing - a worker is not 50 MB.
    |
    | Note supervisor-pdf is heavier than its `memory` value suggests: Browsershot
    | spawns Chromium as a *child* process, whose few hundred MB Horizon never
    | sees or counts.
    |
    */

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_DEFAULT_MAX_PROCESSES', 3),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-analytics' => [
                'maxProcesses' => (int) env('HORIZON_ANALYTICS_MAX_PROCESSES', 1),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-pdf' => [
                'maxProcesses' => (int) env('HORIZON_PDF_MAX_PROCESSES', 1),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-tickets' => [
                'maxProcesses' => (int) env('HORIZON_TICKETS_MAX_PROCESSES', 6),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-bulk' => [
                'maxProcesses' => (int) env('HORIZON_BULK_MAX_PROCESSES', 1),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'supervisor-analytics' => [
                'maxProcesses' => 2,
            ],
            'supervisor-pdf' => [
                'maxProcesses' => 1,
            ],
            'supervisor-tickets' => [
                'maxProcesses' => 3,
            ],
            'supervisor-bulk' => [
                'maxProcesses' => 1,
            ],
        ],
    ],
];
