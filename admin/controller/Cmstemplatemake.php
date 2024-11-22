<?php

namespace tpext\cms\admin\controller;

use tpext\think\App;
use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\TemplaBuilder;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsTemplateHtml;

/**
 * Undocumented class
 * @title 页面生成
 */
class Cmstemplatemake extends Controller
{
    use actions\HasBase;
    use actions\HasAdd;
    use actions\HasIndex;

    protected function initialize()
    {
        $this->pageTitle = '页面生成';
        $this->pagesize = 6;
        $this->pk = 'path';

        $this->pagesize = 9999;
    }

    public function make()
    {
        $template_id = input('id/d');
        $template = CmsTemplate::where('id', $template_id)->find();
        if (!$template) {
            return $this->builder()->layer()->closeRefresh(0, '模板不存在-id:' . $template_id);
        }
        $builder = $this->builder('页面生成', '模板：' . $template['name']);
        $types = input('types', '');

        if (empty($types)) {
            CmsTemplateHtml::scanPageFiles($template_id,  App::getRootPath() . 'theme/' . $template['view_path']);
            $form = $builder->form();
            $form->checkbox('types', '生成类型')
                ->options(['channel' => '栏目', 'content' => '内容', 'index' => '首页', 'route' => '路由文件', 'static' => '静态资源'])
                ->inline(false)
                ->checkallBtn()
                ->required()
                ->default(['channel', 'content', 'index', 'route', 'static']);

            $form->ajax(false);
            $form->bottomButtons(false);
            $form->btnSubmit('生&nbsp;&nbsp;成');
        } else {
            $templateBuilder = new TemplaBuilder;
            if (is_string($types)) {
                $types = explode(',', $types);
            }

            $res = $templateBuilder->make($template_id, [], $types, input('from_channel_id/d', 0), input('from_content_id/d', 0), input('content_done/d', 0));

            if ($res['code'] == 1) {
                $msg_arr = $res['msg_arr'] ?? [];
                $res['msg'] = '<div><label class="label-default">' . implode('</label></div><div><label class="label-default">', $msg_arr) . '</label></div>';

                if ($res['is_over']) {
                    $builder->content()->display('{$msg|raw}', $res);
                } else {
                    $params = [
                        'id' => $template_id,
                        'types' => implode(',', $types),
                        'from_channel_id' => $res['from_channel_id'] ?: 0,
                        'from_content_id' => $res['from_content_id'] ?: 0,
                        'content_done' => $res['content_done'] ?: 0,
                    ];
                    $res['url'] = url('make', $params);
                    $builder->content()->display('{$msg|raw}<div class="hidden" id="goon">若页面长时间未刷新，可点此<a href="{$url|raw}">继续</a></div><script>setTimeout(function(){location.href="{$url|raw}"},1000);setTimeout(function(){$("#goon").removeClass("hidden")},20000);</script>', $res);
                }
            } else {
                $builder->content()->display('{$msg}', $res);
            }
        }

        return $builder;
    }
}
