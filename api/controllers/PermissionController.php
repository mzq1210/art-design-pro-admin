<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Menu;
use Yii;
use yii\rbac\Item;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class PermissionController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'permission.view',
        'create' => 'permission.create',
        'update' => 'permission.update',
        'delete' => 'permission.delete',
        'diagnose' => 'permission.view',
        'sync-from-menu' => 'permission.create',
    ];

    public function actionIndex(): array
    {
        $keyword = trim((string) Yii::$app->request->post('keyword', ''));
        $permissions = Yii::$app->authManager->getPermissions();

        $list = [];
        foreach ($permissions as $permission) {
            if ($keyword !== ''
                && !str_contains($permission->name, $keyword)
                && !str_contains((string) $permission->description, $keyword)
            ) {
                continue;
            }

            $list[] = $this->serializePermission($permission);
        }

        return [
            'records' => $list,
            'total' => count($list),
            'page' => 1,
            'size' => count($list),
        ];
    }

    public function actionCreate(): array
    {
        $name = trim((string) Yii::$app->request->post('name', ''));
        $description = trim((string) Yii::$app->request->post('description', ''));
        $ruleName = trim((string) Yii::$app->request->post('rule_name', ''));

        if ($name === '') {
            throw new BadRequestHttpException('Permission name is required.');
        }

        if (Yii::$app->authManager->getPermission($name) !== null) {
            throw new BadRequestHttpException('Permission already exists.');
        }

        if (Yii::$app->authManager->getRole($name) !== null) {
            throw new BadRequestHttpException('A role with the same name already exists.');
        }

        if ($ruleName !== '' && Yii::$app->authManager->getRule($ruleName) === null) {
            throw new BadRequestHttpException('Rule does not exist.');
        }

        $permission = Yii::$app->authManager->createPermission($name);
        $permission->description = $description;
        $permission->ruleName = $ruleName === '' ? null : $ruleName;

        if (!Yii::$app->authManager->add($permission)) {
            throw new BadRequestHttpException('Failed to create permission.');
        }

        $this->invalidateMenuCache();

        return $this->serializePermission($permission);
    }

    public function actionUpdate(): array
    {
        $name = trim((string) Yii::$app->request->post('name', ''));
        $description = trim((string) Yii::$app->request->post('description', ''));
        $ruleName = trim((string) Yii::$app->request->post('rule_name', ''));

        if ($name === '') {
            throw new BadRequestHttpException('Permission name is required.');
        }

        $permission = Yii::$app->authManager->getPermission($name);
        if ($permission === null) {
            throw new NotFoundHttpException('Permission does not exist.');
        }

        if ($ruleName !== '' && Yii::$app->authManager->getRule($ruleName) === null) {
            throw new BadRequestHttpException('Rule does not exist.');
        }

        $permission->description = $description;
        $permission->ruleName = $ruleName === '' ? null : $ruleName;

        if (!Yii::$app->authManager->update($name, $permission)) {
            throw new BadRequestHttpException('Failed to update permission.');
        }

        $this->invalidateMenuCache();

        return $this->serializePermission($permission);
    }

    public function actionDelete(): array
    {
        $name = trim((string) Yii::$app->request->post('name', ''));
        if ($name === '') {
            throw new BadRequestHttpException('Permission name is required.');
        }

        $permission = Yii::$app->authManager->getPermission($name);
        if ($permission === null) {
            throw new NotFoundHttpException('Permission does not exist.');
        }

        if (!Yii::$app->authManager->remove($permission)) {
            throw new BadRequestHttpException('Failed to delete permission.');
        }

        $this->invalidateMenuCache();

        return [
            'deleted' => true,
        ];
    }

    public function actionDiagnose(): array
    {
        $menuPermissions = $this->getMenuPermissionMap();
        $rbacPermissions = Yii::$app->authManager->getPermissions();

        $missing = [];
        foreach ($menuPermissions as $name => $title) {
            if (!isset($rbacPermissions[$name])) {
                $missing[] = [
                    'name' => $name,
                    'description' => $title,
                ];
            }
        }

        $orphan = [];
        foreach ($rbacPermissions as $name => $permission) {
            if (!isset($menuPermissions[$name])) {
                $orphan[] = $this->serializePermission($permission);
            }
        }

        return [
            'missing' => $missing,
            'orphan' => $orphan,
            'menu_total' => count($menuPermissions),
            'rbac_total' => count($rbacPermissions),
        ];
    }

    public function actionSyncFromMenu(): array
    {
        $auth = Yii::$app->authManager;
        $menuPermissions = $this->getMenuPermissionMap();

        $created = [];
        $updated = [];
        $existed = [];

        foreach ($menuPermissions as $name => $title) {
            $permission = $auth->getPermission($name);

            if ($permission === null) {
                $permission = $auth->createPermission($name);
                $permission->description = $title;

                if (!$auth->add($permission)) {
                    throw new BadRequestHttpException("Failed to create permission: {$name}.");
                }

                $created[] = $this->serializePermission($permission);
                $this->invalidateMenuCache();
                continue;
            }

            if ((string)$permission->description === '' && $title !== '') {
                $permission->description = $title;

                if (!$auth->update($name, $permission)) {
                    throw new BadRequestHttpException("Failed to update permission: {$name}.");
                }

                $updated[] = $this->serializePermission($permission);
                $this->invalidateMenuCache();
                continue;
            }

            $existed[] = $this->serializePermission($permission);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'existed_count' => count($existed),
            'menu_total' => count($menuPermissions),
        ];
    }

    private function serializePermission(Item $permission): array
    {
        return [
            'name' => $permission->name,
            'description' => $permission->description,
            'rule_name' => $permission->ruleName,
            'created_at' => $permission->createdAt,
            'updated_at' => $permission->updatedAt,
        ];
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
}
