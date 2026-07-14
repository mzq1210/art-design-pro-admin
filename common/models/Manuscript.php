<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class Manuscript extends ActiveRecord
{
    public const TYPE_ORIGINAL = 1;
    public const TYPE_CUSTOMER = 2;

    public static function tableName(): string
    {
        return '{{%crm_manuscript}}';
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
            [['manuscript_no', 'title', 'article_link'], 'required'],
            [['manuscript_type', 'customer_id', 'contract_id', 'fulfillment_id', 'contract_product_id', 'product_id', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
            [['manuscript_no'], 'string', 'max' => 50],
            [['title'], 'string', 'max' => 200],
            [['article_link'], 'string', 'max' => 255],
            [['remark'], 'string', 'max' => 500],
        ];
    }

    public function fields(): array
    {
        return [
            'id' => static fn(self $model): int => (int)$model->id,
            'manuscript_no',
            'manuscript_type' => static fn(self $model): int => (int)$model->manuscript_type,
            'customer_id' => static fn(self $model): int => (int)$model->customer_id,
            'customer_name' => static fn(self $model): string => $model->customer->customer_name ?? '',
            'contract_id' => static fn(self $model): int => (int)$model->contract_id,
            'fulfillment_id' => static fn(self $model): int => (int)$model->fulfillment_id,
            'contract_product_id' => static fn(self $model): int => (int)$model->contract_product_id,
            'product_id' => static fn(self $model): int => (int)$model->product_id,
            'product_name' => static fn(self $model): string => $model->product->product_name ?? '',
            'product_code' => static fn(self $model): string => $model->product->product_code ?? '',
            'title',
            'article_link',
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

    public function getWriterLinks(): ActiveQuery
    {
        return $this->hasMany(ManuscriptWriter::class, ['manuscript_id' => 'id'])
            ->andWhere([ManuscriptWriter::tableName() . '.deleted' => 0]);
    }

    public function markDeleted(): void
    {
        $this->deleted = 1;
        $this->deleted_at = time();
    }
}
