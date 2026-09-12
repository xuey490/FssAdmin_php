<?php

declare(strict_types=1);

/**
 * This file is part of FssPHP Framework.
 *
 * @link     https://github.com/xuey490/project
 * @license  https://github.com/xuey490/project/blob/main/LICENSE
 *
 * @Filename: %filename%
 * @Date: 2025-12-17
 * @Developer: xuey863toy
 * @Email: xuey863toy@gmail.com
 */

namespace Framework\Core;

use Framework\Attributes\Route;
use Framework\Attributes\Routes\BaseMapping;
use Framework\Attributes\Routes\Prefix;
use Framework\Attributes\MiddlewareProviderInterface;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;
use InvalidArgumentException;
use \ReflectionClass;
use \ReflectionMethod;

/**
 * AttributeRouteLoader
 *
 * 核心逻辑：
 * 1. 扫描控制器目录
 * 2. 解析 PHP Attributes (Route, GetMapping, Auth, Log...)
 * 3. 解析 DocBlock (@method, @middleware...)
 * 4. 提取实现了 MiddlewareProviderInterface 接口的中间件
 * 5. 合并所有数据生成 Symfony RouteCollection
 */
class AttributeRouteLoader
{
    // 常量定义 - 消除魔法字符串/硬编码
    private const ROUTE_CONTROLLER_KEY = '_controller';
    private const ROUTE_GROUP_KEY = '_group';
    private const ROUTE_MIDDLEWARE_KEY = '_middleware';
    private const ROUTE_AUTH_KEY = '_auth';
    private const ROUTE_ROLES_KEY = '_roles';
    private const ROUTE_ATTRIBUTES_KEY = '_attributes';
    private const DEFAULT_HTTP_METHOD = 'GET';

    private const ALLOWED_MIDDLEWARE_CHARS = '/^[a-zA-Z0-9_\\\-]+$/';
	
	// 支持 Symfony 风格的 {参数名} 占位符，同时限制参数名只能是字母/数字/下划线
	private const PATH_REGEX = '/^\/[a-zA-Z0-9_\/{}-]+$/';
	// 新增：反射结果缓存（key=类名，value=ReflectionClass）
	/** @var array<mixed> */
	private array $reflection_cache = [];


    // 路由默认参数配置
    private const DEFAULT_ROUTE_PARAMS = [
        self::ROUTE_CONTROLLER_KEY => '',
        self::ROUTE_GROUP_KEY => null,
        self::ROUTE_MIDDLEWARE_KEY => [],
        self::ROUTE_AUTH_KEY => null,
        self::ROUTE_ROLES_KEY => [],
        self::ROUTE_ATTRIBUTES_KEY => [],
    ];

    private string $controller_dir;
    private string $controller_namespace;
    /** @var array<mixed> */
    private array $allowed_middleware; // 中间件白名单
	
	/** @var array<mixed> */
	private array $scan_whitelist = []; // 只扫描含 Controller 的文件 'Controller.php'
	/** @var array<mixed> */
	private array $scan_blacklist = ['BaseController.php']; // 排除基础控制器
	/** @var array<mixed>|null */
	private ?array $scanned_files_cache = null;

    /**
     * 构造函数
     *
     * @param string $controller_dir 控制器目录路径
     * @param string $controller_namespace 控制器命名空间
     * @param array<mixed> $allowed_middleware 允许的中间件白名单
     */
    public function __construct(string $controller_dir, string $controller_namespace, array $allowed_middleware = [])
    {
        $this->controller_dir = rtrim($controller_dir, '/');
        $this->controller_namespace = rtrim($controller_namespace, '\\');
        $this->allowed_middleware = $allowed_middleware;
    }

