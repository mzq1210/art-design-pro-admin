<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class AdProduct extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_ad_product}}';
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
            [['product_name', 'product_code'], 'required'],
            [['category_id', 'inventory_total', 'inventory_used', 'delivery_cycle_days', 'status', 'is_hot', 'cover_attachment_id', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['list_price', 'base_price', 'sale_price'], 'number'],
            [['specification'], 'string'],
            [['product_name', 'media_name'], 'string', 'max' => 100],
            [['product_code', 'ad_type'], 'string', 'max' => 50],
            [['unit'], 'string', 'max' => 20],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(ProductCategory::class, ['id' => 'category_id']);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
