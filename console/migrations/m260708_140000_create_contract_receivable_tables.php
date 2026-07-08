<?php

declare(strict_types=1);

use yii\db\Migration;

class m260708_140000_create_contract_receivable_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_contract}}', [
            'id' => $this->primaryKey(),
            'contract_no' => $this->string(50)->notNull()->defaultValue('')->comment('合同编号'),
            'contract_name' => $this->string(150)->notNull()->defaultValue('')->comment('合同名称'),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'owner_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('负责人ID'),
            'contract_type' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('合同类型'),
            'sign_date' => $this->date()->null()->comment('签订日期'),
            'start_date' => $this->date()->null()->comment('开始日期'),
            'end_date' => $this->date()->null()->comment('结束日期'),
            'total_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('产品总金额'),
            'discount_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('优惠金额'),
            'tax_rate' => $this->decimal(5, 2)->notNull()->defaultValue(0)->comment('税率'),
            'tax_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('税额'),
            'final_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('合同最终金额'),
            'received_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已回款金额'),
            'pending_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('待回款金额'),
            'invoice_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已开票金额'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('合同状态：1草稿 2执行中 3已完成 4已作废'),
            'approval_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('审批状态：0未提交 1审批中 2通过 3拒绝'),
            'archive_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('归档状态：0未归档 1已归档'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_contract_no', '{{%crm_contract}}', 'contract_no', true);
        $this->createIndex('idx_crm_contract_customer_id', '{{%crm_contract}}', 'customer_id');
        $this->createIndex('idx_crm_contract_owner_user_id', '{{%crm_contract}}', 'owner_user_id');
        $this->createIndex('idx_crm_contract_status', '{{%crm_contract}}', 'status');
        $this->createIndex('idx_crm_contract_deleted', '{{%crm_contract}}', 'deleted');

        $this->createTable('{{%crm_contract_product}}', [
            'id' => $this->primaryKey(),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'product_id' => $this->integer()->notNull()->defaultValue(0)->comment('产品ID'),
            'category_id' => $this->integer()->notNull()->defaultValue(0)->comment('分类ID'),
            'product_name' => $this->string(100)->notNull()->defaultValue('')->comment('产品名称快照'),
            'media_name' => $this->string(100)->notNull()->defaultValue('')->comment('媒体名称快照'),
            'ad_type' => $this->string(50)->notNull()->defaultValue('')->comment('广告形式快照'),
            'unit' => $this->string(20)->notNull()->defaultValue('')->comment('计价单位快照'),
            'list_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('刊例价'),
            'sale_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('成交单价'),
            'discount_rate' => $this->decimal(5, 2)->notNull()->defaultValue(0)->comment('折扣率'),
            'quantity' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('签约数量'),
            'executed_quantity' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已履约数量'),
            'amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('签约金额'),
            'start_date' => $this->date()->null()->comment('投放开始日期'),
            'end_date' => $this->date()->null()->comment('投放结束日期'),
            'delivery_requirements' => $this->text()->null()->comment('履约要求'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_crm_contract_product_contract_id', '{{%crm_contract_product}}', 'contract_id');
        $this->createIndex('idx_crm_contract_product_product_id', '{{%crm_contract_product}}', 'product_id');
        $this->createIndex('idx_crm_contract_product_deleted', '{{%crm_contract_product}}', 'deleted');

        $this->createTable('{{%crm_receivable_plan}}', [
            'id' => $this->primaryKey(),
            'plan_no' => $this->string(50)->notNull()->defaultValue('')->comment('回款计划编号'),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'owner_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('负责人ID'),
            'plan_name' => $this->string(150)->notNull()->defaultValue('')->comment('计划名称'),
            'plan_date' => $this->date()->null()->comment('计划回款日期'),
            'plan_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('计划金额'),
            'received_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已收金额'),
            'pending_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('待收金额'),
            'invoice_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('开票金额'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态：1待回款 2部分回款 3已回款 4已作废'),
            'settlement_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('结清状态：0未结清 1已结清'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_receivable_plan_no', '{{%crm_receivable_plan}}', 'plan_no', true);
        $this->createIndex('idx_crm_receivable_plan_contract_id', '{{%crm_receivable_plan}}', 'contract_id');
        $this->createIndex('idx_crm_receivable_plan_customer_id', '{{%crm_receivable_plan}}', 'customer_id');
        $this->createIndex('idx_crm_receivable_plan_status', '{{%crm_receivable_plan}}', 'status');
        $this->createIndex('idx_crm_receivable_plan_deleted', '{{%crm_receivable_plan}}', 'deleted');

        $this->createTable('{{%crm_receivable_record}}', [
            'id' => $this->primaryKey(),
            'record_no' => $this->string(50)->notNull()->defaultValue('')->comment('回款记录编号'),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'receivable_plan_id' => $this->integer()->notNull()->defaultValue(0)->comment('回款计划ID'),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'owner_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('负责人ID'),
            'receipt_date' => $this->date()->null()->comment('到账日期'),
            'receipt_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('到账金额'),
            'receipt_method' => $this->string(50)->notNull()->defaultValue('')->comment('回款方式'),
            'receipt_account' => $this->string(100)->notNull()->defaultValue('')->comment('回款账户'),
            'payer_name' => $this->string(150)->notNull()->defaultValue('')->comment('付款方名称'),
            'bank_serial_no' => $this->string(100)->notNull()->defaultValue('')->comment('银行流水号'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态：1有效 2作废'),
            'writeoff_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('核销状态：0未核销 1已核销'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_receivable_record_no', '{{%crm_receivable_record}}', 'record_no', true);
        $this->createIndex('idx_crm_receivable_record_contract_id', '{{%crm_receivable_record}}', 'contract_id');
        $this->createIndex('idx_crm_receivable_record_plan_id', '{{%crm_receivable_record}}', 'receivable_plan_id');
        $this->createIndex('idx_crm_receivable_record_customer_id', '{{%crm_receivable_record}}', 'customer_id');
        $this->createIndex('idx_crm_receivable_record_status', '{{%crm_receivable_record}}', 'status');
        $this->createIndex('idx_crm_receivable_record_deleted', '{{%crm_receivable_record}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_receivable_record}}');
        $this->dropTable('{{%crm_receivable_plan}}');
        $this->dropTable('{{%crm_contract_product}}');
        $this->dropTable('{{%crm_contract}}');
    }
}
