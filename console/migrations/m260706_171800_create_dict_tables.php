<?php

declare(strict_types=1);

use yii\db\Migration;

class m260706_171800_create_dict_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%dict_type}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(100)->notNull()->comment('字典名称'),
            'code' => $this->string(100)->notNull()->comment('字典编码'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态：1启用，0禁用'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
            'remark' => $this->string(255)->defaultValue('')->comment('备注'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-dict_type-code', '{{%dict_type}}', 'code', true);

        $this->createTable('{{%dict_item}}', [
            'id' => $this->primaryKey(),
            'type_id' => $this->integer()->notNull()->comment('字典类型ID'),
            'label' => $this->string(100)->notNull()->comment('显示文本'),
            'value' => $this->string(100)->notNull()->comment('字典值'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态：1启用，0禁用'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
            'remark' => $this->string(255)->defaultValue('')->comment('备注'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-dict_item-type_id', '{{%dict_item}}', 'type_id');
        $this->createIndex('idx-dict_item-type_value', '{{%dict_item}}', ['type_id', 'value'], true);
        $this->addForeignKey(
            'fk-dict_item-type_id',
            '{{%dict_item}}',
            'type_id',
            '{{%dict_type}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-dict_item-type_id', '{{%dict_item}}');
        $this->dropTable('{{%dict_item}}');
        $this->dropTable('{{%dict_type}}');
    }
}
