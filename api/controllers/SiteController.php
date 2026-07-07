<?php

declare(strict_types=1);

namespace api\controllers;

use api\components\AdminLoginLogComponent;
use common\helpers\JwtHelper;
use common\models\LoginForm;
use common\models\User;
use Yii;
use yii\web\Controller;
use yii\web\UnauthorizedHttpException;

class SiteController extends Controller
{
    public $enableCsrfValidation = false;

    private AdminLoginLogComponent $adminLoginLog;

    public function __construct($id, $module, AdminLoginLogComponent $adminLoginLog, $config = [])
    {
        $this->adminLoginLog = $adminLoginLog;
        parent::__construct($id, $module, $config);
    }

    public function actionLogin(): array
    {
        $model = new LoginForm();

        $model->username = (string)Yii::$app->request->post('username');
        $model->password = (string)Yii::$app->request->post('password');

        if (!$model->login()) {
            $this->adminLoginLog->failed($model->username, 'Login failed');
            throw new UnauthorizedHttpException('用户名或密码错误');
        }

        $user = Yii::$app->user->identity;
        $this->adminLoginLog->success((int)$user->id, $user->username);

        return [
            'access_token'  => JwtHelper::generateAccessToken((int)$user->id),
            'refresh_token' => JwtHelper::generateRefreshToken((int)$user->id),
            'expires_in'    => Yii::$app->params['jwtAccessTokenExpire'],
            'token_type'    => 'Bearer',
            'user'          => [
                'id'       => $user->id,
                'username' => $user->username,
            ],
        ];
    }

    public function actionRefreshToken(): array
    {
        $refreshToken = Yii::$app->request->post('refresh_token');

        if (!$refreshToken) {
            throw new \yii\web\UnauthorizedHttpException('缺少 refresh_token');
        }

        $userId = JwtHelper::getRefreshUserId($refreshToken);

        $user = User::findOne([
            'id'     => $userId,
            'status' => User::STATUS_ACTIVE,
        ]);

        if ($user === null) {
            throw new \yii\web\UnauthorizedHttpException('用户不存在或已禁用');
        }

        return [
            'access_token' => JwtHelper::generateAccessToken((int)$user->id),
            'expires_in'   => Yii::$app->params['jwtAccessTokenExpire'],
            'token_type'   => 'Bearer',
        ];
    }

    public function actionTest(): array
    {
        //添加超级管理员默认用户
        /*$user = new \common\models\User();
        $user->username = 'admin';
        $user->email = 'admin@example.com';
        $user->setPassword('123456');
        $user->generateAuthKey();
        $user->status = \common\models\User::STATUS_ACTIVE;
        $user->created_at = time();
        $user->updated_at = time();
        $user->save(false);*/
        return ['message' => 'api ok'];
    }

}
