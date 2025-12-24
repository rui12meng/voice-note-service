<?php
namespace Note\Controller;

/**
 * 标签控制器
 * $Id: tags.php $
 * @author mengrui
 */

class Tags extends \App\Application
{
    /**
     * @var mixed
     */
    private $_tagsService;

    /**
     * 构造函数
     * @param  string $appName
     * @param  string $controllerName
     * @param  string $actionName
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        $this->_tagsService = \Lsf\Loader::service('Tags', false, APP_NAME_NOTE);

    }

    /**
     * 用户添加日记标签
     * 接收参数：uid、noteid、tag_name(必传)
     * @param  void
     * @return void
     */
    public function add()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }
        $tagName = $this->post('tag_name', true);
        if ( ! isset($tagName) || empty($tagName)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'tag_name');
        }

        $data = [
            'user_id'      => $uid,
            'note_id'  => $noteId,
            'name' => $tagName,
            'normalized_name' => mb_strtolower(trim($tagName), 'UTF-8'),//$tagName,
            'source' => 'user',
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        $result = $this->_tagsService->addTag($data);

        $eCode = ECODE_SUCCESS;
        $responseData = [];
        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }

        }else{
            $responseData = [
                'tag_id' => (int)$result,
                'tag_name' => $data['name'],
                'create_time' => $data['created_at'],
            ];
        }

        return $this->json($eCode, $responseData);
    }

    /**
     * 用户删除日记标签
     * 接收参数：uid、note_id、tag_name(必传)
     * 若标签不存在，静默忽略
     * @return void
     */
    public function delete()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101;

        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        $tagId = $this->post('tag_id', true);
        if (!isset($tagId) || empty($tagId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'tag_id');
        }

        $result = $this->_tagsService->deleteTag($uid, $noteId, $tagId);
        $eCode = ECODE_SUCCESS;

        if (is_int($result) && $result < 0) {
            switch ($result) {
                //数据库异常
                case -6:
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                //未知错误
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }

        return $this->json($eCode , []);
    }

}

