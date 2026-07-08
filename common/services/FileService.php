<?php

declare(strict_types=1);

namespace common\services;

use common\models\FileAttachment;
use common\models\FileGroup;
use Yii;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class FileService
{
    private const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm'];

    public function groupIndex(string $keyword): array
    {
        $query = FileGroup::find()->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC]);

        if ($keyword !== '') {
            $query->andWhere(['or', ['like', 'name', $keyword], ['like', 'code', $keyword]]);
        }

        $records = array_map([$this, 'serializeGroup'], $query->all());

        return [
            'records' => $records,
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function createGroup(array $data): array
    {
        $model = new FileGroup();
        $this->loadGroup($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeGroup($model);
    }

    public function updateGroup(int $id, array $data): array
    {
        $model = $this->findGroup($id);
        $this->loadGroup($model, $data);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeGroup($model);
    }

    public function deleteGroup(int $id): array
    {
        $model = $this->findGroup($id);

        if (FileAttachment::find()->where(['group_id' => $model->id])->exists()) {
            throw new BadRequestHttpException('Please move or delete files in this group first.');
        }

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete file group.');
        }

        return ['deleted' => true];
    }

    public function index(int $page, int $size, mixed $groupId, string $keyword): array
    {
        $query = FileAttachment::find();
        if ($groupId !== '' && $groupId !== null) {
            $query->andWhere(['group_id' => (int)$groupId]);
        }

        if ($keyword !== '') {
            $query->andWhere(['or', ['like', 'name', $keyword], ['like', 'scene', $keyword]]);
        }

        $total = (int)(clone $query)->count();
        $records = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->all();

        return [
            'records' => array_map([$this, 'serializeFile'], $records),
            'current' => $page,
            'size' => $size,
            'total' => $total,
        ];
    }

    public function upload(UploadedFile $file, array $data, int $userId, string $hostInfo): array
    {
        if ($file->size > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException('File size cannot exceed 20MB.');
        }

        $extension = strtolower((string)$file->extension);
        if ($extension === '' || in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new BadRequestHttpException('File type is not allowed.');
        }

        $groupId = (int)($data['group_id'] ?? 0);
        if ($groupId > 0 && FileGroup::findOne($groupId) === null) {
            throw new BadRequestHttpException('File group does not exist.');
        }

        $scene = $this->normalizeScene((string)($data['scene'] ?? 'attachment'));
        $datePath = date('Ym');
        $relativeDir = '/uploads/' . $scene . '/' . $datePath;
        $saveDir = Yii::getAlias('@api/web') . $relativeDir;
        FileHelper::createDirectory($saveDir);

        $storageName = Yii::$app->security->generateRandomString(24) . '.' . $extension;
        $savePath = $saveDir . DIRECTORY_SEPARATOR . $storageName;

        if (!$file->saveAs($savePath)) {
            throw new BadRequestHttpException('Failed to save uploaded file.');
        }

        $path = $relativeDir . '/' . $storageName;
        $model = new FileAttachment();
        $model->group_id = $groupId;
        $model->scene = $scene;
        $model->name = $file->name;
        $model->storage_name = $storageName;
        $model->path = $path;
        $model->url = $hostInfo . $path;
        $model->extension = $extension;
        $model->mime_type = (string)$file->type;
        $model->size = (int)$file->size;
        $model->remark = trim((string)($data['remark'] ?? ''));
        $model->created_by = $userId;

        if (!$model->save()) {
            @unlink($savePath);
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeFile($model);
    }

    public function update(int $id, array $data): array
    {
        $model = $this->findFile($id);
        $groupId = (int)($data['group_id'] ?? $model->group_id);

        if ($groupId > 0 && FileGroup::findOne($groupId) === null) {
            throw new BadRequestHttpException('File group does not exist.');
        }

        $model->group_id = $groupId;
        $model->remark = trim((string)($data['remark'] ?? (string)$model->remark));

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeFile($model);
    }

    public function delete(int $id): array
    {
        $model = $this->findFile($id);
        $filePath = Yii::getAlias('@api/web') . $model->path;

        if ($model->delete() === false) {
            throw new BadRequestHttpException('Failed to delete file.');
        }

        if (is_file($filePath)) {
            @unlink($filePath);
        }

        return ['deleted' => true];
    }

    private function loadGroup(FileGroup $model, array $data): void
    {
        $model->name = trim((string)($data['name'] ?? ''));
        $model->code = trim((string)($data['code'] ?? ''));
        $model->sort = (int)($data['sort'] ?? 0);
        $model->remark = trim((string)($data['remark'] ?? ''));
    }

    private function findGroup(int $id): FileGroup
    {
        $model = FileGroup::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('File group does not exist.');
        }

        return $model;
    }

    private function findFile(int $id): FileAttachment
    {
        $model = FileAttachment::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('File does not exist.');
        }

        return $model;
    }

    private function normalizeScene(string $scene): string
    {
        $scene = strtolower(trim($scene));
        $scene = preg_replace('/[^a-z0-9_-]/', '', $scene) ?: 'attachment';

        return substr($scene, 0, 32);
    }

    private function serializeGroup(FileGroup $model): array
    {
        return [
            'id' => (int)$model->id,
            'name' => $model->name,
            'code' => $model->code,
            'sort' => (int)$model->sort,
            'remark' => $model->remark ?: '',
            'created_at' => (int)$model->created_at,
            'updated_at' => (int)$model->updated_at,
        ];
    }

    private function serializeFile(FileAttachment $model): array
    {
        return [
            'id' => (int)$model->id,
            'group_id' => (int)$model->group_id,
            'scene' => $model->scene,
            'name' => $model->name,
            'storage_name' => $model->storage_name,
            'path' => $model->path,
            'url' => $model->url,
            'extension' => $model->extension,
            'mime_type' => $model->mime_type,
            'size' => (int)$model->size,
            'remark' => $model->remark ?: '',
            'created_by' => (int)$model->created_by,
            'created_at' => (int)$model->created_at,
            'updated_at' => (int)$model->updated_at,
        ];
    }

    private function firstError(FileGroup|FileAttachment $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: 'Invalid file data.';
    }
}
