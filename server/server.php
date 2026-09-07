#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * server.php
 * Workerman wrapper for FSSPHP with WebSocket support
 *
 * Usage:
 *   php server.php start          - Start in debug mode (foreground)
 *   php server.php start -d       - Start in daemon mode (background)
 *   php server.php stop           - Stop server
 *   php server.php restart        - Restart server
 *   php server.php reload         - Reload business logic
 *   php server.php status         - Show server status
 *   php server.php connections    - Show connections
 *
 * Services:
 *   - HTTP Server: http://0.0.0.0:8000
 *   - WebSocket Server: ws://0.0.0.0:1234 (or wss:// with SSL)
 */

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Workerman\Timer;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Framework\Core\Framework;
use Framework\Schema\SchemaWarmup;
use Framework\Schema\SchemaRegistry;
use Framework\Utils\WorkermanHealth;
use Framework\Pool\RedisPool;
use Framework\Pool\MysqlPool;
use Framework\Pool\PoolManager;
use Framework\Queue\RedisConsumerService;
use App\Queue\Handlers\DefaultMessageHandler;
use App\Queue\Handlers\ArticleMessageHandler;

// 只允许 CLI 模式运行
if (php_sapi_name() !== 'cli') {
    return;
}

define('WORKERMAN_ENV', true);
define('BASE_PATH', __DIR__);
define('APP_ROOT', __DIR__);
define('LOG_DIR', APP_ROOT . '/storage/workerman');
define('HEALTH_FILE', LOG_DIR . '/health.json');
define('WS_LOG_FILE', LOG_DIR . '/websocket.log');

// 创建日志目录
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}

const MEMORY_LIMIT_MB = 256;
const MEMORY_CHECK_INTERVAL = 10;

require_once __DIR__ . '/vendor/autoload.php';

// 设置日志文件
Worker::$logFile = LOG_DIR . '/workerman.log';
if (DIRECTORY_SEPARATOR === '\\') {
    Worker::$logFileMaxSize = 0;
    $staleTmp = LOG_DIR . '/workerman.log.tmp';
    if (is_file($staleTmp)) {
        @unlink($staleTmp);
    }
}
// ----------------------------------------------------------------------
// 日志工具
// ----------------------------------------------------------------------
function log_info(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_DIR . '/server.log', $line, FILE_APPEND);
}

function ws_log(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(WS_LOG_FILE, $line, FILE_APPEND);
}

// ----------------------------------------------------------------------
// 健康检查与日志轮转
// ----------------------------------------------------------------------
function update_health(?Worker $worker = null): void {
    $snapshot = WorkermanHealth::snapshot($worker?->id, $worker?->name);
    WorkermanHealth::writeHealthFile(HEALTH_FILE, $snapshot);
    WorkermanHealth::appendMemoryHistory(LOG_DIR, $snapshot, $worker?->name ?? 'http');
}

function rotate_logs(): void {
    $files = [
        LOG_DIR . '/server.log',
        WS_LOG_FILE
    ];
    
    foreach ($files as $file) {
        if (file_exists($file) && filesize($file) > 2 * 1024 * 1024) {
            $new = LOG_DIR . '/' . basename($file, '.log') . '-' . date('Ymd_His') . '.log';
            rename($file, $new);
            log_info("[LogRotate] Rotated to $new");
        }
    }
}

// ----------------------------------------------------------------------
// Symfony Request / Response 转换
// ----------------------------------------------------------------------
function convert_to_workerman_response(SymfonyResponse $res): WorkermanResponse {
    $headers = [];

    foreach ($res->headers->allPreserveCase() as $name => $values) {
        if (strtolower($name) === 'set-cookie') {
            $headers[$name] = $values;
        } else {
            $headers[$name] = is_array($values) ? implode(', ', $values) : $values;
        }
    }

    $content = $res->getContent();

    // 移除可能存在的 Content-Length 头，让 Workerman 自动计算
    if (isset($headers['content-length'])) {
        unset($headers['content-length']);
    }

    return new WorkermanResponse($res->getStatusCode(), $headers, $content);
}

/**
 * 将 Workerman Request 转换为 Symfony Request
 */
