<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\HasBuilder;
use tpext\cms\admin\model\CmsPosition;
use tpext\cms\admin\model\CmsBanner as BannerModel;
use tpext\myadmin\admin\model\AdminUser;

/**
 * Undocumented class
 * @title 广告管理
 */
class Cmsbanner extends Controller
{
    use HasBuilder;

    /**
     * Undocumented variable
     *
     * @var BannerModel
     */
    protected $dataModel;
    /**
     * Undocumented variable
     *
     * @var CmsPosition
     */
    protected $positionModel;

    protected function initialize()
    {
        $this->dataModel = new BannerModel;
        $this->positionModel = new CmsPosition;
        $this->pageTitle = '广告管理';
        $this->enableField = 'is_show';
    }

    protected function filterWhere()
    {
        $searchData = request()->post();

        $where = [];
        if (!empty($searchData['title'])) {
            $where[] = ['title', 'like', '%' . $searchData['title'] . '%'];
        }

        if (!empty($searchData['position_id'])) {
            $where[] = ['position_id', 'eq', $searchData['position_id']];
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

        $search->text('title', '标题', 3)->maxlength(20);
        $search->select('position_id', '位置', 3)->optionsData($this->positionModel->all());
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
        $table->text('title', '标题')->autoPost()->getWrapper()->addStyle('max-width:200px');
        $table->show('position', '位置');
        $table->image('image', '图片')->default(url('/admin/upload/ext', ['type' => '暂无']))->thumbSize(150, 150);
        $table->show('description', '摘要')->default('暂无')->getWrapper()->addStyle('max-width:200px');
        $table->show('link', '链接');
        $table->switchBtn('is_show', '显示')->default(1)->autoPost();
        $table->show('create_time', '添加时间')->getWrapper()->addStyle('width:180px');
        $table->show('update_time', '修改时间')->getWrapper()->addStyle('width:180px');

        $table->getToolbar()
            ->btnAdd()
            ->btnEnableAndDisable('显示', '隐藏')
            ->btnDelete()
            ->btnRefresh();

        $table->getActionbar()
            ->btnEdit()
            ->btnDelete();
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

        $form->text('title', '标题')->required()->maxlength(55);
        $form->select('position_id', '位置')->required()->optionsData($this->positionModel->all());
        $form->textarea('description', '摘要')->maxlength(555);
        $form->image('image', '图片')->mediumSize();
        $form->number('sort', '排序')->default(0);
        $form->switchBtn('is_show', '显示')->default(1);
        if ($isEdit) {
            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }
    }

    /**
     * 保存数据
     *
     * @param integer $id
     * @return void
     */
    private function save($id = 0)
    {
        $data = request()->only([
            'title',
            'position_id',
            'description',
            'image',
            'sort',
            'is_show',
        ], 'post');

        $result = $this->validate($data, [
            'title|标题' => 'require',
            'image|图片' => 'require',
            'position_id|位置' => 'require',
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
