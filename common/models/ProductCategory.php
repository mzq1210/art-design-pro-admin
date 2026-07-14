<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ProductCategory extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_product_category}}';
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
            [['category_name', 'category_code'], 'required'],
            [['parent_id', 'sort', 'status', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['category_name'], 'string', 'max' => 100],
            [['category_code'], 'string', 'max' => 50],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'parent_id' => static fn(self $model): int => (int)$model->parent_id,
            'category_name',
            'category_code',
            'sort' => static fn(self $model): int => (int)$model->sort,
            'status' => static fn(self $model): int => (int)$model->status,
            'remark' => static fn(self $model): string => (string)$model->remark,
            'product_count' => static fn(): int => 0,
            'created_at' => static fn(self $model): int => (int)$model->created_at,
            'updated_at' => static fn(self $model): int => (int)$model->updated_at,
        ];
    }

    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(AdProduct::class, ['category_id' => 'id'])
            ->andWhere([AdProduct::tableName() . '.deleted' => 0]);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
