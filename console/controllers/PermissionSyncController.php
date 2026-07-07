<?php

declare(strict_types=1);

namespace console\controllers;

use common\models\Menu;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;

class PermissionSyncController extends Controller
{
    public function actionIndex(string $roleName = ''): int
    {
        $auth = Yii::$app->authManager;
        $permissions = $this->getMenuPermissionMap();
        $created = 0;
        $assigned = 0;
        $role = $roleName === '' ? null : $auth->getRole($roleName);

        foreach ($permissions as $name => $title) {
            $permission = $auth->getPermission($name);
            if ($permission === null) {
                $permission = $auth->createPermission($name);
                $permission->description = $title;
                $auth->add($permission);
                $created++;
            }

            if ($role !== null && !$auth->hasChild($role, $permission)) {
                $auth->addChild($role, $permission);
                $assigned++;
            }
        }

        $this->stdout("Menu permissions: " . count($permissions) . "\n", Console::FG_GREEN);
        $this->stdout("Created permissions: {$created}\n", Console::FG_GREEN);

        if ($roleName !== '') {
            if ($role === null) {
                $this->stderr("Role does not exist: {$roleName}\n", Console::FG_RED);
                return self::EXIT_CODE_ERROR;
            }

            $this->stdout("Assigned to {$roleName}: {$assigned}\n", Console::FG_GREEN);
        }

        $this->invalidateMenuCache();

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * @return array<string, string>
     */
    private function getMenuPermissionMap(): array
    {
        $menus = Menu::find()
            ->select(['permission', 'title'])
            ->where(['not', ['permission' => null]])
            ->andWhere(['<>', 'permission', ''])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        $permissions = [];
        foreach ($menus as $menu) {
            $name = trim((string)$menu['permission']);
            if ($name === '' || isset($permissions[$name])) {
                continue;
            }

            $permissions[$name] = trim((string)$menu['title']);
        }

        return $permissions;
    }

    private function invalidateMenuCache(): void
    {
        try {
            Yii::$app->menuCache->set('version', time());
        } catch (\Throwable) {
        }
    }
}
