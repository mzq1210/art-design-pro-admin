<?php

declare(strict_types=1);

use yii\db\Migration;

class m260708_120000_create_manuscript_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_manuscript}}', [
            'id' => $this->primaryKey(),
            'manuscript_no' => $this->string(50)->notNull()->defaultValue('')->comment('稿件编号'),
            'manuscript_type' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('稿件类型：1原创 2客户稿'),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'fulfillment_id' => $this->integer()->notNull()->defaultValue(0)->comment('履约ID'),
            'contract_product_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同产品明细ID'),
            'product_id' => $this->integer()->notNull()->defaultValue(0)->comment('产品ID'),
            'title' => $this->string(200)->notNull()->defaultValue('')->comment('稿件标题'),
            'article_link' => $this->string(255)->notNull()->defaultValue('')->comment('文章链接'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_manuscript_no', '{{%crm_manuscript}}', 'manuscript_no', true);
        $this->createIndex('idx_crm_manuscript_type', '{{%crm_manuscript}}', 'manuscript_type');
        $this->createIndex('idx_crm_manuscript_customer_id', '{{%crm_manuscript}}', 'customer_id');
        $this->createIndex('idx_crm_manuscript_product_id', '{{%crm_manuscript}}', 'product_id');
        $this->createIndex('idx_crm_manuscript_contract_id', '{{%crm_manuscript}}', 'contract_id');
        $this->createIndex('idx_crm_manuscript_fulfillment_id', '{{%crm_manuscript}}', 'fulfillment_id');
        $this->createIndex('idx_crm_manuscript_deleted', '{{%crm_manuscript}}', 'deleted');

        $this->createTable('{{%crm_manuscript_writer}}', [
            'id' => $this->primaryKey(),
            'manuscript_id' => $this->integer()->notNull()->defaultValue(0)->comment('稿件ID'),
            'writer_id' => $this->integer()->notNull()->defaultValue(0)->comment('撰写人ID'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_crm_manuscript_writer_manuscript_id', '{{%crm_manuscript_writer}}', 'manuscript_id');
        $this->createIndex('idx_crm_manuscript_writer_writer_id', '{{%crm_manuscript_writer}}', 'writer_id');
        $this->createIndex('idx_crm_manuscript_writer_deleted', '{{%crm_manuscript_writer}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_manuscript_writer}}');
        $this->dropTable('{{%crm_manuscript}}');
    }
}
