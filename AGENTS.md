# AGENTS.md

本文件给后续参与本项目的 AI 助手或协作者使用。项目当前是基于 Yii2 Advanced 的前后端分离后台 API 项目，后端 API 已独立在 `api/` 应用中。

## 协作原则

- 用户当前希望自己动手操作。除非用户明确要求“帮我修改/直接改文件”，否则只在聊天中解释原因、给步骤和代码片段，不要直接改项目文件。
- 如果用户让“检查代码”，可以只读查看文件，但不要顺手重构或修复。
- 不要回滚用户已有改动，不要执行 `git reset --hard`、`git checkout --` 等破坏性命令。
- 修改前先看现有代码风格和配置，优先沿用 Yii2 Advanced 项目的结构。
- 涉及 Yii2、PHP-JWT、Composer、PHP 等库或框架文档时，优先使用 Context7 获取当前文档后再回答。
- 不要把本地敏感配置、数据库密码、JWT secret 等内容完整输出到聊天中。

## 项目概况

- 根目录：`D:\www\art-design-pro-admin`
- 框架：Yii2 Advanced Project Template
- PHP 要求：`>=8.2`
- 当前 CLI PHP 版本：`PHP 8.2.30`
- 当前 Composer 版本：`Composer 2.9.2`
- Yii2 依赖约束：`~2.0.54`
- 当前安装 Yii2 版本：`yiisoft/yii2 2.0.55`
- JWT 库约束：`firebase/php-jwt:^7.1`
- 当前安装 JWT 库版本：`firebase/php-jwt 7.1.0`
- API 应用目录：`api/`
- 公共模型和工具目录：`common/`

关键文件：

- `api/config/main.php`：API 应用主配置、JSON 响应、路由、`user` 组件。
- `api/controllers/BaseController.php`：需要登录的 API 控制器基类。
- `api/controllers/SiteController.php`：公开接口，例如登录、测试、刷新 token。
- `api/controllers/UserController.php`：用户相关接口，例如个人信息。
- `common/models/User.php`：Yii2 `IdentityInterface` 实现，JWT 鉴权入口在这里。
- `common/helpers/JwtHelper.php`：JWT 生成和解析工具。

## 已安装 Composer 直接依赖

生产依赖：

- `php >=8.2`
- `yiisoft/yii2 2.0.55`
- `yiisoft/yii2-bootstrap5 2.0.51`
- `yiisoft/yii2-symfonymailer 2.0.4`
- `firebase/php-jwt 7.1.0`
- `yiisoft/yii2-redis 2.1.2`
- `yiisoft/yii2-queue 2.3.8`

开发依赖：

- `codeception/c3 2.9.0`
- `codeception/codeception 5.3.5`
- `codeception/lib-innerbrowser 4.1.1`
- `codeception/module-asserts 3.3.0`
- `codeception/module-filesystem 3.0.2`
- `codeception/module-phpbrowser 3.0.2`
- `codeception/module-yii2 1.1.12`
- `codeception/verify 3.4.0`
- `phpstan/phpstan 2.2.3`
- `symfony/process 6.4.41`
- `yiisoft/yii2-coding-standards 3.0.1`
- `yiisoft/yii2-debug 2.1.28`
- `yiisoft/yii2-faker 2.0.5`
- `yiisoft/yii2-gii 2.2.7`

Composer scripts：

- `composer cs`：运行 PHP_CodeSniffer。
- `composer cs-fix`：运行 PHP_CodeBeautifier 自动修复代码风格。
- `composer static`：运行 PHPStan 静态分析。
- `composer tests`：运行 Codeception 测试。

## 关键配置

API 主配置位于 `api/config/main.php`。

配置合并顺序：

```php
common/config/params.php
common/config/params-local.php
api/config/params.php
api/config/params-local.php
```

`request` 组件：

- `csrfParam` 为 `_csrf-api`。
- 已配置 `application/json` 使用 `yii\web\JsonParser`，所以接口可以接收 JSON body。

`response` 组件：

- 固定返回 JSON：`yii\web\Response::FORMAT_JSON`。
- 使用 `on beforeSend` 事件统一包装成功和失败响应。

`user` 组件：

- `identityClass` 为 `common\models\User`。
- `enableSession` 为 `false`，API 不依赖 session。
- `loginUrl` 为 `null`，未登录不会跳转登录页，而是返回 401。

`urlManager` 组件：

- `enablePrettyUrl` 为 `true`。
- `enableStrictParsing` 为 `true`。
- `showScriptName` 为 `false`。
- 开启严格路由后，新增接口必须配置 `rules`，否则即使控制器方法存在也会 404。

当前主要路由：

```php
'POST login' => 'site/login',
'GET test' => 'site/test',
'GET user/profile' => 'user/profile',
'POST refresh-token' => 'site/refresh-token',
```

`errorHandler`：

- `errorAction` 为 `null`，API 错误不走传统页面错误 action。

`log`：

