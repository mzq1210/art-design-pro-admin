<?php

declare(strict_types=1);

use yii\db\Migration;

class m260707_190000_create_customer_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_customer}}', [
            'id' => $this->primaryKey(),
            'customer_name' => $this->string(150)->notNull()->defaultValue('')->comment('客户名称'),
            'customer_code' => $this->string(50)->notNull()->defaultValue('')->comment('客户编码'),
            'customer_type' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('客户类型'),
            'industry' => $this->string(100)->notNull()->defaultValue('')->comment('所属行业'),
            'level' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('客户等级'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('客户状态'),
            'owner_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('负责人ID'),
            'company_address' => $this->string(255)->notNull()->defaultValue('')->comment('公司地址'),
            'website' => $this->string(255)->notNull()->defaultValue('')->comment('官网'),
            'taxpayer_no' => $this->string(100)->notNull()->defaultValue('')->comment('纳税人识别号'),
            'bank_name' => $this->string(150)->notNull()->defaultValue('')->comment('开户行'),
            'bank_account' => $this->string(100)->notNull()->defaultValue('')->comment('银行账号'),
            'invoice_title' => $this->string(150)->notNull()->defaultValue('')->comment('开票抬头'),
            'cooperation_start_date' => $this->date()->null()->comment('合作开始日期'),
            'source' => $this->string(50)->notNull()->defaultValue('')->comment('客户来源'),
            'follow_status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('跟进状态'),
            'latest_follow_time' => $this->integer()->notNull()->defaultValue(0)->comment('最近跟进时间'),
            'signed_contract_amount' => $this->decimal(14, 2)->notNull()->defaultValue(0)->comment('累计签约金额'),
            'received_amount' => $this->decimal(14, 2)->notNull()->defaultValue(0)->comment('累计回款金额'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_customer_customer_code', '{{%crm_customer}}', 'customer_code', true);
        $this->createIndex('idx_crm_customer_customer_name', '{{%crm_customer}}', 'customer_name');
        $this->createIndex('idx_crm_customer_customer_type', '{{%crm_customer}}', 'customer_type');
        $this->createIndex('idx_crm_customer_level', '{{%crm_customer}}', 'level');
        $this->createIndex('idx_crm_customer_status', '{{%crm_customer}}', 'status');
        $this->createIndex('idx_crm_customer_owner_user_id', '{{%crm_customer}}', 'owner_user_id');
        $this->createIndex('idx_crm_customer_deleted', '{{%crm_customer}}', 'deleted');

        $this->createTable('{{%crm_customer_contact}}', [
            'id' => $this->primaryKey(),
            'customer_id' => $this->integer()->notNull()->defaultValue(0)->comment('客户ID'),
            'contact_name' => $this->string(50)->notNull()->defaultValue('')->comment('联系人姓名'),
            'mobile' => $this->string(20)->notNull()->defaultValue('')->comment('手机号'),
            'wechat' => $this->string(50)->notNull()->defaultValue('')->comment('微信号'),
            'email' => $this->string(100)->notNull()->defaultValue('')->comment('邮箱'),
            'position' => $this->string(100)->notNull()->defaultValue('')->comment('职位'),
            'is_primary' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否主联系人'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_crm_customer_contact_customer_id', '{{%crm_customer_contact}}', 'customer_id');
        $this->createIndex('idx_crm_customer_contact_contact_name', '{{%crm_customer_contact}}', 'contact_name');
        $this->createIndex('idx_crm_customer_contact_mobile', '{{%crm_customer_contact}}', 'mobile');
        $this->createIndex('idx_crm_customer_contact_is_primary', '{{%crm_customer_contact}}', 'is_primary');
        $this->createIndex('idx_crm_customer_contact_status', '{{%crm_customer_contact}}', 'status');
        $this->createIndex('idx_crm_customer_contact_deleted', '{{%crm_customer_contact}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_customer_contact}}');
        $this->dropTable('{{%crm_customer}}');
    }
}
