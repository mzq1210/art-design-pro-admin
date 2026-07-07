<?php

declare(strict_types=1);

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php',
);

return [
    'id' => 'app-api',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        'log',
        \api\bootstrap\DiBootstrap::class,
    ],
    'controllerNamespace' => 'api\controllers',
    'components' => [
        'request' => [
            'csrfParam' => '_csrf-api',
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'on beforeSend' => static function ($event): void {
                $response = $event->sender;

                if ($response->data === null) {
                    return;
                }

                if ($response->isSuccessful) {
                    $response->data = [
                        'code' => 0,
                        'message' => 'success',
                        'data' => $response->data,
                    ];
                    return;
                }

                $message = $response->data['message'] ?? 'Request failed';
                $response->data = [
                    'code' => $response->statusCode,
                    'message' => $message,
                    'data' => null,
                ];
            },
        ],
        'user' => [
            'identityClass' => \common\models\User::class,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => null,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                //登录相关
                'POST login' => 'site/login',
                'POST refresh-token' => 'site/refresh-token',
                'POST common/upload' => 'common/upload',
                'POST user/profile' => 'user/profile',
                'POST user/update-profile' => 'user/update-profile',
                'POST user/change-password' => 'user/change-password',
                'POST user/menus' => 'user/menus',
                'POST user/index' => 'user/index',
                'POST user/view' => 'user/view',
                'POST user/create' => 'user/create',
                'POST user/update' => 'user/update',
                'POST user/delete' => 'user/delete',
                'POST user/roles' => 'user/roles',
                'POST user/assign-roles' => 'user/assign-roles',

                // 公告管理
                'POST notice/index' => 'notice/index',
                'POST notice/view' => 'notice/view',
                'POST notice/create' => 'notice/create',
                'POST notice/update' => 'notice/update',
                'POST notice/delete' => 'notice/delete',

                // 角色管理
                'POST role/index' => 'role/index',
                'POST role/create' => 'role/create',
                'POST role/update' => 'role/update',
                'POST role/delete' => 'role/delete',
                'POST role/permissions' => 'role/permissions',
                'POST role/assign-permissions' => 'role/assign-permissions',

                // 权限管理
                'POST permission/index' => 'permission/index',
                'POST permission/create' => 'permission/create',
                'POST permission/update' => 'permission/update',
                'POST permission/delete' => 'permission/delete',
                'POST permission/diagnose' => 'permission/diagnose',
                'POST permission/sync-from-menu' => 'permission/sync-from-menu',

                // 规则管理
                'POST rule/index' => 'rule/index',
                'POST rule/create' => 'rule/create',
                'POST rule/delete' => 'rule/delete',

                // 菜单管理
                'POST menu/index' => 'menu/index',
                'POST menu/tree' => 'menu/tree',
                'POST menu/create' => 'menu/create',
                'POST menu/update' => 'menu/update',
                'POST menu/delete' => 'menu/delete',

                // 字典管理
                'POST dict/type-index' => 'dict/type-index',
                'POST dict/type-create' => 'dict/type-create',
                'POST dict/type-update' => 'dict/type-update',
                'POST dict/type-delete' => 'dict/type-delete',
                'POST dict/item-index' => 'dict/item-index',
                'POST dict/item-create' => 'dict/item-create',
                'POST dict/item-update' => 'dict/item-update',
                'POST dict/item-delete' => 'dict/item-delete',
                'POST dict/options' => 'dict/options',

                // 文件管理
                'POST file/group-index' => 'file/group-index',
                'POST file/group-create' => 'file/group-create',
                'POST file/group-update' => 'file/group-update',
                'POST file/group-delete' => 'file/group-delete',
                'POST file/index' => 'file/index',
                'POST file/upload' => 'file/upload',
                'POST file/update' => 'file/update',
                'POST file/delete' => 'file/delete',

                // 操作日志
                'POST operation-log/index' => 'operation-log/index',
                'POST operation-log/delete' => 'operation-log/delete',
                'POST operation-log/clear' => 'operation-log/clear',

                // 队列任务示例
                'POST queue/index' => 'queue/index',
                'POST queue/create-demo' => 'queue/create-demo',
                'POST queue/retry' => 'queue/retry',
                'POST queue/delete' => 'queue/delete',
                [
                    'class' => yii\rest\UrlRule::class,
                    'controller' => [
                        'menu',
                    ],
                    //URL 和 controller 保持一致，不自动复数化 URL
                    'pluralize' => false,
                ],
            ],
        ],
    ],
    'params' => $params,
];