- 使用 `yii\log\FileTarget`。
- 记录 `error` 和 `warning`。
- `traceLevel` 在 `YII_DEBUG` 下为 `3`，否则为 `0`。

JWT 相关参数建议放在本地参数配置中，不要硬编码在代码里：

```php
'jwtSecret' => '至少 32 字节以上的随机密钥',
'jwtIssuer' => '签发方',
'jwtAudience' => '接收方',
'jwtAccessTokenExpire' => 7200,
'jwtRefreshTokenExpire' => 3600 * 24 * 30,
```

注意：不要在聊天、提交记录或公开文档中暴露真实 `jwtSecret`、数据库账号密码等敏感信息。

## 当前 API 约定

当前 API 统一返回 JSON，格式为：

```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

失败时大致为：

```json
{
  "code": 401,
  "message": "错误信息",
  "data": null
}
```

统一包装逻辑在 `api/config/main.php` 的 `response` 组件 `on beforeSend` 事件中完成。这是 Yii2 Response 组件事件，不是中间件。

当前路由使用严格解析：

```php
'enablePrettyUrl' => true,
'enableStrictParsing' => true,
'showScriptName' => false,
```

新增接口时一般需要在 `urlManager.rules` 中显式添加路由，否则可能返回 404。

已存在的核心接口：

- `POST /login`：登录，返回 `access_token`、`refresh_token`、过期秒数、token 类型和用户信息。
- `GET /test`：测试接口，返回 `api ok`。
- `GET /user/profile`：需要 Bearer token，返回当前用户信息。
- `POST /refresh-token`：使用 `refresh_token` 换取新的 `access_token`。

## 认证和 JWT

当前项目已从 Yii2 默认 `auth_key` 方案改为 JWT：

- 登录成功后生成 `access_token` 和 `refresh_token`。
- `access_token` 用于请求业务接口。
- `refresh_token` 只用于调用 `/refresh-token` 换新的 `access_token`。
- token 使用 `Authorization: Bearer <token>` 传递。
- Yii2 会通过 `HttpBearerAuth` 自动读取 Bearer token。
- `HttpBearerAuth` 会调用 `Yii::$app->user->loginByAccessToken()`。
- 最终会调用 `common\models\User::findIdentityByAccessToken($token, $type = null)`。
- `findIdentityByAccessToken()` 内部应解析 JWT，拿到用户 ID 后查询 active 用户。

当前 JWT 是无状态方案：

- 重新登录不会自动让旧的 `access_token` 失效。
- 旧的 `refresh_token` 在未过期前仍然可以刷新。
- token 是否可用主要取决于签名、过期时间、type、用户状态。

这是当前阶段可以接受的简单方案。后续如果需要“重新登录踢掉旧 token”“退出登录立即失效”“多设备管理”，再扩展：

- `token_version` 方案：用户表加版本号，JWT payload 带版本号。
- refresh token 入库：支持多设备、退出当前设备、登录记录。
- Redis 黑名单：JWT 带 `jti`，退出或封禁时加入黑名单。

## BaseController 约定

需要登录的 API 控制器建议继承 `api\controllers\BaseController`。

`BaseController` 当前职责：

- 关闭 CSRF。
- 配置 CORS。
- 启用 `yii\filters\auth\HttpBearerAuth`。
- 放行 `OPTIONS` 预检请求。

公开接口，例如 `/login` 和 `/refresh-token`，可以放在不继承 `BaseController` 的控制器中，或在认证规则里加入 `except`。

## 新增接口的一般步骤

1. 判断接口是否需要登录。
2. 需要登录：新建或使用继承 `BaseController` 的控制器。
3. 不需要登录：放在公开控制器中，或配置认证例外。
4. 编写 `actionXxx()` 方法，返回数组即可，由全局 response 自动包装。
5. 在 `api/config/main.php` 的 `urlManager.rules` 添加路由。
6. 用 Postman 测试：
   - 登录拿 token。
   - 业务接口 Header 加 `Authorization: Bearer <access_token>`。
   - refresh 接口 body 传 `refresh_token`。

## declare(strict_types=1)

`declare(strict_types=1);` 是 PHP 文件级配置，只对当前文件生效。

- 不是只放在 `BaseController` 就能全局生效。
- 如果要使用严格类型，需要每个 PHP 文件自己加。
- Yii2 不强制要求，但当前项目新写 API 相关文件可以继续保持这个习惯。

## 注意事项

- 现有部分中文注释或异常信息可能出现乱码，处理时注意文件编码，建议统一保存为 UTF-8。
- JWT secret 必须足够长，不要使用短字符串；`firebase/php-jwt` 会因为密钥太短报 `Provided key is too short`。
- `common/config/params-local.php` 一类本地配置可能包含密钥，不要提交或泄露。
- `enableStrictParsing` 开启后，控制器里写了 action 但没配置路由，依然会 404。
- 如果接口返回 `Attempt to read property "username" on null`，优先检查是否真正启用了 `HttpBearerAuth`，以及请求是否携带了正确的 Bearer token。
