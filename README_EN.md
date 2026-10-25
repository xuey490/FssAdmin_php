## FSSADMIN Full-Stack Admin Management System



<strong>FssAdmin is an open-source enterprise-level mid-to-back-end management platform template built on the latest frontend technology stack including Vue3, Vite, TypeScript, Pinia, Pinia persistence plugin, Unocss, and ElementPlus. Compared to other popular admin templates, it is more concise, faster, and easier to understand, making it very beginner-friendly. This project has a low learning curve with comprehensive code comments and extensive examples, making it ideal for enterprise projects, small to medium-sized projects, personal projects, and graduation designs. Users, roles, menus, dictionary management, and common platform pages will be developed sequentially for direct backend API integration, enabling rapid development.</strong>


<p align="center">
  <a href="#features">Features</a> •
  <a href="#tech-stack">Tech Stack</a> •
  <a href="#installation">Installation</a> •
  <a href="#modules">Modules</a> •
  <a href="#api-documentation">API Documentation</a> •
  <a href="#project-structure">Project Structure</a>
</p>

---


## DEMO：https://v3.phpframe.org/  User：admin  Password： 123456

---

## 👨‍💻 Author

**blue2004 (xuey863toy)**
📧 Email: xuey863toy@gmail.com
🌐 GitHub: https://github.com/xuey490

<p align="center">
  ⭐ If this project helps you, please give it a Star! ⭐
</p>

---

## 📖 Project Overview

**FSSADMIN** is a modern full-stack framework based on **FSSPHP**, powered by the **Workerman** in-memory engine, supporting **multi-tenant SaaS architecture**. The project includes:

- **Backend Framework**: Custom lightweight PHP framework (`framework/`)
- **Frontend Application**: Vue 3 + Element Plus admin dashboard (`web/`)
- **Plugin System**: Hot-pluggable feature extensions (`plugins/`)

### Backend Features

| Feature | Description |
|---------|-------------|
| 🚀 **High Performance** | Workerman in-memory engine, 10x+ performance improvement over traditional PHP-FPM |
| 🏢 **Multi-Tenant SaaS** | Complete tenant isolation solution (data row-level + menu permission isolation) |
| 🔐 **RBAC Permissions** | Multi-tenant based RBAC permission control model |
| 🔌 **Dual ORM Support** | Supports both ThinkORM / Laravel ORM (experimental stage code) |
| 🎨 **Attribute Routing** | PHP 8 native annotation routing with auto-scanning and caching |
| 🧩 **Plugin System** | Complete plugin lifecycle management (install/uninstall/enable/disable) |
| 📦 **Code Generator** | One-click CRUD template generation for improved development efficiency |

### Frontend Features

- 🎯 Uses mainstream technologies: Element Plus + Vite + Vue3 + TypeScript + UnoCSS + Pinia
- 🍊 Multiple layouts and rich theme adaptations for mobile, iPad, and PC
- 🐼 Built-in permission management pages ready for direct backend API integration during secondary development
- 🌸 Integrated login, logout, and permission verification
- 🎃 Encapsulated button and input debounce/throttling, background watermark, and left-side infinite recursive menu
- 🍀 Integrated `pinia`, the Vuex replacement - lightweight, simple, easy to use, configured with pinia persistence plugin
- 😍 Secondary encapsulation of Dialog, Drawer, Notification, Message, and Popconfirm for more convenient operations
- 🍓 Secondary encapsulation of axios for better unified interface management
- 🌍 Integrated ECharts charts
- 🌈 Integrated `unocss`, antfu's open-source atomic CSS solution, very lightweight
- 🐟 Multi-environment configuration integration: dev, testing, production
- 🌼 Integrated `eslint + prettier` for code linting and formatting unification
- Integrated `stylelint` for SCSS, LESS, CSS normalization
- 👻 Integrated `mock` API service
- 🏡 Integrated `iconify` icons with custom SVG icon support for elegant icon usage

---

## ✨ Features

### User & Permission System
- ✅ Multi-tenant login and switching
- ✅ JWT + Session dual authentication mode
- ✅ Role-based permission assignment (RBAC)
- ✅ Dynamic route menus
- ✅ Department data permissions
- ✅ Position management
- ✅ Operation logs & Login logs

