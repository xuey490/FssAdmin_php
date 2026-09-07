#!/usr/bin/env swoole-cli
<?php

declare(strict_types=1);

/**
 * swoole.php
 * Swoole Http\Server wrapper for NovaPHP
 *
 * Usage (swoole-cli 为准):
 *   swoole-cli swoole.php start          前台启动
 *   swoole-cli swoole.php start -d       守护进程
 *   swoole-cli swoole.php stop
 *   swoole-cli swoole.php restart
 *   swoole-cli swoole.php reload         仅重载 HTTP Worker
 *   swoole-cli swoole.php status
 *
 * Services:
 *   - HTTP:       http://0.0.0.0:8000
 *   - WebSocket:  ws://0.0.0.0:1234 （HTTP 同进程 addListener，禁止再 new 第二个 Server）
 *   - Queue:      Redis LIST 消费进程（含 MySQL 连接池）
 *
 * reload 换 HTTP Worker，同进程 WS 连接会断；队列自定义进程仍要 restart。
 * 不要与 php server.php start 同时占用 8000/1234。
 *
 * Monitor：每 10s 一行内存（f=已加载文件 n=请求数）；超 256MB SIGUSR1；
 * 轮询 app/config/framework 热更新。GET /_health 看各 Worker 快照。
 */

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server as SwooleHttpServer;
use Swoole\Process;
use Swoole\Table;
use Swoole\Timer;
use Swoole\WebSocket\Frame;
use Swoole\Event;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Framework\Core\Framework;
use Framework\Schema\SchemaWarmup;
use Framework\Schema\SchemaRegistry;
use Framework\Utils\WorkermanHealth;
use Framework\Pool\RedisPool;
use Framework\Pool\MysqlPool;
use Framework\Pool\PoolManager;
use Framework\Queue\MessageHandlerInterface;
use Framework\Queue\RedisConsumerService;
use App\Queue\Handlers\DefaultMessageHandler;
use App\Queue\Handlers\ArticleMessageHandler;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "swoole.php must run in CLI.\n");
    exit(1);
}
date_default_timezone_set('Asia/Shanghai');

define('SWOOLE_ENV', true);
define('BASE_PATH', __DIR__);
define('APP_ROOT', __DIR__);
define('LOG_DIR', APP_ROOT . '/storage/swoole');
define('HEALTH_FILE', LOG_DIR . '/health.json');
define('PID_FILE', LOG_DIR . '/swoole.pid');
define('WS_LOG_FILE', LOG_DIR . '/websocket.log');

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

const MEMORY_LIMIT_MB = 256;
const MEMORY_CHECK_INTERVAL_MS = 10000;
const HTTP_HOST = '0.0.0.0';
const HTTP_PORT = 8000;
const WS_HOST = '0.0.0.0';
const WS_PORT = 1234;
const HTTP_WORKER_NUM = 4;
const PACKAGE_MAX_LENGTH = 64 * 1024 * 1024;
const FILE_WATCH_INTERVAL_MS = 2000;
const MEMORY_RELOAD_DEBOUNCE_S = 3;
const FILE_RELOAD_DEBOUNCE_S = 1;

if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @param string $msg
 */
function log_info(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_DIR . '/server.log', $line, FILE_APPEND);
}

function ws_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(WS_LOG_FILE, $line, FILE_APPEND);
}

function update_health(?int $workerId = null, string $name = 'http'): void
{
    $snapshot = WorkermanHealth::snapshot($workerId, $name);
    WorkermanHealth::writeHealthFile(HEALTH_FILE, $snapshot);
    WorkermanHealth::appendMemoryHistory(LOG_DIR, $snapshot, $name);
}

function rotate_logs(): void
{
    $files = [
        LOG_DIR . '/server.log',
        WS_LOG_FILE,
    ];
    foreach ($files as $file) {
        if (file_exists($file) && filesize($file) > 2 * 1024 * 1024) {
            $new = LOG_DIR . '/' . basename($file, '.log') . '-' . date('Ymd_His') . '.log';
            rename($file, $new);
            log_info('[LogRotate] Rotated to ' . $new);
        }
    }
}

