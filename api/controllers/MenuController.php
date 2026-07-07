<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Menu;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class MenuController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'menu.view',
        'tree' => 'menu.view',
        'create' => 'menu.create',
        'update' => 'menu.update',
        'delete' => 'menu.delete',
    ];

    public function actionIndex(): array
    {
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));
        $query = Menu::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);

        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'title', $keyword],
                ['like', 'name', $keyword],
                ['like', 'path', $keyword],
                ['like', 'permission', $keyword],
            ]);
        }

        $records = array_map([$this, 'serializeMenu'], $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function actionTree(): array
    {
        $records = Menu::find()
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return [
            'records' => $this->buildTree(array_map([$this, 'serializeMenu'], $records)),
        ];
    }

    public function actionCreate(): array
    {
        $model = new Menu();
        $this->loadMenu($model);

        $now = time();
        $model->created_at = $now;
        $model->updated_at = $now;

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        $this->syncPermission($model);
        $this->invalidateMenuCache();

        return $this->serializeMenu($model);
    }

    public function actionUpdate(): array
    {
        $id = (int)Yii::$app->request->post('id', 0);
        $model = Menu::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Menu does not exist.');
        }

        $this->loadMenu($model);
        $model->updated_at = time();

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        $this->syncPermission($model);
        $this->invalidateMenuCache();

        return $this->serializeMenu($model);
    }

    public function actionDelete(): array
    {
        $id = (int)Yii::$app->request->post('id', 0);
        $model = Menu::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Menu does not exist.');
        }

        if (Menu::find()->where(['parent_id' => $model->id])->exists()) {
            throw new BadRequestHttpException('Please delete child menus first.');
        }

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete menu.');
        }

        $this->invalidateMenuCache();

        return [
            'deleted' => true,
            'id' => $id,
        ];
    }

    private function loadMenu(Menu $model): void
    {
        $post = Yii::$app->request->post();
        $model->load($post, '');

        $model->parent_id = (int)($post['parent_id'] ?? 0);
        $model->type = (int)($post['type'] ?? 2);
        $model->sort = (int)($post['sort'] ?? 0);
        $model->visible = (int)($post['visible'] ?? 1);
        $model->keep_alive = (int)($post['keep_alive'] ?? 1);
        $model->is_external = (int)($post['is_external'] ?? 0);

        if ($model->parent_id > 0 && Menu::findOne($model->parent_id) === null) {
            throw new BadRequestHttpException('Parent menu does not exist.');
        }

        if (!$model->isNewRecord && $model->parent_id === (int)$model->id) {
            throw new BadRequestHttpException('Parent menu cannot be itself.');
        }

        if (!in_array($model->type, [1, 2, 3], true)) {
            throw new BadRequestHttpException('Invalid menu type.');
        }
    }

    private function serializeMenu(Menu $menu): array
    {
        return [
            'id' => (int)$menu->id,
            'parent_id' => (int)$menu->parent_id,
            'type' => (int)$menu->type,
            'title' => $menu->title,
            'name' => $menu->name,
            'path' => $menu->path,
            'component' => $menu->component,
            'icon' => $menu->icon,
            'permission' => $menu->permission,
            'sort' => (int)$menu->sort,
            'visible' => (int)$menu->visible,
            'keep_alive' => (int)$menu->keep_alive,
            'is_external' => (int)$menu->is_external,
            'external_url' => $menu->external_url,
            'remark' => $menu->remark,
            'created_at' => $menu->created_at === null ? null : (int)$menu->created_at,
            'updated_at' => $menu->updated_at === null ? null : (int)$menu->updated_at,
        ];
    }

    private function buildTree(array $records, int $parentId = 0): array
    {
        $tree = [];
        foreach ($records as $record) {
            if ((int)$record['parent_id'] !== $parentId) {
                continue;
            }

            $children = $this->buildTree($records, (int)$record['id']);
            if ($children !== []) {
                $record['children'] = $children;
            }
            $tree[] = $record;
        }

        return $tree;
    }

    private function firstError(Menu $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: 'Invalid menu data.';
    }

    private function syncPermission(Menu $menu): void
    {
        $name = trim((string)$menu->permission);
        if ($name === '') {
            return;
        }

        $auth = Yii::$app->authManager;
        $permission = $auth->getPermission($name);

        if ($permission === null) {
            $permission = $auth->createPermission($name);
            $permission->description = trim((string)$menu->title);

            if (!$auth->add($permission)) {
                throw new BadRequestHttpException("Failed to create permission: {$name}.");
            }

            return;
        }

        if ((string)$permission->description === '' && trim((string)$menu->title) !== '') {
            $permission->description = trim((string)$menu->title);

            if (!$auth->update($name, $permission)) {
                throw new BadRequestHttpException("Failed to update permission: {$name}.");
            }
        }
    }
}
