<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\DictItem;
use common\models\DictType;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class DictController extends BaseController
{
    protected array $rbacPermissions = [
        'type-index' => 'dict.view',
        'type-create' => 'dict.create',
        'type-update' => 'dict.update',
        'type-delete' => 'dict.delete',
        'item-index' => 'dict.view',
        'item-create' => 'dict.create',
        'item-update' => 'dict.update',
        'item-delete' => 'dict.delete',
        'options' => 'dict.view',
    ];

    public function actionTypeIndex(): array
    {
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));
        $query = DictType::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_DESC]);

        if ($keyword !== '') {
            $query->andWhere(['or', ['like', 'name', $keyword], ['like', 'code', $keyword]]);
        }

        $records = array_map([$this, 'serializeType'], $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function actionTypeCreate(): array
    {
        $model = new DictType();
        $this->loadType($model);
        $model->created_at = time();
        $model->updated_at = time();

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeType($model);
    }

    public function actionTypeUpdate(): array
    {
        $model = $this->findType((int)Yii::$app->request->post('id', 0));
        $this->loadType($model);
        $model->updated_at = time();

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeType($model);
    }

    public function actionTypeDelete(): array
    {
        $model = $this->findType((int)Yii::$app->request->post('id', 0));

        if (DictItem::find()->where(['type_id' => $model->id])->exists()) {
            throw new BadRequestHttpException('Please delete dict items first.');
        }

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete dict type.');
        }

        return ['deleted' => true];
    }

    public function actionItemIndex(): array
    {
        $typeId = (int)Yii::$app->request->post('type_id', 0);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = DictItem::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);
        if ($typeId > 0) {
            $query->andWhere(['type_id' => $typeId]);
        }

        if ($keyword !== '') {
            $query->andWhere(['or', ['like', 'label', $keyword], ['like', 'value', $keyword]]);
        }

        $records = array_map([$this, 'serializeItem'], $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function actionItemCreate(): array
    {
        $model = new DictItem();
        $this->loadItem($model);
        $model->created_at = time();
        $model->updated_at = time();

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeItem($model);
    }

    public function actionItemUpdate(): array
    {
        $model = $this->findItem((int)Yii::$app->request->post('id', 0));
        $this->loadItem($model);
        $model->updated_at = time();

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeItem($model);
    }

    public function actionItemDelete(): array
    {
        $model = $this->findItem((int)Yii::$app->request->post('id', 0));

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete dict item.');
        }

        return ['deleted' => true];
    }

    public function actionOptions(): array
    {
        $code = trim((string)Yii::$app->request->post('code', ''));
        if ($code === '') {
            throw new BadRequestHttpException('Dict code is required.');
        }

        $type = DictType::findOne(['code' => $code, 'status' => 1]);
        if ($type === null) {
            return ['records' => []];
        }

        $items = DictItem::find()
            ->where(['type_id' => $type->id, 'status' => 1])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return [
            'records' => array_map([$this, 'serializeItem'], $items),
        ];
    }

    private function loadType(DictType $model): void
    {
        $model->name = trim((string)Yii::$app->request->post('name', ''));
        $model->code = trim((string)Yii::$app->request->post('code', ''));
        $model->status = (int)Yii::$app->request->post('status', 1);
        $model->sort = (int)Yii::$app->request->post('sort', 0);
        $model->remark = trim((string)Yii::$app->request->post('remark', ''));
    }

    private function loadItem(DictItem $model): void
    {
        $model->type_id = (int)Yii::$app->request->post('type_id', 0);
        $model->label = trim((string)Yii::$app->request->post('label', ''));
        $model->value = trim((string)Yii::$app->request->post('value', ''));
        $model->status = (int)Yii::$app->request->post('status', 1);
        $model->sort = (int)Yii::$app->request->post('sort', 0);
        $model->remark = trim((string)Yii::$app->request->post('remark', ''));
    }

    private function findType(int $id): DictType
    {
        $model = DictType::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Dict type does not exist.');
        }

        return $model;
    }

    private function findItem(int $id): DictItem
    {
        $model = DictItem::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Dict item does not exist.');
        }

        return $model;
    }

    private function serializeType(DictType $model): array
    {
        return [
            'id' => (int)$model->id,
            'name' => $model->name,
            'code' => $model->code,
            'status' => (int)$model->status,
            'sort' => (int)$model->sort,
            'remark' => $model->remark ?: '',
            'created_at' => (int)$model->created_at,
            'updated_at' => (int)$model->updated_at,
        ];
    }

    private function serializeItem(DictItem $model): array
    {
        return [
            'id' => (int)$model->id,
            'type_id' => (int)$model->type_id,
            'label' => $model->label,
            'value' => $model->value,
            'status' => (int)$model->status,
            'sort' => (int)$model->sort,
            'remark' => $model->remark ?: '',
            'created_at' => (int)$model->created_at,
            'updated_at' => (int)$model->updated_at,
        ];
    }

    private function firstError(DictType|DictItem $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: 'Invalid dict data.';
    }
}
