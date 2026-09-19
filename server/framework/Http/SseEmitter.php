<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * 运行时把 SSE 事件写到当前 HTTP 连接。
 */
interface SseEmitter
{
    /**
     * @param array<string, mixed> $data
     */
    public function event(string $name, array $data): void;
}
