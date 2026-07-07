<?php

namespace console\controllers;

use yii\console\Controller;

class TestController extends Controller
{

    public $enableCsrfValidation = false;

    public function actionIndex()
    {
        $auth = \Yii::$app->authManager;

        $admin = $auth->createRole('admin');
        $admin->description = '管理员';
        $auth->add($admin);

        $permissions = [
            'notice.view' => '查看公告',
            'notice.create' => '新增公告',
            'notice.update' => '修改公告',
            'notice.delete' => '删除公告',
        ];

        foreach ($permissions as $name => $description) {
            $permission = $auth->createPermission($name);
            $permission->description = $description;
            $auth->add($permission);
            $auth->addChild($admin, $permission);
        }

        $auth->assign($admin, 1);
    }

}




