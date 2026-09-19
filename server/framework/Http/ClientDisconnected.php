<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * 浏览器已断开 SSE / 长连接，应立即停止向上游拉模型。
 */
final class ClientDisconnected extends \RuntimeException
{
}
