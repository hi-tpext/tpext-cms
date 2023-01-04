<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\TemplaBuilder;
use tpext\cms\common\model\CmsTemplate;

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

        $templateBuilder = new TemplaBuilder;
        $res = $templateBuilder->make($template_id, [], ['channel', 'content'], input('from_channel_id/d', 0), input('from_content_id/d', 0));

        $builder = $this->builder('页面生成', $template['name']);

        if ($res['code'] == 1) {
            $msg_arr = $res['msg_arr'] ?? [];
            $res['msg'] = implode('<br>', $msg_arr);

            if ($res['is_over']) {
                $builder->content()->display('{$msg|raw}', $res);
            } else {
                $res['url'] = url('make', ['id' => $template_id, 'from_channel_id' => $res['from_channel_id'] ?: 0, 'from_content_id' => $res['from_content_id'] ?: 0]);
                $builder->content()->display('{$msg|raw}<script>setTimeout(function(){location.href="{$url}"},1000)</script>', $res);
            }
        } else {
            $builder->content()->display('{$msg}', $res);
        }

        return $builder;
    }
}
