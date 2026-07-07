<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "menu".
 *
 * @property int $id 菜单ID
 * @property int $parent_id 父级ID，0表示顶级
 * @property int $type 类型：1目录，2菜单，3按钮
 * @property string $title 菜单标题
 * @property string|null $name 路由名称
 * @property string|null $path 路由路径
 * @property string|null $component 组件路径
 * @property string|null $icon 图标
 * @property string|null $permission 绑定权限标识，如 user.view
 * @property int $sort 排序，越小越靠前
 * @property int $visible 是否显示：1显示，0隐藏
 * @property int $keep_alive 是否缓存：1是，0否
 * @property int $is_external 是否外链：1是，0否
 * @property string|null $external_url 外链地址
 * @property string|null $remark 备注
 * @property int|null $created_at 创建时间
 * @property int|null $updated_at 更新时间
 */
class Menu extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'menu';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'path', 'component', 'icon', 'permission', 'external_url', 'remark', 'created_at', 'updated_at'], 'default', 'value' => null],
            [['is_external'], 'default', 'value' => 0],
            [['keep_alive'], 'default', 'value' => 1],
            [['parent_id', 'type', 'sort', 'visible', 'keep_alive', 'is_external', 'created_at', 'updated_at'], 'integer'],
            [['title'], 'required'],
            [['title', 'name', 'icon', 'permission'], 'string', 'max' => 100],
            [['path', 'component', 'remark'], 'string', 'max' => 255],
            [['external_url'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'parent_id' => 'Parent ID',
            'type' => 'Type',
            'title' => 'Title',
            'name' => 'Name',
            'path' => 'Path',
            'component' => 'Component',
            'icon' => 'Icon',
            'permission' => 'Permission',
            'sort' => 'Sort',
            'visible' => 'Visible',
            'keep_alive' => 'Keep Alive',
            'is_external' => 'Is External',
            'external_url' => 'External Url',
            'remark' => 'Remark',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

}
