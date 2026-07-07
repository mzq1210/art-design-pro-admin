<?php

declare(strict_types=1);

namespace api\controllers;

use Yii;
use yii\rbac\Rule;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class RuleController extends BaseController
{
    protected array $rbacPermissions = [
        'index' => 'rule.view',
        'create' => 'rule.create',
        'delete' => 'rule.delete',
    ];

    public function actionIndex(): array
    {
        $rules = Yii::$app->authManager->getRules();

        $list = [];
        foreach ($rules as $rule) {
            $list[] = $this->serializeRule($rule);
        }

        return [
            'records' => $list,
            'total'   => count($list),
            'page'    => 1,
            'size'    => count($list),
        ];
    }

    public function actionCreate(): array
    {
        $class = trim((string)Yii::$app->request->post('class', ''));

        if ($class === '') {
            throw new BadRequestHttpException('Rule class is required.');
        }

        if (!class_exists($class)) {
            throw new BadRequestHttpException('Rule class does not exist.');
        }

        if (!is_subclass_of($class, Rule::class)) {
            throw new BadRequestHttpException('Rule class must extend yii\\rbac\\Rule.');
        }

        /** @var Rule $rule */
        $rule = new $class();

        if (Yii::$app->authManager->getRule($rule->name) !== null) {
            throw new BadRequestHttpException('Rule already exists.');
        }

        if (!Yii::$app->authManager->add($rule)) {
            throw new BadRequestHttpException('Failed to create rule.');
        }

        return $this->serializeRule($rule);
    }

    public function actionDelete(): array
    {
        $name = trim((string)Yii::$app->request->post('name', ''));
        if ($name === '') {
            throw new BadRequestHttpException('Rule name is required.');
        }

        $rule = Yii::$app->authManager->getRule($name);
        if ($rule === null) {
            throw new NotFoundHttpException('Rule does not exist.');
        }

        if (!Yii::$app->authManager->remove($rule)) {
            throw new BadRequestHttpException('Failed to delete rule.');
        }

        return [
            'deleted' => true,
        ];
    }

    private function serializeRule(Rule $rule): array
    {
        return [
            'name'       => $rule->name,
            'class'      => get_class($rule),
            'created_at' => $rule->createdAt,
            'updated_at' => $rule->updatedAt,
        ];
    }
}
