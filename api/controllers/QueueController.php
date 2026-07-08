<?php

declare(strict_types=1);

namespace api\controllers;

use common\services\QueueTaskService;
use Yii;

class QueueController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'queue.view',
        'create-demo' => 'queue.create',
        'retry' => 'queue.create',
        'delete' => 'queue.delete',
    ];

    private QueueTaskService $queueTaskService;

    public function __construct($id, $module, QueueTaskService $queueTaskService, $config = [])
    {
        $this->queueTaskService = $queueTaskService;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): array
    {
        return $this->queueTaskService->index(
            max(1, (int)Yii::$app->request->post('page', 1)),
            max(1, (int)Yii::$app->request->post('size', 15)),
            trim((string)Yii::$app->request->post('keyword', '')),
            Yii::$app->request->post('status', '')
        );
    }

    public function actionCreateDemo(): array
    {
        return $this->queueTaskService->createDemo(
            trim((string)Yii::$app->request->post('name', 'Demo queue task')),
            max(1, min(10, (int)Yii::$app->request->post('seconds', 3))),
            (int)Yii::$app->user->id
        );
    }

    public function actionRetry(): array
    {
        return $this->queueTaskService->retry((int)Yii::$app->request->post('id', 0));
    }

    public function actionDelete(): array
    {
        return $this->queueTaskService->delete((int)Yii::$app->request->post('id', 0));
    }
}
