<?php

declare(strict_types=1);

namespace common\services;

use common\models\OaEmployee;
use common\models\OaSyncLog;
use common\models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yii;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;

class DingTalkService
{
    private Client $http;
    private bool $verbose = false;
    private static float $lastRequestAt = 0.0;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?: new Client([
            'base_uri' => 'https://oapi.dingtalk.com',
            'timeout' => 20,
        ]);
    }

    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;

        return $this;
    }

    public function getAccessToken(): string
    {
        $cacheKey = 'dingtalk:access_token';
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $appKey = (string)Yii::$app->params['dingtalk']['appKey'];
        $appSecret = (string)Yii::$app->params['dingtalk']['appSecret'];

        if ($appKey === '' || $appSecret === '') {
            throw new InvalidConfigException('DingTalk appKey/appSecret is empty.');
        }

        $data = $this->request('GET', '/gettoken', [
            'query' => [
                'appkey' => $appKey,
                'appsecret' => $appSecret,
            ],
        ]);

        $token = (string)($data['access_token'] ?? '');
        if ($token === '') {
            throw new BadRequestHttpException('Failed to get DingTalk access token.');
        }

        Yii::$app->cache->set($cacheKey, $token, max(60, (int)($data['expires_in'] ?? 7200) - 300));

        return $token;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentTree(int $parentId = 0): array
    {
        $json = (object)[];
        if ($parentId > 0) {
            $json = ['dept_id' => $parentId];
        }

        try {
            $data = $this->request('POST', '/topapi/v2/department/listsub', [
                'query' => ['access_token' => $this->getAccessToken()],
                'json' => $json,
            ]);
        } catch (BadRequestHttpException $e) {
            if (!str_contains($e->getMessage(), '不在授权范围') && !str_contains($e->getMessage(), 'scope')) {
                throw $e;
            }

            $label = $parentId > 0 ? "部门 {$parentId}" : '当前应用';
            throw new BadRequestHttpException("{$label}不在钉钉应用通讯录授权范围内，请在钉钉开放平台调整通讯录权限范围，或改用已授权部门ID。原始错误：{$e->getMessage()}");
        }

        return $data['result'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentUsers(int $departmentId = 1): array
    {
        $users = [];
        $cursor = 0;
        $size = 100;

        do {
            try {
                $data = $this->request('POST', '/topapi/v2/user/list', [
                    'query' => ['access_token' => $this->getAccessToken()],
                    'json' => [
                        'dept_id' => $departmentId,
                        'cursor' => $cursor,
                        'size' => $size,
                        'contain_access_limit' => false,
                    ],
                ]);
            } catch (BadRequestHttpException $e) {
                throw new BadRequestHttpException("部门 {$departmentId} 不在钉钉应用通讯录授权范围内，请在钉钉开放平台调整通讯录权限范围，或改用已授权部门ID。原始错误：{$e->getMessage()}");
            }

            $result = $data['result'] ?? [];
            foreach (($result['list'] ?? []) as $user) {
                $users[] = $user;
            }

            $cursor = (int)($result['next_cursor'] ?? 0);
        } while (($result['has_more'] ?? false) === true);

        return $users;
    }

    /**
     * @return array{total:int,success:int,fail:int,errors:array<int, string>}
     */
    public function syncEmployees(int $departmentId = 0, bool $recursive = true): array
    {
        $startedAt = time();
        $log = new OaSyncLog();
        $log->sync_type = 'dingtalk_employee';
        $log->status = 0;
        $log->started_at = $startedAt;
        $log->save(false);

        $departments = $recursive ? $this->collectDepartmentIds($departmentId) : $this->getTopDepartmentIds($departmentId);
        $seen = [];
        $total = 0;
        $success = 0;
        $fail = 0;
        $errors = [];

        foreach ($departments as $deptId) {
            $departmentUsers = $this->getDepartmentUsers($deptId);
            $this->log("部门 {$deptId} 获取员工：" . count($departmentUsers) . ' 人');

            foreach ($departmentUsers as $item) {
                $userid = (string)($item['userid'] ?? '');
                if ($userid === '' || isset($seen[$userid])) {
                    continue;
                }

                $seen[$userid] = true;
                $total++;

                try {
                    $this->saveEmployee($item);
                    $this->log('同步员工：' . ($item['name'] ?? '') . "，userid：{$userid}");
                    $success++;
                } catch (\Throwable $e) {
                    $fail++;
                    $this->log("同步失败：{$userid}，" . $e->getMessage());
                    $errors[] = $userid . ': ' . $e->getMessage();
                }
            }
        }

        $finishedAt = time();
        $log->status = $fail === 0 ? 1 : 2;
        $log->total_count = $total;
        $log->success_count = $success;
        $log->fail_count = $fail;
        $log->finished_at = $finishedAt;
        $log->message = "success: {$success}, fail: {$fail}";
        $log->error = $errors === [] ? null : implode("\n", $errors);
        $log->save(false);

        return [
            'total' => $total,
            'success' => $success,
            'fail' => $fail,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function collectDepartmentIds(int $departmentId = 0): array
    {
        $ids = $departmentId > 0 ? [$departmentId] : [];

        foreach ($this->getDepartmentTree($departmentId) as $department) {
            $childId = (int)($department['dept_id'] ?? 0);
            if ($childId <= 0) {
                continue;
            }

            $this->log('部门：' . ($department['name'] ?? '') . "，ID：{$childId}");
            array_push($ids, ...$this->collectDepartmentIds($childId));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int>
     */
    private function getTopDepartmentIds(int $departmentId = 0): array
    {
        if ($departmentId > 0) {
            return [$departmentId];
        }

        $ids = [];
        foreach ($this->getDepartmentTree(0) as $department) {
            $childId = (int)($department['dept_id'] ?? 0);
            if ($childId > 0) {
                $this->log('部门：' . ($department['name'] ?? '') . "，ID：{$childId}");
                $ids[] = $childId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function saveEmployee(array $item): void
    {
        $userid = (string)($item['userid'] ?? '');
        $name = trim((string)($item['name'] ?? ''));
        if ($userid === '' || $name === '') {
            throw new BadRequestHttpException('DingTalk userid/name is empty.');
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $employee = OaEmployee::findOne(['dingtalk_userid' => $userid]) ?: new OaEmployee();
            $user = $employee->isNewRecord ? $this->createLocalUser($item) : User::findOne((int)$employee->user_id);

            if ($user === null) {
                $user = $this->createLocalUser($item);
            }

            $email = $this->normalizeEmail($item, $userid);
            $avatar = (string)($item['avatar'] ?? '');
            $mobile = trim((string)($item['mobile'] ?? ''));

            if ($email !== '' && $user->email !== $email && !User::find()->where(['email' => $email])->andWhere(['<>', 'id', (int)$user->id])->exists()) {
                $user->email = $email;
            }

            if ($avatar !== '' && $user->hasAttribute('avatar')) {
                $user->avatar = $avatar;
            }

            if ($user->hasAttribute('mobile')) {
                $user->mobile = $mobile;
            }

            if ($user->hasAttribute('real_name')) {
                $user->real_name = $name;
            }

            $user->status = User::STATUS_ACTIVE;
            $user->save(false);

            $employee->user_id = (int)$user->id;
            $employee->dingtalk_userid = $userid;
            $employee->unionid = (string)($item['unionid'] ?? '');
            $employee->name = $name;
            $employee->mobile = (string)($item['mobile'] ?? '');
            $employee->email = $email;
            $employee->avatar = $avatar;
            $employee->department_ids = json_encode($item['dept_id_list'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $employee->department_names = '';
            $employee->position = (string)($item['title'] ?? '');
            $employee->job_number = (string)($item['job_number'] ?? '');
            $employee->status = ((bool)($item['active'] ?? true)) ? 1 : 3;
            $employee->synced_at = time();
            $employee->raw_data = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $employee->save(false);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function createLocalUser(array $item): User
    {
        $userid = (string)$item['userid'];
        $email = $this->normalizeEmail($item, $userid);
        $mobile = trim((string)($item['mobile'] ?? ''));
        $name = trim((string)($item['name'] ?? ''));
        $username = $mobile !== '' ? $mobile : $this->fallbackUsername($userid);

        $exists = User::findOne(['username' => $username]) ?: User::findOne(['email' => $email]);
        if ($exists !== null) {
            return $exists;
        }

        $user = new User();
        $user->username = $this->uniqueUsername($username);
        $user->email = $email;
        if ($user->hasAttribute('mobile')) {
            $user->mobile = $mobile;
        }
        if ($user->hasAttribute('real_name')) {
            $user->real_name = $name;
        }
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword($this->initialPassword($mobile, $userid));
        $user->generateAuthKey();
        $user->save(false);

        return $user;
    }

    private function uniqueUsername(string $base): string
    {
        $username = trim($base, '_');
        $username = $username === '' ? 'dt_user' : $username;
        $candidate = $username;
        $index = 1;

        while (User::find()->where(['username' => $candidate])->exists()) {
            $candidate = $username . '_' . $index;
            $index++;
        }

        return $candidate;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function normalizeEmail(array $item, string $userid): string
    {
        $email = trim((string)($item['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return strtolower(preg_replace('/[^a-zA-Z0-9_.-]/', '_', $userid)) . '@dingtalk.local';
    }

    private function fallbackUsername(string $userid): string
    {
        return 'dt_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $userid);
    }

    private function initialPassword(string $mobile, string $userid): string
    {
        $source = $mobile !== '' ? $mobile : $userid;
        $password = substr($source, -6);

        return strlen($password) >= 6 ? $password : str_pad($password, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        $maxRetries = max(0, (int)(Yii::$app->params['dingtalk']['maxRetries'] ?? 3));

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $this->waitForRateLimit();

            $response = $this->http->request($method, $uri, $options);
            $data = json_decode((string)$response->getBody(), true);

            if (!is_array($data)) {
                throw new BadRequestHttpException('Invalid DingTalk response.');
            }

            $errCode = (int)($data['errcode'] ?? 0);
            $message = (string)($data['errmsg'] ?? 'DingTalk request failed.');

            if ($errCode === 0) {
                return $data;
            }

            if ($attempt < $maxRetries && $this->isRateLimitError($errCode, $message)) {
                sleep(min(3, $attempt + 1));
                continue;
            }

            throw new BadRequestHttpException($message);
        }

        throw new BadRequestHttpException('DingTalk request failed.');
    }

    private function waitForRateLimit(): void
    {
        $intervalMs = max(100, (int)(Yii::$app->params['dingtalk']['requestIntervalMs'] ?? 600));
        $elapsedMs = (int)((microtime(true) - self::$lastRequestAt) * 1000);

        if ($elapsedMs < $intervalMs) {
            usleep(($intervalMs - $elapsedMs) * 1000);
        }

        self::$lastRequestAt = microtime(true);
    }

    private function isRateLimitError(int $errCode, string $message): bool
    {
        return $errCode === 90018
            || str_contains($message, '90018')
            || str_contains($message, 'qps')
            || str_contains($message, 'QPS')
            || str_contains($message, '流控')
            || str_contains($message, '次数过多');
    }

    private function log(string $message): void
    {
        if (!$this->verbose) {
            return;
        }

        echo $message . PHP_EOL;
    }
}
