<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $job_id
 * @property string $name
 * @property string|null $payload
 * @property string|null $result
 * @property int $status
 * @property int $attempts
 * @property string|null $error
 * @property int $created_by
 * @property int $created_at
 * @property int|null $started_at
 * @property int|null $finished_at
 * @property int $updated_at
 */
class QueueTask extends ActiveRecord
{
    public const STATUS_WAITING = 0;
    public const STATUS_RUNNING = 1;
    public const STATUS_SUCCESS = 2;
    public const STATUS_FAILED = 3;

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public static function tableName(): string
    {
        return '{{%queue_task}}';
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['payload', 'result', 'error'], 'string'],
            [['status', 'attempts', 'created_by', 'created_at', 'started_at', 'finished_at', 'updated_at'], 'integer'],
            [['job_id'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 100],
        ];
    }
}
