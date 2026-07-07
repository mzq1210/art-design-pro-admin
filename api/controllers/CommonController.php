<?php

declare(strict_types=1);

namespace api\controllers;

use Yii;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;

class CommonController extends BaseController
{
    protected array $authOnlyActions = ['upload'];

    private const MAX_FILE_SIZE = 2 * 1024 * 1024;
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function actionUpload(): array
    {
        $file = UploadedFile::getInstanceByName('file');
        if ($file === null) {
            throw new BadRequestHttpException('No file uploaded.');
        }

        if ($file->size > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException('File size cannot exceed 2MB.');
        }

        $extension = strtolower((string)$file->extension);
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException('Only image files are allowed.');
        }

        $imageInfo = @getimagesize($file->tempName);
        if ($imageInfo === false || !in_array((string)$imageInfo['mime'], self::ALLOWED_MIME_TYPES, true)) {
            throw new BadRequestHttpException('Invalid image type.');
        }

        $scene = $this->normalizeScene((string)Yii::$app->request->post('scene', 'common'));
        $datePath = date('Ym');
        $relativeDir = '/uploads/' . $scene . '/' . $datePath;
        $saveDir = Yii::getAlias('@api/web') . $relativeDir;

        FileHelper::createDirectory($saveDir);

        $fileName = Yii::$app->security->generateRandomString(24) . '.' . $extension;
        $savePath = $saveDir . DIRECTORY_SEPARATOR . $fileName;

        if (!$file->saveAs($savePath)) {
            throw new BadRequestHttpException('Failed to save uploaded file.');
        }

        $path = $relativeDir . '/' . $fileName;

        return [
            'url' => Yii::$app->request->hostInfo . $path,
            'path' => $path,
            'name' => $file->name,
            'size' => (int)$file->size,
            'scene' => $scene,
        ];
    }

    private function normalizeScene(string $scene): string
    {
        $scene = strtolower(trim($scene));
        $scene = preg_replace('/[^a-z0-9_-]/', '', $scene) ?: 'common';

        return substr($scene, 0, 32);
    }
}
