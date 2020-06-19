<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\HasBuilder;
use tpext\cms\common\model\CmsCategory;
use tpext\cms\common\model\CmsContent as ContentModel;
use tpext\cms\common\model\CmsTag;
use tpext\cms\common\Module;
use tpext\myadmin\admin\model\AdminUser;

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
        $this->pagesize = 6;
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

        if (isset($searchData['is_show']) && $searchData['is_show'] != '') {
            $where[] = ['is_show', 'eq', $searchData['is_show']];
        }

        if (!empty($searchData['tags'])) {
            $where[] = ['tags', 'like', '%' . $searchData['tags'] . '%'];
        }

        if (isset($searchData['attr'])) {
            if (in_array('is_recommend', $searchData['attr'])) {
                $where[] = ['is_recommend', 'eq', 1];
            }
            if (in_array('is_hot', $searchData['attr'])) {
                $where[] = ['is_hot', 'eq', 1];
            }
            if (in_array('is_top', $searchData['attr'])) {
                $where[] = ['is_top', 'eq', 1];
            }
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

        $search->text('title', '标题', 4)->maxlength(20);
        $search->text('author', '作者', 4)->maxlength(20);
        $search->select('category_id', '栏目', 4)->options([0 => '请选择'] + $this->categoryModel->buildTree());
        $search->select('is_show', '显示', 4)->options([1 => '是', 0 => '否']);
        $search->select('tags', '标签', 4)->optionsData(CmsTag::all(), 'name');
        $search->checkbox('attr', '属性', 4)->options(['is_recommend' => '推荐', 'is_hot' => '热门', 'is_top' => '置顶']);
    }

    public function index()
    {
        $builder = $this->builder($this->pageTitle, $this->indexText);

        $tree = $builder->tree('1 left-tree');

        $tree->fill($this->categoryModel->all());

        $tree->trigger('.row-category_id');

        $this->table = $builder->table('1 right-list');

        $builder->addStyleSheet('
            .left-tree
            {
                width:12%;
                float:left;
            }

            .right-list
            {
                width:88%;
                float:right;
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
        $table->image('logo', '封面图')->default(url('/admin/upload/ext', ['type' => '暂无']))->thumbSize(80, 80);
        $table->text('title', '标题')->autoPost()->getWrapper()->addStyle('max-width:200px');
        $table->show('category', '栏目');
        $table->show('author', '作者')->default('暂无');
        $table->show('source', '来源')->default('暂无');
        $table->matches('tags', '标签')->optionsData(CmsTag::all());
        $table->switchBtn('is_show', '显示')->default(1)->autoPost();
        $table->checkbox('attr', '属性')->autoPost(url('editAttr'))->options(['is_recommend' => '推荐', 'is_hot' => '热门', 'is_top' => '置顶'])->inline(false);
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->text('click', '点击量')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->show('publish_time', '发布时间')->getWrapper()->addStyle('width:160px');
        $table->raw('times', '添加/修改时间')->getWrapper()->addStyle('width:160px');

        foreach ($data as &$d) {
            $d['times'] = $d['create_time'] . '<br>' . $d['update_time'];
        }

        unset($d);

        $table->sortable('id,sort,click');

        $table->getToolbar()
            ->btnAdd('', '添加', 'btn-primary', 'mdi-plus', 'data-layer-size="98%,98%"')
            ->btnEnableAndDisable('显示', '隐藏')
            ->btnDelete()
            ->btnRefresh();

        $table->getActionbar()
            ->btnEdit('', '', 'btn-primary', 'mdi-lead-pencil', 'data-layer-size="98%,98%"')
            ->btnDelete();
    }

    /**
     * Undocumented function
     * @title 属性修改
     *
     * @return mixed
     */
    public function editAttr()
    {
        $id = input('post.id/d', '');
        $value = input('post.value', '');

        if (empty($id)) {
            $this->error('参数有误');
        }

        $attr = explode(',', $value);

        $data = [];

        if (!empty($attr)) {
            $data['is_recommend'] = in_array('is_recommend', $attr);
            $data['is_hot'] = in_array('is_hot', $attr);
            $data['is_top'] = in_array('is_top', $attr);
        } else {
            $data['is_recommend'] = 0;
            $data['is_hot'] = 0;
            $data['is_top'] = 0;
        }

        $res = $this->dataModel->update($data, [$this->getPk() => $id]);

        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败，或无更改');
        }
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

        $form->fields('', '', 8)->size(0, 12)->showLabel(false);
        $form->defaultDisplayerSize(12, 12);

        $form->text('title', '标题')->required()->maxlength(55);
        $form->select('category_id', '栏目')->required()->options([0 => '请选择'] + $this->categoryModel->buildTree());
        $form->multipleSelect('tags', '标签')->optionsData(CmsTag::all(), 'name')->help('可到【标签管理】菜单添加标签');
        $form->tags('keyword', '关键字')->maxlength(255);
        $form->textarea('description', '摘要')->maxlength(255);

        $editor = 'editor';
        if (!empty($config['editor'])) {
            $editor = $config['editor'];
        }
        $form->$editor('content', '内容')->required();

        $form->fieldsEnd();

        $form->fields('', '', 4)->size(0, 12)->showLabel(false);

        $form->image('logo', '封面图')->mediumSize();
        $form->file('attachment', '附件')->mediumSize();
        $form->text('author', '作者')->maxlength(33)->default($admin ? $admin['name'] : '');
        $form->text('source', '来源')->maxlength(55)->default($admin ? $admin['group_name'] : '');
        $form->datetime('publish_time', '发布时间')->required()->default(date('Y-m-d H:i:s'));
        $form->number('click', '点击量')->default(0);
        $form->number('sort', '排序')->default(0);

        $form->checkbox('attr', '属性')->options(['is_recommend' => '推荐', 'is_hot' => '热门', 'is_top' => '置顶']);
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
            'keyword',
            'description',
            'logo',
            'attachment',
            'author',
            'source',
            'publish_time',
            'sort',
            'is_show',
            'content',
            'attr',
            'click',
        ], 'post');

        $result = $this->validate($data, [
            'title|标题' => 'require',
            'category_id|栏目' => 'require',
            'content|内容' => 'require',
        ]);

        if (true !== $result) {

            $this->error($result);
        }

        if (isset($data['attr']) && !empty($data['attr'])) {
            $data['is_recommend'] = in_array('is_recommend', $data['attr']);
            $data['is_hot'] = in_array('is_hot', $data['attr']);
            $data['is_top'] = in_array('is_top', $data['attr']);
        } else {
            $data['is_recommend'] = 0;
            $data['is_hot'] = 0;
            $data['is_top'] = 0;
        }

        if (isset($data['tags']) && !empty($data['tags'])) {
            $data['tags'] = ',' . implode(',', $data['tags']) . ',';
        } else {
            $data['tags'] = '';
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
            $data['create_user'] = session('admin_id');
            $res = $this->dataModel->create($data);
        }

        if (!$res) {
            $this->error('保存失败');
        }

        return $this->builder()->layer()->closeRefresh(1, '保存成功');
    }
}
