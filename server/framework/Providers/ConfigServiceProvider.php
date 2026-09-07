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

use Framework\Config\ConfigService;
use Framework\Config\Cache\EnvAwareConfigCache;
use Framework\Container\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ConfigServiceProvider implements ServiceProviderInterface
{
    // public function __invoke(ContainerConfigurator $configurator): void
    public function register(ContainerConfigurator $configurator): void
    {
        $services = $configurator->services();
		
		$config_cache = '%kernel.project_dir%/storage/cache/config_cache.php';
		// 注册 config_cache 服务
        $services->set('config_cache', EnvAwareConfigCache::class)
            ->args([
                $config_cache ,
				300
            ])
            ->public();		
		
		
        // 注册 ConfigService 服务
        $services->set('config', ConfigService::class)	// $globalConfig = $this->container->get('config')->load();
            ->args([
                '%kernel.project_dir%/config',
                service('config_cache'),
				null , 
				['routes.php', 'services.php']
            ])
            ->public();  // ($this->container->get(ConfigService::class)->load());

        // 注册 ConfigService 业务类
        $services->set(ConfigService::class)
            ->args([
                '%kernel.project_dir%/config',
                service('config_cache'),
				null , 
				['routes.php', 'services.php']
            ])
            ->public();
    }

    public function boot(ContainerInterface $container): void
    # public function boot(ContainerConfigurator $container): void
    {}
}
