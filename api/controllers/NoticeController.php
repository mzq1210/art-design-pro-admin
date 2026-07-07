<?php

declare(strict_types=1);

namespace api\controllers;

use common\models\Notice;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class NoticeController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'notice.view',
        'view' => 'notice.view',
        'create' => 'notice.create',
        'update' => 'notice.update',
        'delete' => 'notice.delete',
    ];

    public function actionIndex(): array
    {
        $page   = Yii::$app->request->post('page', 1);
        $size   = Yii::$app->request->post('size', 10);
        $status = Yii::$app->request->post('status', -1);

        $query = Notice::find();
        if ($status !== -1) {
            $query = $query->andWhere(['status' => $status]);
        }

        $list  = $query->offset(($page - 1) * $size)
            ->limit($size)->orderBy(['id' => SORT_DESC])->asArray()->all();
        $total = Notice::find()->count();
        return [
            'records' => $list,
            'current' => $page + 1,
            'size'    => $size,
            'total'   => $total
        ];
    }

    public function actionCreate(): array
    {
        $post  = Yii::$app->request->post();
        $model = new Notice();
        $model->load($post, '');
        if (!$model->save()) {
            throw new BadRequestHttpException('创建失败');
        }
        return [
            'model' => $model
        ];
    }

    public function actionUpdate(): array
    {
        $post  = Yii::$app->request->post();
        $model = Notice::findOne($post['id']);
        if ($model === null) {
            throw new NotFoundHttpException('公告不存在');
        }

        $model->load($post, '');
        if (!$model->save()) {
            throw new BadRequestHttpException('更新失败');
        }
        return [
            'model' => $model
        ];
    }

    public function actionDelete(): array
    {
        $id    = Yii::$app->request->post('id');
        $model = Notice::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('公告不存在');
        }

        $model->updateAttributes(['status' => 0]);
        return [
            'id'      => $model->id,
            'deleted' => true,
        ];
    }

    public function actionView(): array
    {
        $post  = Yii::$app->request->post();
        $model = Notice::findOne($post['id']);
        if ($model === null) {
            throw new NotFoundHttpException('公告不存在');
        }

        return [
            'model' => $model
        ];
    }
}
