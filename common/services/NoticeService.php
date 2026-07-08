<?php

declare(strict_types=1);

namespace common\services;

use common\models\Notice;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class NoticeService
{
    public function index(int $page, int $size, int $status): array
    {
        $query = Notice::find();
        if ($status !== -1) {
            $query->andWhere(['status' => $status]);
        }

        $total = (int)(clone $query)->count();
        $records = $query
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        return [
            'records' => $records,
            'current' => $page,
            'size' => $size,
            'total' => $total,
        ];
    }

    public function create(array $data): array
    {
        $model = new Notice();
        $model->load($data, '');

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return [
            'model' => $model,
        ];
    }

    public function update(int $id, array $data): array
    {
        $model = $this->findNotice($id);
        $model->load($data, '');

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return [
            'model' => $model,
        ];
    }

    public function delete(int $id): array
    {
        $model = $this->findNotice($id);
        $model->updateAttributes(['status' => 0]);

        return [
            'id' => (int)$model->id,
            'deleted' => true,
        ];
    }

    public function view(int $id): array
    {
        return [
            'model' => $this->findNotice($id),
        ];
    }

    private function findNotice(int $id): Notice
    {
        $model = Notice::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Notice does not exist.');
        }

        return $model;
    }

    private function firstError(Notice $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: 'Invalid notice data.';
    }
}
