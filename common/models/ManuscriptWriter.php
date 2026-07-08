<?php

declare(strict_types=1);

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ManuscriptWriter extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%crm_manuscript_writer}}';
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
            [['manuscript_id', 'writer_id'], 'required'],
            [['manuscript_id', 'writer_id', 'created_by', 'updated_by', 'deleted', 'deleted_at', 'created_at', 'updated_at'], 'integer'],
        ];
    }

    public function getManuscript(): ActiveQuery
    {
        return $this->hasOne(Manuscript::class, ['id' => 'manuscript_id']);
    }

    public function getWriter(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'writer_id']);
    }

    public static function markDeletedByManuscript(int $manuscriptId, int $userId = 0): int
    {
        $now = time();

        return static::updateAll(
            ['deleted' => 1, 'deleted_at' => $now, 'updated_at' => $now, 'updated_by' => $userId],
            ['manuscript_id' => $manuscriptId, 'deleted' => 0]
        );
    }
}
