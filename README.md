## <p align="center"> FSSADMIN 全栈后台管理系统</p>



  <strong>FssAdmin 是一款开源企业级别的中后台管理系统，基于 Vue3、Vite、TypeScript、Pinia、Pinia持久化插件、Unocss 和 ElementPlus等前端最新技术栈。相较于其他比较流行的后台管理模板，更加简洁、快捷和容易理解，非常适合中小型项目、个人项目，企业级的OA,ERM,ERP,政务系统等开发。</strong>



---

## 演示地址：https://v3.phpframe.org/  账号：admin  密码： 123456


---

## 👨‍💻 Author

**blue2004 (xuey863toy)**  
📧 Email: xuey863toy@gmail.com  
🌐 GitHub: https://github.com/xuey490

欢迎加入QQ交流群：
![输入图片说明](https://v3.phpframe.org/assets/qqgroup-C2pjhKmS.png)

<p align="center">
  ⭐ 如果这个项目对您有帮助，请给一个 Star！⭐
</p>

---


## 📖 项目简介

**FSSADMIN** 是一个基于 FssPHP 的现代化全栈框架，支持 **多租户 SaaS 架构**。同一套业务代码可在 **PHP-FPM、Workerman、Swoole** 三种进程模型下运行，入口只适配 HTTP 层，控制器 / 服务 / 路由 / 权限无需改写。

项目包含：

- **后端框架**: 自研轻量级 PHP 框架 (`framework/`)
- **前端应用**: 基于 Vue 3 + Element Plus 的管理后台 (`web/`)
- **插件系统**: 支持热插拔的功能扩展 (`plugins/`)

### 三种启动模式（同一内核）

这是本框架与多数 PHP 后台的差异点：业务写一次，运行时按环境选择。`Framework::handleRequest()` 对三种入口保持同一契约 `{ code, msg, message, data }`。

| 模式 | 入口 | 启动 | 适合 |
|------|------|------|------|
| **PHP-FPM** | `public/index.php` | Nginx / Apache + php-fpm，或 `php -S localhost:8000 -t public` | 传统主机、共享部署、与现有 LNMP 共存 |
| **Workerman** | `server.php` | `php server.php start` | 常驻内存、热重载、HTTP + WebSocket + 队列 |
| **Swoole** | `swoole.php` | `swoole-cli swoole.php start` | 多进程 Worker、连接池、文件监视自动 reload Worker |

**不要**同时启动 Workerman 与 Swoole（默认都占用 `8000` / `1234`）。

Swoole 模式额外能力：`app/` `config/` `framework/` 文件变更自动平滑重载 HTTP Worker；Redis / MySQL 连接池；同进程 WebSocket（`addListener`，禁止再 `new` 第二个 Server）；队列自定义进程；`GET /_health` 查看各 Worker 内存与请求快照。Swoole 扩展不走 Composer，请使用 **swoole-cli 6.x**。

### 后端特点

| 特性 | 说明 |
|------|------|
| 🧬 **三运行时** | FPM / Workerman / Swoole 共用 `app/` + `framework/`，按入口切换进程模型 |
| 🚀 **高性能** | 常驻内存（Workerman / Swoole）比传统 PHP-FPM 有数量级提升 |
| 🏢 **多租户 SaaS** | 完整的租户隔离方案（数据行级隔离 + 菜单权限隔离） |
| 🔐 **RBAC 权限** | 基于多租户的 Casbin RBAC 权限控制模型 |
| 🔌 **双 ORM 支持** | 同时支持 Laravel Eloquent / ThinkORM（`ORM_DRIVER` 切换） |
| 🎨 **Attribute 路由** | PHP 8 原生注解路由，自动扫描与缓存 |
| 🧩 **插件系统** | 完整的插件生命周期管理（安装/卸载/启用/禁用） |
| 📦 **代码生成器** | 一键生成 CRUD 模板，提升开发效率 |

### 前端特点

- 🎯 使用 Element Plus + Vite + Vue3 + TypeScript + Uncoss + Pinia 等主流技术。
- 🍊 多种布局和丰富的主题适配移动端、IPad和PC端。
- 🐼 内置权限管理页面，进行二次开发可直接对接后端接口即可。
- 🌸 集成登陆、注销及权限验证。
- 🎃 封装按钮和Input框的防抖、限流和背景水印以及左侧无限递归菜单。
- 🍀 集成 `pinia`，vuex 的替代方案，轻量、简单、易用，并且配置pinia持久化插件。
- 😍 二次封装Dialog对话框、Drawer抽屉、Notification通知、Message消息提示和Popconfirm确认框，操作更加方便快捷。
- 🍓 二次封装axios，方便接口更好的统一管理。
- 🌍 集成Echarts图表。
- 🌈 集成 `unocss`，antfu 开源的原子 css 解决方案，非常轻量。
- 🐟 集成多环境配置，dev、测试、生产环境。
- 🌼 集成 `eslint + prettier`，代码约束和格式化统一。
-  集成 `stylelint`，代码约束scss、less、css规范化。
- 👻 集成 `mock` 接口服务。
- 🏡 集成 `iconify` 图标，支持自定义 svg 图标, 优雅使用icon。

---

## ✨ 功能特性

### 用户与权限系统
- ✅ 多租户登录与切换
- ✅ JWT + Session 双认证模式
- ✅ 角色权限分配 (RBAC)
- ✅ 菜单动态路由
- ✅ 部门数据权限
- ✅ 岗位管理
- ✅ 操作日志 & 登录日志

### 系统管理
- ✅ 系统配置分组管理
- ✅ 数据字典维护
- ✅ 菜单管理（支持树形结构）
- ✅ 文件附件管理（支持分类）
- ✅ 定时任务管理 (Crontab)
- ✅ 数据库维护工具
- ✅ 服务器监控面板
- ✅ Redis 监控面板
- ✅ 缓存管理工具

### 开发工具
- ✅ 代码生成器（CRUD 模板生成）
- ✅ 数据库表结构导入
- ✅ 插件市场与管理终端
- ✅ 热重载开发模式（Workerman `reload` / Swoole 文件监视自动换 Worker）

### 内容管理 (Article)
- ✅ 文章发布与管理
- ✅ 文章分类
- ✅ Banner 轮播图管理

### 安全防护
- ✅ CSRF Token 保护
- ✅ XSS 过滤中间件
- ✅ CORS 跨域配置
- ✅ 接口频率限制 (Rate Limit)
- ✅ Referer 来源检查
- ✅ IP 黑名单
- ✅ 测试环境写操作保护

---

## 🛠 技术栈

### 后端技术栈

版本与仓库根目录 `composer.json` 对齐（`require` / `require-dev`）。

| 类别 | 技术 | 版本 | 说明 |
|------|------|------|------|
| **运行时** | PHP | ^8.3 | 要求 PHP 8.3+ |
| **进程模型** | PHP-FPM | - | `public/index.php`，传统 CGI |
| **进程模型** | Workerman | ^5.2 | `php server.php start` |
| **进程模型** | Swoole | 6.x（swoole-cli） | `swoole-cli swoole.php start`，非 Composer 包 |
| **HTTP Kernel** | Symfony HttpFoundation / HttpKernel / Routing | ^7.4 | Request/Response、内核、路由 |
| **依赖注入** | Symfony DependencyInjection | ^7.4 | 容器与服务 |
| **配置 / 缓存 / 环境** | Symfony Config / Cache / Dotenv / Finder / Translation / ExpressionLanguage | ^7.4 | 配置编译、缓存、`.env`、文件扫描、表达式 |
| **ORM (默认)** | illuminate/database + config/events/pagination | ^12.58 | Laravel Eloquent |
| **ORM (备选)** | topthink/think-orm | ^4.0.51 | ThinkPHP ORM |
| **验证 / 模板** | think-validate / think-template / think-cache | ^3.0 | 校验、模板、PSR-16 缓存 |
| **视图** | Twig | ^3.14 | 视图渲染 |
| **权限控制** | Casbin | ^4.2 | RBAC 权限模型 |
| **JWT 认证** | lcobucci/jwt + lcobucci/clock | ^5.6 / ^3.5 | JSON Web Token |
| **HTTP 客户端** | Guzzle | ^7.10 | 出站 HTTP |
| **Redis** | Predis | ^3.3 | Redis 客户端（缓存 / Session / 队列） |
| **XSS 过滤** | ezyang/htmlpurifier | ^4.18 | HTML 净化 |
| **加密** | phpseclib | ^3.0 | 非对称/对称算法 |
| **图像处理** | Intervention Image | ^4.0 | 图片处理 |
| **Markdown** | League Commonmark | ^2.6 | Markdown 解析 |
| **日志** | Monolog | ^3.9 | 结构化日志 |
| **环境变量** | vlucas/phpdotenv | ^5.6 | `.env` 加载 |
| **UUID** | Ramsey UUID | ^4.9 | 唯一标识 |
| **PSR** | psr/container, event-dispatcher, log, simple-cache | ^2 / ^1 / ^3 / ^3 | 标准接口 |
| **内部组件** | xuey490/config, database, log, storage | ^1.0–^1.1 | 配置 / 数据库 / 日志 / 存储封装 |
| **开发工具** | PHPStan ^2.2、PHP-CS-Fixer ^3.88、PHPUnit、composer-unused | require-dev | 静态分析、格式化、测试 |

### 前端技术栈

| 类别 | 技术 | 版本 | 说明 |
|------|------|------|------|
| **框架** | Vue.js | ^3.5 | 渐进式 JS 框架 |
| **构建工具** | Vite | ^7.1 | 下一代构建工具 |
| **语言** | TypeScript | ~5.6 | 类型安全的 JS |
| **UI 组件库** | Element Plus | ^2.11 | Vue 3 组件库 |
| **CSS 框架** | TailwindCSS | ^4.1 | 原子化 CSS |
| **状态管理** | Pinia | ^3.0 | Vue 状态库 |
| **路由** | Vue Router | ^4.5 | SPA 路由 |
| **国际化** | Vue I18n | ^9.14 | 多语言支持 |
| **HTTP 客户端** | Axios | ^1.12 | HTTP 请求 |
| **图表** | ECharts | ^6.0 | 数据可视化 |
| **表格** | XLSX | ^0.18 | Excel 导入导出 |
| **编辑器** | WangEditor | ^5.1 | 富文本编辑器 |
| **视频播放** | XGPlayer | ^3.0 | 西瓜播放器 |
| **拖拽** | Vue Draggable Plus | ^0.6 | 拖拽排序 |
| **图标** | Iconify | ^5.0 | 图标库 |
| **加密** | CryptoJS | ^4.2 | 加密解密 |
| **二维码** | QRCode.vue | ^3.6 | 二维码生成 |
| **文件下载** | FileSaver | ^2.0 | 文件保存 |
| **代码高亮** | Highlight.js | ^11.0 | 语法高亮 |
| **图片裁剪** | Vue Img Cutter | ^3.0 | 头像裁剪 |


---

## 🚀 安装说明

### 环境要求

| 软件 | 版本要求 |
|------|----------|
| PHP | >= 8.3 |
| Composer | >= 2.0 |
| MySQL/MariaDB | >= 8.0 |
| Redis | >= 6.0 |
| Node.js | >= 20.19 (前端开发) |
| pnpm | >= 8.8 (前端包管理) |
| swoole-cli | 6.x（可选，仅 Swoole 模式需要；与系统 PHP 独立） |
| Extensions | redis, pdo_mysql, mbstring, json, openssl, gd, fileinfo |

### 后端安装步骤

```bash
# 1. 克隆项目
git clone https://github.com/xuey490/FssAdmin.git
cd server

# 2. 安装 PHP 依赖
composer install

# 3. 编辑 .env 文件，配置数据库和 Redis 连接,最好.env 和config目录的配置文件内容一致
vim .env

```


```
# 4. 编辑 config 文件，配置数据库和 Redis 连接 关键配置:
vim /config/database.php

DB_CONNECTION=mysql

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=fssoa

DB_USERNAME=root

DB_PASSWORD=root

```

```bash
# 5. 导入数据库结构
mysql -u root -p fssoa < database/fssoa.sql

# 6. 设置目录权限 (Linux)
chmod -R 755 storage/
chmod -R 777 storage/logs/ storage/cache/

# 7. 启动服务（三选一，勿同时占用 8000/1234）

# Workerman 常驻内存
php server.php start
php server.php start -d    # 守护进程
php server.php stop
php server.php reload
php server.php status

# Swoole 多进程（需 swoole-cli，不要用系统 php 跑 swoole.php）
swoole-cli swoole.php start
swoole-cli swoole.php start -d
swoole-cli swoole.php stop
swoole-cli swoole.php reload   # 仅重载 HTTP Worker
swoole-cli swoole.php status

# 传统 FPM / 内置服务器
php -S localhost:8000 -t public
# 生产环境用 Nginx/Apache + php-fpm，文档根目录指向 public/
```

### 前端安装步骤

```bash
# 1. 进入前端目录
cd web

# 2. 安装依赖
pnpm install

# 3. 启动开发服务器
pnpm dev

# 4. 构建生产版本
pnpm build
```

**`web/.env` 关键配置:**

```env
VITE_BASE_URL=/
VITE_API_URL=/dev-api
VITE_API_PROXY_URL=http://localhost:8000
VITE_PORT=5730
VITE_VERSION=1.0.0
```

### Windows 快速启动

```bash
# 双击或执行
start.bat
```

### 生产环境部署

```bash
# Workerman（Supervisor）
[program:novaphp-workerman]
command=php server.php start
directory=/path/to/novaphp
autostart=true
autorestart=true
user=www-data

# Swoole（Supervisor；二进制用 swoole-cli）
[program:novaphp-swoole]
command=swoole-cli swoole.php start
directory=/path/to/novaphp
autostart=true
autorestart=true
user=www-data

# Nginx 反向代理 (FPM 模式)
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/novaphp/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:///run/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Nginx 反代常驻进程（Workerman / Swoole 已监听 8000 时）
# proxy_pass http://127.0.0.1:8000;
```

## 演示图片
![](https://admin.phpframe.org/preview/login.png)

![](https://admin.phpframe.org/preview/user.png)

![](https://admin.phpframe.org/preview/dept.png)

![](https://admin.phpframe.org/preview/homepage.png)

![](https://admin.phpframe.org/preview/ecommerce.png)

![](https://admin.phpframe.org/preview/redis.png)

![](https://admin.phpframe.org/preview/server.png)


## 📦 模块介绍

### 后端核心模块 (`app/`)

```
app/
├── Controllers/          # 控制器层 (23个控制器)
│   ├── AuthController.php        # 认证控制器 (登录/登出/Token刷新)
│   ├── UserController.php         # 用户管理
│   ├── RoleController.php         # 角色管理
│   ├── MenuController.php         # 菜单管理
│   ├── DeptController.php         # 部门管理
│   ├── TenantController.php       # 租户管理
│   ├── ConfigController.php       # 配置管理
│   ├── DictController.php         # 字典管理
│   ├── AttachmentController.php   # 附件管理
│   ├── ArticleController.php      # 文章管理
│   ├── ServerController.php       # 服务器监控
│   ├── CrontabController.php      # 定时任务
│   ├── DatabaseController.php     # 数据库工具
│   ├── GenerateController.php     # 代码生成器
│   └── ...                       # 其他控制器
├── Models/               # 数据模型层 (26个模型)
├── Services/             # 业务服务层 (22个服务)
├── Dao/                   # 数据访问对象 (24个DAO)
├── Middlewares/           # 中间件 (13个中间件)
│   ├── AuthMiddleware.php          # 认证中间件
│   ├── PermissionMiddleware.php    # 权限中间件
│   ├── CasbinRbacMiddleware.php    # Casbin RBAC 中间件
│   ├── TenantMiddleware.php        # 租户中间件
│   └── ...
└── Traits/               # 特征类
    ├── CrudActionTrait.php         # CRUD 操作
    ├── CrudQueryTrait.php         # 查询构造
    ├── CrudFilterTrait.php        # 数据过滤
    └── DataScopeTrait.php         # 数据权限范围
```

### 框架核心模块 (`framework/`)

```
framework/
├── Core/                 # 框架核心
│   ├── Framework.php             # 主入口 (单例)
│   ├── Kernel.php                # 内核引导
│   ├── Router.php                # 路由引擎 (注解+自动推断)
│   └── AttributeRouteLoader.php  # 注解路由加载器
├── Container/            # DI 容器
│   ├── Container.php              # 服务容器
│   └── Compiler/                 # 编译优化
├── Basic/                # 基础类
│   ├── BaseController.php         # 基础控制器
│   ├── BaseService.php           # 基础服务
│   ├── BaseJsonResponse.php      # 统一响应
│   └── Traits/                   # CRUD 特征
├── Tenant/               # 多租户
│   ├── TenantContext.php          # 租户上下文
│   ├── JwtTenantContext.php       # JWT 租户解析
│   └── SessionTenantContext.php   # Session 租户解析
├── Security/             # 安全组件
│   └── CasbinRbac.php            # Casbin RBAC 封装
├── Middleware/           # 框架中间件
│   ├── CorsMiddleware.php         # CORS 跨域
│   ├── CsrfProtectionMiddleware.php # CSRF 防护
│   ├── RateLimitMiddleware.php    # 频率限制
│   └── XssFilterMiddleware.php    # XSS 过滤
├── Plugin/               # 插件系统
│   ├── PluginManager.php          # 插件管理器
│   ├── PluginCacheManager.php     # 插件缓存
│   └── Migration/                 # 迁移执行器
├── Attributes/           # 注解定义
│   ├── Route.php                  # 路由注解
│   ├── Auth.php                   # 认证注解
│   ├── Role.php                   # 角色注解
│   ├── Permission.php             # 权限注解
│   └── Cache.php                  # 缓存注解
├── ORM/                  # ORM 适配层
│   └── Factories/                 # 工厂 (Laravel/ThinkPHP)
├── Utils/                # 工具类
│   ├── JwtFactory.php             # JWT 工厂
│   ├── Captcha.php                # 验证码
│   ├── Pagination.php             # 分页
│   └── Tree.php                   # 树形结构处理
└── View/                 # 视图引擎
    └── ViewRender.php             # Twig 渲染器
```

### 前端模块结构 (`web/src/`)

```
web/src/
├── api/                  # API 接口定义 (26个模块)
│   ├── auth.ts                   # 认证接口
│   ├── system/user.ts            # 用户接口
│   ├── system/role.ts            # 角色接口
│   ├── system/menu.ts            # 菜单接口
│   ├── system/dept.ts            # 部门接口
│   ├── system/tenant.ts          # 租户接口
│   ├── system/config.ts          # 配置接口
│   ├── safeguard/attachment.ts   # 附件接口
│   ├── safeguard/dict.ts          # 字典接口
│   ├── monitor/redis.ts          # Redis 监控
│   ├── tool/generate.ts          # 代码生成
│   └── article.ts                # 文章接口
├── views/                # 页面视图 (60+ 页面)
│   ├── dashboard/                # 仪表盘 (控制台/分析/电商)
│   ├── system/                   # 系统管理 (用户/角色/菜单/部门/岗位/租户/配置)
│   ├── safeguard/                # 安全中心 (附件/字典/数据库/缓存/日志/服务器/Redis)
│   ├── tool/                     # 开发工具 (代码生成/定时任务)
│   ├── auth/                     # 认证页面 (登录/注册/忘记密码)
│   ├── article/                  # 内容管理
│   └── plugin/                   # 插件管理
├── store/                # Pinia 状态管理
│   ├── user.ts                   # 用户状态
│   ├── menu.ts                   # 菜单状态
│   ├── setting.ts                # 系统设置
│   ├── dict.ts                    # 字典缓存
│   ├── table.ts                  # 表格状态
│   └── worktab.ts                # 标签页状态
├── components/           # 公共组件
│   ├── sai/                      # SAI Admin 组件
│   └── core/                     # 核心组件
├── composables/          # 组合式函数
├── utils/                # 工具函数
├── hooks/                # 钩子函数
├── directives/           # 自定义指令
├── enums/                # 枚举类型
├── types/                # TypeScript 类型定义
└── locales/              # 国际化语言包
```

### 插件系统 (`plugins/`)

| 插件 | 名称 | 说明 |
|------|------|------|
| blog | 博客管理 | 文章发布、分类、标签管理 |
| bbs | 论坛示例 | 插件开发示例模板 |

---

## 📡 API 接口文档

### 认证模块 (`/api/core`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/api/core/login` | 用户登录 | 否 |
| POST | `/api/core/logout` | 用户登出 | 是 |
| POST | `/api/core/refresh` | 刷新令牌 | 否 |
| GET | `/api/core/system/user` | 获取当前用户信息 | 是 |
| GET | `/api/core/system/menu` | 获取用户菜单 | 是 |
| GET | `/api/core/system/permissions` | 获取用户权限 | 是 |
| POST | `/api/core/user/modifyPassword` | 修改密码 | 是 |
| POST | `/api/core/user/updateInfo` | 修改个人资料 | 是 |
| GET | `/api/core/tenants-by-username` | 获取用户名对应租户列表 | 否 |
| GET | `/api/core/user-tenants` | 获取当前用户租户列表 | 是 |
| POST | `/api/core/switch-tenant` | 切换租户 | 是 |
| GET | `/api/core/captcha` | 获取验证码 | 否 |

### 用户管理 (`/api/core/system` 或 `/api/core/user`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/user` | 用户列表(分页) | 是 |
| POST | `/api/core/user` | 新增用户 | 是 |
| PUT | `/api/core/user/{id}` | 编辑用户 | 是 |
| DELETE | `/api/core/user/{id}` | 删除用户 | 是 |
| PUT | `/api/core/user/status` | 修改用户状态 | 是 |
| PUT | `/api/core/user/resetPwd` | 重置用户密码 | 是 |
| GET | `/api/core/system/getUserList` | 用户下拉列表 | 是 |

### 角色管理 (`/api/core/role`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/role` | 角色列表 | 是 |
| POST | `/api/core/role` | 新增角色 | 是 |
| PUT | `/api/core/role/{id}` | 编辑角色 | 是 |
| DELETE | `/api/core/role/{id}` | 删除角色 | 是 |
| PUT | `/api/core/role/{id}/menu` | 分配菜单权限 | 是 |

### 菜单管理 (`/api/core/menu`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/menu` | 菜单树 | 是 |
| POST | `/api/core/menu` | 新增菜单 | 是 |
| PUT | `/api/core/menu/{id}` | 编辑菜单 | 是 |
| DELETE | `/api/core/menu/{id}` | 删除菜单 | 是 |

### 部门管理 (`/api/core/dept`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/dept` | 部门树 | 是 |
| POST | `/api/core/dept` | 新增部门 | 是 |
| PUT | `/api/core/dept/{id}` | 编辑部门 | 是 |
| DELETE | `/api/core/dept/{id}` | 删除部门 | 是 |

### 租户管理 (`/api/core/tenant`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/tenant` | 租户列表 | 是 |
| POST | `/api/core/tenant` | 新增租户 | 是 |
| PUT | `/api/core/tenant/{id}` | 编辑租户 | 是 |
| DELETE | `/api/core/tenant/{id}` | 删除租户 | 是 |
| GET | `/api/core/tenant/{id}/users` | 租户用户列表 | 是 |
| POST | `/api/core/tenant/{id}/users` | 添加租户用户 | 是 |
| DELETE | `/api/core/tenant/{id}/users/{userId}` | 移除租户用户 | is |

### 字典管理 (`/api/core/dict`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/dict` | 字典类型列表 | 是 |
| GET | `/api/core/dict/{typeId}` | 字典数据列表 | 是 |
| POST | `/api/core/dict/type` | 新增字典类型 | 是 |
| PUT | `/api/core/dict/type/{id}` | 编辑字典类型 | 是 |
| DELETE | `/api/core/dict/type/{id}` | 删除字典类型 | 是 |
| POST | `/api/core/dict/data` | 新增字典数据 | 是 |
| PUT | `/api/core/dict/data/{id}` | 编辑字典数据 | 是 |
| DELETE | `/api/core/dict/data/{id}` | 删除字典数据 | is |
| GET | `/api/core/system/dictAll` | 所有字典数据(缓存) | 是 |

### 配置管理 (`/api/core/config`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/config` | 配置组列表 | 是 |
| GET | `/api/core/config/group/{groupId}` | 配置项列表 | 是 |
| PUT | `/api/core/config/{id}` | 修改配置项 | is |

### 附件管理 (`/api/core/attachment`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/attachment` | 附件列表 | 是 |
| POST | `/api/core/uploadImage` | 上传图片 | 是 |
| POST | `/api/core/uploadFile` | 上传文件 | 是 |
| POST | `/api/core/chunkUpload` | 分片上传 | 是 |
| GET | `/api/core/system/getResourceList` | 资源列表 | 是 |
| GET | `/api/core/system/getResourceCategory` | 资源分类 | is |

### 文章管理 (`/api/core/article`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/article` | 文章列表 | 是 |
| POST | `/api/core/article` | 发布文章 | 是 |
| PUT | `/api/core/article/{id}` | 编辑文章 | 是 |
| DELETE | `/api/core/article/{id}` | 删除文章 | is |
| GET | `/api/core/article/category` | 文章分类 | 是 |

### 定时任务 (`/api/tool/crontab`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/tool/crontab` | 任务列表 | 是 |
| POST | `/api/tool/crontab` | 新增任务 | 是 |
| PUT | `/api/tool/crontab/{id}` | 编辑任务 | 是 |
| DELETE | `/api/tool/crontab/{id}` | 删除任务 | is |
| PUT | `/api/tool/crontab/{id}/status` | 启停任务 | 是 |
| GET | `/api/tool/crontab/{id}/logs` | 执行日志 | 是 |

### 代码生成 (`/api/tool/generate`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/tool/generate/tables` | 数据库表列表 | 是 |
| GET | `/api/tool/generate/table/{tableName}` | 表字段信息 | 是 |
| POST | `/api/tool/generate` | 生成 CRUD 代码 | is |

### 服务器监控 (`/api/core/server`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/server/info` | 服务器信息 | 是 |
| GET | `/api/core/server/process` | 进程信息 | is |
| GET | `/api/core/server/php` | PHP 配置信息 | is |
| GET | `/api/core/server/mysql` | MySQL 信息 | is |

### Redis 监控 (`/api/core/monitor/redis`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/monitor/redis/info` | Redis 信息 | 是 |
| GET | `/api/core/monitor/redis/keys` | Key 列表 | is |
| GET | `/api/core/monitor/redis/key/{key}` | Key 详情 | 是 |
| DELETE | `/api/core/monitor/redis/key/{key}` | 删除 Key | is |
| GET | `/api/core/monitor/redis/stats` | 统计信息 | is |

### 日志管理 (`/api/core/logs`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/system/getLoginLogList` | 登录日志 | 是 |
| GET | `/api/core/system/getOperationLogList` | 操作日志 | is |
| GET | `/api/core/email/log` | 邮件日志 | is |

### 数据库工具 (`/api/core/database`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/database` | 数据库表列表 | 是 |
| GET | `/api/core/database/table/{table}` | 表详情 | 是 |
| GET | `/api/core/database/ddl` | 表DDL信息 | 是 |
| GET | `/api/core/database/recycle` | 回收站(已删数据) | is |

### 系统工具 (`/api/core/system`)

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/core/system/statistics` | 控制台统计 | 是 |
| GET | `/api/core/system/loginChart` | 登录趋势图 | 是 |
| GET | `/api/core/system/loginBarChart` | 登录柱状图 | is |
| GET | `/api/core/system/clearAllCache` | 清理全部缓存 | is |

### 健康检查

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/_health` | 健康检查 | 否 |



---

## 📊 数据库设计

项目共包含 **29 张** 数据表，采用 `sa_` 前缀命名：

| 表名 | 说明 |
|------|------|
| `sa_system_user` | 系统用户表 |
| `sa_system_role` | 角色表 |
| `sa_system_user_role` | 用户角色关联表 |
| `sa_system_menu` | 菜单表 |
| `sa_system_role_menu` | 角色菜单关联表 |
| `sa_system_dept` | 部门表 |
| `sa_system_user_dept` | 用户部门关联表 |
| `sa_system_post` | 岗位表 |
| `sa_system_user_post` | 用户岗位关联表 |
| `sa_system_tenant` | 租户表 |
| `sa_system_user_tenant` | 用户租户关联表 |
| `sa_system_config` | 系统配置表 |
| `sa_system_config_group` | 配置分组表 |
| `sa_system_dict_type` | 字典类型表 |
| `sa_system_dict_data` | 字典数据表 |
| `sa_system_attachment` | 附件表 |
| `sa_system_category` | 附件分类表 |
| `sa_system_login_log` | 登录日志表 |
| `sa_system_operation_log` | 操作日志表 |
| `sa_system_mail` | 邮件日志表 |
| `sa_tool_crontab` | 定时任务表 |
| `sa_tool_crontab_log` | 任务执行日志表 |
| `sa_tool_generate_tables` | 代码生成-表信息 |
| `sa_tool_generate_columns` | 代码生成-字段信息 |
| `casbin_rule` | Casbin 权限规则表 |
| `sa_article` | 文章表 |
| `sa_article_category` | 文章分类表 |
| `sa_article_banner` | 轮播图表 |
| `plugin_migrations` | 插件迁移记录表 |

---

## 🧪 测试

项目包含 **232 个测试文件**，覆盖框架所有核心模块：

```bash
# 运行所有单元测试
./vendor/bin/phpunit

# 运行特定测试文件
./vendor/bin/phpunit tests/Unit/Core/FrameworkTest.php
```

**测试覆盖模块：**

- Core (Framework/Kernel/Router/App)
- Container (DI/注入/编译)
- Event (事件分发/监听器)
- Middleware (13种中间件)
- Security (Casbin/RBAC/CSRF/XSS)
- Tenant (多租户/JWT/Session)
- ORM (Laravel/ThinkPHP 双适配)
- Cache (PSR-16 缓存)
- Validation (验证器)
- View (Twig 模板)
- Plugin (插件管理/迁移)
- Utils (工具类)

---

## 📝 目录结构概览

```
NovaPHP0.0.9/
├── app/                      # 应用业务代码
│   ├── Controllers/          # 控制器
│   ├── Models/               # 数据模型
│   ├── Services/             # 业务服务
│   ├── Dao/                  # 数据访问对象
│   ├── Middlewares/          # 中间件
│   └── Traits/               # 特征类
├── framework/                # 核心框架代码
│   ├── Core/                 # 框架核心
│   ├── Container/            # DI 容器
│   ├── Basic/                # 基础类
│   ├── Tenant/               # 多租户组件
│   ├── Security/             # 安全组件
│   ├── Middleware/           # 框架中间件
│   ├── Plugin/               # 插件系统
│   ├── Attributes/           # PHP 8 注解
│   ├── ORM/                  # ORM 适配层
│   ├── Utils/                # 工具类
│   └── View/                 # 视图引擎
├── config/                   # 配置文件
├── database/                 # 数据库 SQL 和迁移
├── plugins/                  # 插件目录
│   ├── blog/                 # 博客插件
│   └── bbs/                  # BBS 示例插件
├── web/                      # 前端 Vue 项目
│   ├── src/
│   │   ├── api/              # API 定义
│   │   ├── views/            # 页面组件
│   │   ├── store/            # 状态管理
│   │   ├── components/       # 公共组件
│   │   ├── utils/            # 工具函数
│   │   └── locales/          # 语言包
│   └── dist/                 # 构建产物
├── public/                   # Web 入口和静态资源
├── storage/                  # 运行时文件 (日志/缓存)
├── tests/                    # 测试用例 (232个文件)
├── vendor/                   # Composer 依赖
├── public/index.php          # PHP-FPM / 内置服务器入口
├── server.php                # Workerman 启动入口
├── swoole.php                # Swoole 启动入口（swoole-cli）
├── composer.json             # PHP 依赖配置
└── LICENSE                   # MIT 许可证
```

---

## 🤝 贡谢

### 核心依赖

| 项目 | 作者/组织 | 许可证 |
|------|-----------|--------|
| [Symfony Components](https://symfony.com/) | Symfony Community | MIT |
| [Workerman](https://www.workerman.net/) | walkor | MIT |
| [Swoole](https://www.swoole.com/) | Swoole Group | Apache 2.0 |
| [Laravel Framework](https://laravel.com/) | Taylor Otwell | MIT |
| [ThinkPHP](https://www.thinkphp.cn/) | liu21st | Apache 2.0 |
| [Guzzle](https://docs.guzzlephp.org/) | Guzzle Contributors | MIT |
| [Predis](https://github.com/predis/predis) | Predis | MIT |
| [HTMLPurifier](https://htmlpurifier.org/) | Edward Z. Yang | LGPL |
| [Vue.js](https://vuejs.org/) | Evan You | MIT |
| [Element Plus](https://element-plus.org/) | Element Plus Team | MIT |
| [Casbin](https://casbin.org/) | Tech Lead | Apache 2.0 |
| [Vite](https://vitejs.dev/) | Evan You & Team | MIT |
| [TailwindCSS](https://tailwindcss.com/) | Adam Wathan & Team | MIT |
| [ECharts](https://echarts.apache.org/) | Apache | Apache 2.0 |
| [Pinia](https://pinia.vuejs.org/) | Vue Core Team | MIT |

### 特别感谢

- **PHP 社区** - 提供强大的语言基础
- **开源社区** - 无数优秀库的支持
- **贡献者** - 每一位为项目提交代码的开发者

---

## 📄 License

本项目基于 **MIT** 协议开源 - 详见 [LICENSE](./LICENSE) 文件

