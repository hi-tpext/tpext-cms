<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\model\CmsTemplatePage as TemplatePageModel;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsContentPage;
use tpext\think\App;
use tpext\cms\common\Module;

/**
 * Undocumented class
 * @title 模板页面管理
 */
class Cmstemplatepage extends Controller
{
    use actions\HasBase;
    use actions\HasAdd;
    use actions\HasIndex;
    use actions\HasEdit;
    use actions\HasDelete;
    use actions\HasAutopost;

    /**
     * Undocumented variable
     *
     * @var TemplatePageModel
     */
    protected $dataModel;

    protected function initialize()
    {
        $this->dataModel = new TemplatePageModel;
        $this->pageTitle = '模板页面管理';
        $this->pagesize = 6;
        $this->sortOrder = 'path';

        $this->selectSearch = 'path';
        $this->selectFields = 'id,path';
        $this->selectTextField = 'path';

        $this->pagesize = 9999;
    }

    protected function filterWhere()
    {
        $searchData = request()->get();

        $where = [];
        if (!empty($searchData['path'])) {
            $where[] = ['path', 'like', '%' . $searchData['path'] . '%'];
        }
        if (!empty($searchData['name'])) {
            $where[] = ['name', 'like', '%' . $searchData['name'] . '%'];
        }

        $where[] = ['template_id', '=', input('template_id/d')];

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

        $search->text('path', '路径', 3)->maxlength(20);
        $search->text('name', '名称', 3)->maxlength(20);
        $search->hidden('template_id')->value(input('template_id/d'));
    }

    /**
     * Undocumented function
     *
     * @param Builder $builder
     * @param string $type index/add/edit/view
     * @return void
     */
    protected function creating($builder, $type = '')
    {
        //其他用户自定义初始化
        if ($type == 'index') {
            $template_id = input('template_id/d');
            $template = CmsTemplate::where('id', $template_id)->find();
            if (!$template) {
                $this->error('模板不存在');
            }
            TemplatePageModel::scanPageFiles($template_id,  App::getRootPath() . 'theme/' . $template['view_path']);
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

        $table->show('name', '名称');
        $table->text('description', '描述')->autoPost();
        $table->raw('path', '路径')->to('<a target="_blank" href="/admin/cmstemplatepage/edit?id={id}">{val}</a>');
        $table->match('type', '类型')->options(['channel' => '栏目', 'content' => '详情', 'common' => '公共', 'single' => '单页', 'index' => '首页'])
            ->mapClassGroup([['channel', 'success'], ['content', 'info'], ['common', 'warning'], ['single', 'purple'], ['index', 'danger']]);
        $table->show('ext', '后缀');
        $table->show('size', '大小')->to('{val}kb');
        $table->show('filectime', '创建时间')->getWrapper()->addStyle('width:180px');
        $table->show('filemtime', '编辑时间')->getWrapper()->addStyle('width:180px');

        $table->sortable('size,name,path,ext,create_time,update_time');

        $table->getToolbar()
            ->btnAdd(url('add', ['template_id' => input('template_id/d')]))
            ->btnRefresh();

        $table->getActionbar()
            ->btnEdit('', '代码', 'btn-warning', 'mdi-table-edit', 'target="_blank"')
            ->btnLink('apply', url('/admin/cmstemplatepage/apply', ['page_id' => '__data.pk__']), '绑定', 'btn-success', 'mdi-link-variant', 'title="绑定到栏目/内容"')
            ->btnDelete()
            ->mapClass([
                'apply' => ['disabled' => '__dis_apply__'],
                'delete' => ['disabled' => '__dis_delete__'],
            ]);

        foreach ($data as &$d) {
            $view_path = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $d['path']);
            if (!is_file($view_path)) {
                $this->dataModel->where('id', $d['id'])->delete(); //真实文件不存在，删除记录
            }

            $isDefault = preg_match('/.+?\/(content|channel)\/default\.html$/i', $d['path']) || preg_match('/theme\/.+?\/index.html$/i', $d['path']);

            $d['__dis_apply__'] = in_array($d['type'], ['common']) || $isDefault;
            $d['__dis_delete__'] = $isDefault;
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

        if ($isEdit) {
            $view_path = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $data['path']);
            $form->show('path', ' ')->showLabel(false)->size(12, 12)->to('路径：{val}');
            $form->aceEditor('content', ' ')->setMode('html')->showLabel(false)->size(12, 12)->value(file_get_contents($view_path));

            $form->butonsSizeClass('btn-md');
            $form->btnSubmit('提&nbsp;&nbsp;交', '12');
        } else {
            $template_id = input('template_id/d');
            $template = CmsTemplate::where('id', $template_id)->find();
            $form->hidden('template_id')->value($template_id);
            $form->show('view_path', '模板基础路径')->value('theme/' . $template['view_path']);
            $form->radio('type', '页面类型')->default('channel')->inline(false)->options(['channel' => '栏目页：' . '/channel/newpage-xx.html', 'content' => '详情页：' . '/content/newpage-xx.html', 'single' => '单页：' . '/newpage-xx.html', 'common' => '公共页：' . '/common/newpage-xx.html']);
            $form->text('name', '文件名称')->required()->default('newpage-xx')->help('格式：只能包含英文数字-_，不支持多级目录')->afterSymbol('.html');
        }
    }

