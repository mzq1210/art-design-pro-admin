<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\OaEmployee;
use common\models\QueueTask;
use common\jobs\SyncDingTalkEmployeesJob;
use Yii;

class DingTalkController extends BaseController
{
    protected array $rbacPermissions = [
        'employee-index' => 'dingtalk.employee.view',
        'employee-sync' => 'dingtalk.employee.sync',
    ];

    public function actionEmployeeIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = OaEmployee::find()->with('user');

        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'name', $keyword],
                ['like', 'mobile', $keyword],
                ['like', 'email', $keyword],
                ['like', 'dingtalk_userid', $keyword],
            ]);
        }

        $total = (int)(clone $query)->count();
        $employees = $query
            ->orderBy(['id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->all();

        return [
            'records' => array_map(fn (OaEmployee $employee): array => $this->serializeEmployee($employee), $employees),
            'current' => $page,
            'size' => $size,
            'total' => $total,
        ];
    }

    public function actionEmployeeSync(): array
    {
        $departmentId = max(0, (int)Yii::$app->request->post('department_id', 0));
        $recursive = (bool)Yii::$app->request->post('recursive', true);

        $task = new QueueTask();
        $task->name = '同步钉钉员工';
        $task->job_id = '';
        $task->payload = json_encode([
            'department_id' => $departmentId,
            'recursive' => $recursive,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $task->status = 0;
        $task->attempts = 0;
        $task->result = '';
        $task->error = '';
        $task->save(false);

        $jobId = Yii::$app->queue->push(new SyncDingTalkEmployeesJob([
            'taskId' => (int)$task->id,
            'departmentId' => $departmentId,
            'recursive' => $recursive,
        ]));

        $task->job_id = (string)$jobId;
        $task->save(false);

        return [
            'queued' => true,
            'task_id' => (int)$task->id,
            'job_id' => (string)$jobId,
        ];
    }

    private function serializeEmployee(OaEmployee $employee): array
    {
        return [
            'id' => (int)$employee->id,
            'user_id' => (int)$employee->user_id,
            'username' => $employee->user->username ?? '',
            'dingtalk_userid' => $employee->dingtalk_userid,
            'unionid' => $employee->unionid,
            'name' => $employee->name,
            'mobile' => $employee->mobile,
            'email' => $employee->email,
            'avatar' => $employee->avatar,
            'department_ids' => json_decode((string)$employee->department_ids, true) ?: [],
            'department_names' => $employee->department_names,
            'position' => $employee->position,
            'job_number' => $employee->job_number,
            'status' => (int)$employee->status,
            'synced_at' => (int)$employee->synced_at,
            'created_at' => (int)$employee->created_at,
            'updated_at' => (int)$employee->updated_at,
        ];
    }
}
