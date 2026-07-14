<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property string $username
 * @property string $controller
 * @property string $action
 * @property string $route
 * @property string $permission
 * @property string $method
 * @property string $ip
 * @property string $user_agent
 * @property string|null $request_data
 * @property int $response_code
 * @property string $message
 * @property int $status
 * @property int $duration
 * @property int $created_at
 */
class OperationLog extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public static function tableName(): string
    {
        return '{{%operation_log}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'response_code', 'status', 'duration', 'created_at'], 'integer'],
            [['request_data'], 'string'],
            [['username', 'controller', 'action', 'route', 'permission', 'method', 'ip', 'user_agent', 'message'], 'string'],
        ];
    }

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'user_id' => static fn(self $model): int => (int)$model->user_id,
            'username',
            'controller',
            'action',
            'route',
            'permission',
            'method',
            'ip',
            'user_agent',
            'request_data' => static fn(self $model): string => $model->request_data ?: '{}',
            'response_code' => static fn(self $model): int => (int)$model->response_code,
            'message',
            'status' => static fn(self $model): int => (int)$model->status,
            'duration' => static fn(self $model): int => (int)$model->duration,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
        ];
    }
}
