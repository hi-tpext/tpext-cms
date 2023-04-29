<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\model\CmsChannel as ChannelModel;

/**
 * Undocumented class
 * @title 栏目管理
 */
class Cmschannel extends Controller
{
    use actions\HasBase;
    use actions\HasAdd;
    use actions\HasIndex;
    use actions\HasEdit;
    use actions\HasView;
    use actions\HasDelete;
    use actions\HasAutopost;

    /**
     * Undocumented variable
     *
     * @var ChannelModel
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new ChannelModel;

        $this->pageTitle = '栏目管理';
        $this->sortOrder = 'id desc';
        $this->pagesize = 8;

        $this->selectSearch = 'name';
        $this->selectFields = 'id,name,full_name';
        $this->selectTextField = 'name';
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
        $table->raw('__text__', '结构')->getWrapper()->addStyle('text-align:left;');
        $table->image('logo', '封面图')->thumbSize(50, 50);
        $table->show('link', '链接')->default('暂无');
        $table->text('name', '名称')->autoPost('', true);
        $table->switchBtn('is_show', '显示')->default(1)->autoPost()->getWrapper()->addStyle('width:80px');
        $table->match('type', '类型')->default(1)->options([1 => '不限', 2 => '目录', 3 => '分类'])->getWrapper()->addStyle('width:80px');
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->text('pagesize', '分页大小')->autoPost()->getWrapper()->addStyle('width:80px');
        $table->show('content_count', '内容统计')->getWrapper()->addStyle('width:80px');
        $table->show('create_time', '添加时间')->getWrapper()->addStyle('width:180px');
        $table->show('update_time', '修改时间')->getWrapper()->addStyle('width:180px');

        $table->sortable([]);

        $table->getActionbar()
            ->btnLink('add', url('add', ['parend_id' => '__data.pk__']), '', 'btn-secondary', 'mdi-plus', 'title="添加下级"')
            ->btnEdit()
            ->btnView()
            ->btnDelete()
            ->mapClass([
                'add' => [
                    'hidden' => '__hi_add__'
                ]
            ]);

        foreach ($data as &$d) {
            $d['__hi_add__'] = $d['type'] == 3;
        }
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

        $tree = [0 => '根栏目'];

        $tree += $this->dataModel->getOptionsData($isEdit ? $data['id'] : 0); //数组合并不要用 array_merge , 会重排数组键 ，作为options导致bug

        $form->text('name', '名称')->required();
        $form->select('parent_id', '上级')->required()->options($tree)->default(input('parend_id'));
        // $form->select('channel_template_id', '栏目模板')->dataUrl(url('/admin/cmstemplate/selectpage'));
        // $form->select('content_template_id', '内容模板')->dataUrl(url('/admin/cmstemplate/selectpage'));
        $form->text('pagesize', '分页大小');
        $form->text('link', '跳转链接')->help('设置后覆盖默认的页面地址');
        $form->image('logo', '封面图');
        $form->switchBtn('is_show', '显示')->default(1);
        $form->radio('type', '类型')->default(1)->options([1 => '不限', 2 => '目录', 3 => '分类'])->required()->help('目录有下级，不能存文章。分类无下级，只能存文章');
        $form->number('sort', '排序')->default(0)->required();

        if ($isEdit) {
            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }
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

        if ($data['parent_id'] == 0) {
            $data['full_name'] = $data['name'];
            $data['path'] = '';
            $data['deep'] = 0;
        } else {
            $upNodes = $this->dataModel->getUpperNodes($data);
            $names = [];
            $ids = [];

            foreach ($upNodes as $node) {
                $names[] = $node['name'];
                $ids[] = $node['id'];
            }

            $data['full_name'] = implode('->', array_reverse($names));
            $data['path'] = implode(',', array_reverse($ids));

            $parent = $this->dataModel->find($data['parent_id']);

            if ($parent) {
                $data['deep'] = $parent['deep'] + 1;
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
