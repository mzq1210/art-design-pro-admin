<?php

declare(strict_types=1);

use yii\db\Migration;

class m260706_181000_create_operation_log_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%operation_log}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull()->defaultValue(0)->comment('操作用户ID'),
            'username' => $this->string(64)->notNull()->defaultValue('')->comment('操作用户名'),
            'controller' => $this->string(64)->notNull()->defaultValue('')->comment('控制器'),
            'action' => $this->string(64)->notNull()->defaultValue('')->comment('动作'),
            'route' => $this->string(128)->notNull()->defaultValue('')->comment('路由'),
            'permission' => $this->string(100)->notNull()->defaultValue('')->comment('权限标识'),
            'method' => $this->string(10)->notNull()->defaultValue('')->comment('请求方法'),
            'ip' => $this->string(45)->notNull()->defaultValue('')->comment('IP地址'),
            'user_agent' => $this->string(255)->notNull()->defaultValue('')->comment('User-Agent'),
            'request_data' => $this->text()->null()->comment('请求参数'),
            'response_code' => $this->integer()->notNull()->defaultValue(0)->comment('响应码'),
            'message' => $this->string(255)->notNull()->defaultValue('')->comment('响应消息'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态：1成功，0失败'),
            'duration' => $this->integer()->notNull()->defaultValue(0)->comment('耗时毫秒'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
        ]);

        $this->createIndex('idx_operation_log_user_id', '{{%operation_log}}', 'user_id');
        $this->createIndex('idx_operation_log_route', '{{%operation_log}}', 'route');
        $this->createIndex('idx_operation_log_created_at', '{{%operation_log}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%operation_log}}');
    }
}
