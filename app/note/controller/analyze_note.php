<?php
namespace Note\Controller;


/**
 * 日记智能分析服务
 * @author mengrui
 * $Id: analyze_note.php $
 */
class AnalyzeNote extends \App\Application
{
    /**
     * @var mixed
     */
    private $_noteAiAnalyzeService;

    /**
     * 构造函数
     * @param string $appName
     * @param string $controllerName
     * @param string $actionName
     * @return void
     */
    public function __construct($appName, $controllerName, $actionName)
    {
        parent::__construct($appName, $controllerName, $actionName);
        $this->_noteAiAnalyzeService = \Lsf\Loader::service('NoteAiAnalysis', false, APP_NAME_NOTE);

    }

    /**
     * 按模块软删除日记智能分析结果
     * 上行参数：uid, note_id, struct_type（模块唯一类型标记）
     */
    public function delete()
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
        $structType = $this->post('struct_type', true);
        if ( ! isset($structType) || empty($structType)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'struct_type');
        }

        $result = $this->_noteAiAnalyzeService->delAiStructData($noteId, $structType);

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

    /**
     * 编辑洞见数据
     * 上行参数：note_id, insights（json格式，包含 problem、cause、inspire 字段）
     */
    public function editInsight()
    {
        /*$uid = $this->uid;
        if (!isset($uid) || empty($uid)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'token');
        }*/
        $uid = 101; // 临时固定 uid，后续接入登录态

        $noteId = $this->post('note_id', true);
        if (!isset($noteId) || empty($noteId)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'note_id');
        }

        $insights = $this->post('insights', true);
        if (!isset($insights) || empty($insights)) {
            return $this->errParamMissing(ECODE_PARAM_MISSING, 'insights');
        }

        // 组装入库 json：{"insight":"xxx","question":"xxx","root_cause":"xxx"}
        $insight = [
            'insight'    => $insights['inspire'] ?? '',
            'question'   => $insights['problem'] ?? '',
            'root_cause' => $insights['cause'] ?? '',
        ];

        //更新数据并返回最新数据
        $result = $this->_noteAiAnalyzeService->getNewAiStructData($noteId, 'insight', $insight);

        $eCode = ECODE_SUCCESS;
        $responseData = [];
        if (is_int($result) && $result < 0) {
            switch ($result) {
                case -6:
                case -7:
                    $eCode = ECODE_DATABASE_QUERY_FAIL;
                    break;
                default:
                    $eCode = ECODE_UNDEFINED_ERROR;
            }
        }else{
            if(isset($result['note_id']) && isset($result['analysis_data'])){
                $analysis_data = json_decode($result['analysis_data'], JSON_UNESCAPED_UNICODE);
            }
            $responseData = [
                'note_id' => $result['note_id'] ?? 0,
                'insight' => [
                    'insight' => $analysis_data['insight'] ?? '',
                    'question' => $analysis_data['question'] ?? '',
                    'root_cause' => $analysis_data['root_cause'] ?? '',
                ],
                'update_time' => $result['updated_at'] ?? '',
            ];
        }

        return $this->json($eCode, $responseData);
    }

}