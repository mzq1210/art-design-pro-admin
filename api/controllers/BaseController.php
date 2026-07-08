<?php

namespace api\controllers;

use common\models\OperationLog;
use Yii;
use yii\base\Action;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

class BaseController extends Controller
{
    protected const MENU_CACHE_VERSION_KEY = 'version';
    protected const MENU_CACHE_TTL = 3600;

    private float $requestStartedAt = 0.0;
    private bool $operationLogWritten = false;

    public $enableCsrfValidation = false;

    /**
     * Public actions do not require login.
     */
    protected array $publicActions = [];

    /**
     * Auth-only actions require JWT login, but do not require RBAC permission.
     */
    protected array $authOnlyActions = [];

    /**
     * RBAC permission map. Key is action id, value is permission name.
     */
    protected array $rbacPermissions = [];

    /**
     * 1. 关闭 CSRF
     * 2. CORS 放在 authenticator 前面
     * 3. 启用 HttpBearerAuth
     * 4. options 不走鉴权，方便跨域预检
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        unset($behaviors['authenticator']);
        //限流
        //unset($behaviors['rateLimiter']);

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => array_merge(['options'], $this->publicActions),
        ];

        return $behaviors;
    }

    public function beforeAction($action): bool
    {
        $this->requestStartedAt = microtime(true);

        if (!parent::beforeAction($action)) {
            return false;
        }

        Yii::$app->response->on(Response::EVENT_BEFORE_SEND, function () use ($action): void {
            $this->writeOperationLog($action);
        });

        if ($this->isPublicAction($action) || $this->isAuthOnlyAction($action)) {
            return true;
        }

        $permission = $this->rbacPermissions[$action->id] ?? null;
        if ($permission === null) {
            if ($this->rbacPermissions === []) {
                return true;
            }

            throw new ForbiddenHttpException('Permission rule is not configured.');
        }

        if (!Yii::$app->user->can($permission)) {
            throw new ForbiddenHttpException("No permission: {$permission}.");
        }

        return true;
    }

    private function isPublicAction(Action $action): bool
    {
        return in_array($action->id, $this->publicActions, true);
    }

    private function isAuthOnlyAction(Action $action): bool
    {
        return in_array($action->id, $this->authOnlyActions, true);
    }

    protected function getMenuCacheVersion(): int
    {
        try {
            $version = Yii::$app->menuCache->get(self::MENU_CACHE_VERSION_KEY);
            if ($version !== false) {
                return (int)$version;
            }

            $version = time();
            Yii::$app->menuCache->set(self::MENU_CACHE_VERSION_KEY, $version);

            return $version;
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function getMenuCache(string $key): mixed
    {
        try {
            return Yii::$app->menuCache->get($key);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function setMenuCache(string $key, mixed $value): void
    {
        try {
            Yii::$app->menuCache->set($key, $value, self::MENU_CACHE_TTL);
        } catch (\Throwable) {
        }
    }

    protected function invalidateMenuCache(): void
    {
        try {
            Yii::$app->menuCache->set(self::MENU_CACHE_VERSION_KEY, time());
        } catch (\Throwable) {
        }
    }

    protected function getActionPermission(string $actionId): string
    {
        return $this->rbacPermissions[$actionId] ?? '';
    }

    private function writeOperationLog(Action $action): void
    {
        if ($this->operationLogWritten) {
            return;
        }

        if ($this->shouldSkipOperationLog($action)) {
            return;
        }

        try {
            $this->operationLogWritten = true;
            $response = Yii::$app->response;
            $data = is_array($response->data) ? $response->data : [];
            $message = (string)($data['message'] ?? ($response->isSuccessful ? 'success' : 'failed'));
            $identity = Yii::$app->user->identity;

            $log = new OperationLog();
            $log->user_id = (int)(Yii::$app->user->id ?: 0);
            $log->username = $identity->username ?? '';
            $log->controller = $this->id;
            $log->action = $action->id;
            $log->route = $this->id . '/' . $action->id;
            $log->permission = $this->getActionPermission($action->id);
            $log->method = Yii::$app->request->method;
            $log->ip = Yii::$app->request->userIP ?: '';
            $log->user_agent = substr((string)Yii::$app->request->userAgent, 0, 255);
            $log->request_data = $this->encodeRequestData();
            $log->response_code = (int)$response->statusCode;
            $log->message = substr($message, 0, 255);
            $log->status = $response->isSuccessful ? 1 : 0;
            $log->duration = (int)round((microtime(true) - $this->requestStartedAt) * 1000);
            $log->save(false);
        } catch (\Throwable) {
        }
    }

    private function shouldSkipOperationLog(Action $action): bool
    {
        if (Yii::$app->request->isOptions) {
            return true;
        }

        if (Yii::$app->user->isGuest) {
            return true;
        }

        $route = $this->id . '/' . $action->id;
        $skippedRoutes = [
            'operation-log/index',
            'user/menus',
            'user/profile',
        ];

        return in_array($route, $skippedRoutes, true);
    }

    private function encodeRequestData(): string
    {
        $data = Yii::$app->request->post();
        $data = $this->maskSensitiveData($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'old_password', 'new_password', 'access_token', 'refresh_token', 'token'];

        foreach ($data as $key => $value) {
            if (in_array((string)$key, $sensitiveKeys, true)) {
                $data[$key] = '******';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
