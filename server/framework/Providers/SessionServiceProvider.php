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

namespace Framework\Providers;

// use Framework\Config\ConfigService;
use Framework\Container\ServiceProviderInterface;
use Framework\Session\FileSessionHandler;
use Framework\Session\RedisGroupSessionHandler;
use Framework\Utils\RedisFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
* 注册全局的session服务
*/
final class SessionServiceProvider implements ServiceProviderInterface
{
    // public function __invoke(ContainerConfigurator $configurator): void
    public function register(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();

		$cacheFile = BASE_PATH . '/storage/cache/config_cache.php';
		$cache = new \Framework\Config\Cache\EnvAwareConfigCache($cacheFile, 300);
		$load = new \Framework\Config\ConfigService( BASE_PATH . '/config', $cache, null , ['routes.php', 'services.php']);

        // === 1. 加载配置 ===
        // redis 连接以 .env 为准，不走可能冻住 REDIS_DB=0 的 config_cache
        $redisConfig   = require BASE_PATH . '/config/redis.php';
        $sessionConfig = $load->get('session'); // require \dirname(__DIR__, 2) . '/config/session.php';

        $storageType     = $sessionConfig['storage_type']          ?? 'file';
        $sessionOptions  = $sessionConfig['options']               ?? [];
        $fileSavePath    = $sessionConfig['file_save_path']        ?? '%kernel.project_dir%/storage/sessions';
        $groupPrefix     = $sessionConfig['redis']['group_prefix'] ?? 'session:default';
        $ttl             = $sessionConfig['redis']['ttl']          ?? 3600;

        // === 2. 注册 Redis 客户端（只有 Redis 模式需要）===
        // if (in_array($storageType, ['redis', 'redis_grouped'], true)) {
        // 注册 RedisFactory 服务
        $services->set('redis.client', \Redis::class)
            ->factory([RedisFactory::class, 'createRedisClient']) // 工厂方法放在自身
            ->args([$redisConfig['nodes'] ?? $redisConfig])       // 兼容新旧配置格式
            ->public();

        // 或者注册封装类别名
        $services
            ->alias('redis', 'redis.client')
            ->public();
        // }

        // === 3. 注册 Session Handler & Storage ===
        switch ($storageType) {
            case 'redis_grouped':
                // ✅ 使用分组 Redis 存储
                $services->set('session.handler', RedisGroupSessionHandler::class)
                    ->args([
                        service('redis.client'),
                        [
                            'group_prefix'   => $groupPrefix,
                            'ttl'            => $ttl,
                            'prefix'         => 'redis_grouped_',         // 普通前缀
                            'locking'        => true,          // 启用显式锁（默认 true）
                            'spin_lock_wait' => 150000,        // 自旋等待 microseconds
                            'lock_ttl'       => 30000,         // 锁过期时间 ms（用于 SET PX）
                        ],
                    ])
                    ->public();

                $services->set('session.storage', NativeSessionStorage::class)
                    ->args([$sessionOptions, service('session.handler')])
                    ->public();
                break;
            case 'redis':
                // ✅ 普通 Redis 存储
                $services->set('session.handler', RedisSessionHandler::class)
                    ->args([
                        service('redis.client'),
                        [
                            'prefix' => 'redis_session_',	// 前缀
                            'ttl'    => $ttl,
                        ],
                    ])
                    ->public();

                $services->set('session.storage', NativeSessionStorage::class)
                    ->args([$sessionOptions, service('session.handler')])
                    ->public();
                break;
            case 'file':
            default:
                // ✅ 文件存储（自定义 FileSessionHandler）
                $services->set('session.handler', FileSessionHandler::class)
                    ->call('setSavePath', [$fileSavePath])
                    ->call('setPrefix', [$sessionOptions['name'] ?? 'sess'])
                    ->public();

                $services->set('session.handler.strict', StrictSessionHandler::class)
                    ->args([service('session.handler')])
                    ->public();

                $services->set('session.storage', NativeSessionStorage::class)
                    ->args([$sessionOptions, service('session.handler.strict')])
                    ->public();
                break;
        }

        // === 4. 注册 Session 服务 ===
        $services->set('session', Session::class)
            ->args([service('session.storage')])
            ->public();
    }

    public function boot(ContainerInterface $container): void
    # public function boot(ContainerConfigurator $container): void
    {}
}