function get_mime_type(string $filePath): string
{
    $mimeTypes = [
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'png'   => 'image/png',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'bmp'   => 'image/bmp',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'ogg'   => 'video/ogg',
        'mp3'   => 'audio/mpeg',
        'wav'   => 'audio/wav',
        'pdf'   => 'application/pdf',
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'   => 'application/vnd.ms-excel',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'   => 'application/vnd.ms-powerpoint',
        'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'   => 'text/plain',
        'html'  => 'text/html',
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'xml'   => 'application/xml',
        'zip'   => 'application/zip',
        'rar'   => 'application/vnd.rar',
        '7z'    => 'application/x-7z-compressed',
        'tar'   => 'application/x-tar',
        'gz'    => 'application/gzip',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
    ];
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

/**
 * @return array<string, mixed>
 */
function load_redis_config(): array
{
    /** @var array<string, mixed> $config */
    $config = require BASE_PATH . '/config/redis.php';

    return $config;
}

/**
 * @return array<string, mixed>
 */
function load_database_config(): array
{
    /** @var array<string, mixed> $config */
    $config = require BASE_PATH . '/config/database.php';

    return $config;
}

/**
 * @param array<string, mixed> $redisConfig
 * @param array<string, mixed> $databaseConfig
 */
function register_pools(array $redisConfig, array $databaseConfig, string $label): void
{
    try {
        if (!empty($redisConfig['pool']['enabled'])) {
            $primaryNode = $redisConfig['nodes'][0] ?? [];
            $poolSection = is_array($redisConfig['pool'] ?? null) ? $redisConfig['pool'] : [];
            $redisPoolConfig = array_merge(is_array($primaryNode) ? $primaryNode : [], $poolSection);
            PoolManager::register('redis.default', new RedisPool($redisPoolConfig));
            log_info(sprintf(
                '[%s] Redis 连接池已初始化，空闲：%d / 最大：%d',
                $label,
                $redisPoolConfig['min_connections'] ?? 2,
                $redisPoolConfig['max_connections'] ?? 10
            ));
        }
    } catch (Throwable $e) {
        log_info(sprintf('[%s] Redis 连接池初始化失败（降级为直连）：%s', $label, $e->getMessage()));
    }

    try {
        if (!empty($databaseConfig['pool']['enabled'])) {
            $mysqlConn = $databaseConfig['connections']['mysql'] ?? [];
            $mysqlConn = is_array($mysqlConn) ? $mysqlConn : [];
            $poolSection = is_array($databaseConfig['pool'] ?? null) ? $databaseConfig['pool'] : [];
            $mysqlPoolConfig = array_merge([
                'host'     => $mysqlConn['hostname'] ?? '127.0.0.1',
                'port'     => (int) ($mysqlConn['hostport'] ?? 3306),
                'database' => $mysqlConn['database'] ?? 'fssoa',
                'username' => $mysqlConn['username'] ?? 'root',
                'password' => $mysqlConn['password'] ?? '',
                'charset'  => $mysqlConn['charset'] ?? 'utf8mb4',
            ], $poolSection);
            PoolManager::register('mysql.default', new MysqlPool($mysqlPoolConfig));
            log_info(sprintf(
                '[%s] MySQL 连接池已初始化，空闲：%d / 最大：%d',
                $label,
                $mysqlPoolConfig['min_connections'] ?? 2,
                $mysqlPoolConfig['max_connections'] ?? 10
            ));
        }
    } catch (Throwable $e) {
        log_info(sprintf('[%s] MySQL 连接池初始化失败（降级为直连）：%s', $label, $e->getMessage()));
    }
}

function convert_swoole_to_symfony_request(SwooleRequest $request): SymfonyRequest
{
    $rawServer = $request->server ?? [];
    $server = [];
    foreach ($rawServer as $key => $value) {
        $server[strtoupper((string) $key)] = $value;
    }

    $headers = $request->header ?? [];
    foreach ($headers as $name => $value) {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', (string) $name));
        $server[$headerKey] = is_array($value) ? implode(', ', $value) : $value;
    }

    $rawBody = (string) ($request->rawContent() ?: '');
    $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
    $uri = (string) ($server['REQUEST_URI'] ?? '/');
    $uriParts = parse_url($uri) ?: [];
    $pathInfo = (string) ($uriParts['path'] ?? '/');
    $queryString = (string) ($uriParts['query'] ?? ($server['QUERY_STRING'] ?? ''));
    $remoteIp = (string) ($server['REMOTE_ADDR'] ?? '127.0.0.1');
    $remotePort = (int) ($server['REMOTE_PORT'] ?? 0);

    $get = $request->get ?? [];
    if ($queryString !== '') {
        parse_str($queryString, $queryParams);
        $get = array_merge($queryParams, is_array($get) ? $get : []);
    }

    $post = is_array($request->post ?? null) ? $request->post : [];
    $cookies = is_array($request->cookie ?? null) ? $request->cookie : [];
    $symfonyFiles = convert_swoole_files(is_array($request->files ?? null) ? $request->files : []);

    $server['REQUEST_METHOD'] = $method;
    $server['REQUEST_URI'] = $uri;
    $server['PATH_INFO'] = $pathInfo;
    $server['QUERY_STRING'] = $queryString;
    $server['REMOTE_ADDR'] = $remoteIp;
    $server['REMOTE_PORT'] = $remotePort;
    $server['SERVER_PROTOCOL'] = (string) ($server['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
    $server['HTTP_HOST'] = (string) ($headers['host'] ?? ($server['HTTP_HOST'] ?? 'localhost'));
    $server['CONTENT_LENGTH'] = $headers['content-length'] ?? strlen($rawBody);
    $server['CONTENT_TYPE'] = (string) ($headers['content-type'] ?? ($server['CONTENT_TYPE'] ?? ''));
    $server['PHP_SELF'] = $pathInfo;
    $server['SCRIPT_NAME'] = $pathInfo;
    $server['SCRIPT_FILENAME'] = '';

    if (!isset($server['HTTP_X_FORWARDED_FOR'])) {
        $server['HTTP_X_FORWARDED_FOR'] = $remoteIp;
    }

    if (in_array($method, ['PUT', 'DELETE', 'PATCH'], true) && $post === [] && $rawBody !== '') {
        parse_str($rawBody, $parsedPost);
        if (is_array($parsedPost)) {
            $post = $parsedPost;
        }
    }

    return new SymfonyRequest(
        is_array($get) ? $get : [],
        $post,
        [],
        $cookies,
        $symfonyFiles,
        $server,
        $rawBody
    );
}

/**
 * @param array<array-key, mixed> $files
 * @return array<array-key, mixed>
 */
function convert_swoole_files(array $files): array
{
    $symfonyFiles = [];
    foreach ($files as $field => $fileInfo) {
        if (!is_array($fileInfo)) {
            continue;
        }
        if (isset($fileInfo['tmp_name'])) {
            $uploaded = make_uploaded_file($fileInfo);
            if ($uploaded !== null) {
                $symfonyFiles[$field] = $uploaded;
            }
            continue;
        }
        $nested = [];
        foreach ($fileInfo as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $uploaded = make_uploaded_file($item);
            if ($uploaded !== null) {
                $nested[$index] = $uploaded;
            }
        }
        if ($nested !== []) {
            $symfonyFiles[$field] = $nested;
        }
    }

    return $symfonyFiles;
}

/**
 * @param array<array-key, mixed> $fileInfo
 */
function make_uploaded_file(array $fileInfo): ?UploadedFile
{
    $tmp = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmp === '' || !file_exists($tmp)) {
        return null;
    }

    return new UploadedFile(
        $tmp,
        (string) ($fileInfo['name'] ?? ''),
        isset($fileInfo['type']) ? (string) $fileInfo['type'] : null,
        (int) ($fileInfo['error'] ?? UPLOAD_ERR_OK),
        true
    );
}

function send_symfony_response(SwooleResponse $swooleRes, SymfonyResponse $res): void
{
    $swooleRes->status($res->getStatusCode());
    foreach ($res->headers->allPreserveCase() as $name => $values) {
        $headerName = (string) $name;
        if (strtolower($headerName) === 'content-length') {
            continue;
        }
        if (strtolower($headerName) === 'set-cookie') {
            foreach ($values as $cookie) {
                $swooleRes->header('Set-Cookie', (string) $cookie, false);
            }
            continue;
        }
        $swooleRes->header($headerName, is_array($values) ? implode(', ', $values) : (string) $values);
    }
    $swooleRes->end($res->getContent() ?: '');
}

function try_send_static_file(SwooleRequest $req, SwooleResponse $res): bool
{
    $uri = (string) ($req->server['request_uri'] ?? '/');
    $pathInfo = parse_url($uri, PHP_URL_PATH);
    if (!is_string($pathInfo) || $pathInfo === '') {
        return false;
    }

    $mapped = str_starts_with($pathInfo, '/api/uploads') ? substr($pathInfo, 4) : $pathInfo;
    $staticDirs = ['/uploads', '/assets', '/css', '/js', '/images', '/favicon.ico'];
    $isStaticFile = false;
    foreach ($staticDirs as $dir) {
        if (str_starts_with($mapped, $dir)) {
            $isStaticFile = true;
            break;
        }
    }
    if (!$isStaticFile) {
        return false;
    }

    $filePath = __DIR__ . '/public' . $mapped;
    $realPath = realpath($filePath);
    $publicDir = realpath(__DIR__ . '/public');
    if ($realPath === false || $publicDir === false || !str_starts_with($realPath, $publicDir) || !is_file($realPath)) {
        $res->status(404);
        $res->header('Content-Type', 'text/plain');
        $res->end('File Not Found');

        return true;
    }

    $cacheControl = 'public, max-age=86400';
    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i', $realPath) === 1) {
        $cacheControl = 'public, max-age=2592000';
    }
    $res->header('Content-Type', get_mime_type($realPath));
    $res->header('Cache-Control', $cacheControl);
    $res->sendfile($realPath);

    return true;
}

function pid_running(int $pid): bool
{
    if ($pid <= 0 || !function_exists('posix_kill')) {
        return false;
    }

    return posix_kill($pid, 0);
}

function port_in_use(int $port): bool
{
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.2);
    if (!is_resource($fp)) {
        return false;
    }
    fclose($fp);

    return true;
}