function convert_to_symfony_request(WorkermanRequest $request): SymfonyRequest
{
    $method = strtoupper($request->method());
    $uri = $request->uri();
    $rawBody = $request->rawBody();
    $remoteIp = $request->connection?->getRemoteIp() ?? '127.0.0.1';
    $remotePort = $request->connection?->getRemotePort() ?? 0;

    $uriParts = parse_url($uri);
    $pathInfo = $uriParts['path'] ?? '/';
    $queryString = $uriParts['query'] ?? '';

    $get = $request->get() ?? [];
    if (!empty($queryString)) {
        parse_str($queryString, $queryParams);
        $get = array_merge($queryParams, $get);
    }

    $post = $request->post() ?? [];
    $cookies = $request->cookie() ?? [];
    
    // 处理上传文件
    $symfonyFiles = [];
    $wmFiles = $request->file() ?? [];
    
    foreach ($wmFiles as $field => $fileInfo) {
        // 单文件
        if (isset($fileInfo['tmp_name'])) {
            if (!empty($fileInfo['tmp_name']) && file_exists($fileInfo['tmp_name'])) {
                $symfonyFiles[$field] = new UploadedFile(
                    $fileInfo['tmp_name'],
                    $fileInfo['name'] ?? '',
                    $fileInfo['type'] ?? null,
                    $fileInfo['error'] ?? UPLOAD_ERR_OK,
                    true
                );
            }
            continue;
        }

        // 多文件
        if (is_array($fileInfo)) {
            $files = [];
            foreach ($fileInfo as $index => $item) {
                if (!isset($item['tmp_name']) || empty($item['tmp_name']) || !file_exists($item['tmp_name'])) {
                    continue;
                }
                $files[$index] = new UploadedFile(
                    $item['tmp_name'],
                    $item['name'] ?? '',
                    $item['type'] ?? null,
                    $item['error'] ?? UPLOAD_ERR_OK,
                    true
                );
            }
            if ($files) {
                $symfonyFiles[$field] = $files;
            }
        }
    }

    $headers = $request->header() ?? [];
    $parameters = array_merge($get, $post);

    $server = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $uri,
        'PATH_INFO' => $pathInfo,
        'QUERY_STRING' => $queryString,
        'REMOTE_ADDR' => $remoteIp,
        'REMOTE_PORT' => $remotePort,
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'HTTP_HOST' => $headers['host'] ?? 'localhost',
        'CONTENT_LENGTH' => $headers['content-length'] ?? strlen($rawBody),
        'CONTENT_TYPE' => $headers['content-type'] ?? '',
        'PHP_SELF' => $pathInfo,
        'SCRIPT_NAME' => $pathInfo,
        'SCRIPT_FILENAME' => '',
    ];

    foreach ($headers as $name => $value) {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $server[$key] = is_array($value) ? implode(', ', $value) : $value;
    }

    if (!isset($server['HTTP_X_FORWARDED_FOR'])) {
        $server['HTTP_X_FORWARDED_FOR'] = $remoteIp;
    }

    if (in_array($method, ['PUT', 'DELETE', 'PATCH']) && empty($post) && !empty($rawBody)) {
        parse_str($rawBody, $parsedPost);
        $post = array_merge($post, $parsedPost);
    }

    return new SymfonyRequest(
        $get,
        $post,
        [],
        $cookies,
        $symfonyFiles,
        $server,
        $rawBody
    );
}

/**
 * 浏览器路径 → public 下的候选文件。/api/uploads 去掉 /api 后落到 public/uploads。
 */
function resolve_public_file_path(string $pathInfo): ?string
{
    if ($pathInfo === '') {
        return null;
    }
    $mapped = str_starts_with($pathInfo, '/api/uploads') ? substr($pathInfo, 4) : $pathInfo;
    $staticDirs = ['/uploads', '/assets', '/css', '/js', '/images', '/favicon.ico'];
    foreach ($staticDirs as $dir) {
        if (str_starts_with($mapped, $dir)) {
            return __DIR__ . '/public' . $mapped;
        }
    }

    return null;
}

/**
 * 获取文件的 MIME 类型
 */
function get_mime_type(string $filePath): string
{
    $mimeTypes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'bmp'  => 'image/bmp',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogg'  => 'video/ogg',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'zip'  => 'application/zip',
        'rar'  => 'application/vnd.rar',
        '7z'   => 'application/x-7z-compressed',
        'tar'  => 'application/x-tar',
        'gz'   => 'application/gzip',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
    ];
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

