<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class CustomerContact extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_customer_contact}}';
    }

    public function behaviors(): array
    {
        $userId = static fn (): int => Yii::$app->has('user') && !Yii::$app->user->isGuest
            ? (int)Yii::$app->user->id
            : 0;

        return [
            TimestampBehavior::class,
            [
                'class' => BlameableBehavior::class,
                'value' => $userId,
                'defaultValue' => 0,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['customer_id', 'contact_name', 'mobile'], 'required'],
            [['customer_id', 'is_primary', 'status', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['contact_name', 'wechat'], 'string', 'max' => 50],
            [['mobile'], 'string', 'max' => 20],
            [['email', 'position'], 'string', 'max' => 100],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'customer_id' => static fn(self $model): int => (int)$model->customer_id,
            'customer_name' => static fn(self $model): string => $model->customer->customer_name ?? '',
            'customer_code' => static fn(self $model): string => $model->customer->customer_code ?? '',
            'contact_name',
            'mobile',
            'wechat' => static fn(self $model): string => (string)$model->wechat,
            'email' => static fn(self $model): string => (string)$model->email,
            'position' => static fn(self $model): string => (string)$model->position,
            'is_primary' => static fn(self $model): int => (int)$model->is_primary,
            'status' => static fn(self $model): int => (int)$model->status,
            'remark' => static fn(self $model): string => (string)$model->remark,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
            'updated_at' => static fn(self $model): int => (int)$model->updated_at,
        ];
    }

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }

    public static function markDeletedByCustomer(int $customerId, int $userId = 0): int
    {
        $now = time();

        return static::updateAll(
            ['deleted' => 1, 'deleted_at' => $now, 'updated_at' => $now, 'updated_by' => $userId],
            ['customer_id' => $customerId, 'deleted' => 0]
        );
    }
}
