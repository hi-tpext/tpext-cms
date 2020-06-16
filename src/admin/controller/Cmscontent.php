<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\HasBuilder;
use tpext\cms\admin\model\CmsCategory;
use tpext\cms\admin\model\CmsContent as ContentModel;
use tpext\myadmin\admin\model\AdminUser;
use tpext\cms\common\Module;

/**
 * Undocumented class
 * @title 内容管理
 */
class Cmscontent extends Controller
{
    use HasBuilder;

    /**
     * Undocumented variable
     *
     * @var ContentModel
     */
    protected $dataModel;
    /**
     * Undocumented variable
     *
     * @var CmsCategory
     */
    protected $categoryModel;

    protected function initialize()
    {
        $this->dataModel = new ContentModel;
        $this->categoryModel = new CmsCategory;
        $this->pageTitle = '内容管理';
        $this->enableField = 'is_show';
    }

    protected function filterWhere()
    {
        $searchData = request()->post();

        $where = [];
        if (!empty($searchData['title'])) {
            $where[] = ['title', 'like', '%' . $searchData['title'] . '%'];
        }

        if (!empty($searchData['author'])) {
            $where[] = ['author', 'like', '%' . $searchData['author'] . '%'];
        }

        if (!empty($searchData['category_id'])) {
            $where[] = ['category_id', 'eq', $searchData['category_id']];
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
        $search->text('author', '作者', 3)->maxlength(20);
        $search->select('category_id', '栏目', 3)->options([0 => '请选择'] + $this->categoryModel->buildTree());
    }

    public function index()
    {
        $builder = $this->builder($this->pageTitle, $this->indexText);

        $tree = $builder->tree('2 left-tree');

        $tree->fill($this->categoryModel->all());

        $tree->trigger('.row-category_id');

        $this->table = $builder->table('10 right-list');

        $builder->addStyleSheet('
            .left-tree
            {
                widht:15%;
            }

            .right-list
            {
                widht:85%;
            }
        ');

        $this->table->pk($this->getPk());
        $this->search = $this->table->getSearch();

        $this->builSearch();
        $this->buildDataList();

        if (request()->isAjax()) {
            return $this->table->partial()->render();
        }

        return $builder->render();
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
        $table->image('logo', '封面')->default(url('/admin/upload/ext', ['type' => '暂无']))->thumbSize(50, 50);
        $table->text('title', '标题')->autoPost()->getWrapper()->addStyle('max-width:200px');
        $table->show('category', '栏目');
        $table->show('author', '作者')->default('暂无');
        $table->show('source', '来源')->default('暂无');
        $table->show('description', '摘要')->default('暂无')->getWrapper()->addStyle('max-width:200px');
        $table->match('status', '状态')->options([1 => '待审核', 2 => '已审核', 3 => '已拒绝']);
        $table->switchBtn('is_show', '显示')->default(1)->autoPost();
        $table->show('publish_time', '发布时间')->getWrapper()->addStyle('width:180px');
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
        $config = Module::getInstance()->getConfig();

        $form = $this->form;

        $admin = !$isEdit ? AdminUser::current() : null;

        $form->text('title', '标题')->required()->maxlength(55);
        $form->select('category_id', '栏目')->required()->options([0 => '请选择'] + $this->categoryModel->buildTree());
        $form->tags('tags', '标签');
        $form->textarea('description', '摘要')->maxlength(555);
        $form->image('logo', '封面')->mediumSize();

        $editor = 'editor';
        if (!empty($config['editor'])) {
            $editor = $config['editor'];
        }

        $form->$editor('content', '内容')->size(2, 10);
        $form->text('author', '作者')->maxlength(33)->default($admin ? $admin['name'] : '');
        $form->text('source', '来源')->maxlength(55)->default($admin ? $admin['group_name'] : '');
        $form->datetime('publish_time', '发布时间')->required()->default(date('Y-m-d H:i:s'));
        $form->number('sort', '排序')->default(0);
        $form->radio('status', '状态')->options([1 => '待审核', 2 => '已审核', 3 => '已拒绝'])->default(1);
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
            'category_id',
            'tags',
            'description',
            'logo',
            'author',
            'source',
            'publish_time',
            'sort',
            'status',
            'is_show',
            'content',
        ], 'post');

        $result = $this->validate($data, [
            'title|标题' => 'require',
            'category_id|栏目' => 'require',
            'content|内容' => 'require'
        ]);

        if (true !== $result) {

            $this->error($result);
        }

        if ($data['category_id']) {
            $parent = $this->categoryModel->get($data['category_id']);
            if ($parent && $parent['type'] == 2) {
                $this->error($parent['name'] . '是目录，不允许存放文章，请重新选择');
            }
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
