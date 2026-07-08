<?php

declare(strict_types=1);

namespace common\jobs;

use common\models\QueueTask;
use common\services\DingTalkService;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SyncDingTalkEmployeesJob extends BaseObject implements JobInterface
{
    public int $taskId = 0;
    public int $departmentId = 0;
    public bool $recursive = true;

    public function execute($queue): void
    {
        $task = QueueTask::findOne($this->taskId);
        if ($task !== null) {
            $task->status = 1;
            $task->attempts = (int)$task->attempts + 1;
            $task->updated_at = time();
            $task->save(false);
        }

        try {
            $result = (new DingTalkService())->syncEmployees($this->departmentId, $this->recursive);

            if ($task !== null) {
                $task->status = 2;
                $task->result = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                $task->updated_at = time();
                $task->save(false);
            }
        } catch (\Throwable $e) {
            if ($task !== null) {
                $task->status = 3;
                $task->error = $e->getMessage();
                $task->updated_at = time();
                $task->save(false);
            }

            throw $e;
        }
    }
}
