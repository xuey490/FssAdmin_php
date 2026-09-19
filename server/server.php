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
 *   - HTTP Server: http://0.0.0.0:8000（普通 /api/）
 *   - AI HTTP Server: http://0.0.0.0:8001（仅 /api/ai/；Linux 随本文件启动）
 *   - WebSocket Server: ws://0.0.0.0:1234 (or wss:// with SSL)
 *   - Windows 请另开：php server-ai.php start
 *   - 反代分流见 docs/deploy/ai-http-split.md
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
use Framework\Http\ClientDisconnected;
use Framework\Http\SseEmitter;
use Framework\Http\SseResponse;
use Framework\Schema\SchemaWarmup;
use Framework\Schema\SchemaRegistry;
use Framework\Utils\WorkermanHealth;
use Framework\Pool\RedisPool;
use Framework\Pool\MysqlPool;
use Framework\Pool\PoolManager;
use Framework\Queue\RedisConsumerService;
use App\Queue\Handlers\DefaultMessageHandler;
use App\Queue\Handlers\ArticleMessageHandler;
use Workerman\Protocols\Http\ServerSentEvents;

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
const HTTP_LISTEN_PORT = 8000;
const AI_LISTEN_PORT = 8001;
const HTTP_WORKER_COUNT = 4;
const AI_WORKER_COUNT = 4;

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

function workerman_env_int(string $key, int $default): int
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return (int) $value;
}

function is_ai_http_path(string $path): bool
{
    return $path === '/api/ai' || str_starts_with($path, '/api/ai/');
}

/**
 * @param array<string, mixed>|null $data
 */
