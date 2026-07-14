<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Contract;
use common\models\Customer;
use common\models\ReceivablePlan;
use common\models\ReceivableRecord;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ReceivableController extends BaseController
{
    protected array $rbacPermissions = [
        'plan-index' => 'receivable.plan.view',
        'record-index' => 'receivable.record.view',
        'select-options' => 'receivable.plan.view',
        'plan-view' => 'receivable.plan.view',
        'plan-create' => 'receivable.plan.create',
        'plan-update' => 'receivable.plan.update',
        'plan-delete' => 'receivable.plan.delete',
        'record-create' => 'receivable.record.create',
        'record-update' => 'receivable.record.update',
        'record-delete' => 'receivable.record.delete',
    ];

    public function actionPlanIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $contractId = (int)Yii::$app->request->post('contract_id', 0);
        $customerId = (int)Yii::$app->request->post('customer_id', 0);
        $status = (int)Yii::$app->request->post('status', 0);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = ReceivablePlan::find()
            ->alias('rp')
            ->select([
                'rp.*',
                'contract_no' => new Expression("COALESCE(ct.contract_no, '')"),
                'contract_name' => new Expression("COALESCE(ct.contract_name, '')"),
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['ct' => Contract::tableName()], 'ct.id = rp.contract_id AND ct.deleted = 0')
            ->leftJoin(['c' => Customer::tableName()], 'c.id = rp.customer_id AND c.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = rp.owner_user_id')
            ->where(['rp.deleted' => 0]);

        if ($contractId > 0) {
            $query->andWhere(['rp.contract_id' => $contractId]);
        }
        if ($customerId > 0) {
            $query->andWhere(['rp.customer_id' => $customerId]);
        }
        if ($status > 0) {
            $query->andWhere(['rp.status' => $status]);
        }
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'rp.plan_no', $keyword],
                ['like', 'rp.plan_name', $keyword],
                ['like', 'ct.contract_no', $keyword],
                ['like', 'ct.contract_name', $keyword],
                ['like', 'c.customer_name', $keyword],
            ]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['rp.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializePlanArray'], $records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'size' => $size,
        ];
    }

    public function actionRecordIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $contractId = (int)Yii::$app->request->post('contract_id', 0);
        $planId = (int)Yii::$app->request->post('receivable_plan_id', 0);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = ReceivableRecord::find()
            ->alias('rr')
            ->select([
                'rr.*',
                'plan_no' => new Expression("COALESCE(rp.plan_no, '')"),
                'plan_name' => new Expression("COALESCE(rp.plan_name, '')"),
                'contract_no' => new Expression("COALESCE(ct.contract_no, '')"),
                'contract_name' => new Expression("COALESCE(ct.contract_name, '')"),
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['rp' => ReceivablePlan::tableName()], 'rp.id = rr.receivable_plan_id AND rp.deleted = 0')
            ->leftJoin(['ct' => Contract::tableName()], 'ct.id = rr.contract_id AND ct.deleted = 0')
            ->leftJoin(['c' => Customer::tableName()], 'c.id = rr.customer_id AND c.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = rr.owner_user_id')
            ->where(['rr.deleted' => 0]);

        if ($contractId > 0) {
            $query->andWhere(['rr.contract_id' => $contractId]);
        }
        if ($planId > 0) {
            $query->andWhere(['rr.receivable_plan_id' => $planId]);
        }
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'rr.record_no', $keyword],
                ['like', 'rr.payer_name', $keyword],
                ['like', 'rr.bank_serial_no', $keyword],
                ['like', 'ct.contract_no', $keyword],
                ['like', 'c.customer_name', $keyword],
            ]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['rr.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeRecordArray'], $records),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'size' => $size,
        ];
    }

    public function actionSelectOptions(): array
    {
        return [
            'contracts' => $this->getContractOptions(),
            'customers' => $this->getCustomerOptions(),
            'users' => $this->getUserOptions(),
        ];
    }

    public function actionPlanView(): array
    {
        return $this->findPlan((int)Yii::$app->request->post('id', 0))->toArray();
    }

    public function actionPlanCreate(): array
    {
        $plan = new ReceivablePlan();
        $this->loadPlan($plan);

        if (!$plan->save()) {
            throw new BadRequestHttpException($this->firstPlanError($plan));
        }

        return $plan->toArray();
    }

    public function actionPlanUpdate(): array
    {
        $plan = $this->findPlan((int)Yii::$app->request->post('id', 0));
        $this->loadPlan($plan);

        if (!$plan->save()) {
            throw new BadRequestHttpException($this->firstPlanError($plan));
        }

        return $plan->toArray();
    }

    public function actionPlanDelete(): array
    {
        $plan = $this->findPlan((int)Yii::$app->request->post('id', 0));
        if (ReceivableRecord::find()->where(['receivable_plan_id' => $plan->id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('已有回款记录的计划不能删除');
        }

        $plan->markDeleted();
        $plan->save(false);

        return ['deleted' => true, 'id' => (int)$plan->id];
    }

    public function actionRecordCreate(): array
    {
        $record = new ReceivableRecord();
        $this->loadRecord($record);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$record->save()) {
                throw new BadRequestHttpException($this->firstRecordError($record));
            }
            $this->refreshPlanAndContract($record->receivable_plan_id);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $record->toArray();
    }

    public function actionRecordUpdate(): array
    {
        $record = $this->findRecord((int)Yii::$app->request->post('id', 0));
        $oldPlanId = (int)$record->receivable_plan_id;
        $this->loadRecord($record);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$record->save()) {
                throw new BadRequestHttpException($this->firstRecordError($record));
            }
            $this->refreshPlanAndContract($oldPlanId);
            $this->refreshPlanAndContract((int)$record->receivable_plan_id);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $record->toArray();
    }

    public function actionRecordDelete(): array
    {
        $record = $this->findRecord((int)Yii::$app->request->post('id', 0));
        $planId = (int)$record->receivable_plan_id;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $record->markDeleted();
            $record->save(false);
            $this->refreshPlanAndContract($planId);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return ['deleted' => true, 'id' => (int)$record->id];
    }

    private function loadPlan(ReceivablePlan $plan): void
    {
        $post = Yii::$app->request->post();
        $contract = Contract::findOne(['id' => (int)($post['contract_id'] ?? 0), 'deleted' => 0]);
        if ($contract === null) {
            throw new BadRequestHttpException('请选择合同');
        }

        $planNo = trim((string)($post['plan_no'] ?? ''));
        $plan->plan_no = $planNo !== '' ? $planNo : ($plan->plan_no ?: $this->generatePlanNo());
        $plan->contract_id = (int)$contract->id;
        $plan->customer_id = (int)$contract->customer_id;
        $ownerUserId = (int)($post['owner_user_id'] ?? 0);
        $plan->owner_user_id = $ownerUserId > 0 ? $ownerUserId : (int)$contract->owner_user_id;
        $plan->plan_name = trim((string)($post['plan_name'] ?? '')) ?: $contract->contract_name . '-回款计划';
        $plan->plan_date = trim((string)($post['plan_date'] ?? '')) ?: null;
        $plan->plan_amount = max(0, (float)($post['plan_amount'] ?? 0));
        $plan->invoice_amount = max(0, (float)($post['invoice_amount'] ?? $plan->invoice_amount));
        $plan->status = (int)($post['status'] ?? ReceivablePlan::STATUS_PENDING);
        $plan->settlement_status = (int)($post['settlement_status'] ?? $plan->settlement_status);
        $plan->remark = trim((string)($post['remark'] ?? ''));
        $plan->pending_amount = max(0, round((float)$plan->plan_amount - (float)$plan->received_amount, 2));

        if ($plan->plan_amount <= 0) {
            throw new BadRequestHttpException('计划金额必须大于 0');
        }
        if ($plan->owner_user_id <= 0 || !User::find()->where(['id' => $plan->owner_user_id, 'status' => User::STATUS_ACTIVE])->exists()) {
            throw new BadRequestHttpException('请选择有效负责人');
        }
        if (!in_array($plan->status, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('计划状态不正确');
        }

        $exists = ReceivablePlan::find()
            ->where(['plan_no' => $plan->plan_no])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$plan->id])
            ->exists();
        if ($exists) {
            throw new BadRequestHttpException('计划编号已存在');
        }
    }

    private function loadRecord(ReceivableRecord $record): void
    {
        $post = Yii::$app->request->post();
        $plan = $this->findPlan((int)($post['receivable_plan_id'] ?? 0));
        if ((int)$plan->status === ReceivablePlan::STATUS_CANCELLED) {
            throw new BadRequestHttpException('已作废计划不能登记回款');
        }
        $contractId = (int)($post['contract_id'] ?? 0);
        if ($contractId > 0 && $contractId !== (int)$plan->contract_id) {
            throw new BadRequestHttpException('回款计划与合同不匹配');
        }

        $recordNo = trim((string)($post['record_no'] ?? ''));
        $record->record_no = $recordNo !== '' ? $recordNo : ($record->record_no ?: $this->generateRecordNo());
        $record->receivable_plan_id = (int)$plan->id;
        $record->contract_id = (int)$plan->contract_id;
        $record->customer_id = (int)$plan->customer_id;
        $ownerUserId = (int)($post['owner_user_id'] ?? 0);
        $record->owner_user_id = $ownerUserId > 0 ? $ownerUserId : (int)$plan->owner_user_id;
        $record->receipt_date = trim((string)($post['receipt_date'] ?? '')) ?: date('Y-m-d');
        $record->receipt_amount = max(0, (float)($post['receipt_amount'] ?? 0));
        $record->receipt_method = trim((string)($post['receipt_method'] ?? ''));
        $record->receipt_account = trim((string)($post['receipt_account'] ?? ''));
        $record->payer_name = trim((string)($post['payer_name'] ?? ''));
        $record->bank_serial_no = trim((string)($post['bank_serial_no'] ?? ''));
        $record->status = (int)($post['status'] ?? ReceivableRecord::STATUS_VALID);
        $record->writeoff_status = (int)($post['writeoff_status'] ?? 1);
        $record->remark = trim((string)($post['remark'] ?? ''));

        if ($record->receipt_amount <= 0) {
            throw new BadRequestHttpException('回款金额必须大于 0');
        }
        if ($record->owner_user_id <= 0 || !User::find()->where(['id' => $record->owner_user_id, 'status' => User::STATUS_ACTIVE])->exists()) {
            throw new BadRequestHttpException('请选择有效负责人');
        }
        if (!in_array($record->status, [1, 2], true)) {
            throw new BadRequestHttpException('回款记录状态不正确');
        }

        $exists = ReceivableRecord::find()
            ->where(['record_no' => $record->record_no])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$record->id])
            ->exists();
        if ($exists) {
            throw new BadRequestHttpException('回款记录编号已存在');
        }
    }

    private function refreshPlanAndContract(int $planId): void
    {
        $plan = ReceivablePlan::findOne(['id' => $planId, 'deleted' => 0]);
        if ($plan === null) {
            return;
        }

        $received = (float)ReceivableRecord::find()
            ->where([
                'receivable_plan_id' => $plan->id,
                'deleted' => 0,
                'status' => ReceivableRecord::STATUS_VALID,
            ])
            ->sum('receipt_amount');

        $plan->received_amount = round($received, 2);
        $plan->pending_amount = max(0, round((float)$plan->plan_amount - $received, 2));
        if ((float)$plan->received_amount <= 0) {
            $plan->status = ReceivablePlan::STATUS_PENDING;
            $plan->settlement_status = 0;
        } elseif ((float)$plan->received_amount + 0.00001 < (float)$plan->plan_amount) {
            $plan->status = ReceivablePlan::STATUS_PARTIAL;
            $plan->settlement_status = 0;
        } else {
            $plan->status = ReceivablePlan::STATUS_RECEIVED;
            $plan->settlement_status = 1;
        }
        $plan->save(false);

        $contract = Contract::findOne(['id' => $plan->contract_id, 'deleted' => 0]);
        if ($contract === null) {
            return;
        }

        $contractReceived = (float)ReceivableRecord::find()
            ->where([
                'contract_id' => $contract->id,
                'deleted' => 0,
                'status' => ReceivableRecord::STATUS_VALID,
            ])
            ->sum('receipt_amount');
        $contract->received_amount = round($contractReceived, 2);
        $contract->pending_amount = max(0, round((float)$contract->final_amount - $contractReceived, 2));
        if ((float)$contract->pending_amount <= 0 && (float)$contract->final_amount > 0) {
            $contract->status = Contract::STATUS_COMPLETED;
        } elseif ((float)$contract->received_amount > 0 && (int)$contract->status === Contract::STATUS_DRAFT) {
            $contract->status = Contract::STATUS_EXECUTING;
        }
        $contract->save(false);
    }

    private function findPlan(int $id): ReceivablePlan
    {
        $model = ReceivablePlan::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('回款计划不存在');
        }

        return $model;
    }

    private function findRecord(int $id): ReceivableRecord
    {
        $model = ReceivableRecord::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('回款记录不存在');
        }

        return $model;
    }

    private function serializePlanArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'plan_no' => (string)($row['plan_no'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'plan_name' => (string)($row['plan_name'] ?? ''),
            'plan_date' => $row['plan_date'] ?? null,
            'plan_amount' => (string)($row['plan_amount'] ?? '0.00'),
            'received_amount' => (string)($row['received_amount'] ?? '0.00'),
            'pending_amount' => (string)($row['pending_amount'] ?? '0.00'),
            'invoice_amount' => (string)($row['invoice_amount'] ?? '0.00'),
            'status' => (int)($row['status'] ?? 1),
            'settlement_status' => (int)($row['settlement_status'] ?? 0),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function serializeRecordArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'record_no' => (string)($row['record_no'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'receivable_plan_id' => (int)($row['receivable_plan_id'] ?? 0),
            'plan_no' => (string)($row['plan_no'] ?? ''),
            'plan_name' => (string)($row['plan_name'] ?? ''),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'receipt_date' => $row['receipt_date'] ?? null,
            'receipt_amount' => (string)($row['receipt_amount'] ?? '0.00'),
            'receipt_method' => (string)($row['receipt_method'] ?? ''),
            'receipt_account' => (string)($row['receipt_account'] ?? ''),
            'payer_name' => (string)($row['payer_name'] ?? ''),
            'bank_serial_no' => (string)($row['bank_serial_no'] ?? ''),
            'status' => (int)($row['status'] ?? 1),
            'writeoff_status' => (int)($row['writeoff_status'] ?? 0),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function getContractOptions(): array
    {
        $rows = Contract::find()
            ->select(['id', 'contract_no', 'contract_name', 'customer_id', 'owner_user_id', 'final_amount', 'pending_amount'])
            ->where(['deleted' => 0])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'contract_no' => (string)$row['contract_no'],
            'contract_name' => (string)$row['contract_name'],
            'customer_id' => (int)$row['customer_id'],
            'owner_user_id' => (int)$row['owner_user_id'],
            'final_amount' => (string)$row['final_amount'],
            'pending_amount' => (string)$row['pending_amount'],
        ], $rows);
    }

    private function getCustomerOptions(): array
    {
        $rows = Customer::find()
            ->select(['id', 'customer_name', 'customer_code'])
            ->where(['deleted' => 0])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'customer_name' => (string)$row['customer_name'],
            'customer_code' => (string)$row['customer_code'],
        ], $rows);
    }

    private function getUserOptions(): array
    {
        $rows = User::find()
            ->select(['id', 'username', 'real_name', 'mobile'])
            ->where(['status' => User::STATUS_ACTIVE])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'name' => (string)($row['real_name'] ?: $row['username']),
            'mobile' => (string)($row['mobile'] ?? ''),
        ], $rows);
    }

    private function generatePlanNo(): string
    {
        return $this->generateNo('HKJH', ReceivablePlan::class, 'plan_no');
    }

    private function generateRecordNo(): string
    {
        return $this->generateNo('HK', ReceivableRecord::class, 'record_no');
    }

    /**
     * @param class-string<ReceivablePlan|ReceivableRecord> $class
     */
    private function generateNo(string $prefix, string $class, string $field): string
    {
        $base = $prefix . date('Ymd');
        for ($i = 0; $i < 50; $i++) {
            $code = $base . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!$class::find()->where([$field => $code])->exists()) {
                return $code;
            }
        }

        return $base . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstPlanError(ReceivablePlan $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }

    private function firstRecordError(ReceivableRecord $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }
}
