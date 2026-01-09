<?php
namespace Note\Service;


/**
 * 服务基类
 * @author mengrui
 * $Id: base.php $
 */

class Base
{
    const REDIS_EXPIRE_BASE_TIME = 600; //10分钟

    /**
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
                \Lsf\Loader::plugin('Log')->error(1000510, [
                    'redis_key'     => $redisKey,
                    'call_function' => 'setex',
                    'result'        => $result
                ]);
                return FALSE;
            }else{
                return TRUE;
            }
        }catch(\RedisException $e){
            \Lsf\Loader::plugin('Log')->error(1000511, [
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
                    \Lsf\Loader::plugin('Log')->error(1000510, ['redis_key' => $redisKey, 'redis_data' => $redisData]);
                    return FALSE;
                }else{
                    return $data;
                }
            }
        }catch(\RedisException $e){
            \Lsf\Loader::plugin('Log')->error(1000511, [
                'redis_key'     => $redisKey,
                'call_function' => 'get',
                'code'          => $e->getCode(),
                'message'       => $e->getMessage()
            ]);
            return FALSE;
        }
    }
}