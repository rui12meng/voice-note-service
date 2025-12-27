<?php
namespace Action\Controller;

/**
 * 用户行动项控制器
 * $Id: actions.php $
 * @author mengrui
 */

class Actions extends \App\Application
{
    /**
     * @var mixed
     */
    private $_actionsService;

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
        $this->_actionsService = \Lsf\Loader::service('Actions', false, APP_NAME_NOTE);

    }

    /**
     * todo 说明：行动项全部来源于笔记
     * 删除某日记下关联的全部行动（即删除日记下的整个行动模块）
     * 对标笔记智能分页结果页面其他模块功能
     * 采取软删除（分析模块），同时软删除行动项
     * @param  void
     * @return void
     */
    public function delBlock(){
        //1. 首先软删除分析模块
        $uid = 101;
        $noteId = $this->post('note_id', true);
        if ( ! isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }
        $structType = 'actions';
        $result = $this->_actionsService->delActionsByNoteId($uid, $noteId, $structType);

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
        //2. 在删除关联行动
    }


}
