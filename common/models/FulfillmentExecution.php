<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class FulfillmentExecution extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_fulfillment_execution}}';
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
            [['fulfillment_id', 'executor_id'], 'required'],
            [['fulfillment_id', 'contract_id', 'contract_product_id', 'customer_id', 'product_id', 'executor_id', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['execute_date'], 'safe'],
            [['execute_quantity', 'unit_price', 'execute_amount'], 'number'],
            [['content_summary', 'result_summary'], 'string'],
            [['external_ref'], 'string', 'max' => 100],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function getFulfillment(): ActiveQuery
    {
        return $this->hasOne(Fulfillment::class, ['id' => 'fulfillment_id']);
    }

    public function getExecutor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'executor_id']);
    }
}