    /**
     * 从多个目录加载路由（支持插件）
     *
     * @param array<mixed> $controllerDirs 控制器目录映射 [namespace => directory]
     * @return RouteCollection
     */
    public function loadRoutesFromMultipleDirs(array $controllerDirs): RouteCollection
    {
        $routeCollection = new RouteCollection();

        foreach ($controllerDirs as $namespace => $dir) {
            // 临时设置当前目录和命名空间
            $originalDir = $this->controller_dir;
            $originalNamespace = $this->controller_namespace;
            $originalCache = $this->scanned_files_cache;

            $this->controller_dir = rtrim($dir, '/');
            $this->controller_namespace = rtrim($namespace, '\\');
            $this->scanned_files_cache = null; // 清除缓存以扫描新目录

            // 加载该目录的路由
            $routes = $this->loadRoutes();
            $routeCollection->addCollection($routes);

            // 恢复原设置
            $this->controller_dir = $originalDir;
            $this->controller_namespace = $originalNamespace;
            $this->scanned_files_cache = $originalCache;
        }

        return $routeCollection;
    }

    /**
     * 加载所有路由
     *
     * @return RouteCollection
     */
    public function loadRoutes(): RouteCollection
    {
        $route_collection = new RouteCollection();
        $controller_files = $this->scan_directory($this->controller_dir);

        foreach ($controller_files as $file) {
            $class_name = $this->convert_file_to_class($file);
			// 调用缓存方法获取反射类
			$ref_class = $this->get_reflection_class($class_name);
			// 类不存在/反射失败/抽象类 都直接跳过
			if ($ref_class === null || $ref_class->isAbstract()) {
				continue;
			}
			/*
            if (!class_exists($class_name)) {
                continue;
            }

            $ref_class = new ReflectionClass($class_name);
            if ($ref_class->isAbstract()) {
                continue;
            }*/

            // 缓存类属性解析结果 - 避免重复反射
            $class_attributes = $ref_class->getAttributes();
            
            // 1. 类级别处理
            $class_data = $this->process_class_level_data($ref_class, $class_attributes);
            
            // 2. 方法级别处理
            $this->process_method_level_data($ref_class, $class_data, $route_collection);
        }

        return $route_collection;
    }

    /**
     * 处理类级别数据（前缀、中间件、权限等）
     *
     * @param \ReflectionClass<object> $ref_class 类反射对象
     * @param array<mixed> $class_attributes 类属性列表
     * @return array<mixed> 处理后的类级别数据
     */
    private function process_class_level_data(ReflectionClass $ref_class, array $class_attributes): array
    {
        // 收集类级注解和自动提取的中间件
        $collected_data = $this->collect_attributes_and_middleware($class_attributes);
        
        // 解析类级基础配置（Prefix/Route/DocBlock）
        $prefix_data = $this->parse_class_prefix_attributes($ref_class);
        $route_data = $this->parse_class_route_attributes($ref_class);
        $doc_block_data = $this->parse_doc_block_annotations($ref_class->getDocComment() ?: null);

        // 合并配置（优先级：DocBlock > Route > Prefix）
        $class_prefix = $doc_block_data['prefix'] ?? $route_data['prefix'] ?? $prefix_data['prefix'] ?? '';
        $class_group = $doc_block_data['group'] ?? $route_data['group'] ?? $prefix_data['group'] ?? null;
        $class_auth = $doc_block_data['auth'] ?? $route_data['auth'] ?? $prefix_data['auth'] ?? null;
        
        // 合并中间件（类级手动配置 + 注解自动提取）
        $class_middleware = array_merge(
            $prefix_data['middleware'] ?? [],
            $route_data['middleware'] ?? [],
            $doc_block_data['middleware'] ?? [],
            $collected_data['middleware']
        );
        
        // 合并角色
        $class_roles = array_values(array_unique(array_merge(
            $prefix_data['roles'] ?? [],
            $route_data['roles'] ?? [],
            $doc_block_data['roles'] ?? []
        )));

        return [
            'prefix' => $class_prefix,
            'group' => $class_group,
            'middleware' => $class_middleware,
            'auth' => $class_auth,
            'roles' => $class_roles,
            'attributes' => $collected_data['attributes']
        ];
    }