function print_port_busy(int $port): void
{
    fwrite(STDERR, sprintf(
        "Port %d is already in use. Stop the old process first:\n  php swoole.php stop\n  ss -lntp | grep %d\n",
        $port,
        $port
    ));
}

function read_master_pid(): ?int
{
    if (!is_file(PID_FILE)) {
        return null;
    }
    $pid = (int) trim((string) file_get_contents(PID_FILE));

    return $pid > 0 ? $pid : null;
}

function stop_master(int $signal = SIGTERM): int
{
    $pid = read_master_pid();
    if ($pid === null || !pid_running($pid)) {
        fwrite(STDOUT, "Swoole server is not running.\n");

        return 1;
    }
    if (!function_exists('posix_kill') || !posix_kill($pid, $signal)) {
        fwrite(STDERR, "Failed to signal pid {$pid}.\n");

        return 1;
    }
    if ($signal === SIGTERM) {
        $waited = 0;
        while ($waited < 50 && pid_running($pid)) {
            usleep(200000);
            $waited++;
        }
        if (pid_running($pid)) {
            posix_kill($pid, SIGKILL);
        }
        fwrite(STDOUT, "Swoole server stopped.\n");
    } else {
        fwrite(STDOUT, "Reload signal sent to pid {$pid}.\n");
    }

    return 0;
}

function print_status(): int
{
    $pid = read_master_pid();
    if ($pid !== null && pid_running($pid)) {
        fwrite(STDOUT, "Swoole server is running, pid={$pid}\n");

        return 0;
    }
    fwrite(STDOUT, "Swoole server is not running.\n");

    return 1;
}

