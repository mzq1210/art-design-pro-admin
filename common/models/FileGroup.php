<?php

declare(strict_types=1);

namespace common\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $sort
 * @property string|null $remark
 * @property int $created_at
 * @property int $updated_at
 */
class FileGroup extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%file_group}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'code'], 'required'],
            [['sort', 'created_at', 'updated_at'], 'integer'],
            [['name', 'code'], 'string', 'max' => 100],
            [['remark'], 'string', 'max' => 255],
            [['remark'], 'default', 'value' => ''],
            [['sort'], 'default', 'value' => 0],
            [['code'], 'unique'],
        ];
    }

    public function getFiles(): ActiveQuery
    {
        return $this->hasMany(FileAttachment::class, ['group_id' => 'id']);
    }
}
