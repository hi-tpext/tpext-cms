<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions\HasAutopost;
use tpext\builder\traits\actions\HasIAED;
use tpext\cms\common\model\CmsTag as Tag;

/**
 * Undocumented class
 * @title 标签管理
 */
class Cmstag extends Controller
{
    use HasIAED;
    use HasAutopost;

    /**
     * Undocumented variable
     *
     * @var Tag
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new Tag;

        $this->pageTitle = '标签管理';
        $this->sortOrder = 'id desc';
        $this->pagesize = 8;
    }

    /**
     * 构建表单
     *
     * @param boolean $isEdit
     * @param array $data
     */
    protected function builForm($isEdit, &$data = [])
    {
        $form = $this->form;

        $form->text('name', '名称')->required();
        $form->image('logo', '封面图片');
        $form->textarea('description', '描述')->maxlength(255);
        $form->text('link', '链接');
        $form->switchBtn('is_show', '显示')->default(1);
        $form->number('sort', '排序')->default(0)->required();

        if ($isEdit) {
            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }
    }

    protected function filterWhere()
    {
        $searchData = request()->post();

        $where = [];
        if (!empty($searchData['name'])) {
            $where[] = ['name', 'like', '%' . $searchData['name'] . '%'];
        }

        return $where;
    }

    /**
     * 构建搜索
     *
     * @return void
     */
    protected function builSearch()
    {
        $search = $this->search;
        $search->text('name', '名称', 3)->maxlength(20);
    }

    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;
        $table->show('id', 'ID');
        $table->image('logo', '封面')->default(url('/admin/upload/ext', ['type' => 'empty'], '', false))->thumbSize(50, 50);
        $table->text('name', '名称')->autoPost('', true);
        $table->show('description', '描述')->getWrapper()->addStyle('width:30%;');
        $table->switchBtn('is_show', '显示')->default(1)->autoPost()->getWrapper()->addStyle('width:120px');
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('width:120px');

        $table->sortable('id,sort');
    }

    private function save($id = 0)
    {
        $data = request()->only([
            'name',
            'logo',
            'description',
            'link',
            'is_show',
            'sort',
        ], 'post');

        $result = $this->validate($data, [
            'name|名称' => 'require',
            'sort|排序' => 'require|number',
            'is_show' => 'require',
        ]);

        if (true !== $result) {

            $this->error($result);
        }

        if ($id) {
            $res = $this->dataModel->update($data, ['id' => $id]);
        } else {
            $res = $this->dataModel->create($data);
        }

        if (!$res) {
            $this->error('保存失败');
        }

        return $this->builder()->layer()->closeRefresh(1, '保存成功');
    }
}