function swoole_version_label(): string
{
    if (defined('SWOOLE_VERSION')) {
        return (string) SWOOLE_VERSION;
    }
    $ver = phpversion('swoole');

    return is_string($ver) && $ver !== '' ? $ver : 'unknown';
}

/**
 * 前台：server.log + STDOUT；守护进程：只写 server.log。
 */
function emit_monitor(string $msg, bool $daemonize): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(LOG_DIR . '/server.log', $line, FILE_APPEND);
    if (!$daemonize) {
        fwrite(STDOUT, $line);
    }
}

function create_worker_stats_table(): Table
{
    $table = new Table(16);
    $table->column('pid', Table::TYPE_INT);
    $table->column('memory_mb', Table::TYPE_FLOAT);
    $table->column('peak_mb', Table::TYPE_FLOAT);
    $table->column('files', Table::TYPE_INT);
    $table->column('requests', Table::TYPE_INT);
    $table->create();

    return $table;
}

function worker_stats_write(Table $table, int $workerId): void
{
    $key = (string) $workerId;
    $prev = $table->exist($key) ? $table->get($key) : false;
    // ponytail: set() 覆盖整行；requests 用 get 快照，极端并发可能少计 1，泄漏判别够用
    $requests = is_array($prev) ? (int) $prev['requests'] : 0;
    $table->set($key, [
        'pid'        => (int) getmypid(),
        'memory_mb'  => memory_get_usage(true) / 1048576,
        'peak_mb'    => memory_get_peak_usage(true) / 1048576,
        'files'      => count(get_included_files()),
        'requests'   => $requests,
    ]);
}

function worker_stats_bump_requests(Table $table, int $workerId): void
{
    $key = (string) $workerId;
    if (!$table->exist($key)) {
        worker_stats_write($table, $workerId);
    }
    $table->incr($key, 'requests', 1);
}

/**
 * @return array<string, mixed>
 */
function worker_stats_snapshot(Table $table): array
{
    $workers = [];
    foreach ($table as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        $workers[] = [
            'worker_id'  => (int) $key,
            'pid'        => (int) ($row['pid'] ?? 0),
            'memory_mb'  => round((float) ($row['memory_mb'] ?? 0), 1),
            'peak_mb'    => round((float) ($row['peak_mb'] ?? 0), 1),
            'files'      => (int) ($row['files'] ?? 0),
            'requests'   => (int) ($row['requests'] ?? 0),
        ];
    }
    usort($workers, static fn (array $a, array $b): int => $a['worker_id'] <=> $b['worker_id']);

    return [
        'time'          => date('Y-m-d H:i:s'),
        'threshold_mb'  => MEMORY_LIMIT_MB,
        'hint'          => '预热: files 跳升后走平; 泄漏: 同一接口连刷 memory/files 仍单调上升',
        'workers'       => $workers,
    ];
}

function format_memory_line(Table $table): string
{
    $parts = [];
    $snapshot = worker_stats_snapshot($table);
    /** @var list<array<string, mixed>> $workers */
    $workers = $snapshot['workers'];
    foreach ($workers as $row) {
        $parts[] = sprintf(
            '#%d:%.1f/pk%.1f f=%d n=%d',
            (int) $row['worker_id'],
            (float) $row['memory_mb'],
            (float) $row['peak_mb'],
            (int) $row['files'],
            (int) $row['requests']
        );
    }
    $body = $parts === [] ? '(no workers yet)' : implode('  ', $parts);

    return '[Memory] ' . $body . '  threshold=' . MEMORY_LIMIT_MB;
}

/**
 * addProcess + Event::wait() 必须自己结束事件循环，否则 Manager 关不掉自定义进程。
 *
 * @return void
 */
function bind_user_process_stop_signals(): void
{
    $stop = static function (): void {
        Timer::clearAll();
        Event::exit();
        exit(0);
    };
    Process::signal(SIGTERM, $stop);
    Process::signal(SIGINT, $stop);
}

/**
 * Ctrl+C 会进监视进程。Windows 上 Master 退出不会带走 addProcess 子进程，
 * Event::exit() 在信号回调里也经常退不掉，必须 SIGKILL 整棵进程树再 exit。
 *
 * @param SwooleHttpServer $http
 * @param Table $workerStats
 * @param bool $daemonize
 * @return void
 */
function bind_monitor_sigint_shutdown(SwooleHttpServer $http, Table $workerStats, bool $daemonize): void
{
    Process::signal(SIGINT, static function () use ($http, $workerStats, $daemonize): void {
        emit_monitor('[Shutdown] SIGINT, stopping', $daemonize);
        force_stop_tree($http, $workerStats);
        Timer::clearAll();
        exit(0);
    });
}

/**
 * @param SwooleHttpServer $http
 * @param Table $workerStats
 * @return void
 */