### System Management
- ✅ System configuration group management
- ✅ Data dictionary maintenance
- ✅ Menu management (supports tree structure)
- ✅ File attachment management (with categories)
- ✅ Scheduled task management (Crontab)
- ✅ Database maintenance tools
- ✅ Server monitoring dashboard
- ✅ Redis monitoring dashboard
- ✅ Cache management tools

### Development Tools
- ✅ Code generator (CRUD template generation)
- ✅ Database table structure import
- ✅ Plugin marketplace & management console
- ✅ Hot-reload development mode

### Content Management (Article)
- ✅ Article publishing & management
- ✅ Article categorization
- ✅ Banner carousel management

### Security Protection
- ✅ CSRF Token protection
- ✅ XSS filtering middleware
- ✅ CORS cross-origin configuration
- ✅ API rate limiting
- ✅ Referer source checking
- ✅ IP blacklist
- ✅ Test environment write operation protection

---

## 🛠 Tech Stack

### Backend Tech Stack

| Category | Technology | Version | Description |
|----------|------------|---------|-------------|
| **Runtime** | PHP | ^8.3 | Requires PHP 8.3+ |
| **HTTP Server** | Workerman | ^5.1 | In-memory engine |
| **Alternative Mode** | PHP-FPM | - | Traditional CGI mode |
| **Dependency Injection** | Symfony DI | ^7.3 | Container & Services |
| **HTTP Component** | Symfony HTTPFoundation | ^7.3 | Request/Response |
| **Routing Component** | Symfony Routing | ^7.3 | URL matching |
| **ORM (Default)** | Illuminate Database | 13.30.* | Laravel Eloquent ORM |
| **ORM (Alternative)** | ThinkORM | ^4.0 | ThinkPHP ORM |
| **Permission Control** | Casbin | ^4.1 | RBAC permission model |
| **JWT Authentication** | Lcobucci JWT | ^5.6 | JSON Web Token |
| **Template Engine** | Twig | ^3.14 | View rendering |
| **Caching** | ThinkCache + Redis | ^3.0 | PSR-16 cache |
| **Session** | Redis Group Session | - | Distributed session |
| **Image Processing** | Intervention Image | ^3.11 | Image manipulation |
| **Markdown** | League Commonmark | ^2.6 | Markdown parsing |
| **Logging** | Monolog | ^3.9 | Structured logging |
| **Queue** | Redis | - | Message subscription |
| **UUID** | Ramsey UUID | ^4.9 | Unique identifier |

### Frontend Tech Stack

| Category | Technology | Version | Description |
|----------|------------|---------|-------------|
| **Framework** | Vue.js | ^3.5 | Progressive JS framework |
| **Build Tool** | Vite | ^7.1 | Next-generation build tool |
| **Language** | TypeScript | ~5.6 | Type-safe JavaScript |
| **UI Component Library** | Element Plus | ^2.11 | Vue 3 component library |
| **CSS Framework** | TailwindCSS | ^4.1 | Atomic CSS |
| **State Management** | Pinia | ^3.0 | Vue state library |
| **Router** | Vue Router | ^4.5 | SPA routing |
| **Internationalization** | Vue I18n | ^9.14 | Multi-language support |
| **HTTP Client** | Axios | ^1.12 | HTTP requests |
| **Charts** | ECharts | ^6.0 | Data visualization |
| **Spreadsheet** | XLSX | ^0.18 | Excel import/export |
| **Rich Text Editor** | WangEditor | ^5.1 | Rich text editor |
| **Video Player** | XGPlayer | ^3.0 | Watermelon Player |
| **Drag & Drop** | Vue Draggable Plus | ^0.6 | Drag sorting |
| **Icons** | Iconify | ^5.0 | Icon library |
| **Encryption** | CryptoJS | ^4.2 | Encryption/decryption |
| **QR Code** | QRCode.vue | ^3.6 | QR code generation |
| **File Download** | FileSaver | ^2.0 | File saving |
| **Syntax Highlighting** | Highlight.js | ^11.0 | Syntax highlighting |
| **Image Cropping** | Vue Img Cutter | ^3.0 | Avatar cropping |

---

## 🚀 Installation

