<?php

use think\facade\Db;
use app\common\model\Config;
use think\migration\Migrator;

class Version239 extends Migrator
{
    /**
     * @throws Throwable
     */
    public function up(): void
    {

        Config::addConfigGroup('ai', 'AI');
        $exist = Db::name('config')->where('name', 'ai_api_url')->value('id');
        if (!$exist) {
            $rows  = [
                [
                    'name'  => 'ai_api_url',
                    'group' => 'ai',
                    'title' => 'API URL',
                    'type'  => 'string',
                    'value' => '',
                    'rule'  => 'required',
                    'weigh' => 9,
                    'tip'   => '请填写 OpenAI Responses API 兼容的 URL，如: https://api.deepseek.com/responses',
                ],
                [
                    'name'  => 'ai_api_key',
                    'group' => 'ai',
                    'title' => 'API Key',
                    'type'  => 'password',
                    'value' => '',
                    'rule'  => 'required',
                    'weigh' => 8,
                    'tip'   => '',
                ],
                [
                    'name'  => 'ai_model_list',
                    'group' => 'ai',
                    'title' => 'Model list',
                    'type'  => 'array',
                    'value' => '[{"key":"DeepSeek V4 Pro","value":"deepseek-v4-pro"}]',
                    'rule'  => 'required',
                    'weigh' => 7,
                    'tip'   => '',
                ],
                [
                    'name'  => 'ai_default_model',
                    'group' => 'ai',
                    'title' => 'Default model',
                    'type'  => 'hidden',
                    'value' => 'deepseek-v4-pro',
                    'rule'  => 'required',
                    'weigh' => 7,
                    'tip'   => '',
                ],
            ];
            $table = $this->table('config');
            $table->insert($rows)->saveData();
        }
    }
}
