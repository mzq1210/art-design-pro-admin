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
