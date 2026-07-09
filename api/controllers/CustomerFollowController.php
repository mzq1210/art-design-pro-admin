<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Customer;
use common\models\CustomerContact;
use common\models\CustomerFollow;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CustomerFollowController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'customer.follow.view',
        'create' => 'customer.follow.create',
        'update' => 'customer.follow.update',
        'delete' => 'customer.follow.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $customerId = (int)Yii::$app->request->post('customer_id', 0);
        $ownerUserId = (int)Yii::$app->request->post('owner_user_id', 0);
        $followStatus = (int)Yii::$app->request->post('follow_status', 0);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = CustomerFollow::find()
            ->alias('cf')
            ->select([
                'cf.*',
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'customer_code' => new Expression("COALESCE(c.customer_code, '')"),
                'contact_name' => new Expression("COALESCE(cc.contact_name, '')"),
                'contact_mobile' => new Expression("COALESCE(cc.mobile, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['c' => Customer::tableName()], 'c.id = cf.customer_id AND c.deleted = 0')
            ->leftJoin(['cc' => CustomerContact::tableName()], 'cc.id = cf.contact_id AND cc.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = cf.owner_user_id')
            ->where(['cf.deleted' => 0]);

        if ($customerId > 0) {
            $query->andWhere(['cf.customer_id' => $customerId]);
        }
        if ($ownerUserId > 0) {
            $query->andWhere(['cf.owner_user_id' => $ownerUserId]);
        }
        if ($followStatus > 0) {
            $query->andWhere(['cf.follow_status' => $followStatus]);
        }
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'c.customer_name', $keyword],
                ['like', 'cc.contact_name', $keyword],
                ['like', 'cf.content', $keyword],
                ['like', 'cf.result', $keyword],
            ]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['cf.follow_time' => SORT_DESC, 'cf.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeFollowArray'], $records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'size' => $size,
        ];
    }

    public function actionCreate(): array
    {
        $model = new CustomerFollow();
        $this->loadFollow($model);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        $this->refreshCustomerFollow($model);

        return $this->serializeFollow($model);
    }

    public function actionUpdate(): array
    {
        $model = $this->findFollow((int)Yii::$app->request->post('id', 0));
        $this->loadFollow($model);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        $this->refreshCustomerFollow($model);

        return $this->serializeFollow($model);
    }

    public function actionDelete(): array
    {
        $model = $this->findFollow((int)Yii::$app->request->post('id', 0));
        $model->markDeleted();
        $model->save(false);

        return ['deleted' => true, 'id' => (int)$model->id];
    }

    private function loadFollow(CustomerFollow $model): void
    {
        $post = Yii::$app->request->post();
        $customer = Customer::findOne(['id' => (int)($post['customer_id'] ?? 0), 'deleted' => 0]);
        if ($customer === null) {
            throw new BadRequestHttpException('请选择客户');
        }

        $contactId = (int)($post['contact_id'] ?? 0);
        if ($contactId > 0 && !CustomerContact::find()->where(['id' => $contactId, 'customer_id' => $customer->id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('联系人不属于当前客户');
        }

        $ownerUserId = (int)($post['owner_user_id'] ?? 0);
        if ($ownerUserId <= 0) {
            $ownerUserId = (int)$customer->owner_user_id;
        }
        if ($ownerUserId <= 0 || !User::find()->where(['id' => $ownerUserId, 'status' => User::STATUS_ACTIVE])->exists()) {
            throw new BadRequestHttpException('请选择有效负责人');
        }

        $followTime = $this->parseTime($post['follow_time'] ?? null);
        $nextFollowTime = $this->parseTime($post['next_follow_time'] ?? null);

        $model->customer_id = (int)$customer->id;
        $model->contact_id = $contactId;
        $model->owner_user_id = $ownerUserId;
        $model->follow_time = $followTime > 0 ? $followTime : time();
        $model->follow_type = (int)($post['follow_type'] ?? CustomerFollow::TYPE_PHONE);
        $model->follow_status = (int)($post['follow_status'] ?? (int)$customer->follow_status);
        $model->next_follow_time = $nextFollowTime;
        $model->content = trim((string)($post['content'] ?? ''));
        $model->result = trim((string)($post['result'] ?? ''));

        if ($model->content === '') {
            throw new BadRequestHttpException('请输入跟进内容');
        }
        if (!in_array($model->follow_type, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('跟进方式不正确');
        }
        if (!in_array($model->follow_status, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('跟进阶段不正确');
        }
    }

    private function parseTime(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : $timestamp;
    }

    private function refreshCustomerFollow(CustomerFollow $follow): void
    {
        $customer = Customer::findOne(['id' => $follow->customer_id, 'deleted' => 0]);
        if ($customer === null) {
            return;
        }

        $customer->follow_status = (int)$follow->follow_status;
        if ((int)$follow->follow_time > (int)$customer->latest_follow_time) {
            $customer->latest_follow_time = (int)$follow->follow_time;
        }
        $customer->save(false);
    }

    private function findFollow(int $id): CustomerFollow
    {
        $model = CustomerFollow::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('跟进记录不存在');
        }

        return $model;
    }

    private function serializeFollow(CustomerFollow $model): array
    {
        $row = $model->toArray();
        $row['customer_name'] = $model->customer->customer_name ?? '';
        $row['customer_code'] = $model->customer->customer_code ?? '';
        $row['contact_name'] = $model->contact->contact_name ?? '';
        $row['contact_mobile'] = $model->contact->mobile ?? '';
        $owner = $model->owner;
        $row['owner_name'] = $owner ? ((string)$owner->real_name !== '' ? $owner->real_name : $owner->username) : '';

        return $this->serializeFollowArray($row);
    }

    private function serializeFollowArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_code' => (string)($row['customer_code'] ?? ''),
            'contact_id' => (int)($row['contact_id'] ?? 0),
            'contact_name' => (string)($row['contact_name'] ?? ''),
            'contact_mobile' => (string)($row['contact_mobile'] ?? ''),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'follow_time' => (int)($row['follow_time'] ?? 0),
            'follow_type' => (int)($row['follow_type'] ?? 1),
            'follow_status' => (int)($row['follow_status'] ?? 1),
            'next_follow_time' => (int)($row['next_follow_time'] ?? 0),
            'content' => (string)($row['content'] ?? ''),
            'result' => (string)($row['result'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function firstError(CustomerFollow $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }
}
