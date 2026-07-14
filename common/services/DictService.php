<?php

declare(strict_types=1);

namespace common\services;

use common\models\DictItem;
use common\models\DictType;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class DictService
{
    public function typeIndex(string $keyword): array
    {
        $query = DictType::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_DESC]);

        if ($keyword !== '') {
            $query->andWhere(['or', ['like', 'name', $keyword], ['like', 'code', $keyword]]);
        }

        $records = array_map(static fn(DictType $model): array => $model->toArray(), $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function createType(array $data): array
    {
        $model = new DictType();
        $this->loadType($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function updateType(int $id, array $data): array
    {
        $model = $this->findType($id);
        $this->loadType($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function deleteType(int $id): array
    {
        $model = $this->findType($id);

        if (DictItem::find()->where(['type_id' => $model->id])->exists()) {
            throw new BadRequestHttpException('Please delete dict items first.');
        }

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete dict type.');
        }

        return ['deleted' => true];
    }

    public function itemIndex(int $typeId, string $keyword): array
    {
        $query = DictItem::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);
        if ($typeId > 0) {
            $query->andWhere(['type_id' => $typeId]);
        }

        if ($keyword !== '') {
            $query->andWhere(['or', ['like', 'label', $keyword], ['like', 'value', $keyword]]);
        }

        $records = array_map(static fn(DictItem $model): array => $model->toArray(), $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function createItem(array $data): array
    {
        $model = new DictItem();
        $this->loadItem($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function updateItem(int $id, array $data): array
    {
        $model = $this->findItem($id);
        $this->loadItem($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function deleteItem(int $id): array
    {
        $model = $this->findItem($id);

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete dict item.');
        }

        return ['deleted' => true];
    }

    public function selectOptions(string $code): array
    {
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
            'records' => array_map(static fn(DictItem $model): array => $model->toArray(), $items),
        ];
    }

    private function loadType(DictType $model, array $data): void
    {
        $model->name = trim((string)($data['name'] ?? ''));
        $model->code = trim((string)($data['code'] ?? ''));
        $model->status = (int)($data['status'] ?? 1);
        $model->sort = (int)($data['sort'] ?? 0);
        $model->remark = trim((string)($data['remark'] ?? ''));
    }

    private function loadItem(DictItem $model, array $data): void
    {
        $model->type_id = (int)($data['type_id'] ?? 0);
        $model->label = trim((string)($data['label'] ?? ''));
        $model->value = trim((string)($data['value'] ?? ''));
        $model->status = (int)($data['status'] ?? 1);
        $model->sort = (int)($data['sort'] ?? 0);
        $model->remark = trim((string)($data['remark'] ?? ''));
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

    private function firstError(DictType|DictItem $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: 'Invalid dict data.';
    }
}
