<?php

declare(strict_types=1);

/**
 * @Filename: RedisConsumerService.php
 * @Date: 2026-06-02
 * @Developer: blue2004
 * @Email: xuey863toy@gmail.com
 */

namespace Framework\Queue;

use Framework\Pool\PoolManager;
use Predis\Client as PredisClient;
use RuntimeException;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Redis 队列消费服务
 *
 * 基于 Redis LIST（BLPOP/LPUSH）实现的轻量消息队列消费者，与 Workerman 生命周期绑定。
 *
 * 特性：
 * - 常驻监听：在独立 Worker 的 onWorkerStart 中通过定时器持续拉取
 * - 失败重试：失败后推入重试队列，超过最大重试次数推入死信队列
 * - 优雅停止：Worker onWorkerStop 时停止消费并记录日志
 * - 日志：复用项目 log_info / error_log 双重落地
 * - 消费并发：每次定时器触发消费 batchSize 条消息
 *
 * 使用方式（在 server.php 队列 Worker 的 onWorkerStart 中）：
 * ```php
 * $consumer = new RedisConsumerService([
 *     'queue'         => 'default',
 *     'pool_name'     => 'redis.default',
 *     'batch_size'    => 5,
 *     'max_retries'   => 3,
 *     'poll_interval' => 0.5,   // 秒
 * ]);
 * $consumer->registerHandler('send_email', new SendEmailHandler());
 * $consumer->start($worker);
 * ```
 */
class RedisConsumerService
{
    /** @var string 主消费队列 Redis Key */
    private string $queue;

    /** @var string 重试队列 Redis Key */
    private string $retryQueue;

    /** @var string 死信队列 Redis Key */
    private string $deadQueue;

    /** @var string 连接池名称 */
    private string $poolName;

    /** @var int 每次批量拉取的最大消息数 */
    private int $batchSize;

    /** @var int 消息处理失败时的最大重试次数 */
    private int $maxRetries;

    /** @var float 轮询间隔（秒） */
    private float $pollInterval;

    /** @var array<string, MessageHandlerInterface> type => handler 映射 */
    private array $handlers = [];

    /** @var bool 是否正在运行 */
    private bool $running = false;

    /** @var int|null Workerman 定时器 ID */
    private ?int $timerId = null;

    /** @var array<string, int> 消息消费统计 */
    private array $stats = [
        'consumed'   => 0,
        'success'    => 0,
        'failed'     => 0,
        'retried'    => 0,
        'dead'       => 0,
    ];

    /**
     * 构造函数
     *
     * @param array<string, mixed> $config 消费服务配置：
     *   [
     *     'queue'         => 'default',      // 队列名（Redis key 前缀为 queue:）
     *     'pool_name'     => 'redis.default', // 连接池名称
     *     'batch_size'    => 5,              // 每次批量消费数
     *     'max_retries'   => 3,              // 最大重试次数
     *     'poll_interval' => 0.5,            // 轮询间隔（秒）
     *   ]
     */
    public function __construct(private readonly array $config = [])
    {
        $queueName        = $config['queue']         ?? 'default';
        $this->queue      = 'queue:' . $queueName;
        $this->retryQueue = 'queue:' . $queueName . ':retry';
        $this->deadQueue  = 'queue:' . $queueName . ':dead';
        $this->poolName   = $config['pool_name']     ?? 'redis.default';
        $this->batchSize  = (int) ($config['batch_size']    ?? 5);
        $this->maxRetries = (int) ($config['max_retries']   ?? 3);
        $this->pollInterval = (float) ($config['poll_interval'] ?? 0.5);
    }

    /**
     * 注册消息处理器
     *
     * @param string                  $type    消息类型（对应消息体中的 type 字段）
     * @param MessageHandlerInterface $handler 处理器实例
     * @return static
     */
    public function registerHandler(string $type, MessageHandlerInterface $handler): static
    {
        $this->handlers[$type] = $handler;
        return $this;
    }

