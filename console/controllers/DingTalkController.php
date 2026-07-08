<?php

declare(strict_types=1);

namespace console\controllers;

use common\models\OaEmployee;
use common\models\User;
use common\services\DingTalkService;
use yii\console\Controller;
use yii\helpers\Console;

class DingTalkController extends Controller
{
    public int $verboseLog = 1;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['verboseLog']);
    }

    public function actionSyncEmployees(int $departmentId = 0, int $recursive = 1): int
    {
        $service = (new DingTalkService())->setVerbose((bool)$this->verboseLog);
        $result = $service->syncEmployees($departmentId, (bool)$recursive);

        $this->stdout("Total: {$result['total']}\n", Console::FG_GREEN);
        $this->stdout("Success: {$result['success']}\n", Console::FG_GREEN);
        $this->stdout("Fail: {$result['fail']}\n", $result['fail'] > 0 ? Console::FG_RED : Console::FG_GREEN);

        foreach ($result['errors'] as $error) {
            $this->stderr($error . "\n", Console::FG_RED);
        }

        return $result['fail'] > 0 ? self::EXIT_CODE_ERROR : self::EXIT_CODE_NORMAL;
    }

    public function actionFixUsernamesByMobile(int $dryRun = 1, int $resetPassword = 0): int
    {
        $employees = OaEmployee::find()
            ->where(['<>', 'mobile', ''])
            ->with('user')
            ->all();

        $changed = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $user = $employee->user;
            $mobile = trim((string)$employee->mobile);

            if ($user === null || $mobile === '') {
                $skipped++;
                continue;
            }

            if ($user->username !== $mobile) {
                $exists = User::find()
                    ->where(['username' => $mobile])
                    ->andWhere(['<>', 'id', (int)$user->id])
                    ->exists();

                if ($exists) {
                    $this->stderr("Skip user {$user->id}: mobile {$mobile} already used.\n", Console::FG_YELLOW);
                    $skipped++;
                    continue;
                }
            }

            $this->stdout("User {$user->id}: {$user->username} -> {$mobile}, name: {$employee->name}\n", Console::FG_GREEN);

            if (!$dryRun) {
                $user->username = $mobile;
                if ($user->hasAttribute('mobile')) {
                    $user->mobile = $mobile;
                }
                if ($user->hasAttribute('real_name')) {
                    $user->real_name = $employee->name;
                }
                if ($resetPassword) {
                    $user->setPassword(substr($mobile, -6));
                }
                $user->save(false);
            }

            $changed++;
        }

        $mode = $dryRun ? 'dry-run' : 'updated';
        $this->stdout("{$mode}: {$changed}, skipped: {$skipped}\n", Console::FG_GREEN);

        return self::EXIT_CODE_NORMAL;
    }
}
