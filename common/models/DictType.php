<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $status
 * @property int $sort
 * @property string|null $remark
 * @property int $created_at
 * @property int $updated_at
 */
class DictType extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dict_type}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'code'], 'required'],
            [['status', 'sort', 'created_at', 'updated_at'], 'integer'],
            [['name', 'code'], 'string', 'max' => 100],
            [['remark'], 'string', 'max' => 255],
            [['remark'], 'default', 'value' => ''],
            [['status'], 'default', 'value' => 1],
            [['sort'], 'default', 'value' => 0],
            [['code'], 'unique'],
        ];
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(DictItem::class, ['type_id' => 'id']);
    }
}
