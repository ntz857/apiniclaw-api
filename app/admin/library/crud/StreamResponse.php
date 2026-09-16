<?php

namespace app\admin\library\crud;

use Closure;
use think\Response;
use Psr\Http\Message\StreamInterface;

/**
 * Server-Sent Events 流式响应
 */
class StreamResponse extends Response
{
    public function __construct(Closure $callback, int $code = 200)
    {
        $this->data       = $callback;
        $this->code       = $code;
        $this->allowCache = false;
        $this->header     = [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /**
     * 将上游 SSE 响应体转发给客户端
     */
    public static function body(StreamInterface $body): static
    {
        return new static(function () use ($body) {
            try {
                $buffer = '';
                while (!$body->eof()) {
                    $chunk = $body->read(8192);
                    if ($chunk === '') {
                        break;
                    }
                    $buffer .= $chunk;
                    while (($position = strpos($buffer, "\n")) !== false) {
                        $line   = substr($buffer, 0, $position);
                        $buffer = substr($buffer, $position + 1);
                        if (!self::writeLine($line)) {
                            return;
                        }
                    }
                }
                if ($buffer !== '' && !self::writeLine($buffer)) {
                    return;
                }
            } finally {
                $body->close();
            }
        });
    }

    /**
     * 发送一条错误事件，不改变 HTTP 状态码
     */
    public static function error(string $message): static
    {
        return new static(function () use ($message) {
            $payload = json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            self::writeLine('event: error');
            self::writeLine('data: ' . $payload);
            self::writeLine('');
        });
    }

    /**
     * 输出一行 SSE 数据并刷新输出缓冲区
     */
    protected static function writeLine(string $line): bool
    {
        $line = rtrim($line, "\r");
        if ($line !== '' && !str_starts_with($line, 'event:') && !str_starts_with($line, 'data:')) {
            return true;
        }

        echo $line . "\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        return !connection_aborted();
    }

    protected function output($data): string
    {
        return '';
    }

    protected function sendData(string $data): void
    {
        ($this->data)();
    }
}
