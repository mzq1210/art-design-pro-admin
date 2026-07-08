<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ReceivablePlan extends ActiveRecord
{
    public const STATUS_PENDING = 1;
    public const STATUS_PARTIAL = 2;
    public const STATUS_RECEIVED = 3;
    public const STATUS_CANCELLED = 4;

    public static function tableName(): string
    {
        return '{{%crm_receivable_plan}}';
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
            [['plan_no', 'contract_id', 'customer_id', 'owner_user_id', 'plan_name'], 'required'],
            [['contract_id', 'customer_id', 'owner_user_id', 'status', 'settlement_status', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['plan_date'], 'safe'],
            [['plan_amount', 'received_amount', 'pending_amount', 'invoice_amount'], 'number'],
            [['plan_no'], 'string', 'max' => 50],
            [['plan_name'], 'string', 'max' => 150],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function getContract(): ActiveQuery
    {
        return $this->hasOne(Contract::class, ['id' => 'contract_id']);
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
}
