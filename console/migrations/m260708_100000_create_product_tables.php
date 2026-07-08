<?php

declare(strict_types=1);

use yii\db\Migration;

class m260708_100000_create_product_tables extends Migration
{
    public function safeUp(): void
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%crm_product_category}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer()->notNull()->defaultValue(0)->comment('上级分类ID'),
            'category_name' => $this->string(100)->notNull()->defaultValue('')->comment('分类名称'),
            'category_code' => $this->string(50)->notNull()->defaultValue('')->comment('分类编码'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_product_category_code', '{{%crm_product_category}}', 'category_code', true);
        $this->createIndex('idx_crm_product_category_parent_id', '{{%crm_product_category}}', 'parent_id');
        $this->createIndex('idx_crm_product_category_name', '{{%crm_product_category}}', 'category_name');
        $this->createIndex('idx_crm_product_category_status', '{{%crm_product_category}}', 'status');
        $this->createIndex('idx_crm_product_category_deleted', '{{%crm_product_category}}', 'deleted');

        $this->createTable('{{%crm_ad_product}}', [
            'id' => $this->primaryKey(),
            'category_id' => $this->integer()->notNull()->defaultValue(0)->comment('产品分类ID'),
            'product_name' => $this->string(100)->notNull()->defaultValue('')->comment('产品名称'),
            'product_code' => $this->string(50)->notNull()->defaultValue('')->comment('产品编码'),
            'media_name' => $this->string(100)->notNull()->defaultValue('')->comment('媒体名称'),
            'ad_type' => $this->string(50)->notNull()->defaultValue('')->comment('广告形式'),
            'unit' => $this->string(20)->notNull()->defaultValue('')->comment('计价单位'),
            'list_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('刊例价'),
            'base_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('底价'),
            'sale_price' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('默认销售价'),
            'inventory_total' => $this->integer()->notNull()->defaultValue(0)->comment('总库存'),
            'inventory_used' => $this->integer()->notNull()->defaultValue(0)->comment('已用库存'),
            'delivery_cycle_days' => $this->integer()->notNull()->defaultValue(0)->comment('履约周期天数'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态'),
            'is_hot' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否热门'),
            'cover_attachment_id' => $this->integer()->notNull()->defaultValue(0)->comment('封面附件ID'),
            'specification' => $this->text()->null()->comment('规格说明'),
            'remark' => $this->string(500)->notNull()->defaultValue('')->comment('备注'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'updated_by' => $this->integer()->notNull()->defaultValue(0)->comment('更新人'),
            'deleted' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否删除'),
            'deleted_at' => $this->integer()->notNull()->defaultValue(0)->comment('删除时间'),
            'created_at' => $this->integer()->notNull()->defaultValue(0)->comment('创建时间'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('更新时间'),
        ], $tableOptions);

        $this->createIndex('uk_crm_ad_product_code', '{{%crm_ad_product}}', 'product_code', true);
        $this->createIndex('idx_crm_ad_product_category_id', '{{%crm_ad_product}}', 'category_id');
        $this->createIndex('idx_crm_ad_product_name', '{{%crm_ad_product}}', 'product_name');
        $this->createIndex('idx_crm_ad_product_media_name', '{{%crm_ad_product}}', 'media_name');
        $this->createIndex('idx_crm_ad_product_ad_type', '{{%crm_ad_product}}', 'ad_type');
        $this->createIndex('idx_crm_ad_product_status', '{{%crm_ad_product}}', 'status');
        $this->createIndex('idx_crm_ad_product_is_hot', '{{%crm_ad_product}}', 'is_hot');
        $this->createIndex('idx_crm_ad_product_deleted', '{{%crm_ad_product}}', 'deleted');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%crm_ad_product}}');
        $this->dropTable('{{%crm_product_category}}');
    }
}
