<?php

declare(strict_types=1);

namespace api\controllers;

use common\services\MenuService;
use Yii;

class MenuController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'menu.view',
        'tree' => 'menu.view',
        'create' => 'menu.create',
        'update' => 'menu.update',
        'delete' => 'menu.delete',
    ];

    private MenuService $menuService;

    public function __construct($id, $module, MenuService $menuService, $config = [])
    {
        $this->menuService = $menuService;
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): array
    {
        return $this->menuService->index(trim((string)Yii::$app->request->post('keyword', '')));
    }

    public function actionTree(): array
    {
        return $this->menuService->tree();
    }

    public function actionCreate(): array
    {
        return $this->menuService->create(Yii::$app->request->post());
    }

    public function actionUpdate(): array
    {
        return $this->menuService->update((int)Yii::$app->request->post('id', 0), Yii::$app->request->post());
    }

    public function actionDelete(): array
    {
        return $this->menuService->delete((int)Yii::$app->request->post('id', 0));
    }
}
