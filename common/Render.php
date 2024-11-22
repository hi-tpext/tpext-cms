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

class Render
{
    /**
     * 生成栏目页
     *
     * @param array|CmsTemplate $template
     * @param array|mixed $channel
     * @param int $page
     * @return array
     */
    public function channel($template, $channel, $page = 1)
    {
        $pageInfo = CmsContentPage::where(['html_type' => 'channel', 'template_id' => $template['id'], 'to_id' => $channel['id']])
            ->find(); //获取绑定的模板

        $tplHtml = null;

        if ($pageInfo) {
            $tplHtml = CmsTemplateHtml::where('id', $pageInfo['html_id'])->find();
        } else {
            //无绑定，使用默认模板
            $tplHtml = CmsTemplateHtml::where('is_default', 1)
                ->where(['type' => 'channel', 'template_id' => $template['id']])
                ->find();
        }
        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目无绑定模板', 'is_over' => true];
        }

        try {
            Processer::setPath($template['prefix']);
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            if ($channel['is_show'] == 0 || $channel['delete_time']) {
                return ['code' => 0, 'msg' => '页面不存在'];
            } else {
                $vars = [
                    'id' => $channel['id'],
                    '__SITE_HOME__' => $template['prefix'],
                    'page' => $page,
                    'channel' => $channel,
                    'pagesize' => $channel['pagesize'],
                    'orderBy' => $channel['order_by'],
                    'page_path' => $template['prefix'] . Processer::resolveChannelPath($channel) . '-[PAGE].html',
                    'web_config' => Module::getInstance()->config(),
                ];
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                    'tpl_cache' => true,
                ];
                $view = new View(App::getRootPath() . $tplFile, $vars,  $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);
            }
            return ['code' => 1, 'msg' => 'ok', 'data' => $out, 'page_info' => $pageInfo, 'tpl_html' => $tplHtml];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => $channel['name'] . ']栏目渲染出错，' . str_replace(App::getRootPath(), '', $e->getFile()) . '#' . $e->getLine() . '|' . $e->getMessage() . '。模板文件：' . $tplFile, 'out' => $out];
        }
    }

    /**
     * 生成内容页
     *
     * @param array|CmsTemplate $template
     * @param array|mixed $content
     * @param int $page
     * @return array
     */
    public function content($template, $content)
    {
        $pageInfo = CmsContentPage::where(['html_type' => 'single', 'template_id' => $template['id'], 'to_id' => $content['id']])
            ->find(); //获取绑定的单页模板

        if (!$pageInfo) {
            $pageInfo = CmsContentPage::where(['html_type' => 'content', 'template_id' => $template['id'], 'to_id' => $content['id']])
                ->find(); //获取绑定的模板
        }

        $tplHtml = null;

        if ($pageInfo) {
            $tplHtml = CmsTemplateHtml::where('id', $pageInfo['html_id'])->find();
        } else {
            //无绑定，使用默认模板
            $tplHtml = CmsTemplateHtml::where('is_default', 1)
                ->where(['type' => 'content', 'template_id' => $template['id']])
                ->find();
        }
        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容无绑定模板'];
        }

        try {
            Processer::setPath($template['prefix']);
            $tplFile =  str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            $out = '';
            if ($content['is_show'] == 0 || $content['delete_time']) {
                return ['code' => 0, 'msg' => '页面不存在'];
            } else {
                $vars = [
                    'id' => $content['id'],
                    'content' => $content,
                    '__SITE_HOME__' => $template['prefix'],
                    'web_config' => Module::getInstance()->config(),
                ];
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                    'tpl_cache' => true,
                ];
                $view = new View(App::getRootPath() . $tplFile, $vars,  $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);
            }
            return ['code' => 1, 'msg' => 'ok', 'data' => $out, 'page_info' => $pageInfo, 'tpl_html' => $tplHtml];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => $content['title'] . ']内容生成出错，' . str_replace(App::getRootPath(), '', $e->getFile()) . '#' . $e->getLine() . '|' . $e->getMessage() . '。模板文件：' . $tplFile, 'out' => $out];
        }
    }

    /**
     * 生成首页
     *
     * @param array|CmsTemplate $template
     * @param int $page
     * @return array
     */
    public function index($template)
    {
        $tplHtml = CmsTemplateHtml::where('is_default', 1)
            ->where(['type' => 'index', 'template_id' => $template['id']])
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $template['name'] . ']无首页模板'];
        }

        try {
            $tplFile =  str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            Processer::setPath($template['prefix']);
            $vars = [
                '__SITE_HOME__' => $template['prefix'],
                'web_config' => Module::getInstance()->config(),
            ];
            $config = [
                'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                'cache_prefix' => $tplHtml['path'],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View(App::getRootPath() . $tplFile, $vars,  $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            return ['code' => 1, 'msg' => 'ok', 'data' => $out, 'tpl_html' => $tplHtml];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[首页]生成出错，' .  str_replace(App::getRootPath(), '', $e->getFile())  . '#' . $e->getLine() . '|' . $e->getMessage()];
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
        $tplHtml = CmsTemplateHtml::where('id', $tplHtmlId)
            ->where(['type' => 'dynamic', 'template_id' => $template['id']])
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '模板不存在-' . $template['id'] . '-' . $tplHtmlId];
        }

        try {
            $tplFile =  str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            Processer::setPath($template['prefix']);
            $vars = [
                '__SITE_HOME__' => $template['prefix'],
                'web_config' => Module::getInstance()->config(),
            ];
            $get = request()->get();
            $vars = array_merge($vars, $get);
            $config = [
                'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                'cache_prefix' => $tplHtml['path'],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View(App::getRootPath() . $tplFile, $vars,  $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            return ['code' => 1, 'msg' => 'ok', 'data' => $out, 'tpl_html' => $tplHtml];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[页面]生成出错，' .  str_replace(App::getRootPath(), '', $e->getFile())  . '#' . $e->getLine() . '|' . $e->getMessage()];
        }
    }

    /**
     * 处理静态资源路径
     *
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
            return ['code' => 1, 'msg' => '[静态资源]发布成功：' . "{$staticPath} => {$staticDir}"];
        }

        return ['code' => 0, 'msg' =>  '[静态资源]发布失败：' . "{$staticPath} => {$staticDir}"];
    }

    /**
     * 替换静态资源路径
     *
     * @param array|mixed $template
     * @param string $content
     * @return string
     */
    public function replaceStaticPath($template, $content)
    {
        $staticDir = '/theme/' . $template['view_path']  . '/';
        $content = preg_replace('/(<link\s+[^>]*?href=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.css[\'\"])/is', "$1{$staticDir}$2", $content);
        $content = preg_replace('/(<script\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.js[\'\"])/is', "$1{$staticDir}$2", $content);
        $content = preg_replace('/(<img\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.\w+[\'\"])/is', "$1{$staticDir}$2", $content);

        return $content;
    }
}
