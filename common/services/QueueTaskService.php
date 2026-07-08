<?php

declare(strict_types=1);

namespace common\services;

use common\jobs\DemoJob;
use common\models\QueueTask;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class QueueTaskService
{
    public function index(int $page, int $size, string $keyword, mixed $status): array
    {
        $query = QueueTask::find();
        if ($keyword !== '') {
            $query->andWhere(['like', 'name', $keyword]);
        }

        if ($status !== '' && $status !== null) {
            $query->andWhere(['status' => (int)$status]);
        }

        $total = (int)(clone $query)->count();
        $records = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->all();

        return [
            'records' => array_map([$this, 'serializeTask'], $records),
            'current' => $page,
            'size' => $size,
            'total' => $total,
        ];
    }

    public function createDemo(string $name, int $seconds, int $userId): array
    {
        $task = new QueueTask();
        $task->name = $name !== '' ? $name : 'Demo queue task';
        $task->payload = json_encode(['seconds' => $seconds], JSON_UNESCAPED_UNICODE);
        $task->status = QueueTask::STATUS_WAITING;
        $task->created_by = $userId;

        if (!$task->save()) {
            throw new BadRequestHttpException($this->firstError($task));
        }

        $jobId = Yii::$app->queue->push(new DemoJob(['taskId' => (int)$task->id]));
        $task->job_id = (string)$jobId;
        $task->save(false);

        return $this->serializeTask($task);
    }

    public function retry(int $id): array
    {
        $task = $this->findTask($id);
        if ((int)$task->status !== QueueTask::STATUS_FAILED) {
            throw new BadRequestHttpException('Only failed tasks can be retried.');
        }

        $task->status = QueueTask::STATUS_WAITING;
        $task->error = null;
        $task->finished_at = null;
        $task->save(false);

        $jobId = Yii::$app->queue->push(new DemoJob(['taskId' => (int)$task->id]));
        $task->job_id = (string)$jobId;
        $task->save(false);

        return $this->serializeTask($task);
    }

    public function delete(int $id): array
    {
        $task = $this->findTask($id);
        if ((int)$task->status === QueueTask::STATUS_RUNNING) {
            throw new BadRequestHttpException('Running task cannot be deleted.');
        }

        if ($task->delete() === false) {
            throw new BadRequestHttpException('Failed to delete queue task.');
        }

        return ['deleted' => true];
    }

    private function findTask(int $id): QueueTask
    {
        $model = QueueTask::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Queue task does not exist.');
        }

        return $model;
    }

    private function serializeTask(QueueTask $model): array
    {
        return [
            'id' => (int)$model->id,
            'job_id' => $model->job_id,
            'name' => $model->name,
            'payload' => $model->payload ?: '{}',
            'result' => $model->result ?: '',
            'status' => (int)$model->status,
            'attempts' => (int)$model->attempts,
            'error' => $model->error ?: '',
            'created_by' => (int)$model->created_by,
            'created_at' => (int)$model->created_at,
            'started_at' => $model->started_at ? (int)$model->started_at : null,
            'finished_at' => $model->finished_at ? (int)$model->finished_at : null,
            'updated_at' => (int)$model->updated_at,
        ];
    }

    private function firstError(QueueTask $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: 'Invalid queue task data.';
    }
}
