<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property string $dingtalk_userid
 * @property string $unionid
 * @property string $name
 * @property string $mobile
 * @property string $email
 * @property string $avatar
 * @property string|null $department_ids
 * @property string $department_names
 * @property string $position
 * @property string $job_number
 * @property int $status
 * @property int|null $synced_at
 * @property string|null $raw_data
 * @property int $created_at
 * @property int $updated_at
 */
class OaEmployee extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public static function tableName(): string
    {
        return '{{%oa_employee}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'dingtalk_userid', 'name'], 'required'],
            [['user_id', 'status', 'synced_at', 'created_at', 'updated_at'], 'integer'],
            [['department_ids', 'raw_data'], 'string'],
            [['dingtalk_userid', 'unionid'], 'string', 'max' => 128],
            [['name', 'position'], 'string', 'max' => 100],
            [['mobile'], 'string', 'max' => 32],
            [['email'], 'string', 'max' => 255],
            [['avatar', 'department_names'], 'string', 'max' => 500],
            [['job_number'], 'string', 'max' => 64],
            [['unionid', 'mobile', 'email', 'avatar', 'department_names', 'position', 'job_number'], 'default', 'value' => ''],
            [['status'], 'default', 'value' => 1],
            [['dingtalk_userid'], 'unique'],
            [['user_id'], 'unique'],
        ];
    }

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'user_id' => static fn(self $model): int => (int)$model->user_id,
            'username' => static fn(self $model): string => $model->user->username ?? '',
            'dingtalk_userid',
            'unionid',
            'name',
            'mobile',
            'email',
            'avatar',
            'department_ids' => static fn(self $model): array => json_decode((string)$model->department_ids, true) ?: [],
            'department_names',
            'position',
            'job_number',
            'status' => static fn(self $model): int => (int)$model->status,
            'synced_at' => static fn(self $model): int => (int)$model->synced_at,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
            'updated_at' => static fn(self $model): int => (int)$model->updated_at,
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
