<?php

declare(strict_types=1);

use yii\db\Migration;

class m260707_150000_create_oa_contract_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%oa_employee}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull()->comment('本地用户ID'),
            'dingtalk_userid' => $this->string(128)->notNull()->comment('钉钉员工userId'),
            'unionid' => $this->string(128)->notNull()->defaultValue('')->comment('钉钉unionId'),
            'name' => $this->string(100)->notNull()->comment('员工姓名'),
            'mobile' => $this->string(32)->notNull()->defaultValue('')->comment('手机号'),
            'email' => $this->string(255)->notNull()->defaultValue('')->comment('邮箱'),
            'avatar' => $this->string(500)->notNull()->defaultValue('')->comment('头像'),
            'department_ids' => $this->text()->null()->comment('钉钉部门ID列表JSON'),
            'department_names' => $this->string(500)->notNull()->defaultValue('')->comment('部门名称'),
            'position' => $this->string(100)->notNull()->defaultValue('')->comment('职位'),
            'job_number' => $this->string(64)->notNull()->defaultValue('')->comment('工号'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态：1在职，2离职，3禁用'),
            'synced_at' => $this->integer()->null()->comment('最近同步时间'),
            'raw_data' => $this->text()->null()->comment('钉钉原始数据JSON'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_oa_employee_user_id', '{{%oa_employee}}', 'user_id', true);
        $this->createIndex('idx_oa_employee_dingtalk_userid', '{{%oa_employee}}', 'dingtalk_userid', true);
        $this->createIndex('idx_oa_employee_mobile', '{{%oa_employee}}', 'mobile');
        $this->createIndex('idx_oa_employee_status', '{{%oa_employee}}', 'status');
        $this->addForeignKey(
            'fk_oa_employee_user_id',
            '{{%oa_employee}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%oa_contract}}', [
            'id' => $this->primaryKey(),
            'contract_no' => $this->string(64)->notNull()->comment('合同编号'),
            'title' => $this->string(200)->notNull()->comment('合同名称'),
            'customer_name' => $this->string(200)->notNull()->comment('客户名称'),
            'customer_contact' => $this->string(100)->notNull()->defaultValue('')->comment('客户联系人'),
            'customer_phone' => $this->string(32)->notNull()->defaultValue('')->comment('客户联系电话'),
            'sales_user_id' => $this->integer()->notNull()->comment('销售本地用户ID'),
            'sales_dingtalk_userid' => $this->string(128)->notNull()->comment('销售钉钉userId'),
            'amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('合同金额'),
            'contract_type' => $this->string(64)->notNull()->defaultValue('')->comment('合同类型'),
            'sign_subject' => $this->string(128)->notNull()->defaultValue('')->comment('签约主体'),
            'expected_sign_date' => $this->date()->null()->comment('预计签约日期'),
            'payment_method' => $this->string(255)->notNull()->defaultValue('')->comment('付款方式'),
            'remark' => $this->text()->null()->comment('备注'),
            'approval_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('审批状态：0草稿，1审批中，2已通过，3已拒绝，4已撤销'),
            'dingtalk_process_instance_id' => $this->string(128)->notNull()->defaultValue('')->comment('钉钉审批实例ID'),
            'submitted_at' => $this->integer()->null()->comment('提交审批时间'),
            'approved_at' => $this->integer()->null()->comment('审批通过时间'),
            'rejected_at' => $this->integer()->null()->comment('审批拒绝时间'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_oa_contract_contract_no', '{{%oa_contract}}', 'contract_no', true);
        $this->createIndex('idx_oa_contract_sales_user_id', '{{%oa_contract}}', 'sales_user_id');
        $this->createIndex('idx_oa_contract_sales_dingtalk_userid', '{{%oa_contract}}', 'sales_dingtalk_userid');
        $this->createIndex('idx_oa_contract_approval_status', '{{%oa_contract}}', 'approval_status');
        $this->createIndex('idx_oa_contract_process_instance_id', '{{%oa_contract}}', 'dingtalk_process_instance_id');
        $this->createIndex('idx_oa_contract_created_at', '{{%oa_contract}}', 'created_at');
        $this->addForeignKey(
            'fk_oa_contract_sales_user_id',
            '{{%oa_contract}}',
            'sales_user_id',
            '{{%user}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->createTable('{{%oa_approval_instance}}', [
            'id' => $this->primaryKey(),
            'contract_id' => $this->integer()->notNull()->comment('合同ID'),
            'process_code' => $this->string(128)->notNull()->comment('钉钉审批模板processCode'),
            'process_instance_id' => $this->string(128)->notNull()->comment('钉钉审批实例ID'),
            'business_id' => $this->string(128)->notNull()->defaultValue('')->comment('钉钉业务编号'),
            'title' => $this->string(255)->notNull()->defaultValue('')->comment('审批标题'),
            'originator_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('发起人本地用户ID'),
            'originator_dingtalk_userid' => $this->string(128)->notNull()->comment('发起人钉钉userId'),
            'status' => $this->string(32)->notNull()->defaultValue('RUNNING')->comment('钉钉实例状态'),
            'result' => $this->string(32)->notNull()->defaultValue('')->comment('审批结果'),
            'url' => $this->string(500)->notNull()->defaultValue('')->comment('钉钉审批链接'),
            'form_values' => $this->text()->null()->comment('审批表单JSON'),
            'tasks' => $this->text()->null()->comment('审批任务JSON'),
            'operation_records' => $this->text()->null()->comment('操作记录JSON'),
            'raw_data' => $this->text()->null()->comment('钉钉实例详情原始JSON'),
            'started_at' => $this->integer()->null()->comment('发起时间'),
            'finished_at' => $this->integer()->null()->comment('完成时间'),
            'last_synced_at' => $this->integer()->null()->comment('最近同步时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_oa_approval_instance_contract_id', '{{%oa_approval_instance}}', 'contract_id');
        $this->createIndex('idx_oa_approval_instance_process_instance_id', '{{%oa_approval_instance}}', 'process_instance_id', true);
        $this->createIndex('idx_oa_approval_instance_status', '{{%oa_approval_instance}}', 'status');
        $this->createIndex('idx_oa_approval_instance_originator', '{{%oa_approval_instance}}', 'originator_user_id');
        $this->addForeignKey(
            'fk_oa_approval_instance_contract_id',
            '{{%oa_approval_instance}}',
            'contract_id',
            '{{%oa_contract}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%oa_approval_event_log}}', [
            'id' => $this->primaryKey(),
            'event_id' => $this->string(128)->notNull()->defaultValue('')->comment('钉钉事件ID'),
            'event_type' => $this->string(128)->notNull()->defaultValue('')->comment('事件类型'),
            'event_corp_id' => $this->string(128)->notNull()->defaultValue('')->comment('事件企业corpId'),
            'process_instance_id' => $this->string(128)->notNull()->defaultValue('')->comment('审批实例ID'),
            'contract_id' => $this->integer()->notNull()->defaultValue(0)->comment('合同ID'),
            'event_born_time' => $this->bigInteger()->null()->comment('事件发生时间'),
            'payload' => $this->text()->null()->comment('事件原始数据JSON'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('处理状态：0待处理，1成功，2失败，3忽略'),
            'handled_at' => $this->integer()->null()->comment('处理时间'),
            'error' => $this->text()->null()->comment('错误信息'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_oa_approval_event_log_event_id', '{{%oa_approval_event_log}}', 'event_id');
        $this->createIndex('idx_oa_approval_event_log_process_instance_id', '{{%oa_approval_event_log}}', 'process_instance_id');
        $this->createIndex('idx_oa_approval_event_log_contract_id', '{{%oa_approval_event_log}}', 'contract_id');
        $this->createIndex('idx_oa_approval_event_log_status', '{{%oa_approval_event_log}}', 'status');
        $this->createIndex('idx_oa_approval_event_log_created_at', '{{%oa_approval_event_log}}', 'created_at');

        $this->createTable('{{%oa_sync_log}}', [
            'id' => $this->primaryKey(),
            'sync_type' => $this->string(64)->notNull()->comment('同步类型'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('状态：0执行中，1成功，2失败'),
            'total_count' => $this->integer()->notNull()->defaultValue(0)->comment('总数量'),
            'success_count' => $this->integer()->notNull()->defaultValue(0)->comment('成功数量'),
            'fail_count' => $this->integer()->notNull()->defaultValue(0)->comment('失败数量'),
            'started_at' => $this->integer()->null()->comment('开始时间'),
            'finished_at' => $this->integer()->null()->comment('结束时间'),
            'message' => $this->string(255)->notNull()->defaultValue('')->comment('结果说明'),
            'error' => $this->text()->null()->comment('错误信息'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('idx_oa_sync_log_sync_type', '{{%oa_sync_log}}', 'sync_type');
        $this->createIndex('idx_oa_sync_log_status', '{{%oa_sync_log}}', 'status');
        $this->createIndex('idx_oa_sync_log_created_at', '{{%oa_sync_log}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%oa_sync_log}}');
        $this->dropTable('{{%oa_approval_event_log}}');
        $this->dropForeignKey('fk_oa_approval_instance_contract_id', '{{%oa_approval_instance}}');
        $this->dropTable('{{%oa_approval_instance}}');
        $this->dropForeignKey('fk_oa_contract_sales_user_id', '{{%oa_contract}}');
        $this->dropTable('{{%oa_contract}}');
        $this->dropForeignKey('fk_oa_employee_user_id', '{{%oa_employee}}');
        $this->dropTable('{{%oa_employee}}');
    }
}
