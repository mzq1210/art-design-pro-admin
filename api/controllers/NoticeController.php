<?php

declare(strict_types=1);

namespace api\controllers;

use common\services\NoticeService;
use Yii;

class NoticeController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'notice.view',
        'view' => 'notice.view',
        'create' => 'notice.create',
        'update' => 'notice.update',
        'delete' => 'notice.delete',
    ];

    private NoticeService $noticeService;

    public function __construct($id, $module, NoticeService $noticeService, $config = [])
    {
        $this->noticeService = $noticeService;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): array
    {
        return $this->noticeService->index(
            max(1, (int)Yii::$app->request->post('page', 1)),
            max(1, (int)Yii::$app->request->post('size', 10)),
            (int)Yii::$app->request->post('status', -1)
        );
    }

    public function actionCreate(): array
    {
        return $this->noticeService->create(Yii::$app->request->post());
    }

    public function actionUpdate(): array
    {
        return $this->noticeService->update((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
    }

    public function actionDelete(): array
    {
        return $this->noticeService->delete((int)Yii::$app->request->post('id', 0));
    }

    public function actionView(): array
    {
        return $this->noticeService->view((int)Yii::$app->request->post('id', 0));
    }
}
