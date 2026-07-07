<?php

declare(strict_types=1);

use yii\db\Migration;

class m260706_181100_create_queue_task_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%queue_task}}', [
            'id' => $this->primaryKey(),
            'job_id' => $this->string(64)->notNull()->defaultValue('')->comment('队列任务ID'),
            'name' => $this->string(100)->notNull()->comment('任务名称'),
            'payload' => $this->text()->null()->comment('任务参数'),
            'result' => $this->text()->null()->comment('执行结果'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('状态：0等待，1执行中，2成功，3失败'),
            'attempts' => $this->integer()->notNull()->defaultValue(0)->comment('尝试次数'),
            'error' => $this->text()->null()->comment('错误信息'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'started_at' => $this->integer()->null()->comment('开始时间'),
            'finished_at' => $this->integer()->null()->comment('结束时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ]);

        $this->createIndex('idx_queue_task_status', '{{%queue_task}}', 'status');
        $this->createIndex('idx_queue_task_created_by', '{{%queue_task}}', 'created_by');
        $this->createIndex('idx_queue_task_created_at', '{{%queue_task}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%queue_task}}');
    }
}