function send_workerman_json(TcpConnection $connection, int $status, string $msg, ?array $data = null): void
{
    $connection->send(new WorkermanResponse($status, [
        'Content-Type' => 'application/json; charset=utf-8',
    ], (string) json_encode([
        'code' => $status,
        'msg' => $msg,
        'message' => $msg,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE)));
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

function send_sse_response(TcpConnection $connection, SseResponse $res): void
{
    $headers = [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
        'Connection' => 'keep-alive',
    ];
    foreach ($res->headers->allPreserveCase() as $name => $values) {
        $headerName = (string) $name;
        $lower = strtolower($headerName);
        if ($lower === 'content-length' || $lower === 'content-type' || $lower === 'set-cookie') {
            continue;
        }
        $headers[$headerName] = is_array($values) ? implode(', ', $values) : (string) $values;
    }

    $connection->send(new WorkermanResponse(200, $headers, ''));

    $aborted = false;
    $previousClose = $connection->onClose;
    $connection->onClose = function (TcpConnection $conn) use (&$aborted, $previousClose): void {
        $aborted = true;
        if (is_callable($previousClose)) {
            $previousClose($conn);
        }
    };

    try {
        $res->emit(new class ($connection, $aborted) implements SseEmitter {
            public function __construct(private TcpConnection $connection, private bool &$aborted)
            {
            }

            public function event(string $name, array $data): void
            {
                if ($this->aborted || $this->connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED) {
                    throw new ClientDisconnected('SSE client disconnected');
                }
                $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($payload === false) {
                    $payload = '{}';
                }
                $this->connection->send(new ServerSentEvents([
                    'event' => $name,
                    'data' => $payload,
                ]));
            }
        });
    } catch (ClientDisconnected) {
    }

    $connection->onClose = $previousClose;
    if ($connection->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
        $connection->close();
    }
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

function boot_workerman_app_worker(Worker $worker, string $label): Framework
{
    log_info("[{$label}] PID " . getmypid() . " started");
    Worker::log("[{$label}] PID " . getmypid() . " started");
    update_health($worker);

    $framework = Framework::getInstance();

    if (defined('WORKERMAN_ENV')) {
        SchemaWarmup::setScanPath(base_path('app/Models'), 'App\Models');
        SchemaWarmup::ignore([
            \App\Models\TempView::class,
        ]);
        SchemaWarmup::warmupAll();
        SchemaRegistry::freeze();
    }

    try {
        $redisConfig = require BASE_PATH . '/config/redis.php';
        $databaseConfig = require BASE_PATH . '/config/database.php';

        if (!empty($redisConfig['pool']['enabled'])) {
            $primaryNode = $redisConfig['nodes'][0] ?? [];
            $redisPoolConfig = array_merge($primaryNode, $redisConfig['pool']);
            PoolManager::register('redis.default', new RedisPool($redisPoolConfig));
            log_info(sprintf(
                '[%s #%d] Redis 连接池已初始化，空闲：%d / 最大：%d',
                $label,
                $worker->id,
                $redisPoolConfig['min_connections'] ?? 2,
                $redisPoolConfig['max_connections'] ?? 10
            ));
        }

        if (!empty($databaseConfig['pool']['enabled'])) {
            $mysqlConn = $databaseConfig['connections']['mysql'] ?? [];
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
                '[%s #%d] MySQL 连接池已初始化，空闲：%d / 最大：%d',
                $label,
                $worker->id,
                $mysqlPoolConfig['min_connections'] ?? 2,
                $mysqlPoolConfig['max_connections'] ?? 10
            ));
        }
    } catch (\Throwable $e) {
        log_info("[{$label}] 连接池初始化失败（降级为直连）：" . $e->getMessage());
    }

    Timer::add(MEMORY_CHECK_INTERVAL, function () use ($worker, $label) {
        update_health($worker);
        rotate_logs();

        $pid = getmypid();
        $time = date('Y-m-d H:i:s');
        $memoryReal = memory_get_usage(true) / 1048576;
        $memoryEmalloc = memory_get_usage(false) / 1048576;
        $includedFiles = count(get_included_files());
        $classes = count(get_declared_classes());
        $interfaces = count(get_declared_interfaces());
        $traits = count(get_declared_traits());

        Worker::log("[{$time}] [Memory] {$label} #{$worker->id} PID {$pid} "
            . "real:{$memoryReal}MB emalloc:{$memoryEmalloc}MB "
            . "files:{$includedFiles} classes:{$classes} "
            . "interfaces:{$interfaces} traits:{$traits}");

        $poolStats = PoolManager::stats();
        if (!empty($poolStats)) {
            $statStr = implode(' ', array_map(
                fn ($n, $s) => "{$n}[idle:{$s['idle']} active:{$s['active']} max:{$s['max']}]",
                array_keys($poolStats),
                $poolStats
            ));
            Worker::log("[{$time}] [Pool] {$label} #{$worker->id} {$statStr}");
        }

        if ($memoryReal > MEMORY_LIMIT_MB) {
            Worker::log("[{$time}] [Warning] {$label} #{$worker->id} PID {$pid} memory exceeded limit ({$memoryReal} MB > " . MEMORY_LIMIT_MB . " MB), stopping...");
            $worker->stop();
        }
    });

    return $framework;
}

function handle_workerman_http_request(
    TcpConnection $connection,
    WorkermanRequest $req,
    ?Framework $framework,
    string $role
): void {
    $symReq = null;
    $symRes = null;
    $path = $req->path();

    try {
        if ($role === 'ai') {
            if ($path === '/_health') {
                update_health();
                $data = file_get_contents(HEALTH_FILE);
                $connection->send(convert_to_workerman_response(
                    new SymfonyResponse($data === false ? '{}' : $data, 200, ['Content-Type' => 'application/json'])
                ));
                return;
            }
            if (!is_ai_http_path($path)) {
                send_workerman_json($connection, 404, 'AI worker 只处理 /api/ai/');
                return;
            }
        }

        if ($role === 'http' && is_ai_http_path($path) && workerman_env_int('WORKERMAN_HTTP_REJECT_AI', 0) === 1) {
            send_workerman_json($connection, 503, 'AI 请求请走 AI 端口（默认 8001），见 docs/deploy/ai-http-split.md');
            return;
        }

        if ($role !== 'ai') {
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
                    $connection->send(new WorkermanResponse(200, $headers, $fileContent === false ? '' : $fileContent));
                    return;
                }

                $connection->send(new WorkermanResponse(404, ['Content-Type' => 'text/plain'], 'File Not Found'));
                return;
            }

            if ($path === '/_health') {
                update_health();
                $data = file_get_contents(HEALTH_FILE);
                $connection->send(convert_to_workerman_response(
                    new SymfonyResponse($data === false ? '{}' : $data, 200, ['Content-Type' => 'application/json'])
                ));
                return;
            }

            if ($path === '/_ws-stats') {
                $wsManager = WebSocketManager::getInstance();
                $stats = [
                    'online_count' => $wsManager->getOnlineCount(),
                    'rooms' => $wsManager->getAllRooms(),
                    'time' => date('Y-m-d H:i:s'),
                ];
                $connection->send(convert_to_workerman_response(
                    new SymfonyResponse((string) json_encode($stats), 200, ['Content-Type' => 'application/json'])
                ));
                return;
            }
        }

        if (!$framework instanceof Framework) {
            send_workerman_json($connection, 503, 'Worker not ready');
            return;
        }

        $symReq = convert_to_symfony_request($req);
        $symRes = $framework->handleRequest($symReq);

        if ($symReq->hasSession()) {
            $session = $symReq->getSession();
            $session->save();
            $session->clear();
        }

        app('cookie')->sendQueuedCookies($symRes);

        if ($symRes instanceof SseResponse) {
            send_sse_response($connection, $symRes);
            return;
        }

        $connection->send(convert_to_workerman_response($symRes));
    } catch (Throwable $e) {
        $error = "[Error] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
        log_info($error);
        Worker::log($error);
        $connection->send(new WorkermanResponse(500, [], "Internal Error: {$e->getMessage()}"));
    } finally {
        if (isset($symReq) && $symReq->hasSession()) {
            $symReq->getSession()->clear();
        }
        unset($symReq, $symRes);
        gc_collect_cycles();
    }
}

