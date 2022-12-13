<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\builder\traits\actions;
use tpext\cms\common\TemplaBuilder;

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

        $templateBuilder = new TemplaBuilder;
        $res = $templateBuilder->make($template_id);

        $builder = $this->builder('页面生成');

        if ($res['code'] == 1) {
            if ($res['over']) {
                $builder->content()->display('{$msg}', $res);
            } else {
                $res['url'] = url('make', ['t' => time()]);
                $builder->content()->display('{$msg}<script>location.href="{$url}"</script>', $res);
            }
        } else {
            $builder->content()->display('{$msg}', $res);
        }

        return $builder;
    }
}
