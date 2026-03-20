<?php
namespace Home\Controller;
use Swoole\Coroutine\Channel;

/**
 * 默认控制器
 * @author mengrui
 * $Id: index.php $
 */
class Index extends \App\Application
{
    private $_indexService;
    private $_stsService;

    /**
     * @param $appName
     * @param $controllerName
     * @param $actionName
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        $this->_indexService = \Lsf\Loader::service('Index', false, APP_NAME_HOME);
        $this->_stsService = \Lsf\Loader::service('StsAli', true);
    }

    /**
     * 首页洞见&情绪
     * 第一层逻辑：用户最近7天已解析日记大于等于3：则聚合查询；否则返回空
     * 展示限制：日记标签最多3个；情绪标签最多三个
     *  --第二层逻辑：
     *  1. 日记标签展示规则
     *         获取近7天已分析日记标签（去重计数 —— “一条日记对同一主题只计一次”），
     *         排序取 TopN
     *  2. 情绪标签展示规则
     *         获取近7天已分析日记的情绪和强度，
     *         对每种情绪做“计数”和“累加强度”
     *         计算每种情绪的“平均星级”
     *         选出现次数最多的为主情绪（必要），再取平均星级最高的前3个情绪！
     */
    public function userLatestInsights(){
        //todo 1. 用户最近7天已解析日记数
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->json(1001013, [], 'token失效');
        }
        $count = $this->_indexService->noteCountLast7Days($uid);
        $response = [];
        $eCode = ECODE_SUCCESS;
        if(is_int($count) && $count >= 3){
            $emotion = $this->_indexService->userEmotionTags($uid);
            $tag = $this->_indexService->userNoteTags($uid);

            $response = [
                'note_tags' => $tag,
                'emotion_tags' => $emotion,
            ];
        }
        return $this->json($eCode, $response);

    }

    public function getStsToken(){
        $uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->json(1001013, [], 'token失效');
        }
        $fileType = $this->post('file_type', true);

        if (!isset($fileType) || empty($fileType)) {
            $fileType = 'audio';
        }
        $result = $this->_stsService->getStsToken($uid, $fileType);
        $eCode = ECODE_SUCCESS;
        if($result === false){
            $eCode = 1001018;
            $result = [];
        }
        return $this->json($eCode, $result);
    }
}
