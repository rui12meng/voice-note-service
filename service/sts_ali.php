<?php
namespace Service;

require_once WEBPATH . '/vendor/autoload.php';

use AlibabaCloud\SDK\Sts\V20150401\Sts;
use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Tea\Exception\TeaError;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use AlibabaCloud\Credentials\Credential\Config as CredentialConfig;


/**
 * 阿里云STS服务
 * $Id: sts_ali.php $
 * @author mengrui
 */

class StsAli
{
    private $_aLiYunOssStsConfig;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct()
    {
        $this->_aLiYunOssStsConfig =\Lsf\Env::group('ALIYUN_STS_');
    }

    /**
     * 获取 STS 临时上传凭证
     * @param $uid int
     * @param $fileType string
     * @return void
     */
    public function getStsToken($uid,$fileType = 'audio') {

        $accessKeyId = $this->_aLiYunOssStsConfig['access_key_id'];
        $accessKeySecret = $this->_aLiYunOssStsConfig['access_key_secret'];
        $roleArn = $this->_aLiYunOssStsConfig['role_arn'];
        $roleSessionName = 'client-upload-session'.'-'.$uid;   // 会话名称，自定义，用于审计
        $durationSeconds = 900;                       // 临时凭证有效期，单位秒，最小900(15分钟)
        $bucketName = $this->_aLiYunOssStsConfig['bucket']; // OSS Bucket 名称
        $regionId = 'cn-beijing';                    // STS 服务所在地域

        $policy = [
            "Version" => "1",
            "Statement" => [
                [
                    "Effect" => "Allow",
                    "Action" => [
                        "oss:PutObject",      // 允许上传
                        "oss:AbortMultipartUpload" // 允许取消分片上传 (大文件需要)
                    ],
                    "Resource" => [
                        "acs:oss:*:*:$bucketName/$fileType/*" // 限制在该 Bucket 下的所有文件
                    ]
                ]
            ]
        ];

        try {
            $credential = new Credential([
                "type" => "ram_role_arn",
                "access_key_id" => $accessKeyId,
                "access_key_secret" => $accessKeySecret,
                "role_arn" => $roleArn,
            ]);

            $config = new Config([
                // 使用 credential 配置凭证
                'credential' => $credential,
                // 产品服务域名
                //'endpoint' => '',
                "regionId"   => $regionId,
            ]);

            // 初始化
            $client = new Sts($config);

            // 构建请求对象
            $assumeRoleRequest = new AssumeRoleRequest([
                "roleArn" => $roleArn,
                "roleSessionName" => $roleSessionName,
                "policy" => $policy,
                "durationSeconds" => $durationSeconds,
            ]);

            //发送请求
            $resp = $client->assumeRole($assumeRoleRequest);

            $logInfo = [
                'sts_session_id'  => $roleSessionName,
                'result'        => $resp,
            ];

            \Lsf\Loader::plugin('Log')->info('', $logInfo, 'sts_sdk_request_end');

            // 解析结果
            if ($resp && $resp->body && $resp->body->Credentials) {
                $cred = $resp->body->Credentials;

                return [
                    'accessKeyId'     => $cred->AccessKeyId,
                    'accessKeySecret' => $cred->AccessKeySecret,
                    'securityToken'   => $cred->SecurityToken,
                    'expiration'      => $cred->Expiration
                ];
            } else {
                //log
                \Lsf\Loader::plugin('Log')->error(1002014, [
                    'err_msg' => 'STS响应数据为空或格式异常',
                ]);
                return false;
            }

        } catch (Exception $e) {
            // 统一错误处理
            if (!($e instanceof TeaError)) {
                $e = new TeaError([], $e->getMessage(), $e->getCode(), $e);
            }

            \Lsf\Loader::plugin('Log')->error(1002014, [
                'err_code' => $e->getCode(),
                'err_message' => $e->getMessage(),
                'suggestion' => !empty($e->data["Recommend"]) ? $e->data["Recommend"] : '',
            ]);
            return false;
        }
    }

}
