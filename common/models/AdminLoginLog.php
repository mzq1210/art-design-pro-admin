<?php

namespace common\models;

/**
 * This is the model class for table "admin_login_log".
 *
 * @property int $id ID
 * @property int|null $user_id 管理员ID
 * @property string $username 登录账号
 * @property string $ip 登录IP
 * @property string $user_agent User-Agent
 * @property int $status 状态：0失败 1成功
 * @property string $message 登录结果说明
 * @property int $created_at 创建时间
 */
class AdminLoginLog extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'admin_login_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id'], 'default', 'value' => null],
            [['message'], 'default', 'value' => ''],
            [['created_at'], 'default', 'value' => 0],
            [['user_id', 'status', 'created_at'], 'integer'],
            [['username'], 'string', 'max' => 64],
            [['ip'], 'string', 'max' => 45],
            [['user_agent'], 'string', 'max' => 512],
            [['message'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'username' => 'Username',
            'ip' => 'Ip',
            'user_agent' => 'User Agent',
            'status' => 'Status',
            'message' => 'Message',
            'created_at' => 'Created At',
        ];
    }

}
