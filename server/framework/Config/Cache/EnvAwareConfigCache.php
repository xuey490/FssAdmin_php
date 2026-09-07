<?php

declare(strict_types=1);

namespace Framework\Config\Cache;

/**
 * 配置缓存签名包含 .env，避免 REDIS_DB 等环境变量变更后仍命中旧快照。
 */
class EnvAwareConfigCache extends ConfigCache
{
    /**
     * @param array<int, string> $files
     */
    public function setConfigFiles(array $files): void
    {
        $envFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envFile)) {
            $files[] = $envFile;
        }
        parent::setConfigFiles($files);
    }
}
