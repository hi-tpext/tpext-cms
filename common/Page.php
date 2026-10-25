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

    /**
     * cacheTime
     * @var int
     */
    protected $cacheTime = 3600 * 24 * 7;

    /**
     * 错误页：主题存在error.html时渲染主题模板(带站点导航)，否则回退内置样式
     *
     * @param array|CmsTemplate|null $template
     * @param int $code 404|403|500
     * @param string $msg
     * @return string
     */
    protected function errorPage($template, $code, $msg)
    {
        if ($template) {
            $render = new Render();
            $html = $render->errorPage($template, $code, $msg);
            if (null !== $html) {
                return $html;
            }
        }

        $title = $code == 404 ? '页面不存在' : ($code == 403 ? '禁止访问' : '服务器错误');

        $marker = $code >= 500 ? '<!--__error_5xx__-->' : '';
        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/>' . $marker . '<title>' . $code . ' ' . $title . '</title></head><body style="text-align:center;padding-top:80px;font-family:sans-serif;"><h1>' . $code . '</h1><h4>' . htmlspecialchars($msg, ENT_QUOTES) . '</h4><p><a href="/">返回首页</a></p></body></html>';
    }

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
            ->cache('cms_template_' . $tpl_id, $this->cacheTime, 'cms_template')
            ->find();

        if (!$template) {
            return $this->errorPage(null, 500, '未能找到主题-' . $tpl_id);
        }

        Processer::setPath($template['prefix']);
        $render = new Render();
        $res = $render->index($template);
        if ($res['code'] == 0) {
            return $this->errorPage($template, 500, $res['msg']);
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
            ->cache('cms_template_' . $tpl_id, $this->cacheTime, 'cms_template')
            ->find();

        if (!$template) {
            return $this->errorPage(null, 500, '未能找到主题-' . $tpl_id);
        }

        Processer::setPath($template['prefix']);
        $table = 'cms_channel';
        $dbNameSpace = Processer::getDbNamespace();
        $channelScope = Table::defaultScope($table);
        $channel = $dbNameSpace::name($table)->where('id', $id)
            ->where($channelScope)
            ->cache('cms_channel_' . $id, $this->cacheTime, $table)
            ->find();

        if (!$channel) {
            return $this->errorPage($template, 404, '栏目不存在-' . $id);
        }

        if ($channel['link']) {
            $redirectUrl = Processer::resolveWebPath($channel['link']);
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>跳转...</title><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></head><body></body></html>';
        }

        $channel = Processer::detail($table, $channel);
        $render = new Render();
        $res = $render->channel($template, $channel, $page);
        if ($res['code'] == 0) {
            return $this->errorPage($template, 500, $res['msg']);
        }
        return $res['data'];
    }

    /**
     * 内容
     * @param int $id
     * @param int $tpl_id
     * @param int $channel_id
     * @param string $extend_table
     * @param int $is_static
     * @return string
     */
    public function content($id, $tpl_id, $channel_id = 0, $extend_table = '', $is_static = 0)
    {
        $template = $this->template->where('id', $tpl_id)
            ->cache('cms_template_' . $tpl_id, $this->cacheTime, 'cms_template')
            ->find();

        if (!$template) {
            return $this->errorPage(null, 500, '未能找到主题-' . $tpl_id);
        }
        Processer::setPath($template['prefix']);
        $table = $extend_table ?: 'cms_content';
        if (!Table::isAllowTable($table)) {
            return $this->errorPage($template, 403, '禁止访问');
        }
        $dbNameSpace = Processer::getDbNamespace();
        $contentScope = Table::defaultScope($table);
        $content = $dbNameSpace::name($table)
            ->where('id', $id)
            ->where($contentScope)
            ->cache($table . '_' . $id, $this->cacheTime, $table)
            ->find();

        if (!$content) {
            return $this->errorPage($template, 404, '内容不存在-' . $id);
        }

        if (!empty($content['link'])) {
            $redirectUrl = Processer::resolveWebPath($content['link']);
            return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>跳转...</title><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></head><body></body></html>';
        }

        if ($table != 'cms_content') {
            $content['channel_id'] = $channel_id;
            $content['extend_table'] = $table;
            $content['is_show'] = $content['is_show'] ?? 1;
            $content['delete_time'] = $content['delete_time'] ?? null;

            $table = 'cms_content';
        }

        $content = Processer::detail($table, $content);
        $render = new Render();
        $res = $render->content($template, $content, $is_static);
        if ($res['code'] == 0) {
            return $this->errorPage($template, 500, $res['msg']);
        }
        return $res['data'];
    }

    /**
     * 内容点击
     * 
     * @param int $id
     * @return int
     */
    public function click($id)
    {
        $render = new Render();
        $click = $render->click($id);

        $config = Module::getInstance()->config();
        $clickMakeStatic = $config['click_make_static'] ?? 123;
        if ($clickMakeStatic < 10) {
            $clickMakeStatic = 10;
        }
        $makeStatic = $config['make_static'] ?? false;
        if ($makeStatic && $click % $clickMakeStatic == 0) {
            $content = model\CmsContent::withTrashed()->where('id', $id)->find();
            if (!$content) {
                return $click;
            }
            $channel = model\CmsChannel::withTrashed()->where('id', $content['channel_id'])->find();
            $buiilder = new TemplaBuilder;
            $templates = model\CmsTemplate::select();
            foreach ($templates as $template) {
                $res = $buiilder->makeContent($template, $channel, $content);
                trace('[' . $content['title'] . ']生成静态文件：' . $res['msg'], 'info');
            }
        }
        return $click;
    }

    /**
     * 动态页面
     * @param int $html_id
     * @param int $tpl_id
     * @param int $id
     * @return string
     */
    public function dynamic($html_id, $tpl_id, $id = 0)
    {
        $template = $this->template->where('id', $tpl_id)
            ->cache('cms_template_' . $tpl_id, $this->cacheTime, 'cms_template')
            ->find();

        if (!$template) {
            return $this->errorPage(null, 500, '未能找到主题-' . $tpl_id);
        }
        Processer::setPath($template['prefix']);
        $render = new Render();
        $res = $render->dynamic($template, $html_id, $id);
        if ($res['code'] == 0) {
            return $this->errorPage($template, 500, $res['msg']);
        }
        return $res['data'];
    }
}
