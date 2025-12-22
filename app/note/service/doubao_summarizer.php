<?php
namespace Note\Service;


/**
 * 豆包文本解析服务
 * @author mengrui
 * $Id: doubao_summarizer.php $
 */

class DoubaoSummarizer
{
    private $_svrVolcModel;
    private $_daoVnAiAnalysisUsageModel;
    private $_model = 'doubao-seed-1-6-flash-250828';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_svrVolcModel = \Lsf\Loader::Model('SvrVolc', false, APP_NAME_NOTE);
        $this->_daoVnAiAnalysisUsageModel = \Lsf\Loader::Model('DaoVnAiAnalysisUsage', false, APP_NAME_NOTE);
    }

    /**
     * 使用 JSON Schema 结构化输出提取标题和摘要
     *
     * @param string $text 原文（≤1000 字符）
     * @param int $userId 触发分析的用户ID
     * @param int $noteId 关联 notes.id（可为空，如测试调用）
     * @return array{title: string, summary: string, compliance_status: int, compliance_reason: string}
     */
    public function summarize(string $text, int $userId, int $noteId = 0): array
    {
        $startTime = microtime(true);
        // 1. 参数校验
        $text = trim($text);
        if (empty($text)) {
            return [];
        }
        // 限制长度防止超大包
        $text = mb_substr($text, 0, 2000);

        // 2. 构建 Prompt
        $systemPrompt = "请为以下文章生成：
1. 标题：不超过20个字符
2. 摘要：不超过100个字符
3. 合规状态：根据欧美标准判断
   - 若明显合规 → status: 2, reason: \"\"
   - 若明显违规 → status: 3, reason: \"不超过100字符的英文原因\"
   - 若不确定 → status: 1, reason: \"\"

只返回纯JSON，不要任何其他内容。";

        // 3. 请求参数
        $payload = [
            'model' => $this->_model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "文章：{$text}"]
            ],
            'max_tokens' => 500,
            'temperature' => 0.1,
            'response_format' => $this->_getJsonSchema(),
            'thinking' => ['type' => 'disabled'],
        ];

        // 4. 调用模型
        try {
            $response = $this->_svrVolcModel->aiSummarize($payload);
        } catch (\Exception $e) {
            // log error if needed
            return [];
        }

        // 计算耗时
        $durationMs = round((microtime(true) - $startTime) * 1000);

        // 5. 解析结果
        $content = $response['choices'][0]['message']['content'] ?? '';
        $final = [];
        
        if (is_array($content)) {
            $final = $content;
        } elseif (is_string($content)) {
            $final = json_decode($content, true);
        }

        if (empty($final) || !is_array($final)) {
            return [];
        }

        // 6. 提取字段
        $title = mb_substr(trim($final['title'] ?? ''), 0, 20);
        $summary = mb_substr(trim($final['summary'] ?? ''), 0, 100);
        
        $status = $final['compliance']['status'] ?? 1;
        if (!in_array($status, [1, 2, 3])) {
            $status = 1;
        }
        
        $reason = mb_substr($final['compliance']['reason'] ?? '', 0, 100);

        // 7. 存储调用日志
        $data = [
            'note_id' => $noteId,
            'user_id' => $userId,
            'ai_model' => $response['model'] ?? $this->_model,
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
            'duration_ms' => $durationMs,
            'request_id' => $response['id'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        try {
            $this->_daoVnAiAnalysisUsageModel->insert($data);
        } catch (\Exception $e) {
            // 日志存储失败不应影响主流程
            // error_log('AI Usage Insert Failed: ' . $e->getMessage());
        }

        // 8. 返回结果
        return [
            'title' => $title,
            'summary' => $summary,
            'compliance_status' => $status,
            'compliance_reason' => $reason,
        ];
    }

    /**
     * 获取 JSON Schema 定义
     * @return array
     */
    private function _getJsonSchema()
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'text_summarize',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                        'compliance' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'integer'],
                                'reason' => ['type' => 'string']
                            ],
                            'required' => ['status', 'reason'],
                            'additionalProperties' => false
                        ]
                    ],
                    'required' => ['title', 'summary', 'compliance'],
                    'additionalProperties' => false
                ],
                'strict' => true
            ],
        ];
    }

}
