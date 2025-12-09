<?php
namespace User\Service;

/**
 * 帐号服务
 * $Id: oauth.php $
 * @author mr
 */

require_once LSFPATH . '/lib/php-jwt/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use \Lsf\Env;

class Oauth
{
    /**
     * @var mixed
     */
    private $_svrDaoUserModel;
    private $_svrDaoVnUserAuthModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_svrDaoUserModel = \Lsf\Loader::model('DaoUser', false, APP_NAME_USER);
        $this->_svrDaoVnUserAuthModel = \Lsf\Loader::model('DaoVnUserAuth', false, APP_NAME_USER);
    }

    /**
     * 验证苹果登录identityToken的合法性和有效性
     * @param void
     * @return string
     */
    public function checkAppleIdentityToken($id_token = ''){
        $data =[];
        //示例
        if (empty($id_token)) {
            return $data;
        }

        // === 下载 Apple 公钥 ===
        // Apple 公钥返回的是 JWK 格式
        $jwks = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);

        // === 将 JWK 转为可验证格式 ===
        $keys = JWK::parseKeySet($jwks);


        // === 4. 验证并解码 id_token ===
        try {
            $decoded = JWT::decode($id_token, $keys);

            if ($decoded->iss !== 'https://appleid.apple.com') {
                throw new \Exception("Invalid issuer");
            }

            if ($decoded->aud !== '你的 Apple Service ID / Client ID') {
                throw new \Exception("Invalid audience");
            }

            if ($decoded->exp < time()) {
                throw new \Exception("expired token");
            }

            $data['apple_uid'] = $decoded->sub;
            $data['email'] = $decoded->email ?? '';


        } catch (\Exception $e) {
            $errData = [
                'method'    => __FUNCTION__,
                'message' => "token verification failed",
                'ecode'     => $e->getCode(),
                'emsg'      => $e->getMessage()
            ];
            \Lsf\Loader::plugin('Log')->error(9010511, $errData);
        }
        return $data;
    }

    /**
     * 苹果登录Or注册，返回登录态
     * @param void
     * @return string
     */
    public function appleLoginOrSignUp($data){
        if(!isset($data['provider']) && empty($data['provider'])){
            $data['provider'] = 'apple';
        }
        //根据 sub（苹果用户唯一ID）查找本地用户
        if(isset($data['apple_uid']) && !empty($data['apple_uid'])){
            $result = $this->_svrDaoVnUserAuthModel->findOauthInfo($$data);
            //找到则返回用户uid
            if(isset($result['uid'])){
                $uid = $result['uid'];
            }else{//找不到则自动注册绑定
                //事务处理

                $result = $this->_svrDaoUserModel->userSign($data);

                if(isset($result) && is_int($result) && $result > 0){
                    $uid = $result;
                }else{
                    return 'err';
                    //log
                }
            }

            //通过uid获取uuid数据，生成jwttoken


            //jwt

        }else{
            //验证客户端授权信息失败

        }




    }

    public function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64url_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function jwt_encode(array $payload): string {
        $header = ["alg" => "HS256", "typ" => "JWT"];
        $h = $this->base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = $this->base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', "$h.$p", \Lsf\Env::get('TOKEN_JWT_SECRET'), true);
        return "$h.$p." . $this->base64url_encode($sig);
    }

    public function jwt_decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        list($h, $p, $s) = $parts;
        $expected = $this->base64url_encode(hash_hmac('sha256', "$h.$p", \Lsf\Env::get('TOKEN_JWT_SECRET'), true));
        // 常量时间对比，减少侧信道风险
        if (!hash_equals($expected, $s)) return null;

        $payload = json_decode($this->base64url_decode($p), true);
        if (!is_array($payload)) return null;

        if (isset($payload['exp']) && time() >= (int)$payload['exp']) return null;
        return $payload;
    }
}