    /**
     * 处理方法级别数据并生成路由
     *
     * @param \ReflectionClass<object> $ref_class 类反射对象
     * @param array<mixed> $class_data 类级别数据
     * @param RouteCollection $route_collection 路由集合
     */
    private function process_method_level_data(ReflectionClass $ref_class, array $class_data, RouteCollection $route_collection): void
    {
        foreach ($ref_class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $method_name = $method->getName();
            
            // 跳过魔术方法
            if (str_starts_with($method_name, '__')) {
                continue;
            }

            // 缓存方法属性解析结果
            $method_attributes = $method->getAttributes();
            
            // 收集方法级注解和自动提取的中间件
            $collected_method_data = $this->collect_attributes_and_middleware($method_attributes);
            
            // 解析方法级文档注释
            $doc_block_data = $this->parse_doc_block_annotations($method->getDocComment() ?: null);
            
            // 解析路由定义（显式注解 > 自动生成）；同一方法可挂多条 #[Route]
            $route_defs = $this->parse_method_route_definitions($method, $doc_block_data);

            foreach ($route_defs as $route_def) {
                $final_data = $this->merge_final_route_data($class_data, $route_def, $doc_block_data, $collected_method_data, $ref_class, $method);
                $this->create_and_add_route($final_data, $route_collection);
            }
        }
    }

    /**
     * 解析类级 Prefix 注解
     *
     * @param \ReflectionClass<object> $ref_class
     * @return array<mixed> */
    private function parse_class_prefix_attributes(ReflectionClass $ref_class): array
    {
        $prefix_attrs = $ref_class->getAttributes(Prefix::class);
        if (empty($prefix_attrs)) {
            return ['prefix' => '', 'middleware' => [], 'auth' => null, 'roles' => [], 'group' => null];
        }

        $inst = $prefix_attrs[0]->newInstance();
        return [
            'prefix' => $inst->prefix ?? '',
            'middleware' => $inst->middleware,
            'auth' => $inst->auth ?? null,
            'roles' => $inst->roles,
            'group' => null
        ];
    }

    /**
     * 解析类级 Route 注解
     *
     * @param \ReflectionClass<object> $ref_class
     * @return array<mixed> */
    private function parse_class_route_attributes(ReflectionClass $ref_class): array
    {
        $route_attrs = $ref_class->getAttributes(Route::class);
        if (empty($route_attrs)) {
            return ['prefix' => '', 'middleware' => [], 'auth' => null, 'roles' => [], 'group' => null];
        }

        $inst = $route_attrs[0]->newInstance();
        return [
            'prefix' => $inst->prefix ?? '',
            'middleware' => $inst->middleware,
            'auth' => $inst->auth ?? null,
            'roles' => $inst->roles,
            'group' => $inst->group ?? null
        ];
    }

    /**
     * 解析方法级路由定义（显式注解或自动生成）
     *
     * @param ReflectionMethod $method
     * @param array<mixed> $doc_block_data
     * @return list<object>
     */
    private function parse_method_route_definitions(ReflectionMethod $method, array $doc_block_data): array
    {
        $defs = [];
        foreach ($method->getAttributes() as $attr) {
            $inst = $attr->newInstance();

            if ($inst instanceof Route) {
                $defs[] = $inst;
                continue;
            }

            if ($inst instanceof BaseMapping) {
                $defs[] = (object) [
                    'path' => $inst->path,
                    'methods' => $inst->methods,
                    'middleware' => $inst->middleware,
                    'defaults' => [],
                    'host' => null,
                    'schemes' => [],
                    'name' => null,
                    'group' => null,
                    'auth' => $inst->auth ?? null,
                    'roles' => $inst->roles,
                    'requirements' => [],
                ];
            }
        }

        if ($defs !== []) {
            return $defs;
        }

        // 没有显式路由注解，但 docblock 有 @path 时生成路由（兼容旧写法）
        if (isset($doc_block_data['path'])) {
            return [(object) [
                'path' => $doc_block_data['path'],
                'methods' => $doc_block_data['methods'] ?? [self::DEFAULT_HTTP_METHOD],
                'middleware' => [],
                'defaults' => [],
                'host' => null,
                'schemes' => [],
                'name' => $doc_block_data['name'] ?? null,
                'group' => $doc_block_data['group'] ?? null,
                'auth' => $doc_block_data['auth'] ?? null,
                'roles' => $doc_block_data['roles'] ?? [],
                'requirements' => [],
            ]];
        }

        return [];
    }