function attach_workerman_ai_worker(?Framework &$framework): Worker
{
    $port = workerman_env_int('WORKERMAN_AI_PORT', AI_LISTEN_PORT);
    $count = max(1, workerman_env_int('WORKERMAN_AI_COUNT', AI_WORKER_COUNT));
    $aiWorker = new Worker('http://0.0.0.0:' . $port);
    $aiWorker->name = 'FSSPHP-AI';
    $aiWorker->count = $count;

    $aiWorker->onWorkerStart = function (Worker $worker) use (&$framework): void {
        $framework = boot_workerman_app_worker($worker, 'AI-Worker');
    };
    $aiWorker->onWorkerStop = function (Worker $worker): void {
        log_info(sprintf('[AI-Worker #%d] 正在关闭连接池...', $worker->id));
        PoolManager::closeAll();
        log_info(sprintf('[AI-Worker #%d] 连接池已关闭', $worker->id));
    };
    $aiWorker->onMessage = function (TcpConnection $connection, WorkermanRequest $req) use (&$framework): void {
        handle_workerman_http_request($connection, $req, $framework, 'ai');
    };

    log_info("[AI] listen http://0.0.0.0:{$port} count={$count} path=/api/ai/");

    return $aiWorker;
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
// 创建 HTTP / AI Worker
// ----------------------------------------------------------------------
$workermanAiOnly = defined('WORKERMAN_AI_ONLY') && WORKERMAN_AI_ONLY;
$workermanWindows = DIRECTORY_SEPARATOR === '\\';
$framework = null;

if (!$workermanAiOnly) {
$httpPort = workerman_env_int('WORKERMAN_HTTP_PORT', HTTP_LISTEN_PORT);
$httpCount = max(1, workerman_env_int('WORKERMAN_HTTP_COUNT', HTTP_WORKER_COUNT));
$httpWorker = new Worker('http://0.0.0.0:' . $httpPort);
$httpWorker->name = 'FSSPHP-HTTP';
$httpWorker->count = $httpCount;

$httpWorker->onWorkerStart = function (Worker $worker) use (&$framework): void {
    $framework = boot_workerman_app_worker($worker, 'HTTP-Worker');
};

$httpWorker->onWorkerStop = function (Worker $worker): void {
    log_info(sprintf('[HTTP-Worker #%d] 正在关闭连接池...', $worker->id));
    PoolManager::closeAll();
    log_info(sprintf('[HTTP-Worker #%d] 连接池已关闭', $worker->id));
};

$httpWorker->onMessage = function (TcpConnection $connection, WorkermanRequest $req) use (&$framework): void {
    handle_workerman_http_request($connection, $req, $framework, 'http');
};

log_info("[HTTP] listen http://0.0.0.0:{$httpPort} count={$httpCount}");

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
} // !$workermanAiOnly（HTTP + WebSocket + Queue）

if ($workermanAiOnly || !$workermanWindows) {
    attach_workerman_ai_worker($framework);
} else {
    $msg = '[AI] Windows 单文件只能起一个 Worker，请另开：php server-ai.php start';
    log_info($msg);
    fwrite(STDOUT, $msg . PHP_EOL);
}

// ----------------------------------------------------------------------
// 运行所有 Worker
// ----------------------------------------------------------------------
Worker::runAll();