### Environment Requirements

| Software | Version Requirement |
|----------|---------------------|
| PHP | >= 8.3 |
| Composer | >= 2.0 |
| MySQL/MariaDB | >= 8.0 |
| Redis | >= 6.0 |
| Node.js | >= 20.19 (frontend development) |
| pnpm | >= 8.8 (frontend package manager) |
| Extensions | redis, pdo_mysql, mbstring, json, openssl, gd, fileinfo |

### Backend Installation Steps

```bash
# 1. Clone the project
git clone https://github.com/xuey490/FssAdmin.git
cd server

# 2. Install PHP dependencies
composer install

# 3. Edit .env file, configure database and Redis connection
vim .env
```

```
# 4. Edit config file, configure database and Redis connection Key configurations:

DB_CONNECTION=mysql

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=fssoa

DB_USERNAME=root

DB_PASSWORD=root

```

```bash
# 5. Import database structure
mysql -u root -p fssoa < database/fssoa.sql

# 6. Set directory permissions (Linux)
chmod -R 755 storage/
chmod -R 777 storage/logs/ storage/cache/

# 7. Start service (Workerman mode)
php server.php start

# Or use traditional FPM mode (requires Nginx/Apache)
# php -S localhost:8000 -t public
```

### Frontend Installation Steps

```bash
# 1. Enter frontend directory
cd web

# 2. Install dependencies
pnpm install

# 3. Start development server
pnpm dev

# 4. Build production version
pnpm build
```

**`web/.env` key configuration:**

```env
VITE_BASE_URL=/
VITE_API_URL=/dev-api
VITE_API_PROXY_URL=http://localhost:8000
VITE_PORT=5730
VITE_VERSION=1.0.0
```

### Windows Quick Start

```bash
# Double-click or execute
start.bat
```

### Production Deployment

```bash
# 1. Use Supervisor to manage Workerman process
[supervisorctl]
program=novaphp
command=php server.php start
directory=/path/to/novaphp
autostart=true
autorestart=true
user=www-data

# 2. Nginx reverse proxy (FPM mode)
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
```

