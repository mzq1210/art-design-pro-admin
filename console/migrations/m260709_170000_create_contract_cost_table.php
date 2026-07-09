<?php

declare(strict_types=1);

use yii\db\Migration;

class m260709_170000_create_contract_cost_table extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_contract_cost}}', [
            'id' => $this->primaryKey(),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'contract_product_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同产品ID'),
            'cost_date' => $this->date()->null()->comment('成本日期'),
            'cost_type' => $this->string(50)->notNull()->defaultValue('')->comment('成本类型'),
            'product_name' => $this->string(100)->notNull()->defaultValue('')->comment('产品名称快照'),
            'amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('成本金额'),
            'reason' => $this->string(255)->notNull()->defaultValue('')->comment('事由'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_crm_contract_cost_contract_id', '{{%crm_contract_cost}}', 'contract_id');
        $this->createIndex('idx_crm_contract_cost_contract_product_id', '{{%crm_contract_cost}}', 'contract_product_id');
        $this->createIndex('idx_crm_contract_cost_cost_type', '{{%crm_contract_cost}}', 'cost_type');
        $this->createIndex('idx_crm_contract_cost_deleted', '{{%crm_contract_cost}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_contract_cost}}');
    }
}
