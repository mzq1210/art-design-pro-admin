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

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'category_id' => static fn(self $model): int => (int)$model->category_id,
            'category_name' => static fn(self $model): string => $model->category->category_name ?? '',
            'category_code' => static fn(self $model): string => $model->category->category_code ?? '',
            'product_name',
            'product_code',
            'media_name' => static fn(self $model): string => (string)$model->media_name,
            'ad_type' => static fn(self $model): string => (string)$model->ad_type,
            'unit' => static fn(self $model): string => (string)$model->unit,
            'list_price' => static fn(self $model): string => (string)$model->list_price,
            'base_price' => static fn(self $model): string => (string)$model->base_price,
            'sale_price' => static fn(self $model): string => (string)$model->sale_price,
            'inventory_total' => static fn(self $model): int => (int)$model->inventory_total,
            'inventory_used' => static fn(self $model): int => (int)$model->inventory_used,
            'inventory_available' => static fn(self $model): int => max(0, (int)$model->inventory_total - (int)$model->inventory_used),
            'delivery_cycle_days' => static fn(self $model): int => (int)$model->delivery_cycle_days,
            'status' => static fn(self $model): int => (int)$model->status,
            'is_hot' => static fn(self $model): int => (int)$model->is_hot,
            'cover_attachment_id' => static fn(self $model): int => (int)$model->cover_attachment_id,
            'specification' => static fn(self $model): string => (string)$model->specification,
            'remark' => static fn(self $model): string => (string)$model->remark,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
            'updated_at' => static fn(self $model): int => (int)$model->updated_at,
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
