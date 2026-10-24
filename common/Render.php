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
use tpext\cms\common\Cache;
use tpext\common\ExtLoader;
use tpext\cms\common\taglib\Table;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\model\CmsTemplateHtml;

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

    protected $cacheTime = 3600 * 24 * 7;

    public function __construct()
    {
        $this->pageModel = new CmsContentPage();
        $this->htmlModel = new CmsTemplateHtml();
    }

    /**
     * 生成首页
     * @param array|CmsTemplate $template
     * @return array
     */
    public function index($template)
    {
        $tplHtml = $this->htmlModel->where('is_default', 1)
            ->where(['type' => 'index', 'template_id' => $template['id']])
            ->cache('cms_html_index_' . $template['id'], $this->cacheTime, 'cms_html')
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $template['name'] . ']无首页模板'];
        }

        try {
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            $vars = [
                'page_title' => '首页' . '_',
                '__site_home__' => $template['prefix'],
                '__page_type__' => 'index',
                '__set_order_by__' => 'is_recommend desc,',
                '__wconf__' => $this->getWconfig(),
            ];
            $vars['vars'] = $vars;
            $config = [
                'cache_prefix' => $tplHtml['path'],
                'tpl_replace_string' => ['@static@' => '/theme/' . $template['view_path'] . '/', '@site_home@' => $template['prefix']],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View(App::getRootPath() . $tplFile, $vars, $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);

            $out = str_replace('</body>', '<script type="text/javascript">' . $this->advScript() . "\n" . '</script>' . "\n" . '</body>', $out);
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[首页]生成出错，' . str_replace(App::getRootPath(), '', $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage())];
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
                ->cache('cms_html_channel_default_' . $template['id'], $this->cacheTime, 'cms_html')
                ->find();
        }

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目无绑定模板', 'is_over' => true];
        }

        try {
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            if ($channel['is_show'] != 1 || $channel['delete_time'] || $channel['channel_path'] == '#') {
                return ['code' => 0, 'msg' => '栏目不存在'];
            } else {
                $channel_ids = $channel['extend_ids'] ? explode(',', $channel['extend_ids']) : [];

                $page_path = $template['prefix'] . Processer::resolveChannelPath($channel) . '-[PAGE].html';

                $get = array_filter(request()->get());
                $params = [];

                if (!empty($get)) {
                    $params = $this->filterParams($get);
                    $page_path .= '?' . http_build_query($params);
                    unset($params['page']); //分页页码不作为模板变量
                }

                $vars = [
                    'page' => $page,
                    'id' => $channel['id'],
                    'channel_id' => $channel['id'],
                    'cid' => $channel['id'],
                    'parent_id' => $channel['parent_id'],
                    'pid' => $channel['parent_id'],
                    'channel_ids' => !empty($channel_ids) ? implode(',', array_merge([$channel['id']], $channel_ids)) : '',
                    'children_ids' => implode(',', $channel['children_ids'] ?? []),
                    'extend_ids' => $channel['extend_ids'],
                    'channel' => $channel,
                    'page_title' => $channel['name'] . '_',
                    'page_description' => $channel['description'] ?: $channel['name'],
                    'page_keywords' => $channel['keywords'] ?: $channel['name'],
                    '__site_home__' => $template['prefix'],
                    '__page_type__' => 'channel',
                    '__set_pagesize__' => $channel['pagesize'],
                    '__set_order_by__' => 'is_top desc,',
                    '__set_page_path__' => $page_path,
                    '__wconf__' => $this->getWconfig(),
                ];

                $vars = array_merge($vars, $params);
                $vars['vars'] = $vars;
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static@' => '/theme/' . $template['view_path'] . '/', '@site_home@' => $template['prefix']],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                    'tpl_cache' => true,
                ];
                $view = new View(App::getRootPath() . $tplFile, $vars, $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);

                $out = str_replace('</body>', '<script type="text/javascript">' . $this->advScript() . "\n" . '</script>' . "\n" . '</body>', $out);
            }
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目生成出错，' . str_replace(App::getRootPath(), '', $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage()) . '。模板文件：' . $tplFile];
        }
    }

    /**
     * 生成内容页
     * @param array|CmsTemplate $template
     * @param array|mixed $content
     * @param int $is_static
     * @return array
     */
    public function content($template, $content, $is_static = 0)
    {
        $tplHtml = !empty($content['extend_table']) ? null : $this->getHtml($template, 'single', $content['id']); //获取绑定的单页模板

        if (!$tplHtml) {
            $tplHtml = $this->getHtml($template, 'content', $content['channel_id']); //获取绑定的内容模板
        }

        if (!$tplHtml) {
            //无绑定，使用默认模板
            $tplHtml = $this->htmlModel->where('is_default', 1)
                ->where(['type' => 'content', 'template_id' => $template['id']])
                ->cache('cms_html_content_default_' . $template['id'], $this->cacheTime, 'cms_html')
                ->find();
        }

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容无绑定模板'];
        }

        try {
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            $out = '';
            if ($content['__not_found__'] || $content['is_show'] != 1 || $content['delete_time']) {
                return ['code' => 0, 'msg' => '内容不存在'];
            } else {
                //is_static=1（静态生成）时点击数只读，不把发布流量计入；动态访问照常累计
                $content['click'] = $this->click($content['id'], $content['extend_table'] ?? '', !$is_static);
                $vars = [
                    'id' => $content['id'],
                    'channel_id' => $content['channel_id'],
                    'cid' => $content['channel_id'],
                    'parent_id' => $content['channel']['parent_id'],
                    'pid' => $content['channel']['parent_id'],
                    'content_id' => $content['id'],
                    'content' => $content,
                    'channel' => $content['channel'],
                    'page_title' => ($content['title'] ?? '') . '_' . $content['channel']['name'] . '_',
                    'page_description' => $content['description'] ?? ($content['title'] ?? ''),
                    'page_keywords' => $content['keywords'] ?? ($content['title'] ?? ''),
                    '__site_home__' => $template['prefix'],
                    '__page_type__' => 'content',
                    '__wconf__' => $this->getWconfig(),
                ];

                $get = array_filter(request()->get());

                if (!empty($get)) {
                    $vars = array_merge($vars, $this->filterParams($get));
                }

                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static@' => '/theme/' . $template['view_path'] . '/', '@site_home@' => $template['prefix']],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                    'tpl_cache' => true,
                ];
                $view = new View(App::getRootPath() . $tplFile, $vars, $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);

                if (!empty($content['extend_table']) && $is_static == 1) {
                    $url = $template['prefix'] . 'd/__click__' . $content['id'];
                    $out = str_replace('</body>', '<script type="text/javascript">' . $this->clickScript($url) . "\n" . '</script>' . "\n" . '</body>', $out);
                }

                $out = str_replace('</body>', '<script type="text/javascript">' . $this->advScript() . "\n" . '</script>' . "\n" . '</body>', $out);
            }
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . ($content['title'] ?? '') . ']内容生成出错，' . str_replace(App::getRootPath(), '', $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage()) . '。模板文件：' . $tplFile];
        }
    }

    /**
     * 内容点击
     *
     * @param int $id
     * @param string $extend_table
     * @param bool $increment 静态生成上下文传false：只读当前值，不累计、不写缓存
     * @return int
     */
    public function click($id, $extend_table = '', $increment = true)
    {
        $table = $extend_table ?: 'cms_content';
        $dbNameSpace = Processer::getDbNamespace();
        $contentScope = Table::defaultScope($table);
        $key = $table . '_click_' . $id;

        $click = 0;
        if (Cache::has($key)) {
            $click = Cache::get($key);
        } else {
            $content = $dbNameSpace::name($table)
                ->where('id', $id)
                ->where($contentScope)
                ->find();

            $click = $content ? ($content['click'] ?? 0) : 0;
        }

        if (!$increment) {
            return (int) $click;
        }

        $click += 1;
        Cache::tag($table)->set($key, $click);

        $config = Module::getInstance()->config();
        $clickWrite = $config['click_write'] ?? 10;
        if ($clickWrite < 10) {
            $clickWrite = 10;
        }
        if ($click % $clickWrite == 0) {
            if ($table == 'cms_content') {//content表才更新，其他表不确定有此字段
                $dbNameSpace::name($table)
                    ->where('id', $id)
                    ->where($contentScope)
                    ->update(['click' => $click]);
            }
        }

        ExtLoader::trigger($key, $id);

        return $click;
    }

    protected function clickScript($url)
    {
        $script = <<<EOT

    var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("");
    xhr.open("GET", "{$url}", true);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4 && xhr.status == 200) {
            console.log('click:' + xhr.responseText);
        }
    };
    xhr.send();
