<?php

declare(strict_types=1);

return [
    'bootstrap' => [
        \common\bootstrap\MailerBootstrap::class,
        'queue',
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'menuCache' => [
            'class' => \yii\redis\Cache::class,
            'redis' => 'redis',
            'keyPrefix' => 'art-design:menu:',
        ],
        'authManager' => [
            'class' => yii\rbac\DbManager::class,
        ],
        'redis' => [
            'class' => yii\redis\Connection::class,
            'hostname' => 'localhost',
            'port' => 6379,
            'database' => 0,
        ],
        'queue' => [
            'class' => yii\queue\redis\Queue::class,
            'redis' => 'redis',
            'channel' => 'queue',
        ],
    ],
];
