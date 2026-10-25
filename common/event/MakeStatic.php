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

use tpext\cms\common\model;
use tpext\common\ExtLoader;
use tpext\cms\common\TemplaBuilder;
use tpext\cms\common\taglib\Processer;

class MakeStatic
{
    public function watch()
    {
        // 监听内容相关事件
        ExtLoader::watch('cms_content_on_after_insert', function ($data) {
            $this->guard('content', $data);
        });
        ExtLoader::watch('cms_content_on_after_update', function ($data) {
            $this->guard('content', $data);
        });
        ExtLoader::watch('cms_content_on_after_delete', function ($data) {
            $this->guard('content', $data);
        });

        // 监听栏目相关事件
        ExtLoader::watch('cms_channel_on_after_insert', function ($data) {
            $this->guard('channel', $data);
        });
        ExtLoader::watch('cms_channel_on_after_update', function ($data) {
            $this->guard('channel', $data);
        });
        ExtLoader::watch('cms_channel_on_after_delete', function ($data) {
            $this->guard('channel', $data);
        });
    }

    /**
     * 事件入口兜底：静态化异常不能炸掉保存/删除主流程，记录日志后吞掉
     *
     * @param string $type
     * @param mixed $data
     * @return void
     */
    protected function guard($type, $data)
    {
        try {
            $type == 'content' ? $this->contentChange($data) : $this->channelChange($data);
        } catch (\Throwable $e) {
            trace('自动静态化失败(不影响保存)：' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine(), 'error');
        }
    }

    /**
     * @param mixed $data
     * @return void
     */
    protected function contentChange($data)
    {
        $buiilder = new TemplaBuilder;
        $templates = model\CmsTemplate::select();
        $channel = model\CmsChannel::withTrashed()->where('id', $data['channel_id'])->find();
        if (!$channel) {
            return;
        }
        trace('文章[' . $data['title'] . ']修改触发静态生成', 'info');
        foreach ($templates as $template) {
            trace('处理模板：' . $template['name'], 'info');
            Processer::setPath($template['prefix']);
            $res = $buiilder->makeContent($template, $channel, $data);
            trace('[' . $data['title'] . ']生成静态文件：' . $res['msg'], 'info');
            $res = $buiilder->makeChannel($template, $channel);
            trace('[' . $channel['name'] . ']列表第一页生成静态文件：' . $res['msg'], 'info');
            $res = $buiilder->makeIndex($template);
            trace('[模板首页]成静态文件：' . $res['msg'], 'info');
        }
    }

    /**
     * @param mixed $data
     * @return void
     */
    protected function channelChange($data)
    {
        $buiilder = new TemplaBuilder;
        $templates = model\CmsTemplate::select();
        trace('栏目[' . $data['name'] . ']修改触发静态生成', 'info');
        foreach ($templates as $template) {
            trace('处理模板：' . $template['name'], 'info');
            Processer::setPath($template['prefix']);
            $res = $buiilder->makeChannel($template, $data);
            trace('[' . $data['name'] . ']列表第一页生成静态文件：' . $res['msg'], 'info');
            $res = $buiilder->makeIndex($template);
            trace('[模板首页]成静态文件：' . $res['msg'], 'info');
        }
    }
}
