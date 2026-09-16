<?php

namespace app\admin\library\crud;

use Throwable;
use ba\Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;

/**
 * CRUD 设计器的 AI 对话服务
 */
class AIChat
{
    /**
     * 上游流式请求总尝试次数（含首次请求）
     */
    protected const MAX_ATTEMPTS = 3;

    /**
     * 每次重试等待的基础时长（毫秒，按尝试次数递增）
     */
    protected const RETRY_BASE_MS = 500;

    /**
     * AI 配置
     * @throws Throwable
     */
    public static function config(): array
    {
        $config    = get_sys_config('', 'ai');
        $modelList = is_array($config['ai_model_list'] ?? null) ? $config['ai_model_list'] : [];

        return [
            'configured'    => !empty($config['ai_api_url']) && !empty($config['ai_api_key']) && $modelList,
            'model_list'    => $modelList,
            'default_model' => (string)($config['ai_default_model'] ?? ''),
        ];
    }

    /**
     * 请求上游 OpenAI Responses 兼容接口并返回流式响应体
     * @throws Throwable
     */
    public static function stream(string $model, array $messages, float $temperature, float $topP): ResponseInterface
    {
        $config = get_sys_config('', 'ai');

        $url = trim((string)($config['ai_api_url'] ?? ''));
        if (!$url) {
            throw new Exception('AI API URL is not configured');
        }

        $key = (string)($config['ai_api_key'] ?? '');
        if (!$key) {
            throw new Exception('AI API Key is not configured');
        }

        $modelList  = is_array($config['ai_model_list'] ?? null) ? $config['ai_model_list'] : [];
        $modelNames = array_column($modelList, 'value');
        if (!$model || !in_array($model, $modelNames)) {
            throw new Exception('AI model is not configured');
        }

        $payload = [
            'model'       => $model,
            'input'       => self::normalizeMessages($messages),
            'stream'      => true,
            'temperature' => $temperature,
            'top_p'       => $topP,
        ];

        return self::request($url, $key, $payload);
    }

    /**
     * 校验并规范化对话上下文
     * @throws Throwable
     */
    protected static function normalizeMessages(array $messages): array
    {
        if (!$messages) {
            throw new Exception('Parameter error');
        }

        $chatMessages = [];
        foreach ($messages as $message) {
            if (
                !is_array($message)
                || !in_array($message['role'] ?? '', ['system', 'user', 'assistant'])
                || !isset($message['content'])
                || !is_string($message['content'])
                || $message['content'] === ''
            ) {
                throw new Exception('Parameter error');
            }
            $chatMessages[] = [
                'role'    => $message['role'],
                'content' => $message['content'],
            ];
        }

        return $chatMessages;
    }

    /**
     * 带重试的上游流式请求
     * 网络错误、5xx、429 在开始流式输出前重试
     * @throws Throwable
     */
    protected static function request(string $url, string $key, array $payload): ResponseInterface
    {
        $client = new Client([
            'timeout'         => 0,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'verify'          => false,
        ]);

        $lastException = null;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                usleep(self::RETRY_BASE_MS * 1000 * $attempt);
            }

            try {
                $response = $client->post($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $key,
                        'Accept'        => 'text/event-stream',
                        'Content-Type'  => 'application/json',
                    ],
                    'json'    => $payload,
                    'stream'  => true,
                ]);
            } catch (TransferException $e) {
                $lastException = $e;
                continue;
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode == 429 || $statusCode >= 500) {
                $response->getBody()->read(4096);
                $response->getBody()->close();
                $lastException = new Exception('AI API error ' . $statusCode);
                continue;
            }

            return $response;
        }

        throw $lastException ?? new Exception('AI API request failed');
    }
}
