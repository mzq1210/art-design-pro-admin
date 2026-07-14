<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\AdProduct;
use common\models\Contract;
use common\models\ContractProduct;
use common\models\Customer;
use common\models\Fulfillment;
use common\models\FulfillmentExecution;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class FulfillmentController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'fulfillment.view',
        'select-options' => 'fulfillment.view',
        'view' => 'fulfillment.view',
        'executions' => 'fulfillment.view',
        'create' => 'fulfillment.create',
        'update' => 'fulfillment.update',
        'delete' => 'fulfillment.delete',
        'create-execution' => 'fulfillment.execute',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $fulfillmentNo = trim((string)Yii::$app->request->post('fulfillment_no', ''));
        $customerId = (int)Yii::$app->request->post('customer_id', 0);
        $productId = (int)Yii::$app->request->post('product_id', 0);
        $ownerUserId = (int)Yii::$app->request->post('owner_user_id', 0);
        $status = (int)Yii::$app->request->post('status', 0);
        $settlementStatus = Yii::$app->request->post('settlement_status', null);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = Fulfillment::find()
            ->alias('f')
            ->select([
                'f.*',
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'product_name' => new Expression("COALESCE(p.product_name, '')"),
                'product_code' => new Expression("COALESCE(p.product_code, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
                'completed_by_name' => new Expression("COALESCE(NULLIF(cu.real_name, ''), cu.username, '')"),
            ])
            ->leftJoin(['c' => Customer::tableName()], 'c.id = f.customer_id AND c.deleted = 0')
            ->leftJoin(['p' => AdProduct::tableName()], 'p.id = f.product_id AND p.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = f.owner_user_id')
            ->leftJoin(['cu' => User::tableName()], 'cu.id = f.completed_by')
            ->where(['f.deleted' => 0]);

        if ($fulfillmentNo !== '') {
            $query->andWhere(['like', 'f.fulfillment_no', $fulfillmentNo]);
        }

        if ($customerId > 0) {
            $query->andWhere(['f.customer_id' => $customerId]);
        }

        if ($productId > 0) {
            $query->andWhere(['f.product_id' => $productId]);
        }

        if ($ownerUserId > 0) {
            $query->andWhere(['f.owner_user_id' => $ownerUserId]);
        }

        if ($status > 0) {
            $query->andWhere(['f.status' => $status]);
        }

        if ($settlementStatus !== null && $settlementStatus !== '') {
            $query->andWhere(['f.settlement_status' => (int)$settlementStatus]);
        }

        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'f.fulfillment_no', $keyword],
                ['like', 'f.external_ref', $keyword],
                ['like', 'f.content_summary', $keyword],
                ['like', 'f.result_summary', $keyword],
                ['like', 'c.customer_name', $keyword],
                ['like', 'p.product_name', $keyword],
            ]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['f.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeFulfillmentArray'], $records),
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
            'products' => $this->getProductOptions(),
            'contracts' => $this->getContractOptions(),
            'contractProducts' => $this->getContractProductOptions(),
            'users' => $this->getUserOptions(),
        ];
    }

    public function actionView(): array
    {
        return $this->findFulfillment((int)Yii::$app->request->post('id', 0))->toArray();
    }

    public function actionExecutions(): array
    {
        $fulfillmentId = (int)Yii::$app->request->post('fulfillment_id', 0);
        $this->findFulfillment($fulfillmentId);

        $rows = FulfillmentExecution::find()
            ->alias('e')
            ->select([
                'e.*',
                'executor_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
            ])
            ->leftJoin(['u' => User::tableName()], 'u.id = e.executor_id')
            ->where(['e.fulfillment_id' => $fulfillmentId, 'e.deleted' => 0])
            ->orderBy(['e.execute_date' => SORT_DESC, 'e.id' => SORT_DESC])
            ->asArray()
            ->all();

        return array_map([$this, 'serializeExecutionArray'], $rows);
    }

    public function actionCreate(): array
    {
        $model = new Fulfillment();
        $this->loadFulfillment($model);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function actionUpdate(): array
    {
        $model = $this->findFulfillment((int)Yii::$app->request->post('id', 0));
        $this->loadFulfillment($model);

        if (!$model->save()) {
            throw new BadRequestHttpException($this->firstError($model));
        }

        return $model->toArray();
    }

    public function actionDelete(): array
    {
        $model = $this->findFulfillment((int)Yii::$app->request->post('id', 0));
        $executionCount = (int)FulfillmentExecution::find()
            ->where(['fulfillment_id' => $model->id, 'deleted' => 0])
            ->count();

        if ($executionCount > 0) {
            throw new BadRequestHttpException('已有执行记录的履约任务不能删除');
        }

        $model->markDeleted();

        if (!$model->save(false)) {
            throw new BadRequestHttpException('删除履约任务失败');
        }

        return [
            'deleted' => true,
            'id' => (int)$model->id,
        ];
    }

    public function actionCreateExecution(): array
    {
        $fulfillment = $this->findFulfillment((int)Yii::$app->request->post('fulfillment_id', 0));
        if ((int)$fulfillment->status === Fulfillment::STATUS_CANCELLED) {
            throw new BadRequestHttpException('已作废的履约任务不能登记执行');
        }

        $execution = new FulfillmentExecution();
        $this->loadExecution($execution, $fulfillment);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$execution->save()) {
                throw new BadRequestHttpException($this->firstError($execution));
            }

            $this->refreshFulfillmentProgress($fulfillment);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $fulfillment->toArray();
    }

    private function loadFulfillment(Fulfillment $model): void
    {
        $post = Yii::$app->request->post();
        $fulfillmentNo = trim((string)($post['fulfillment_no'] ?? ''));
        $contractProduct = $this->findContractProduct((int)($post['contract_product_id'] ?? 0));
        $contract = Contract::findOne(['id' => (int)$contractProduct->contract_id, 'deleted' => 0]);
        if ($contract === null) {
            throw new BadRequestHttpException('合同不存在');
        }

        $model->fulfillment_no = $fulfillmentNo !== '' ? $fulfillmentNo : ($model->fulfillment_no ?: $this->generateFulfillmentNo());
        $model->contract_id = (int)$contractProduct->contract_id;
        $model->contract_product_id = (int)$contractProduct->id;
        $model->customer_id = (int)$contract->customer_id;
        $model->product_id = (int)$contractProduct->product_id;
        $model->owner_user_id = (int)($post['owner_user_id'] ?? 0);
        $model->plan_date = trim((string)($post['plan_date'] ?? '')) ?: null;
        $model->fulfillment_date = trim((string)($post['fulfillment_date'] ?? '')) ?: $model->fulfillment_date;
        $model->execute_quantity = max(0, (float)($post['execute_quantity'] ?? 0));
        $model->unit_price = max(0, (float)($post['unit_price'] ?? $contractProduct->sale_price));
        if ((float)$model->unit_price <= 0) {
            $model->unit_price = (float)$contractProduct->sale_price;
        }
        $model->execute_amount = round((float)$model->execute_quantity * (float)$model->unit_price, 2);
        $model->status = (int)($post['status'] ?? Fulfillment::STATUS_PENDING);
        $model->settlement_status = (int)($post['settlement_status'] ?? 0);
        $model->external_ref = trim((string)($post['external_ref'] ?? ''));
        $model->content_summary = trim((string)($post['content_summary'] ?? ''));
        $model->result_summary = trim((string)($post['result_summary'] ?? ''));
        $model->remark = trim((string)($post['remark'] ?? ''));

        if ($model->customer_id <= 0) {
            throw new BadRequestHttpException('请选择客户');
        }

        if ($model->product_id <= 0) {
            throw new BadRequestHttpException('请选择产品');
        }

        if ($model->owner_user_id <= 0) {
            throw new BadRequestHttpException('请选择履约负责人');
        }

        if ($model->execute_quantity <= 0) {
            throw new BadRequestHttpException('计划履约数量必须大于 0');
        }

        if ((float)$contractProduct->quantity > 0 && (float)$model->execute_quantity > (float)$contractProduct->quantity) {
            throw new BadRequestHttpException('计划履约数量不能超过合同产品数量');
        }

        if (!in_array($model->status, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('履约状态不正确');
        }

        if (!in_array($model->settlement_status, [0, 1], true)) {
            throw new BadRequestHttpException('结算状态不正确');
        }

        $this->assertOptionsExist($model->customer_id, $model->product_id, $model->owner_user_id);

        $exists = Fulfillment::find()
            ->where(['fulfillment_no' => $model->fulfillment_no])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$model->id])
            ->exists();

        if ($exists) {
            throw new BadRequestHttpException('履约单号已存在');
        }
    }

    private function loadExecution(FulfillmentExecution $execution, Fulfillment $fulfillment): void
    {
        $post = Yii::$app->request->post();

        $execution->fulfillment_id = (int)$fulfillment->id;
        $execution->contract_id = (int)$fulfillment->contract_id;
        $execution->contract_product_id = (int)$fulfillment->contract_product_id;
        $execution->customer_id = (int)$fulfillment->customer_id;
        $execution->product_id = (int)$fulfillment->product_id;
        $execution->executor_id = (int)($post['executor_id'] ?? (Yii::$app->user->id ?: 0));
        $execution->execute_date = trim((string)($post['execute_date'] ?? '')) ?: date('Y-m-d');
        $execution->execute_quantity = max(0, (float)($post['execute_quantity'] ?? 0));
        $execution->unit_price = max(0, (float)($post['unit_price'] ?? $fulfillment->unit_price));
        $execution->execute_amount = round((float)$execution->execute_quantity * (float)$execution->unit_price, 2);
        $execution->external_ref = trim((string)($post['external_ref'] ?? $fulfillment->external_ref));
        $execution->content_summary = trim((string)($post['content_summary'] ?? $fulfillment->content_summary));
        $execution->result_summary = trim((string)($post['result_summary'] ?? ''));
        $execution->remark = trim((string)($post['remark'] ?? ''));

        if ($execution->executor_id <= 0) {
            throw new BadRequestHttpException('请选择执行人');
        }

        if ($execution->execute_quantity <= 0) {
            throw new BadRequestHttpException('本次执行数量必须大于 0');
        }

        if (!User::find()->where(['id' => $execution->executor_id, 'status' => User::STATUS_ACTIVE])->exists()) {
            throw new BadRequestHttpException('执行人不存在或已禁用');
        }
    }

    private function refreshFulfillmentProgress(Fulfillment $fulfillment): void
    {
        $summary = FulfillmentExecution::find()
            ->select([
                'quantity' => new Expression('COALESCE(SUM(execute_quantity), 0)'),
                'amount' => new Expression('COALESCE(SUM(execute_amount), 0)'),
                'last_date' => new Expression('MAX(execute_date)'),
            ])
            ->where(['fulfillment_id' => $fulfillment->id, 'deleted' => 0])
            ->asArray()
            ->one();

        $lastExecution = FulfillmentExecution::find()
            ->where(['fulfillment_id' => $fulfillment->id, 'deleted' => 0])
            ->orderBy(['execute_date' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        $executedQuantity = (float)($summary['quantity'] ?? 0);
        $fulfillment->executed_quantity = $executedQuantity;
        $fulfillment->executed_amount = (float)($summary['amount'] ?? 0);
        $fulfillment->fulfillment_date = $summary['last_date'] ?: null;
        $fulfillment->completed_by = $lastExecution ? (int)$lastExecution->executor_id : 0;

        if ($executedQuantity <= 0) {
            $fulfillment->status = Fulfillment::STATUS_PENDING;
        } elseif ($executedQuantity + 0.00001 < (float)$fulfillment->execute_quantity) {
            $fulfillment->status = Fulfillment::STATUS_EXECUTING;
        } else {
            $fulfillment->status = Fulfillment::STATUS_COMPLETED;
        }

        if (!$fulfillment->save(false)) {
            throw new BadRequestHttpException('刷新履约进度失败');
        }

        $this->refreshContractProductProgress((int)$fulfillment->contract_product_id);
    }

    private function refreshContractProductProgress(int $contractProductId): void
    {
        if ($contractProductId <= 0) {
            return;
        }

        $contractProduct = ContractProduct::findOne(['id' => $contractProductId, 'deleted' => 0]);
        if ($contractProduct === null) {
            return;
        }

        $executedQuantity = (float)FulfillmentExecution::find()
            ->where([
                'contract_product_id' => $contractProductId,
                'deleted' => 0,
            ])
            ->sum('execute_quantity');

        $contractProduct->executed_quantity = round($executedQuantity, 2);
        $contractProduct->save(false);
    }

    private function findFulfillment(int $id): Fulfillment
    {
        $model = Fulfillment::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('履约任务不存在');
        }

        return $model;
    }

    private function serializeFulfillmentArray(array $row): array
    {
        $executeQuantity = (float)($row['execute_quantity'] ?? 0);
        $executedQuantity = (float)($row['executed_quantity'] ?? 0);

        return [
            'id' => (int)($row['id'] ?? 0),
            'fulfillment_no' => (string)($row['fulfillment_no'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_product_id' => (int)($row['contract_product_id'] ?? 0),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'product_id' => (int)($row['product_id'] ?? 0),
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'completed_by' => (int)($row['completed_by'] ?? 0),
            'completed_by_name' => (string)($row['completed_by_name'] ?? ''),
            'plan_date' => $row['plan_date'] ?? null,
            'fulfillment_date' => $row['fulfillment_date'] ?? null,
            'execute_quantity' => (string)($row['execute_quantity'] ?? '0.00'),
            'unit_price' => (string)($row['unit_price'] ?? '0.00'),
            'execute_amount' => (string)($row['execute_amount'] ?? '0.00'),
            'executed_quantity' => (string)($row['executed_quantity'] ?? '0.00'),
            'executed_amount' => (string)($row['executed_amount'] ?? '0.00'),
            'remaining_quantity' => (string)max(0, round($executeQuantity - $executedQuantity, 2)),
            'status' => (int)($row['status'] ?? Fulfillment::STATUS_PENDING),
            'settlement_status' => (int)($row['settlement_status'] ?? 0),
            'external_ref' => (string)($row['external_ref'] ?? ''),
            'content_summary' => (string)($row['content_summary'] ?? ''),
            'result_summary' => (string)($row['result_summary'] ?? ''),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function serializeExecutionArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'fulfillment_id' => (int)($row['fulfillment_id'] ?? 0),
            'executor_id' => (int)($row['executor_id'] ?? 0),
            'executor_name' => (string)($row['executor_name'] ?? ''),
            'execute_date' => $row['execute_date'] ?? null,
            'execute_quantity' => (string)($row['execute_quantity'] ?? '0.00'),
            'unit_price' => (string)($row['unit_price'] ?? '0.00'),
            'execute_amount' => (string)($row['execute_amount'] ?? '0.00'),
            'external_ref' => (string)($row['external_ref'] ?? ''),
            'content_summary' => (string)($row['content_summary'] ?? ''),
            'result_summary' => (string)($row['result_summary'] ?? ''),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function assertOptionsExist(int $customerId, int $productId, int $ownerUserId): void
    {
        if (!Customer::find()->where(['id' => $customerId, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('客户不存在');
        }

        if (!AdProduct::find()->where(['id' => $productId, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('产品不存在');
        }

        if (!User::find()->where(['id' => $ownerUserId, 'status' => User::STATUS_ACTIVE])->exists()) {
            throw new BadRequestHttpException('履约负责人不存在或已禁用');
        }
    }

    private function findContractProduct(int $id): ContractProduct
    {
        $model = ContractProduct::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new BadRequestHttpException('请选择合同产品');
        }

        return $model;
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
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_code' => (string)($row['customer_code'] ?? ''),
        ], $rows);
    }

    private function getProductOptions(): array
    {
        $rows = AdProduct::find()
            ->select(['id', 'product_name', 'product_code', 'sale_price'])
            ->where(['deleted' => 0, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
            'sale_price' => (string)($row['sale_price'] ?? '0.00'),
        ], $rows);
    }

    private function getContractOptions(): array
    {
        $rows = Contract::find()
            ->select(['id', 'contract_no', 'contract_name', 'customer_id', 'owner_user_id'])
            ->where(['deleted' => 0])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
        ], $rows);
    }

    private function getContractProductOptions(): array
    {
        $rows = ContractProduct::find()
            ->alias('cp')
            ->select([
                'cp.*',
                'contract_no' => new Expression("COALESCE(ct.contract_no, '')"),
                'contract_name' => new Expression("COALESCE(ct.contract_name, '')"),
                'customer_id' => new Expression('COALESCE(ct.customer_id, 0)'),
                'owner_user_id' => new Expression('COALESCE(ct.owner_user_id, 0)'),
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'product_code' => new Expression("COALESCE(p.product_code, '')"),
            ])
            ->leftJoin(['ct' => Contract::tableName()], 'ct.id = cp.contract_id AND ct.deleted = 0')
            ->leftJoin(['c' => Customer::tableName()], 'c.id = ct.customer_id AND c.deleted = 0')
            ->leftJoin(['p' => AdProduct::tableName()], 'p.id = cp.product_id AND p.deleted = 0')
            ->where(['cp.deleted' => 0])
            ->andWhere(['not', ['ct.id' => null]])
            ->orderBy(['cp.id' => SORT_DESC])
            ->limit(1000)
            ->asArray()
            ->all();

        return array_map(static function (array $row): array {
            $quantity = (float)($row['quantity'] ?? 0);
            $executedQuantity = (float)($row['executed_quantity'] ?? 0);

            return [
                'id' => (int)$row['id'],
                'contract_id' => (int)$row['contract_id'],
                'contract_no' => (string)($row['contract_no'] ?? ''),
                'contract_name' => (string)($row['contract_name'] ?? ''),
                'customer_id' => (int)($row['customer_id'] ?? 0),
                'customer_name' => (string)($row['customer_name'] ?? ''),
                'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
                'product_id' => (int)$row['product_id'],
                'product_name' => (string)($row['product_name'] ?? ''),
                'product_code' => (string)($row['product_code'] ?? ''),
                'media_name' => (string)($row['media_name'] ?? ''),
                'ad_type' => (string)($row['ad_type'] ?? ''),
                'unit' => (string)($row['unit'] ?? ''),
                'sale_price' => (string)($row['sale_price'] ?? '0.00'),
                'quantity' => (string)($row['quantity'] ?? '0.00'),
                'executed_quantity' => (string)($row['executed_quantity'] ?? '0.00'),
                'remaining_quantity' => (string)max(0, round($quantity - $executedQuantity, 2)),
            ];
        }, $rows);
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

    private function generateFulfillmentNo(): string
    {
        $prefix = 'LY' . date('Ymd');

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!Fulfillment::find()->where(['fulfillment_no' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstError(Fulfillment|FulfillmentExecution $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }
}
