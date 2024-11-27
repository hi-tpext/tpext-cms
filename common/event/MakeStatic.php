<?php
// +----------------------------------------------------------------------
// | tpext.cms
// +----------------------------------------------------------------------
// | Copyright (c) tpext.cms All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: lhy <ichynul@163.com>
// +----------------------------------------------------------------------

namespace tpext\cms\common\event;

use tpext\common\ExtLoader;
use tpext\cms\common\TemplaBuilder;
use tpext\cms\common\model;

class MakeStatic
{
    public function watch()
    {
        // 监听内容相关事件
        ExtLoader::watch('cms_content_on_after_insert', function ($data) {
            $this->contentChange($data);
        });
        ExtLoader::watch('cms_content_on_after_update', function ($data) {
            $this->contentChange($data);
        });
        ExtLoader::watch('cms_content_on_after_delete', function ($data) {
            $this->contentChange($data);
        });

        // 监听栏目相关事件
        ExtLoader::watch('cms_channel_on_after_insert', function ($data) {
            $this->channelChange($data);
        });
        ExtLoader::watch('cms_channel_on_after_update', function ($data) {
            $this->channelChange($data);
        });
        ExtLoader::watch('cms_channel_on_after_delete', function ($data) {
            $this->channelChange($data);
        });
    }

    /**
     * Summary of contentChange
     * @param mixed $data
     * @return void
     */
    protected function contentChange($data)
    {
        $buiilder = new TemplaBuilder;
        $templates = model\CmsTemplate::select();
        $channel = model\CmsChannel::withTrashed()->where('id', $data['channel_id'])->find();
        foreach ($templates as $template) {
            $buiilder->makeContent($template, $channel, $data);
            $buiilder->makeChannel($template, $channel);
            $buiilder->makeIndex($template);
        }
    }

    /**
     * Summary of channelChange
     * @param mixed $data
     * @return void
     */
    protected function channelChange($data)
    {
        $buiilder = new TemplaBuilder;
        $templates = model\CmsTemplate::select();
        foreach ($templates as $template) {
            $buiilder->makeChannel($template, $data);
            $buiilder->makeIndex($template);
        }
    }
}
