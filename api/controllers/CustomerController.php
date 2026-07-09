<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\AdProduct;
use common\models\ContractProduct;
use common\models\Customer;
use common\models\CustomerContact;
use common\models\CustomerFollow;
use common\models\Contract;
use common\models\Fulfillment;
use common\models\Manuscript;
use common\models\ReceivablePlan;
use common\models\ReceivableRecord;
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
            'customers' => $this->getCustomerOptions(),
            'users' => $this->getUserOptions(),
        ];
    }

    public function actionView(): array
    {
        $model = $this->findCustomer((int)Yii::$app->request->post('id', 0));
        $contracts = Contract::find()
            ->where(['customer_id' => $model->id, 'deleted' => 0])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();
        $contractIds = array_map(static fn (array $row): int => (int)$row['id'], $contracts);
        $follows = CustomerFollow::find()
            ->where(['customer_id' => $model->id, 'deleted' => 0])
            ->orderBy(['follow_time' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(50)
            ->all();
        $plans = $this->getReceivablePlans((int)$model->id);
        $records = $this->getReceivableRecords((int)$model->id);
        $fulfillments = $this->getFulfillments((int)$model->id);
        $manuscripts = $this->getManuscripts((int)$model->id);

        return [
            'customer' => $this->serializeCustomer($model),
            'stats' => [
                'contract_count' => count($contracts),
                'contract_amount' => (string)array_sum(array_map(static fn (array $row): float => (float)($row['final_amount'] ?? 0), $contracts)),
                'received_amount' => (string)array_sum(array_map(static fn (array $row): float => (float)($row['received_amount'] ?? 0), $contracts)),
                'pending_amount' => (string)array_sum(array_map(static fn (array $row): float => (float)($row['pending_amount'] ?? 0), $contracts)),
                'contact_count' => (int)CustomerContact::find()->where(['customer_id' => $model->id, 'deleted' => 0])->count(),
                'follow_count' => (int)CustomerFollow::find()->where(['customer_id' => $model->id, 'deleted' => 0])->count(),
            ],
            'contacts' => array_map(
                [$this, 'serializeContact'],
                CustomerContact::find()
                    ->where(['customer_id' => $model->id, 'deleted' => 0])
                    ->orderBy(['is_primary' => SORT_DESC, 'id' => SORT_ASC])
                    ->all()
            ),
            'follows' => array_map(
                [$this, 'serializeFollow'],
                $follows
            ),
            'contracts' => array_map(
                [$this, 'serializeContractArray'],
                $contracts
            ),
            'contract_products' => $this->getContractProducts($contractIds),
            'receivable_plans' => $plans,
            'receivable_records' => $records,
            'fulfillments' => $fulfillments,
            'manuscripts' => $manuscripts,
            'timeline' => $this->buildTimeline($follows, $contracts, $plans, $records, $fulfillments, $manuscripts),
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
            'latest_follow_time' => (int)($row['latest_follow_time'] ?? 0),
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

    private function serializeFollow(CustomerFollow $follow): array
    {
        $contact = $follow->contact;
        $owner = $follow->owner;

        return [
            'id' => (int)$follow->id,
            'customer_id' => (int)$follow->customer_id,
            'contact_id' => (int)$follow->contact_id,
            'contact_name' => $contact->contact_name ?? '',
            'contact_mobile' => $contact->mobile ?? '',
            'owner_user_id' => (int)$follow->owner_user_id,
            'owner_name' => $owner ? ((string)$owner->real_name !== '' ? $owner->real_name : $owner->username) : '',
            'follow_time' => (int)$follow->follow_time,
            'follow_type' => (int)$follow->follow_type,
            'follow_status' => (int)$follow->follow_status,
            'next_follow_time' => (int)$follow->next_follow_time,
            'content' => (string)$follow->content,
            'result' => (string)$follow->result,
            'created_at' => (int)$follow->created_at,
            'updated_at' => (int)$follow->updated_at,
        ];
    }

    private function serializeContractArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'contract_type' => (int)($row['contract_type'] ?? 1),
            'parent_contract_id' => (int)($row['parent_contract_id'] ?? 0),
            'final_amount' => (string)($row['final_amount'] ?? '0.00'),
            'received_amount' => (string)($row['received_amount'] ?? '0.00'),
            'pending_amount' => (string)($row['pending_amount'] ?? '0.00'),
            'status' => (int)($row['status'] ?? 1),
            'approval_status' => (int)($row['approval_status'] ?? 0),
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }

    /**
     * @param int[] $contractIds
     */
    private function getContractProducts(array $contractIds): array
    {
        if ($contractIds === []) {
            return [];
        }

        $rows = ContractProduct::find()
            ->where(['contract_id' => $contractIds, 'deleted' => 0])
            ->orderBy(['contract_id' => SORT_DESC, 'sort' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)($row['id'] ?? 0),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'product_id' => (int)($row['product_id'] ?? 0),
            'product_name' => (string)($row['product_name'] ?? ''),
            'media_name' => (string)($row['media_name'] ?? ''),
            'ad_type' => (string)($row['ad_type'] ?? ''),
            'unit' => (string)($row['unit'] ?? ''),
            'sale_price' => (string)($row['sale_price'] ?? '0.00'),
            'quantity' => (string)($row['quantity'] ?? '0.00'),
            'executed_quantity' => (string)($row['executed_quantity'] ?? '0.00'),
            'amount' => (string)($row['amount'] ?? '0.00'),
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'delivery_requirements' => (string)($row['delivery_requirements'] ?? ''),
        ], $rows);
    }

    private function getReceivablePlans(int $customerId): array
    {
        $rows = ReceivablePlan::find()
            ->alias('rp')
            ->select([
                'rp.*',
                'contract_no' => new Expression("COALESCE(ct.contract_no, '')"),
                'contract_name' => new Expression("COALESCE(ct.contract_name, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['ct' => Contract::tableName()], 'ct.id = rp.contract_id AND ct.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = rp.owner_user_id')
            ->where(['rp.customer_id' => $customerId, 'rp.deleted' => 0])
            ->orderBy(['rp.plan_date' => SORT_ASC, 'rp.id' => SORT_ASC])
            ->asArray()
            ->all();

        return array_map([$this, 'serializeReceivablePlanArray'], $rows);
    }

    private function getReceivableRecords(int $customerId): array
    {
        $rows = ReceivableRecord::find()
            ->alias('rr')
            ->select([
                'rr.*',
                'plan_no' => new Expression("COALESCE(rp.plan_no, '')"),
                'plan_name' => new Expression("COALESCE(rp.plan_name, '')"),
                'contract_no' => new Expression("COALESCE(ct.contract_no, '')"),
                'contract_name' => new Expression("COALESCE(ct.contract_name, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['rp' => ReceivablePlan::tableName()], 'rp.id = rr.receivable_plan_id AND rp.deleted = 0')
            ->leftJoin(['ct' => Contract::tableName()], 'ct.id = rr.contract_id AND ct.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = rr.owner_user_id')
            ->where(['rr.customer_id' => $customerId, 'rr.deleted' => 0])
            ->orderBy(['rr.receipt_date' => SORT_DESC, 'rr.id' => SORT_DESC])
            ->asArray()
            ->all();

        return array_map([$this, 'serializeReceivableRecordArray'], $rows);
    }

    private function getFulfillments(int $customerId): array
    {
        $rows = Fulfillment::find()
            ->alias('f')
            ->select([
                'f.*',
                'contract_no' => new Expression("COALESCE(ct.contract_no, '')"),
                'contract_name' => new Expression("COALESCE(ct.contract_name, '')"),
                'product_name' => new Expression("COALESCE(p.product_name, '')"),
                'product_code' => new Expression("COALESCE(p.product_code, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['ct' => Contract::tableName()], 'ct.id = f.contract_id AND ct.deleted = 0')
            ->leftJoin(['p' => AdProduct::tableName()], 'p.id = f.product_id AND p.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = f.owner_user_id')
            ->where(['f.customer_id' => $customerId, 'f.deleted' => 0])
            ->orderBy(['f.id' => SORT_DESC])
            ->asArray()
            ->all();

        return array_map([$this, 'serializeFulfillmentArray'], $rows);
    }

    private function getManuscripts(int $customerId): array
    {
        $rows = Manuscript::find()
            ->alias('m')
            ->select([
                'm.*',
                'product_name' => new Expression("COALESCE(p.product_name, '')"),
                'product_code' => new Expression("COALESCE(p.product_code, '')"),
            ])
            ->leftJoin(['p' => AdProduct::tableName()], 'p.id = m.product_id AND p.deleted = 0')
            ->where(['m.customer_id' => $customerId, 'm.deleted' => 0])
            ->orderBy(['m.id' => SORT_DESC])
            ->asArray()
            ->all();

        return array_map([$this, 'serializeManuscriptArray'], $rows);
    }

    private function serializeReceivablePlanArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'plan_no' => (string)($row['plan_no'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'plan_name' => (string)($row['plan_name'] ?? ''),
            'plan_date' => $row['plan_date'] ?? null,
            'plan_amount' => (string)($row['plan_amount'] ?? '0.00'),
            'received_amount' => (string)($row['received_amount'] ?? '0.00'),
            'pending_amount' => (string)($row['pending_amount'] ?? '0.00'),
            'invoice_amount' => (string)($row['invoice_amount'] ?? '0.00'),
            'status' => (int)($row['status'] ?? 1),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }

    private function serializeReceivableRecordArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'record_no' => (string)($row['record_no'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'plan_name' => (string)($row['plan_name'] ?? ''),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'receipt_date' => $row['receipt_date'] ?? null,
            'receipt_amount' => (string)($row['receipt_amount'] ?? '0.00'),
            'receipt_method' => (string)($row['receipt_method'] ?? ''),
            'payer_name' => (string)($row['payer_name'] ?? ''),
            'status' => (int)($row['status'] ?? 1),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }

    private function serializeFulfillmentArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'fulfillment_no' => (string)($row['fulfillment_no'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'plan_date' => $row['plan_date'] ?? null,
            'fulfillment_date' => $row['fulfillment_date'] ?? null,
            'execute_quantity' => (string)($row['execute_quantity'] ?? '0.00'),
            'executed_quantity' => (string)($row['executed_quantity'] ?? '0.00'),
            'execute_amount' => (string)($row['execute_amount'] ?? '0.00'),
            'status' => (int)($row['status'] ?? 1),
            'content_summary' => (string)($row['content_summary'] ?? ''),
            'result_summary' => (string)($row['result_summary'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }

    private function serializeManuscriptArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'manuscript_no' => (string)($row['manuscript_no'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'manuscript_type' => (int)($row['manuscript_type'] ?? 1),
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
            'article_link' => (string)($row['article_link'] ?? ''),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
        ];
    }

    /**
     * @param CustomerFollow[] $follows
     */
    private function buildTimeline(array $follows, array $contracts, array $plans, array $records, array $fulfillments, array $manuscripts): array
    {
        $items = [];
        foreach ($follows as $follow) {
            $items[] = [
                'type' => 'follow',
                'title' => '新增跟进记录',
                'content' => (string)$follow->content,
                'operator' => $follow->owner ? ((string)$follow->owner->real_name !== '' ? $follow->owner->real_name : $follow->owner->username) : '',
                'time' => (int)$follow->follow_time,
                'link_id' => (int)$follow->id,
            ];
        }
        foreach ($contracts as $row) {
            $items[] = [
                'type' => 'contract',
                'title' => '新增销售合同',
                'content' => (string)($row['contract_name'] ?? ''),
                'amount' => (string)($row['final_amount'] ?? '0.00'),
                'time' => (int)($row['created_at'] ?? 0),
                'link_id' => (int)($row['id'] ?? 0),
            ];
        }
        foreach ($plans as $row) {
            $items[] = [
                'type' => 'receivable_plan',
                'title' => '新增回款计划',
                'content' => (string)($row['plan_name'] ?? ''),
                'amount' => (string)($row['plan_amount'] ?? '0.00'),
                'time' => (int)($row['created_at'] ?? 0),
                'link_id' => (int)($row['id'] ?? 0),
            ];
        }
        foreach ($records as $row) {
            $items[] = [
                'type' => 'receivable_record',
                'title' => '登记回款',
                'content' => (string)($row['contract_name'] ?? ''),
                'amount' => (string)($row['receipt_amount'] ?? '0.00'),
                'time' => (int)($row['created_at'] ?? 0),
                'link_id' => (int)($row['id'] ?? 0),
            ];
        }
        foreach ($fulfillments as $row) {
            $items[] = [
                'type' => 'fulfillment',
                'title' => '新增履约任务',
                'content' => (string)($row['product_name'] ?? ''),
                'time' => (int)($row['created_at'] ?? 0),
                'link_id' => (int)($row['id'] ?? 0),
            ];
        }
        foreach ($manuscripts as $row) {
            $items[] = [
                'type' => 'manuscript',
                'title' => '新增稿件',
                'content' => (string)($row['title'] ?? ''),
                'time' => (int)($row['created_at'] ?? 0),
                'link_id' => (int)($row['id'] ?? 0),
            ];
        }

        usort($items, static fn (array $a, array $b): int => ((int)$b['time']) <=> ((int)$a['time']));

        return array_slice($items, 0, 30);
    }

    private function getCustomerOptions(): array
    {
        $rows = Customer::find()
            ->select(['id', 'customer_name', 'customer_code', 'owner_user_id'])
            ->where(['deleted' => 0])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'customer_name' => (string)$row['customer_name'],
            'customer_code' => (string)$row['customer_code'],
            'owner_user_id' => (int)$row['owner_user_id'],
        ], $rows);
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
