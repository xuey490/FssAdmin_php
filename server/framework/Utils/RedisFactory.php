<?php

declare(strict_types=1);

/**
 * This file is part of FssPHP Framework.
 *
 * @link     https://github.com/xuey490/project
 * @license  https://github.com/xuey490/project/blob/main/LICENSE
 *
 * @Filename: %filename%
 * @Date: 2025-11-24
 * @Developer: xuey863toy
 * @Email: xuey863toy@gmail.com
 */

namespace Framework\Utils;

/*
class RedisFactory {

    public static function createRedisClient(array $config): \Redis {
        $redis = new \Redis();
        $connected = $redis->connect($config['host'], $config['port'], $config['timeout']);
        if (!$connected) {
            throw new RuntimeException('Failed to connect to Redis');
        }
        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }
        if (isset($config['database'])) {
            $redis->select($config['database']);
        }
        return $redis;
    }
}
*/

use Redis;
use RuntimeException;
use Workerman\Timer;

/**
 * Redis 客户端工厂（带自动重连 + 故障切换 + 自动续期分布式锁）.
 */
class RedisFactory
{
    /** @var array<mixed> Redis 节点配置 */
    private static array $configList = [];

    /** @var null|\Redis 当前连接 */
    private static ?\Redis $redis = null;

    /** @var int 最大重试次数 */
    private static int $maxRetries = 3;

    /** @var float 重试间隔（秒） */
    private static float $retryDelay = 0.1;

    /** @var array<string, array{timer_id: int, ttl: int}> 锁续期定时器映射 */
    private static array $lockTimers = [];

    // ========== 连接管理 ==========

    /**
    * @param array<mixed> $configList
    */
    public static function createRedisClient(array $configList): \Redis
    {
        self::$configList = $configList;
        return self::client();
    }

    public static function client(): \Redis
    {
        if (! self::$redis instanceof \Redis || ! self::$redis->isConnected()) {
            self::connect();
        } else {
            self::ensureSelectedDb(self::$redis);
        }
        return self::$redis;
    }

    // ========== 基础 Redis 操作（略，保持不变）==========
    // === Redis 封装方法 ===

    public static function set(string $key, mixed $value, int $ttl = 0, bool $nx = false): bool
    {
        return self::exec(function (\Redis $r) use ($key, $value, $ttl, $nx) {
            $opt = [];
            if ($ttl > 0) {
                $opt['ex'] = $ttl;
            }
            if ($nx) {
                $opt['nx'] = true;
            }
            return $r->set($key, $value, $opt);
        });
    }

    public static function get(string $key): mixed
    {
        return self::exec(fn (\Redis $r) => $r->get($key));
    }

    /**
    * @param array<mixed>|string  $key
    */
    public static function del(array|string $key): int
    {
        return self::exec(fn (\Redis $r) => $r->del($key));
    }

    public static function exists(string $key): bool
    {
        return (bool) self::exec(fn (\Redis $r) => $r->exists($key));
    }

    public static function incr(string $key, int $by = 1): int
    {
        return self::exec(fn (\Redis $r) => $r->incrBy($key, $by));
    }

    public static function decr(string $key, int $by = 1): int
    {
        return self::exec(fn (\Redis $r) => $r->decrBy($key, $by));
    }

    public static function hSet(string $hash, string $field, mixed $value): int
    {
        return self::exec(fn (\Redis $r) => $r->hSet($hash, $field, $value));
    }

    public static function hGet(string $hash, string $field): mixed
    {
        return self::exec(fn (\Redis $r) => $r->hGet($hash, $field));
    }

    /**
    * @return array<mixed>
    */
    public static function hGetAll(string $hash): array
    {
        return self::exec(fn (\Redis $r) => $r->hGetAll($hash));
    }

    public static function expire(string $key, int $ttl): bool
    {
        return (bool) self::exec(fn (\Redis $r) => $r->expire($key, $ttl));
    }
    // ========== 分布式锁（增强版）==========

    /**
     * 获取分布式锁（带自动续期）.
     *
     * @param  string       $key      锁名称
     * @param  int          $ttl      初始过期时间（秒）
     * @param  int          $renewTtl 续期时间（秒），建议为 ttl 的 1/3
     * @return false|string 成功返回唯一 token，失败返回 false
     */
    public static function lock(string $key, int $ttl = 10, ?int $renewTtl = null): false|string
    {
        $token   = uniqid('lock_', true); // 唯一标识，防止误删
        $fullKey = "lock:{$key}";

        $acquired = self::exec(function (\Redis $r) use ($fullKey, $token, $ttl) {
            return $r->set($fullKey, $token, ['nx', 'ex' => $ttl]);
        });

        if (! $acquired) {
            return false;
        }

        // 启动自动续期（每 renewTtl 秒续一次）
        $renewTtl = $renewTtl ?? max(1, intval($ttl / 3));

        $timerId = Timer::add($renewTtl, function () use ($fullKey, $token, $ttl) {
            try {
                // Lua 脚本：仅当 token 匹配时续期
                $script = '
                    if redis.call("GET", KEYS[1]) == ARGV[1] then
                        return redis.call("EXPIRE", KEYS[1], ARGV[2])
                    else
                        return 0
                    end
                ';
                self::exec(function (\Redis $r) use ($script, $fullKey, $token, $ttl) {
                    $r->eval($script, [$fullKey, $token, $ttl], 1);
                });
            } catch (\Throwable $e) {
                error_log('[Redis Lock Renew] Error: ' . $e->getMessage());
                // 续期失败不中断，由 TTL 自然过期
            }
        }, [], true); // true = persistent

        self::$lockTimers[$fullKey] = [
            'timer_id' => $timerId,
            'token'    => $token,
        ];

        return $token;
    }

