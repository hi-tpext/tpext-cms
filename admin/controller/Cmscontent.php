<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsContent as ContentModel;
use tpext\cms\common\model\CmsTag;
use tpext\cms\common\Module;

/**
 * Undocumented class
 * @title 内容管理
 */
class Cmscontent extends Controller
{
    use actions\HasBase;
    use actions\HasAdd;
    use actions\HasIndex;
    use actions\HasEdit;
    use actions\HasView;
    use actions\HasDelete;
    use actions\HasEnable;
    use actions\HasAutopost;

    /**
     * Undocumented variable
     *
     * @var ContentModel
     */
    protected $dataModel;
    /**
     * Undocumented variable
     *
     * @var CmsChannel
     */
    protected $channelModel;

    protected function initialize()
    {
        $this->dataModel = new ContentModel;
        $this->channelModel = new CmsChannel;
        $this->pageTitle = '内容管理';
        $this->enableField = 'is_show';
        $this->pagesize = 6;

        $this->selectSearch = 'title';
        $this->selectFields = 'id,title,channel_id';
        $this->selectTextField = 'title';
        $this->selectWith = ['channel'];

        $this->indexWith = ['channel'];

        //左侧树
        $this->treeModel = $this->channelModel; //分类模型
        $this->treeTextField = 'name'; //分类模型中的分类名称字段
        $this->treeKey = 'channel_id'; //关联的键　localKey
        $this->treeType = 'jstree';
    }

    protected function filterWhere()
    {
        $searchData = request()->get();

        $where = [];
        if (!empty($searchData['title'])) {
            $where[] = ['title', 'like', '%' . $searchData['title'] . '%'];
        }

        if (!empty($searchData['author'])) {
            $where[] = ['author', 'like', '%' . $searchData['author'] . '%'];
        }

        if (!empty($searchData['channel_id'])) {
            $where[] = ['channel_id', '=', $searchData['channel_id']];
        }

        if (isset($searchData['is_show']) && $searchData['is_show'] != '') {
            $where[] = ['is_show', '=', $searchData['is_show']];
        }

        if (!empty($searchData['tags'])) {
            $where[] = ['tags', 'like', '%' . $searchData['tags'] . '%'];
        }

        if (isset($searchData['attr'])) {
            if (in_array('is_recommend', $searchData['attr'])) {
                $where[] = ['is_recommend', '=', 1];
            }
            if (in_array('is_hot', $searchData['attr'])) {
                $where[] = ['is_hot', '=', 1];
            }
            if (in_array('is_top', $searchData['attr'])) {
                $where[] = ['is_top', '=', 1];
            }
        }

        return $where;
    }

