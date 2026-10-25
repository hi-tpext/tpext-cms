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
use tpext\common\ExtLoader;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsTemplateHtml;

class RouteBuilder
{
    /**
     * 重建并写入路由文件
     *
     * @param boolean $forceWrite 强制写入（无视120秒节流）
     * @return boolean 是否成功（库中数据非法/写入失败返回false，保持上一版路由）
     */
    public function builder($forceWrite = false)
    {
        $channels = CmsChannel::select();

        $channelPathArr = [];
        $contentPathArr = [];

        foreach ($channels as $cha) {
            //写入侧兜底：这三个字段会拼进生成的PHP路由文件，表单之外的写入口（autopost、
            //历史数据、直接改库）不受表单校验约束，任一非法即整次中止不写、保留上一版路由
            if (!$this->safeChannelPath($cha['channel_path'])) {
                trace('路由生成中止：栏目[id=' . $cha['id'] . ']channel_path含非法字符：' . $cha['channel_path'], 'error');
                return false;
            }
            if (!$this->safeChannelPath($cha['content_path'])) {
                trace('路由生成中止：栏目[id=' . $cha['id'] . ']content_path含非法字符：' . $cha['content_path'], 'error');
                return false;
            }
            if (!preg_match('/^\w*$/', $cha['extend_table'] ?? '')) {
                trace('路由生成中止：栏目[id=' . $cha['id'] . ']extend_table含非法字符：' . $cha['extend_table'], 'error');
                return false;
            }

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
                    'channel_id' => (int) $cha['id'],
                    'extend_table' => $cha['extend_table'] ?? '',
                ];
            }

