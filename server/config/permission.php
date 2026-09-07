<?php
return [
    // 默认驱动名称（根节点）
    'default' => 'default',
    
    // 日志配置
    'log' => [
        'enabled' => true,
        'logger' => 'casbin',
        'path' => storage_path() . '/logs/casbin.log',
    ],
    
    // ========== 核心修改：新增 enforcers 节点包裹所有驱动配置 ==========
    'enforcers' => [
        // default 驱动配置（放在 enforcers 下）
        'default' => [
            'model' => [
                'config_type' => 'file',
                'config_file_path' => __DIR__ . '/casbin_rbac_model.conf',
            ],
            // 使用统一的 DatabaseAdapter
            'adapter' => \App\Services\Casbin\DatabaseAdapter::class,
            // 数据库设置	
            'database' => [
                'connection' => 'mysql',
                'rules_table' => 'casbin_rule',
                'rules_name' => null
            ],
			/*
            // 数据库配置（原生 PDO 所需）
            'database' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'oa', // 替换为你的数据库名
                'username' => 'root', // 替换为你的数据库用户名
                'password' => '123456', // 替换为你的数据库密码
                'charset' => 'utf8mb4',
                'table' => 'oa_casbin_rules'
            ],
			*/
			
			
            // Redis Watcher 配置（与 .env REDIS_* 对齐）
            'redis_watcher' => [
                'enable' => true,
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => (int) env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD') ?: '',
                'database' => (int) env('REDIS_DB', 0),
                'channel' => '/casbin',
                'timeout' => 5.0,
            ],
        ],
        
        // restful 驱动配置（同样放在 enforcers 下）
        'restful' => [
            'model' => [
                'config_type' => 'file',
                'config_file_path' => __DIR__ . '/plugin/casbin/webman-permission/restful-model.conf', // 修正路径：去掉 config_path()，改用 __DIR__
                'config_text' => '',
            ],
            // 使用统一的 DatabaseAdapter
            'adapter' => \App\Services\Casbin\DatabaseAdapter::class,
            'database' => [
                'connection' => '',
                'rules_table' => 'restful_casbin_rule',
                'rules_name' => null
            ],
        ],
    ],
];