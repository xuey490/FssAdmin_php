<?php

// config/redis.php

/**
 * Redis 多节点配置清单
 *
 * 说明：
 * - 按优先顺序排列，系统会从第一个节点开始尝试连接
 * - 如果连接失败，会自动切换到下一台
 * - 支持任意数量的 Redis 节点
 *
 * pool 节 说明（Redis 连接池参数）：
 * - min_connections：Worker 启动时预热的最小连接数
 * - max_connections：每个 Worker 进程允许的最大连接数
 * - borrow_timeout：借出连接的最大等待时间（秒）
 * - retry_attempts：创建连接失败时的重试次数
 */

return [
    // ---------------------------------------------------------------
    // 节点列表（按优先级排列，首个可用节点将作为池的连接目标）
    // ---------------------------------------------------------------
    'nodes' => [
        [
            'name'     => 'primary',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD') ?: null,
            'database' => (int) env('REDIS_DB', 0),
            'timeout'  => 2.0,
        ],
        [
            'name'     => 'backup-1',
            'host'     => '192.168.0.100',
            'port'     => 6379,
            'password' => null,
            'database' => 1,
            'timeout'  => 2.0,
        ],
        [
            'name'     => 'backup-2',
            'host'     => '192.168.0.101',
            'port'     => 6379,
            'password' => null,
            'database' => 2,
            'timeout'  => 2.0,
        ],
    ],

    // ---------------------------------------------------------------
    // 连接池配置（Redis 连接池，在 Workerman 常驻模式下生效）
    // ---------------------------------------------------------------
    'pool' => [
        'enabled'         => true, 	//(bool) (env('REDIS_POOL_ENABLED') ?? true),
        'min_connections' => 2, 	//(int) (env('REDIS_POOL_MIN') ?? 2),
        'max_connections' => 10, 	//(int) (env('REDIS_POOL_MAX') ?? 10),
        'borrow_timeout'  => 3.0, 	//(float) (env('REDIS_POOL_TIMEOUT') ?? 3.0),
        'retry_attempts'  => 3, 	//(int) (env('REDIS_POOL_RETRY') ?? 3),
    ],

    // ---------------------------------------------------------------
    // 队列消费服务配置
    // ---------------------------------------------------------------
    'queue' => [
        'enabled'       => true , //(bool) (env('REDIS_QUEUE_ENABLED') ?? true),
        'worker_count'  => 2, //(int) (env('REDIS_QUEUE_WORKERS') ?? 2),   // 队列消费 Worker 进程数
        'queues'        => [
            [
                'name'          => 'default',   // 队列名（对应 Redis key: queue:default）
                'pool_name'     => 'redis.default',
                'batch_size'    => (int) (env('REDIS_QUEUE_BATCH') ?? 5),
                'max_retries'   => (int) (env('REDIS_QUEUE_RETRIES') ?? 3),
                'poll_interval' => (float) (env('REDIS_QUEUE_INTERVAL') ?? 0.5),
            ],
            // 可扩展更多队列：
            // [
            //     'name'          => 'email',
            //     'pool_name'     => 'redis.default',
            //     'batch_size'    => 3,
            //     'max_retries'   => 5,
            //     'poll_interval' => 1.0,
            // ],
        ],
    ],
];
