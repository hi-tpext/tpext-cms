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
use tpext\cms\common\model\CmsTemplateHtml;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsContentPage;
use tpext\think\App;

class RouteBuilder
{
    public function builder($focusWrite = false)
    {
        $channels = CmsChannel::select();

        $channelPathArr = [];
        $contentPathArr = [];

        foreach ($channels as $cha) {
            $channel_path_key = md5($cha['channel_path']);
            $content_path_key = md5($cha['content_path']);

            if (!isset($channelPathArr[$channel_path_key])) {
                $channelPathArr[$channel_path_key] = [
                    'ids' => [],
                    'path' => $cha['channel_path'],
                ];
            }
            if (!isset($contentPathArr[$content_path_key])) {
                $contentPathArr[$content_path_key] = [
                    'ids' => [],
                    'path' => $cha['content_path'],
                ];
            }

            $channelPathArr[$channel_path_key]['ids'][] = $cha['id'];
            $contentPathArr[$content_path_key]['ids'][] = $cha['id'];
        }

        $indexRoutes = $this->makeIndexRoute();
        $channelRoutes = $this->makeChannelRoute($channelPathArr);
        $contentRoutes = $this->makeContentRoute($contentPathArr);

        $this->witeToFile(array_merge($indexRoutes, $channelRoutes, $contentRoutes), $focusWrite);
    }

    /**
     * 生成首页路由
     * @return string[]
     */
    public function makeIndexRoute()
    {
        return [
            "Route::get('__prefix__', Page::class . '@index')",
        ];
    }

    /**
     * 生成栏目路由
     * @param array $urlPaths
     * @return string[]
     */
    protected function makeChannelRoute($urlPaths)
    {
        $rules = [];
        foreach ($urlPaths as $url) {
            $path = $url['path'];
            if ($path == '#') {
                continue;
            }
            if (stristr($path, '[id]') === false) {
                $ids = $url['ids'];
                foreach ($ids as $id) {
                    $rules[] = "Route::get('__prefix__channel/{$path}-<page>', Page::class . '@channel?id={$id}')->pattern(['id' => '\d+', 'page' => '\d+'])";
                    $rules[] = "Route::get('__prefix__channel/{$path}', Page::class . '@channel?id={$id}')->pattern(['id' => '\d+'])";
                }
            } else {
                $path = str_replace('[id]', '<id>', $path);
                $rules[] = "Route::get('__prefix__channel/{$path}-<page>', Page::class . '@channel')->pattern(['id' => '\d+', 'page' => '\d+'])";
                $rules[] = "Route::get('__prefix__channel/{$path}', Page::class . '@channel')->pattern(['id' => '\d+'])";
            }
        }
        return $rules;
    }

    /**
     * 生成内容路由
     * @param array $urlPaths
     * @return string[]
     */
    protected function makeContentRoute($urlPaths)
    {
        $rules = [];
        foreach ($urlPaths as $url) {
            $path = $url['path'];
            $path = str_replace('[id]', '<id>', $path);
            $rules[] = "Route::get('__prefix__content/{$path}', Page::class . '@content')->pattern(['id' => '\d+'])";
        }
        return $rules;
    }

    /**
     * 获取单页列表
     * @param array|CmsTemplate $template
     * @return string[]
     */
    protected function getSinglePages($template)
    {
        $singlePages = CmsContentPage::where('template_id', $template['id'])->with(['template_html'])
            ->where('html_type', 'single')->select();

        if (empty($singlePages)) {
            return [];
        }

        $pages = [];
        foreach ($singlePages as $page) {
            $html = $page['template_html'];
            if (empty($html)) {
                continue;
            }
            $path = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?).html$/i', '$1', $html['path']);
            $pages[] = [
                'id' => $page['to_id'],
                'path' => $path,
            ];
        }

        return $pages;
    }

    /**
     * 获取动态页列表
     * @param array|CmsTemplate $template
     * @return string[]
     */
    protected function getDynamicPages($template)
    {
        $dynamicPages = CmsTemplateHtml::where('template_id', $template['id'])
            ->where('type', 'dynamic')->select();

        if (empty($dynamicPages)) {
            return [];
        }

        $pages = [];
        foreach ($dynamicPages as $page) {
            $path = preg_replace('/theme\/[\w\-]+?\/dynamic\/([\w\-]+?).html$/i', '$1', $page['path']);
            $pages[] = [
                'id' => $page['id'],
                'path' => $path,
            ];
        }

        return $pages;
    }

    /**
     * Undocumented function
     *
     * @param array $routesGroup
     * @param boolean $focusWrite
     * @return void
     */
    protected function witeToFile($routesRules, $focusWrite)
    {
        $routeFile = App::getRootPath() . 'route/tpex-tcms.php';

        if (is_file($routeFile) && time() - filemtime($routeFile) < 120 && !$focusWrite) {
            return;
        }

        $templates = CmsTemplate::select();

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' *tpext.cms 自动生成扩展路由，请不要手动修改.';
        $lines[] = ' *时间:' . date('Y-m-d H:i:s');
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'use think\Facade\Route;';
        $lines[] = 'use tpext\cms\common\Page;';
        $lines[] = '';

        foreach ($templates as $tmpl) {
            $prefix = trim($tmpl['prefix'], '/');

            $singlePages = $this->getSinglePages($tmpl);
            $dynamicPages = $this->getDynamicPages($tmpl);

            if ($prefix) {
                foreach ($routesRules as $rule) {
                    $lines[] = str_replace('__prefix__', '/' . $prefix . '/', $rule) . "->append(['tpl_id' => {$tmpl['id']}]);";
                }

                foreach ($singlePages as $page) {
                    $lines[] = "Route::get('/{$prefix}/{$page['path']}', Page::class . '@content?id={$page['id']}')->append(['tpl_id' => {$tmpl['id']}]);";
                }

                foreach ($dynamicPages as $page) {
                    $lines[] = "Route::get('/{$prefix}/dynamic/{$page['path']}', Page::class . '@dynamic?html_id={$page['id']}')->append(['tpl_id' => {$tmpl['id']}]);";
                }
            } else {
                foreach ($routesRules as $rule) {
                    $lines[] = str_replace('__prefix__', '/', $rule) . "->append(['tpl_id' => {$tmpl['id']}]);";
                }

                foreach ($singlePages as $page) {
                    $lines[] = "Route::get('/{$page['path']}', Page::class . '@content?id={$page['id']}')->append(['tpl_id' => {$tmpl['id']}]);";
                }

                foreach ($dynamicPages as $page) {
                    if ($page['path'] == 'tag') {
                        $lines[] = "Route::get('/dynamic/tag-<id>', Page::class . '@dynamic?html_id={$page['id']}')->pattern(['id' => '\d+'])->append(['tpl_id' => {$tmpl['id']}]);";
                    } else {
                        $lines[] = "Route::get('/dynamic/{$page['path']}', Page::class . '@dynamic?html_id={$page['id']}')->append(['tpl_id' => {$tmpl['id']}]);";
                    }
                }
            }

            $lines[] = '';
        }

        if (!is_dir(App::getRootPath() . 'route/')) {
            mkdir(App::getRootPath() . 'route/', 0755, true);
        }

        file_put_contents($routeFile, implode(PHP_EOL, $lines));
    }
}
