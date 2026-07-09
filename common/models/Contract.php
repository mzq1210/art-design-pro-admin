<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class Contract extends ActiveRecord
{
    public const STATUS_DRAFT = 1;
    public const STATUS_EXECUTING = 2;
    public const STATUS_COMPLETED = 3;
    public const STATUS_CANCELLED = 4;


    public const TYPE_SALES = 1;
    public const TYPE_FRAMEWORK = 2;
    public const TYPE_SUPPLEMENT = 3;


    public static function tableName(): string
    {
        return '{{%crm_contract}}';
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
            [['contract_no', 'contract_name', 'customer_id', 'owner_user_id'], 'required'],
            [['customer_id', 'owner_user_id', 'contract_type', 'parent_contract_id', 'status', 'approval_status', 'archive_status', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['sign_date', 'start_date', 'end_date'], 'safe'],
            [['total_amount', 'discount_amount', 'tax_rate', 'tax_amount', 'final_amount', 'received_amount', 'pending_amount', 'invoice_amount'], 'number'],
            [['framework_scope'], 'string'],
            [['contract_no'], 'string', 'max' => 50],
            [['contract_name'], 'string', 'max' => 150],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_SALES => '销售合同',
            self::TYPE_FRAMEWORK => '框架协议',
            self::TYPE_SUPPLEMENT => '补充协议',
        ];
    }

    public function getCustomer(): ActiveQuery
    {
        return $this->hasOne(Customer::class, ['id' => 'customer_id']);
    }

    public function getOwner(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'owner_user_id']);
    }

    public function getParentContract(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_contract_id']);
    }

    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(ContractProduct::class, ['contract_id' => 'id'])
            ->andWhere([ContractProduct::tableName() . '.deleted' => 0])
            ->orderBy([ContractProduct::tableName() . '.sort' => SORT_ASC, ContractProduct::tableName() . '.id' => SORT_ASC]);
    }

    public function getCosts(): ActiveQuery
    {
        return $this->hasMany(ContractCost::class, ['contract_id' => 'id'])
            ->andWhere([ContractCost::tableName() . '.deleted' => 0])
            ->orderBy([ContractCost::tableName() . '.cost_date' => SORT_ASC, ContractCost::tableName() . '.id' => SORT_ASC]);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
