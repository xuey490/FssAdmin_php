<?php

declare(strict_types=1);

/**
 * 操作日志中间件
 * 记录所有非白名单的 POST/PUT/PATCH/DELETE 请求
 *
 * @package App\Middlewares
 */

namespace App\Middlewares;

use App\Models\SysOperationLog;
use App\Services\IpLocationService;
use Framework\Tenant\JwtTenantContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OperationLogMiddleware
{
    /**
     * IP地理位置服务
     * @var IpLocationService
     * @return mixed
     */
    protected IpLocationService $ipLocationService;
	
    /**
     * 白名单路径前缀（不记录操作日志）
     * @return mixed
     * @var array<array-key, mixed>
     */
    protected array $whitelist = [
        '/api/core/login',
        '/api/core/logout',
        '/api/core/refresh',
        '/api/core/captcha',
        '/api/core/logs/',
        '/api/system/monitor',
        '/api/system/redis',
        '/api/system/database',
    ];

    /**
     * 构造函数
     * @return mixed
     */
    public function __construct()
    {
        $this->ipLocationService = new IpLocationService();
    }
	
    public function handle(Request $request, callable $next): Response
    {
        $startTime = microtime(true);
        $response  = $next($request);

        // 只记录写操作
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        // 白名单跳过
        $path = $request->getPathInfo();
        foreach ($this->whitelist as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $response;
            }
        }
		$duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->writeLog($request, $response, $duration);

        return $response;
    }

    protected function writeLog(Request $request, Response $response, float $duration): void
    {
        try {
            $user      = $request->attributes->get('user', []);
            
            // 当 AuthMiddleware 尚未运行时（例如无 #[Auth] 注解的接口），user 属性为空，
            // 此时从 JWT Token 中提取用户信息作为兜底
            if (empty($user['username']) && empty($user['name'])) {
                $user = $this->resolveUserFromToken($request);
            }
            
            $userAgent = $request->headers->get('User-Agent', '');
            $ip        = $request->headers->get('CF-Connecting-IP') ?? $request->getClientIp();
			$location  = $this->ipLocationService->getLocation($ip);

            // 获取请求数据（合并 POST body 和 JSON body）
            $params = $request->request->all();
            if (empty($params)) {
                $raw = $request->getContent();
                if (!empty($raw)) {
                    $decoded = json_decode($raw, true);
                    $params  = is_array($decoded) ? $decoded : [];
                }
            }
            $requestData = $this->encodeRequestData($params);

            // 解析路由名称作为 service_name
            $routeInfo   = $request->attributes->get('_route');
            $routeName   = '';
            if (is_array($routeInfo)) {
                $routeName = $routeInfo['params']['_route_name'] ?? ($routeInfo['name'] ?? '');
            } elseif (is_string($routeInfo)) {
                $routeName = $routeInfo;
            }

            SysOperationLog::record([
                'username'     => $user['username'] ?? ($user['name'] ?? ''),
                'app'          => 'system',
                'method'       => strtoupper($request->getMethod()),
                'router'       => $request->getPathInfo(),
                'service_name' => $routeName,
                'ip'           => $ip,
                'ip_location'  => $location,
                'request_data' => $requestData,
                'created_by'   => $user['id'] ?? 0,
				'duration'	   => $duration,
            ]);
        } catch (\Throwable $e) {
            // 日志写入失败不影响主流程，开发环境下输出错误
            if (env('APP_DEBUG', false)) {
                error_log('[OperationLogMiddleware] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
    }

    /**
     * 脱敏并按 UTF-8 字符边界截断请求体，避免 substr 切断汉字触发 MySQL 1366。
     *
     * @param array<array-key, mixed> $params
     * @return string
     */
    protected function encodeRequestData(array $params): string
    {
        unset($params['password'], $params['old_password'], $params['new_password']);
        $params = $this->truncateLargeFields($params);
        $json = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) {
            return '{}';
        }

        $maxBytes = 2000;
        if (strlen($json) <= $maxBytes) {
            return $json;
        }

        return mb_strcut($json, 0, $maxBytes, 'UTF-8') . '...';
    }

    /**
     * @param array<array-key, mixed> $params
     * @return array<array-key, mixed>
     */
    protected function truncateLargeFields(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->truncateLargeFields($value);
                continue;
            }
            if (is_string($value) && strlen($value) > 200) {
                $params[$key] = mb_strcut($value, 0, 200, 'UTF-8') . '...';
            }
        }

        return $params;
    }

    /**
     * 从 JWT Token 中解析用户信息（当 AuthMiddleware 尚未注入 user 属性时兜底使用）
     *
     * @param Request $request
     * @return array{username: string, id: int}
     */
    protected function resolveUserFromToken(Request $request): array
    {
        $token = $this->extractAccessToken($request);
        if ($token === null || $token === '') {
            return ['username' => '', 'id' => 0];
        }

        try {
            $parsed = app('jwt')->parseForAccess($token);
            $claims = $parsed->claims();
            return [
                'username' => $claims->get('name') ?? '',
                'id'       => (int) ($claims->get('uid') ?? 0),
            ];
        } catch (\Throwable) {
            return ['username' => '', 'id' => 0];
        }
    }

    /**
     * 从请求中提取 access_token（优先 Authorization 头，其次 Cookie）
     *
     * @param Request $request
     * @return string|null
     */
    protected function extractAccessToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if ($header !== null && $header !== '' && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $cookieToken = $request->cookies->get('access_token');
        return is_string($cookieToken) && $cookieToken !== '' ? $cookieToken : null;
    }
}