<?php

declare(strict_types=1);

namespace api\controllers;

use common\services\DictService;
use Yii;

class DictController extends BaseController
{
    protected array $rbacPermissions = [
        'type-index' => 'dict.view',
        'type-create' => 'dict.create',
        'type-update' => 'dict.update',
        'type-delete' => 'dict.delete',
        'item-index' => 'dict.view',
        'item-create' => 'dict.create',
        'item-update' => 'dict.update',
        'item-delete' => 'dict.delete',
        'select-options' => 'dict.view',
    ];

    private DictService $dictService;

    public function __construct($id, $module, DictService $dictService, $config = [])
    {
        $this->dictService = $dictService;
        parent::__construct($id, $module, $config);
    }

    public function actionTypeIndex(): array
    {
        return $this->dictService->typeIndex(trim((string)Yii::$app->request->post('keyword', '')));
    }

    public function actionTypeCreate(): array
    {
        return $this->dictService->createType(Yii::$app->request->post());
    }

    public function actionTypeUpdate(): array
    {
        return $this->dictService->updateType((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
    }

    public function actionTypeDelete(): array
    {
        return $this->dictService->deleteType((int)Yii::$app->request->post('id', 0));
    }

    public function actionItemIndex(): array
    {
        return $this->dictService->itemIndex(
            (int)Yii::$app->request->post('type_id', 0),
            trim((string)Yii::$app->request->post('keyword', ''))
        );
    }

    public function actionItemCreate(): array
    {
        return $this->dictService->createItem(Yii::$app->request->post());
    }

    public function actionItemUpdate(): array
    {
        return $this->dictService->updateItem((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
    }

    public function actionItemDelete(): array
    {
        return $this->dictService->deleteItem((int)Yii::$app->request->post('id', 0));
    }

    public function actionSelectOptions(): array
    {
        return $this->dictService->selectOptions(trim((string)Yii::$app->request->post('code', '')));
    }
}
