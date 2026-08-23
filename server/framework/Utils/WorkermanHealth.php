<?php

declare(strict_types=1);

namespace Framework\Utils;

/**
 * Workerman 进程健康与内存快照工具.
 */
final class WorkermanHealth
{
    /**
     * 采集当前 worker 内存与健康指标.
     * @return array<mixed>
 */
    public static function snapshot(?int $workerId = null, ?string $workerName = null): array
    {
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

        return [
            'pid'              => getmypid(),
            'worker_id'        => $workerId,
            'worker_name'      => $workerName,
            'memory_mb'        => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb'   => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_limit_mb'  => defined('MEMORY_LIMIT_MB') ? MEMORY_LIMIT_MB : null,
            'time'             => date('Y-m-d H:i:s'),
            'uptime_s'         => round(microtime(true) - $startTime, 2),
            'php'              => PHP_VERSION,
            'os'               => PHP_OS,
        ];
    }

    /**
     * 写入 health.json（供 /_health 与外部监控读取）.
     * @param array<mixed> $data
 */
    public static function writeHealthFile(string $healthFile, array $data): void
    {
        $dir = dirname($healthFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $healthFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 追加内存历史到 JSONL（便于 soak 对比与告警）.
     * @param array<mixed> $data
     */
    public static function appendMemoryHistory(string $logDir, array $data, ?string $label = null): void
    {
        if ($label !== null) {
            $data['label'] = $label;
        }

        if (! is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $line = json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($logDir . '/memory-history.jsonl', $line, FILE_APPEND | LOCK_EX);
    }
}
