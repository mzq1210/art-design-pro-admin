<?php

declare(strict_types=1);

use yii\db\Migration;

class m260707_170000_add_mobile_real_name_to_user extends Migration
{
    public function safeUp(): void
    {
        $schema = $this->db->getTableSchema('{{%user}}', true);

        if ($schema !== null && !isset($schema->columns['mobile'])) {
            $this->addColumn('{{%user}}', 'mobile', $this->string(32)->notNull()->defaultValue('')->comment('手机号'));
            $this->createIndex('idx_user_mobile', '{{%user}}', 'mobile');
        }

        if ($schema !== null && !isset($schema->columns['real_name'])) {
            $this->addColumn('{{%user}}', 'real_name', $this->string(100)->notNull()->defaultValue('')->comment('姓名'));
            $this->createIndex('idx_user_real_name', '{{%user}}', 'real_name');
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->getTableSchema('{{%user}}', true);

        if ($schema !== null && isset($schema->columns['real_name'])) {
            $this->dropIndex('idx_user_real_name', '{{%user}}');
            $this->dropColumn('{{%user}}', 'real_name');
        }

        if ($schema !== null && isset($schema->columns['mobile'])) {
            $this->dropIndex('idx_user_mobile', '{{%user}}');
            $this->dropColumn('{{%user}}', 'mobile');
        }
    }
}
