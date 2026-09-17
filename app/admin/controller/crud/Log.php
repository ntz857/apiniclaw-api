<?php

namespace app\admin\controller\crud;

use Throwable;
use app\admin\model\CrudLog;
use app\admin\library\crud\Helper;
use app\common\controller\Backend;

/**
 * crud记录
 *
 */
class Log extends Backend
{
    /**
     * Log模型对象
     * @var object
     * @phpstan-var CrudLog
     */
    protected object $model;

    protected string|array $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id', 'table_name', 'comment'];

    protected array $noNeedPermission = ['index'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CrudLog();

        if (!$this->auth->check('crud/crud/index')) {
            $this->error(__('You have no permission'), [], 401);
        }
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit)->each(function ($item) {
                $webLangDir      = Helper::parseWebDirNameData($item['table']['name'], 'lang', $item['table']['webViewsDir']);
                $item['lang_en'] = $webLangDir['en'] . '.ts';
                $item['lang_cn'] = $webLangDir['zh-cn'] . '.ts';
            });

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}