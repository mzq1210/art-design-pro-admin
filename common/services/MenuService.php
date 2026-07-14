<?php

declare(strict_types=1);

namespace common\services;

use common\models\Menu;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class MenuService
{
    public function index(string $keyword): array
    {
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

        $records = array_map(static fn(Menu $menu): array => $menu->toArray(), $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function tree(): array
    {
        $records = Menu::find()
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return [
            'records' => $this->buildTree(array_map(static fn(Menu $menu): array => $menu->toArray(), $records)),
        ];
    }

    public function create(array $data): array
    {
        $model = new Menu();
        $this->loadMenu($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        $this->syncPermission($model);
        $this->invalidateMenuCache();

        return $model->toArray();
    }

    public function update(int $id, array $data): array
    {
        $model = $this->findMenu($id);
        $this->loadMenu($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        $this->syncPermission($model);
        $this->invalidateMenuCache();

        return $model->toArray();
    }

    public function delete(int $id): array
    {
        $model = $this->findMenu($id);

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

    private function loadMenu(Menu $model, array $data): void
    {
        $model->load($data, '');

        $model->parent_id = (int)($data['parent_id'] ?? 0);
        $model->type = (int)($data['type'] ?? 2);
        $model->sort = (int)($data['sort'] ?? 0);
        $model->visible = (int)($data['visible'] ?? 1);
        $model->keep_alive = (int)($data['keep_alive'] ?? 1);
        $model->is_external = (int)($data['is_external'] ?? 0);

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

    private function findMenu(int $id): Menu
    {
        $model = Menu::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Menu does not exist.');
        }

        return $model;
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

    private function invalidateMenuCache(): void
    {
        try {
            Yii::$app->menuCache->set('version', time());
        } catch (\Throwable) {
        }
    }
}
