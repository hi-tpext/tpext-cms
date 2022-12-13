<?php

namespace tpext\cms\common;

use tpext\think\App;
use tpext\cms\common\View;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\model\CmsTemplatePage;

class TemplaBuilder
{
    /**
     * Undocumented function
     *
     * @param int $template_id
     * @return array
     */
    public function make($template_id)
    {
        $template = CmsTemplate::where('id', $template_id)->find();

        if (!$template) {
            return ['code' => 0, 'msg' => '模板不存在'];
        }

        $pageList = CmsTemplatePage::where('template_id', $template_id)->select();

        foreach ($pageList as $li) {
            $pageList = CmsContentPage::where('page_id', $li['id'])->select();
        }

        $file = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, 'theme/default/channel/default.html');

        $view = new View($file, ['id' => 1]);
    }

    /**
     * 生成栏目页
     *
     * @param int $template_id
     * @param int $channel_id
     * @return array
     */
    public function makeChannel($template_id, $channel_id)
    {
        $page = CmsContentPage::where('page_id', $li['id'])->where('template_id', $channel_id)->find();
    }

    /**
     * 生成详情页
     *
     * @param int $template_id
     * @param int $channel_id
     * @return array
     */
    public function makeContent($template_id, $content_id)
    {
        //
    }
}
