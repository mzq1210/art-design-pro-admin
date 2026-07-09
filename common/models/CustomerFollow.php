<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class CustomerFollow extends ActiveRecord
{
    public const TYPE_PHONE = 1;
    public const TYPE_WECHAT = 2;
    public const TYPE_VISIT = 3;
    public const TYPE_OTHER = 4;

    public static function tableName(): string
    {
        return '{{%crm_customer_follow}}';
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
            [['customer_id', 'owner_user_id', 'follow_time', 'content'], 'required'],
            [['customer_id', 'contact_id', 'owner_user_id', 'follow_time', 'follow_type', 'follow_status', 'next_follow_time', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['result'], 'string', 'max' => 500],
        ];
    }

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getContact(): ActiveQuery
    {
        return $this->hasOne(CustomerContact::class, ['id' => 'contact_id']);
    }

    public function getOwner(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'owner_user_id']);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
