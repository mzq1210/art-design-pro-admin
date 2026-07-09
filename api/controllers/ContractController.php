<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\AdProduct;
use common\models\Contract;
use common\models\ContractCost;
use common\models\ContractProduct;
use common\models\Customer;
use common\models\Fulfillment;
use common\models\ReceivablePlan;
use common\models\ReceivableRecord;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ContractController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'contract.view',
        'select-options' => 'contract.view',
        'view' => 'contract.view',
        'products' => 'contract.view',
        'create' => 'contract.create',
        'update' => 'contract.update',
        'delete' => 'contract.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $contractNo = trim((string)Yii::$app->request->post('contract_no', ''));
        $customerId = (int)Yii::$app->request->post('customer_id', 0);
        $ownerUserId = (int)Yii::$app->request->post('owner_user_id', 0);
        $contractType = (int)Yii::$app->request->post('contract_type', 0);
        $status = (int)Yii::$app->request->post('status', 0);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = Contract::find()
            ->alias('ct')
            ->select([
                'ct.*',
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'customer_code' => new Expression("COALESCE(c.customer_code, '')"),
                'owner_name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
                'parent_contract_name' => new Expression("COALESCE(p.contract_name, '')"),
            ])
            ->leftJoin(['c' => Customer::tableName()], 'c.id = ct.customer_id AND c.deleted = 0')
            ->leftJoin(['u' => User::tableName()], 'u.id = ct.owner_user_id')
            ->leftJoin(['p' => Contract::tableName()], 'p.id = ct.parent_contract_id AND p.deleted = 0')
            ->where(['ct.deleted' => 0]);

        if ($contractNo !== '') {
            $query->andWhere(['like', 'ct.contract_no', $contractNo]);
        }
        if ($customerId > 0) {
            $query->andWhere(['ct.customer_id' => $customerId]);
        }
        if ($ownerUserId > 0) {
            $query->andWhere(['ct.owner_user_id' => $ownerUserId]);
        }
        if ($contractType > 0) {
            $query->andWhere(['ct.contract_type' => $contractType]);
        }
        if ($status > 0) {
            $query->andWhere(['ct.status' => $status]);
        }
        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'ct.contract_no', $keyword],
                ['like', 'ct.contract_name', $keyword],
                ['like', 'c.customer_name', $keyword],
            ]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['ct.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeContractArray'], $records),
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
            'users' => $this->getUserOptions(),
            'framework_contracts' => $this->getFrameworkContractOptions(),
        ];
    }

    public function actionView(): array
    {
        return $this->serializeContract($this->findContract((int)Yii::$app->request->post('id', 0)));
    }

    public function actionProducts(): array
    {
        $contract = $this->findContract((int)Yii::$app->request->post('contract_id', 0));
        return array_map([$this, 'serializeProductArray'], $contract->products);
    }

    public function actionCreate(): array
    {
        $contract = new Contract();
        $rows = $this->loadContract($contract);
        $costRows = Yii::$app->request->post('costs', []);
        $planRows = Yii::$app->request->post('receivable_plans', []);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$contract->save()) {
                throw new BadRequestHttpException($this->firstError($contract));
            }
            $this->saveProducts($contract, $rows);
            $this->saveCosts($contract, is_array($costRows) ? array_values($costRows) : []);
            $this->refreshContractAmount($contract);
            if ((int)$contract->contract_type === Contract::TYPE_FRAMEWORK) {
                $this->clearReceivablePlans($contract);
            } else {
                $this->saveReceivablePlans($contract, is_array($planRows) ? array_values($planRows) : []);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->serializeContract($contract);
    }

    public function actionUpdate(): array
    {
        $contract = $this->findContract((int)Yii::$app->request->post('id', 0));
        $rows = $this->loadContract($contract);
        $costRows = Yii::$app->request->post('costs', []);
        $planRows = Yii::$app->request->post('receivable_plans', []);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$contract->save()) {
                throw new BadRequestHttpException($this->firstError($contract));
            }
            $this->saveProducts($contract, $rows);
            $this->saveCosts($contract, is_array($costRows) ? array_values($costRows) : []);
            $this->refreshContractAmount($contract);
            if ((int)$contract->contract_type === Contract::TYPE_FRAMEWORK) {
                $this->clearReceivablePlans($contract);
            } else {
                $this->saveReceivablePlans($contract, is_array($planRows) ? array_values($planRows) : []);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->serializeContract($contract);
    }

    public function actionDelete(): array
    {
        $contract = $this->findContract((int)Yii::$app->request->post('id', 0));
        if (ReceivablePlan::find()->where(['contract_id' => $contract->id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('已有回款计划的合同不能删除');
        }
        if (Fulfillment::find()->where(['contract_id' => $contract->id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('已有履约任务的合同不能删除');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($contract->products as $product) {
                $product->markDeleted();
                $product->save(false);
            }
            foreach ($contract->costs as $cost) {
                $cost->markDeleted();
                $cost->save(false);
            }
            $contract->markDeleted();
            $contract->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return ['deleted' => true, 'id' => (int)$contract->id];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadContract(Contract $contract): array
    {
        $post = Yii::$app->request->post();
        $contractNo = trim((string)($post['contract_no'] ?? ''));

        $contract->contract_no = $contractNo !== '' ? $contractNo : ($contract->contract_no ?: $this->generateContractNo());
        $contract->contract_name = trim((string)($post['contract_name'] ?? ''));
        $contract->customer_id = (int)($post['customer_id'] ?? 0);
        $contract->owner_user_id = (int)($post['owner_user_id'] ?? 0);
        $contract->contract_type = (int)($post['contract_type'] ?? 1);
        $contract->parent_contract_id = (int)($post['parent_contract_id'] ?? 0);
        $contract->sign_date = trim((string)($post['sign_date'] ?? '')) ?: null;
        $contract->start_date = trim((string)($post['start_date'] ?? '')) ?: null;
        $contract->end_date = trim((string)($post['end_date'] ?? '')) ?: null;
        $contract->discount_amount = max(0, (float)($post['discount_amount'] ?? 0));
        $contract->tax_rate = max(0, (float)($post['tax_rate'] ?? 0));
        $contract->invoice_amount = max(0, (float)($post['invoice_amount'] ?? $contract->invoice_amount));
        $contract->status = (int)($post['status'] ?? Contract::STATUS_DRAFT);
        $contract->approval_status = (int)($post['approval_status'] ?? $contract->approval_status);
        $contract->archive_status = (int)($post['archive_status'] ?? $contract->archive_status);
        $contract->framework_scope = trim((string)($post['framework_scope'] ?? ''));
        $contract->remark = trim((string)($post['remark'] ?? ''));
        if ((int)$contract->contract_type === Contract::TYPE_FRAMEWORK) {
            $contract->parent_contract_id = 0;
            $contract->discount_amount = 0;
            $contract->tax_rate = 0;
            $contract->invoice_amount = 0;
        }

        if ($contract->contract_name === '') {
            throw new BadRequestHttpException('请输入合同名称');
        }
        if ($contract->customer_id <= 0) {
            throw new BadRequestHttpException('请选择客户');
        }
        if ($contract->owner_user_id <= 0) {
            throw new BadRequestHttpException('请选择负责人');
        }
        if (!Customer::find()->where(['id' => $contract->customer_id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('客户不存在');
        }
        if (!User::find()->where(['id' => $contract->owner_user_id, 'status' => User::STATUS_ACTIVE])->exists()) {
            throw new BadRequestHttpException('负责人不存在或已禁用');
        }
        if (!in_array((int)$contract->contract_type, [Contract::TYPE_SALES, Contract::TYPE_FRAMEWORK, Contract::TYPE_SUPPLEMENT], true)) {
            throw new BadRequestHttpException('合同类型不正确');
        }
        if ((int)$contract->parent_contract_id > 0) {
            $parentContract = Contract::findOne([
                'id' => (int)$contract->parent_contract_id,
                'customer_id' => (int)$contract->customer_id,
                'contract_type' => Contract::TYPE_FRAMEWORK,
                'deleted' => 0,
            ]);
            if ($parentContract === null || (int)$parentContract->id === (int)$contract->id) {
                throw new BadRequestHttpException('关联框架协议不存在');
            }
        }
        if (!in_array($contract->status, [1, 2, 3, 4], true)) {
            throw new BadRequestHttpException('合同状态不正确');
        }

        $exists = Contract::find()
            ->where(['contract_no' => $contract->contract_no])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$contract->id])
            ->exists();
        if ($exists) {
            throw new BadRequestHttpException('合同编号已存在');
        }

        $rows = $post['products'] ?? [];
        if ((int)$contract->contract_type === Contract::TYPE_FRAMEWORK) {
            return [];
        }
        if (!is_array($rows) || $rows === []) {
            throw new BadRequestHttpException('请至少添加一个合同产品');
        }

        return array_values($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function saveProducts(Contract $contract, array $rows): void
    {
        $keepIds = [];
        foreach ($rows as $index => $row) {
            $id = (int)($row['id'] ?? 0);
            $productId = (int)($row['product_id'] ?? 0);
            $sourceProduct = AdProduct::findOne(['id' => $productId, 'deleted' => 0]);
            if ($sourceProduct === null) {
                throw new BadRequestHttpException('合同产品不存在');
            }

            $contractProduct = $id > 0
                ? ContractProduct::findOne(['id' => $id, 'contract_id' => $contract->id, 'deleted' => 0])
                : new ContractProduct();
            if ($contractProduct === null) {
                throw new BadRequestHttpException('合同产品明细不存在');
            }

            $quantity = max(0, (float)($row['quantity'] ?? 0));
            $salePrice = max(0, (float)($row['sale_price'] ?? $sourceProduct->sale_price));
            if ($quantity <= 0) {
                throw new BadRequestHttpException('合同产品数量必须大于 0');
            }

            $contractProduct->contract_id = (int)$contract->id;
            $contractProduct->product_id = $productId;
            $contractProduct->category_id = (int)$sourceProduct->category_id;
            $contractProduct->product_name = (string)$sourceProduct->product_name;
            $contractProduct->media_name = (string)$sourceProduct->media_name;
            $contractProduct->ad_type = (string)$sourceProduct->ad_type;
            $contractProduct->unit = (string)$sourceProduct->unit;
            $contractProduct->list_price = (float)$sourceProduct->list_price;
            $contractProduct->sale_price = $salePrice;
            $contractProduct->discount_rate = (float)$sourceProduct->list_price > 0 ? round($salePrice / (float)$sourceProduct->list_price * 100, 2) : 0;
            $contractProduct->quantity = $quantity;
            $contractProduct->amount = round($quantity * $salePrice, 2);
            $contractProduct->start_date = trim((string)($row['start_date'] ?? '')) ?: null;
            $contractProduct->end_date = trim((string)($row['end_date'] ?? '')) ?: null;
            $contractProduct->delivery_requirements = trim((string)($row['delivery_requirements'] ?? ''));
            $contractProduct->sort = (int)($row['sort'] ?? ($index + 1) * 10);

            if (!$contractProduct->save()) {
                throw new BadRequestHttpException($this->firstError($contractProduct));
            }
            $keepIds[] = (int)$contractProduct->id;
        }

        $oldProducts = ContractProduct::find()
            ->where(['contract_id' => $contract->id, 'deleted' => 0])
            ->andFilterWhere(['not in', 'id', $keepIds])
            ->all();
        foreach ($oldProducts as $oldProduct) {
            $oldProduct->markDeleted();
            $oldProduct->save(false);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function saveCosts(Contract $contract, array $rows): void
    {
        $keepIds = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $amount = round(max(0, (float)($row['amount'] ?? 0)), 2);
            $costType = trim((string)($row['cost_type'] ?? ''));
            $costDate = trim((string)($row['cost_date'] ?? ''));
            $contractProductId = (int)($row['contract_product_id'] ?? 0);

            if ($costType === '') {
                throw new BadRequestHttpException('请选择成本类型');
            }
            if ($amount <= 0) {
                throw new BadRequestHttpException('成本金额必须大于 0');
            }

            $contractProduct = null;
            if ($contractProductId > 0) {
                $contractProduct = ContractProduct::findOne([
                    'id' => $contractProductId,
                    'contract_id' => (int)$contract->id,
                    'deleted' => 0,
                ]);
                if ($contractProduct === null) {
                    throw new BadRequestHttpException('合同成本关联的产品不存在');
                }
            }

            $cost = $id > 0
                ? ContractCost::findOne(['id' => $id, 'contract_id' => $contract->id, 'deleted' => 0])
                : new ContractCost();
            if ($cost === null) {
                throw new BadRequestHttpException('合同成本不存在');
            }

            $cost->contract_id = (int)$contract->id;
            $cost->contract_product_id = $contractProductId;
            $cost->cost_date = $costDate !== '' ? $costDate : null;
            $cost->cost_type = $costType;
            $cost->product_name = $contractProduct ? (string)$contractProduct->product_name : trim((string)($row['product_name'] ?? ''));
            $cost->amount = $amount;
            $cost->reason = trim((string)($row['reason'] ?? ''));
            $cost->remark = trim((string)($row['remark'] ?? ''));

            if (!$cost->save()) {
                throw new BadRequestHttpException($this->firstContractCostError($cost));
            }
            $keepIds[] = (int)$cost->id;
        }

        $oldCosts = ContractCost::find()
            ->where(['contract_id' => $contract->id, 'deleted' => 0])
            ->andFilterWhere(['not in', 'id', $keepIds])
            ->all();
        foreach ($oldCosts as $oldCost) {
            $oldCost->markDeleted();
            $oldCost->save(false);
        }
    }

    private function refreshContractAmount(Contract $contract): void
    {
        $totalAmount = (float)ContractProduct::find()
            ->where(['contract_id' => $contract->id, 'deleted' => 0])
            ->sum('amount');
        $contract->total_amount = round($totalAmount, 2);
        $contract->tax_amount = round(max(0, $contract->total_amount - (float)$contract->discount_amount) * (float)$contract->tax_rate / 100, 2);
        $contract->final_amount = round(max(0, $contract->total_amount - (float)$contract->discount_amount) + (float)$contract->tax_amount, 2);
        $contract->pending_amount = max(0, round((float)$contract->final_amount - (float)$contract->received_amount, 2));
        if (!$contract->save(false)) {
            throw new BadRequestHttpException('刷新合同金额失败');
        }
    }

    private function clearReceivablePlans(Contract $contract): void
    {
        $plans = ReceivablePlan::find()
            ->where(['contract_id' => $contract->id, 'deleted' => 0])
            ->all();
        foreach ($plans as $plan) {
            if (ReceivableRecord::find()->where(['receivable_plan_id' => $plan->id, 'deleted' => 0])->exists()) {
                throw new BadRequestHttpException('已有回款记录的计划不能从框架协议中删除');
            }
            $plan->markDeleted();
            $plan->save(false);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function saveReceivablePlans(Contract $contract, array $rows): void
    {
        if ($rows === []) {
            throw new BadRequestHttpException('请至少添加一期回款计划');
        }

        $totalPlanAmount = 0.0;
        foreach ($rows as $index => $row) {
            $planName = trim((string)($row['plan_name'] ?? ''));
            $planDate = trim((string)($row['plan_date'] ?? ''));
            $planAmount = round(max(0, (float)($row['plan_amount'] ?? 0)), 2);
            $invoiceAmount = round(max(0, (float)($row['invoice_amount'] ?? 0)), 2);

            if ($planName === '') {
                throw new BadRequestHttpException('第 ' . ($index + 1) . ' 期回款计划名称不能为空');
            }
            if ($planDate === '') {
                throw new BadRequestHttpException('第 ' . ($index + 1) . ' 期回款计划日期不能为空');
            }
            if ($planAmount <= 0) {
                throw new BadRequestHttpException('第 ' . ($index + 1) . ' 期回款金额必须大于 0');
            }
            if ($invoiceAmount - $planAmount > 0.00001) {
                throw new BadRequestHttpException('第 ' . ($index + 1) . ' 期开票金额不能大于回款金额');
            }

            $totalPlanAmount += $planAmount;
        }

        if ($rows !== [] && abs($totalPlanAmount - (float)$contract->final_amount) > 0.01) {
            throw new BadRequestHttpException('回款计划总金额必须等于合同最终金额');
        }

        $keepIds = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $plan = $id > 0
                ? ReceivablePlan::findOne(['id' => $id, 'contract_id' => $contract->id, 'deleted' => 0])
                : new ReceivablePlan();
            if ($plan === null) {
                throw new BadRequestHttpException('回款计划不存在');
            }

            $receivedAmount = (float)$plan->received_amount;
            $planAmount = round(max(0, (float)($row['plan_amount'] ?? 0)), 2);
            $plan->plan_no = $plan->plan_no ?: $this->generateReceivablePlanNo();
            $plan->contract_id = (int)$contract->id;
            $plan->customer_id = (int)$contract->customer_id;
            $plan->owner_user_id = (int)$contract->owner_user_id;
            $plan->plan_name = trim((string)($row['plan_name'] ?? ''));
            $plan->plan_date = trim((string)($row['plan_date'] ?? '')) ?: null;
            $plan->plan_amount = $planAmount;
            $plan->invoice_amount = round(max(0, (float)($row['invoice_amount'] ?? 0)), 2);
            $plan->pending_amount = max(0, round($planAmount - $receivedAmount, 2));
            $plan->status = $receivedAmount <= 0
                ? ReceivablePlan::STATUS_PENDING
                : ($receivedAmount + 0.00001 < $planAmount ? ReceivablePlan::STATUS_PARTIAL : ReceivablePlan::STATUS_RECEIVED);
            $plan->settlement_status = $plan->status === ReceivablePlan::STATUS_RECEIVED ? 1 : 0;
            $plan->remark = trim((string)($row['remark'] ?? ''));

            if (!$plan->save()) {
                throw new BadRequestHttpException($this->firstReceivablePlanError($plan));
            }
            $keepIds[] = (int)$plan->id;
        }

        $oldPlans = ReceivablePlan::find()
            ->where(['contract_id' => $contract->id, 'deleted' => 0])
            ->andFilterWhere(['not in', 'id', $keepIds])
            ->all();
        foreach ($oldPlans as $oldPlan) {
            if (ReceivableRecord::find()->where(['receivable_plan_id' => $oldPlan->id, 'deleted' => 0])->exists()) {
                throw new BadRequestHttpException('已有回款记录的计划不能从合同中删除');
            }
            $oldPlan->markDeleted();
            $oldPlan->save(false);
        }
    }

    private function findContract(int $id): Contract
    {
        $model = Contract::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('合同不存在');
        }

        return $model;
    }

    private function serializeContract(Contract $contract): array
    {
        $row = $contract->toArray();
        $customer = $contract->customer;
        $owner = $contract->owner;
        $parentContract = $contract->parentContract;
        $row['customer_name'] = $customer->customer_name ?? '';
        $row['customer_code'] = $customer->customer_code ?? '';
        $row['owner_name'] = $owner ? ((string)$owner->real_name !== '' ? $owner->real_name : $owner->username) : '';
        $row['parent_contract_name'] = $parentContract->contract_name ?? '';
        $row['products'] = array_map([$this, 'serializeProductArray'], $contract->products);
        $row['costs'] = array_map([$this, 'serializeCostArray'], $contract->costs);
        $row['receivable_plans'] = array_map([$this, 'serializeReceivablePlanArray'], ReceivablePlan::find()
            ->where(['contract_id' => $contract->id, 'deleted' => 0])
            ->orderBy(['id' => SORT_ASC])
            ->all());

        return $this->serializeContractArray($row);
    }

    private function serializeContractArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'contract_no' => (string)($row['contract_no'] ?? ''),
            'contract_name' => (string)($row['contract_name'] ?? ''),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_code' => (string)($row['customer_code'] ?? ''),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_name' => (string)($row['owner_name'] ?? ''),
            'contract_type' => (int)($row['contract_type'] ?? 1),
            'parent_contract_id' => (int)($row['parent_contract_id'] ?? 0),
            'parent_contract_name' => (string)($row['parent_contract_name'] ?? ''),
            'sign_date' => $row['sign_date'] ?? null,
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'total_amount' => (string)($row['total_amount'] ?? '0.00'),
            'discount_amount' => (string)($row['discount_amount'] ?? '0.00'),
            'tax_rate' => (string)($row['tax_rate'] ?? '0.00'),
            'tax_amount' => (string)($row['tax_amount'] ?? '0.00'),
            'final_amount' => (string)($row['final_amount'] ?? '0.00'),
            'received_amount' => (string)($row['received_amount'] ?? '0.00'),
            'pending_amount' => (string)($row['pending_amount'] ?? '0.00'),
            'invoice_amount' => (string)($row['invoice_amount'] ?? '0.00'),
            'status' => (int)($row['status'] ?? 1),
            'approval_status' => (int)($row['approval_status'] ?? 0),
            'archive_status' => (int)($row['archive_status'] ?? 0),
            'framework_scope' => (string)($row['framework_scope'] ?? ''),
            'remark' => (string)($row['remark'] ?? ''),
            'products' => $row['products'] ?? [],
            'costs' => $row['costs'] ?? [],
            'receivable_plans' => $row['receivable_plans'] ?? [],
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function serializeProductArray(ContractProduct|array $row): array
    {
        if ($row instanceof ContractProduct) {
            $row = $row->toArray();
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'product_id' => (int)($row['product_id'] ?? 0),
            'category_id' => (int)($row['category_id'] ?? 0),
            'product_name' => (string)($row['product_name'] ?? ''),
            'media_name' => (string)($row['media_name'] ?? ''),
            'ad_type' => (string)($row['ad_type'] ?? ''),
            'unit' => (string)($row['unit'] ?? ''),
            'list_price' => (string)($row['list_price'] ?? '0.00'),
            'sale_price' => (string)($row['sale_price'] ?? '0.00'),
            'discount_rate' => (string)($row['discount_rate'] ?? '0.00'),
            'quantity' => (string)($row['quantity'] ?? '0.00'),
            'executed_quantity' => (string)($row['executed_quantity'] ?? '0.00'),
            'amount' => (string)($row['amount'] ?? '0.00'),
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'delivery_requirements' => (string)($row['delivery_requirements'] ?? ''),
            'sort' => (int)($row['sort'] ?? 0),
        ];
    }

    private function serializeCostArray(ContractCost|array $row): array
    {
        if ($row instanceof ContractCost) {
            $row = $row->toArray();
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'contract_product_id' => (int)($row['contract_product_id'] ?? 0),
            'cost_date' => $row['cost_date'] ?? null,
            'cost_type' => (string)($row['cost_type'] ?? ''),
            'product_name' => (string)($row['product_name'] ?? ''),
            'amount' => (string)($row['amount'] ?? '0.00'),
            'reason' => (string)($row['reason'] ?? ''),
            'remark' => (string)($row['remark'] ?? ''),
        ];
    }

    private function serializeReceivablePlanArray(ReceivablePlan|array $row): array
    {
        if ($row instanceof ReceivablePlan) {
            $row = $row->toArray();
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'plan_no' => (string)($row['plan_no'] ?? ''),
            'plan_name' => (string)($row['plan_name'] ?? ''),
            'plan_date' => $row['plan_date'] ?? null,
            'plan_amount' => (string)($row['plan_amount'] ?? '0.00'),
            'received_amount' => (string)($row['received_amount'] ?? '0.00'),
            'pending_amount' => (string)($row['pending_amount'] ?? '0.00'),
            'invoice_amount' => (string)($row['invoice_amount'] ?? '0.00'),
            'status' => (int)($row['status'] ?? ReceivablePlan::STATUS_PENDING),
            'settlement_status' => (int)($row['settlement_status'] ?? 0),
            'remark' => (string)($row['remark'] ?? ''),
        ];
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

    private function getProductOptions(): array
    {
        $rows = AdProduct::find()
            ->select(['id', 'category_id', 'product_name', 'product_code', 'media_name', 'ad_type', 'unit', 'list_price', 'sale_price'])
            ->where(['deleted' => 0, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'category_id' => (int)$row['category_id'],
            'product_name' => (string)$row['product_name'],
            'product_code' => (string)$row['product_code'],
            'media_name' => (string)$row['media_name'],
            'ad_type' => (string)$row['ad_type'],
            'unit' => (string)$row['unit'],
            'list_price' => (string)$row['list_price'],
            'sale_price' => (string)$row['sale_price'],
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

    private function getFrameworkContractOptions(): array
    {
        $rows = Contract::find()
            ->alias('ct')
            ->select([
                'ct.id',
                'ct.contract_no',
                'ct.contract_name',
                'ct.customer_id',
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
            ])
            ->leftJoin(['c' => Customer::tableName()], 'c.id = ct.customer_id AND c.deleted = 0')
            ->where([
                'ct.deleted' => 0,
                'ct.contract_type' => Contract::TYPE_FRAMEWORK,
            ])
            ->orderBy(['ct.id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'contract_no' => (string)$row['contract_no'],
            'contract_name' => (string)$row['contract_name'],
            'customer_id' => (int)$row['customer_id'],
            'customer_name' => (string)($row['customer_name'] ?? ''),
        ], $rows);
    }

    private function generateContractNo(): string
    {
        $prefix = 'HT' . date('Ymd');
        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!Contract::find()->where(['contract_no' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function generateReceivablePlanNo(): string
    {
        $prefix = 'HKJH' . date('Ymd');
        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!ReceivablePlan::find()->where(['plan_no' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstError(Contract|ContractProduct $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }

    private function firstReceivablePlanError(ReceivablePlan $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '回款计划保存失败';
    }
    private function firstContractCostError(ContractCost $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '合同成本保存失败';
    }
}