function force_stop_tree(SwooleHttpServer $http, Table $workerStats): void
{
    $self = (int) getmypid();
    $mgr = (int) $http->manager_pid;
    $master = (int) $http->master_pid;
    $pids = [];
    foreach ($workerStats as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = (int) ($row['pid'] ?? 0);
        if ($pid > 0 && $pid !== $self) {
            $pids[] = $pid;
        }
    }
    $stats = $http->stats();
    if (is_array($stats)) {
        $workerNum = (int) ($stats['worker_num'] ?? HTTP_WORKER_NUM);
        $taskNum = (int) ($stats['task_worker_num'] ?? 0);
        $userNum = (int) ($stats['user_worker_num'] ?? 0);
        for ($i = 0; $i < $userNum; $i++) {
            $pid = (int) $http->getWorkerPid($workerNum + $taskNum + $i);
            if ($pid > 0 && $pid !== $self) {
                $pids[] = $pid;
            }
        }
    }
    if ($mgr > 0 && $mgr !== $self) {
        $pids[] = $mgr;
    }
    if ($master > 0 && $master !== $self) {
        $pids[] = $master;
    }

    $http->shutdown();
    foreach (array_unique($pids) as $pid) {
        Process::kill((int) $pid, SIGKILL);
    }
}

/**
 * @param SwooleHttpServer $server
 * @param int $signal
 * @return void
 */
function stop_user_processes(SwooleHttpServer $server, int $signal): void
{
    $stats = $server->stats();
    if (!is_array($stats)) {
        return;
    }
    $workerNum = (int) ($stats['worker_num'] ?? HTTP_WORKER_NUM);
    $taskNum = (int) ($stats['task_worker_num'] ?? 0);
    $userNum = (int) ($stats['user_worker_num'] ?? 0);
    $self = (int) getmypid();
    for ($i = 0; $i < $userNum; $i++) {
        $pid = (int) $server->getWorkerPid($workerNum + $taskNum + $i);
        if ($pid <= 0 || $pid === $self) {
            continue;
        }
        Process::kill($pid, $signal);
    }
}

/**
 * @param SwooleHttpServer $http
 * @param string $reason
 * @param bool $daemonize
 * @return bool
 */
function request_worker_reload(SwooleHttpServer $http, string $reason, bool $daemonize): bool
{
    // ponytail: 用 Server::reload() 通知 Manager，避免 posix_kill(master, SIGUSR1) 打乱 Master 的 signalfd
    if (!$http->reload()) {
        emit_monitor('[Reload] reload() failed (' . $reason . ')', $daemonize);

        return false;
    }
    emit_monitor('[Reload] HTTP workers (' . $reason . ')', $daemonize);

    return true;
}

function print_startup_banner(SwooleHttpServer $server, bool $daemonize): void
{
    $lines = [
        '============================================================',
        ' FssPHP Swoole',
        ' time: ' . date('Y-m-d H:i:s'),
        ' PHP ' . PHP_VERSION . '  Swoole ' . swoole_version_label() . '  SWOOLE_ENV=1',
        ' HTTP http://' . HTTP_HOST . ':' . HTTP_PORT,
        ' WS   ws://' . WS_HOST . ':' . WS_PORT,
        ' pid=' . $server->master_pid . '  manager=' . $server->manager_pid,
        ' worker_num=' . HTTP_WORKER_NUM . '  package_max_length=64MB  memory_limit=' . MEMORY_LIMIT_MB . 'MB',
        ' watch: app/  config/  framework/',
        ' CLI: reload | stop',
        ' reload 换 HTTP Worker（同进程 WS 会断）；队列进程仍要 restart',
        '============================================================',
    ];
    foreach ($lines as $line) {
        log_info($line);
        fwrite(STDOUT, $line . PHP_EOL);
    }
    if ($daemonize) {
        fwrite(STDOUT, "Daemonized. Use: swoole-cli swoole.php stop\n");
    }
}

function rel_watch_path(string $path): string
{
    $root = str_replace('\\', '/', APP_ROOT);
    $norm = str_replace('\\', '/', $path);
    if (str_starts_with($norm, $root)) {
        return ltrim(substr($norm, strlen($root)), '/');
    }

    return $path;
}

/**
 * @param list<string> $dirs
 * @param array<string, int> $lastMtimes
 * @return list<string>
 */
function scan_source_changes(array $dirs, array &$lastMtimes, bool $baseline): array
{
    $changed = [];
    $okExt = ['php' => true, 'json' => true, 'ini' => true, 'env' => true];
    $skipDir = ['.git' => true, '.idea' => true, 'vendor' => true, 'runtime' => true, 'storage' => true, 'node_modules' => true];
    $skipSuffix = ['.tmp', '.swp', '.bak'];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
        } catch (Throwable $e) {
            continue;
        }
        /** @var SplFileInfo $file */
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $filename = $file->getFilename();
            foreach ($skipSuffix as $suf) {
                if (str_ends_with($filename, $suf)) {
                    continue 2;
                }
            }
            $parts = preg_split('#[\\\\/]#', $file->getPath()) ?: [];
            foreach ($parts as $part) {
                if (isset($skipDir[$part])) {
                    continue 2;
                }
            }
            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            if (!isset($okExt[$ext])) {
                continue;
            }
            $mtime = (int) $file->getMTime();
            $real = $file->getRealPath() ?: $file->getPathname();
            if ($baseline) {
                $lastMtimes[$real] = $mtime;
                continue;
            }
            if (!isset($lastMtimes[$real])) {
                $lastMtimes[$real] = $mtime;
                $changed[] = $real;
                continue;
            }
            if ($mtime !== $lastMtimes[$real]) {
                $lastMtimes[$real] = $mtime;
                $changed[] = $real;
            }
        }
    }

    return $changed;
}