// ----------------------------------------------------------------------
// WebSocket 连接管理器
// ----------------------------------------------------------------------
class WebSocketManager
{
    private static ?WebSocketManager $instance = null;
    private array $connections = []; // 存储所有连接
    private array $rooms = []; // 存储房间信息
    
    public static function getInstance(): WebSocketManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 添加连接
     */
    public function addConnection(TcpConnection $connection): void
    {
        $this->connections[$connection->id] = [
            'connection' => $connection,
            'user_id' => null,
            'rooms' => [],
            'data' => [],
            'connected_at' => time()
        ];
        
        ws_log("[WS] Connection #{$connection->id} added. Total: " . count($this->connections));
    }
    
    /**
     * 移除连接
     */
    public function removeConnection(TcpConnection $connection): void
    {
        $connId = $connection->id;
        
        if (isset($this->connections[$connId])) {
            // 从所有房间中移除
            foreach ($this->connections[$connId]['rooms'] as $roomId) {
                $this->leaveRoom($connection, $roomId);
            }
            
            unset($this->connections[$connId]);
            ws_log("[WS] Connection #{$connId} removed. Total: " . count($this->connections));
        }
    }
    
    /**
     * 绑定用户ID
     */
    public function bindUser(TcpConnection $connection, $userId): void
    {
        if (isset($this->connections[$connection->id])) {
            $this->connections[$connection->id]['user_id'] = $userId;
            ws_log("[WS] Connection #{$connection->id} bound to user #{$userId}");
        }
    }
    
    /**
     * 加入房间
     */
    public function joinRoom(TcpConnection $connection, string $roomId): void
    {
        if (!isset($this->connections[$connection->id])) {
            return;
        }
        
        // 添加到房间的连接列表
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }
        $this->rooms[$roomId][$connection->id] = true;
        
        // 添加到连接的房间列表
        $this->connections[$connection->id]['rooms'][$roomId] = true;
        
