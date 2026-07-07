<?php

declare(strict_types=1);

namespace common\jobs;

use common\models\QueueTask;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use yii\queue\Queue;
use yii\queue\RetryableJobInterface;

class DemoJob extends BaseObject implements JobInterface, RetryableJobInterface
{
    public int $taskId = 0;

    public function execute($queue): void
    {
        $task = QueueTask::findOne($this->taskId);
        if ($task === null) {
            return;
        }

        $now = time();
        $task->status = QueueTask::STATUS_RUNNING;
        $task->attempts++;
        $task->started_at = $task->started_at ?: $now;
        $task->updated_at = $now;
        $task->save(false);

        try {
            $payload = json_decode((string)$task->payload, true) ?: [];
            $seconds = max(1, min(10, (int)($payload['seconds'] ?? 3)));
            sleep($seconds);

            $task->status = QueueTask::STATUS_SUCCESS;
            $task->result = json_encode([
                'message' => 'Demo job finished.',
                'seconds' => $seconds,
                'finished_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);
            $task->error = null;
            $task->finished_at = time();
            $task->updated_at = time();
            $task->save(false);
        } catch (Throwable $exception) {
            $task->status = QueueTask::STATUS_FAILED;
            $task->error = $exception->getMessage();
            $task->finished_at = time();
            $task->updated_at = time();
            $task->save(false);

            Yii::error($exception, __METHOD__);
            throw $exception;
        }
    }

    public function getTtr(): int
    {
        return 60;
    }

    public function canRetry($attempt, $error): bool
    {
        return $attempt < 3;
    }
}
