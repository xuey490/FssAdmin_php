<?php

// 本地附件：磁盘仍在 public/uploads；对外 URL 为 {APP_URL}/api/uploads/...。
// 生产若只反代了 /prod（VITE_API_URL=/prod），必须再反代 /api，或让前端把地址改成 /prod/api/uploads。
return [
    'disk' => dirname(__DIR__) . '/public/uploads',
    'public_uri' => '/api/uploads',
    // 空则使用 APP_URL。本地填 http://localhost:8000，生产填浏览器域名。
    'public_domain' => '',
    'max_size' => 5 * 1024 * 1024,
    'whitelist_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'txt'],
    'blacklist_extensions' => ['php', 'php5', 'phtml', 'exe', 'sh'],
    'naming' => 'uuid',
];
