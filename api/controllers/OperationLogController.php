<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\OperationLog;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class OperationLogController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'operation-log.view',
        'delete' => 'operation-log.delete',
        'clear' => 'operation-log.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 15));
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));
        $status = Yii::$app->request->post('status', '');
        $route = trim((string)Yii::$app->request->post('route', ''));

        $query = OperationLog::find();
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'username', $keyword],
                ['like', 'ip', $keyword],
                ['like', 'message', $keyword],
            ]);
        }

        if ($status !== '' && $status !== null) {
            $query->andWhere(['status' => (int)$status]);
        }

        if ($route !== '') {
            $query->andWhere(['like', 'route', $route]);
        }

        $total = (int)(clone $query)->count();
        $records = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->all();

        return [
            'records' => array_map(static fn(OperationLog $model): array => $model->toArray(), $records),
            'current' => $page,
            'size' => $size,
            'total' => $total,
        ];
    }

    public function actionDelete(): array
    {
        $model = $this->findLog((int)Yii::$app->request->post('id', 0));
        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete operation log.');
        }

        return ['deleted' => true];
    }

    public function actionClear(): array
    {
        $days = max(1, (int)Yii::$app->request->post('days', 30));
        $timestamp = time() - $days * 86400;
        $count = OperationLog::deleteAll(['<', 'created_at', $timestamp]);

        return ['deleted' => $count];
    }

    private function findLog(int $id): OperationLog
    {
        $model = OperationLog::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Operation log does not exist.');
        }

        return $model;
    }
}
