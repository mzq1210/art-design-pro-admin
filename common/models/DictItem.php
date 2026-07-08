<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $type_id
 * @property string $label
 * @property string $value
 * @property int $status
 * @property int $sort
 * @property string|null $remark
 * @property int $created_at
 * @property int $updated_at
 */
class DictItem extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public static function tableName(): string
    {
        return '{{%dict_item}}';
    }

    public function rules(): array
    {
        return [
            [['type_id', 'label', 'value'], 'required'],
            [['type_id', 'status', 'sort', 'created_at', 'updated_at'], 'integer'],
            [['label', 'value'], 'string', 'max' => 100],
            [['remark'], 'string', 'max' => 255],
            [['remark'], 'default', 'value' => ''],
            [['status'], 'default', 'value' => 1],
            [['sort'], 'default', 'value' => 0],
            [['type_id', 'value'], 'unique', 'targetAttribute' => ['type_id', 'value']],
            [['type_id'], 'exist', 'targetClass' => DictType::class, 'targetAttribute' => ['type_id' => 'id']],
        ];
    }

    public function getType(): ActiveQuery
    {
        return $this->hasOne(DictType::class, ['id' => 'type_id']);
    }
}
