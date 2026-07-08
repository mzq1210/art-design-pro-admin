<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\AdProduct;
use common\models\ProductCategory;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ProductCategoryController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'product.category.view',
        'tree' => 'product.category.view',
        'select-options' => 'product.category.view',
        'view' => 'product.category.view',
        'create' => 'product.category.create',
        'update' => 'product.category.update',
        'delete' => 'product.category.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $categoryName = trim((string)Yii::$app->request->post('category_name', ''));
        $categoryCode = trim((string)Yii::$app->request->post('category_code', ''));
        $status = (int)Yii::$app->request->post('status', 0);
        $parentId = (int)Yii::$app->request->post('parent_id', 0);

        $query = ProductCategory::find()
            ->alias('pc')
            ->select([
                'pc.*',
                'product_count' => 'COUNT(p.id)',
            ])
            ->leftJoin(['p' => AdProduct::tableName()], 'p.category_id = pc.id AND p.deleted = 0')
            ->where(['pc.deleted' => 0])
            ->groupBy(['pc.id']);

        if ($categoryName !== '') {
            $query->andWhere(['like', 'pc.category_name', $categoryName]);
        }

        if ($categoryCode !== '') {
            $query->andWhere(['like', 'pc.category_code', $categoryCode]);
        }

        if ($status > 0) {
            $query->andWhere(['pc.status' => $status]);
        }

        if ($parentId > 0) {
            $query->andWhere(['pc.parent_id' => $parentId]);
        }

        $total = (int)(clone $query)->count();
        $records = $query
            ->orderBy(['pc.sort' => SORT_ASC, 'pc.id' => SORT_ASC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeCategoryArray'], $records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'size' => $size,
        ];
    }

    public function actionTree(): array
    {
        return $this->buildTree($this->getCategoryRows());
    }

    public function actionSelectOptions(): array
    {
        return [
            'categories' => $this->getCategoryRows(true),
            'tree' => $this->buildTree($this->getCategoryRows()),
        ];
    }

    public function actionView(): array
    {
        return $this->serializeCategory($this->findCategory((int)Yii::$app->request->post('id', 0)));
    }

    public function actionCreate(): array
    {
        $model = new ProductCategory();
        $this->loadCategory($model, true);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeCategory($model);
    }

    public function actionUpdate(): array
    {
        $model = $this->findCategory((int)Yii::$app->request->post('id', 0));
        $this->loadCategory($model, false);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeCategory($model);
    }

    public function actionDelete(): array
    {
        $model = $this->findCategory((int)Yii::$app->request->post('id', 0));

        if (ProductCategory::find()->where(['parent_id' => $model->id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('当前分类下存在子分类，不能删除');
        }

        if (AdProduct::find()->where(['category_id' => $model->id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('当前分类下存在产品，不能删除');
        }

        $model->markDeleted();

        if (!$model->save(false)) {
            throw new BadRequestHttpException('删除产品分类失败');
        }

        return [
            'deleted' => true,
            'id' => (int)$model->id,
        ];
    }

    private function loadCategory(ProductCategory $model, bool $isCreate): void
    {
        $post = Yii::$app->request->post();
        $categoryCode = trim((string)($post['category_code'] ?? ''));

        $model->parent_id = (int)($post['parent_id'] ?? 0);
        $model->category_name = trim((string)($post['category_name'] ?? ''));
        $model->category_code = $categoryCode !== '' ? $categoryCode : ($model->category_code ?: $this->generateCategoryCode());
        $model->sort = (int)($post['sort'] ?? 0);
        $model->status = (int)($post['status'] ?? 1);
        $model->remark = trim((string)($post['remark'] ?? ''));

        if ($model->category_name === '') {
            throw new BadRequestHttpException('分类名称不能为空');
        }

        if (!in_array($model->status, [1, 2], true)) {
            throw new BadRequestHttpException('分类状态不正确');
        }

        if ((int)$model->parent_id === (int)$model->id && (int)$model->id > 0) {
            throw new BadRequestHttpException('上级分类不能选择自己');
        }

        if ($model->parent_id > 0) {
            $parent = ProductCategory::findOne(['id' => $model->parent_id, 'deleted' => 0]);
            if ($parent === null) {
                throw new BadRequestHttpException('上级分类不存在');
            }
            if ((int)$parent->status !== 1) {
                throw new BadRequestHttpException('上级分类已停用');
            }
            $this->guardCategoryLoop((int)$model->parent_id, (int)$model->id);
        }

        if (!$isCreate && (int)$model->status !== 1) {
            $this->guardDisableCategory($model);
        }

        $exists = ProductCategory::find()
            ->where(['category_code' => $model->category_code])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$model->id])
            ->exists();

        if ($exists) {
            throw new BadRequestHttpException('分类编码已存在');
        }
    }

    private function guardCategoryLoop(int $parentId, int $selfId): void
    {
        while ($selfId > 0 && $parentId > 0) {
            if ($parentId === $selfId) {
                throw new BadRequestHttpException('分类层级不能形成循环');
            }

            $parentId = (int)(ProductCategory::find()
                ->select('parent_id')
                ->where(['id' => $parentId, 'deleted' => 0])
                ->scalar() ?: 0);
        }
    }

    private function guardDisableCategory(ProductCategory $model): void
    {
        if (ProductCategory::find()->where(['parent_id' => $model->id, 'status' => 1, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('当前分类下存在启用中的子分类，不能停用');
        }

        if (AdProduct::find()->where(['category_id' => $model->id, 'status' => 1, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('当前分类下存在启用中的产品，不能停用');
        }
    }

    private function findCategory(int $id): ProductCategory
    {
        $model = ProductCategory::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('产品分类不存在');
        }

        return $model;
    }

    private function getCategoryRows(bool $enabledOnly = false): array
    {
        $query = ProductCategory::find()
            ->select(['id', 'parent_id', 'category_name', 'category_code', 'sort', 'status'])
            ->where(['deleted' => 0]);

        if ($enabledOnly) {
            $query->andWhere(['status' => 1]);
        }

        $rows = $query->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])->asArray()->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)($row['id'] ?? 0),
            'parent_id' => (int)($row['parent_id'] ?? 0),
            'category_name' => (string)($row['category_name'] ?? ''),
            'category_code' => (string)($row['category_code'] ?? ''),
            'sort' => (int)($row['sort'] ?? 0),
            'status' => (int)($row['status'] ?? 1),
        ], $rows);
    }

    private function buildTree(array $rows): array
    {
        $nodes = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $nodes[(int)$row['id']] = $row;
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = (int)$node['parent_id'];
            if ($parentId > 0 && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
                continue;
            }

            $tree[] = &$node;
        }
        unset($node);

        return array_values($tree);
    }

    private function serializeCategory(ProductCategory $model): array
    {
        return $this->serializeCategoryArray($model->toArray());
    }

    private function serializeCategoryArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'parent_id' => (int)($row['parent_id'] ?? 0),
            'category_name' => (string)($row['category_name'] ?? ''),
            'category_code' => (string)($row['category_code'] ?? ''),
            'sort' => (int)($row['sort'] ?? 0),
            'status' => (int)($row['status'] ?? 1),
            'remark' => (string)($row['remark'] ?? ''),
            'product_count' => (int)($row['product_count'] ?? 0),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function generateCategoryCode(): string
    {
        $prefix = 'PC' . date('Ymd');

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!ProductCategory::find()->where(['category_code' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstError(ProductCategory $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存产品分类失败';
    }
}
