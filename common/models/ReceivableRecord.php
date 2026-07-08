<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ReceivableRecord extends ActiveRecord
{
    public const STATUS_VALID = 1;
    public const STATUS_CANCELLED = 2;

    public static function tableName(): string
    {
        return '{{%crm_receivable_record}}';
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
            [['record_no', 'contract_id', 'receivable_plan_id', 'customer_id', 'owner_user_id'], 'required'],
            [['contract_id', 'receivable_plan_id', 'customer_id', 'owner_user_id', 'status', 'writeoff_status', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['receipt_date'], 'safe'],
            [['receipt_amount'], 'number'],
            [['record_no', 'receipt_method'], 'string', 'max' => 50],
            [['receipt_account', 'bank_serial_no'], 'string', 'max' => 100],
            [['payer_name'], 'string', 'max' => 150],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function getPlan(): ActiveQuery
    {
        return $this->hasOne(ReceivablePlan::class, ['id' => 'receivable_plan_id']);
    }

    public function getContract(): ActiveQuery
    {
        return $this->hasOne(Contract::class, ['id' => 'contract_id']);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
