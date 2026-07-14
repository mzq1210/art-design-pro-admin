<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class Fulfillment extends ActiveRecord
{
    public const STATUS_PENDING = 1;
    public const STATUS_EXECUTING = 2;
    public const STATUS_COMPLETED = 3;
    public const STATUS_CANCELLED = 4;

    public static function tableName(): string
    {
        return '{{%crm_fulfillment}}';
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
            [['fulfillment_no', 'customer_id', 'product_id', 'owner_user_id'], 'required'],
            [['contract_id', 'contract_product_id', 'customer_id', 'product_id', 'owner_user_id', 'completed_by', 'status', 'settlement_status', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['plan_date', 'fulfillment_date'], 'safe'],
            [['execute_quantity', 'unit_price', 'execute_amount', 'executed_quantity', 'executed_amount'], 'number'],
            [['content_summary', 'result_summary'], 'string'],
            [['fulfillment_no'], 'string', 'max' => 50],
            [['external_ref'], 'string', 'max' => 100],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'fulfillment_no',
            'contract_id' => static fn(self $model): int => (int)$model->contract_id,
            'contract_product_id' => static fn(self $model): int => (int)$model->contract_product_id,
            'customer_id' => static fn(self $model): int => (int)$model->customer_id,
            'customer_name' => static fn(self $model): string => $model->customer->customer_name ?? '',
            'product_id' => static fn(self $model): int => (int)$model->product_id,
            'product_name' => static fn(self $model): string => $model->product->product_name ?? '',
            'product_code' => static fn(self $model): string => $model->product->product_code ?? '',
            'owner_user_id' => static fn(self $model): int => (int)$model->owner_user_id,
            'owner_name' => static function (self $model): string {
                $owner = $model->owner;
                return $owner ? ((string)$owner->real_name !== '' ? $owner->real_name : $owner->username) : '';
            },
            'completed_by' => static fn(self $model): int => (int)$model->completed_by,
            'completed_by_name' => static function (self $model): string {
                $user = $model->completedBy;
                return $user ? ((string)$user->real_name !== '' ? $user->real_name : $user->username) : '';
            },
            'plan_date',
            'fulfillment_date',
            'execute_quantity' => static fn(self $model): string => (string)$model->execute_quantity,
            'unit_price' => static fn(self $model): string => (string)$model->unit_price,
            'execute_amount' => static fn(self $model): string => (string)$model->execute_amount,
            'executed_quantity' => static fn(self $model): string => (string)$model->executed_quantity,
            'executed_amount' => static fn(self $model): string => (string)$model->executed_amount,
            'remaining_quantity' => static fn(self $model): string => (string)max(0, round((float)$model->execute_quantity - (float)$model->executed_quantity, 2)),
            'status' => static fn(self $model): int => (int)$model->status,
            'settlement_status' => static fn(self $model): int => (int)$model->settlement_status,
            'external_ref' => static fn(self $model): string => (string)$model->external_ref,
            'content_summary' => static fn(self $model): string => (string)$model->content_summary,
            'result_summary' => static fn(self $model): string => (string)$model->result_summary,
            'remark' => static fn(self $model): string => (string)$model->remark,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
            'updated_at' => static fn(self $model): int => (int)$model->updated_at,
        ];
    }

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(AdProduct::class, ['id' => 'product_id']);
    }

    public function getOwner(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'owner_user_id']);
    }

    public function getCompletedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'completed_by']);
    }

    public function getExecutions(): ActiveQuery
    {
        return $this->hasMany(FulfillmentExecution::class, ['fulfillment_id' => 'id'])
            ->andWhere([FulfillmentExecution::tableName() . '.deleted' => 0]);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
