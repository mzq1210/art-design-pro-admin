<?php

declare(strict_types=1);

namespace api\controllers;

use common\services\FileService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;

class FileController extends BaseController
{
    protected array $rbacPermissions = [
        'group-index' => 'file.view',
        'group-create' => 'file.group.create',
        'group-update' => 'file.group.update',
        'group-delete' => 'file.group.delete',
        'index' => 'file.view',
        'upload' => 'file.upload',
        'update' => 'file.update',
        'delete' => 'file.delete',
    ];

    private FileService $fileService;

    public function __construct($id, $module, FileService $fileService, $config = [])
    {
        $this->fileService = $fileService;
        parent::__construct($id, $module, $config);
    }

    public function actionGroupIndex(): array
    {
        return $this->fileService->groupIndex(trim((string)Yii::$app->request->post('keyword', '')));
    }

    public function actionGroupCreate(): array
    {
        return $this->fileService->createGroup(Yii::$app->request->post());
    }

    public function actionGroupUpdate(): array
    {
        return $this->fileService->updateGroup((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
    }

    public function actionGroupDelete(): array
    {
        return $this->fileService->deleteGroup((int)Yii::$app->request->post('id', 0));
    }

    public function actionIndex(): array
    {
        return $this->fileService->index(
            max(1, (int)Yii::$app->request->post('page', 1)),
            max(1, (int)Yii::$app->request->post('size', 12)),
            Yii::$app->request->post('group_id', ''),
            trim((string)Yii::$app->request->post('keyword', ''))
        );
    }

    public function actionUpload(): array
    {
        $file = UploadedFile::getInstanceByName('file');
        if ($file === null) {
            throw new BadRequestHttpException('No file uploaded.');
        }

        return $this->fileService->upload(
            $file,
            Yii::$app->request->post(),
            (int)Yii::$app->user->id,
            Yii::$app->request->hostInfo
        );
    }

    public function actionUpdate(): array
    {
        return $this->fileService->update((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
    }

    public function actionDelete(): array
    {
        return $this->fileService->delete((int)Yii::$app->request->post('id', 0));
    }
}
