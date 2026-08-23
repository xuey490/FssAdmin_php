<?php

declare(strict_types=1);

/**
 * @Filename: MysqlPool.php
 * @Date: 2026-06-02
 * @Developer: blue2004
 * @Email: xuey863toy@gmail.com
 */

namespace Framework\Pool;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PDO;
use PDOException;
use RuntimeException;
use SplStack;

/**
 * MySQL 连接池
 *
 * 基于 Illuminate\Database（Eloquent）的底层 PDO 连接实现。
 * 每个 Workerman Worker 进程独立持有一个池实例，连接不跨进程共享。
 *
 * 设计要点：
 * - 池内存储原始 PDO 对象，与 Eloquent 连接解耦，灵活性更高
 * - healthCheck 使用 SELECT 1 验证连接可用性
 * - 支持断线重连（reconnect_on_failure）
 */
class MysqlPool implements PoolInterface
{
    /** @var SplStack<PDO> 空闲 PDO 连接栈 */
    private SplStack $idle;

    /** @var int 当前活跃（借出）连接数 */
    private int $active = 0;

    /** @var int 最大连接数 */
    private int $maxConnections;

    /** @var int 最小（预热）连接数 */
    private int $minConnections;

    /** @var float 借出等待超时（秒） */
    private float $borrowTimeout;

    /** @var int 创建连接失败时的重试次数 */
    private int $retryAttempts;

    /** @var bool 是否启用断线重连 */
    private bool $reconnectOnFailure;

    /** @var array<string, mixed> DSN 组件 */
    private array $dsn;

    /** @var bool 池是否已关闭 */
    private bool $closed = false;

    /**
     * 构造函数
     *
     * @param array<string, mixed> $config 连接池配置，示例：
     *   [
     *     'host'                 => '127.0.0.1',
     *     'port'                 => 3306,
     *     'database'             => 'fssoa',
     *     'username'             => 'root',
     *     'password'             => 'root',
     *     'charset'              => 'utf8mb4',
     *     'min_connections'      => 2,
     *     'max_connections'      => 10,
     *     'borrow_timeout'       => 3.0,
     *     'retry_attempts'       => 3,
     *     'reconnect_on_failure' => true,
     *   ]
     */
    public function __construct(private readonly array $config = [])
    {
        $this->maxConnections     = (int) ($config['max_connections'] ?? 10);
        $this->minConnections     = (int) ($config['min_connections'] ?? 2);
        $this->borrowTimeout      = (float) ($config['borrow_timeout'] ?? 3.0);
        $this->retryAttempts      = (int) ($config['retry_attempts'] ?? 3);
        $this->reconnectOnFailure = (bool) ($config['reconnect_on_failure'] ?? true);

        $this->dsn = [
            'host'     => $config['host']     ?? '127.0.0.1',
            'port'     => (int) ($config['port'] ?? 3306),
            'database' => $config['database'] ?? 'fssoa',
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'charset'  => $config['charset']  ?? 'utf8mb4',
        ];

        $this->idle = new SplStack();
        $this->warmup();
    }

