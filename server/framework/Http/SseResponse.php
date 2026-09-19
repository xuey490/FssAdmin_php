<?php

declare(strict_types=1);

namespace Framework\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * 空 body 的流式响应：业务把事件写进 Closure，Workerman/Swoole 适配器负责真正 write。
 * 不要在 Closure 里 echo/flush，php://output 到不了长驻进程的 socket。
 */
final class SseResponse extends Response
{
    /**
     * @param \Closure(SseEmitter): void $emitter
     */
    public function __construct(private readonly \Closure $emitter)
    {
        parent::__construct('', Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function emit(SseEmitter $sse): void
    {
        ($this->emitter)($sse);
    }
}
