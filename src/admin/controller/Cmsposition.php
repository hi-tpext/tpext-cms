<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions\HasAutopost;
use tpext\builder\traits\actions\HasIAED;
use tpext\cms\admin\model\CmsPosition as Position;

/**
 * Undocumented class
 * @title 广告位置
 */
class Cmsposition extends Controller
{
    use HasIAED;
    use HasAutopost;

    /**
     * Undocumented variable
     *
     * @var Position
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new Position;

        $this->pageTitle = '广告位置';
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

        $form->text('name', '名称')->required();
        $form->image('logo', '封面图片');
        $form->switchBtn('is_show', '显示')->default(1);
        $form->datetime('start_time', '开始时间')->required()->default(date('Y-m-d 00:00:00'));
        $form->datetime('end_time', '结束时间')->required()->default(date('Y-m-d 00:00:00', strtotime('+1year')));
        $form->text('sort', '排序')->default(0)->required();

        if ($isEdit) {
            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }
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
        $table->text('name', '名称')->autoPost('', true)->getWrapper()->addStyle('max-width:80px');
        $table->switchBtn('is_show', '显示')->default(1)->autoPost()->getWrapper()->addStyle('max-width:120px');
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('max-width:40px');
        $table->show('start_time', '开始时间')->getWrapper()->addStyle('width:180px');
        $table->show('end_time', '结束时间')->getWrapper()->addStyle('width:180px');
    }

    private function save($id = 0)
    {
        $data = request()->only([
            'name',
            'logo',
            'is_show',
            'start_time',
            'end_time',
            'sort',
        ], 'post');

        $result = $this->validate($data, [
            'name|名称' => 'require',
            'sort|排序' => 'require|number',
            'start_time|开始时间' => 'require|date',
            'end_time|结束时间' => 'require|date',
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