    public function apply()
    {
        $page_id = input('page_id/d');

        $page = $this->dataModel->where('id', $page_id)->find();

        if (!$page) {
            $this->error('模板页面不存在！');
        }

        $template = CmsTemplate::where('id', $page['template_id'])->find();

        if (!$template) {
            $this->error('模板类型不存在！');
        }

        $pageList = CmsContentPage::where('page_id', $page_id)->select();
        $relation_ids = [];

        if (request()->isGet()) {

            foreach ($pageList as $prow) {
                $relation_ids[] = $prow['to_id'];
            }

            $builder = $this->builder('模板管理', '页面分配');

            $form = $builder->form();

            $form->show('path', '模板基础路径');
            $form->match('type', '页面类型')->options(['channel' => '栏目', 'content' => '详情', 'common' => '公共', 'single' => '单页']);
            if (str_replace('\\', '/',  'theme/' . $template['view_path'] . '/index.html') == str_replace('\\', '/', $page['path'])) {
                $form->show('tips', ' ')->value('首页不需要分配');
                $form->readonly();
            } else if (stripos($page['path'], 'common') !== false) {
                $form->show('tips', ' ')->value('公共页面不需要分配');
                $form->readonly();
            } else if (pathinfo($page['path'], PATHINFO_FILENAME) == 'default') {
                $form->show('tips', ' ')->value('栏目/详情默认模板不需要分配');
                $form->readonly();
            } else {
                if ($page['type'] == 'channel') {
                    $form->multipleSelect('relation_ids', '选择栏目')->value($relation_ids)->help('可选择多个栏目')->dataUrl(url('/admin/cmschannel/selectpage', ['chose_page_id' => 1]), '{full_name}')->required();
                } else if ($page['type'] == 'content') {
                    $form->multipleSelect('relation_ids', '选择栏目')->value($relation_ids)->help('可选择多个栏目')->dataUrl(url('/admin/cmschannel/selectpage', ['chose_page_id' => 1]), '{full_name}')->required();
                } else if ($page['type'] == 'single') {
                    $form->multipleSelect('relation_ids', '选择内容详情')->value($relation_ids)->help('选择一篇文章')->dataUrl(url('/admin/cmscontent/selectpage'), '{id}#{title}[所属栏目：{channel.full_name}]')->required();
                } else {
                    $form->show('tips', ' ')->value('未知页面类型');
                }
            }

            $form->fill($page);
            return $builder;
        }

        $relation_ids = input('post.relation_ids/a');

        if (empty($relation_ids)) {
            $this->error('请选择页面');
        }

        $activeIds = [];
        $perm = null;
        $allIds = [];

        foreach ($pageList as $prow) {
            $allIds[] = $prow['id'];
        }

        $success = 0;

        foreach ($relation_ids as $to_id) {
            $perm = null;
            foreach ($pageList as $prow) {
                if ($prow['to_id'] == $to_id) {
                    $perm = $prow;
                    break;
                }
            }
            if ($perm) {
                $activeIds[] = $perm['id'];
                $success += 1;
            } else {
                $perm = new CmsContentPage;
                $res = $perm->save([
                    'to_id' => $to_id,
                    'template_id' => $page['template_id'],
                    'page_id' => $page_id,
                    'page_type' => $page['type'],
                ]);
                if ($res) {
                    $success += 1;
                }
            }
        }

        $delIds = array_diff($allIds, $activeIds);

        if (!empty($delIds)) {
            CmsContentPage::destroy(array_values($delIds));
        }

        if (!$success) {
            $this->error('保存失败');
        }

        return $this->builder()->layer()->closeRefresh(1, '保存成功');
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
            'content',
            'name',
            'template_id',
            'type',
        ], 'post');

        $result = $this->validate($data, []);

        if (true !== $result) {

            $this->error($result);
        }

        if (isset($data['template_id'])) //新键页面
        {
            $data['name'] = str_replace('.html', '', $data['name']);

            $template = CmsTemplate::where('id', $data['template_id'])->find();

            $templatePath = str_replace(['\\', '/'], '/', $template['view_path']);

            $text = '新页面';
            $assets = '../assets';
            $dir = $data['type'] . '/';

            if ($data['type'] == 'single') {
                $assets = './assets';
                $dir = '';
            }

            $path = 'theme/' . $templatePath . '/' . $dir . $data['name'] . '.html';

            $newTpl = '';

            if ($data['type'] == 'common') {
                $newTpl = file_get_contents(Module::getInstance()->getRoot() . 'tpl/common.html');
            } else {
                $newTpl = file_get_contents(Module::getInstance()->getRoot() . 'tpl/new.html');
            }

            $newFilePath = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

            if (is_file($newFilePath)) {
                $this->error('文件已存在！');
            }

            $newRes = file_put_contents($newFilePath, str_replace(['__content__', '../assets'], [$text, $assets], $newTpl));

            if (!$newRes) {
                $this->error('创建新文件失败');
            }

            $key = md5(strtolower($path));

            $data = array_merge([
                'path' => $path,
                'key' => $key,
                'ext' => 'html',
                'filectime' => date('Y-m-d H:i:s', filectime($newFilePath)),
                'filemtime' => date('Y-m-d H:i:s', filemtime($newFilePath)),
                'size' => round(filesize($newFilePath) / 1024, 2),
            ], $data);

            return $this->doSave($data, $id);
        }

        $page = $this->dataModel->where('id', $id)->find();

        $file_path = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $page['path']);

        $res = file_put_contents($file_path, $data['content']);

        if (!$res) {
            $this->error('保存失败');
        }

        $this->success('保存成功，页面即将刷新~', null, '', 1);
    }
}
