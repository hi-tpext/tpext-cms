<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\cms\common\Module;
use tpext\builder\traits\actions;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsContent as ContentModel;

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
        $this->pagesize = 8;

        $this->selectSearch = 'title';
        $this->selectFields = 'id,title,channel_id';
        $this->selectTextField = '{title}({channel.name})';
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
        $table->raw('title', '标题')->to('<a href="{preview_url}" target="_blank">{val}</a>');
        $table->show('channel_id', '栏目')->to('{channel.full_name}');
        $table->show('author', '作者')->cut(10)->default('暂无');
        $table->show('source', '来源')->cut(10)->default('暂无');
        $table->show('tag_names', '合集')->cut(20);
        $table->switchBtn('is_show', '显示')->default(1)->autoPost();
        $table->checkbox('attr', '属性')->autoPost(url('editAttr'))->options(['is_recommend' => '推荐', 'is_top' => '置顶', 'is_hot' => '热门'])->inline(false);
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->show('publish_time', '发布时间')->getWrapper()->addStyle('width:140px');
        $table->fields('create_time', '添加/修改时间')->with(
            $table->show('create_time', '添加时间'),
            $table->show('update_time', '修改时间'),
        )->getWrapper()->addStyle('width:140px');

        $table->sortable('id,channel_id,author,source,is_show,publish_time,sort,click,create_time,update_time');

        $table->getToolbar()
            ->btnAdd('', '添加', 'btn-primary', 'mdi-plus', 'data-layer-size="98%,98%"')
            ->btnEnableAndDisable('显示', '隐藏')
            ->btnDelete()
            ->btnRefresh();

        $table->getActionbar()
            ->btnEdit('', '', 'btn-primary', 'mdi-lead-pencil', 'data-layer-size="98%,98%" title="编辑"')
            ->btnView('', '', 'btn-info', 'mdi-eye-outline', 'data-layer-size="98%,98%" title="查看"')
            ->br()
            ->btnLink('copy', url('copy', ['id' => '__data.pk__']), '', 'btn-success', 'mdi-content-copy', 'data-layer-size="1000px,auto" title="复制"')
            ->btnDelete();

        $template = CmsTemplate::find();
        Processer::setPath($template['prefix']);

        foreach ($data as &$d) {
            $channel = $d['channel'];
            if ($channel) {
                $d['preview_url'] = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']) . Processer::resolveContentPath($d, $channel) . '.html';
            }
        }

        $this->builder()->addStyleSheet('
        .table > tbody > tr > td .row-title,.table > tbody > tr > td .row-tag_names
        {
            white-space:normal;
        }
        ');
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

        $info = $this->dataModel->find($id);
        $res = $info && $info->force()->save($data);

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

        $content = $this->dataModel->where('id', $id)->withAttr(['content'])->find();

        $builder = $this->builder($this->pageTitle, '复制');
        if (!$content) {
            return $builder->layer()->closeRefresh(0, '数据不存在');
        }

        if (request()->isGet()) {
            $form = $builder->form();
            $form->show('title', '标题')->value($content['title']);
            $form->selectTree('channel_id', '复制到栏目')->multiple(false)->optionsData($this->channelModel->select(), 'name', 'id', 'parent_id', '')->required();
            if ($content['reference_id'] > 0) {
                $form->radio('copy_type', '方式')->blockStyle()->options(['reference' => '引用'])->default('reference')->required()
                    ->help('引用：引用当前内容，原内容修改后同步更新，当前内容为引用，只能复制为引用类型。')->readonly();
            } else {
                $form->radio('copy_type', '方式')->blockStyle()->options(['copy' => '复制', 'reference' => '引用'])->default('copy')->required()
                    ->help('复制：复制当前内容，生成一个新的内容，之后再无关联；引用：引用当前内容，原内容修改后同步更新。');
            }

            return $builder;
        }
        $channel_id = input('post.channel_id/d');
        $copy_type = input('post.copy_type', 'copy');

        if ($channel_id == $content['channel_id']) {
            $this->error('复制到栏目不能和原栏目相同');
        }
        $newModel = new ContentModel;
        $newData = $content->toArray();
        $newData['channel_id'] = $channel_id;
        unset($newData['id']);

        if ($copy_type == 'copy') {
            $newData['content'] = $content['content'];
        } else {
            $reference_id = $id;
            if ($newData['reference_id'] > 0) {
                $reference_id = $newData['reference_id'];
            }
            $newData['reference_id'] = $reference_id;
            $newData['content'] = '@' . $reference_id;
        }

        $res = $newModel->save($newData);

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
        $form = $this->form;

        $isReference = $isEdit && $data['reference_id'] > 0;
        $config = Module::getInstance()->getConfig();

        $form->defaultDisplayerSize(12, 12);
        $form->hidden('id');
        $form->hidden('reference_id');

        $form->left(7)->with(
            function () use ($form, $config, $data, $isReference) {
                $form->text('title', '标题')->required()->maxlength(125);
                $form->selectTree('channel_id', '栏目', 6)->multiple(false)->optionsData($this->channelModel->order('sort')->select(), 'name', 'id', 'parent_id', '')->required();
                $form->multipleSelect('tags', '合集', 6)->dataUrl(url('/admin/cmstag/selectPage'))->help('可到【合集管理】菜单添加合集');
                $form->text('keywords', '关键字')->readonly($isReference)->maxlength(255)->help('多个关键字请用英文逗号隔开');
                $form->textarea('description', '摘要')->readonly($isReference)->maxlength(255)->help('留空则自动从内容中提取');

                $editor = 'editor';
                if (!empty($config['editor'])) {
                    $editor = $config['editor'];
                }
                if ($isReference) {
                    $form->raw('content_html', '引用内容')->to('<div>复制于<label class="label label-default">@{reference_id}</label>，内容只读，去<a title="点击去编辑" href="' . url('edit', ['id' => $data['reference_id']]) . '">[编辑]</a></div>' . $data['content']);
                    $form->hidden('content')->value('@' . $data['reference_id']);
                } else {
                    $form->$editor('content', '内容');
                }
            }
        );

        $form->right(5)->with(function () use ($form, $isEdit, $isReference) {
            $admin = !$isEdit ? (session('admin_user') ?: []) : null;
            $form->image('logo', '封面图')->mediumSize()->readonly($isReference);
            $form->text('author', '作者', 6)->readonly($isReference)->maxlength(32)->default($admin ? $admin['name'] : '');
            $form->text('source', '来源', 6)->readonly($isReference)->maxlength(32)->default($admin && $admin['group'] ? $admin['group']['name'] : '');

            $form->radio('is_show', '显示', 4)->options([1 => '是', 0 => '否'])->default(9)->blockStyle();
            $form->number('click', '点击量', 4)->default(0);
            $form->number('sort', '排序', 4)->default(0);

            $form->checkbox('attr', '属性')->options(['is_recommend' => '推荐', 'is_top' => '置顶', 'is_hot' => '热门'])
                ->blockStyle()
                ->help('推荐：优先在首页显示；置顶：栏目页优先显示；热门：排序无影响，可以在样式上突出显示。');
            $form->text('link', '跳转链接')->help('设置后覆盖默认的页面地址')->readonly($isReference);
            $form->multipleSelect('mention_ids', '关联内容')->dataUrl(url('/admin/cmscontent/selectPage'), '[{id}]{title}({channel.name})');
            $form->file('attachment', '附件')->smallSize()->readonly($isReference);

            $form->datetime('publish_time', '发布时间', 4)->required()->default(date('Y-m-d H:i:s'));
            if ($isEdit) {
                $form->show('create_time', '添加时间', 4);
                $form->show('update_time', '修改时间', 4);
            }
        });
    }

    protected function _autopost()
    {
        $this->checkToken();

        $id = input('post.id/d', '');
        $name = input('post.name', '');
        $value = input('post.value', '');

        if (empty($id) || empty($name)) {
            $this->error(__blang('bilder_parameter_error'));
        }

        if (!empty($this->postAllowFields) && !in_array($name, $this->postAllowFields)) {
            $this->error(__blang('bilder_field_not_allowed'));
        }

        $info = $this->dataModel->where($this->getPk(), $id)->find();

        $res = $info && $info->save([$name => $value]);

        if ($res) {
            $this->success(__blang('bilder_update_succeeded'));
        } else {
            $this->error(__blang('bilder_update_failed_or_no_changes'));
        }
    }

    /**
     * 保存数据
     *
     * @param integer $id
     * @return void
     */
    protected function save($id = 0)
    {
        $data = request()->only([
            'id',
            'title',
            'channel_id',
            'tags',
            'keywords',
            'description',
            'logo',
            'link',
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
            'mention_ids',
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

        if ($data['channel_id']) {
            $parent = $this->channelModel->find($data['channel_id']);
            if ($parent && $parent['type'] == 2) {
                $this->error($parent['name'] . '是目录，不允许存放内容，请重新选择');
            }
        }

        if (!$id) {
            $data['create_user'] = session('admin_id');
        } else {
            if (!empty($data['mention_ids'])) {
                if (in_array($id, $data['mention_ids'])) {
                    $this->error('关联内容不能是自身-id：' . $id);
                }
            }
        }

        return $this->doSave($data, $id);
    }
}
