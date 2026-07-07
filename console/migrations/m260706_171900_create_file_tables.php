<?php

declare(strict_types=1);

use yii\db\Migration;

class m260706_171900_create_file_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%file_group}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(100)->notNull()->comment('分组名称'),
            'code' => $this->string(100)->notNull()->comment('分组编码'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
            'remark' => $this->string(255)->defaultValue('')->comment('备注'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-file_group-code', '{{%file_group}}', 'code', true);

        $this->createTable('{{%file_attachment}}', [
            'id' => $this->primaryKey(),
            'group_id' => $this->integer()->notNull()->defaultValue(0)->comment('分组ID'),
            'scene' => $this->string(64)->notNull()->defaultValue('common')->comment('场景'),
            'name' => $this->string(255)->notNull()->comment('原始文件名'),
            'storage_name' => $this->string(255)->notNull()->comment('存储文件名'),
            'path' => $this->string(500)->notNull()->comment('相对路径'),
            'url' => $this->string(500)->notNull()->comment('访问地址'),
            'extension' => $this->string(20)->notNull()->defaultValue('')->comment('扩展名'),
            'mime_type' => $this->string(100)->notNull()->defaultValue('')->comment('MIME'),
            'size' => $this->integer()->notNull()->defaultValue(0)->comment('大小'),
            'remark' => $this->string(255)->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('上传用户'),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-file_attachment-group_id', '{{%file_attachment}}', 'group_id');
        $this->createIndex('idx-file_attachment-scene', '{{%file_attachment}}', 'scene');
        $this->createIndex('idx-file_attachment-created_at', '{{%file_attachment}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%file_attachment}}');
        $this->dropTable('{{%file_group}}');
    }
}