EOT;

        return $script;
    }

    protected function advScript()
    {
        $script = <<<EOT

    try {
        if (window.console && window.console.log) {
            console.log("%c Powered by © tpext.cms", "font-size:32px;background:#333;color:#fff");
            console.log("%c https://github.com/hi-tpext/tpext-cms", "font-size:20px;background:#333;color:#fff");
        }
    } catch (e) {
     
    }
EOT;

        return $script;
    }

    /**
     * 生成动态页面
     * @param array|CmsTemplate $template
     * @param int $tplHtmlId
     * @param int $id
     * @return array
     */
    public function dynamic($template, $tplHtmlId, $id = 0)
    {
        $tplHtml = $this->htmlModel->where('id', $tplHtmlId)
            ->where('template_id', $template['id'])
            ->where('type', 'in', ['dynamic', 'single'])
            ->cache('cms_html_' . $tplHtmlId, $this->cacheTime, 'cms_html')
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '模板不存在-' . $template['id'] . '-' . $tplHtmlId];
        }

        try {
            $tplFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);

            $page_path = '';

            $get = array_filter(request()->get());
            $params = $this->filterParams($get, false);

            if (!empty($get)) {
                $page_path = '?' . http_build_query(array_diff_key($params, ['page' => 1]));
                $page_path .= $page_path == '?' ? '' : '&';
                $page_path .= 'page=[PAGE]';
            }

            $vars = [
                'id' => $id,
                '__site_home__' => $template['prefix'],
                '__wconf__' => $this->getWconfig(),
                '__set_page_path__' => $page_path,
                '__page_type__' => 'dynamic',
            ];

            $vars = array_merge($vars, $params);

            $vars['vars'] = $vars;
            $config = [
                'cache_prefix' => $tplHtml['path'],
                'tpl_replace_string' => ['@static@' => '/theme/' . $template['view_path'] . '/', '@site_home@' => $template['prefix']],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View(App::getRootPath() . $tplFile, $vars, $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            return ['code' => 1, 'msg' => 'ok', 'data' => $out];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[页面]生成出错，' . str_replace(App::getRootPath(), '', $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage())];
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
        if ($type == 'single') {
            $tplHtml = $this->htmlModel->where(['type' => $type, 'template_id' => $template['id'], 'to_id' => $toId])
                ->cache('cms_page_' . $template['id'] . '_' . $type . '_' . $toId, $this->cacheTime, 'cms_html')
                ->find();
            return $tplHtml;
        }

        $pageInfo = $this->pageModel->where(['html_type' => $type, 'template_id' => $template['id'], 'to_id' => $toId])
            ->cache('cms_page_' . $template['id'] . '_' . $type . '_' . $toId, $this->cacheTime, 'cms_page')
            ->find(); //获取绑定的模板

        if ($pageInfo) {
            $tplHtml = $this->htmlModel->where('id', $pageInfo['html_id'])
                ->cache('cms_html_' . $pageInfo['html_id'], $this->cacheTime, 'cms_html')
                ->find();
            if (!$tplHtml) {
                $pageInfo->delete(); //删除无效的绑定记录
            }
            return $tplHtml;
        }

        return null;
    }

    protected function getWconfig()
    {
        $config = Module::getInstance()->config();
        unset($config['allow_tables'], $config['editor']);
        return $config;
    }

    /**
     * 处理静态资源路径
     * @param array|CmsTemplate $template
     * @return array
     */
    public function copyStatic($template)
    {
        $staticPath = 'theme/' . $template['view_path'] . DIRECTORY_SEPARATOR . 'static';
        $staticPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $staticPath);
        $staticDir = 'theme' . DIRECTORY_SEPARATOR . $template['view_path'];
        $statiFullPath = App::getPublicPath() . $staticDir;

        if (is_dir($statiFullPath)) {
            file_put_contents($statiFullPath . DIRECTORY_SEPARATOR . 'version.txt', date('Y-m-d-H:i:s'));
            if (is_file($statiFullPath . DIRECTORY_SEPARATOR . 'no-publish.txt')) {
                return ['code' => 0, 'msg' => '[静态资源]发布取消：目录中存在no-publish.txt文件，已关闭资源发布模式。' . "{$staticPath} => public" . DIRECTORY_SEPARATOR . "{$staticDir}"];
            }
            if (class_exists(\ZipArchive::class)) {
                $zipBackup = new \ZipArchive();
                if (!is_dir($statiFullPath . '__bak' . DIRECTORY_SEPARATOR)) {
                    mkdir($statiFullPath . '__bak' . DIRECTORY_SEPARATOR, 0755, true);
                }
                $zipBackup->open($statiFullPath . '__bak' . DIRECTORY_SEPARATOR . date('YmdHis') . '.zip', \ZipArchive::CREATE);
                $this->addFilesToZip($zipBackup, $statiFullPath);
                $zipBackup->close();
            }
            $this->clearDirOldFiles($statiFullPath . '__bak' . DIRECTORY_SEPARATOR);
        }
        Tool::deleteDir($statiFullPath);
        $res = Tool::copyDir(App::getRootPath() . $staticPath, $statiFullPath);
        if ($res) {
            file_put_contents(
                $statiFullPath . DIRECTORY_SEPARATOR . '不要修改此目录中文件.txt',
                '此目录是存放模板静态资源的，' . "\n"
                . '不要修改、替换文件或上传新文件到此目录及子目录，' . "\n"
                . '否则重新发布模板资源后改动文件将还原或丢失，' . "\n"
                . '原始文件存放于' . $staticPath . '目录下。' . "\n"
                . '请修改原始文件，再发布静态资源到此目录。' . "\n"
                . '如果您不想使用此模式，请在此位置新建文件：no-publish.txt，以避免修改被覆盖。' . "\n"
            );

            if (is_dir($statiFullPath . '/css/')) {

                $directory = new \DirectoryIterator($statiFullPath . '/css/');

                $v = $this->getStaticVersion($template['view_path']);
                $dir = '/theme/' . $template['view_path'] . '/';

                foreach ($directory as $fileinfo) {
                    if ($fileinfo->isFile() && $fileinfo->getExtension() === "css") {
                        $css = file_get_contents($fileinfo->getPathname());
                        $css = $this->replaceCssImgPath($css, $v, $dir);
                        file_put_contents($fileinfo->getPathname(), $css);
                    }
                }
            }
            return ['code' => 1, 'msg' => '[静态资源]发布成功：' . "{$staticPath} => public" . DIRECTORY_SEPARATOR . "{$staticDir}"];
        }

        return ['code' => 0, 'msg' => '[静态资源]发布失败：' . "{$staticPath} => public" . DIRECTORY_SEPARATOR . "{$staticDir}"];
    }

    /**
     * Undocumented function
     *
     * @param \ZipArchive $zipHandler
     * @param string $path
     * @param string $subPath
     * @return void
     */
    protected function addFilesToZip($zipHandler, $path, $subPath = '')
    {
        if (!is_dir($path)) {
            return;
        }

        $dir = opendir($path);

        $sonDir = null;

        while (false !== ($file = readdir($dir))) {

            if (($file != '.') && ($file != '..') && ($file != '.git')) {

                $sonDir = $path . DIRECTORY_SEPARATOR . $file;

                if (is_dir($sonDir)) {
                    $this->addFilesToZip($zipHandler, $sonDir, $subPath . $file . '/');
                } else {

                    $zipHandler->addFile($sonDir, $subPath . $file);  //向压缩包中添加文件
                }
            }
        }
        closedir($dir);
        unset($sonDir);
    }

    /**
     * 保留目录最新N个文件，其余全部删除
     * @param string $dir 文件夹路径
     * @param int $keepNum 保留文件数量
     * @return void
     */
    protected function clearDirOldFiles(string $dir, int $keepNum = 3): void
    {
        // 路径校验
        if (!is_dir($dir)) {
            return;
        }

        $files = [];
        $handle = opendir($dir);
        while (($filename = readdir($handle)) !== false) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }
            $filePath = $dir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($filePath)) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($ext == 'zip') {
                    $files[] = [
                        'path' => $filePath,
                        'mtime' => filemtime($filePath) // 文件修改时间戳
                    ];
                }
            } else {
                $files[] = [
                    'path' => $filePath,
                    'mtime' => filemtime($filePath) // 文件修改时间戳
                ];
            }
        }
        closedir($handle);

        // 按修改时间倒序，最新文件排在前面
        usort($files, function ($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });

        // 超过保留数量的文件全部删除
        $delFiles = array_slice($files, $keepNum);
        foreach ($delFiles as $item) {
            if (is_file($item['path'])) {
                unlink($item['path']);
            } else if (is_dir($item['path'])) {
                Tool::deleteDir($item['path']);
            }
        }
    }

    /**
     * 替换静态资源路径
     * @param array|mixed $template
     * @param string $content
     * @return string
     */
    public function replaceStaticPath($template, $content)
    {
        if (!is_dir('theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR)) {
            return $content;
        }

        $staticDir = '/theme/' . $template['view_path'] . '/';
        $v = $this->getStaticVersion($template['view_path']);

        $content = preg_replace('/(<link\s+[^>]*?href=[\'\"])(?:\.{1,2}\/)?static\/([^>]+?\.\w+)([\'\"])/is', "$1{$staticDir}$2?v={$v}$3", $content);
        $content = preg_replace('/(<script\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/([^>]+?\.js)([\'\"])/is', "$1{$staticDir}$2?v={$v}$3", $content);
        $content = preg_replace('/(<img\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/([^>]+?\.\w+)([\'\"])/is', "$1{$staticDir}$2?v={$v}$3", $content);

        return $content;
    }

    /**
     * Summary of getStaticVersion
     * @param mixed $viewPath
     * @return bool|int|string
     */
    public function getStaticVersion($viewPath)
    {
        $dir = App::getPublicPath() . 'theme' . DIRECTORY_SEPARATOR . $viewPath . DIRECTORY_SEPARATOR;
        $versionFilePath = $dir . 'version.txt';
        if (is_file($versionFilePath)) {
            $v = file_get_contents($versionFilePath);
            return $v;
        } else {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $v = date('Y-m-d-H:i:s');
            if (file_put_contents($versionFilePath, $v)) {
                return $v;
            }
        }

        return '1';
    }

    /**
     * 替换css中图片路径
     * @param string $content
     * @param string $v
     * @param string $staticDir
     * @return string
     */
    protected function replaceCssImgPath($content, $v, $staticDir)
    {
        $content = preg_replace('/(background\s*:[^>]*?url\([\'\"]?)(?:\.{1,2}\/)?static\/([^\'\"\);]+?\.\w+)([\'\"]?)/is', "$1{$staticDir}$2?v={$v}$3", $content);
        $content = preg_replace('/(background\-img\s*:[^>]*?url\([\'\"]?)(?:\.{1,2}\/)?static\/([^\'\"\);]+?\.\w+)([\'\"]?)/is', "$1{$staticDir}$2?v={$v}$3", $content);

        return $content;
    }

    /**
     * 渲染主题错误页：主题根目录存在error.html时使用（内置 error_code、error_msg、page_title 等变量，
     * 可正常使用标签与公共部件如navi/footer），否则返回null由调用方回退内置样式
     *
     * @param array|CmsTemplate $template
     * @param int $code 404|403|500
     * @param string $msg
     * @return string|null
     */
    public function errorPage($template, $code, $msg)
    {
        $file = App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR . 'error.html';

        if (!is_file($file)) {
            return null;
        }

        try {
            $vars = [
                'error_code' => $code,
                'error_msg' => $msg,
                'page_title' => $code . '_' . ($template['name'] ?? ''),
                'page_description' => $template['name'] ?? '',
                'page_keywords' => $template['name'] ?? '',
                '__site_home__' => $template['prefix'],
                '__wconf__' => $this->getWconfig(),
                '__page_type__' => 'error',
            ];

            $vars['vars'] = $vars;
            $config = [
                'cache_prefix' => 'error',
                'tpl_replace_string' => ['@static@' => '/theme/' . $template['view_path'] . '/', '@site_home@' => $template['prefix']],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                'tpl_cache' => true,
            ];
            $view = new View($file, $vars, $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            return $out;
        } catch (\Throwable $e) {
            trace('[错误页]渲染出错，' . str_replace(App::getRootPath(), '', $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage()));
            return null;
        }
    }

    /**
     * Summary of filterParams
     * 过滤get参数：
     * 1.数组值转为逗号串，值经sql_guard防御；
     * 2.剔除__前缀的框架内部变量(如__set_page_path__会进入分页链接href，被覆盖可注入javascript:协议)；
     * 3.$strict为true(栏目/内容页)时同时剔除页面身份变量(只应来自页面数据注入，不应来自url覆盖)；
     * 动态页传入false，保留业务参数的灵活性
     *
     * @param array $params
     * @param bool $strict
     * @return array
     */
    protected function filterParams($params, $strict = true)
    {
        array_walk($params, function (&$value, $key) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $value = Processer::sqlGuard($value);
        });

        $reserved = ['page_title', 'page_description', 'page_keywords'];

        if ($strict) {
            $reserved = array_merge($reserved, [
                'id', 'content', 'content_id', 'channel', 'channel_id', 'cid',
                'channel_ids', 'children_ids', 'extend_ids', 'parent_id', 'pid',
            ]);
        }

        foreach ($params as $key => $val) {
            if (strpos($key, '__') === 0 || in_array($key, $reserved)) {
                unset($params[$key]);
            }
        }

        if (ExtLoader::isWebman() && PHP_VERSION < 8.1) {
            unset($params['html_id']);
            unset($params['tpl_id']);
            unset($params['channel_id']);
            unset($params['extend_table']);
        }

        return $params;
    }
}