    /**
     * 释放锁（安全删除 + 清理续期任务）.
     *
     * @param string $key   锁名称
     * @param string $token 加锁时返回的 token
     */
    public static function unlock(string $key, string $token): void
    {
        $fullKey = "lock:{$key}";

        // Lua 脚本：原子判断并删除
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        try {
            self::exec(function (\Redis $r) use ($script, $fullKey, $token) {
                $r->eval($script, [$fullKey, $token], 1);
            });
        } finally {
            // 清理定时器（即使 Redis 删除失败也要清理本地资源）
            if (isset(self::$lockTimers[$fullKey])) {
                Timer::del(self::$lockTimers[$fullKey]['timer_id']);
                unset(self::$lockTimers[$fullKey]);
            }
        }
    }

    /**
     * 事务封装.
     * @return array<mixed>
 */
    public static function transaction(callable $callback): array
    {
        return self::exec(function (\Redis $r) use ($callback) {
            $r->multi();
            $callback($r);
            return $r->exec();
        });
    }

    private static function connect(): void
    {
        foreach (self::$configList as $config) {
            if (! is_array($config)) {
                continue;
            }
            $config = self::applyEnvOverrides($config);
            try {
                $redis = new \Redis();
                $ok    = $redis->connect(
                    (string) $config['host'],
                    (int) ($config['port'] ?? 6379),
                    (float) ($config['timeout'] ?? 2.0)
                );
                if (! $ok) {
                    throw new \RuntimeException("Connect fail: {$config['host']}");
                }
                if (! empty($config['password'])) {
                    $redis->auth($config['password']);
                }
                $redis->select((int) ($config['database'] ?? 0));
                $redis->ping(); // 验证连接
                self::$redis = $redis;
                return;
            } catch (\Throwable $e) {
                error_log('[Redis] Fail connect node: ' . $e->getMessage());
            }
        }
        throw new \RuntimeException('No Redis node available.');
    }

    /**
     * 以 .env REDIS_* 覆盖节点参数，避免 config_cache 冻住旧的 database=0。
     *
     * @param array<mixed> $config
     * @return array<mixed>
     */
    private static function applyEnvOverrides(array $config): array
    {
        if (! function_exists('env')) {
            return $config;
        }
        $host = env('REDIS_HOST');
        if (is_string($host) && $host !== '') {
            $config['host'] = $host;
        }
        $port = env('REDIS_PORT');
        if ($port !== null && $port !== false && $port !== '') {
            $config['port'] = (int) $port;
        }
        $password = env('REDIS_PASSWORD');
        if ($password !== null && $password !== false) {
            $config['password'] = $password === '' ? null : $password;
        }
        $database = env('REDIS_DB');
        if ($database !== null && $database !== false && $database !== '') {
            $config['database'] = (int) $database;
        }
        return $config;
    }

    private static function ensureSelectedDb(\Redis $redis): void
    {
        $db = self::resolvedDatabase();
        if ((int) $redis->getDbNum() === $db) {
            return;
        }
        $redis->select($db);
    }

    private static function resolvedDatabase(): int
    {
        if (function_exists('env')) {
            $db = env('REDIS_DB');
            if ($db !== null && $db !== false && $db !== '') {
                return (int) $db;
            }
        }
        $first = self::$configList[0] ?? [];
        return is_array($first) ? (int) ($first['database'] ?? 0) : 0;
    }

    /**
     * 通用执行器（带重试与故障转移）.
     
     * @return mixed
*/
    private static function exec(callable $fn)
    {
        $attempt = 0;
        while ($attempt < self::$maxRetries) {
            try {
                if (! self::$redis || ! self::$redis->isConnected()) {
                    self::connect();
                } else {
                    self::ensureSelectedDb(self::$redis);
                }
                return $fn(self::$redis);
            } catch (\Throwable $e) {
                ++$attempt;
                if ($attempt >= self::$maxRetries) {
                    throw $e;
                }
                usleep((int) (self::$retryDelay * 1_000_000));
                self::connect();
            }
        }
        throw new \RuntimeException('Redis operation failed after retries.');
    }
}