    /**
     * 合并最终路由数据
     *
     * @param array<mixed> $class_data
     * @param object $route_def
     * @param array<mixed> $doc_block_data
     * @param array<mixed> $collected_method_data
     * @param \ReflectionClass<object> $ref_class
     * @param ReflectionMethod $method
     * @return array<mixed> */
    private function merge_final_route_data(array $class_data, object $route_def, array $doc_block_data, array $collected_method_data, ReflectionClass $ref_class, ReflectionMethod $method): array
    {
        // 路径规范化
        $final_path = $this->normalize_path(
            $class_data['prefix'] . '/' . ltrim($route_def->path, '/')
        );

        // 验证路径合法性
        $this->validate_route_path($final_path);

        // 合并基础配置
        $final_group = $doc_block_data['group'] ?? $route_def->group ?? $class_data['group'];
        $final_auth = $doc_block_data['auth'] ?? $route_def->auth ?? $class_data['auth'];
        
        // 合并角色（去重）
        $final_roles = array_values(array_unique(array_merge(
            $class_data['roles'],
            $route_def->roles ?? [],
            $doc_block_data['roles'] ?? []
        )));

        // 合并中间件（去重 + 过滤 + 白名单校验）
        $raw_middleware = array_merge(
            $class_data['middleware'],
            $route_def->middleware ?? [],
            $collected_method_data['middleware'],
            $doc_block_data['middleware'] ?? []
        );
        
        $final_middleware = $this->process_middleware_list($raw_middleware);

        // 合并注解（方法覆盖类）
        $merged_attributes = array_merge($class_data['attributes'], $collected_method_data['attributes']);

        // 路由名称
        $route_name = $route_def->name ?? 
                      $doc_block_data['name'] ?? 
                      strtolower(str_replace('\\', '_', $ref_class->getName())) . '_' . $method->getName();
				
		//dump(sprintf('加载控制器 %s，生成路由 %s', $route_name, $final_path));
        return [
            'path' => $final_path,
            'methods' => $route_def->methods ?: [self::DEFAULT_HTTP_METHOD],
            'middleware' => $final_middleware,
            'group' => $final_group,
            'auth' => $final_auth,
            'roles' => $final_roles,
            'attributes' => $merged_attributes,
            'route_name' => $route_name,
            'controller' => $ref_class->getName() . '::' . $method->getName(),
            'requirements' => $route_def->requirements ?? [],
            'host' => $route_def->host ?? '',
            'schemes' => $route_def->schemes ?? []
        ];
    }

    /**
     * 创建并添加路由到集合
     *
     * @param array<mixed> $final_data
     * @param RouteCollection $route_collection
     */
    private function create_and_add_route(array $final_data, RouteCollection $route_collection): void
    {
        // 构建默认参数
        $defaults = array_merge(self::DEFAULT_ROUTE_PARAMS, [
            self::ROUTE_CONTROLLER_KEY => $final_data['controller'],
            self::ROUTE_GROUP_KEY => $final_data['group'],
            self::ROUTE_MIDDLEWARE_KEY => $final_data['middleware'],
            self::ROUTE_AUTH_KEY => $final_data['auth'],
            self::ROUTE_ROLES_KEY => $final_data['roles'],
            self::ROUTE_ATTRIBUTES_KEY => $final_data['attributes'],
        ]);

        // 创建 Symfony 路由
        $sf_route = new SymfonyRoute(
            path: $final_data['path'],
            defaults: $defaults,
            requirements: $final_data['requirements'],
            options: [],
            host: $final_data['host'],
            schemes: $final_data['schemes'],
            methods: $final_data['methods']
        );

		$route_name = $final_data['route_name'];
		// 检查路由名称重复
		$suffix = 1;
		while ($route_collection->get($route_name)) {
			$route_name = $final_data['route_name'] . '_' . $suffix++;
		}
		$route_collection->add($route_name, $sf_route);
        // 添加到路由集合
        //$route_collection->add($final_data['route_name'], $sf_route);
    }