            $channelPathArr[$channel_path_key]['ids'][] = (int) $cha['id'];
            $contentPathArr[$content_path_key]['ids'][] = (int) $cha['id'];
        }

        $indexRoutes = $this->makeIndexRoute();
        $channelRoutes = $this->makeChannelRoute($channelPathArr);
        $contentRoutes = $this->makeContentRoute($contentPathArr);

        try {
            $res = $this->witeToFile(array_merge($indexRoutes, $channelRoutes, $contentRoutes), $forceWrite);
        } catch (\Throwable $e) {
            trace('write route error:' . $e->__tostring(), 'error');
            return false;
        }

        return $res !== false;
    }

    /**
     * 栏目/内容路径白名单：'#'（无独立栏目页）、字母数字(含中文)、-、_、/、.、[id]占位符
     * 与后台表单保存侧校验一致
     *
     * @param string $path
     * @return bool
     */
    protected function safeChannelPath($path)
    {
        return is_string($path) && ($path === '#' || preg_match('/^[\w\-\/\[\]\.]+$/u', $path));
    }

    /**
     * 路由路径白名单：字母数字(含中文)、-、_、/、.、[id]占位符
     *
     * @param string $path
     * @return bool
     */
    protected function safePath($path)
    {
        return is_string($path) && $path !== '' && preg_match('/^[\w\-\/\[\]\.]+$/u', $path);
    }

    /**
     * 生成首页路由
     * @return string[]
     */
    public function makeIndexRoute()
    {
        return [
            "Route::get('__prefix__$', Page::class . '@index')",
            "Route::get('__prefix__index$', Page::class . '@index')",
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
                    $rules[] = [
                        'rule' => "Route::get('__prefix__c/{$path}-<page>$', Page::class . '@channel')",
                        'append' => ['id' => $id],
                    ];
                    $rules[] = [
                        'rule' => "Route::get('__prefix__c/{$path}$', Page::class . '@channel')",
                        'append' => ['id' => $id],
                    ];
                }
            } else {
                $path = str_replace('[id]', '<id>', $path);
                $rules[] = "Route::get('__prefix__c/{$path}-<page>$', Page::class . '@channel')->pattern(['id' => '\d+', 'page' => '\d+'])";
                $rules[] = "Route::get('__prefix__c/{$path}$', Page::class . '@channel')->pattern(['id' => '\d+'])";
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
            $channel_id = $url['path'] == '[id]' ? 0 : $url['channel_id'];
            $extend_table = $url['path'] == '[id]' ? "''" : "'{$url['extend_table']}'";
            $rules[] = "Route::get('__prefix__d/{$path}$', Page::class . '@content')->pattern(['id' => '\d+'])"
                . "->append(['channel_id' => {$channel_id}, 'extend_table' => {$extend_table}])";
        }

        $rules[] = "Route::get('__prefix__d/__click__<id>$', Page::class . '@click')->pattern(['id' => '\d+'])->ajax()";

        return $rules;
    }

    /**
     * 获取单页列表
     * @param array|CmsTemplate $template
     * @return string[]
     */
    protected function getSinglePages($template)
    {
        $singlePages = CmsTemplateHtml::where('template_id', $template['id'])->where('type', 'single')->select();

        if (empty($singlePages)) {
            return [];
        }

        $pages = [];
        foreach ($singlePages as $page) {
            $path = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?).html$/i', '$1', $page['path']);
            $pages[$path] = [
                'id' => $page['id'],
                'to_id' => $page['to_id'],
                'path' => $path,
            ];
        }

        return array_values($pages);
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
            $pages[$path] = [
                'id' => $page['id'],
                'path' => $path,
            ];
        }

        return array_values($pages);
    }

    /**
     * 写入路由文件
     *
     * @param array $routesRules
     * @param boolean $forceWrite
     * @return boolean false=数据非法或写失败（保持上一版路由），true=已写入或内容未变跳过
     */
    protected function witeToFile($routesRules, $forceWrite)
    {
        $routeFile = App::getRootPath() . 'route/tpext-cms.php';

        if (is_file($routeFile) && time() - filemtime($routeFile) < 120 && !$forceWrite) {
            return true;
        }

        $templates = CmsTemplate::select();

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' *tpext.cms 自动生成扩展路由，请不要手动修改.';
        $lines[] = ' */';
        $lines[] = '';
        if (ExtLoader::isWebman()) {
            $lines[] = 'use tpext\cms\common\webman\Route;//仅用于cms路由，不要使用在其他地方';
        } else {
            $lines[] = 'use think\facade\Route;';
        }

        $lines[] = 'use tpext\cms\common\Page;';
        $lines[] = '';

        foreach ($templates as $tmpl) {
            //prefix拼进每一行路由字符串，非法值（空串/../引号等）会使整份文件不可用：任一模板非法即整次中止
            if (!PathGuard::safePrefix($tmpl['prefix'])) {
                trace('路由生成中止：模板[id=' . $tmpl['id'] . ']prefix非法：' . var_export($tmpl['prefix'], true), 'error');
                return false;
            }

            $prefix = trim($tmpl['prefix'], '/');

            $singlePages = $this->getSinglePages($tmpl);
            $dynamicPages = $this->getDynamicPages($tmpl);

            $prefix = $prefix ? '/' . $prefix : '';

            foreach ($routesRules as $rule) {
                if (is_array($rule)) {
                    $append = $rule['append'];
                    $rule = $rule['rule'];
                    $append['tpl_id'] = (int) $tmpl['id'];
                    $rule = str_replace('__prefix__', $prefix . '/', $rule) . "->append([";
                    foreach ($append as $k => $v) {
                        $rule .= "'{$k}' => " . intval($v) . ", ";
                    }
                    $rule = rtrim($rule, ', ') . "]);";
                    $lines[] = $rule;
                } else {
                    $lines[] = str_replace('__prefix__', $prefix . '/', $rule) . "->append(['tpl_id' => " . intval($tmpl['id']) . "]);";
                }
            }

            foreach ($singlePages as $page) {
                if (!$this->safePath($page['path'])) {
                    continue;
                }
                if ($page['to_id'] > 0) {
                    $lines[] = "Route::get('{$prefix}/{$page['path']}$', Page::class . '@content')->append(['id' => " . intval($page['to_id']) . ", 'tpl_id' => " . intval($tmpl['id']) . "]);";
                } else {
                    $lines[] = "Route::get('{$prefix}/{$page['path']}$', Page::class . '@dynamic')->append(['html_id' => " . intval($page['id']) . ", 'tpl_id' => " . intval($tmpl['id']) . "]);";
                }
            }

            foreach ($dynamicPages as $page) {
                if (!$this->safePath($page['path'])) {
                    continue;
                }
                if ($page['path'] == 'tag') {
                    $lines[] = "Route::get('{$prefix}/e/tag-<id>$', Page::class . '@dynamic')->pattern(['id' => '\d+'])"
                        . "->append(['html_id' => " . intval($page['id']) . ", 'tpl_id' => " . intval($tmpl['id']) . "]);";
                } else {
                    $lines[] = "Route::get('{$prefix}/e/{$page['path']}$', Page::class . '@dynamic')->append(['html_id' => " . intval($page['id']) . ", 'tpl_id' => " . intval($tmpl['id']) . "]);";
                }
            }

            $lines[] = '';
        }

        if (!is_dir(App::getRootPath() . 'route/')) {
            mkdir(App::getRootPath() . 'route/', 0755, true);
        }

        //原子写：内容未变跳过（不刷mtime），变化时临时文件+rename，避免并发include到写一半的文件
        $content = implode(PHP_EOL, $lines);
        if (is_file($routeFile) && md5_file($routeFile) === md5($content)) {
            return true;
        }

        $tmpFile = $routeFile . '.' . uniqid() . '.tmp';
        if (file_put_contents($tmpFile, $content) === false) {
            @unlink($tmpFile);
            trace('路由生成失败：无法写入临时文件' . $tmpFile, 'error');
            return false;
        }
        if (!rename($tmpFile, $routeFile)) {
            @unlink($tmpFile);
            trace('路由生成失败：临时文件改名失败' . $tmpFile, 'error');
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($routeFile, true);
        }

        if (ExtLoader::isWebman()) {
            $routeConfig = file_get_contents(config_path() . '/route.php');

            if (!strstr($routeConfig, 'route/tpext-cms.php')) {
                $routeConfig .= PHP_EOL . '//引入cms路由';
                $routeConfig .= PHP_EOL . "if (file_exists(base_path('route/tpext-cms.php'))) {";
                $routeConfig .= PHP_EOL . "    require_once base_path('route/tpext-cms.php');";
                $routeConfig .= PHP_EOL . '}';
            }

            file_put_contents(config_path() . '/route.php', $routeConfig);

            ExtLoader::reloadWebman();
        }

        return true;
    }
}
