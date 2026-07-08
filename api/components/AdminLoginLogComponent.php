<?php

declare(strict_types=1);

namespace api\components;

use common\models\AdminLoginLog;
use Throwable;
use Yii;
use yii\base\Component;
use yii\helpers\StringHelper;

class AdminLoginLogComponent extends Component
{
    public const STATUS_FAILED = 0;
    public const STATUS_SUCCESS = 1;

    public function success(int $userId, string $username, string $message = 'Login success'): void
    {
        $this->record($userId, $username, self::STATUS_SUCCESS, $message);
    }

    public function failed(string $username, string $message = 'Login failed', int|null $userId = null): void
    {
        $this->record($userId, $username, self::STATUS_FAILED, $message);
    }

    public function record(int|null $userId, string $username, int $status, string $message): void
    {
        try {
            $request = Yii::$app->request;

            $log = new AdminLoginLog();
            $log->user_id = $userId;
            $log->username = StringHelper::truncate($username, 64, '');
            $log->ip = StringHelper::truncate($request->userIP ?? '', 45, '');
            $log->user_agent = StringHelper::truncate($request->userAgent ?? '', 512, '');
            $log->status = $status;
            $log->message = StringHelper::truncate($message, 255, '');
            $log->save(false);
        } catch (Throwable $e) {
            Yii::warning($e->getMessage(), __METHOD__);
        }
    }
}
