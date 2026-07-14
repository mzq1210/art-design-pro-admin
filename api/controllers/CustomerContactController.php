<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Customer;
use common\models\CustomerContact;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CustomerContactController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'customer.view',
        'create' => 'customer.contact.create',
        'update' => 'customer.contact.update',
        'delete' => 'customer.contact.delete',
        'set-primary' => 'customer.contact.update',
    ];

    public function actionIndex(): array
    {
        $customerId = (int)Yii::$app->request->post('customer_id', 0);
        $query = CustomerContact::find()
            ->alias('cc')
            ->select(['cc.*', 'customer_name' => 'c.customer_name', 'customer_code' => 'c.customer_code'])
            ->leftJoin(['c' => Customer::tableName()], 'c.id = cc.customer_id')
            ->where(['cc.deleted' => 0]);

        if ($customerId > 0) {
            $query->andWhere(['cc.customer_id' => $customerId]);
        }

        $records = $query
            ->orderBy(['cc.is_primary' => SORT_DESC, 'cc.id' => SORT_ASC])
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeContactArray'], $records),
            'total' => count($records),
            'page' => 1,
            'size' => count($records),
        ];
    }

    public function actionCreate(): array
    {
        $model = new CustomerContact();
        $this->loadContact($model);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ((int)$model->is_primary === 1) {
                CustomerContact::updateAll(['is_primary' => 0], ['customer_id' => $model->customer_id, 'deleted' => 0]);
            }

            if (!$model->save()) {
                throw new BadRequestHttpException($this->firstError($model));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $model->toArray();
    }

    public function actionUpdate(): array
    {
        $model = $this->findContact((int)Yii::$app->request->post('id', 0));
        $this->loadContact($model);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if ((int)$model->is_primary === 1) {
                CustomerContact::updateAll(
                    ['is_primary' => 0],
                    ['and', ['customer_id' => $model->customer_id, 'deleted' => 0], ['<>', 'id', $model->id]]
                );
            }

            if (!$model->save()) {
                throw new BadRequestHttpException($this->firstError($model));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $model->toArray();
    }

    public function actionSetPrimary(): array
    {
        $model = $this->findContact((int)Yii::$app->request->post('id', 0));

        CustomerContact::updateAll(['is_primary' => 0], ['customer_id' => $model->customer_id, 'deleted' => 0]);
        $model->is_primary = 1;
        $model->save(false);

        return $model->toArray();
    }

    public function actionDelete(): array
    {
        $model = $this->findContact((int)Yii::$app->request->post('id', 0));
        $model->markDeleted();

        if (!$model->save(false)) {
            throw new BadRequestHttpException('删除联系人失败');
        }

        return [
            'deleted' => true,
            'id' => (int)$model->id,
        ];
    }

    private function loadContact(CustomerContact $model): void
    {
        $post = Yii::$app->request->post();
        $customerId = (int)($post['customer_id'] ?? 0);

        if (!Customer::find()->where(['id' => $customerId, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('客户不存在');
        }

        $model->customer_id = $customerId;
        $model->contact_name = trim((string)($post['contact_name'] ?? ''));
        $model->mobile = trim((string)($post['mobile'] ?? ''));
        $model->wechat = trim((string)($post['wechat'] ?? ''));
        $model->email = trim((string)($post['email'] ?? ''));
        $model->position = trim((string)($post['position'] ?? ''));
        $model->is_primary = (int)($post['is_primary'] ?? 0);
        $model->status = (int)($post['status'] ?? 1);
        $model->remark = trim((string)($post['remark'] ?? ''));

        if ($model->contact_name === '') {
            throw new BadRequestHttpException('联系人姓名不能为空');
        }

        if ($model->mobile === '') {
            throw new BadRequestHttpException('手机号不能为空');
        }

        if (!in_array($model->status, [0, 1], true)) {
            throw new BadRequestHttpException('联系人状态不正确');
        }
    }

    private function findContact(int $id): CustomerContact
    {
        $model = CustomerContact::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('联系人不存在');
        }

        return $model;
    }

    private function serializeContactArray(array $row): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'customer_code' => (string)($row['customer_code'] ?? ''),
            'contact_name' => (string)($row['contact_name'] ?? ''),
            'mobile' => (string)($row['mobile'] ?? ''),
            'wechat' => (string)($row['wechat'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'position' => (string)($row['position'] ?? ''),
            'is_primary' => (int)($row['is_primary'] ?? 0),
            'status' => (int)($row['status'] ?? 1),
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    private function firstError(CustomerContact $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }
}
