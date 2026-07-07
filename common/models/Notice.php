<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "notice".
 *
 * @property int $id
 * @property string|null $title
 * @property int|null $status
 * @property string|null $created_at
 */
class Notice extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'notice';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status', 'created_at'], 'default', 'value' => null],
            [['title'], 'default', 'value' => ''],
            [['status'], 'integer'],
            [['created_at'], 'safe'],
            [['title'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'status' => 'Status',
            'created_at' => 'Created At',
        ];
    }

}
