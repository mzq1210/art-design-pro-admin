<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\AdProduct;
use common\models\ProductCategory;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ProductController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'product.view',
        'select-options' => 'product.view',
        'view' => 'product.view',
        'create' => 'product.create',
        'update' => 'product.update',
        'delete' => 'product.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $productName = trim((string)Yii::$app->request->post('product_name', ''));
        $productCode = trim((string)Yii::$app->request->post('product_code', ''));
        $categoryId = (int)Yii::$app->request->post('category_id', 0);
        $mediaName = trim((string)Yii::$app->request->post('media_name', ''));
        $adType = trim((string)Yii::$app->request->post('ad_type', ''));
        $status = (int)Yii::$app->request->post('status', 0);
        $isHot = Yii::$app->request->post('is_hot', null);

        $query = AdProduct::find()
            ->alias('p')
            ->select([
                'p.*',
                'category_name' => new Expression("COALESCE(pc.category_name, '')"),
                'category_code' => new Expression("COALESCE(pc.category_code, '')"),
            ])
            ->leftJoin(['pc' => ProductCategory::tableName()], 'pc.id = p.category_id AND pc.deleted = 0')
            ->where(['p.deleted' => 0]);

        if ($productName !== '') {
            $query->andWhere(['like', 'p.product_name', $productName]);
        }

        if ($productCode !== '') {
            $query->andWhere(['like', 'p.product_code', $productCode]);
        }

        if ($categoryId > 0) {
            $query->andWhere(['p.category_id' => $categoryId]);
        }

        if ($mediaName !== '') {
            $query->andWhere(['like', 'p.media_name', $mediaName]);
        }

        if ($adType !== '') {
            $query->andWhere(['p.ad_type' => $adType]);
        }

        if ($status > 0) {
            $query->andWhere(['p.status' => $status]);
        }

        if ($isHot !== null && $isHot !== '') {
            $query->andWhere(['p.is_hot' => (int)$isHot]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['p.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeProductArray'], $records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'size' => $size,
        ];
    }

    public function actionSelectOptions(): array
    {
        $categories = ProductCategory::find()
            ->select(['id', 'parent_id', 'category_name', 'category_code'])
            ->where(['deleted' => 0, 'status' => 1])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        return [
            'categories' => array_map(static fn (array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'parent_id' => (int)($row['parent_id'] ?? 0),
                'category_name' => (string)($row['category_name'] ?? ''),
                'category_code' => (string)($row['category_code'] ?? ''),
            ], $categories),
        ];
    }

    public function actionView(): array
    {
        return $this->findProduct((int)Yii::$app->request->post('id', 0))->toArray();
    }

    public function actionCreate(): array
    {
        $model = new AdProduct();
        $this->loadProduct($model, true);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function actionUpdate(): array
    {
        $model = $this->findProduct((int)Yii::$app->request->post('id', 0));
        $this->loadProduct($model, false);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function actionDelete(): array
    {
        $model = $this->findProduct((int)Yii::$app->request->post('id', 0));
        $model->markDeleted();

        if (!$model->save(false)) {
            throw new BadRequestHttpException('删除产品失败');
        }

        return [
            'deleted' => true,
            'id' => (int)$model->id,
        ];
    }

    private function loadProduct(AdProduct $model, bool $isCreate): void
    {
        $post = Yii::$app->request->post();
        $productCode = trim((string)($post['product_code'] ?? ''));

        $model->category_id = (int)($post['category_id'] ?? 0);
        $model->product_name = trim((string)($post['product_name'] ?? ''));
        if ($isCreate) {
            $model->product_code = $productCode !== '' ? $productCode : $this->generateProductCode();
        }
        $model->media_name = trim((string)($post['media_name'] ?? ''));
        $model->ad_type = trim((string)($post['ad_type'] ?? ''));
        $model->unit = trim((string)($post['unit'] ?? ''));
        $model->list_price = (float)($post['list_price'] ?? 0);
        $model->base_price = (float)($post['base_price'] ?? 0);
        $model->sale_price = (float)($post['sale_price'] ?? 0);
        $model->inventory_total = max(0, (int)($post['inventory_total'] ?? 0));
        $model->delivery_cycle_days = max(0, (int)($post['delivery_cycle_days'] ?? 0));
        $model->status = (int)($post['status'] ?? 1);
        $model->is_hot = (int)($post['is_hot'] ?? 0);
        $model->cover_attachment_id = (int)($post['cover_attachment_id'] ?? 0);
        $model->specification = trim((string)($post['specification'] ?? ''));
        $model->remark = trim((string)($post['remark'] ?? ''));

        if ($model->product_name === '') {
            throw new BadRequestHttpException('产品名称不能为空');
        }

        if (!in_array($model->status, [1, 2], true)) {
            throw new BadRequestHttpException('产品状态不正确');
        }

        if (!in_array($model->is_hot, [0, 1], true)) {
            throw new BadRequestHttpException('热门状态不正确');
        }

        if ($model->category_id > 0) {
            $category = ProductCategory::findOne(['id' => $model->category_id, 'deleted' => 0]);
            if ($category === null) {
                throw new BadRequestHttpException('产品分类不存在');
            }
            if ((int)$category->status !== 1) {
                throw new BadRequestHttpException('产品分类已停用');
            }
        }

        if ($isCreate) {
            $exists = AdProduct::find()
                ->where(['product_code' => $model->product_code])
                ->andWhere(['deleted' => 0])
                ->exists();

            if ($exists) {
                throw new BadRequestHttpException('产品编码已存在');
            }
        }
    }

    private function findProduct(int $id): AdProduct
    {
        $model = AdProduct::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('产品不存在');
        }

        return $model;
    }

    private function serializeProductArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'category_id' => (int)($row['category_id'] ?? 0),
            'category_name' => (string)($row['category_name'] ?? ''),
            'category_code' => (string)($row['category_code'] ?? ''),
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
            'media_name' => (string)($row['media_name'] ?? ''),
            'ad_type' => (string)($row['ad_type'] ?? ''),
            'unit' => (string)($row['unit'] ?? ''),
            'list_price' => (string)($row['list_price'] ?? '0.00'),
            'base_price' => (string)($row['base_price'] ?? '0.00'),
            'sale_price' => (string)($row['sale_price'] ?? '0.00'),
            'inventory_total' => (int)($row['inventory_total'] ?? 0),
            'inventory_used' => (int)($row['inventory_used'] ?? 0),
            'inventory_available' => max(0, (int)($row['inventory_total'] ?? 0) - (int)($row['inventory_used'] ?? 0)),
            'delivery_cycle_days' => (int)($row['delivery_cycle_days'] ?? 0),
            'status' => (int)($row['status'] ?? 1),
            'is_hot' => (int)($row['is_hot'] ?? 0),
            'cover_attachment_id' => (int)($row['cover_attachment_id'] ?? 0),
            'specification' => (string)($row['specification'] ?? ''),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function generateProductCode(): string
    {
        $prefix = 'AD' . date('Ymd');

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!AdProduct::find()->where(['product_code' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstError(AdProduct $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存产品失败';
    }
}
