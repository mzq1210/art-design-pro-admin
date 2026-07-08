<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $sync_type
 * @property int $status
 * @property int $total_count
 * @property int $success_count
 * @property int $fail_count
 * @property int|null $started_at
 * @property int|null $finished_at
 * @property string $message
 * @property string|null $error
 * @property int $created_at
 * @property int $updated_at
 */
class OaSyncLog extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public static function tableName(): string
    {
        return '{{%oa_sync_log}}';
    }

    public function rules(): array
    {
        return [
            [['sync_type'], 'required'],
            [['status', 'total_count', 'success_count', 'fail_count', 'started_at', 'finished_at', 'created_at', 'updated_at'], 'integer'],
            [['error'], 'string'],
            [['sync_type'], 'string', 'max' => 64],
            [['message'], 'string', 'max' => 255],
            [['message'], 'default', 'value' => ''],
        ];
    }
}