function run_watch_selfcheck(): int
{
    $mtimes = [];
    $dirs = [
        APP_ROOT . '/app',
        APP_ROOT . '/config',
        APP_ROOT . '/framework',
    ];
    $baselineChanges = scan_source_changes($dirs, $mtimes, true);
    if ($baselineChanges !== []) {
        fwrite(STDERR, "selfcheck fail: baseline must not report changes\n");

        return 1;
    }
    if (count($mtimes) < 10) {
        fwrite(STDERR, 'selfcheck fail: baseline too small ' . count($mtimes) . PHP_EOL);

        return 1;
    }
    $again = scan_source_changes($dirs, $mtimes, false);
    if ($again !== []) {
        fwrite(STDERR, 'selfcheck fail: second scan expected empty, got ' . implode(', ', $again) . PHP_EOL);

        return 1;
    }
    fwrite(STDOUT, 'selfcheck ok watch_files=' . count($mtimes) . PHP_EOL);

    return 0;
}

/**
 * @return array<string, MessageHandlerInterface>
 */
function queue_handlers(): array
{
    return [
        'default'           => new DefaultMessageHandler(),
        'article_published' => new ArticleMessageHandler(),
        'article_view'      => new ArticleMessageHandler(),
    ];
}

function attach_websocket_listener(SwooleHttpServer $http): void
{
    // Linux 官方 Swoole：进程内只能有一个 Server。addProcess 里再 new WebSocket\Server
    // 会报 "server is running. unable to create Swoole\WebSocket\Server"（不是端口占用）。
    $port = $http->addListener(WS_HOST, WS_PORT, SWOOLE_SOCK_TCP);
    if ($port === false) {
        fwrite(STDERR, sprintf("Failed to listen WebSocket %s:%d (port in use?)\n", WS_HOST, WS_PORT));
        exit(1);
    }
    $port->set([
        'open_websocket_protocol' => true,
    ]);

    $http->on('open', static function (SwooleHttpServer $server, SwooleRequest $req): void {
        ws_log('[WS] open fd=' . $req->fd);
    });
    // ponytail: 框架暂无 WS 业务协议，忽略帧（不对齐 server.php 805-977）
    $http->on('message', static function (SwooleHttpServer $server, Frame $frame): void {
    });
    $http->on('close', static function (SwooleHttpServer $server, int $fd): void {
        $info = $server->getClientInfo($fd);
        $serverPort = is_array($info) ? (int) ($info['server_port'] ?? 0) : 0;
        if ($serverPort === WS_PORT) {
            ws_log('[WS] close fd=' . $fd);
        }
    });
}

/**
 * @param array<string, mixed> $redisConfig
 * @param array<string, mixed> $databaseConfig
 */
function attach_queue_processes(SwooleHttpServer $http, array $redisConfig, array $databaseConfig): void
{
    $queueConfig = $redisConfig['queue'] ?? [];
    if (!is_array($queueConfig) || empty($queueConfig['enabled'])) {
        return;
    }
    $workerCount = max(1, (int) ($queueConfig['worker_count'] ?? 2));
    $queues = is_array($queueConfig['queues'] ?? null) ? $queueConfig['queues'] : [];

    for ($i = 0; $i < $workerCount; $i++) {
        $http->addProcess(new Process(static function () use ($i, $redisConfig, $databaseConfig, $queues): void {
            bind_user_process_stop_signals();
            $label = 'Queue-Worker #' . $i;
            log_info(sprintf('[%s] PID %d 启动', $label, getmypid()));

            try {
                $primaryNode = $redisConfig['nodes'][0] ?? [];
                $poolSection = is_array($redisConfig['pool'] ?? null) ? $redisConfig['pool'] : [];
                PoolManager::register('redis.default', new RedisPool(array_merge(
                    is_array($primaryNode) ? $primaryNode : [],
                    $poolSection
                )));
                log_info(sprintf('[%s] Redis 连接池已初始化', $label));
            } catch (Throwable $e) {
                log_info(sprintf('[%s] Redis 连接池初始化失败：%s', $label, $e->getMessage()));
            }

            try {
                if (!empty($databaseConfig['pool']['enabled'])) {
                    $mysqlConn = $databaseConfig['connections']['mysql'] ?? [];
                    $mysqlConn = is_array($mysqlConn) ? $mysqlConn : [];
                    $poolSection = is_array($databaseConfig['pool'] ?? null) ? $databaseConfig['pool'] : [];
                    PoolManager::register('mysql.default', new MysqlPool(array_merge([
                        'host'     => $mysqlConn['hostname'] ?? '127.0.0.1',
                        'port'     => (int) ($mysqlConn['hostport'] ?? 3306),
                        'database' => $mysqlConn['database'] ?? 'fssoa',
                        'username' => $mysqlConn['username'] ?? 'root',
                        'password' => $mysqlConn['password'] ?? '',
                        'charset'  => $mysqlConn['charset'] ?? 'utf8mb4',
                    ], $poolSection)));
                    log_info(sprintf('[%s] MySQL 连接池已初始化', $label));
                }
            } catch (Throwable $e) {
                log_info(sprintf('[%s] MySQL 连接池初始化失败：%s', $label, $e->getMessage()));
            }

            foreach ($queues as $qCfg) {
                if (!is_array($qCfg)) {
                    continue;
                }
                try {
                    $consumer = new RedisConsumerService(array_merge($qCfg, [
                        'queue' => $qCfg['queue'] ?? $qCfg['name'] ?? 'default',
                    ]));
                    $consumer->registerHandlers(queue_handlers());
                    $consumer->startScheduled($i, static function (float $seconds, callable $callback): void {
                        Timer::tick((int) max(1, $seconds * 1000), $callback);
                    });
                    log_info(sprintf('[%s] 队列 [%s] 消费服务已启动', $label, $qCfg['name'] ?? 'unknown'));
                } catch (Throwable $e) {
                    log_info(sprintf(
                        '[%s] 队列 [%s] 启动失败：%s',
                        $label,
                        $qCfg['name'] ?? 'unknown',
                        $e->getMessage()
                    ));
                }
            }

            Event::wait();
            PoolManager::closeAll();
            log_info(sprintf('[%s] 已停止', $label));
        }, false, 0, false));
    }
}

