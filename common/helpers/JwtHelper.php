<?php

declare(strict_types=1);

namespace common\helpers;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Yii;
use yii\web\UnauthorizedHttpException;

class JwtHelper
{
    private const ALG = 'HS256';

    public static function generateAccessToken(int $userId): string
    {
        $now = time();

        $payload = [
            'iss'  => Yii::$app->params['jwtIssuer'],
            'aud'  => Yii::$app->params['jwtAudience'],
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + Yii::$app->params['jwtAccessTokenExpire'],
            'sub'  => (string)$userId,
            'type' => 'access',
        ];

        return JWT::encode($payload, self::secret(), self::ALG);
    }

    public static function generateRefreshToken(int $userId): string
    {
        $now = time();

        $payload = [
            'iss'  => Yii::$app->params['jwtIssuer'],
            'aud'  => Yii::$app->params['jwtAudience'],
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + Yii::$app->params['jwtRefreshTokenExpire'],
            'sub'  => (string)$userId,
            'type' => 'refresh',
        ];

        return JWT::encode($payload, self::secret(), self::ALG);
    }

    public static function getUserId(string $token): int
    {
        $payload = self::decode($token);

        if (($payload->type ?? '') !== 'access') {
            throw new UnauthorizedHttpException('Token 类型错误');
        }

        return (int)$payload->sub;
    }

    public static function getRefreshUserId(string $token): int
    {
        $payload = self::decode($token);

        if (($payload->type ?? '') !== 'refresh') {
            throw new UnauthorizedHttpException('Refresh token 类型错误');
        }

        return (int)$payload->sub;
    }

    public static function decode(string $token): object
    {
        try {
            return JWT::decode($token, new Key(self::secret(), self::ALG));
        } catch (ExpiredException) {
            throw new UnauthorizedHttpException('Token 已过期');
        } catch (BeforeValidException) {
            throw new UnauthorizedHttpException('Token 尚未生效');
        } catch (\Throwable) {
            throw new UnauthorizedHttpException('Token 无效');
        }
    }

    private static function secret(): string
    {
        return Yii::$app->params['jwtSecret'];
    }
}