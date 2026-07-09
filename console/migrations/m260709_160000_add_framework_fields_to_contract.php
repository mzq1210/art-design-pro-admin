<?php

declare(strict_types=1);

use yii\db\Migration;

class m260709_160000_add_framework_fields_to_contract extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%crm_contract}}', 'parent_contract_id', $this->integer()->notNull()->defaultValue(0)->comment('关联框架协议ID')->after('contract_type'));
        $this->addColumn('{{%crm_contract}}', 'framework_scope', $this->text()->null()->comment('框架协议合作范围')->after('end_date'));
        $this->createIndex('idx_crm_contract_parent_contract_id', '{{%crm_contract}}', 'parent_contract_id');
        $this->createIndex('idx_crm_contract_contract_type', '{{%crm_contract}}', 'contract_type');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_crm_contract_contract_type', '{{%crm_contract}}');
        $this->dropIndex('idx_crm_contract_parent_contract_id', '{{%crm_contract}}');
        $this->dropColumn('{{%crm_contract}}', 'framework_scope');
        $this->dropColumn('{{%crm_contract}}', 'parent_contract_id');
    }
}
