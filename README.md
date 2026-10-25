## <p align="center">FSSADMIN 全栈后台管理系统</p>

<p align="center">
  <strong>开源企业级中后台：同一套业务代码，可在 PHP-FPM / Workerman / Swoole 三种运行时中切换。</strong>
</p>

<p align="center">
  <a href="https://www.phpframe.org/">官网</a> ·
  <a href="https://v3.phpframe.org/">在线演示</a> ·
  <a href="https://zread.ai/xuey490/project">使用文档</a> ·
  <a href="https://github.com/xuey490/FssAdmin_php">GitHub</a> ·
  <a href="./README_EN.md">English</a>
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white" />
  <img alt="Vue" src="https://img.shields.io/badge/Vue-3.5-42b883?logo=vuedotjs&logoColor=white" />
  <img alt="License" src="https://img.shields.io/badge/License-MIT-blue" />
  <img alt="Workerman" src="https://img.shields.io/badge/Workerman-5.2-orange" />
  <img alt="Swoole" src="https://img.shields.io/badge/Swoole-6.x-8892BF" />
</p>

**FssAdmin** 适合中小型项目、个人项目，以及企业 OA / ERP / 政务等后台。前端基于 Vue 3 + Vite + TypeScript + Element Plus；后端是自研轻量框架 **FssPHP**，支持多租户 SaaS、Casbin RBAC、插件热插拔与代码生成。

---

## 目录

