<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Customer;
use common\models\CustomerContact;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CustomerController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'customer.view',
        'view' => 'customer.view',
        'select-options' => 'customer.view',
        'create' => 'customer.create',
        'update' => 'customer.update',
        'delete' => 'customer.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $customerName = trim((string)Yii::$app->request->post('customer_name', ''));
        $customerCode = trim((string)Yii::$app->request->post('customer_code', ''));
        $customerType = (int)Yii::$app->request->post('customer_type', 0);
        $level = (int)Yii::$app->request->post('level', 0);
        $status = (int)Yii::$app->request->post('status', 0);
        $ownerUserId = (int)Yii::$app->request->post('owner_user_id', 0);

        $query = Customer::find()
            ->alias('c')
            ->select([
                'c.*',
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
                'owner_mobile' => new Expression("COALESCE(u.mobile, '')"),
            ])
            ->leftJoin(['u' => User::tableName()], 'u.id = c.owner_user_id')
            ->where(['c.deleted' => 0]);

        if ($customerName !== '') {
            $query->andWhere(['like', 'c.customer_name', $customerName]);
        }

        if ($customerCode !== '') {
            $query->andWhere(['like', 'c.customer_code', $customerCode]);
        }

        if ($customerType > 0) {
            $query->andWhere(['c.customer_type' => $customerType]);
        }

        if ($level > 0) {
            $query->andWhere(['c.level' => $level]);
        }

        if ($status > 0) {
            $query->andWhere(['c.status' => $status]);
        }

        if ($ownerUserId > 0) {
            $query->andWhere(['c.owner_user_id' => $ownerUserId]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['c.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeCustomerArray'], $records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'size' => $size,
        ];
    }

    public function actionSelectOptions(): array
    {
        return [
            'users' => $this->getUserOptions(),
        ];
    }

    public function actionView(): array
    {
        $model = $this->findCustomer((int)Yii::$app->request->post('id', 0));

        return [
            'customer' => $this->serializeCustomer($model),
            'contacts' => array_map(
                [$this, 'serializeContact'],
                CustomerContact::find()
                    ->where(['customer_id' => $model->id, 'deleted' => 0])
                    ->orderBy(['is_primary' => SORT_DESC, 'id' => SORT_ASC])
                    ->all()
            ),
        ];
    }

    public function actionCreate(): array
    {
        $model = new Customer();
        $this->loadCustomer($model);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$model->save()) {
                throw new BadRequestHttpException($this->firstError($model));
            }

            $contact = $this->buildPrimaryContact($model);
            if ($contact !== null && !$contact->save()) {
                throw new BadRequestHttpException($this->firstError($contact));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->serializeCustomer($model);
    }

    public function actionUpdate(): array
    {
        $model = $this->findCustomer((int)Yii::$app->request->post('id', 0));
        $this->loadCustomer($model);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $this->serializeCustomer($model);
    }

    public function actionDelete(): array
    {
        $model = $this->findCustomer((int)Yii::$app->request->post('id', 0));
        $userId = (int)(Yii::$app->user->id ?: 0);

        $model->markDeleted();

        if (!$model->save(false)) {
            throw new BadRequestHttpException('删除客户失败');
        }

        CustomerContact::markDeletedByCustomer((int)$model->id, $userId);

        return [
            'deleted' => true,
            'id' => (int)$model->id,
        ];
    }

    private function loadCustomer(Customer $model): void
    {
        $post = Yii::$app->request->post();
        $customerCode = trim((string)($post['customer_code'] ?? ''));

        $model->customer_name = trim((string)($post['customer_name'] ?? ''));
        $model->customer_code = $customerCode !== '' ? $customerCode : ($model->customer_code ?: $this->generateCustomerCode());
        $model->customer_type = (int)($post['customer_type'] ?? 1);
        $model->industry = trim((string)($post['industry'] ?? ''));
        $model->level = (int)($post['level'] ?? 1);
        $model->status = (int)($post['status'] ?? 1);
        $model->owner_user_id = (int)($post['owner_user_id'] ?? 0);
        $model->company_address = trim((string)($post['company_address'] ?? ''));
        $model->website = trim((string)($post['website'] ?? ''));
        $model->taxpayer_no = trim((string)($post['taxpayer_no'] ?? ''));
        $model->bank_name = trim((string)($post['bank_name'] ?? ''));
        $model->bank_account = trim((string)($post['bank_account'] ?? ''));
        $model->invoice_title = trim((string)($post['invoice_title'] ?? ''));
        $model->cooperation_start_date = trim((string)($post['cooperation_start_date'] ?? '')) ?: null;
        $model->source = trim((string)($post['source'] ?? ''));
        $model->follow_status = (int)($post['follow_status'] ?? 1);
        $model->remark = trim((string)($post['remark'] ?? ''));
        if ($model->customer_name === '') {
            throw new BadRequestHttpException('客户名称不能为空');
        }

        if (!in_array($model->customer_type, [1, 2, 3], true)) {
            throw new BadRequestHttpException('客户类型不正确');
        }

        if (!in_array($model->level, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('客户等级不正确');
        }

        if (!in_array($model->status, [1, 2, 3], true)) {
            throw new BadRequestHttpException('客户状态不正确');
        }

        if (!in_array($model->follow_status, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('跟进状态不正确');
        }

        $exists = Customer::find()
            ->where(['customer_code' => $model->customer_code])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$model->id])
            ->exists();

        if ($exists) {
            throw new BadRequestHttpException('客户编码已存在');
        }
    }

    private function buildPrimaryContact(Customer $customer): ?CustomerContact
    {
        $post = Yii::$app->request->post();
        $contactName = trim((string)($post['contact_name'] ?? ''));
        $mobile = trim((string)($post['contact_mobile'] ?? ''));

        if ($contactName === '' && $mobile === '') {
            return null;
        }

        $contact = new CustomerContact();
        $contact->customer_id = (int)$customer->id;
        $contact->contact_name = $contactName;
        $contact->mobile = $mobile;
        $contact->wechat = trim((string)($post['contact_wechat'] ?? ''));
        $contact->email = trim((string)($post['contact_email'] ?? ''));
        $contact->position = trim((string)($post['contact_position'] ?? ''));
        $contact->remark = trim((string)($post['contact_remark'] ?? ''));
        $contact->is_primary = 1;
        $contact->status = 1;
        return $contact;
    }

    private function findCustomer(int $id): Customer
    {
        $model = Customer::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('客户不存在');
        }

        return $model;
    }

    private function serializeCustomer(Customer $model): array
    {
        $row = $model->toArray();
        $owner = $model->owner_user_id > 0 ? User::findOne($model->owner_user_id) : null;
        $row['owner_name'] = $owner ? ((string)$owner->real_name !== '' ? $owner->real_name : $owner->username) : '';
        $row['owner_mobile'] = $owner->mobile ?? '';

        return $this->serializeCustomerArray($row);
    }

    private function serializeCustomerArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_code' => (string)($row['customer_code'] ?? ''),
            'customer_type' => (int)($row['customer_type'] ?? 1),
            'industry' => (string)($row['industry'] ?? ''),
            'level' => (int)($row['level'] ?? 1),
            'status' => (int)($row['status'] ?? 1),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'owner_mobile' => (string)($row['owner_mobile'] ?? ''),
            'company_address' => (string)($row['company_address'] ?? ''),
            'website' => (string)($row['website'] ?? ''),
            'taxpayer_no' => (string)($row['taxpayer_no'] ?? ''),
            'bank_name' => (string)($row['bank_name'] ?? ''),
            'bank_account' => (string)($row['bank_account'] ?? ''),
            'invoice_title' => (string)($row['invoice_title'] ?? ''),
            'cooperation_start_date' => $row['cooperation_start_date'] ?? null,
            'source' => (string)($row['source'] ?? ''),
            'follow_status' => (int)($row['follow_status'] ?? 1),
            //'latest_follow_time' => (int)($row['latest_follow_time'] ?? 0),
            'signed_contract_amount' => (string)($row['signed_contract_amount'] ?? '0.00'),
            'received_amount' => (string)($row['received_amount'] ?? '0.00'),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function serializeContact(CustomerContact $contact): array
    {
        return [
            'id' => (int)$contact->id,
            'customer_id' => (int)$contact->customer_id,
            'contact_name' => $contact->contact_name,
            'mobile' => $contact->mobile,
            'wechat' => $contact->wechat,
            'email' => $contact->email,
            'position' => $contact->position,
            'is_primary' => (int)$contact->is_primary,
            'status' => (int)$contact->status,
            'remark' => $contact->remark,
            'created_at' => (int)$contact->created_at,
            'updated_at' => (int)$contact->updated_at,
        ];
    }

    private function getUserOptions(): array
    {
        $users = User::find()
            ->select(['id', 'username', 'real_name', 'mobile'])
            ->where(['status' => User::STATUS_ACTIVE])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        return array_map(static fn (array $user): array => [
            'id' => (int)$user['id'],
            'name' => (string)($user['real_name'] ?: $user['username']),
            'mobile' => (string)($user['mobile'] ?? ''),
        ], $users);
    }

    private function generateCustomerCode(): string
    {
        $prefix = 'KH' . date('Ymd');

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!Customer::find()->where(['customer_code' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstError(Customer|CustomerContact $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }
}
