<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsTemplateHtml;
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
        $table->match('type', '类型')->default(1)->options([1 => '不限', 2 => '目录', 3 => '分类'])->mapClassGroup([[1, 'success'], [2, 'info'], [3, 'warning']])->getWrapper()->addStyle('width:80px');
        $table->text('sort', '排序')->autoPost('', true)->getWrapper()->addStyle('width:80px');
        $table->text('pagesize', '分页大小')->autoPost()->getWrapper()->addStyle('width:80px');
        $table->show('content_count', '内容统计')->getWrapper()->addStyle('width:80px');
        $table->fields('page_path', '生成路径')->with(
            $table->show('channel_path', '栏目生成路径')->to('channel/{val}.html'),
            $table->show('content_path', '内容生成路径')->to('content/{val}.html')
        );
        $table->fields('create_time', '添加/修改时间')->with(
            $table->show('create_time', '添加时间'),
            $table->show('update_time', '修改时间'),
        )->getWrapper()->addStyle('width:160px');

        $table->sortable([]);

        $table->getToolbar()
            ->btnAdd()
            ->btnDelete()
            ->btnRefresh()
            ->btnLink(url('refresh'), '刷新层级', 'btn-success', 'mdi-autorenew');

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
     * @title 刷新层级关系
     * @return mixed
     */
    public function refresh()
    {
        $builder = $this->builder('栏目管理', '刷新层级关系');
        $step = input('step', '0');
        $list = [];
        $maxLevel = $this->dataModel::max('deep');
        if ($step <= $maxLevel) {
            $list = $this->dataModel::where('deep', $step)->select();
            foreach ($list as $li) {
                $upNodes = $this->dataModel->getUpperNodes($li);
                $names = [];
                $ids = [];
                foreach ($upNodes as $node) {
                    if ($node['id'] == $li['id']) {
                        continue;
                    }
                    $names[] = $node['name'];
                    $ids[] = $node['id'];
                }
                $data['full_name'] = $li['deep'] == 0 ? $li['name'] : implode('->', array_reverse($names));
                $data['path'] = $li['parent_id'] == 0 ? ',0,' : ',' . implode(',', array_reverse($ids)) . ',';
                $data['deep'] = $li['parent_id'] == 0 ? 1 : count($ids);

                $li->save($data);
            }
            $next = $step + 1;
            $url = url('refresh', ['step' => $next]);
            $builder->display('<h4>（{$step}/{$maxLevel}）栏目层级已处理</h4><script>setTimeout(function(){location.href=\'{$url}\'},1000)</script>', ['step' => $step, 'maxLevel' => $maxLevel, 'url' => $url]);
        } else {
            $builder->display('<h4>完成</h4>');
        }

        return $builder;
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

        $form->tab('基本信息');
        $form->text('name', '名称')->required();
        $form->select('parent_id', '上级')->required()->options($tree)->default(input('parend_id'));
        $form->image('logo', '封面图');
        $form->radio('type', '类型')->default(1)->options([1 => '不限', 2 => '目录', 3 => '分类'])->required()->help('目录有下级，不能存文章。分类无下级，只能存文章');
        // $form->select('channel_template_id', '栏目模板')->dataUrl(url('/admin/cmstemplate/selectpage'));
        // $form->select('content_template_id', '内容模板')->dataUrl(url('/admin/cmstemplate/selectpage'));
        $form->switchBtn('is_show', '显示')->default(1);
        $form->number('sort', '排序')->default(0)->required();

        if ($isEdit) {
            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }
        $form->tab('生成设置');
        $form->number('pagesize', '分页大小')->default(20)->required();
        $form->text('channel_path', '栏目生成路径')->required()->size(2, 6)->beforSymbol('/channel/')->afterSymbol('.html')->default('c[id]')->help('[id]为变量，栏目编号');
        $form->text('content_path', '内容生成路径')->required()->size(2, 6)->beforSymbol('/content/')->afterSymbol('.html')->default('a[id]')->help('[id]为变量，内容编号');
        $form->text('link', '跳转链接')->help('设置后覆盖栏目生成地址，用于外链或站内跳转');

        if ($isEdit) {
            $form->tab('模板信息');

            $templates = CmsTemplate::select();

            foreach ($templates as $tpl) {
                $channelPage = CmsContentPage::where(['html_type' => 'channel', 'template_id' => $tpl['id'], 'to_id' => $data['id']])->with(['template'])->find();
                $tplHtml = null;
                if ($channelPage) {
                    $tplHtml = CmsTemplateHtml::where('id', $channelPage['html_id'])->find();
                } else {
                    //无绑定，使用默认模板
                    $tplHtml = CmsTemplateHtml::where('is_default', 1)
                        ->where(['type' => 'channel', 'template_id' => $tpl['id']])
                        ->find();
                }
                if ($tplHtml) {
                    $form->show('template_id' . $tpl['id'], $tpl['name'] . '[栏目]')->value($tplHtml['path']);
                } else {
                    $form->show('template_id' . $tpl['id'], $tpl['name'] . '[栏目]')->value('-暂无-');
                }

                $contentPage = CmsContentPage::where(['html_type' => 'content', 'template_id' => $tpl['id'], 'to_id' => $data['id']])->with(['template'])->find();
                $tplHtml = null;
                if ($contentPage) {
                    $tplHtml = CmsTemplateHtml::where('id', $contentPage['html_id'])->find();
                } else {
                    //无绑定，使用默认模板
                    $tplHtml = CmsTemplateHtml::where('is_default', 1)
                        ->where(['type' => 'content', 'template_id' => $tpl['id']])
                        ->find();
                }
                if ($tplHtml) {
                    $form->show('template_id' . $tpl['id'], $tpl['name'] . '[内容]')->value($tplHtml['path']);
                } else {
                    $form->show('template_id' . $tpl['id'], $tpl['name'] . '[内容]')->value('-暂无-');
                }
            }
        }
    }

    private function save($id = 0)
    {
        $data = request()->only([
            'name',
            'parent_id',
            'logo',
            'pagesize',
            'link',
            'is_show',
            'type',
            'sort',
            'channel_path',
            'content_path',
        ], 'post');

        $result = $this->validate($data, [
            'name|名称' => 'require',
            'sort|排序' => 'require|number',
            'type|类型' => 'require|number',
            'parent_id|上级' => 'require|number',
            'is_show|是否显示' => 'require',
            'channel_path|栏目生成路径' => 'require',
            'content_path|内容生成路径' => 'require',
        ]);

        if (true !== $result) {

            $this->error($result);
        }

        if ($data['parent_id']) {
            $parent = $this->dataModel->find($data['parent_id']);
            if ($parent && $parent['type'] == 3) {
                $this->error($parent['name'] . '不允许有下级栏目，请重新选择');
            }
        }
        $data['channel_path'] = str_replace('\\', '/', $data['channel_path']);
        $data['content_path'] = str_replace('\\', '/', $data['content_path']);

        if ($id && $data['parent_id'] == $id) {
            $this->error('上级不能是自己');
        }

        return $this->doSave($data, $id);
    }
}
