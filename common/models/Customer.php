<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class Customer extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_customer}}';
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
            [['customer_name'], 'required'],
            [['customer_type', 'level', 'status', 'owner_user_id', 'follow_status', 'latest_follow_time', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['signed_contract_amount', 'received_amount'], 'number'],
            [['cooperation_start_date'], 'safe'],
            [['customer_name', 'invoice_title', 'bank_name'], 'string', 'max' => 150],
            [['customer_code', 'source'], 'string', 'max' => 50],
            [['industry', 'taxpayer_no', 'bank_account'], 'string', 'max' => 100],
            [['company_address', 'website'], 'string', 'max' => 255],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function getContacts(): ActiveQuery
    {
        return $this->hasMany(CustomerContact::class, ['customer_id' => 'id'])
            ->andWhere([CustomerContact::tableName() . '.deleted' => 0]);
    }

    public function getFollows(): ActiveQuery
    {
        return $this->hasMany(CustomerFollow::class, ['customer_id' => 'id'])
            ->andWhere([CustomerFollow::tableName() . '.deleted' => 0])
            ->orderBy([CustomerFollow::tableName() . '.follow_time' => SORT_DESC]);
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
