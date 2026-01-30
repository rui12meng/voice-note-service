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

        $reflectionId = $this->_daoVnReflectionsModel->insert($data);
        if($reflectionId === false){
            return -7;
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
            $reflectionAiId = $this->_daoVnReflectionAiAnalysisModel->insert($data);
            if($reflectionAiId === false){
                return -7;
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
请基于用户当日的自我评价、情绪、满意度和行动完成情况，进行分析。
只与用户过去的自己对比，不与他人或理想状态比较。

分析重点：
1. 用户的主观感受（自评、情绪、满意度）与实际行动之间的关系
2. 今天最值得关注的一个行为或情绪模式
3. 找到一个最紧急、最有价值的调整点

用户今日数据：
1. 自评：{$data['summary']}
2. 满意度（1–5）：{$data['satisfaction']}
3. 主情绪：{$data['emotion']}
4. 行动完成情况：
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


}