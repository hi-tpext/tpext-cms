<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\taglib\Table;
use tpext\manager\common\logic\DbLogic;

/**
 * @title 标签列表
 */
class Cmsusetag extends Controller
{
    use actions\HasBase;
    use actions\HasIndex;

    protected function initialize()
    {
        $this->pageTitle = '标签列表';
        $this->pagesize = 50;
        $this->pk = 'tag_name';
    }

    /**
     * 构建表格
     *
     * @param array $data
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;
        $table->show('tag_name', '标签名称');
        $table->show('desc', '数据表');
        $table->raw('db_table', '数据表名')->to('<a target="_blank" href="{table_url}">{val}</a>');
        $table->show('id_key', '主键');
        $table->show('cid_key', '分类键');
        $table->show('pid_key', '父级键');
        $table->show('default_order', '默认排序');
        $table->show('default_scope', '默认条件');
        $table->show('operations', '支持操作');

        $table->getToolbar()
            ->btnRefresh();

        $table->getActionbar()
            ->btnView();
    }

    /**
     * 构建数据列表
     *
     * @param array $where
     * @param string $sortOrder
     * @param int $page
     * @param int $total
     * @return array
     */
    protected function buildDataList($where = [], $sortOrder = '', $page = 1, &$total = -1)
    {
        $tables = Table::getTables();
        $result = [];

        $dbLogic = new DbLogic;

        foreach ($tables as $dbTable => $info) {
            if (empty($info['tag_name'])) {
                continue;
            }

            $tableName = $info['desc'] ?? $dbTable;

            // 获取该标签支持的操作
            $operations = ['list'];
            if (!empty($info['id_key'])) {
                $operations[] = 'get';
                $operations[] = 'prev';
                $operations[] = 'next';
            }
            if (!empty($info['pid_key'])) {
                $operations[] = 'parents';
            }

            $result[] = [
                'tag_name' => $info['tag_name'],
                'desc' => $tableName,
                'db_table' => $dbTable,
                'id_key' => $info['id_key'] ?? '-',
                'cid_key' => $info['cid_key'] ?: '-',
                'pid_key' => $info['pid_key'] ?: '-',
                'default_order' => $info['default_order'] ?? '-',
                'default_scope' => $info['default_scope'] ?? '-',
                'operations' => implode('、', $operations),
                'tag_info' => $info,
                'table_url' => url('/admin/dbtable/datalist', ['name' => $dbLogic->getPrefix() . $dbTable])
            ];
        }

        $total = count($result);
        return $result;
    }

    /**
     * 查看详情
     *
     * @return void
     */
    public function view()
    {
        $tag_name = input('id');

        $builder = $this->builder($this->pageTitle, '查看详情', 'view');

        $data = $this->getDataById($tag_name);
        if (!$data) {
            return $builder->layer()->close(0, '数据不存在');
        }

        $form = $builder->form();
        $this->form = $form;
        $this->buildForm(true, $data);
        $form->fill($data);
        $form->readonly();

        return $builder->render();
    }

    /**
     * 构建表单
     *
     * @param boolean $isEdit
     * @param array $data
     */
    protected function buildForm($isEdit, &$data = [])
    {
        $form = $this->form;
        $form->show('tag_name', '标签名称');
        $form->show('desc', '数据表');
        $form->show('db_table', '数据表名');
        $form->show('operations', '支持操作');
        $form->show('description', '说明');

        // 显示标签详细配置
        $tagInfo = $data['tag_info'] ?? [];
        if (!empty($tagInfo)) {
            $form->divider('标签详细配置');
            $form->show('tag_info.id_key', '主键字段');
            $form->show('tag_info.cid_key', '分类字段')->default('无');
            $form->show('tag_info.pid_key', '父级字段')->default('无');
            $form->show('tag_info.default_order', '默认排序');
            $form->show('tag_info.default_fields', '默认字段');
            $form->show('tag_info.default_scope', '默认条件');
        }

        // 标签使用演示
        $tagName = $data['tag_name'] ?? '';
        $dbTable = $data['db_table'] ?? '';
        $idKey = $tagInfo['id_key'] ?? '';
        $cidKey = $tagInfo['cid_key'] ?? '';
        $pidKey = $tagInfo['pid_key'] ?? '';

        $form->divider('标签使用演示');

        // 基本用法
        $code = "基本列表:\n";
        $code .= "{cms:{$tagName}@list}...{/cms:{$tagName}@list}\n\n";

        // 带参数的列表
        $code .= "带参数列表:(num为不分页)\n";
        $code .= "{cms:{$tagName}@list num=\"10\" order=\"sort asc\"}...{/cms:{$tagName}@list}\n\n";

        // 带分类过滤
        if (!empty($cidKey)) {
            $code .= "分类过滤:\n";
            $code .= "{cms:{$tagName}@list cid=\"3\"}...{/cms:{$tagName}@list}\n\n";
            $code .= "分类过滤:(多个分类)\n";
            $code .= "{cms:{$tagName}@list cid=\"3,4,5\"}...{/cms:{$tagName}@list}\n\n";
            $code .= "分类过滤:(大于3小于等于10)\n";
            $code .= "{cms:{$tagName}@list cid=\"gt 3 elt 10\"}...{/cms:{$tagName}@list}\n\n";
        }

        // 分页列表
        $code .= "分页列表:(pagesize为分页大小)\n";
        $code .= "{cms:{$tagName}@list pagesize=\"10\"}...{/cms:{$tagName}@list}\n\n";

        // where条件
        $code .= "where条件:\n";
        if (!empty($cidKey)) {
            $code .= "{cms:{$tagName}@list where=\"{$cidKey}>3\"}...{/cms:{$tagName}@list}\n\n";
        } else {
            $code .= "{cms:{$tagName}@list where=\"{$idKey}>3\"}...{/cms:{$tagName}@list}\n\n";
        }

        // 上一篇/下一篇 (仅内容表)
        if ($dbTable === 'cms_content') {
            $code .= "上一篇/下一篇:\n";
            $code .= "{cms:{$tagName}@prev}...{/cms:{$tagName}@prev}\n";
            $code .= "{cms:{$tagName}@next}...{/cms:{$tagName}@next}\n\n";
        }

        // 父级列表
        if (!empty($pidKey)) {
            $code .= "父级列表:\n";
            $code .= "{cms:{$tagName}@parents}...{/cms:{$tagName}@parents}\n";
        }

        $form->raw('_demo', '使用演示')->default("<pre style='background:#f5f5f5;padding:10px;border-radius:4px;'>{$code}</pre>");
    }

    /**
     * 获取单条数据
     *
     * @param string $tag_name
     * @return array|null
     */
    protected function getDataById($tag_name)
    {
        $dataList = $this->buildDataList();
        foreach ($dataList as $item) {
            if ($item['tag_name'] == $tag_name) {
                return $item;
            }
        }
        return null;
    }
}
