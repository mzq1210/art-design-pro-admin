<?php

declare(strict_types=1);

namespace api\controllers;

use Yii;
use yii\rbac\Item;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class RoleController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'role.view',
        'create' => 'role.create',
        'update' => 'role.update',
        'delete' => 'role.delete',
        'permissions' => 'role.permission.view',
        'assign-permissions' => 'role.permission.assign',
    ];

    public function actionIndex(): array
    {
        $roles = Yii::$app->authManager->getRoles();

        $list = [];
        foreach ($roles as $role) {
            $list[] = [
                'name'        => $role->name,
                'description' => $role->description,
                'created_at'  => $role->createdAt,
                'updated_at'  => $role->updatedAt,
            ];
        }

        return [
            'records' => $list,
            'total'   => count($list),
            'page'    => 1,
            'size'    => count($list),
        ];
    }

    public function actionCreate(): array
    {
        $name        = trim((string)Yii::$app->request->post('name', ''));
        $description = trim((string)Yii::$app->request->post('description', ''));

        if ($name === '') {
            throw new BadRequestHttpException('Role name is required.');
        }

        if (Yii::$app->authManager->getRole($name) !== null) {
            throw new BadRequestHttpException('Role already exists.');
        }

        if (Yii::$app->authManager->getPermission($name) !== null) {
            throw new BadRequestHttpException('A permission with the same name already exists.');
        }

        $role              = Yii::$app->authManager->createRole($name);
        $role->description = $description;

        if (!Yii::$app->authManager->add($role)) {
            throw new BadRequestHttpException('Failed to create role.');
        }

        return $this->serializeRole($role);
    }

    public function actionUpdate(): array
    {
        $name        = trim((string)Yii::$app->request->post('name', ''));
        $description = trim((string)Yii::$app->request->post('description', ''));

        if ($name === '') {
            throw new BadRequestHttpException('Role name is required.');
        }

        $role = Yii::$app->authManager->getRole($name);
        if ($role === null) {
            throw new NotFoundHttpException('Role does not exist.');
        }

        $role->description = $description;

        if (!Yii::$app->authManager->update($name, $role)) {
            throw new BadRequestHttpException('Failed to update role.');
        }

        return $this->serializeRole($role);
    }

    public function actionDelete(): array
    {
        $name = trim((string)Yii::$app->request->post('name', ''));

        if ($name === '') {
            throw new BadRequestHttpException('Role name is required.');
        }

        $role = Yii::$app->authManager->getRole($name);
        if ($role === null) {
            throw new NotFoundHttpException('Role does not exist.');
        }

        if (!Yii::$app->authManager->remove($role)) {
            throw new BadRequestHttpException('Failed to delete role.');
        }

        return [
            'deleted' => true,
        ];
    }

    public function actionPermissions(): array
    {
        $name = trim((string)Yii::$app->request->post('name', ''));
        if ($name === '') {
            throw new BadRequestHttpException('Role name is required.');
        }

        $role = Yii::$app->authManager->getRole($name);
        if ($role === null) {
            throw new NotFoundHttpException('Role does not exist.');
        }

        $permissions = [];
        foreach (Yii::$app->authManager->getChildren($name) as $child) {
            if ($child->type !== Item::TYPE_PERMISSION) {
                continue;
            }

            $permissions[] = $child->name;
        }

        return [
            'role'        => $name,
            'permissions' => $permissions,
        ];
    }

    public function actionAssignPermissions(): array
    {
        $name            = trim((string)Yii::$app->request->post('name', ''));
        $permissionNames = Yii::$app->request->post('permissions', []);

        if ($name === '') {
            throw new BadRequestHttpException('Role name is required.');
        }

        if (!is_array($permissionNames)) {
            throw new BadRequestHttpException('Permissions must be an array.');
        }

        $role = Yii::$app->authManager->getRole($name);
        if ($role === null) {
            throw new NotFoundHttpException('Role does not exist.');
        }

        $auth        = Yii::$app->authManager;
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($auth->getChildren($name) as $child) {
                if ($child->type === Item::TYPE_PERMISSION) {
                    $auth->removeChild($role, $child);
                }
            }

            foreach (array_unique($permissionNames) as $permissionName) {
                $permissionName = trim((string)$permissionName);
                if ($permissionName === '') {
                    continue;
                }

                $permission = $auth->getPermission($permissionName);
                if ($permission === null) {
                    throw new BadRequestHttpException("Permission {$permissionName} does not exist.");
                }

                if (!$auth->hasChild($role, $permission)) {
                    $auth->addChild($role, $permission);
                }
            }

            $transaction->commit();
            $this->invalidateMenuCache();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [
            'assigned' => true,
        ];
    }

    private function serializeRole(Item $role): array
    {
        return [
            'name'        => $role->name,
            'description' => $role->description,
            'created_at'  => $role->createdAt,
            'updated_at'  => $role->updatedAt,
        ];
    }
}
