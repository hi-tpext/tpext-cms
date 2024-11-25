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

namespace tpext\cms\common;

use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\taglib\Table;

class Page
{
    /**
     * @var CmsTemplate
     */
    protected $template = null;

    public function __construct()
    {
        $this->template = new CmsTemplate;
    }

    /**
     * 首页
     * @param int $tpl_id
     * @return string
     */
    public function index($tpl_id)
    {
        $template = $this->template->where('id', $tpl_id)
            ->cache('cms_template_' . $tpl_id, 3600, 'cms_template')
            ->find();

        if (!$template) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>未能找到them-' . $tpl_id . '</h4></body></html>';
        }

        $render = new Render();
        $res = $render->index($template);
        if ($res['code'] == 0) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>' . $res['msg'] . '</h4></body></html>';
        }
        return $res['data'];
    }

    /**
     * 栏目
     * @param int $id
     * @param int $tpl_id
     * @param int $page
     * @return string
     */
    public function channel($id, $tpl_id, $page = 1)
    {
        $template = $this->template->where('id', $tpl_id)
            ->cache('cms_template_' . $tpl_id, 3600, 'cms_template')
            ->find();

        if (!$template) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>未能找到them-' . $tpl_id . '</h4></body></html>';
        }

        $table = 'cms_channel';

        $dbNameSpace = Processer::getDbNamespace();
        $channelScope = Table::defaultScope($table);
        $channel = $dbNameSpace::name($table)->where('id', $id)
            ->where($channelScope)
            ->cache('cms_channel_' . $id, 3600, $table)
            ->find();

        if (!$channel) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>页面不存在</h4></body></html>';
        }

        $channel = Processer::detail($table, $channel);
        $render = new Render();
        $res = $render->channel($template, $channel, $page);
        if ($res['code'] == 0) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>' . $res['msg'] . '</h4></body></html>';
        }
        return $res['data'];
    }

    /**
     * 内容
     * @param int $id
     * @param int $tpl_id
     * @return string
     */
    public function content($id, $tpl_id)
    {
        $template = $this->template->where('id', $tpl_id)
            ->cache('cms_template_' . $tpl_id, 3600, 'cms_template')
            ->find();

        if (!$template) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>未能找到them-' . $tpl_id . '</h4></body></html>';
        }

        $table = 'cms_content';

        $dbNameSpace = Processer::getDbNamespace();
        $channelScope = Table::defaultScope($table);
        $content = $dbNameSpace::name($table)
            ->where('id', $id)
            ->where($channelScope)
            ->cache('cms_content_' . $id, 3600, $table)
            ->find();

        if (!$content) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>页面不存在</h4></body></html>';
        }

        $content = Processer::detail($table, $content);
        $render = new Render();
        $res = $render->content($template, $content);
        if ($res['code'] == 0) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>' . $res['msg'] . '</h4></body></html>';
        }
        return $res['data'];
    }

    /**
     * 动态页面
     * @param int $html_id
     * @param int $tpl_id
     * @return string
     */
    public function dynamic($html_id, $tpl_id)
    {
        $template = $this->template->where('id', $tpl_id)
            ->cache('cms_template_' . $tpl_id, 3600, 'cms_template')
            ->find();

        if (!$template) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>未能找到them-' . $tpl_id . '</h4></body></html>';
        }

        $render = new Render();
        $res = $render->dynamic($template, $html_id);
        if ($res['code'] == 0) {
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>500</title></head><body><h4>' . $res['msg'] . '</h4></body></html>';
        }
        return $res['data'];
    }
}