- [在线演示](#-在线演示)
- [项目特点](#-项目特点)
- [功能一览](#-功能一览)
- [技术栈](#-技术栈)
- [模块结构](#-模块结构)
- [环境要求](#-环境要求)
- [正确部署](#-正确部署)
- [常见部署问题](#-常见部署问题)
- [联系方式与技术支援](#-联系方式与技术支援)
- [开源协议](#-开源协议)

---

## 🎬 在线演示

| 项目 | 内容 |
|------|------|
| 演示地址 | https://v3.phpframe.org/ |
| 账号 | `admin` |
| 密码 | `123456` |
| 官网 | https://www.phpframe.org/ |
| 文档 | https://zread.ai/xuey490/project |

> 演示环境仅供浏览体验，请勿写入生产数据或修改权限。

**界面预览**

![](https://admin.phpframe.org/preview/login.png)
![](https://admin.phpframe.org/preview/homepage.png)
![](https://admin.phpframe.org/preview/user.png)
![](https://admin.phpframe.org/preview/dept.png)
![](https://admin.phpframe.org/preview/ecommerce.png)
![](https://admin.phpframe.org/preview/redis.png)
![](https://admin.phpframe.org/preview/server.png)

---

## ✨ 项目特点

### 后端特点

| 特性 | 说明 |
|------|------|
| 🧬 **三运行时** | FPM / Workerman / Swoole 共用 `app/` + `framework/`，只换入口，不改业务 |
| 🚀 **高性能** | Workerman / Swoole 常驻内存，比传统 PHP-FPM 有数量级提升 |
| 🏢 **多租户 SaaS** | 行级数据隔离 + 菜单权限隔离，支持登录后切换租户 |
| 🔐 **RBAC** | Casbin 多租户权限模型，注解 + 中间件双重校验 |
| 🔌 **双 ORM** | `ORM_DRIVER=laravelORM`（默认 Eloquent）或 `thinkORM` |
| 🎨 **Attribute 路由** | PHP 8 注解路由，自动扫描与缓存 |
| 🧩 **插件系统** | 安装 / 卸载 / 启用 / 禁用，独立命名空间与迁移 |
| 📦 **代码生成器** | 按表结构一键生成前后端 CRUD |
| 🛡 **安全中间件** | CSRF、XSS、CORS、限流、Referer、IP 黑名单 |

同一内核：`Framework::handleRequest()` 对三种入口保持同一契约 `{ code, msg, message, data }`。

| 模式 | 入口 | 启动命令 | 适合 |
|------|------|----------|------|
| **PHP-FPM** | `server/public/index.php` | Nginx / Apache + php-fpm，或 `php -S localhost:8000 -t public` | 传统主机、与现有 LNMP 共存 |
| **Workerman** | `server/server.php` | `php server.php start` | 常驻内存、热重载、HTTP + WebSocket + 队列 |
| **Swoole** | `server/swoole.php` | `swoole-cli swoole.php start` | 多进程 Worker、连接池、文件监视自动 reload |

**不要**同时启动 Workerman 与 Swoole（默认都占用 `8000`）。Swoole 扩展不走 Composer，请使用 **swoole-cli 6.x**。

### 前端特点

- Vue 3 + Vite 8 + TypeScript + Element Plus + Pinia（含持久化）
- Tailwind CSS 4 + UnoCSS 原子化样式，适配 PC / 平板 / 手机
- 后端动态菜单路由，按钮级权限，登录 / 注销 / Token 刷新
- 二次封装 Axios、Dialog、Drawer、Message、表格与上传
- ECharts、WangEditor、分片上传、Excel 导入导出、水印
- `eslint` + `prettier` + `stylelint`，多环境 `.env`
- 图标：Iconify + 自定义 SVG

---

## 🗂 功能一览

### 用户与权限

- 多租户登录、租户切换
- JWT + Session 双认证，可选单设备登录
- 角色 / 菜单 / 部门 / 岗位 / 数据权限
- 动态路由、操作日志、登录日志

### 系统管理

- 系统配置分组、数据字典
- 树形菜单、附件分类与分片上传
- 定时任务（Crontab）及执行日志
- 数据库维护、回收站、缓存清理
- 服务器监控、Redis 监控与 Key 浏览

### 开发工具

- 代码生成器（表导入、预览、生成文件）
- 插件市场与管理终端
- Workerman `reload` / Swoole 文件监视热更新

### 内容与流程

- 文章发布、分类、Banner
- 博客 / 论坛插件示例
- 流程中心（请假、采购、公文、用车等固定表单）

### 安全防护

- CSRF Token、XSS 过滤、CORS 白名单
- 接口限流、Referer 检查、IP 黑名单
- 生产环境要求强 `JWT_SECRET`，禁止使用默认弱密钥
- `APP_DEBUG=false` 时不回显堆栈与绝对路径

---

## 🛠 技术栈

版本与仓库内 `server/composer.json`、`web/package.json` 对齐。

### 后端

| 类别 | 技术 | 版本 | 说明 |
|------|------|------|------|
| 运行时 | PHP | ^8.3 | 必须 8.3+ |
| 进程模型 | PHP-FPM | — | `public/index.php` |
| 进程模型 | Workerman | ^5.2 | `php server.php start`，HTTP 8000 / AI 8001 / WS 1234 |
| 进程模型 | Swoole | 6.x（swoole-cli） | `swoole-cli swoole.php start`，HTTP 8000 / WS 12341 |
| HTTP / DI / 路由 | Symfony HttpFoundation / HttpKernel / Routing / DI | ^7.4 | 请求响应、内核、容器 |
| 配置 / 缓存 / 环境 | Symfony Config / Cache / Dotenv / Finder / Translation | ^7.4 | 配置编译、`.env`、i18n |
| ORM（默认） | illuminate/database 等 | 13.30.* | Laravel Eloquent |
| ORM（备选） | topthink/think-orm | ^4.0.51 | ThinkPHP ORM |
| 校验 / 模板 / 缓存 | think-validate / think-template / think-cache | ^3.0 | 校验、模板、PSR-16 |
| 视图 | Twig | ^3.14 | 服务端渲染 |
| 权限 | Casbin | ^4.2 | RBAC |
| JWT | lcobucci/jwt | ^5.6 | 访问令牌 |
| HTTP 客户端 | Guzzle | ^7.10 | 出站请求 |
| Redis | Predis | ^3.3 | 缓存 / Session / 队列 |
| 安全 | HTMLPurifier / phpseclib | ^4.18 / ^3.0 | XSS 净化、加解密 |
| 图像 / Markdown | Intervention Image / CommonMark | ^4.0 / ^2.6 | 图片与文档 |
| 日志 | Monolog | ^3.9 | 结构化日志 |
| 开发 | PHPStan / PHP-CS-Fixer / PHPUnit | require-dev | 静态分析、格式化、测试 |

### 前端

| 类别 | 技术 | 版本 | 说明 |
|------|------|------|------|
| 框架 | Vue.js | ^3.5 | 渐进式框架 |
| 构建 | Vite | ^8.0 | 开发与生产构建 |
| 语言 | TypeScript | ~5.6 | 类型安全 |
| UI | Element Plus | 2.13 | 组件库 |
| CSS | Tailwind CSS + UnoCSS | ^4.1 / 66.x | 原子化 CSS |
| 状态 | Pinia + persistedstate | ^3.0 / ^4.3 | 状态与持久化 |
| 路由 | Vue Router | 5.0 | SPA 路由 |
| i18n | Vue I18n | 11.4 | 中 / 英等 |
| HTTP | Axios | 1.15 | 统一请求 |
| 图表 | ECharts | ^6.0 | 仪表盘 |
| 表格 / Excel | vxe-table / XLSX | 4.18 / ^0.18 | 大表与导入导出 |
| 编辑器 | WangEditor / md-editor-v3 | ^5.1 / ^6.3 | 富文本与 Markdown |
| 播放 / 拖拽 | XGPlayer / Vue Draggable Plus | ^3.0 / ^0.6 | 媒体与排序 |
| 图标 | Iconify | ^5.0 | 图标集 |
| 工程化 | ESLint / Prettier / Stylelint / Husky | — | 质量门禁 |

---

## 📦 模块结构

仓库根目录：

```
FssAdmin_php/
├── README.md                 # 本说明
├── README_EN.md
├── LICENSE
├── server/                   # 后端（FssPHP）
└── web/                      # 前端（Vue 3 管理台）
```

### 后端业务 `server/app/`

| 层 | 规模 | 职责 |
|----|------|------|
| Controllers | 25 | 认证、用户、角色、菜单、部门、岗位、租户、配置、字典、附件、文章、监控、任务、生成器、插件、邮件等 |
| Models | 29 | 与 `sa_` 表及 Casbin 对应 |
| Services | 24 | 业务编排、权限、租户、流程处理器 |
| Dao | 21 | 数据访问 |
| Middlewares | 13 | 认证、权限、租户、安全 |

### 框架内核 `server/framework/`

| 模块 | 说明 |
|------|------|
| Core | `Framework` / `Kernel` / 注解路由 |
| Container / DI | Symfony 容器与属性注入 |
| Basic | 基类、CRUD Trait、租户 Scope |
| Tenant | JWT / Session 租户上下文 |
| Security | Casbin RBAC |
| Middleware | CORS、CSRF、限流、XSS |
| Plugin | 插件生命周期与迁移 |
| Attributes | Route / Auth / Role / Permission / Cache |
| ORM | Laravel / ThinkPHP 适配 |
| Queue / Pool | Redis 队列、MySQL / Redis 连接池 |
| Console | 代码生成、插件、路由列表等命令 |
| View | Twig 渲染 |

### 前端 `web/src/`

| 目录 | 说明 |
|------|------|
| `views/auth` | 登录 / 注册 / 忘记密码 |
| `views/dashboard` | 控制台、分析、电商、人事、个人中心、赞助 |
| `views/system` | 用户、角色、菜单、部门、岗位、租户、配置、插件 |
| `views/safeguard` | 附件、字典、数据库、缓存、日志、服务器、Redis、邮件日志 |
| `views/tool` | 代码生成、定时任务 |
| `views/article` | 内容管理 |
| `api/` | 与后端模块一一对应的接口定义 |
| `store/` | 用户、菜单、设置、字典、标签页 |
| `components/` | SAI 业务组件 + 布局 / 图表等核心组件 |

### 插件 `server/plugins/`

| 插件 | 说明 |
|------|------|
| blog | 文章、分类、标签 |
| bbs | 论坛插件开发示例 |

### 主要数据表（`sa_` 前缀）

用户 / 角色 / 菜单 / 部门 / 岗位 / 租户及关联表，配置与字典，附件与分类，登录 / 操作 / 邮件日志，定时任务与代码生成元数据，文章与 Banner，`casbin_rule`，`plugin_migrations`。完整结构见 `server/database/fssoa.sql`。

---

## 💻 环境要求

| 软件 | 版本 |
|------|------|
| PHP | >= 8.3 |
| Composer | >= 2.0 |
| MySQL / MariaDB | >= 8.0 |
| Redis | >= 6.0 |
| Node.js | >= 20.19 |
| pnpm | >= 8.8 |
| swoole-cli | 6.x（仅 Swoole 模式） |
| PHP 扩展 | redis、pdo_mysql、mbstring、json、openssl、gd、fileinfo |

---

## 🚀 正确部署

请按 **后端先通、前端再连** 的顺序。开发与生产共用同一套后端，只是进程模型和 Nginx 不同。

### 1. 获取代码

```bash
git clone https://github.com/xuey490/FssAdmin_php.git
cd FssAdmin_php
```

### 2. 后端：依赖与配置

```bash
cd server
composer install
cp .env.example .env
```

编辑 `server/.env`，至少改这些项（**`.env` 与 `config/` 中对应项保持一致**）：

```env
APP_ENV=prod
APP_DEBUG=false
APP_URL=https://your-domain.com

# 前后端分离时填写前端源，禁止使用 *
CORS_ALLOWED_ORIGINS=https://admin.your-domain.com

DB_TYPE=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fssoa
DB_USERNAME=root
DB_PASSWORD=你的密码
DB_PREFIX=sa_
ORM_DRIVER=laravelORM

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
CACHE_DRIVER=redis
SESSION_STORAGE=redis

# 必填：>=32 字节随机密钥，否则框架拒绝启动
# 生成：php -r "echo 'base64:'.base64_encode(random_bytes(48)), PHP_EOL;"
JWT_SECRET=

COOKIE_SECRET=再换一套 32 位随机串
COOKIE_DOMAIN=.your-domain.com
```

导入结构并设权限：

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS fssoa DEFAULT CHARSET utf8mb4;"
mysql -u root -p fssoa < database/fssoa.sql

chmod -R 755 storage/
chmod -R 777 storage/logs/ storage/cache/ storage/workerman/ runtime/
```

### 3. 后端：启动（三选一）

工作目录必须是 `server/`。

```bash
# A. Workerman（推荐开发 / 生产常驻）
php server.php start
php server.php start -d    # 守护进程
php server.php stop
php server.php reload
php server.php status
# Linux 会同时听 8000（/api）和 8001（/api/ai）
# Windows 另开：php server-ai.php start
# 快捷重启：双击 server/start.bat（内部启动 server3.php）

# B. Swoole（需 swoole-cli，不要用系统 php 跑 swoole.php）
swoole-cli swoole.php start
swoole-cli swoole.php start -d
swoole-cli swoole.php stop
swoole-cli swoole.php reload
swoole-cli swoole.php status

# C. 传统 FPM / 内置服务器
php -S localhost:8000 -t public
# 生产用 Nginx + php-fpm，文档根指向 server/public/
```

健康检查：`GET http://127.0.0.1:8000/_health`

### 4. 前端：开发

```bash
cd web
pnpm install
pnpm dev
```

`web/.env` / `web/.env.development` 关键项：

```env
VITE_BASE_URL=/
VITE_API_URL=/api
VITE_API_PROXY_URL=http://localhost:8000
VITE_PORT=3060
VITE_ACCESS_MODE=backend
VITE_WITH_CREDENTIALS=true
```

开发时 Vite 把 `/api` 代理到后端 `8000`。默认开发页：`http://localhost:5730`（以 `vite.config.ts` 的 `server.port` 为准）。

### 5. 前端：生产构建

```bash
cd web
# 按环境改 .env.production
pnpm build
```

`web/.env.production` 示例：

```env
VITE_BASE_URL=/
VITE_API_URL=/prod
VITE_DROP_CONSOLE=true
```

产物在 `web/dist/`。生产 Nginx 需把 `VITE_API_URL` 前缀反代到后端，并把 SPA 回退到 `index.html`。

### 6. 生产：Supervisor + Nginx

**Supervisor（Workerman）**

```ini
[program:fssadmin-workerman]
command=php server.php start
directory=/var/www/FssAdmin_php/server
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/fssadmin-workerman.log
```

**Supervisor（Swoole）**

```ini
[program:fssadmin-swoole]
command=swoole-cli swoole.php start
directory=/var/www/FssAdmin_php/server
autostart=true
autorestart=true
user=www-data
```

**Nginx：前后端分离（推荐）**

```nginx
server {
    listen 80;
    server_name admin.your-domain.com;
    root /var/www/FssAdmin_php/web/dist;
    index index.html;

    # 前端构建里的 API 前缀（开发 /api，生产示例 /prod）
    location /prod/ {
        proxy_pass http://127.0.0.1:8000/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
    }

    location /api/ {
        proxy_pass http://127.0.0.1:8000/api/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

**Nginx：纯 FPM（无常驻进程）**

```nginx
server {
    listen 80;
    server_name api.your-domain.com;
    root /var/www/FssAdmin_php/server/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(log|sql|env)$ { deny all; }
}
```

生产检查清单：

1. `APP_DEBUG=false`，`JWT_SECRET`、`COOKIE_SECRET` 已换成环境独有强密钥  
2. 数据库已导入，`storage/` 可写  
3. Redis 已启动，Session / 缓存驱动指向 Redis  
4. 只选一种后端进程，端口 8000 未被占用  
5. `APP_URL`、`CORS_ALLOWED_ORIGINS` 与浏览器实际域名一致  
6. 前端 `pnpm build` 后的 `VITE_API_URL` 与 Nginx `location` 一致  
7. 用 Supervisor 或 systemd 保活，不要裸跑前台进程  

---

## ❓ 常见部署问题

| 现象 | 处理 |
|------|------|
| 启动报 JWT 密钥无效 | `.env` 设置 `JWT_SECRET`（≥32 字节），并与 `config/jwt.php` 读取来源一致 |
| 前端能开页面但接口 404 | 后端未监听 8000，或 Nginx / Vite 代理前缀与 `VITE_API_URL` 不一致 |
| 跨域失败 | 生产填写 `CORS_ALLOWED_ORIGINS`，不要用 `*`；开发走 Vite 代理即可 |
| 登录后立刻掉线 | 检查 Redis、`SESSION_STORAGE`、Cookie Domain、前后端是否同站或已开凭证 |
| Workerman 与 Swoole 抢端口 | 只启动一个；Swoole WS 为 12341，Workerman WS 为 1234 |
| Windows 下 AI 接口不通 | 再执行 `php server-ai.php start`（Linux 由 `server.php` 一并拉起 8001） |
| 上传 / 日志失败 | `storage/`、`public/uploads/` 对运行用户可写 |
| 改代码不生效 | FPM 要清 OPcache；Workerman 用 `reload`；Swoole 监视 `app/` `config/` `framework/` |

---

## 📡 接口约定

- 统一前缀：`/api/core`（系统）、`/api/tool`（工具）、`/api/article`（内容）、`/api/flow`（流程）
- 响应：`{ code, msg, message, data }`
- 登录：`POST /api/core/login`（无需登录）
- 健康检查：`GET /_health`（无需登录）
- 更细的路径表见历史文档或本仓库 Issue / Zread 文档

本地单测（在 `server/`）：

```bash
./vendor/bin/phpunit
```

当前约 **181** 个测试文件，覆盖内核、容器、中间件、租户、ORM、插件等。

---

## 📞 联系方式与技术支援

### 作者

| 项 | 内容 |
|----|------|
| 作者 | blue2004（xuey863toy） |
| 邮箱 | [xuey863toy@gmail.com](mailto:xuey863toy@gmail.com) |
| GitHub | https://github.com/xuey490 |
| 本仓库 | https://github.com/xuey490/FssAdmin_php |
| 微信 | `xdbyvibm6`（点击复制见演示站「赞助」页） |
| 官网 | https://www.phpframe.org/ |

欢迎加入 QQ 交流群：

![QQ 交流群](https://v3.phpframe.org/assets/qqgroup-C2pjhKmS.png)

### 技术支援怎么提

1. **先查文档**：官网 https://www.phpframe.org/ ，智能文档 https://zread.ai/xuey490/project  
2. **搜 / 提 Issue**：https://github.com/xuey490/FssAdmin_php/issues ，附 PHP / Node 版本、启动方式（FPM / Workerman / Swoole）、完整报错与复现步骤  
3. **群内提问**：QQ 群发截图与 `.env` 中已打码的关键配置（不要发数据库密码、JWT 密钥）  
4. **邮件**：xuey863toy@gmail.com ，主题建议带 `[FssAdmin]`  
5. **商业 / 源码咨询**：可加微信 `xdbyvibm6` 沟通全套源码与定制（详见演示站赞助页）

社区支援为自愿互助，不承诺 SLA。生产故障请在邮件或 Issue 中标明「生产」并提供日志片段。

<p align="center">⭐ 若本项目对你有帮助，欢迎 Star 支持后续维护 ⭐</p>

---

## 🤝 致谢

站在这些开源项目之上：Symfony、Workerman、Swoole、Laravel Eloquent、ThinkPHP / ThinkORM、Casbin、Vue、Vite、Element Plus、Pinia、Tailwind CSS、ECharts。

感谢 PHP 社区、前端社区以及每一位提交 Issue 与 PR 的开发者。

---

## 📄 开源协议

本项目基于 **MIT** 协议开源，详见 [LICENSE](./LICENSE)。
