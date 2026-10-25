<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\cms\common\model;
use tpext\cms\common\Module;
use tpext\builder\traits\actions;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\model\CmsContentModel;
use tpext\myadmin\admin\model\AdminGroup;
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
        //attr经editAttr单独入口提交，不列入autopost白名单
        $this->postAllowFields = ['is_show', 'sort'];
        $this->channelModel = new CmsChannel;
        $this->pageTitle = '内容管理';
        $this->enableField = 'is_show';
        $this->pagesize = 8;

        $this->selectSearch = 'title';
        $this->selectFields = 'id,title,channel_id';
        $this->selectTextField = '{title}({channel.name})';
        $this->selectWith = ['channel'];

        $this->indexWith = ['channel', 'model'];

        //左侧树
        $this->treeModel = $this->channelModel; //分类模型
        $this->treeTextField = 'name'; //分类模型中的分类名称字段
        $this->treeKey = 'channel_id'; //关联的键　localKey
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
     * @param array $data
     * @return void
     */
    protected function buildTable(&$data = [])
    {
        $table = $this->table;

        $models = model\CmsContentModel::order('sort asc,id asc')->select();
        $colors = ['info', 'purple', 'dark', 'primary', 'success', 'warning', 'secondary', 'danger', 'yellow', 'pink', 'cyan'];

        $table->show('id', 'ID');
        $table->image('logo', '封面图')->thumbSize(60, 60);
        $table->raw('title', '标题')->to('<a href="{preview_url}" target="_blank">{val}</a>');
        $table->show('channel_id', '栏目')->to('{channel.full_name}');
        if (count($models) > 1) {
            $classGroup = [];
            foreach ($models as $i => $model) {
                $classGroup[] = [$model['id'], $colors[$i % 11], 'model_id'];
            }
            $table->show('model_id', '模型', 1)->to('{model.name}')->mapClassGroup($classGroup);
        }
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

        $table->getToolbar()
            ->html('批量操作：')
            ->btnEnableAndDisable('显示', '隐藏')
            ->btnDelete()
            ->btnRefresh()
            ->btnToggleSearch()
            ->html('&nbsp;&nbsp;发布：');

        foreach ($models as $i => $model) {
            $table->getToolbar()->btnAdd(
                url('add', ['model_id' => $model['id']]),
                $model['name'],
                'btn-' . $colors[$i % 11],
                'mdi-plus',
                'data-layer-size="98%,98%" title="添加新的' . $model['name'] . '"'
            );
        }

        $table->getActionbar()
            ->btnEdit('', '', 'btn-primary', 'mdi-lead-pencil', 'data-layer-size="98%,98%" title="编辑"')
            ->btnView('', '', 'btn-info', 'mdi-eye-outline', 'data-layer-size="98%,98%" title="查看"')
            ->br()
            ->btnLink('copy', url('copy', ['id' => '__data.pk__']), '', 'btn-success', 'mdi-content-copy', 'data-layer-size="1000px,auto" title="复制"')
            ->btnDelete();

        $template = model\CmsTemplate::order('sort asc,id asc')->find();
        Processer::setPath($template['prefix']);

        foreach ($data as &$d) {
            $channel = $d['channel'];
            if ($channel) {
                $d['preview_url'] = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']) . Processer::resolveContentPath($d, $channel) . '.html';
            }
        }

        $this->builder()->addStyleSheet('
        .table > tbody > tr > td .row-title,.table > tbody > tr > td .row-channel_id,.table > tbody > tr > td .row-tag_names
        {
            white-space:normal;
        }
        ');

        $table->sortable('id,channel_id,author,source,is_show,publish_time,sort,click,create_time,update_time');
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

        $content = $this->dataModel->where('id', $id)->with(['detail'])->find();

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

        //与新增/编辑同一套栏目校验（目录栏目、extend_table栏目、模型不匹配的拒绝复制）
        $this->checkChannelAllowed($channel_id, $newData['model_id']);

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

        $model_id = $isEdit ? $data['model_id'] : input('model_id/d', 1);

        $fieldsInfo = $this->getFieldsInfo($model_id);

        //下拉/单选类自定义字段的options批量预载（原逐字段查询是N+1）
        $customNames = [];
        foreach ($fieldsInfo['fields'] as $field) {
            if ($field['is_custom'] == 1 && in_array($field['displayer_type'], ['radio', 'checkbox', 'tree', 'selecttree', 'transfer', 'select', 'multipleSelect'])) {
                $customNames[] = $field['name'];
            }
        }

        $ctx = [
            'isEdit' => $isEdit,
            'isReference' => $isReference,
            'data' => $data,
            'config' => $config,
            'customOptions' => $customNames ? model\CmsContentField::where('name', 'in', $customNames)->column('options', 'name') : [],
        ];

        $form->defaultDisplayerSize(12, 12);
        $form->hidden('id');
        $form->hidden('reference_id');
        $form->hidden('model_id')->default($model_id);

        if (count($fieldsInfo['extend_fields']) > 0) {
            $form->tab('基本信息');
        }

        $form->left(count($fieldsInfo['main_right_fields']) > 0 ? 7 : 12)->with(
            function () use ($form, $fieldsInfo, $ctx) {
                $form->text('title', '标题')->required()->maxlength(125);
                $form->selectTree('channel_id', '栏目', 6)->multiple(false)->optionsData($this->channelModel->order('sort')->select(), 'name', 'id', 'parent_id', '')->required();

                $this->renderRegion('main_left', $form, $fieldsInfo, $ctx);
            }
        );

        $form->right(count($fieldsInfo['main_right_fields']) > 0 ? 5 : 12)->with(function () use ($form, $fieldsInfo, $ctx) {
            $this->renderRegion('main_right', $form, $fieldsInfo, $ctx);
        });

        if (count($fieldsInfo['extend_fields']) > 0) {
            $form->tab('扩展信息');
            $this->renderRegion('extend', $form, $fieldsInfo, $ctx);
        }
    }

    /**
     * 区域字段序列（渲染顺序）：字段名对应 builtinFieldSpecs 的键；
     * content/attachments/attr 为特殊字段，@custom 为该区域自定义字段插入点，
     * @html 为分隔符，@times 为编辑时展示的添加/修改时间
     */
    protected function regionSpecs($region)
    {
        $specs = [
            'main_left' => ['tags', 'keywords', 'description', '@custom', 'attachments', 'content'],
            'main_right' => ['logo', 'author', 'source', '@html', '@custom', 'attachments', 'is_show', 'click', 'sort', '@html', 'attr', 'publish_time', '@times'],
            'extend' => ['link', 'mention_ids', '@custom', 'attachments'],
        ];

        return $specs[$region] ?? [];
    }

    //内置字段规格；公共链（label/help/required取值规则）见 renderSpecField，default为回调时运行时求值，extra为字段专属链式调用
    protected function builtinFieldSpecs()
    {
        return [
            'tags' => ['displayer' => 'multipleSelect', 'label' => '合集', 'colSize' => 6, 'extra' => function ($f) {
                $f->dataUrl(url('/admin/cmstag/selectPage'));
            }],
            'keywords' => ['displayer' => 'text', 'label' => '关键字', 'readonly' => true, 'maxlength' => 255, 'helpDefault' => '多个关键字请用英文逗号隔开'],
            'description' => ['displayer' => 'textarea', 'label' => '摘要', 'readonly' => true, 'maxlength' => 255, 'helpDefault' => '留空则自动从内容中提取'],
            'logo' => ['displayer' => 'image', 'label' => '封面图', 'readonly' => true, 'extra' => function ($f) {
                $f->mediumSize();
            }],
            'author' => ['displayer' => 'text', 'label' => '作者', 'colSize' => 6, 'readonly' => true, 'maxlength' => 32, 'default' => function ($ctx) {
                $admin = !$ctx['isEdit'] ? (session('admin_user') ?: []) : null;
                return $admin ? $admin['name'] : '';
            }],
            'source' => ['displayer' => 'text', 'label' => '来源', 'colSize' => 6, 'readonly' => true, 'maxlength' => 32, 'default' => function ($ctx) {
                $admin = !$ctx['isEdit'] ? (session('admin_user') ?: []) : null;
                return $admin ? AdminGroup::where(['id' => $admin['group_id']])->value('name') : '';
            }],
            'is_show' => ['displayer' => 'radio', 'label' => '显示', 'colSize' => 4, 'options' => [1 => '是', 0 => '否'], 'default' => 0, 'skipRequired' => true, 'extra' => function ($f) {
                $f->blockStyle();
            }],
            'click' => ['displayer' => 'number', 'label' => '点击量', 'colSize' => 4, 'default' => 0],
            'sort' => ['displayer' => 'number', 'label' => '排序', 'colSize' => 4, 'default' => 0],
            'publish_time' => ['displayer' => 'datetime', 'label' => '发布时间', 'colSize' => 4, 'alwaysRequired' => true, 'default' => date('Y-m-d H:i:s')],
            'link' => ['displayer' => 'text', 'label' => '跳转链接', 'readonly' => true, 'skipRequired' => true, 'helpOnNull' => '设置后覆盖默认的页面地址'],
            'mention_ids' => ['displayer' => 'multipleSelect', 'label' => '关联内容', 'extra' => function ($f) {
                $f->dataUrl(url('/admin/cmscontent/selectPage'), '[{id}]{title}({channel.name})');
            }],
        ];
    }

    protected function renderRegion($region, $form, $fieldsInfo, $ctx)
    {
        $fields = $fieldsInfo['fields'];
        $specs = $this->builtinFieldSpecs();

        foreach ($this->regionSpecs($region) as $key) {
            if ($key == '@custom') {
                foreach ($fields as $field) {
                    if ($field['is_custom'] == 1 && $field['position'] == $region) {
                        $this->customField($form, $field, $ctx['isReference'], $ctx['customOptions']);
                    }
                }
                continue;
            }
            if ($key == '@html') {
                $form->html('');
                continue;
            }
            if ($key == '@times') {
                if ($ctx['isEdit']) {
                    $form->show('create_time', '添加时间', 4);
                    $form->show('update_time', '修改时间', 4);
                }
                continue;
            }
            if ($key == 'content') {
                if (in_array('content', $fieldsInfo['field_names'])) {
                    $this->renderContentField($form, $fields['content'], $ctx);
                }
                continue;
            }
            if ($key == 'attr') {
                $this->renderAttrField($form, $fieldsInfo);
                continue;
            }
            if ($key == 'attachments') {
                //附件唯一，归属区域优先级：main_left > main_right > extend
                $owner = in_array('attachments', $fieldsInfo['main_left_fields']) ? 'main_left'
                    : (in_array('attachments', $fieldsInfo['main_right_fields']) ? 'main_right' : 'extend');
                if ($owner == $region && in_array('attachments', $fieldsInfo['field_names'])) {
                    $this->renderAttachmentsField($form, $fields['attachments'], $ctx['isReference']);
                }
                continue;
            }
            if (!in_array($key, $fieldsInfo['field_names']) || !isset($specs[$key])) {
                continue;
            }
            $this->renderSpecField($form, $key, $specs[$key], $fields[$key], $ctx);
        }
    }

    protected function renderSpecField($form, $name, $spec, $meta, $ctx)
    {
        $args = [$name, $meta['comment'] ?: $spec['label']];
        if (!empty($spec['colSize'])) {
            $args[] = $spec['colSize'];
        }
        $f = $form->{$spec['displayer']}(...$args);

        if (!empty($spec['readonly'])) {
            $f->readonly($ctx['isReference']);
        }
        if (!empty($spec['maxlength'])) {
            $f->maxlength($spec['maxlength']);
        }
        if (isset($spec['options'])) {
            $f->options($spec['options']);
        }
        if (array_key_exists('default', $spec)) {
            $default = $spec['default'];
            $f->default(is_callable($default) ? $default($ctx) : $default);
        }
        if (isset($spec['helpOnNull'])) {
            $f->help($meta['help'] ?? $spec['helpOnNull']);
        } else {
            $help = $meta['help'];
            if ($help === '' || is_null($help)) {
                $help = $spec['helpDefault'] ?? '';
            }
            $f->help($help);
        }
        if (empty($spec['skipRequired'])) {
            $f->required(!empty($spec['alwaysRequired']) || strstr($meta['rules'], 'required'));
        }
        if (!empty($spec['extra'])) {
            $spec['extra']($f, $ctx);
        }
    }

    protected function renderAttachmentsField($form, $meta, $isReference)
    {
        $displayer = $meta['displayer_type'];
        $f = $form->$displayer('attachments', $meta['comment'] ?: '附件')
            ->readonly($isReference)
            ->help($meta['help'])
            ->required(strstr($meta['rules'], 'required'));

        if (in_array($displayer, ['files', 'images'])) {
            $f->limit(20);
        }
        if (in_array($displayer, ['image', 'images'])) {
            $f->bigSize();
        }
    }

    //正文：编辑器类型取模型字段配置（editor时回退站点配置默认），引用内容只读
    protected function renderContentField($form, $meta, $ctx)
    {
        $editor = 'editor';
        if ($meta['displayer_type'] == 'editor') {
            if (!empty($ctx['config']['editor'])) {
                $editor = $ctx['config']['editor'];
            }
        } else {
            $editor = $meta['displayer_type'];
        }
        if ($ctx['isReference']) {
            $form->raw('content_html', '引用内容')->to('<div>复制于<label class="label label-default">@{reference_id}</label>，内容只读，去<a title="点击去编辑" href="' . url('edit', ['id' => $ctx['data']['reference_id']]) . '">[编辑]</a></div>' . $ctx['data']['content']);
            $form->hidden('content')->value('@' . $ctx['data']['reference_id']);
        } else {
            $e = $form->$editor('content', $meta['comment'] ?: '内容')
                ->help($meta['help'])
                ->required(strstr($meta['rules'], 'required'));
            if ($editor == 'textarea') {
                $e->rows(10);
            }
        }
    }

    protected function renderAttrField($form, $fieldsInfo)
    {
        $names = ['is_recommend' => '推荐', 'is_top' => '置顶', 'is_hot' => '热门'];
        $attr = [];
        foreach ($names as $name => $label) {
            if (in_array($name, $fieldsInfo['field_names'])) {
                $attr[$name] = $fieldsInfo['fields'][$name]['comment'] ?: $label;
            }
        }
        if (empty($attr)) {
            return;
        }
        $form->checkbox('attr', '属性')->options($attr)
            ->blockStyle()
            ->help('推荐：优先在首页显示；置顶：栏目页优先显示；热门：排序无影响，可以在样式上突出显示。');
    }

    protected function customField($form, $modelField, $isReference, $optionsMap = [])
    {
        $displayer = $modelField['displayer_type'];
        $f = $form->$displayer($modelField['name'], $modelField['comment'])
            ->readonly($isReference)
            ->help($modelField['help'])
            ->required(strstr($modelField['rules'], 'required'));

        if (in_array($displayer, ['radio', 'checkbox', 'tree', 'selecttree', 'transfer', 'select', 'multipleSelect'])) {
            if (!empty($optionsMap[$modelField['name']])) {
                $options = trim($optionsMap[$modelField['name']]);
                if (in_array($displayer, ['select', 'multipleSelect']) && preg_match('/^api:.+$/is', $options)) {
                    $api = explode(',', str_replace('api:', '', $options));
                    $url = $api[0];
                    $text = $api[1] ?? '';
                    $id = $api[2] ?? '';
                    $f->dataUrl($url, $text, $id);
                } else {
                    $optionsArr = [];
                    $arr = explode("\n", $options);
                    foreach ($arr as $a) {
                        $aa = explode(':', trim($a));
                        $optionsArr[$aa[0]] = $aa[1] ?? $aa[0];
                    }
                    $f->options($optionsArr);
                }
            }
        }
    }

    //栏目合法性校验（新增/编辑/复制共用）
    protected function checkChannelAllowed($channel_id, $model_id)
    {
        $channel = $this->channelModel->find($channel_id);
        if (!$channel) {
            $this->error('栏目不存在');
        }
        if ($channel['type'] == 2) {
            $this->error('所选栏目[' . $channel['name'] . ']是目录，不允许存放内容，请重新选择');
        }
        if ($channel['extend_table']) {
            $this->error('所选栏目[' . $channel['name'] . "]内容类型是[{$channel['extend_table']}]，不允许存放[cms_content]内容，请重新选择");
        }
        if (!in_array($model_id, explode(',', $channel['model_ids']))) {
            $model = CmsContentModel::find($model_id);
            $this->error('所选栏目[' . $channel['name'] . ']不支持存放[' . ($model ? $model['name'] : '未知') . ']内容，请重新选择');
        }
    }
    /**
     * 获取模型字段信息
     * @param int $model_id
     * @return array
     */
    protected function getFieldsInfo($model_id)
    {
        $modelFields = model\CmsContentModelField::where('model_id', $model_id)->cache('cms_content_model_fields_' . $model_id, null, 'cms_content_model_field')->select()->toArray();
        $modelFieldNames = array_column($modelFields, 'name');
        $modelMainLeftFieldNames = model\CmsContentModelField::where(['model_id' => $model_id, 'position' => 'main_left'])->cache('cms_content_model_fields_main_left_' . $model_id, null, 'cms_content_model_field')->column('name');
        $modelMainRightFieldNames = model\CmsContentModelField::where(['model_id' => $model_id, 'position' => 'main_right'])->cache('cms_content_model_fields_main_right_' . $model_id, null, 'cms_content_model_field')->column('name');
        $modelExtendFieldNames = model\CmsContentModelField::where(['model_id' => $model_id, 'position' => 'extend'])->cache('cms_content_model_fields_extend_' . $model_id, null, 'cms_content_model_field')->column('name');

        $allFields = [];

        array_walk($modelFields, function ($item, $key) use (&$allFields) {
            $allFields[$item['name']] = $item;
        });

        $info = [
            'fields' => $allFields,
            'field_names' => $modelFieldNames,
            'main_left_fields' => $modelMainLeftFieldNames,
            'main_right_fields' => $modelMainRightFieldNames,
            'extend_fields' => $modelExtendFieldNames,
        ];

        return $info;
    }

    protected function _autopost()
    {
        $this->checkToken();

        $id = input('post.id/d', '');
        $name = input('post.name', '');
        $value = input('post.value', '');

        if (empty($id) || empty($name)) {
            $this->error(__blang('builder_parameter_error'));
        }

        if (!in_array($name, $this->postAllowFields) || !is_scalar($value)) {
            $this->error(__blang('builder_field_not_allowed'));
        }

        $info = $this->dataModel->where($this->getPk(), $id)->find();

        $res = $info && $info->save([$name => $value]);

        if ($res) {
            $this->success(__blang('builder_update_succeeded'));
        } else {
            $this->error(__blang('builder_update_failed_or_no_changes'));
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
        $model_id = input('post.model_id/d', 0);

        $modelFields = model\CmsContentModelField::where('model_id', $model_id)->cache('cms_content_model_fields_' . $model_id, null, 'cms_content_model_field')->select()->toArray();
        $modelFieldNames = array_column($modelFields, 'name');

        $data = request()->only(array_merge([
            'id',
            'title',
            'channel_id',
            'reference_id',
            'model_id',
            'attr',
        ], $modelFieldNames), 'post');

        $result = $this->validate($data, [
            'title|标题' => 'require',
            'channel_id|栏目' => 'require',
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
            $this->checkChannelAllowed($data['channel_id'], $data['model_id']);
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
