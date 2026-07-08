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
