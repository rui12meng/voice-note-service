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
    private $_model = 'doubao-seed-1-6-flash-250828';

    /**
     * 构造函数
     * @param  void
     * @return void
     */
    public function __construct(){
        $this->_svrVolcModel = \Lsf\Loader::Model('SvrVolc', false, APP_NAME_NOTE);
    }

    /**
     * 使用 JSON Schema 结构化输出提取标题和摘要
     *
     * @param string $text 原文（≤1000 字符）
     * @return array{title: string, summary: string}
     */
    public function summarize(string $text): array
    {
        // 严格限制输出长度（减少 token 消耗）
        $prompt = "请为以下文章生成一个不超过20字的标题和一段不超过100字的摘要。只返回JSON，不要任何其他内容。\n\n文章：{$text}";

        $payload = [
            'model' => $this->_model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 150, // 足够容纳 20+100 字
            'temperature' => 0.1, // 降低随机性，提高一致性
            //关键：强制模型输出指定 JSON 结构
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    "name" => "text_summarize",
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 20],
                            'summary' => ['type' => 'string', 'maxLength' => 100],
                        ],
                        'required' => ['title', 'summary'],
                    ],

                ],
            ],
            'thinking' => ['type' => 'disabled'],
        ];

        $response = $this->_svrVolcModel->aiSummarize($payload);

        $result = $response['choices'][0]['message']['content'] ?? '';
        if (is_string($result)) {
            $final = json_decode($result, true);
        } else {
            $final = $result; // 如果第一次就成功转成数组/对象，就不用再转
        }
        $title = trim($final['title']) ?? '';
        $summary = trim($final['summary']) ?? '';
        $id = trim($response['id']) ?? '';
        $model = trim($response['model']) ?? '';
        $completion_tokens = trim($response['usage']['completion_tokens']) ?? '';
        $prompt_tokens = trim($response['usage']['prompt_tokens']) ?? '';
        $total_tokens = trim($response['usage']['total_tokens']) ?? '';

        //存库sql

        return [
            'id' => $id,
            'model' => $model,
            'completion_tokens' => $completion_tokens,
            'prompt_tokens' => $prompt_tokens,
            'total_tokens' => $total_tokens,
            'title' => $title,
            'summary' => $summary,
        ];
    }

}