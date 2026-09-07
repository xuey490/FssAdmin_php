<?php

// config/cache.php
return [
    'default' => env('CACHE_DRIVER', 'file'),
	'REPO_CACHE_DISABLED'	=> true,	//数据库缓存
    'stores' => [
        'file' => [
            'driver' => 'file',
			'type' => 'File',  //兼容thinkcache
            'path' => BASE_PATH . '/storage/cache',
            'prefix' => 'cache_',           // ← 新增：key 前缀
            'enable_tags' => true,          // ← 新增：是否启用标签
        ],

        'redis' => [
            'driver' => 'redis',
			'type'   => 'Redis', //兼容thinkcache
			'host'       => env('REDIS_HOST', '127.0.0.1'),
			'port'	 	=> (int) env('REDIS_PORT', 6379),
            'password'  => env('REDIS_PASSWORD') ?: '',
			'expire'	=>3600,

            // database: Symfony CacheFactory DSN；select: think-cache phpredis（只认 select，缺了会落到 db0）
            'database' => (int) env('REDIS_DB', 0),
            'select'   => (int) env('REDIS_DB', 0),
            'prefix' => 'redis_cache_',           // Redis 缓存前缀
            'enable_tags' => true,
        ],

        'memcached' => [
            'driver' => 'memcached',
			'type'   => 'memcached',
            'servers' => [
                ['host' => env('MEMCACHED_HOST', '127.0.0.1'), 'port' => env('MEMCACHED_PORT', 11211)],
            ],
            'prefix' => 'mem_',
            'enable_tags' => true,
        ],
		
		
        'apcu' => [
            'driver' => 'apcu',
            'prefix' => 'apcu_',
            'enable_tags' => false,         // APCu 不适合大量标签（可选）
        ],

        'array' => [
            'driver' => 'array',
            'prefix' => 'array_',
            'enable_tags' => true,
        ],
    ],
];


