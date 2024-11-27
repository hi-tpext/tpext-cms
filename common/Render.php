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

use tpext\think\App;
use tpext\common\Tool;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsTemplateHtml;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\taglib\Table;

class Render
{
    /**
     * @var CmsContentPage
     */
    protected $pageModel = null;

    /**
     * @var CmsTemplateHtml
     */
    protected $htmlModel = null;

    public function __construct()
    {
        $this->pageModel = new CmsContentPage();
        $this->htmlModel = new CmsTemplateHtml();
    }

    /**
     * 生成首页
     * @param array|CmsTemplate $template
     * @param int $page
     * @return array
     */
    public function index($template)
    {
        $tplHtml = $this->htmlModel->where('is_default', 1)
            ->where(['type' => 'index', 'template_id' => $template['id']])
            ->cache('cms_html_index_' . $template['id'], 3600, 'cms_html')
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $template['name'] . ']无首页模板'];
        }

        try {
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            Processer::setPath($template['prefix']);
            $vars = [
                '__site_home__' => $template['prefix'],
                '__page_type__' => 'index',
                '__wconf__' => Module::getInstance()->config(),
            ];
            $config = [
                'cache_prefix' => $tplHtml['path'],
                'tpl_replace_string' => ['@static' => '/theme/' . $template['view_path'] . '/'],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View(App::getRootPath() . $tplFile, $vars, $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[首页]生成出错，' . str_replace(App::getRootPath(), '', $e->getFile()) . '#' . $e->getLine() . '|' . $e->getMessage()];
        }
    }

    /**
     * 生成栏目页
     * @param array|CmsTemplate $template
     * @param array|mixed $channel
     * @param int $page
     * @return array
     */
    public function channel($template, $channel, $page = 1)
    {
        $tplHtml = $this->getHtml($template, 'channel', $channel['id']); //获取绑定的模板

        if (!$tplHtml) {
            //无绑定，使用默认模板
            $tplHtml = $this->htmlModel->where('is_default', 1)
                ->where(['type' => 'channel', 'template_id' => $template['id']])
                ->cache('cms_html_channel_default_' . $template['id'], 3600, 'cms_html')
                ->find();
        }

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目无绑定模板', 'is_over' => true];
        }

        try {
            Processer::setPath($template['prefix']);
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            if ($channel['is_show'] == 0 || $channel['delete_time'] || $channel['channel_path'] == '#') {
                return ['code' => 0, 'msg' => '页面不存在'];
            } else {
                $channel_ids = $channel['children_ids'] ?? [];
                $channel_ids = array_merge([$channel['id']], $channel_ids, explode(',', $channel['extend_ids']));

                $vars = [
                    'page' => $page,
                    'id' => $channel['id'],
                    'channel_id' => $channel['id'],
                    'channel_ids' => implode(',', array_unique($channel_ids)),
                    'channel' => $channel,
                    '__site_home__' => $template['prefix'],
                    '__page_type__' => 'channel',
                    '__set_pagesize__' => $channel['pagesize'],
                    '__set_order_by__' => 'is_top desc,' . ($channel['order_by'] ?: Table::defaultOrder('cms_content')),
                    '__set_page_path__' => $template['prefix'] . Processer::resolveChannelPath($channel) . '-[PAGE].html',
                    '__wconf__' => Module::getInstance()->config(),
                ];
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static' => '/theme/' . $template['view_path'] . '/'],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                    'tpl_cache' => true,
                ];
                $view = new View(App::getRootPath() . $tplFile, $vars, $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);
            }
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目渲染出错，' . str_replace(App::getRootPath(), '', $e->getFile()) . '#' . $e->getLine() . '|' . $e->getMessage() . '。模板文件：' . $tplFile];
        }
    }

    /**
     * 生成内容页
     * @param array|CmsTemplate $template
     * @param array|mixed $content
     * @param int $page
     * @return array
     */
    public function content($template, $content)
    {
        $tplHtml = $this->getHtml($template, 'single', $content['id']); //获取绑定的单页模板

        if (!$tplHtml) {
            $tplHtml = $this->getHtml($template, 'content', $content['id']); //获取绑定的内容模板
        }

        if (!$tplHtml) {
            //无绑定，使用默认模板
            $tplHtml = $this->htmlModel->where('is_default', 1)
                ->where(['type' => 'content', 'template_id' => $template['id']])
                ->cache('cms_html_content_default_' . $template['id'], 3600, 'cms_html')
                ->find();
        }

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容无绑定模板'];
        }

        try {
            Processer::setPath($template['prefix']);
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            $out = '';
            if ($content['is_show'] == 0 || $content['delete_time']) {
                return ['code' => 0, 'msg' => '页面不存在'];
            } else {
                $vars = [
                    'id' => $content['id'],
                    'channel_id' => $content['channel_id'],
                    'content' => $content,
                    'channel' => $content['channel'],
                    '__site_home__' => $template['prefix'],
                    '__page_type__' => 'content',
                    '__wconf__' => Module::getInstance()->config(),
                ];
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static' => '/theme/' . $template['view_path'] . '/'],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                    'tpl_cache' => true,
                ];
                $view = new View(App::getRootPath() . $tplFile, $vars, $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);
            }
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容生成出错，' . str_replace(App::getRootPath(), '', $e->getFile()) . '#' . $e->getLine() . '|' . $e->getMessage() . '。模板文件：' . $tplFile];
        }
    }

    /**
     * 生成动态页面
     * @param array|CmsTemplate $template
     * @param int $tplHtmlId
     * @return array
     */
    public function dynamic($template, $tplHtmlId)
    {
        $tplHtml = $this->htmlModel->where('id', $tplHtmlId)
            ->where(['type' => 'dynamic', 'template_id' => $template['id']])
            ->cache('cms_html_' . $template['id'], 3600)
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '模板不存在-' . $template['id'] . '-' . $tplHtmlId];
        }

        try {
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            Processer::setPath($template['prefix']);
            $vars = [
                '__site_home__' => $template['prefix'],
                '__wconf__' => Module::getInstance()->config(),
            ];
            $param = request()->param();
            $vars = array_merge($vars, $param);
            $config = [
                'cache_prefix' => $tplHtml['path'],
                'tpl_replace_string' => ['@static' => '/theme/' . $template['view_path'] . '/'],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View(App::getRootPath() . $tplFile, $vars, $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[页面]生成出错，' . str_replace(App::getRootPath(), '', $e->getFile()) . '#' . $e->getLine() . '|' . $e->getMessage()];
        }
    }

    /**
     * @param array|CmsTemplate $template
     * @param string $type
     * @param int $toId
     * @return CmsTemplateHtml|null
     */
    protected function getHtml($template, $type, $toId)
    {
        $pageInfo = $this->pageModel->where(['html_type' => $type, 'template_id' => $template['id'], 'to_id' => $toId])
            ->cache('cms_page_' . $template['id'] . '_' . $type . '_' . $toId, 3600, 'cms_page')
            ->find(); //获取绑定的模板

        if ($pageInfo) {
            $tplHtml = $this->htmlModel->where('id', $pageInfo['html_id'])
                ->cache('cms_html_' . $pageInfo['html_id'], 3600, 'cms_html')
                ->find();
            if (!$tplHtml) {
                $pageInfo->delete(); //删除无效的绑定记录
            }
            return $tplHtml;
        }

        return null;
    }

    /**
     * 处理静态资源路径
     * @param array|CmsTemplate $template
     * @return array
     */
    public function copyStatic($template)
    {
        $staticPath = App::getRootPath() . 'theme/' . $template['view_path'] . '/static';
        $staticPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $staticPath);
        $staticDir = 'theme' . DIRECTORY_SEPARATOR . $template['view_path'];

        Tool::deleteDir(App::getPublicPath() . $staticDir);
        $res = Tool::copyDir($staticPath, App::getPublicPath() . $staticDir);
        if ($res) {
            file_put_contents(
                App::getPublicPath() . $staticDir . DIRECTORY_SEPARATOR . '不要修改此目录中文件.txt',
                '此目录是存放模板静态资源的，' . "\n"
                . '不要修改、替换文件或上传新文件到此目录及子目录，' . "\n"
                . '否则重新发布模板资源后改动文件将还原或丢失，' . "\n"
                . '原始文件存放于' . $staticPath . '目录下。'
            );
            return ['code' => 1, 'msg' => '[静态资源]发布成功：' . "{$staticPath} => public" . DIRECTORY_SEPARATOR . "{$staticDir}"];
        }

        return ['code' => 0, 'msg' => '[静态资源]发布失败：' . "{$staticPath} => public" . DIRECTORY_SEPARATOR . "{$staticDir}"];
    }

    /**
     * 替换静态资源路径
     * @param array|mixed $template
     * @param string $content
     * @return string
     */
    public function replaceStaticPath($template, $content)
    {
        $v = Module::getInstance()->config('assets_ver', '1.0');
        $staticDir = '/theme/' . $template['view_path'] . '/';
        $content = preg_replace('/(<link\s+[^>]*?href=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.css)([\'\"])/is', "$1{$staticDir}$2?v={$v}$3", $content);
        $content = preg_replace('/(<script\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.js)([\'\"])/is', "$1{$staticDir}$2?v={$v}$3", $content);
        $content = preg_replace('/(<img\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.\w+)([\'\"])/is', "$1{$staticDir}$2?v={$v}$3", $content);

        return $content;
    }
}