        ws_log("[WS] Connection #{$connection->id} joined room '{$roomId}'. Room size: " . count($this->rooms[$roomId]));
    }
    
    /**
     * 离开房间
     */
    public function leaveRoom(TcpConnection $connection, string $roomId): void
    {
        if (!isset($this->connections[$connection->id])) {
            return;
        }
        
        // 从房间中移除
        if (isset($this->rooms[$roomId][$connection->id])) {
            unset($this->rooms[$roomId][$connection->id]);
            if (empty($this->rooms[$roomId])) {
                unset($this->rooms[$roomId]);
            }
        }
        
        // 从连接的房间列表中移除
        unset($this->connections[$connection->id]['rooms'][$roomId]);
        
        ws_log("[WS] Connection #{$connection->id} left room '{$roomId}'");
    }
    
    /**
     * 发送消息给指定连接
     */
    public function sendToConnection(TcpConnection $connection, array $data): void
    {
        $connection->send(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 发送消息给指定用户
     */
    public function sendToUser($userId, array $data): int
    {
        $count = 0;
        foreach ($this->connections as $connData) {
            if ($connData['user_id'] === $userId) {
                $connData['connection']->send(json_encode($data, JSON_UNESCAPED_UNICODE));
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * 发送消息到房间
     */
    public function sendToRoom(string $roomId, array $data, ?TcpConnection $exclude = null): int
    {
        if (!isset($this->rooms[$roomId])) {
            return 0;
        }
        
        $count = 0;
        $message = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        foreach ($this->rooms[$roomId] as $connId => $true) {
            if ($exclude && $connId === $exclude->id) {
                continue;
            }
            
            if (isset($this->connections[$connId])) {
                $this->connections[$connId]['connection']->send($message);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * 广播消息给所有连接
     */
    public function broadcast(array $data, ?TcpConnection $exclude = null): int
    {
        $count = 0;
        $message = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        foreach ($this->connections as $connId => $connData) {
            if ($exclude && $connId === $exclude->id) {
                continue;
            }
            
            $connData['connection']->send($message);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * 获取在线连接数
     */
    public function getOnlineCount(): int
    {
        return count($this->connections);
    }
    
    /**
     * 获取房间信息
     */
    public function getRoomInfo(string $roomId): ?array
    {
        if (!isset($this->rooms[$roomId])) {
            return null;
        }
        
        $connections = [];
        foreach ($this->rooms[$roomId] as $connId => $true) {
            if (isset($this->connections[$connId])) {
                $connections[] = [
                    'id' => $connId,
                    'user_id' => $this->connections[$connId]['user_id'],
                    'connected_at' => $this->connections[$connId]['connected_at']
                ];
            }
        }
        
        return [
            'room_id' => $roomId,
            'count' => count($connections),
            'connections' => $connections
        ];
    }
    
    /**
     * 获取所有房间
     */
    public function getAllRooms(): array
    {
        return array_keys($this->rooms);
    }
}

// ----------------------------------------------------------------------
// 创建 HTTP Worker
// ----------------------------------------------------------------------
$httpWorker = new Worker('http://0.0.0.0:8000');
$httpWorker->name = 'FSSPHP-HTTP';
$httpWorker->count = 4;

// 存储 Framework 实例
$framework = null;

// ----------------------------------------------------------------------
// HTTP Worker 启动回调
// ----------------------------------------------------------------------
$httpWorker->onWorkerStart = function(Worker $worker) use (&$framework) {
    log_info("[HTTP-Worker] PID " . getmypid() . " started");
    Worker::log("[HTTP-Worker] PID " . getmypid() . " started");
    update_health();

    // 初始化框架
    $framework = Framework::getInstance();

    // Schema 预热
    if (defined('WORKERMAN_ENV')) {
        SchemaWarmup::setScanPath(base_path('app/Models'), 'App\Models');
        SchemaWarmup::ignore([
            \App\Models\TempView::class,
        ]);
        SchemaWarmup::warmupAll();
        SchemaRegistry::freeze();
    }
    
    // ---------------------------------------------------------------
    // 连接池初始化（每个 Worker 进程独立持有，不跨进程共享）
    // ---------------------------------------------------------------
    try {
        $redisConfig   = require BASE_PATH . '/config/redis.php';
        $databaseConfig = require BASE_PATH . '/config/database.php';

        // --- Redis 连接池 ---
        if (!empty($redisConfig['pool']['enabled'])) {
            $primaryNode = $redisConfig['nodes'][0] ?? [];
            $redisPoolConfig = array_merge($primaryNode, $redisConfig['pool']);
            PoolManager::register('redis.default', new RedisPool($redisPoolConfig));
            log_info(sprintf(
                '[HTTP-Worker #%d] Redis 连接池已初始化，空闲：%d / 最大：%d',
                $worker->id,
                $redisPoolConfig['min_connections'] ?? 2,
                $redisPoolConfig['max_connections'] ?? 10
            ));
        }

        // --- MySQL 连接池 ---
        if (!empty($databaseConfig['pool']['enabled'])) {
            $mysqlConn      = $databaseConfig['connections']['mysql'] ?? [];
            $mysqlPoolConfig = array_merge([
                'host'     => $mysqlConn['hostname'] ?? '127.0.0.1',
                'port'     => (int) ($mysqlConn['hostport'] ?? 3306),
                'database' => $mysqlConn['database'] ?? 'fssoa',
                'username' => $mysqlConn['username'] ?? 'root',
                'password' => $mysqlConn['password'] ?? '',
                'charset'  => $mysqlConn['charset']  ?? 'utf8mb4',
            ], $databaseConfig['pool']);
            PoolManager::register('mysql.default', new MysqlPool($mysqlPoolConfig));
            log_info(sprintf(
                '[HTTP-Worker #%d] MySQL 连接池已初始化，空闲：%d / 最大：%d',
                $worker->id,
                $mysqlPoolConfig['min_connections'] ?? 2,
                $mysqlPoolConfig['max_connections'] ?? 10
            ));
        }
    } catch (\Throwable $e) {
        log_info('[HTTP-Worker] 连接池初始化失败（降级为直连）：' . $e->getMessage());
    }

    // 定时任务：内存监控、日志轮转、健康检查
    Timer::add(MEMORY_CHECK_INTERVAL, function() use ($worker) {
        update_health($worker);
        rotate_logs();
        
        $pid = getmypid();
        $time = date('Y-m-d H:i:s');
        
        $memoryReal  = memory_get_usage(true) / 1048576;
        $memoryEmalloc = memory_get_usage(false) / 1048576;
        $includedFiles = count(get_included_files());
        $classes = count(get_declared_classes());
        $interfaces = count(get_declared_interfaces());
        $traits = count(get_declared_traits());
        $objects = (function() {
            $count = 0;
            foreach (get_defined_vars() as $v) is_object($v) && $count++;
            return $count;
        })();
        
        Worker::log("[{$time}] [Memory] HTTP-Worker #{$worker->id} PID {$pid} "
            . "real:{$memoryReal}MB emalloc:{$memoryEmalloc}MB "
            . "files:{$includedFiles} classes:{$classes} "
            . "interfaces:{$interfaces} traits:{$traits}");

        // 连接池统计日志
        $poolStats = PoolManager::stats();
        if (!empty($poolStats)) {
            $statStr = implode(' ', array_map(
                fn($n, $s) => "{$n}[idle:{$s['idle']} active:{$s['active']} max:{$s['max']}]",
                array_keys($poolStats),
                $poolStats
            ));
            Worker::log("[{$time}] [Pool] HTTP-Worker #{$worker->id} {$statStr}");
        }

        // 内存超限则重启
        if ($memoryReal > MEMORY_LIMIT_MB) {
            Worker::log("[{$time}] [Warning] HTTP-Worker #{$worker->id} PID {$pid} memory exceeded limit ({$memoryReal} MB > " . MEMORY_LIMIT_MB . " MB), stopping...");
            $worker->stop();
        }
    });
};

// ----------------------------------------------------------------------
// HTTP Worker 停止回调（关闭连接池）
// ----------------------------------------------------------------------
$httpWorker->onWorkerStop = function(Worker $worker) {
    log_info(sprintf('[HTTP-Worker #%d] 正在关闭连接池...', $worker->id));
    PoolManager::closeAll();
    log_info(sprintf('[HTTP-Worker #%d] 连接池已关闭', $worker->id));
};

// ----------------------------------------------------------------------
// HTTP 请求处理回调
// ----------------------------------------------------------------------
$httpWorker->onMessage = function(TcpConnection $connection, WorkermanRequest $req) use (&$framework) {
    $symReq = null;
    $symRes = null;
    
    try {
        // ==================== 静态文件处理 ====================
        $uri = $req->uri();
        $pathInfo = parse_url($uri, PHP_URL_PATH);
        $filePath = is_string($pathInfo) ? resolve_public_file_path($pathInfo) : null;

        if ($filePath !== null) {
            $realPath = realpath($filePath);
            $publicDir = realpath(__DIR__ . '/public');
            
            if ($realPath && $publicDir && strpos($realPath, $publicDir) === 0 && is_file($realPath)) {
                $contentType = get_mime_type($realPath);
                $fileContent = file_get_contents($realPath);
                
                $headers = [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'public, max-age=86400',
                ];
                
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $realPath)) {
                    $headers['Cache-Control'] = 'public, max-age=2592000';
                }
                
                $connection->send(new WorkermanResponse(200, $headers, $fileContent));
                return;
            }
            
            $connection->send(new WorkermanResponse(404, ['Content-Type' => 'text/plain'], 'File Not Found'));
            return;
        }
        // ==================== 静态文件处理结束 ====================

        // 健康检查端点
        if ($req->path() === '/_health') {
            update_health();
            $data = file_get_contents(HEALTH_FILE);
            $response = new SymfonyResponse($data, 200, ['Content-Type' => 'application/json']);
            $connection->send(convert_to_workerman_response($response));
            return;
        }
        
        // WebSocket 统计信息端点
        if ($req->path() === '/_ws-stats') {
            $wsManager = WebSocketManager::getInstance();
            $stats = [
                'online_count' => $wsManager->getOnlineCount(),
                'rooms' => $wsManager->getAllRooms(),
                'time' => date('Y-m-d H:i:s')
            ];
            $response = new SymfonyResponse(json_encode($stats), 200, ['Content-Type' => 'application/json']);
            $connection->send(convert_to_workerman_response($response));
            return;
        }

        // 转换请求并处理
        $symReq = convert_to_symfony_request($req);
        $symRes = $framework->handleRequest($symReq);
        
        // 保存 Session 并清理内存，防止跨请求累积
        if ($symReq->hasSession()) {
            $session = $symReq->getSession();
            $session->save();
            $session->clear();
        }
        
        // 发送队列中的 Cookie
        app('cookie')->sendQueuedCookies($symRes);
        
        $connection->send(convert_to_workerman_response($symRes));

    } catch (Throwable $e) {
        $error = "[Error] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
        log_info($error);
        Worker::log($error);
        $connection->send(new WorkermanResponse(500, [], "Internal Error: {$e->getMessage()}"));
    } finally {
        // 清理资源：Request/Response + Session 内存
        if (isset($symReq) && $symReq->hasSession()) {
            $symReq->getSession()->clear();
        }
        unset($symReq, $symRes);
        gc_collect_cycles();
    }
};

// ----------------------------------------------------------------------
// 创建 WebSocket Worker (ws://0.0.0.0:1234)
// ----------------------------------------------------------------------
$wsWorker = new Worker('websocket://0.0.0.0:1234');
$wsWorker->name = 'FSSPHP-WebSocket';
$wsWorker->count = 1;

// 如果需要 SSL/TLS (wss://)，取消下面的注释并配置证书路径
/*
$wsWorker->transport = 'ssl';
$wsWorker->context = [
    'ssl' => [
        'local_cert'  => '/path/to/your/cert.pem',
        'local_pk'    => '/path/to/your/private.key',
        'verify_peer' => false,
    ]
];
*/

// ----------------------------------------------------------------------
// WebSocket Worker 启动回调
// ----------------------------------------------------------------------
$wsWorker->onWorkerStart = function(Worker $worker) {
    ws_log("[WS-Worker] PID " . getmypid() . " started");
    Worker::log("[WS-Worker] PID " . getmypid() . " started");
    
    // 心跳检测定时器
    Timer::add(55, function() use ($worker) {
        $wsManager = WebSocketManager::getInstance();
        $time = date('Y-m-d H:i:s');
        
        foreach ($worker->connections as $connection) {
            // 如果上次心跳时间超过 120 秒，则关闭连接
            if (empty($connection->lastHeartbeatTime)) {
                $connection->lastHeartbeatTime = time();
            } elseif (time() - $connection->lastHeartbeatTime > 120) {
                ws_log("[WS] Connection #{$connection->id} timeout, closing");
                $connection->close();
                continue;
            }
            
            // 发送心跳包
            $connection->send(json_encode(['type' => 'ping']));
        }
        
        ws_log("[{$time}] [WS-Heartbeat] Online: " . $wsManager->getOnlineCount());
    });
};

// ----------------------------------------------------------------------
// WebSocket 连接建立回调
// ----------------------------------------------------------------------
$wsWorker->onConnect = function(TcpConnection $connection) {
    $connection->lastHeartbeatTime = time();
    
    $wsManager = WebSocketManager::getInstance();
    $wsManager->addConnection($connection);
    
    ws_log("[WS] New connection #{$connection->id} from {$connection->getRemoteIp()}");
    
    // 发送欢迎消息
    $wsManager->sendToConnection($connection, [
        'type' => 'connected',
        'data' => [
            'connection_id' => $connection->id,
            'message' => 'Welcome to FSSPHP WebSocket Server',
            'time' => date('Y-m-d H:i:s')
        ]
    ]);
};

// ----------------------------------------------------------------------
// WebSocket 消息接收回调
// ----------------------------------------------------------------------
$wsWorker->onMessage = function(TcpConnection $connection, string $data) {
    $wsManager = WebSocketManager::getInstance();
    
    try {
        // 更新心跳时间
        $connection->lastHeartbeatTime = time();
        
        // 解析消息
        $message = json_decode($data, true);
        
        if (!$message || !isset($message['type'])) {
            $wsManager->sendToConnection($connection, [
                'type' => 'error',
                'data' => ['message' => 'Invalid message format']
            ]);
            return;
        }
        
        $type = $message['type'];
        $payload = $message['data'] ?? [];
        
        ws_log("[WS] Received message type '{$type}' from connection #{$connection->id}");
        
        // 根据消息类型处理
        switch ($type) {
            case 'pong':
                // 心跳响应，已更新心跳时间
                break;
                
            case 'bind':
                // 绑定用户ID
                if (isset($payload['user_id'])) {
                    $wsManager->bindUser($connection, $payload['user_id']);
                    $wsManager->sendToConnection($connection, [
                        'type' => 'bind_success',
                        'data' => ['user_id' => $payload['user_id']]
                    ]);
                }
                break;
                
            case 'join':
                // 加入房间
                if (isset($payload['room_id'])) {
                    $wsManager->joinRoom($connection, $payload['room_id']);
                    
                    // 通知房间内其他人
                    $wsManager->sendToRoom($payload['room_id'], [
                        'type' => 'user_joined',
                        'data' => [
                            'connection_id' => $connection->id,
                            'room_id' => $payload['room_id']
                        ]
                    ], $connection);
                    
                    // 发送确认给当前连接
                    $wsManager->sendToConnection($connection, [
                        'type' => 'join_success',
                        'data' => ['room_id' => $payload['room_id']]
                    ]);
                }
                break;
                
            case 'leave':
                // 离开房间
                if (isset($payload['room_id'])) {
                    $wsManager->leaveRoom($connection, $payload['room_id']);
                    
                    // 通知房间内其他人
                    $wsManager->sendToRoom($payload['room_id'], [
                        'type' => 'user_left',
                        'data' => [
                            'connection_id' => $connection->id,
                            'room_id' => $payload['room_id']
                        ]
                    ], $connection);
                    
                    // 发送确认给当前连接
                    $wsManager->sendToConnection($connection, [
                        'type' => 'leave_success',
                        'data' => ['room_id' => $payload['room_id']]
                    ]);
                }
                break;
                
            case 'message':
                // 发送消息到房间
                if (isset($payload['room_id']) && isset($payload['content'])) {
                    $wsManager->sendToRoom($payload['room_id'], [
                        'type' => 'message',
                        'data' => [
                            'connection_id' => $connection->id,
                            'room_id' => $payload['room_id'],
                            'content' => $payload['content'],
                            'time' => date('Y-m-d H:i:s')
                        ]
                    ]);
                }
                break;
                
            case 'broadcast':
                // 广播消息
                $count = $wsManager->broadcast([
                    'type' => 'broadcast',
                    'data' => [
                        'connection_id' => $connection->id,
                        'content' => $payload['content'] ?? '',
                        'time' => date('Y-m-d H:i:s')
                    ]
                ], $connection);
                
                $wsManager->sendToConnection($connection, [
                    'type' => 'broadcast_success',
                    'data' => ['sent_to' => $count]
                ]);
                break;
                
            case 'private_message':
                // 私聊消息
                if (isset($payload['user_id']) && isset($payload['content'])) {
                    $count = $wsManager->sendToUser($payload['user_id'], [
                        'type' => 'private_message',
                        'data' => [
                            'from_connection_id' => $connection->id,
                            'content' => $payload['content'],
                            'time' => date('Y-m-d H:i:s')
                        ]
                    ]);
                    
                    $wsManager->sendToConnection($connection, [
                        'type' => 'private_message_sent',
                        'data' => [
                            'user_id' => $payload['user_id'],
                            'delivered' => $count > 0
                        ]
                    ]);
                }
                break;
                
            case 'get_room_info':
                // 获取房间信息
                if (isset($payload['room_id'])) {
                    $roomInfo = $wsManager->getRoomInfo($payload['room_id']);
                    $wsManager->sendToConnection($connection, [
                        'type' => 'room_info',
                        'data' => $roomInfo
                    ]);
                }
                break;
                
            case 'get_online_count':
                // 获取在线人数
                $wsManager->sendToConnection($connection, [
                    'type' => 'online_count',
                    'data' => ['count' => $wsManager->getOnlineCount()]
                ]);
                break;
                
            default:
                // 未知消息类型
                $wsManager->sendToConnection($connection, [
                    'type' => 'error',
                    'data' => ['message' => "Unknown message type: {$type}"]
                ]);
        }
        
    } catch (Throwable $e) {
        ws_log("[WS-Error] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        $wsManager->sendToConnection($connection, [
            'type' => 'error',
            'data' => ['message' => 'Internal server error']
        ]);
    }
};

// ----------------------------------------------------------------------
// WebSocket 连接关闭回调
// ----------------------------------------------------------------------
$wsWorker->onClose = function(TcpConnection $connection) {
    $wsManager = WebSocketManager::getInstance();
    $wsManager->removeConnection($connection);
    
    ws_log("[WS] Connection #{$connection->id} closed");
};

// ----------------------------------------------------------------------
// WebSocket 错误回调
// ----------------------------------------------------------------------
$wsWorker->onError = function(TcpConnection $connection, $code, $msg) {
    ws_log("[WS-Error] Connection #{$connection->id} error: {$code} - {$msg}");
};

// ----------------------------------------------------------------------
// 队列消费 Worker（独立进程，避免阻塞 http/wss 业务流）
// ----------------------------------------------------------------------
$redisConfigForQueue = require __DIR__ . '/config/redis.php';

if (!empty($redisConfigForQueue['queue']['enabled'])) {
    $queueConfig   = $redisConfigForQueue['queue'];
    $workerCount   = (int) ($queueConfig['worker_count'] ?? 2);
    $queueWorker   = new Worker();
    $queueWorker->name  = 'FSSPHP-Queue';
    $queueWorker->count = $workerCount;

    $queueWorker->onWorkerStart = function (Worker $worker) use ($redisConfigForQueue, $queueConfig) {
        log_info(sprintf('[Queue-Worker #%d] PID %d 启动', $worker->id, getmypid()));
        Worker::log(sprintf('[Queue-Worker #%d] PID %d 启动', $worker->id, getmypid()));

        // 1. 初始化 Redis 连接池（队列 Worker 独立持有）
        try {
            $primaryNode     = $redisConfigForQueue['nodes'][0] ?? [];
            $redisPoolConfig = array_merge($primaryNode, $redisConfigForQueue['pool'] ?? []);
            PoolManager::register('redis.default', new RedisPool($redisPoolConfig));
            log_info(sprintf('[Queue-Worker #%d] Redis 连接池已初始化', $worker->id));
        } catch (\Throwable $e) {
            log_info(sprintf('[Queue-Worker #%d] Redis 连接池初始化失败：%s', $worker->id, $e->getMessage()));
            return;
        }

        // 2. 注册处理器并启动各队列消费服务
        foreach ($queueConfig['queues'] ?? [] as $qCfg) {
            try {
                $consumer = new RedisConsumerService($qCfg);

                // 注册消息处理器（根据 type 路由到对应 Handler）
                $consumer->registerHandlers([
                    // 默认兜底处理器（type 不匹配时不会路由到此，仅作备用）
                    'default'           => new DefaultMessageHandler(),
                    // 文章业务消息（发布通知 + 浏览统计）
                    'article_published' => new ArticleMessageHandler(),
                    'article_view'      => new ArticleMessageHandler(),
                    // 扩展示例（取消注释并实现对应 Handler 类）：
                    // 'send_email'   => new \App\Queue\Handlers\SendEmailHandler(),
                    // 'send_sms'     => new \App\Queue\Handlers\SendSmsHandler(),
                    // 'export_excel' => new \App\Queue\Handlers\ExportExcelHandler(),
                ]);

                $consumer->start($worker);
                log_info(sprintf(
                    '[Queue-Worker #%d] 队列 [%s] 消费服务已启动',
                    $worker->id,
                    $qCfg['name'] ?? 'unknown'
                ));
            } catch (\Throwable $e) {
                log_info(sprintf(
                    '[Queue-Worker #%d] 队列 [%s] 启动失败：%s',
                    $worker->id,
                    $qCfg['name'] ?? 'unknown',
                    $e->getMessage()
                ));
            }
        }
    };

    $queueWorker->onWorkerStop = function (Worker $worker) {
        log_info(sprintf('[Queue-Worker #%d] 正在停止，关闭连接池...', $worker->id));
        PoolManager::closeAll();
        log_info(sprintf('[Queue-Worker #%d] 已停止', $worker->id));
    };

    $queueWorker->onError = function (TcpConnection $connection, $code, $msg) {
        log_info(sprintf('[Queue-Worker] 错误：%d - %s', $code, $msg));
    };
}

// ----------------------------------------------------------------------
// 运行所有 Worker
// ----------------------------------------------------------------------
Worker::runAll();
