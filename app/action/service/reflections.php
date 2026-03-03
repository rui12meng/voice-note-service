<?php
namespace Action\Service;


/**
 * 行动复盘服务
 * @author mengrui
 * $Id: reflections.php $
 */

class Reflections
{

    private $_daoVnActionsModel;
    private $_daoVnReflectionsModel;
    private $_daoVnReflectionAiAnalysisModel;

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_daoVnActionsModel = \Lsf\Loader::Model('DaoVnActions',true);
        $this->_daoVnReflectionsModel = \Lsf\Loader::Model('DaoVnUserReflections',false, APP_NAME_ACTION);
        $this->_daoVnReflectionAiAnalysisModel = \Lsf\Loader::Model('DaoVnReflectionAiAnalysis',false, APP_NAME_ACTION);
    }

    /**
     * v0.2 版本
     * 创建复盘基础信息&AI分析
     * @param  int $uid    用户ID
     * @param  string $type
     * @param  string $startDate
     * @param  string $endDate
     * @param  array $actionIds 行动ids
     * @param  int $satisfaction 满意度评分
     * @param  string $summary 自我评价
     */
    public function createAndAIAnalysis($uid, $type, $startDate, $endDate, $actionIds, $satisfaction, $summary){
        // todo 1. 优先存储用户复盘数据
        $data = [
            'user_id' => $uid,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'satisfaction_score' => $satisfaction,
            'daily_self_summary' => $summary,
            'action_ids' => json_encode($actionIds, JSON_UNESCAPED_UNICODE),
        ];

        // 检查是否存在（防止唯一索引报错）
        $checkWhere = [
            'user_id' => $uid,
            'type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        $exist = $this->_daoVnReflectionsModel->select('id', $checkWhere, '', 1);

        if (!empty($exist) && isset($exist[0]['id'])) {
            $reflectionId = $exist[0]['id'];
            $result = $this->_daoVnReflectionsModel->update($data, ['id' => $reflectionId]);
            if ($result === false) {
                return -7;
            }
        } else {
            $reflectionId = $this->_daoVnReflectionsModel->insert($data);
            if ($reflectionId === false) {
                return -7;
            }
        }

        //todo 2. 查询actions
        $where = [
            'user_id' => $uid,
            'id' => ['IN', $actionIds],
        ];

        $result = $this->_daoVnActionsModel->select('title, status, habit_id, streak', $where);
        if($result === false){
            return -7;
        }

        $promptData = [
            'summary' => $summary,
            'satisfaction' => $satisfaction,
            'actions' => $result
        ];

        $reflectionResult = $this->RetrospectiveWithAIAnalysis($promptData);
        //todo 将复盘结果存入表
        if (!empty($reflectionResult)) {
            $nextActions = isset($reflectionResult['next_action']) ? $reflectionResult['next_action'] : [];
            unset($reflectionResult['next_action']);

            $data = [
                'reflection_id' => $reflectionId,
                'type' => $type,
                'analysis' => json_encode($reflectionResult, JSON_UNESCAPED_UNICODE),
                'action_suggestions' => json_encode($nextActions, JSON_UNESCAPED_UNICODE),
            ];

            // 检查是否存在
            $checkAiWhere = [
                'reflection_id' => $reflectionId,
            ];
            $existAi = $this->_daoVnReflectionAiAnalysisModel->select('id', $checkAiWhere, '', 1);

            if (!empty($existAi) && isset($existAi[0]['id'])) {
                $result = $this->_daoVnReflectionAiAnalysisModel->update($data, ['id' => $existAi[0]['id']]);
                if ($result === false) {
                    return -7;
                }
            } else {
                $reflectionAiId = $this->_daoVnReflectionAiAnalysisModel->insert($data);
                if ($reflectionAiId === false) {
                    return -7;
                }
            }
        }
    }

    /**
     * 获取用户某日行动ID列表
     * @param  int $uid    用户ID
     * @param  string $type
     * @param  string $date
     * @param  array $actionIds 行动ids
     * @param  int $satisfaction 满意度评分
     * @param  string $emotion 主情绪
     * @param  string $summary 自我评价
     * @param  int $syncToNote 是否同步到日记
     * @param  int $nextAction 是否生成下一步计划/建议
     * @return void
     */
    public function createRetrospectiveWithAIAnalysis($uid, $type, $date, $actionIds, $satisfaction, $emotion, $summary, $syncToNote, $nextAction){
        //todo 1. 优先存储用户复盘数据
        $data = [
            'user_id' => $uid,
            'type' => $type,
            'start_date' => $date,
            'end_date' => $date,
            'satisfaction_score' => $satisfaction,
            'primary_emotion' => $emotion,
            'daily_self_summary' => $summary,
            'sync_to_diary' => $syncToNote,
            'generate_next_action' => $nextAction,
            'action_ids' => json_encode($actionIds, JSON_UNESCAPED_UNICODE),
        ];

        // 检查是否存在（防止唯一索引报错）
        $checkWhere = [
            'user_id' => $uid,
            'type' => $type,
            'start_date' => $date,
            'end_date' => $date,
        ];
        $exist = $this->_daoVnReflectionsModel->select('id', $checkWhere, '', 1);

        if (!empty($exist) && isset($exist[0]['id'])) {
            $reflectionId = $exist[0]['id'];
            $this->_daoVnReflectionsModel->update($data, ['id' => $reflectionId]);
        } else {
            $reflectionId = $this->_daoVnReflectionsModel->insert($data);
            if ($reflectionId === false) {
                return -7;
            }
        }

        //todo 2. 查询actions
        $where = [
            'user_id' => $uid,
            'id' => ['IN', $actionIds],
        ];

        $result = $this->_daoVnActionsModel->select('title, status, habit_id, streak', $where);
        if($result === false){
            return -7;
        }

        //todo 组装prompt数据

        // 用户自评（个性化/差异化）
        // actions 完成/未完成 状态， 如果是习惯，有连续坚持次数
        // 用户对今天的任务满意度自评分（1-5分）；主情绪（5种中的一种）

        $promptData = [
            'summary' => $summary,
            'satisfaction' => $satisfaction,
            'emotion' => $emotion,
            'actions' => $result
        ];

        $reflectionResult = $this->RetrospectiveWithAIAnalysis($promptData);
        //todo 将复盘结果存入表
        if (!empty($reflectionResult)) {
            $nextActions = isset($reflectionResult['next_action']) ? $reflectionResult['next_action'] : [];
            unset($reflectionResult['next_action']);

            $data = [
                'reflection_id' => $reflectionId,
                'type' => $type,
                'analysis' => json_encode($reflectionResult, JSON_UNESCAPED_UNICODE),
                'action_suggestions' => json_encode($nextActions, JSON_UNESCAPED_UNICODE),
            ];

            // 检查是否存在
            $checkAiWhere = [
                'reflection_id' => $reflectionId,
            ];
            $existAi = $this->_daoVnReflectionAiAnalysisModel->select('id', $checkAiWhere, '', 1);

            if (!empty($existAi) && isset($existAi[0]['id'])) {
                $this->_daoVnReflectionAiAnalysisModel->update($data, ['id' => $existAi[0]['id']]);
            } else {
                $reflectionAiId = $this->_daoVnReflectionAiAnalysisModel->insert($data);
                if ($reflectionAiId === false) {
                    return -7;
                }
            }
        }
    }

    public function RetrospectiveWithAIAnalysis($data){
        // 1. 构建 Prompt
        $actionsStr = "";
        foreach ($data['actions'] as $action) {
            $statusStr = $action['status'] == 1 ? "已完成" : "未完成";
            $habitInfo = "";
            if (!empty($action['habit_id'])) {
                $habitInfo = "（习惯坚持天数：{$action['streak']}）";
            }
            $actionsStr .= "- {$action['title']}：{$statusStr}{$habitInfo}\n";
        }

        $systemPrompt = <<<PROMPT
你是一位以用户成长为中心的行动复盘教练。
请基于用户当日的自我评价、满意度和行动完成情况，进行分析。
只与用户过去的自己对比，不与他人或理想状态比较。

分析重点：
1. 用户的主观感受（自评、满意度）与实际行动之间的关系
2. 今天最值得关注的一个行为
3. 找到一个最紧急、最有价值的调整点

用户今日数据：
1. 自评：{$data['summary']}
2. 满意度（1–5）：{$data['satisfaction']}
3. 行动完成情况：
{$actionsStr}

请严格按以下 JSON 结构输出（不要输出多余内容）：

{
  "ai_summary": "一句话总结（≤100字符，客观、贴合今日实际）",
  "suggestions": [
    "1条最紧急、最值得尝试的改进建议",
    "如有必要，第2条补充建议（最多2条）"
  ],
  "encouragement": "基于今天的真实表现给出的具体鼓励",
  "next_action": [
    "1条明确的下一步行动建议（优先明日可执行或一个微习惯）",
    "如有必要，第2条备选行动（最多2条）"
  ]
}

注意：所有建议必须具体、可执行，并贴合今日情境。
PROMPT;

        // 2. 调用 AI 模型
        $svrVolc = \Lsf\Loader::Model('SvrVolc', true);
        $payload = [
            'model' => 'doubao-seed-1-6-flash-250828', // 使用与 summarizer 相同的模型
            'messages' => [
                ['role' => 'user', 'content' => $systemPrompt]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'], // 强制 JSON 输出
        ];

        try {
            $response = $svrVolc->aiSummarize($payload);
            $content = $response['choices'][0]['message']['content'] ?? '';
            
            // 解析 JSON
            $result = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
            return [];
        } catch (\Exception $e) {
            \Lsf\Loader::plugin('Log')->error(1001015, [
                'error' => 'AI Reflection Analysis Failed',
                'msg' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取用户当日行动总数量
     * @param  int $uid    用户ID
     * @param  string $execDay  行动日期
     * @return void
     */
    public function countTodayActions($uid, $execDay){
        $where = [
            'user_id' => $uid,
            'due_date' => $execDay,
            'is_deleted' => 0,
        ];
        //先查询日记是否属于该用户
        $actionsNum = $this->_daoVnNotesModel->count('id', $where);
        if($actionsNum === false){
            return -7;
        }
        return $actionsNum;

    }

    /**
     * 用户根据id删除复盘记录
     * @param  int $uid    用户ID
     * @param  int $reflectionId  复盘id
     * @return void
     */
    public function deleteReflectionById($uid, $reflectionId){
        $data = ['is_deleted' => 1];
        $where = [
            'id' => $reflectionId,
            'user_id' => $uid,
        ];
        $result = $this->_daoVnReflectionsModel->update($data, $where);
        //todo 分析数据同步删除（可后期实现）

        if($result === false){
            return -7;
        }
        // 若不存在或已删除，静默忽略，不报错
        if (empty($result) || (int)($result) >= 0) {
            return 0;
        }else{
            return -6;
        }
    }

    /**
     * 用户复盘列表
     * @param  int $uid    用户ID
     * @param  int $cursor  游标
     * @param  int $pageSize 每页数量
     * @param array $filters 查询条件数组
     * @return void
     */
    public function reflectionList($uid, $cursor, $pageSize, $filters){
        $where = [
            'user_id' => $uid,
            'is_deleted' => 0,
        ];
        $where = array_merge($where, $filters);
        if (!empty($cursor)) {
            $where['id'] = ['LT', (int)$cursor];
        }

        $columns = 'id, type, start_date, end_date, satisfaction_score, daily_self_summary, weekly_focus_for_next';
        $orderBy = 'id DESC';
        $list = $this->_daoVnReflectionsModel->select($columns, $where, $orderBy, $pageSize+1);

        if($list === false){
            return -7;
        }

        $hasNext = count($list) > $pageSize;
        if ($hasNext) {
            $list = array_slice($list, 0, $pageSize);
        }

        $nextCursor = $hasNext ? end($list)['id'] : 0;

        return [
            'list' => $this->_replaceNullWithEmptyString($list),
            'pagination' => [
                'has_next_page' => $hasNext,
                'next_cursor' => $nextCursor,
            ],
        ];
    }

    /**
     * 对数组中「所有字段」的 null 值统一替换为空字符串
     * @param   array $array
     * @return  array
     */
    private function _replaceNullWithEmptyString(array $array)
    {
        if(!empty($array)){
            foreach ($array as &$row) {
                if (is_array($row)) {
                    foreach ($row as $key => $value) {
                        if ($value === null) {
                            $row[$key] = '';
                        }
                    }
                }
            }
        }
        return $array;
    }

    /**
     * 用户根据复盘id获取复盘详情
     * @param  int $uid    用户ID
     * @param  int $reflectionId  复盘id
     * @return void
     */
    public function getReflectionDetailById($uid, $reflectionId){
        // 使用链表查询一次性取出复盘&分析数据
        $row = $this->_daoVnReflectionsModel->reflectionDetail($uid, $reflectionId);

        if ($row === false) {
            return -7;
        }
        $result = [];
        if(isset($row[0])){
            $result = $row[0];
        }
        // 解析分析数据
        if ($result['analysis']) {
            $result['analysis'] = json_decode($result['analysis'], true);
        }
        // 解析行动建议数据
        if ($result['action_suggestions']) {
            $result['action_suggestions'] = json_decode($result['action_suggestions'], true);
        }

        return $result;
    }
}