function attach_monitor_process(SwooleHttpServer $http, Table $workerStats, bool $daemonize): void
{
    $http->addProcess(new Process(static function () use ($http, $workerStats, $daemonize): void {
        bind_user_process_stop_signals();
        bind_monitor_sigint_shutdown($http, $workerStats, $daemonize);

        $watchDirs = [
            APP_ROOT . '/app',
            APP_ROOT . '/config',
            APP_ROOT . '/framework',
        ];
        $lastMtimes = [];
        $baselineDone = false;
        $lastMemReloadAt = 0.0;
        $lastFileReloadAt = 0.0;

        Timer::tick(MEMORY_CHECK_INTERVAL_MS, static function () use ($http, $workerStats, $daemonize, &$lastMemReloadAt): void {
            emit_monitor(format_memory_line($workerStats), $daemonize);
            WorkermanHealth::writeHealthFile(HEALTH_FILE, worker_stats_snapshot($workerStats));
            $now = microtime(true);
            foreach ($workerStats as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $mb = (float) ($row['memory_mb'] ?? 0);
                if ($mb <= MEMORY_LIMIT_MB) {
                    continue;
                }
                if ($now - $lastMemReloadAt < MEMORY_RELOAD_DEBOUNCE_S) {
                    break;
                }
                $lastMemReloadAt = $now;
                emit_monitor(sprintf(
                    '[Warning] HTTP-Worker #%s pid=%s memory %.1f MB > %d MB, smooth reload',
                    (string) $key,
                    (string) ($row['pid'] ?? '?'),
                    $mb,
                    MEMORY_LIMIT_MB
                ), $daemonize);
                request_worker_reload($http, 'memory', $daemonize);
                break;
            }
        });

        Timer::tick(FILE_WATCH_INTERVAL_MS, static function () use (
            $http,
            $watchDirs,
            $daemonize,
            &$lastMtimes,
            &$baselineDone,
            &$lastFileReloadAt
        ): void {
            $changed = scan_source_changes($watchDirs, $lastMtimes, !$baselineDone);
            if (!$baselineDone) {
                $baselineDone = true;
                emit_monitor('[Watch] baseline files=' . count($lastMtimes), $daemonize);

                return;
            }
            if ($changed === []) {
                return;
            }
            $now = microtime(true);
            if ($now - $lastFileReloadAt < FILE_RELOAD_DEBOUNCE_S) {
                return;
            }
            $lastFileReloadAt = $now;
            $shown = array_slice($changed, 0, 5);
            $names = array_map(static fn (string $p): string => rel_watch_path($p), $shown);
            $extra = count($changed) > 5 ? ' ...' : '';
            request_worker_reload($http, 'file ' . implode(', ', $names) . $extra, $daemonize);
        });

        Event::wait();
    }, false, 0, false));
}

