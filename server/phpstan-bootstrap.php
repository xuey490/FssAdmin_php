<?php

declare(strict_types=1);

// PHPStan 分析期引导文件：声明运行时全局常量，避免大量 constant.notFound 误报。
// 该文件仅在静态分析时由 PHPStan 加载，不影响运行时逻辑。
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// 框架入口常量——public/index.php 中定义，此处为静态分析提供符号声明
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}