    /**
     * 批量注册消息处理器
     *
     * @param array<string, MessageHandlerInterface> $handlers
     * @return static
     */
    public function registerHandlers(array $handlers): static
    {
        foreach ($handlers as $type => $handler) {
            $this->registerHandler($type, $handler);
        }
        return $this;
    }

    /**
     * 启动消费服务（绑定到 Workerman Worker）
     *
     * 在 Worker 的 onWorkerStart 回调中调用此方法。
     *
     * @param Worker $worker Workerman Worker 实例
     * @return void
     */
    public function start(Worker $worker): void
    {
        $this->startScheduled((int) $worker->id, function (float $seconds, callable $callback): void {
            $timerId = Timer::add($seconds, $callback);
            if ($this->timerId === null) {
                $this->timerId = $timerId;
            }
        });
    }

    /**
     * 用外部调度器启动消费（Swoole Timer::tick 等）。
     *
     * @param int $workerId 进程编号（仅用于日志）
     * @param callable(float, callable(): void): void $everySeconds 注册周期任务：秒 + 回调
     */
    public function startScheduled(int $workerId, callable $everySeconds): void
    {
        if ($this->running) {
            return;
        }

        if (!PoolManager::has($this->poolName)) {
            throw new RuntimeException(sprintf(
                '[RedisConsumer] 连接池 [%s] 未注册，请在 onWorkerStart 中先初始化池。',
                $this->poolName
            ));
        }

        $this->running = true;

        $this->log(sprintf(
            '[RedisConsumer] Worker #%d 启动消费，队列：%s，批量：%d，轮询间隔：%.2fs',
            $workerId,
            $this->queue,
            $this->batchSize,
            $this->pollInterval
        ));

        $everySeconds($this->pollInterval, function (): void {
            $this->consumeBatch();
        });

        $everySeconds(60.0, function () use ($workerId): void {
            $this->log(sprintf(
                '[RedisConsumer] Worker #%d 统计 | 已消费:%d 成功:%d 失败:%d 重试:%d 死信:%d | 队列:%s',
                $workerId,
                $this->stats['consumed'],
                $this->stats['success'],
                $this->stats['failed'],
                $this->stats['retried'],
                $this->stats['dead'],
                $this->queue
            ));
        });
    }

    /**
     * 停止消费服务（在 Worker onWorkerStop 中调用）
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;

        if ($this->timerId !== null) {
            Timer::del($this->timerId);
            $this->timerId = null;
        }

        $this->log('[RedisConsumer] 消费服务已停止');
    }

    /**
     * 批量消费一批消息
     */
    private function consumeBatch(): void
    {
        if (!$this->running) {
            return;
        }

        
        $redis = null;

        try {
            $redis = PoolManager::borrow($this->poolName);

            for ($i = 0; $i < $this->batchSize; $i++) {
                // LPOP：非阻塞取出，无消息则退出本轮
                $raw = $redis->lpop($this->queue);
                if ($raw === null) {
                    break;
                }

                $this->stats['consumed']++;
                $this->processMessage($redis, $raw);
            }

            // 处理重试队列（到期重试消息）
            $this->processRetryQueue($redis);

        } catch (Throwable $e) {
            $this->log(sprintf('[RedisConsumer] 批量消费异常：%s', $e->getMessage()));
        } finally {
            if ($redis !== null) {
                PoolManager::release($this->poolName, $redis);
            }
        }
    }

