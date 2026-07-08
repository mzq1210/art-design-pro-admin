<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ContractProduct extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_contract_product}}';
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
            [['contract_id', 'product_id', 'product_name'], 'required'],
            [['contract_id', 'product_id', 'category_id', 'sort', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['list_price', 'sale_price', 'discount_rate', 'quantity', 'executed_quantity', 'amount'], 'number'],
            [['start_date', 'end_date'], 'safe'],
            [['delivery_requirements'], 'string'],
            [['product_name', 'media_name'], 'string', 'max' => 100],
            [['ad_type'], 'string', 'max' => 50],
            [['unit'], 'string', 'max' => 20],
        ];
    }

    public function getContract(): ActiveQuery
    {
        return $this->hasOne(Contract::class, ['id' => 'contract_id']);
    }

    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(AdProduct::class, ['id' => 'product_id']);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