function start_http_server(bool $daemonize): void
{
    $existing = read_master_pid();
    if ($existing !== null && pid_running($existing)) {
        fwrite(STDERR, "Swoole server already running, pid={$existing}\n");
        exit(1);
    }
    if (port_in_use(HTTP_PORT)) {
        print_port_busy(HTTP_PORT);
        exit(1);
    }
    if (port_in_use(WS_PORT)) {
        print_port_busy(WS_PORT);
        exit(1);
    }

    $redisConfig = load_redis_config();
    $databaseConfig = load_database_config();
    $framework = null;
    $workerStats = create_worker_stats_table();

    try {
        $http = new SwooleHttpServer(HTTP_HOST, HTTP_PORT, SWOOLE_PROCESS);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Failed to bind ' . HTTP_HOST . ':' . HTTP_PORT . ': ' . $e->getMessage() . PHP_EOL);
        print_port_busy(HTTP_PORT);
        exit(1);
    }
    $http->set([
        'worker_num'       => HTTP_WORKER_NUM,
        'daemonize'        => $daemonize,
        'pid_file'         => PID_FILE,
        'log_file'         => LOG_DIR . '/swoole.log',
        'max_request'      => 10000,
        'reload_async'     => true,
        'enable_coroutine' => false,
        'max_wait_time'    => 10,
        // 默认 2MB，图片上传约 3MB 会被直接掐掉（ERRNO 7102 / ECONNRESET）
        'package_max_length' => PACKAGE_MAX_LENGTH,
    ]);

    attach_websocket_listener($http);
    attach_queue_processes($http, $redisConfig, $databaseConfig);
    attach_monitor_process($http, $workerStats, $daemonize);

    $http->on('start', static function (SwooleHttpServer $server) use ($daemonize): void {
        print_startup_banner($server, $daemonize);
    });

    $http->on('BeforeShutdown', static function (SwooleHttpServer $server): void {
        log_info('[Shutdown] stopping user processes');
        stop_user_processes($server, SIGTERM);
        // ponytail: Windows/swoole-cli 上 SIGTERM 经常到不了 addProcess，关机再 SIGKILL
        stop_user_processes($server, SIGKILL);
    });

    $http->on('WorkerStart', function (SwooleHttpServer $server, int $workerId) use (&$framework, $redisConfig, $databaseConfig, $workerStats): void {
        $label = 'HTTP-Worker #' . $workerId;
        log_info(sprintf('[%s] PID %d started', $label, getmypid()));
        update_health($workerId);

        $framework = Framework::getInstance();

        SchemaWarmup::setScanPath(base_path('app/Models'), 'App\\Models');
        $ignore = [];
        if (class_exists(\App\Models\TempView::class)) {
            $ignore[] = \App\Models\TempView::class;
        }
        SchemaWarmup::ignore($ignore);
        SchemaWarmup::warmupAll();
        SchemaRegistry::freeze();

        register_pools($redisConfig, $databaseConfig, $label);
        worker_stats_write($workerStats, $workerId);

        Timer::tick(MEMORY_CHECK_INTERVAL_MS, function () use ($workerId, $workerStats): void {
            WorkermanHealth::appendMemoryHistory(LOG_DIR, WorkermanHealth::snapshot($workerId, 'http'), 'http');
            rotate_logs();
            worker_stats_write($workerStats, $workerId);
            $poolStats = PoolManager::stats();
            if ($poolStats !== []) {
                $statStr = implode(' ', array_map(
                    static fn (string $n, array $s): string => sprintf(
                        '%s[idle:%s active:%s max:%s]',
                        $n,
                        $s['idle'] ?? '?',
                        $s['active'] ?? '?',
                        $s['max'] ?? '?'
                    ),
                    array_keys($poolStats),
                    array_values($poolStats)
                ));
                log_info('[Pool] HTTP-Worker #' . $workerId . ' ' . $statStr);
            }
        });
    });

    $http->on('WorkerStop', static function (SwooleHttpServer $server, int $workerId): void {
        log_info(sprintf('[HTTP-Worker #%d] 正在关闭连接池...', $workerId));
        PoolManager::closeAll();
        log_info(sprintf('[HTTP-Worker #%d] 连接池已关闭', $workerId));
    });

    // reload_async：旧 Worker 必须在 WorkerExit 清掉 Timer，否则进程退不掉，Ctrl+C / reload 会卡住
    $http->on('WorkerExit', static function (): void {
        Timer::clearAll();
    });

    $http->on('request', function (SwooleRequest $req, SwooleResponse $res) use (&$framework, $http, $workerStats): void {
        $symReq = null;
        $symRes = null;
        try {
            if (try_send_static_file($req, $res)) {
                return;
            }

            $path = parse_url((string) ($req->server['request_uri'] ?? '/'), PHP_URL_PATH);
            if ($path === '/_health') {
                worker_stats_write($workerStats, (int) $http->worker_id);
                $res->header('Content-Type', 'application/json; charset=utf-8');
                $res->end((string) json_encode(
                    worker_stats_snapshot($workerStats),
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                ));

                return;
            }

            if (!$framework instanceof Framework) {
                $res->status(503);
                $res->end('Worker not ready');

                return;
            }

            $symReq = convert_swoole_to_symfony_request($req);
            $symRes = $framework->handleRequest($symReq);

            if ($symReq->hasSession()) {
                $session = $symReq->getSession();
                $session->save();
                $session->clear();
            }

            app('cookie')->sendQueuedCookies($symRes);
            send_symfony_response($res, $symRes);
        } catch (Throwable $e) {
            $error = "[Error] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
            log_info($error);
            if (method_exists($res, 'isWritable') && !$res->isWritable()) {
                return;
            }
            $res->status(500);
            $res->end('Internal Error: ' . $e->getMessage());
        } finally {
            if (isset($symReq) && $symReq->hasSession()) {
                $symReq->getSession()->clear();
            }
            unset($symReq, $symRes);
            worker_stats_bump_requests($workerStats, (int) $http->worker_id);
            gc_collect_cycles();
        }
    });

    $http->start();
}

// ----------------------------------------------------------------------
// CLI
// ----------------------------------------------------------------------
$command = $argv[1] ?? 'start';
$daemonize = in_array('-d', $argv, true);

if (in_array($command, ['start', 'restart'], true) && !class_exists(SwooleHttpServer::class)) {
    fwrite(STDERR, "Swoole\\Http\\Server not found. Start with: swoole-cli swoole.php start\n");
    exit(1);
}

switch ($command) {
    case 'start':
        start_http_server($daemonize);
        break;
    case 'stop':
        exit(stop_master(SIGTERM));
    case 'reload':
        exit(stop_master(SIGUSR1));
    case 'restart':
        stop_master(SIGTERM);
        start_http_server($daemonize);
        break;
    case 'status':
        exit(print_status());
    case 'selfcheck':
        exit(run_watch_selfcheck());
    default:
        fwrite(STDERR, "Unknown command: {$command}\n");
        fwrite(STDERR, "Usage: swoole-cli swoole.php start|stop|restart|reload|status [-d]\n");
        exit(1);
}