    /**
     * 收集注解对象 & 从接口自动提取中间件
     *
     * @param array<mixed> $attributes
     * @return array<mixed> */
    private function collect_attributes_and_middleware(array $attributes): array
    {
        $attributes_map = [];
        $middleware_list = [];

        foreach ($attributes as $attr) {
            $attr_name = $attr->getName();
            
            // 排除基础路由注解
            if (in_array($attr_name, [Route::class, Prefix::class, BaseMapping::class]) || is_subclass_of($attr_name, BaseMapping::class)) {
                continue;
            }

            try {
                $inst = $attr->newInstance();
                $attributes_map[$attr_name] = $inst;

                // 提取中间件（实现接口的注解）
                if ($inst instanceof MiddlewareProviderInterface) {
                    $provided = $inst->getMiddleware();
                    $candidates = is_array($provided) ? $provided : [$provided];
                    
                    foreach ($candidates as $mid) {
                        if (is_string($mid) && !empty($mid)) {
                            $middleware_list[] = $mid;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // 记录异常日志
                error_log(sprintf(
                    'Error parsing attribute %s: %s (File: %s, Line: %d)',
                    $attr_name,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
                continue;
            }
        }

        // 去重优化（使用 array_flip 比 array_unique 更高效）
        $middleware_list = array_keys(array_flip($middleware_list));

        return [
            'attributes' => $attributes_map,
            'middleware' => $middleware_list
        ];
    }

    /**
     * 从 DocBlock 解析注解数据
     *
     * @param string|null $doc_comment
     * @return array<mixed> */
    private function parse_doc_block_annotations(?string $doc_comment): array
    {
        if ($doc_comment === null || trim($doc_comment) === '') {
            return [];
        }

        $annotations = [];

        // 通用注解提取方法
        $extract_single = fn(string $prefix) => $this->extract_single_annotation($doc_comment, $prefix);
        $extract_list = fn(string $prefix) => $this->extract_list_annotation($doc_comment, $prefix);

        // 解析各类注解
        $annotations['methods'] = $extract_list('method');
        $annotations['auth'] = $extract_single('auth') !== null ? filter_var($extract_single('auth'), FILTER_VALIDATE_BOOLEAN) : null;
        $annotations['roles'] = $extract_list('role');
        $annotations['middleware'] = $extract_list('middleware');
        $annotations['prefix'] = $extract_single('prefix');
        $annotations['group'] = $extract_single('group');
        $annotations['name'] = $extract_single('name');
        $annotations['path'] = $extract_single('path');

        // 过滤空值
        return array_filter($annotations, fn($v) => $v !== null && $v !== []);
    }

    /**
     * 提取单个值注解
     *
     * @param string $doc_comment
     * @param string $prefix
     * @return string|null
     */
    private function extract_single_annotation(string $doc_comment, string $prefix): ?string
    {
        if (preg_match("/@{$prefix}\s+([^\r\n]+)/i", $doc_comment, $matches)) {
            $value = trim($matches[1]);
            return $value !== '' ? $value : null;
        }
        return null;
    }

    /**
     * 提取列表型注解（逗号分隔）
     *
     * @param string $doc_comment
     * @param string $prefix
     * @return array<mixed> */
    private function extract_list_annotation(string $doc_comment, string $prefix): array
    {
        $value = $this->extract_single_annotation($doc_comment, $prefix);
        if ($value === null) {
            return [];
        }

        $list = array_map('trim', explode(',', $value));
        return array_filter($list, fn($v) => $v !== '');
    }

    /**
     * 规范化路径格式
     *
     * @param string $path
     * @return string
     */
    private function normalize_path(string $path): string
    {
        return '/' . trim($path, '/');
    }

    /**
     * 验证路由路径合法性
     *
     * @param string $path
     * @throws InvalidArgumentException
     */
	// 路径验证方法
	private function validate_route_path(string $path): void
	{
		// 1. 过滤非法字符（仅保留字母、数字、下划线、斜杠、短横线、{}）
		$sanitized_path = preg_replace('/[^a-zA-Z0-9_\/{}-]/', '', $path);
		if ($sanitized_path !== $path) {
			throw new InvalidArgumentException("Invalid characters in route path: {$path}");
		}

		// 2. 验证基础路径格式
		if (!preg_match(self::PATH_REGEX, $path)) {
			throw new InvalidArgumentException("Invalid route path format: {$path}");
		}

		// 3. 校验参数占位符的合法性
		$open_brace = substr_count($path, '{');
		$close_brace = substr_count($path, '}');
		
		// 检查 {} 是否成对
		if ($open_brace !== $close_brace) {
			throw new InvalidArgumentException("Unmatched '{' and '}' in path: {$path}");
		}
		
		// 检查所有 {} 都是合法的参数格式（如 {id} 合法，{id-123} 合法，{id!} 不合法）
		if ($open_brace > 0) {
			$invalid_params = [];
			preg_match_all('/\{([^}]+)\}/', $path, $matches);
			foreach ($matches[1] as $param) {
				if (!preg_match('/^[a-zA-Z0-9_]+$/', $param)) {
					$invalid_params[] = $param;
				}
			}
			if (!empty($invalid_params)) {
				throw new InvalidArgumentException("Invalid parameter name(s) in path: " . implode(', ', $invalid_params));
			}
		}
	}


    /**
     * 处理中间件列表（去重、过滤、白名单校验）
     *
     * @param array<mixed> $middleware_list
     * @return array<mixed> */
    private function process_middleware_list(array $middleware_list): array
    {
        // 过滤空值和非字符串
        $filtered = array_filter($middleware_list, fn($v) => is_string($v) && !empty($v));
        
        // 校验中间件名称格式
        $validated = array_filter($filtered, fn($mid) => preg_match(self::ALLOWED_MIDDLEWARE_CHARS, $mid));
        
        // 白名单校验（如果配置了白名单）
        if (!empty($this->allowed_middleware)) {
            $validated = array_filter($validated, fn($mid) => in_array($mid, $this->allowed_middleware));
        }

        // 高效去重
        return array_keys(array_flip($validated));
    }

    /**
     * 扫描目录获取所有PHP文件
     *
     * @param string $dir
     * @return array<mixed> */
    private function scan_directory(string $dir): array
    {
				if ($this->scanned_files_cache !== null) {
					return $this->scanned_files_cache;
				}
				
        if (!is_dir($dir)) {
            return [];
        }

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'php') {
                $files[] = $file->getPathname();
            }
        }

				/*新增黑白名单功能*/
				// 原有扫描逻辑 + 白名单/黑名单过滤
				$files = array_filter($files, function($file) {
					$filename = basename($file);
					$is_whitelist = empty($this->scan_whitelist) || preg_match('/('.implode('|', $this->scan_whitelist).')/', $filename);
					$is_blacklist = !empty($this->scan_blacklist) && preg_match('/('.implode('|', $this->scan_blacklist).')/', $filename);
					return $is_whitelist && !$is_blacklist;
				});
				$this->scanned_files_cache = $files;
				return $files;				
        //return $files;
    }


    /**
     * 将文件路径转换为类名
     *
     * @param string $file
     * @return string
     */
	private function convert_file_to_class(string $file): string
	{
		$relative = str_replace($this->controller_dir, '', $file);
		$relative = trim(str_replace(['/', '.php'], ['\\', ''], $relative), '\\');
		$class_name = "{$this->controller_namespace}\\{$relative}";

		// 仅校验命名空间归属，类存在性交给 get_reflection_class 处理
		if (strpos($class_name, $this->controller_namespace) !== 0) {
			throw new InvalidArgumentException("Invalid class name: {$class_name}");
		}

		return $class_name;
	}

	/**
	 * 获取反射类并缓存结果，避免重复反射
	 *
	 * @param string $class_name 类名（带命名空间）
     * @return \ReflectionClass<object>|null 反射类对象，类不存在则返回null
     */
    private function get_reflection_class(string $class_name): ?ReflectionClass
	{
		// 1. 优先从缓存获取，避免重复创建ReflectionClass
		if (isset($this->reflection_cache[$class_name])) {
			return $this->reflection_cache[$class_name];
		}

		// 2. 类不存在则返回null
		if (!class_exists($class_name)) {
			return null;
		}

		// 3. 创建反射类并缓存
        $ref_class = new ReflectionClass($class_name);
        $this->reflection_cache[$class_name] = $ref_class;
        return $ref_class;
	}
	
	/**
	 * 清空反射缓存（用于热重载场景）
	 */
	public function clear_reflection_cache(): void
	{
		$this->reflection_cache = [];
	}

	/**
	 * 清空指定类的反射缓存
	 *
	 * @param string $class_name 类名
	 */
	public function clear_reflection_cache_for_class(string $class_name): void
	{
		unset($this->reflection_cache[$class_name]);
	}
}