<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\AdProduct;
use common\models\Customer;
use common\models\Manuscript;
use common\models\ManuscriptWriter;
use common\models\User;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ManuscriptController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'manuscript.view',
        'select-options' => 'manuscript.view',
        'view' => 'manuscript.view',
        'create' => 'manuscript.create',
        'update' => 'manuscript.update',
        'delete' => 'manuscript.delete',
    ];

    public function actionIndex(): array
    {
        $page = max(1, (int)Yii::$app->request->post('page', 1));
        $size = max(1, (int)Yii::$app->request->post('size', 10));
        $manuscriptNo = trim((string)Yii::$app->request->post('manuscript_no', ''));
        $manuscriptType = (int)Yii::$app->request->post('manuscript_type', 0);
        $customerId = (int)Yii::$app->request->post('customer_id', 0);
        $productId = (int)Yii::$app->request->post('product_id', 0);
        $writerId = (int)Yii::$app->request->post('writer_id', 0);
        $keyword = trim((string)Yii::$app->request->post('keyword', ''));

        $query = Manuscript::find()
            ->alias('m')
            ->select([
                'm.*',
                'customer_name' => new Expression("COALESCE(c.customer_name, '')"),
                'product_name' => new Expression("COALESCE(p.product_name, '')"),
                'product_code' => new Expression("COALESCE(p.product_code, '')"),
            ])
            ->leftJoin(['c' => Customer::tableName()], 'c.id = m.customer_id AND c.deleted = 0')
            ->leftJoin(['p' => AdProduct::tableName()], 'p.id = m.product_id AND p.deleted = 0')
            ->where(['m.deleted' => 0]);

        if ($manuscriptNo !== '') {
            $query->andWhere(['like', 'm.manuscript_no', $manuscriptNo]);
        }

        if ($manuscriptType > 0) {
            $query->andWhere(['m.manuscript_type' => $manuscriptType]);
        }

        if ($customerId > 0) {
            $query->andWhere(['m.customer_id' => $customerId]);
        }

        if ($productId > 0) {
            $query->andWhere(['m.product_id' => $productId]);
        }

        if ($writerId > 0) {
            $query->innerJoin(
                ['mwf' => ManuscriptWriter::tableName()],
                'mwf.manuscript_id = m.id AND mwf.deleted = 0 AND mwf.writer_id = :writerId',
                [':writerId' => $writerId]
            );
        }

        if ($keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'm.title', $keyword],
                ['like', 'm.article_link', $keyword],
                ['like', 'c.customer_name', $keyword],
                ['like', 'p.product_name', $keyword],
            ]);
        }

        $total = (int)$query->count();
        $records = $query
            ->orderBy(['m.id' => SORT_DESC])
            ->offset(($page - 1) * $size)
            ->limit($size)
            ->asArray()
            ->all();

        return [
            'records' => array_map([$this, 'serializeManuscriptArray'], $records),
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
            'writers' => $this->getUserOptions(),
        ];
    }

    public function actionView(): array
    {
        return $this->serializeManuscript($this->findManuscript((int)Yii::$app->request->post('id', 0)));
    }

    public function actionCreate(): array
    {
        $model = new Manuscript();
        $writerIds = $this->loadManuscript($model);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$model->save()) {
                throw new BadRequestHttpException($this->firstError($model));
            }

            $this->syncWriters((int)$model->id, $writerIds);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->serializeManuscript($model);
    }

    public function actionUpdate(): array
    {
        $model = $this->findManuscript((int)Yii::$app->request->post('id', 0));
        $writerIds = $this->loadManuscript($model);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!$model->save()) {
                throw new BadRequestHttpException($this->firstError($model));
            }

            $this->syncWriters((int)$model->id, $writerIds);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->serializeManuscript($model);
    }

    public function actionDelete(): array
    {
        $model = $this->findManuscript((int)Yii::$app->request->post('id', 0));
        $userId = (int)(Yii::$app->user->id ?: 0);

        $model->markDeleted();

        if (!$model->save(false)) {
            throw new BadRequestHttpException('删除稿件失败');
        }

        ManuscriptWriter::markDeletedByManuscript((int)$model->id, $userId);

        return [
            'deleted' => true,
            'id' => (int)$model->id,
        ];
    }

    /**
     * @return int[]
     */
    private function loadManuscript(Manuscript $model): array
    {
        $post = Yii::$app->request->post();
        $manuscriptNo = trim((string)($post['manuscript_no'] ?? ''));

        $model->manuscript_no = $manuscriptNo !== '' ? $manuscriptNo : ($model->manuscript_no ?: $this->generateManuscriptNo());
        $model->manuscript_type = (int)($post['manuscript_type'] ?? Manuscript::TYPE_ORIGINAL);
        $model->customer_id = (int)($post['customer_id'] ?? 0);
        $model->contract_id = (int)($post['contract_id'] ?? 0);
        $model->fulfillment_id = (int)($post['fulfillment_id'] ?? 0);
        $model->contract_product_id = (int)($post['contract_product_id'] ?? 0);
        $model->product_id = (int)($post['product_id'] ?? 0);
        $model->title = trim((string)($post['title'] ?? ''));
        $model->article_link = trim((string)($post['article_link'] ?? ''));
        $model->remark = trim((string)($post['remark'] ?? ''));

        if (!in_array($model->manuscript_type, [Manuscript::TYPE_ORIGINAL, Manuscript::TYPE_CUSTOMER], true)) {
            throw new BadRequestHttpException('稿件类型不正确');
        }

        if ($model->title === '') {
            throw new BadRequestHttpException('稿件标题不能为空');
        }

        if ($model->article_link === '') {
            throw new BadRequestHttpException('文章链接不能为空');
        }

        if ($model->manuscript_type === Manuscript::TYPE_CUSTOMER && $model->customer_id <= 0) {
            throw new BadRequestHttpException('客户稿必须选择客户');
        }

        if ($model->customer_id > 0 && !Customer::find()->where(['id' => $model->customer_id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('客户不存在');
        }

        if ($model->product_id > 0 && !AdProduct::find()->where(['id' => $model->product_id, 'deleted' => 0])->exists()) {
            throw new BadRequestHttpException('产品不存在');
        }

        $exists = Manuscript::find()
            ->where(['manuscript_no' => $model->manuscript_no])
            ->andWhere(['deleted' => 0])
            ->andFilterWhere(['<>', 'id', (int)$model->id])
            ->exists();

        if ($exists) {
            throw new BadRequestHttpException('稿件编号已存在');
        }

        $writerIds = $this->normalizeWriterIds($post['writer_ids'] ?? []);
        if ($writerIds === []) {
            throw new BadRequestHttpException('请选择至少一位撰写人');
        }

        $validWriterCount = (int)User::find()
            ->where(['id' => $writerIds, 'status' => User::STATUS_ACTIVE])
            ->count();

        if ($validWriterCount !== count($writerIds)) {
            throw new BadRequestHttpException('撰写人不存在或已禁用');
        }

        return $writerIds;
    }

    private function findManuscript(int $id): Manuscript
    {
        $model = Manuscript::findOne(['id' => $id, 'deleted' => 0]);
        if ($model === null) {
            throw new NotFoundHttpException('稿件不存在');
        }

        return $model;
    }

    /**
     * @param int[] $writerIds
     */
    private function syncWriters(int $manuscriptId, array $writerIds): void
    {
        $userId = (int)(Yii::$app->user->id ?: 0);

        ManuscriptWriter::markDeletedByManuscript($manuscriptId, $userId);

        foreach ($writerIds as $writerId) {
            $link = ManuscriptWriter::findOne([
                'manuscript_id' => $manuscriptId,
                'writer_id' => $writerId,
            ]) ?? new ManuscriptWriter();

            $link->manuscript_id = $manuscriptId;
            $link->writer_id = $writerId;
            $link->deleted = 0;
            $link->deleted_at = 0;

            if (!$link->save()) {
                throw new BadRequestHttpException($this->firstError($link));
            }
        }
    }

    private function serializeManuscript(Manuscript $model): array
    {
        $row = $model->toArray();
        $customer = $model->customer_id > 0 ? Customer::findOne($model->customer_id) : null;
        $product = $model->product_id > 0 ? AdProduct::findOne($model->product_id) : null;
        $row['customer_name'] = $customer->customer_name ?? '';
        $row['product_name'] = $product->product_name ?? '';
        $row['product_code'] = $product->product_code ?? '';

        return $this->serializeManuscriptArray($row);
    }

    private function serializeManuscriptArray(array $row): array
    {
        $writerRows = $this->getWriterRows((int)($row['id'] ?? 0));

        return [
            'id' => (int)($row['id'] ?? 0),
            'manuscript_no' => (string)($row['manuscript_no'] ?? ''),
            'manuscript_type' => (int)($row['manuscript_type'] ?? Manuscript::TYPE_ORIGINAL),
            'customer_id' => (int)($row['customer_id'] ?? 0),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'contract_id' => (int)($row['contract_id'] ?? 0),
            'fulfillment_id' => (int)($row['fulfillment_id'] ?? 0),
            'contract_product_id' => (int)($row['contract_product_id'] ?? 0),
            'product_id' => (int)($row['product_id'] ?? 0),
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'article_link' => (string)($row['article_link'] ?? ''),
            'writer_ids' => array_map(static fn (array $writer): int => (int)$writer['id'], $writerRows),
            'writer_names' => implode('、', array_map(static fn (array $writer): string => (string)$writer['name'], $writerRows)),
            'writers' => $writerRows,
            'remark' => (string)($row['remark'] ?? ''),
            'created_at' => (int)($row['created_at'] ?? 0),
            'updated_at' => (int)($row['updated_at'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{id:int,name:string,mobile:string}>
     */
    private function getWriterRows(int $manuscriptId): array
    {
        if ($manuscriptId <= 0) {
            return [];
        }

        $rows = ManuscriptWriter::find()
            ->alias('mw')
            ->select([
                'id' => 'u.id',
                'name' => new Expression("COALESCE(NULLIF(u.real_name, ''), u.username, '')"),
                'mobile' => new Expression("COALESCE(u.mobile, '')"),
            ])
            ->innerJoin(['u' => User::tableName()], 'u.id = mw.writer_id')
            ->where(['mw.manuscript_id' => $manuscriptId, 'mw.deleted' => 0])
            ->orderBy(['mw.id' => SORT_ASC])
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'mobile' => (string)($row['mobile'] ?? ''),
        ], $rows);
    }

    /**
     * @return int[]
     */
    private function normalizeWriterIds(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $writerIds = [];
        foreach ($value as $writerId) {
            $writerId = (int)$writerId;
            if ($writerId > 0) {
                $writerIds[$writerId] = $writerId;
            }
        }

        return array_values($writerIds);
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
            ->select(['id', 'product_name', 'product_code'])
            ->where(['deleted' => 0, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->limit(500)
            ->asArray()
            ->all();

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'],
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_code' => (string)($row['product_code'] ?? ''),
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

    private function generateManuscriptNo(): string
    {
        $prefix = 'GJ' . date('Ymd');

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!Manuscript::find()->where(['manuscript_no' => $code])->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(md5((string)microtime(true)), 0, 6));
    }

    private function firstError(Manuscript|ManuscriptWriter $model): string
    {
        $errors = $model->getFirstErrors();
        return reset($errors) ?: '保存失败';
    }
}
