<?php

// config/middleware.php

return [
    /*
    |--------------------------------------------------------------------------
    | 全局中间件配置
    |--------------------------------------------------------------------------
    | 控制哪些中间件启用，以及它们的参数
    */
    // CORS 跨域配置
    'cors' => [
        // 允许的来源域名白名单（逗号分隔），通过 .env 的 CORS_ALLOWED_ORIGINS 配置。
        // 例：CORS_ALLOWED_ORIGINS=https://admin.example.com,https://app.example.com
        // 留空表示不下发跨域许可（同源部署无需配置，跨域必须显式列出，禁止使用 '*'）。
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        ))),
        // 是否允许携带凭证（Cookie）。注意：开启凭证时禁止使用 '*'，必须回显具体 Origin。
        'allow_credentials' => true,
    ],

	// Csrf的配置
    'csrf_protection' => [
        'enabled' => true,
        'token_name' => '_token',
        // 说明：带 Bearer 头的请求已在中间件层自动跳过 CSRF（见 CsrfProtectionMiddleware）。
        // 这里仅豁免「无法携带 CSRF token 的引导/回调类端点」：
        //  - 登录引导：登录/登出/刷新/切换租户/验证码（此时尚无会话或走 refresh cookie）
        //  - 服务器间回调：webhook / 支付通知（外部发起，无 Cookie 也无 Bearer）
        // 其余「纯 Cookie 自动携带」的写请求将被强制 CSRF 校验。
        'except' => [
            '/api/core/login',
            '/api/core/logout',
            '/api/core/refresh',
            '/api/core/switch-tenant',
            '/api/core/captcha*',
            '/api/schoole/teacher/login',
            '/api/schoole/teacher/tenants',
            '/webhook/*',
            '/payment/notify',
        ],
        'error_message' => '请求无效，请刷新页面后重试。',
        'remove_after_validation' => false, // 用完即焚
    ],

	// Referer配置
    'referer_check' => [
        'enabled' => true,
        'allowed_hosts' => [
            'localhost',
            '127.0.0.1',
            'yourdomain.com',
            'sub.yourdomain.com',
			'localhost:5173'
        ],
        'allowed_schemes' => ['http', 'https'],
        'except' => [
            '/api/*',
            '/admin/*',
            '/Admin/*',
            '/payment/*'
        ],
        'strict' => false, // false = 允许空 Referer（如隐私模式）
        'error_message' => '请求来源不被允许。',
    ],
	
	
    'rate_limit' => [
        'enabled' => true,
        'maxRequests' => 10000,	//60秒内的最大请求数 ,测试设大一点的数
		'period'	=> 60,  //60秒
        'except' => [
            '/api/*',
            '/admin/*',
            '/Admin/*',
            '/webhook/*',
            '/payment/notify'
        ],		
    ],
	
	'debug'	=> [
		'enabled' => env('APP_DEBUG' , false),
	],

    // 安全响应头（对用户无感，纯增强）
    'security_headers' => [
        'enabled'            => true,
        'frame_options'      => 'DENY',
        'referrer_policy'    => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
        // 后端主要返回 JSON 与少量 Twig 错误页；保留 style-src 'unsafe-inline'
        // 以免破坏内置错误页的内联样式。前端 SPA 由各自服务单独托管，不受此 CSP 影响。
        'csp'                => "default-src 'self'; frame-ancestors 'none'; object-src 'none'; base-uri 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:",
        // 仅 HTTPS 下生效
        'hsts'               => 'max-age=31536000; includeSubDomains',
    ],

    // 敏感认证接口限流（弥补全局限流豁免 /api/* 的缺口）
    'login_rate_limit' => [
        'enabled'               => true,
        'paths'                 => [
            '/api/core/login',
            '/api/core/refresh',
            '/api/core/captcha*',
        ],
        'ip_max_attempts'       => 50,   // 同一 IP 每周期最大尝试数
        'identity_max_attempts' => 10,   // 同一 IP+用户名 每周期最大尝试数
        'period'                => 300,  // 周期（秒）
    ],

    // 测试环境写操作保护（拦截 POST/PUT/PATCH/DELETE）
    'test_env_write_guard' => [
        'enabled' => true,
        'only_envs' => ['test', 'testing'],
        'block_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
        'whitelist' => [
            '/api/core/login',
            '/api/core/logout',
            '/api/core/refresh',
            '/api/core/captcha*',
			'/api/core/switch-tenant',
            '/api/flow/*',
        ],
    ],

    // 可扩展其他中间件
    // 'rate_limit' => [...]
];
