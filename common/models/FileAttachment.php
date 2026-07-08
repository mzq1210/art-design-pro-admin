<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $group_id
 * @property string $scene
 * @property string $name
 * @property string $storage_name
 * @property string $path
 * @property string $url
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string|null $remark
 * @property int $created_by
 * @property int $created_at
 * @property int $updated_at
 */
class FileAttachment extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public static function tableName(): string
    {
        return '{{%file_attachment}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'storage_name', 'path', 'url'], 'required'],
            [['group_id', 'size', 'created_by', 'created_at', 'updated_at'], 'integer'],
            [['scene'], 'string', 'max' => 64],
            [['name', 'storage_name', 'remark'], 'string', 'max' => 255],
            [['path', 'url'], 'string', 'max' => 500],
            [['extension'], 'string', 'max' => 20],
            [['mime_type'], 'string', 'max' => 100],
            [['group_id', 'size', 'created_by'], 'default', 'value' => 0],
            [['scene'], 'default', 'value' => 'common'],
            [['extension', 'mime_type', 'remark'], 'default', 'value' => ''],
        ];
    }
}
