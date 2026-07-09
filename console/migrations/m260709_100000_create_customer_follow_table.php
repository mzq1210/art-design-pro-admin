<?php

declare(strict_types=1);

use yii\db\Migration;

class m260709_100000_create_customer_follow_table extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_customer_follow}}', [
            'id' => $this->primaryKey(),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'contact_id' => $this->integer()->notNull()->defaultValue(0)->comment('联系人ID'),
            'owner_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('负责人ID'),
            'follow_time' => $this->integer()->notNull()->defaultValue(0)->comment('跟进时间'),
            'follow_type' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('跟进方式'),
            'follow_status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('跟进阶段'),
            'next_follow_time' => $this->integer()->notNull()->defaultValue(0)->comment('下次跟进时间'),
            'content' => $this->text()->null()->comment('跟进内容'),
            'result' => $this->string(500)->notNull()->defaultValue('')->comment('跟进结果'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_crm_customer_follow_customer_id', '{{%crm_customer_follow}}', 'customer_id');
        $this->createIndex('idx_crm_customer_follow_contact_id', '{{%crm_customer_follow}}', 'contact_id');
        $this->createIndex('idx_crm_customer_follow_owner_user_id', '{{%crm_customer_follow}}', 'owner_user_id');
        $this->createIndex('idx_crm_customer_follow_follow_time', '{{%crm_customer_follow}}', 'follow_time');
        $this->createIndex('idx_crm_customer_follow_deleted', '{{%crm_customer_follow}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_customer_follow}}');
    }
}
