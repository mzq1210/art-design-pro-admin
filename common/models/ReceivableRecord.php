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

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'record_no',
            'contract_id' => static fn(self $model): int => (int)$model->contract_id,
            'contract_no' => static fn(self $model): string => $model->contract->contract_no ?? '',
            'contract_name' => static fn(self $model): string => $model->contract->contract_name ?? '',
            'receivable_plan_id' => static fn(self $model): int => (int)$model->receivable_plan_id,
            'plan_no' => static fn(self $model): string => $model->plan->plan_no ?? '',
            'plan_name' => static fn(self $model): string => $model->plan->plan_name ?? '',
            'customer_id' => static fn(self $model): int => (int)$model->customer_id,
            'customer_name' => static fn(self $model): string => $model->customer->customer_name ?? '',
            'owner_user_id' => static fn(self $model): int => (int)$model->owner_user_id,
            'owner_name' => static function (self $model): string {
                $owner = $model->owner;
                return $owner ? ((string)$owner->real_name !== '' ? $owner->real_name : $owner->username) : '';
            },
            'receipt_date',
            'receipt_amount' => static fn(self $model): string => (string)$model->receipt_amount,
            'receipt_method' => static fn(self $model): string => (string)$model->receipt_method,
            'receipt_account' => static fn(self $model): string => (string)$model->receipt_account,
            'payer_name' => static fn(self $model): string => (string)$model->payer_name,
            'bank_serial_no' => static fn(self $model): string => (string)$model->bank_serial_no,
            'status' => static fn(self $model): int => (int)$model->status,
            'writeoff_status' => static fn(self $model): int => (int)$model->writeoff_status,
            'remark' => static fn(self $model): string => (string)$model->remark,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
            'updated_at' => static fn(self $model): int => (int)$model->updated_at,
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

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
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
