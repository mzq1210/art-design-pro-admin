<?php

declare(strict_types=1);

use yii\db\Migration;

class m260708_130000_create_fulfillment_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_fulfillment}}', [
            'id' => $this->primaryKey(),
            'fulfillment_no' => $this->string(50)->notNull()->defaultValue('')->comment('履约单号'),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'contract_product_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同产品明细ID'),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'product_id' => $this->integer()->notNull()->defaultValue(0)->comment('产品ID'),
            'owner_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('履约负责人ID'),
            'completed_by' => $this->integer()->notNull()->defaultValue(0)->comment('最近完成人ID'),
            'plan_date' => $this->date()->null()->comment('计划履约日期'),
            'fulfillment_date' => $this->date()->null()->comment('最近履约日期'),
            'execute_quantity' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('计划履约数量'),
            'unit_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('履约单价'),
            'execute_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('计划履约金额'),
            'executed_quantity' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已执行数量'),
            'executed_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已执行金额'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('履约状态：1待执行 2执行中 3已完成 4已作废'),
            'settlement_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('结算状态：0未核销 1已核销'),
            'external_ref' => $this->string(100)->notNull()->defaultValue('')->comment('外部投放单号'),
            'content_summary' => $this->text()->null()->comment('投放内容说明'),
            'result_summary' => $this->text()->null()->comment('履约结果说明'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_fulfillment_no', '{{%crm_fulfillment}}', 'fulfillment_no', true);
        $this->createIndex('idx_crm_fulfillment_contract_id', '{{%crm_fulfillment}}', 'contract_id');
        $this->createIndex('idx_crm_fulfillment_contract_product_id', '{{%crm_fulfillment}}', 'contract_product_id');
        $this->createIndex('idx_crm_fulfillment_customer_id', '{{%crm_fulfillment}}', 'customer_id');
        $this->createIndex('idx_crm_fulfillment_product_id', '{{%crm_fulfillment}}', 'product_id');
        $this->createIndex('idx_crm_fulfillment_owner_user_id', '{{%crm_fulfillment}}', 'owner_user_id');
        $this->createIndex('idx_crm_fulfillment_status', '{{%crm_fulfillment}}', 'status');
        $this->createIndex('idx_crm_fulfillment_plan_date', '{{%crm_fulfillment}}', 'plan_date');
        $this->createIndex('idx_crm_fulfillment_deleted', '{{%crm_fulfillment}}', 'deleted');

        $this->createTable('{{%crm_fulfillment_execution}}', [
            'id' => $this->primaryKey(),
            'fulfillment_id' => $this->integer()->notNull()->defaultValue(0)->comment('履约任务ID'),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'contract_product_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同产品明细ID'),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'product_id' => $this->integer()->notNull()->defaultValue(0)->comment('产品ID'),
            'executor_id' => $this->integer()->notNull()->defaultValue(0)->comment('执行人ID'),
            'execute_date' => $this->date()->null()->comment('执行日期'),
            'execute_quantity' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('本次执行数量'),
            'unit_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('执行单价'),
            'execute_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('本次执行金额'),
            'external_ref' => $this->string(100)->notNull()->defaultValue('')->comment('外部流水号'),
            'content_summary' => $this->text()->null()->comment('执行内容说明'),
            'result_summary' => $this->text()->null()->comment('执行结果说明'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_crm_fulfillment_execution_fulfillment_id', '{{%crm_fulfillment_execution}}', 'fulfillment_id');
        $this->createIndex('idx_crm_fulfillment_execution_contract_id', '{{%crm_fulfillment_execution}}', 'contract_id');
        $this->createIndex('idx_crm_fulfillment_execution_contract_product_id', '{{%crm_fulfillment_execution}}', 'contract_product_id');
        $this->createIndex('idx_crm_fulfillment_execution_customer_id', '{{%crm_fulfillment_execution}}', 'customer_id');
        $this->createIndex('idx_crm_fulfillment_execution_product_id', '{{%crm_fulfillment_execution}}', 'product_id');
        $this->createIndex('idx_crm_fulfillment_execution_executor_id', '{{%crm_fulfillment_execution}}', 'executor_id');
        $this->createIndex('idx_crm_fulfillment_execution_execute_date', '{{%crm_fulfillment_execution}}', 'execute_date');
        $this->createIndex('idx_crm_fulfillment_execution_deleted', '{{%crm_fulfillment_execution}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_fulfillment_execution}}');
        $this->dropTable('{{%crm_fulfillment}}');
    }
}
