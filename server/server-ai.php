#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 仅启动 AI HTTP Worker（默认 http://0.0.0.0:8001，只处理 /api/ai/）。
 *
 * Linux：`php server.php start` 已同时监听 8000+8001，不要再跑本文件（端口冲突）。
 * Windows：Workerman 单文件只能起一个 Worker，需另开本进程：
 *   php server.php start
 *   php server-ai.php start
 *
 * 进程数 / 端口：
 *   WORKERMAN_AI_PORT=8001
 *   WORKERMAN_AI_COUNT=4
 *
 * 反代分流：docs/deploy/ai-http-split.md
 */
define('WORKERMAN_AI_ONLY', true);

require __DIR__ . '/server.php';