    /**
     * 处理单条消息
     *
     * @param PredisClient $redis  Redis 连接
     * @param string       $raw    原始消息字符串
     */
    private function processMessage(PredisClient $redis, string $raw): void
    {
        $envelope = null;

        try {
            $envelope = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->log(sprintf('[RedisConsumer] 消息 JSON 解析失败，已丢弃：%s', $raw));
            $this->stats['failed']++;
            return;
        }

        $type    = $envelope['type']    ?? 'unknown';
        $payload = $envelope['payload'] ?? $envelope;
        $retries = (int) ($envelope['_retries'] ?? 0);

        if (!isset($this->handlers[$type])) {
            $this->log(sprintf('[RedisConsumer] 未找到处理器 type=%s，消息已丢弃', $type));
            $this->stats['failed']++;
            return;
        }

        try {
            $this->handlers[$type]->handle($payload);
            $this->stats['success']++;
        } catch (Throwable $e) {
            $this->stats['failed']++;
            $this->log(sprintf(
                '[RedisConsumer] 处理失败 type=%s retries=%d/%d：%s',
                $type,
                $retries,
                $this->maxRetries,
                $e->getMessage()
            ));

            if ($retries < $this->maxRetries) {
                // 推入重试队列（延迟 backoff：指数退避）
                $envelope['_retries']    = $retries + 1;
                $envelope['_retry_at']   = time() + (int) (2 ** $retries * 5); // 5s, 10s, 20s
                $envelope['_last_error'] = $e->getMessage();

                $redis->rpush($this->retryQueue, [json_encode($envelope, JSON_UNESCAPED_UNICODE)]);
                $this->stats['retried']++;

                $this->log(sprintf(
                    '[RedisConsumer] 消息已推入重试队列，将在 %d 秒后重试（第 %d/%d 次）',
                    2 ** $retries * 5,
                    $retries + 1,
                    $this->maxRetries
                ));
            } else {
                // 超过最大重试次数，推入死信队列
                $envelope['_dead_at']    = time();
                $envelope['_last_error'] = $e->getMessage();

                $redis->rpush($this->deadQueue, [json_encode($envelope, JSON_UNESCAPED_UNICODE)]);
                $this->stats['dead']++;

                $this->log(sprintf(
                    '[RedisConsumer] 消息已推入死信队列（已重试 %d 次）：type=%s',
                    $retries,
                    $type
                ));
            }
        }
    }

    /**
     * 处理重试队列：将到期的消息重新推回主队列
     *
     * @param PredisClient $redis
     */
    private function processRetryQueue(PredisClient $redis): void
    {
        $now = time();
        $len = $redis->llen($this->retryQueue);

        if ($len === 0) {
            return;
        }

        // 扫描重试队列（最多扫描 20 条，避免阻塞太久）
        $scanLimit = min($len, 20);
        $toRequeue = [];
        $toKeep    = [];

        for ($i = 0; $i < $scanLimit; $i++) {
            $raw = $redis->lpop($this->retryQueue);
            if ($raw === null) {
                break;
            }

            try {
                $envelope = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $retryAt = (int) ($envelope['_retry_at'] ?? 0);

            if ($retryAt <= $now) {
                $toRequeue[] = $raw;
            } else {
                $toKeep[] = $raw;
            }
        }

        // 重新入队：到期消息推主队列，未到期放回重试队列尾部
        foreach ($toRequeue as $raw) {
            $redis->rpush($this->queue, [$raw]);
        }
        foreach ($toKeep as $raw) {
            $redis->rpush($this->retryQueue, [$raw]);
        }
    }

    /**
     * 向指定队列推入消息（静态工厂方法，便于业务层使用）
     *
     * @param string               $queueName  队列名（不含 'queue:' 前缀）
     * @param string               $type       消息类型
     * @param array<string, mixed> $payload    消息内容
     * @param string               $poolName   Redis 连接池名称
     * @return void
     */
    public static function dispatch(
        string $queueName,
        string $type,
        array $payload,
        string $poolName = 'redis.default'
    ): void {
        $envelope = [
            'type'       => $type,
            'payload'    => $payload,
            '_retries'   => 0,
            '_queued_at' => time(),
        ];

        
        $redis = PoolManager::borrow($poolName);

        try {
            $redis->rpush('queue:' . $queueName, [json_encode($envelope, JSON_UNESCAPED_UNICODE)]);
        } finally {
            PoolManager::release($poolName, $redis);
        }
    }

    /**
     * 获取消费统计信息
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 写日志
     */
    private function log(string $message): void
    {
        if (function_exists('log_info')) {
            log_info($message);
        } else {
            error_log($message);
        }
    }
}
