<?php

declare(strict_types=1);

namespace api\bootstrap;

use api\components\AdminLoginLogComponent;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;

final class DiBootstrap implements BootstrapInterface
{
    /**
     * @param Application $app Application instance.
     */
    public function bootstrap($app): void
    {
        Yii::$container->setSingleton(AdminLoginLogComponent::class, AdminLoginLogComponent::class);
    }
}
