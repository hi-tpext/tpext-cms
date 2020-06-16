<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions\HasAutopost;
use tpext\builder\traits\actions\HasIAED;
use tpext\cms\admin\model\CmsCategory as Category;

/**
 * Undocumented class
 * @title 栏目管理
 */
class Cmscategory extends Controller
{
    use HasIAED;
    use HasAutopost;

    /**
     * Undocumented variable
     *
     * @var Category
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new Category;

        $this->pageTitle = '栏目管理';
        $this->sortOrder = 'id desc';
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

        $tree = [0 => '根栏目'];

        $tree += $this->dataModel->buildTree(0, 0, $isEdit ? $data['id'] : 0); //数组合并不要用 array_merge , 会重排数组键 ，作为options导致bug

        $form->text('name', '名称')->required();
        $form->select('parent_id', '上级')->required()->options($tree);
        $form->text('link', '链接');
        $form->image('logo', '封面图片');
        $form->switchBtn('is_show', '显示')->default(1);
        $form->radio('type', '类型')->default(1)->options([1 => '不限', 2 => '目录', 3 => '分类'])->required()->help('目录有下级，不能存文章。分类无下级，只能存文章');
        $form->text('sort', '排序')->default(0)->required();

        if ($isEdit) {
            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }
    }

    /**
     * Undocumented function
     *
     * @param Table $table
     * @return void
     */
    protected function buildDataList()
    {
        $table = $this->table;

        $table->sortable([]);

        $data = $this->dataModel->buildList(0, 0);
        $this->buildTable($data);
        $table->fill($data);
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
        $table->raw('title_show', '名称')->getWrapper()->addStyle('text-align:left;');
        $table->show('link', '链接')->default('暂无');
        $table->text('name', '名称')->autoPost('', true)->getWrapper()->addStyle('max-width:80px');
        $table->switchBtn('is_show', '显示')->default(1)->autoPost()->getWrapper()->addStyle('max-width:120px');
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('max-width:40px');
        $table->show('create_time', '添加时间')->getWrapper()->addStyle('width:180px');
        $table->show('update_time', '修改时间')->getWrapper()->addStyle('width:180px');

        $table->sortable([]);
    }

    private function save($id = 0)
    {
        $data = request()->only([
            'name',
            'parent_id',
            'logo',
            'link',
            'is_show',
            'type',
            'sort',
        ], 'post');

        $result = $this->validate($data, [
            'name|名称' => 'require',
            'sort|排序' => 'require|number',
            'type|类型' => 'require|number',
            'parent_id|上级' => 'require|number',
            'is_show' => 'require',
        ]);

        if (true !== $result) {

            $this->error($result);
        }

        if ($data['parent_id']) {
            $parent = $this->dataModel->get($data['parent_id']);
            if ($parent && $parent['type'] == 3) {
                $this->error($parent['name'] . '不允许有下级栏目，请重新选择');
            }
        }

        if ($id) {
            if ($data['parent_id'] == $id) {
                $this->error('上级不能是自己');
            }
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