    /**
     * 预热：建立最小连接数
     */
    private function warmup(): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            try {
                $this->idle->push($this->createConnection());
            } catch (\Throwable $e) {
                $this->log(sprintf(
                    '[MysqlPool] 预热连接 #%d 失败：%s',
                    $i + 1,
                    $e->getMessage()
                ));
            }
        }

        $this->log(sprintf(
            '[MysqlPool] 预热完成，空闲连接数：%d / %d',
            $this->idle->count(),
            $this->minConnections
        ));
    }

    /**
     * {@inheritDoc}
 */
    public function borrow(): object
    {
        if ($this->closed) {
            throw new RuntimeException('MySQL 连接池已关闭，无法借出连接。');
        }

        $deadline = microtime(true) + $this->borrowTimeout;

        while (true) {
            // 1. 优先取空闲连接
            if (!$this->idle->isEmpty()) {
                $pdo = $this->idle->pop();
                if ($this->healthCheck($pdo)) {
                    $this->active++;
                    return $pdo;
                }
                // 不健康：尝试重连或丢弃
                if ($this->reconnectOnFailure) {
                    try {
                        $pdo = $this->createConnection();
                        $this->active++;
                        return $pdo;
                    } catch (\Throwable $e) {
                        $this->log('[MysqlPool] 重连失败：' . $e->getMessage());
                    }
                }
                $this->log('[MysqlPool] 丢弃不健康连接');
                continue;
            }

            // 2. 池未满，新建连接
            $total = $this->active + $this->idle->count();
            if ($total < $this->maxConnections) {
                $pdo = $this->createConnectionWithRetry();
                $this->active++;
                return $pdo;
            }

            // 3. 池已满，等待或超时
            if (microtime(true) >= $deadline) {
                throw new RuntimeException(sprintf(
                    'MySQL 连接池已耗尽（最大 %d），等待超时（%.1f 秒）。',
                    $this->maxConnections,
                    $this->borrowTimeout
                ));
            }

            usleep(10_000); // 10ms 轮询
        }
    }

    /**
     * {@inheritDoc}
 */
    public function release(object $connection): void
    {
        if (!$connection instanceof PDO) {
            return;
        }

        $this->active = max(0, $this->active - 1);

        if ($this->closed) {
            $connection = null;
            return;
        }

        // 确保自动提交，防止未提交事务残留
        try {
            if ($connection->inTransaction()) {
                $connection->rollBack();
                $this->log('[MysqlPool] 归还时检测到未提交事务，已回滚');
            }
        } catch (\Throwable) {
            // 连接已断开，丢弃
            $this->log('[MysqlPool] 归还连接失败，已丢弃');
            return;
        }

        if ($this->healthCheck($connection)) {
            $this->idle->push($connection);
        } else {
            $this->log('[MysqlPool] 归还连接不健康，已丢弃');
            // 补充新连接
            try {
                $this->idle->push($this->createConnection());
            } catch (\Throwable $e) {
                $this->log('[MysqlPool] 补充连接失败：' . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritDoc}
 */
    public function healthCheck(object $connection): bool
    {
        if (!$connection instanceof PDO) {
            return false;
        }

        try {
            $connection->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     * @return array<mixed>
 */
    public function stats(): array
    {
        return [
            'idle'   => $this->idle->count(),
            'active' => $this->active,
            'total'  => $this->idle->count() + $this->active,
            'max'    => $this->maxConnections,
        ];
    }

    /**
     * {@inheritDoc}
 */
    public function close(): void
    {
        $this->closed = true;

        while (!$this->idle->isEmpty()) {
            $this->idle->pop(); // PDO 无显式 close，置 null 即可
        }

        $this->log('[MysqlPool] 连接池已关闭');
    }

    /**
     * 创建 PDO 连接（带重试）
     *
     * @return PDO
     * @throws RuntimeException
     */
    private function createConnectionWithRetry(): PDO
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $this->createConnection();
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->log(sprintf(
                    '[MysqlPool] 创建连接失败（第 %d/%d 次）：%s',
                    $attempt,
                    $this->retryAttempts,
                    $e->getMessage()
                ));
                if ($attempt < $this->retryAttempts) {
                    usleep(100_000 * $attempt);
                }
            }
        }

        throw new RuntimeException(
            sprintf('MySQL 连接创建失败，已重试 %d 次：%s', $this->retryAttempts, $lastException?->getMessage()),
            0,
            $lastException
        );
    }

    /**
     * 创建原始 PDO 连接
     *
     * @return PDO
     * @throws PDOException
     */
    private function createConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->dsn['host'],
            $this->dsn['port'],
            $this->dsn['database'],
            $this->dsn['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $initSql = sprintf(
            "SET NAMES '%s' COLLATE '%s_unicode_ci'",
            $this->dsn['charset'],
            $this->dsn['charset']
        );
        if (PHP_VERSION_ID >= 80500 && class_exists(\Pdo\Mysql::class)) {
            $options[\Pdo\Mysql::ATTR_INIT_COMMAND] = $initSql;
        } else {
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = $initSql;
        }

        $pdo = new PDO($dsn, $this->dsn['username'], $this->dsn['password'], $options);

        return $pdo;
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
