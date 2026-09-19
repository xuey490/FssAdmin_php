<?php

declare(strict_types=1);

/**
 * This file is part of Fssphp Framework.
 *
 * @link     https://github.com/xuey490/project
 * @license  https://github.com/xuey490/project/blob/main/LICENSE
 *
 * @Filename: PluginMarketService.php
 * @Date: 2025-03-31
 * @Developer: Fssphp Team
 * @Email: xuey863toy@gmail.com
 */

namespace Framework\Plugin;

use App\Services\PluginService;
use RuntimeException;

/**
 * 插件市场服务
 *
 * 提供从远程市场发现、搜索、下载、安装插件的功能。
 *
 * @package Framework\Plugin
 */
class PluginMarketService
{
    /**
     * 市场配置
     *
     * @var array<mixed> */
    private array $config;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $configFile = BASE_PATH . '/config/plugin/market.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [
                'official_url' => 'https://market.Fssphp.cn/api',
                'api_key' => '',
                'allow_third_party' => true,
                'third_party_markets' => [],
                'download_path' => BASE_PATH . '/storage/tmp/plugins',
                'timeout' => 30,
                'cache_ttl' => 3600,
            ];
        }

        $this->ensureDownloadDirectory();
    }

    /**
     * 搜索插件
     *
     * @param string $keyword 搜索关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string|null $market 市场地址（null 使用官方市场）
     * @return array<mixed> */
    public function search(string $keyword, int $page = 1, int $limit = 20, ?string $market = null): array
    {
        $baseUrl = $market ?? $this->config['official_url'];

        $response = $this->request('GET', "{$baseUrl}/plugins", [
            'query' => [
                'keyword' => $keyword,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);

        return $response;
    }

    /**
     * 获取插件详情
     *
     * @param string $name 插件名称
     * @param string|null $market 市场地址
     * @return array<mixed> */
    public function detail(string $name, ?string $market = null): array
    {
        $baseUrl = $market ?? $this->config['official_url'];

        $response = $this->request('GET', "{$baseUrl}/plugins/{$name}");

        return $response;
    }

    /**
     * 获取插件版本列表
     *
     * @param string $name 插件名称
     * @param string|null $market 市场地址
     * @return array<mixed> */
    public function versions(string $name, ?string $market = null): array
    {
        $baseUrl = $market ?? $this->config['official_url'];

        $response = $this->request('GET', "{$baseUrl}/plugins/{$name}/versions");

        return $response;
    }

    /**
     * 下载插件包
     *
     * @param string $name 插件名称
     * @param string $version 版本号
     * @param string|null $market 市场地址
     * @return string 下载文件的本地路径
     */
    public function download(string $name, string $version, ?string $market = null): string
    {
        $baseUrl = $market ?? $this->config['official_url'];
        $downloadUrl = "{$baseUrl}/plugins/{$name}/download?version={$version}";

        // 创建临时文件
        $tmpPath = $this->config['download_path'];
        $filename = "{$name}-{$version}.zip";
        $savePath = "{$tmpPath}/{$filename}";

        // 下载文件
        $this->downloadFile($downloadUrl, $savePath);

        return $savePath;
    }

    /**
     * 从市场安装插件
     *
     * @param string $name 插件名称
     * @param string $version 版本号（默认最新版本）
     * @param string|null $market 市场地址
     * @return array<mixed> */
    public function install(string $name, string $version = 'latest', ?string $market = null): array
    {
        try {
            // 1. 获取插件详情
            $detail = $this->detail($name, $market);

            if (!isset($detail['data'])) {
                return ['success' => false, 'message' => '插件不存在'];
            }

            // 确定版本
            if ($version === 'latest') {
                $version = $detail['data']['latest_version'] ?? '1.0.0';
            }

            // 2. 下载插件包
            $zipPath = $this->download($name, $version, $market);

            // 3. 解压安装
            $pluginService = new PluginService();
            $result = $pluginService->uploadAndInstall([
                'name' => "{$name}.zip",
                'tmp_name' => $zipPath,
            ]);

            // 4. 清理临时文件
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            return $result;

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 检查插件更新
     *
     * @param array<mixed> $plugins 插件列表 [['name' => 'blog', 'version' => '1.0.0'], ...]
     * @return array<mixed> */
    public function checkUpdates(array $plugins): array
    {
        $baseUrl = $this->config['official_url'];

        $response = $this->request('POST', "{$baseUrl}/plugins/check-updates", [
            'json' => ['plugins' => $plugins],
        ]);

        return $response;
    }

    /**
     * 获取所有市场列表
     *
     * @return array<mixed> */
    public function getMarkets(): array
    {
        $markets = [
            [
                'name' => '官方市场',
                'url' => $this->config['official_url'],
                'official' => true,
            ],
        ];

        if ($this->config['allow_third_party']) {
            foreach ($this->config['third_party_markets'] as $url) {
                $markets[] = [
                    'name' => parse_url($url, PHP_URL_HOST) ?? $url,
                    'url' => $url,
                    'official' => false,
                ];
            }
        }

        return $markets;
    }

    /**
     * 发送 HTTP 请求
     *
     * @param string $method
     * @param string $url
     * @param array<mixed> $options
     * @return array<mixed> */
    private function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();

        // 基本设置
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // 方法
        $method = strtoupper($method);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // 请求头
        $headers = [
            'Accept: application/json',
            'User-Agent: Fssphp-Plugin-Client/0.8.1',
        ];

        if (!empty($this->config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Query 参数
        if (isset($options['query'])) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($options['query']);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        // JSON body
        if (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, [
                'Content-Type: application/json',
            ]));
        }

        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        unset($ch);

        if ($error) {
            throw new RuntimeException("HTTP 请求失败: {$error}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("HTTP 错误: {$httpCode}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('响应 JSON 解析失败');
        }

        return $data;
    }

    /**
     * 下载文件
     *
     * @param string $url
     * @param string $savePath
     */
    private function downloadFile(string $url, string $savePath): void
    {
        $ch = curl_init($url);

        $fp = fopen($savePath, 'wb');
        if ($fp === false) {
            throw new RuntimeException("无法创建文件: {$savePath}");
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout'] * 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // 认证
        if (!empty($this->config['api_key'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->config['api_key'],
            ]);
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        unset($ch);
        fclose($fp);

        if ($error) {
            unlink($savePath);
            throw new RuntimeException("下载失败: {$error}");
        }

        if ($httpCode >= 400) {
            unlink($savePath);
            throw new RuntimeException("下载失败: HTTP {$httpCode}");
        }
    }

    /**
     * 确保下载目录存在
     */
    private function ensureDownloadDirectory(): void
    {
        $path = $this->config['download_path'];
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new RuntimeException("无法创建下载目录: {$path}");
            }
        }
    }
}
