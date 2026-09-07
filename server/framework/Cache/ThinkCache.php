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

namespace Framework\Cache;

use think\facade\Cache;

/**
 * ThinkCache 工厂类
 * 
 * 用于创建 ThinkPHP 缓存的适配器实例，封装 ThinkPHP Cache 门面。
 * 支持根据配置切换不同的缓存存储驱动（如 redis、file 等）。
 * 
 * 使用示例：
 * ```php
 * // 创建工厂实例
 * $factory = new ThinkCache(require BASE_PATH . '/config/cache.php');
 * 
 * // 创建默认缓存驱动
 * $cache = $factory->create();
 * 
 * // 创建指定存储驱动
 * $redisCache = $factory->create('redis');
 * $fileCache  = $factory->create('file');
 * ```
 *
 * @package Framework\Cache
 * @author  xuey863toy
 */
/*
# thinkcache测试
#$cache = app('cache');
#$cache->set('test1', ['name' => 'mike'], 3600);
#$test1 =$cache->get('test1');
//$test1 = $cache->clear();



//cache('foo', 'bar', 120);  // set
//echo cache('foo');         // get

//$factory = new \Framework\Cache\ThinkCache(require BASE_PATH . '/config/cache.php');
//$redisCache = $factory->create('redis'); // ✅ 成功
//$redisCache ->set('foo111', 'bar', 120);

//$ca = app(\Framework\Cache\ThinkCache::class)->create('redis');
//$ca->set('xxxx', 'bar', 120);

// File
$fileCache = $factory->create('file');
$fileCache->set('foot', 'bar___11');
echo $fileCache->get('foot');


*/
class ThinkCache
{
    /**
     * 缓存配置数组
     * 
     * 包含 default（默认驱动）、stores（各驱动详细配置）等配置项，
     * 用于初始化 ThinkPHP Cache 门面。
     *
     * @var array<mixed> */
    private array $config;

    /**
     * 构造函数
     * 
     * 初始化 ThinkCache 工厂实例，配置 ThinkPHP Cache 门面。
     *
     * @param array<mixed> $config 缓存配置数组，包含 default 和 stores 配置
     */
    public function __construct(array $config = [])
    {
        $this->syncRedisSelect($config);
        $this->config = $config;
        Cache::config($config);
    }

    /*
    public function __construct(protected array $config = [])
    {
    }


    public function create(): ThinkAdapter
    {
        // ✅ 直接用配置创建 Cache 实例
        $cache = new Cache($this->config);

        // ✅ 再包装成框架的适配器
        return new ThinkAdapter($cache);
    }
    */

    /**
     * 创建缓存适配器实例
     * 
     * 根据 store 参数创建指定驱动类型的缓存适配器。
     * 如果未指定 store，则使用配置中的默认驱动。
     * 
     * 支持的存储驱动取决于 ThinkPHP 配置中的 stores 定义，
     * 通常包括：redis、file、memcache 等。
     *
     * @param string|null $store 存储驱动名称，如 'redis'、'file'，为 null 时使用默认驱动
     * 
     * @return ThinkAdapter ThinkCache 适配器实例，可进行标准的缓存操作
     */
    /*
    *
    工厂支持切换 store（即选择 redis 或 file）
    $redisCache = $container->get(ThinkCache::class)->create('redis');
    $fileCache  = $container->get(ThinkCache::class)->create('file');
    *
    */
    public function create(?string $store = null): ThinkAdapter
    {
        $cache = Cache::connect($this->config['stores'][$this->config['default']]);
        if ($store) {
            $cache = Cache::store($store); // $cache->store($store);
        }
        return new ThinkAdapter($cache);
    }

    /**
     * think-cache phpredis 用 select 选库，忽略 database；与 Symfony CacheFactory 的 database 对齐。
     *
     * @param array<mixed> $config
     */
    private function syncRedisSelect(array &$config): void
    {
        foreach ($config['stores'] ?? [] as &$store) {
            if (($store['driver'] ?? '') !== 'redis') {
                continue;
            }
            if (! isset($store['select']) && isset($store['database'])) {
                $store['select'] = (int) $store['database'];
            }
        }
        unset($store);
    }
}