    /**
     * 构建搜索
     *
     * @return void
     */
    protected function buildSearch()
    {
        $search = $this->search;

        $search->text('title', '标题', 3)->maxlength(50);
        $search->text('author', '作者', 3)->maxlength(20);
        $search->select('channel_id', '栏目', 3)->dataUrl(url('/admin/cmschannel/selectPage'));
        $search->select('is_show', '显示', 3)->options([1 => '是', 0 => '否']);
        $search->select('tags', '标签', 3)->dataUrl(url('/admin/cmstag/selectPage'));
        $search->checkbox('attr', '属性', 3)->options(['is_recommend' => '推荐', 'is_hot' => '热门', 'is_top' => '置顶']);
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
        $table->image('logo', '封面图')->thumbSize(60, 60);
        $table->text('title', '标题')->autoPost()->getWrapper()->addStyle('max-width:200px');
        $table->show('channel_id', '栏目')->to('{channel.full_name}');
        $table->show('author', '作者')->default('暂无');
        $table->show('source', '来源')->default('暂无');
        $table->matches('tags', '标签')->optionsData(CmsTag::select());
        $table->switchBtn('is_show', '显示')->default(1)->autoPost();
        $table->checkbox('attr', '属性')->autoPost(url('editAttr'))->options(['is_recommend' => '推荐', 'is_hot' => '热门', 'is_top' => '置顶'])->inline(false);
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->text('click', '点击量')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->show('publish_time', '发布时间')->getWrapper()->addStyle('width:160px');
        $table->fields('create_time', '添加/修改时间')->with(
            $table->show('create_time', '添加时间'),
            $table->show('update_time', '修改时间'),
        )->getWrapper()->addStyle('width:160px');

        $table->sortable('id,channel_id,author,source,is_show,publish_time,sort,click,create_time,update_time');

        $table->getToolbar()
            ->btnAdd('', '添加', 'btn-primary', 'mdi-plus', 'data-layer-size="98%,98%"')
            ->btnEnableAndDisable('显示', '隐藏')
            ->btnDelete()
            ->btnRefresh();

        $table->getActionbar()
            ->btnEdit('', '', 'btn-primary', 'mdi-lead-pencil', 'data-layer-size="98%,98%" title="编辑"')
            ->btnView('', '', 'btn-info', 'mdi-eye-outline', 'data-layer-size="98%,98%" title="查看"')
            ->btnLink('copy', url('copy', ['id' => '__data.pk__']), '', 'btn-success', 'mdi-content-copy', 'data-layer-size="1000px,auto" title="复制"')
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
     * @title 复制
     *
     * @return mixed
     */
    public function copy()
    {
        $id = input('id/d');

        $content = $this->dataModel->where('id', $id)->find();

        $builder = $this->builder($this->pageTitle, '复制');
        if (request()->isGet()) {
            if (!$content) {
                return $builder->layer()->closeRefresh(0, '数据不存在');
            }
            $form = $builder->form();
            $form->show('title', '标题')->value($content['title']);
            $form->selectTree('channel_id', '复制到栏目')->multiple(false)->optionsData($this->channelModel->select(), 'name', 'id', 'parent_id', '')->required();

            return $builder;
        }
        $data = request()->post();
        if ($data['channel_id'] == $content['channel_id']) {
            $this->error('复制到栏目不能和原栏目相同');
        }
        $newData = new ContentModel;
        $content['reference_id'] = $id;
        $content['content'] = '@' . $id;
        $content['channel_id'] = $data['channel_id'];
        unset($content['id']);
        $res = $newData->save($content->toArray());

        if ($res) {
            return $builder->layer()->closeRefresh(1, '复制成功');
        } else {
            $this->error('复制失败');
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
        $config = Module::getInstance()->getConfig();

        $form = $this->form;

        $admin = !$isEdit ? (session('admin_User') ?: []) : null;

        $form->fields('', '', 7)->size(0, 12)->showLabel(false);
        $form->defaultDisplayerSize(12, 12);

        $form->hidden('id');
        $form->text('title', '标题')->required()->maxlength(55);
        $form->selectTree('channel_id', '栏目')->multiple(false)->optionsData($this->channelModel->select(), 'name', 'id', 'parent_id', '')->required();
        $form->multipleSelect('tags', '标签')->dataUrl(url('/admin/cmstag/selectPage'))->help('可到【标签管理】菜单添加标签');
        $form->tags('keyword', '关键字');
        $form->text('link', '跳转链接')->help('设置后覆盖默认的页面地址');
        $form->textarea('description', '摘要')->help('留空则自动从内容中提取')->maxlength(255);

        $editor = 'editor';
        if (!empty($config['editor'])) {
            $editor = $config['editor'];
        }
        if ($isEdit && $data['reference_id'] > 0) {
            $form->raw('content_html', '内容')->to('<div>复制于<label class="label label-default">@{reference_id}</label>，内容只读，去<a title="点击去编辑" href="' . url('edit', ['id' => $data['reference_id']]) . '">[编辑]</a></div>' . $data['content']);
            $form->hidden('content')->value('@' . $data['reference_id']);
            $form->hidden('reference_id');
        } else {
            $form->$editor('content', '内容')->required();
        }


        $form->fieldsEnd();

        $form->fields('', '', 5)->size(0, 12)->showLabel(false);

        $form->image('logo', '封面图')->smallSize();
        $form->file('attachment', '附件')->smallSize();
        $form->text('author', '作者', 6)->maxlength(33)->default($admin ? $admin['name'] : '');
        $form->text('source', '来源', 6)->maxlength(55)->default($admin && $admin['group'] ? $admin['group']['name'] : '');
        $form->datetime('publish_time', '发布时间')->required()->default(date('Y-m-d H:i:s'));
        $form->number('click', '点击量', 6)->default(0);
        $form->number('sort', '排序', 6)->default(0);

        $form->checkbox('attr', '属性')->options(['is_recommend' => '推荐', 'is_hot' => '热门', 'is_top' => '置顶']);
        $form->switchBtn('is_show', '显示')->default(1);

        if ($isEdit) {
            $form->show('create_time', '添加时间', 6);
            $form->show('update_time', '修改时间', 6);
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
            'id',
            'title',
            'channel_id',
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
            'reference_id',
        ], 'post');

        $result = $this->validate($data, [
            'title|标题' => 'require',
            'channel_id|栏目' => 'require',
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

        if ($data['channel_id']) {
            $parent = $this->channelModel->find($data['channel_id']);
            if ($parent && $parent['type'] == 2) {
                $this->error($parent['name'] . '是目录，不允许存放文章，请重新选择');
            }
        }

        if (!$id) {
            $data['create_user'] = session('admin_id');
        }

        return $this->doSave($data, $id);
    }
}