## System thumb preview
![](https://admin.phpframe.org/preview/login.png)

![](https://admin.phpframe.org/preview/user.png)

![](https://admin.phpframe.org/preview/dept.png)

![](https://admin.phpframe.org/preview/homepage.png)

![](https://admin.phpframe.org/preview/ecommerce.png)

![](https://admin.phpframe.org/preview/redis.png)

![](https://admin.phpframe.org/preview/server.png)


## 📦 Module Introduction

### Core Backend Modules (`app/`)

```
app/
├── Controllers/          # Controller layer (23 controllers)
│   ├── AuthController.php        # Authentication controller (login/logout/token refresh)
│   ├── UserController.php         # User management
│   ├── RoleController.php         # Role management
│   ├── MenuController.php         # Menu management
│   ├── DeptController.php         # Department management
│   ├── TenantController.php       # Tenant management
│   ├── ConfigController.php       # Configuration management
│   ├── DictController.php         # Dictionary management
│   ├── AttachmentController.php   # Attachment management
│   ├── ArticleController.php      # Article management
│   ├── ServerController.php       # Server monitoring
│   ├── CrontabController.php      # Scheduled tasks
│   ├── DatabaseController.php     # Database tools
│   ├── GenerateController.php     # Code generator
│   └── ...                       # Other controllers
├── Models/               # Data model layer (26 models)
├── Services/             # Business service layer (22 services)
├── Dao/                   # Data access objects (24 DAOs)
├── Middlewares/           # Middleware (13 middlewares)
│   ├── AuthMiddleware.php          # Authentication middleware
│   ├── PermissionMiddleware.php    # Permission middleware
│   ├── CasbinRbacMiddleware.php    # Casbin RBAC middleware
│   ├── TenantMiddleware.php        # Tenant middleware
│   └── ...
└── Traits/               # Trait classes
    ├── CrudActionTrait.php         # CRUD operations
    ├── CrudQueryTrait.php         # Query construction
    ├── CrudFilterTrait.php        # Data filtering
    └── DataScopeTrait.java         # Data permission scope
```

### Framework Core Modules (`framework/`)

```
framework/
├── Core/                 # Framework core
│   ├── Framework.php             # Main entry (singleton)
│   ├── Kernel.php                # Kernel bootstrap
│   ├── Router.php                # Routing engine (annotation + auto-inference)
│   └── AttributeRouteLoader.php  # Annotation route loader
├── Container/            # DI container
│   ├── Container.php              # Service container
│   └── Compiler/                 # Compile optimization
├── Basic/                # Base classes
│   ├── BaseController.php         # Base controller
│   ├── BaseService.php           # Base service
│   ├── BaseJsonResponse.java      # Unified response
│   └── Traits/                   # CRUD traits
├── Tenant/               # Multi-tenancy
│   ├── TenantContext.java          # Tenant context
│   ├── JwtTenantContext.php       # JWT tenant resolution
│   └── SessionTenantContext.php   # Session tenant resolution
├── Security/             # Security components
│   └── CasbinRbac.java            # Casbin RBAC wrapper
├── Middleware/           # Framework middleware
│   ├── CorsMiddleware.php         # CORS cross-origin
│   ├── CsrfProtectionMiddleware.php # CSRF protection
│   ├── RateLimitMiddleware.php    # Rate limiting
│   └── XssFilterMiddleware.php    # XSS filtering
├── Plugin/               # Plugin system
│   ├── PluginManager.java          # Plugin manager
│   ├── PluginCacheManager.php     # Plugin cache
│   └── Migration/                 # Migration executor
├── Attributes/           # Annotation definitions
│   ├── Route.php                  # Route annotation
│   ├── Auth.php                   # Auth annotation
│   ├── Role.php                   # Role annotation
│   ├── Permission.java             # Permission annotation
│   └── Cache.php                  # Cache annotation
├── ORM/                  # ORM adapter layer
│   └── Factories/                 # Factory (Laravel/ThinkPHP)
├── Utils/                # Utility classes
│   ├── JwtFactory.php             # JWT factory
│   ├── Captcha.php                # CAPTCHA
│   ├── Pagination.java             # Pagination
│   └── Tree.php                   # Tree structure processing
└── View/                 # View engine
    └── ViewRender.php             # Twig renderer
```

### Frontend Module Structure (`web/src/`)

```
web/src/
├── api/                  # API interface definitions (26 modules)
│   ├── auth.ts                   # Authentication APIs
│   ├── system/user.ts            # User APIs
│   ├── system/role.ts            # Role APIs
│   ├── system/menu.ts            # Menu APIs
│   ├── system/dept.ts            # Department APIs
│   ├── system/tenant.ts          # Tenant APIs
│   ├── system/config.ts          # Configuration APIs
│   ├── safeguard/attachment.ts   # Attachment APIs
│   ├── safeguard/dict.ts          # Dictionary APIs
│   ├── monitor/redis.ts          # Redis monitoring
│   ├── tool/generate.ts          # Code generation
│   └── article.ts                # Article APIs
├── views/                # Page views (60+ pages)
│   ├── dashboard/                # Dashboard (console/analytics/e-commerce)
│   ├── system/                   # System management (users/roles/menus/departments/posts/tenants/configs)
│   ├── safeguard/                # Security center (attachments/dictionaries/database/cache/logs/server/Redis)
│   ├── tool/                     # Development tools (code generator/scheduled tasks)
│   ├── auth/                     # Authentication pages (login/register/forgot password)
│   ├── article/                  # Content management
│   └── plugin/                   # Plugin management
├── store/                # Pinia state management
│   ├── user.ts                   # User state
│   ├── menu.ts                   # Menu state
│   ├── setting.ts                # System settings
│   ├── dict.ts                    # Dictionary cache
│   ├── table.ts                  # Table state
│   └── worktab.ts                # Tab state
├── components/           # Shared components
│   ├── sai/                      # SAI Admin components
│   └── core/                     # Core components
├── composables/          # Composable functions
├── utils/                # Utility functions
├── hooks/                # Hook functions
├── directives/           # Custom directives
├── enums/                # Enum types
├── types/                # TypeScript type definitions
└── locales/              # Internationalization language packs
```

### Plugin System (`plugins/`)

| Plugin | Name | Description |
|--------|------|-------------|
| blog | Blog Management | Article publishing, category, tag management |
| bbs | Forum Example | Plugin development example template |

---

## 📡 API Documentation

### Authentication Module (`/api/core`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| POST | `/api/core/login` | User login | No |
| POST | `/api/core/logout` | User logout | Yes |
| POST | `/api/core/refresh` | Refresh token | No |
| GET | `/api/core/system/user` | Get current user info | Yes |
| GET | `/api/core/system/menu` | Get user menus | Yes |
| GET | `/api/core/system/permissions` | Get user permissions | Yes |
| POST | `/api/core/user/modifyPassword` | Change password | Yes |
| POST | `/api/core/user/updateInfo` | Update profile | Yes |
| GET | `/api/core/tenants-by-username` | Get tenant list by username | No |
| GET | `/api/core/user-tenants` | Get current user tenant list | Yes |
| POST | `/api/core/switch-tenant` | Switch tenant | Yes |
| GET | `/api/core/captcha` | Get CAPTCHA | No |

### User Management (`/api/core/system` or `/api/core/user`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/user` | User list (paginated) | Yes |
| POST | `/api/core/user` | Create user | Yes |
| PUT | `/api/core/user/{id}` | Edit user | Yes |
| DELETE | `/api/core/user/{id}` | Delete user | Yes |
| PUT | `/api/core/user/status` | Change user status | Yes |
| PUT | `/api/core/user/resetPwd` | Reset user password | Yes |
| GET | `/api/core/system/getUserList` | User dropdown list | Yes |

### Role Management (`/api/core/role`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/role` | Role list | Yes |
| POST | `/api/core/role` | Create role | Yes |
| PUT | `/api/core/role/{id}` | Edit role | Yes |
| DELETE | `/api/core/role/{id}` | Delete role | Yes |
| PUT | `/api/core/role/{id}/menu` | Assign menu permissions | Yes |

### Menu Management (`/api/core/menu`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/menu` | Menu tree | Yes |
| POST | `/api/core/menu` | Create menu | Yes |
| PUT | `/api/core/menu/{id}` | Edit menu | Yes |
| DELETE | `/api/core/menu/{id}` | Delete menu | Yes |

### Department Management (`/api/core/dept`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/dept` | Department tree | Yes |
| POST | `/api/core/dept` | Create department | Yes |
| PUT | `/api/core/dept/{id}` | Edit department | Yes |
| DELETE | `/api/core/dept/{id}` | Delete department | Yes |

### Tenant Management (`/api/core/tenant`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/tenant` | Tenant list | Yes |
| POST | `/api/core/tenant` | Create tenant | Yes |
| PUT | `/api/core/tenant/{id}` | Edit tenant | Yes |
| DELETE | `/api/core/tenant/{id}` | Delete tenant | Yes |
| GET | `/api/core/tenant/{id}/users` | Tenant user list | Yes |
| POST | `/api/core/tenant/{id}/users` | Add tenant user | Yes |
| DELETE | `/api/core/tenant/{id}/users/{userId}` | Remove tenant user | Yes |

### Dictionary Management (`/api/core/dict`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/dict` | Dictionary type list | Yes |
| GET | `/api/core/dict/{typeId}` | Dictionary data list | Yes |
| POST | `/api/core/dict/type` | Create dictionary type | Yes |
| PUT | `/api/core/dict/type/{id}` | Edit dictionary type | Yes |
| DELETE | `/api/core/dict/type/{id}` | Delete dictionary type | Yes |
| POST | `/api/core/dict/data` | Create dictionary data | Yes |
| PUT | `/api/core/dict/data/{id}` | Edit dictionary data | Yes |
| DELETE | `/api/core/dict/data/{id}` | Delete dictionary data | Yes |
| GET | `/api/core/system/dictAll` | All dictionary data (cached) | Yes |

### Configuration Management (`/api/core/config`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/config` | Configuration group list | Yes |
| GET | `/api/core/config/group/{groupId}` | Configuration item list | Yes |
| PUT | `/api/core/config/{id}` | Update configuration item | Yes |

### Attachment Management (`/api/core/attachment`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/attachment` | Attachment list | Yes |
| POST | `/api/core/uploadImage` | Upload image | Yes |
| POST | `/api/core/uploadFile` | Upload file | Yes |
| POST | `/api/core/chunkUpload` | Chunked upload | Yes |
| GET | `/api/core/system/getResourceList` | Resource list | Yes |
| GET | `/api/core/system/getResourceCategory` | Resource categories | Yes |

### Article Management (`/api/core/article`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/article` | Article list | Yes |
| POST | `/api/core/article` | Publish article | Yes |
| PUT | `/api/core/article/{id}` | Edit article | Yes |
| DELETE | `/api/core/article/{id}` | Delete article | Yes |
| GET | `/api/core/article/category` | Article categories | Yes |

### Scheduled Tasks (`/api/tool/crontab`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/tool/crontab` | Task list | Yes |
| POST | `/api/tool/crontab` | Create task | Yes |
| PUT | `/api/tool/crontab/{id}` | Edit task | Yes |
| DELETE | `/api/tool/crontab/{id}` | Delete task | Yes |
| PUT | `/api/tool/crontab/{id}/status` | Start/stop task | Yes |
| GET | `/api/tool/crontab/{id}/logs` | Execution logs | Yes |

### Code Generation (`/api/tool/generate`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/tool/generate/tables` | Database table list | Yes |
| GET | `/api/tool/generate/table/{tableName}` | Table field info | Yes |
| POST | `/api/tool/generate` | Generate CRUD code | Yes |

### Server Monitoring (`/api/core/server`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/server/info` | Server info | Yes |
| GET | `/api/core/server/process` | Process info | Yes |
| GET | `/api/core/server/php` | PHP configuration info | Yes |
| GET | `/api/core/server/mysql` | MySQL info | Yes |

### Redis Monitoring (`/api/core/monitor/redis`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/monitor/redis/info` | Redis info | Yes |
| GET | `/api/core/monitor/redis/keys` | Key list | Yes |
| GET | `/api/core/monitor/redis/key/{key}` | Key details | Yes |
| DELETE | `/api/core/monitor/redis/key/{key}` | Delete key | Yes |
| GET | `/api/core/monitor/redis/stats` | Statistics | Yes |

### Log Management (`/api/core/logs`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/system/getLoginLogList` | Login logs | Yes |
| GET | `/api/core/system/getOperationLogList` | Operation logs | Yes |
| GET | `/api/core/email/log` | Email logs | Yes |

### Database Tools (`/api/core/database`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/database` | Database table list | Yes |
| GET | `/api/core/database/table/{table}` | Table details | Yes |
| GET | `/api/core/database/ddl` | Table DDL info | Yes |
| GET | `/api/core/database/recycle` | Recycle bin (deleted data) | Yes |

### System Tools (`/api/core/system`)

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/core/system/statistics` | Dashboard statistics | Yes |
| GET | `/api/core/system/loginChart` | Login trend chart | Yes |
| GET | `/api/core/system/loginBarChart` | Login bar chart | Yes |
| GET | `/api/core/system/clearAllCache` | Clear all cache | Yes |

### Health Check

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/_health` | Health check | No |


---

## 📊 Database Design

The project contains **29 data tables**, using the `sa_` prefix naming convention:

| Table Name | Description |
|------------|-------------|
| `sa_system_user` | System users table |
| `sa_system_role` | Roles table |
| `sa_system_user_role` | User-role association table |
| `sa_system_menu` | Menus table |
| `sa_system_role_menu` | Role-menu association table |
| `sa_system_dept` | Departments table |
| `sa_system_user_dept` | User-department association table |
| `sa_system_post` | Positions table |
| `sa_system_user_post` | User-position association table |
| `sa_system_tenant` | Tenants table |
| `sa_system_user_tenant` | User-tenant association table |
| `sa_system_config` | System configuration table |
| `sa_system_config_group` | Configuration groups table |
| `sa_system_dict_type` | Dictionary types table |
| `sa_system_dict_data` | Dictionary data table |
| `sa_system_attachment` | Attachments table |
| `sa_system_category` | Attachment categories table |
| `sa_system_login_log` | Login logs table |
| `sa_system_operation_log` | Operation logs table |
| `sa_system_mail` | Email logs table |
| `sa_tool_crontab` | Scheduled tasks table |
| `sa_tool_crontab_log` | Task execution logs table |
| `sa_tool_generate_tables` | Code generation - table info |
| `sa_tool_generate_columns` | Code generation - column info |
| `casbin_rule` | Casbin permission rules table |
| `sa_article` | Articles table |
| `sa_article_category` | Article categories table |
| `sa_article_banner` | Carousel banners table |
| `plugin_migrations` | Plugin migration records table |

---

## 🧪 Testing

The project includes **232 test files** covering all core framework modules:

```bash
# Run all unit tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Unit/Core/FrameworkTest.php
```

**Test Coverage Modules:**

- Core (Framework/Kernel/Router/App)
- Container (DI/injection/compilation)
- Event (event dispatch/listeners)
- Middleware (13 middleware types)
- Security (Casbin/RBAC/CSRF/XSS)
- Tenant (Multi-tenancy/JWT/Session)
- ORM (Laravel/ThinkPHP dual adapter)
- Cache (PSR-16 cache)
- Validation (Validators)
- View (Twig template)
- Plugin (plugin management/migration)
- Utils (Utility classes)

---

## 📝 Directory Structure Overview

```
NovaPHP0.0.9/
├── app/                      # Application business code
│   ├── Controllers/          # Controllers
│   ├── Models/               # Data models
│   ├── Services/             # Business services
│   ├── Dao/                  # Data Access Objects
│   ├── Middlewares/          # Middleware
│   └── Traits/               # Trait classes
├── framework/                # Core framework code
│   ├── Core/                 # Framework core
│   ├── Container/            # DI container
│   ├── Basic/                # Base classes
│   ├── Tenant/               # Multi-tenancy components
│   ├── Security/             # Security components
│   ├── Middleware/           # Framework middleware
│   ├── Plugin/               # Plugin system
│   ├── Attributes/           # PHP 8 annotations
│   ├── ORM/                  # ORM adapter layer
│   ├── Utils/                # Utility classes
│   └── View/                 # View engine
├── config/                   # Configuration files
├── database/                 # Database SQL and migrations
├── plugins/                  # Plugins directory
│   ├── blog/                 # Blog plugin
│   └── bbs/                  # BBS example plugin
├── web/                      # Frontend Vue project
│   ├── src/
│   │   ├── api/              # API definitions
│   │   ├── views/            # Page components
│   │   ├── store/            # State management
│   │   ├── components/       # Shared components
│   │   ├── utils/            # Utility functions
│   │   └── locales/          # Language packs
│   └── dist/                 # Build output
├── public/                   # Web entry point and static assets
├── storage/                  # Runtime files (logs/cache)
├── tests/                    # Test cases (232 files)
├── vendor/                   # Composer dependencies
├── server.php                # Workerman startup entry
├── composer.json             # PHP dependency configuration
└── LICENSE                   # MIT License
```

---

## 🤝 Acknowledgments

### Core Dependencies

| Project | Author/Organization | License |
|---------|---------------------|---------|
| [Symfony Components](https://symfony.com/) | Symfony Community | MIT |
| [Workerman](https://www.workerman.net/) | walkor | MIT |
| [Laravel Framework](https://laravel.com/) | Taylor Otwell | MIT |
| [ThinkPHP](https://www.thinkphp.cn/) | liu21st | Apache 2.0 |
| [Vue.js](https://vuejs.org/) | Evan You | MIT |
| [Element Plus](https://element-plus.org/) | Element Plus Team | MIT |
| [Casbin](https://casbin.org/) | Tech Lead | Apache 2.0 |
| [Vite](https://vitejs.dev/) | Evan You & Team | MIT |
| [TailwindCSS](https://tailwindcss.com/) | Adam Wathan & Team | MIT |
| [ECharts](https://echarts.apache.org/) | Apache | Apache 2.0 |
| [Pinia](https://pinia.vuejs.org/) | Vue Core Team | MIT |

### Special Thanks

- **PHP Community** - For providing a strong language foundation
- **Open Source Community** - For countless excellent libraries
- **Contributors** - Every developer who has contributed code to this project

---

## 📄 License

This project is open-sourced under the **MIT** License - See [LICENSE](./LICENSE) file for details


