<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ContractCost extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_contract_cost}}';
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
            [['contract_id', 'cost_type', 'amount'], 'required'],
            [['contract_id', 'contract_product_id', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['amount'], 'number'],
            [['cost_date'], 'safe'],
            [['cost_type'], 'string', 'max' => 50],
            [['product_name'], 'string', 'max' => 100],
            [['reason'], 'string', 'max' => 255],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function getContract(): ActiveQuery
    {
        return $this->hasOne(Contract::class, ['id' => 'contract_id']);
    }

    public function getContractProduct(): ActiveQuery
    {
        return $this->hasOne(ContractProduct::class, ['id' => 'contract_product_id']);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
