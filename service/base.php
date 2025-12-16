<?php
namespace Service;

require_once LSFPATH . '/lib/php-jwt/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lsf\Env;

/**
 * 服务基类
 * @author mengrui
 * $Id: base.php $
 */

class Base
{
    const REDIS_EXPIRE_BASE_TIME = 600; //10分钟



    /**
     * 生成 JWT token
     * @param void
     * @return string
     */
    public function generateTokens(int $userId) : array {
        $now = time();
        $jti = bin2hex(random_bytes(32));// 256-bit 随机字符串（64字符十六进制）
        // 1. access_token
        $accessPayload = [
            'sub' => $userId,        // 用户ID
            'jti' => $jti,
            'iat' => $now,           // 签发时间
            'exp' => $now + Env::get('TOKEN_ACCESS_TTL'),
        ];
        $token_jwt_access_secret = Env::get('TOKEN_JWT_ACCESS_SECRET');
        $accessToken = JWT::encode($accessPayload, $token_jwt_access_secret, 'HS256');

        // 2. refresh_token
        $refreshPayload = [
            'sub' => $userId,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $now + Env::get('TOKEN_REFRESH_TTL'),
        ];
        $refreshToken = JWT::encode($refreshPayload, Env::get('TOKEN_JWT_REFRESH_SECRET'), 'HS256');

        return [
            'jti' => $accessPayload['jti'],
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $now + Env::get('TOKEN_ACCESS_TTL')
        ];
    }

    /**
     * 验证 refresh_token
     * @param void
     * @return void
     */
    protected function verifyRefreshToken(string $token) {
        try {
            $payload = JWT::decode($token,  new Key(\Lsf\Env::get('TOKEN_JWT_REFRESH_SECRET'), 'HS256'));

        } catch (\Exception $e) {
            \Lsf\Loader::plugin('Log')->error(100001510, [
                'call_function' => 'verifyRefreshToken',
                'msg'        => 'refresh token jwt decode err',
                'error'        => $e,
            ]);
            return false;
        }
        return (array)$payload;
    }

    /*
     * 设置缓存
     * @param  string  $redisKey
     * @param  array   $data
     * @param  int     $expire
     * @return bool
     */
    protected function setCache($redisKey, $data, $expire = self::REDIS_EXPIRE_BASE_TIME)
    {
        try{
            $redisValue = json_encode($data, JSON_UNESCAPED_UNICODE);
            $result = \Lsf\Loader::plugin('RedisPool')->redis()->setex($redisKey, $expire, $redisValue);
            if(!$result){
                \Lsf\Loader::plugin('Log')->error(9040510, [
                    'redis_key'     => $redisKey,
                    'call_function' => 'setex',
                    'result'        => $result
                ]);
                return FALSE;
            }else{
                return TRUE;
            }
        }catch(\RedisException $e){
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'redis_key'     => $redisKey,
                'call_function' => 'setex',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage()
            ]);
            return FALSE;
        }
    }

    /**
     * 读取缓存
     * @param  string  $redisKey
     * @return mixed
     */
    protected function getCache($redisKey)
    {
        try{
            $redisData = \Lsf\Loader::plugin('RedisPool')->redis()->get($redisKey);
            if(!empty($redisData)){
                $data = json_decode($redisData, TRUE);
                if(json_last_error() > 0){
                    \Lsf\Loader::plugin('Log')->error(9044500, ['redis_key' => $redisKey, 'redis_data' => $redisData]);
                    return FALSE;
                }else{
                    return $data;
                }
            }
        }catch(\RedisException $e){
            \Lsf\Loader::plugin('Log')->error(9040511, [
                'redis_key'     => $redisKey,
                'call_function' => 'get',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage()
            ]);
            return FALSE;
        }
    }
}