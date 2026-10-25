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
use tpext\cms\common\PathGuard;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsContent;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsTemplateHtml;

class TemplaBuilder
{
    /**
     * single绑定缓存 [template_id => [to_id => CmsTemplateHtml]]
     * @var array
     */
    protected $singleHtmlMap = [];

    /**
     * 根据模板id生成所有内容
     *
     * @param int $templateId
     * @param array $channelIds
     * @param array $types
     * @param int $fromChannelId
     * @param int $fromContentId
     * @param int $contentDone
     * @param int $startTime
     * @return array
     */
    public function make($templateId, $channelIds = [], $types = ['channel', 'content', 'index', 'static'], $fromChannelId = 0, $fromContentId = 0, $contentDone = 0, $startTime = 0)
    {
        $template = CmsTemplate::where('id', $templateId)->cache('cms_template_' . $templateId)->find();

        if (!$template) {
            return ['code' => 0, 'msg' => '未能找到模板信息:' . $templateId, 'over' => 1];
        }
        if (empty($types)) {
            return ['code' => 0, 'msg' => '生成类型[栏目/内容/首页/sitemap/静态资源]至少指定其中一种', 'over' => 1];
        }

        Processer::setPath($template['prefix']);

        $msgArr = [];
        if (in_array('channel', $types) || in_array('content', $types)) {
            $channelSize = 3;
            $contentSize = 50;
            $channelList = [];
            $channelCount = 0;
            $contentCount = 0;
            $contentTotal = 0;
            if (empty($channelIds)) {
                $channelList = CmsChannel::where('id', '>', $fromChannelId)->limit($channelSize)->order('id asc')->select();
                $channelCount = CmsChannel::count();
                if (in_array('content', $types)) {
                    //只数可见：contentDone只累计可见内容，总数含隐藏会导致"全部：x/y"永远到不了y
                    $contentTotal = CmsContent::where('is_show', 1)->count();
                }
            } else {
                $channelList = CmsChannel::where('id', 'in', $channelIds)->where('id', '>', $fromChannelId)->limit($channelSize)->order('id asc')->select();
                $channelCount = CmsChannel::where('id', 'in', $channelIds)->count();
                if (in_array('content', $types)) {
                    $contentTotal = CmsContent::where('is_show', 1)->where('channel_id', 'in', $channelIds)->count();
                }
            }

            $isChannelOver = count($channelList) < $channelSize;
            $isContentOver = false;

            foreach ($channelList as $channel) {
                if (empty($channelIds)) {
                    $msgArr[] = '[栏目进度]' . $channel['name'] . '(' . CmsChannel::where('id', '<=', $channel['id'])->count() . '/' . $channelCount . ')，已用时：' . $this->formatTime(time() - $startTime);
                } else {
                    $msgArr[] = '[栏目进度]' . $channel['name'] . '(' . CmsChannel::where('id', 'in', $channelIds)->where('id', '<=', $channel['id'])->count() . '/' . $channelCount . ')，已用时：' . $this->formatTime(time() - $startTime);
                }
                $contentCount = CmsContent::where('channel_id', $channel['id'])->count();
                //生成内容
                if (in_array('content', $types)) {
                    $contentList = CmsContent::where('channel_id', $channel['id'])->where('id', '>', $fromContentId)->limit($contentSize)->order('id asc')->select();

                    //进度基数每栏目每轮只查一次，循环内位置内存累加（原逐篇count(id<=当前id)是一篇一查）
                    $doneInChannel = CmsContent::where('channel_id', $channel['id'])->where('id', '<=', $fromContentId)->count();

                foreach ($contentList as $content) {
                    //隐藏(is_show=0)内容也渲染：其URL写出静态404页，直链访问得到404而非旧内容；
                    //软删除内容默认查询查不到，此判断仅为兜底。游标对每条都推进，避免分批时隐藏内容被反复重渲染
                    $fromContentId = $content['id'];
                    $resB = $this->makeContent($template, $channel, $content);
                    $doneInChannel += 1;
                    if ($content['is_show'] == 0 || $content['delete_time']) {
                        continue;
                    }
                    $contentDone += 1;
                    $msgArr[] = '[内容详情进度](当前栏目：' . $doneInChannel . '/' . $contentCount . ') | (全部：' . $contentDone . '/' . $contentTotal . ')';

                    $msgArr[] = $resB['msg'] . (isset($resB['detail']) ? '（' . $resB['detail'] . '）' : '');
                    if ($contentDone % $contentSize == 0) {
                        break;
                    }
                }
                    $isContentOver = count($contentList) < $contentSize;
                } else {
                    $isContentOver = true;
                }

                if (!$isContentOver) {
                    break;
                }
                //生成栏目
                if (in_array('channel', $types)) {
                    $resA = $this->makeChannel($template, $channel);
                    $msgArr[] = $resA['msg'] . (isset($resA['detail']) ? '（' . $resA['detail'] . '）' : '');
                }

                $fromChannelId = $channel['id'];
                if ($isContentOver) {
                    $fromContentId = 0;
                }
            }
            if (count($channelList) == 0) {
                $isChannelOver = $isContentOver = true;
            }
        } else {
            $isChannelOver = $isContentOver = true;
        }

        if ($isChannelOver && $isContentOver) {
            if (in_array('index', $types)) {
                $resC = $this->makeIndex($template);
                $msgArr[] = $resC['msg'] . (isset($resC['detail']) ? '（' . $resC['detail'] . '）' : '');
            } else {
                $msgArr[] = '[首页]未选择生成';
            }

            if (in_array('sitemap', $types)) {
                $resS = $this->makeSitemap($template);
                $msgArr[] = $resS['msg'] . (isset($resS['detail']) ? '（' . $resS['detail'] . '）' : '');
            } else {
                $msgArr[] = '[sitemap]未选择生成';
            }

            if (empty($channelIds)) {
                $routeBuilder = new RouteBuilder;
                $routeBuilder->builder(true);
                $msgArr[] = '[路由]生成成功';
            } else {
                //单栏目生成不重建路由：栏目路径增删改已有保存事件(MakeRoute)兜底，
                //此处强写会在批量逐栏目生成时反复重写整个路由文件
                $msgArr[] = '[路由]跳过（单栏目生成不重建）';
            }

            if (in_array('channel', $types) && empty($channelIds)) {
                $htmlPath = Processer::getOutPath() . 'c/';
                $count = $this->delfiles($htmlPath, $startTime - 60, 1000);
                $msgArr[] = '清理过期html文件' . $template['prefix'] . 'c/*.html (' . $count . ')个';
            }
            if (in_array('content', $types) && empty($channelIds)) {
                $htmlPath = Processer::getOutPath() . 'd/';
                $count = $this->delfiles($htmlPath, $startTime - 60 * 4, 1000);
                $msgArr[] = '清理过期html文件' . $template['prefix'] . 'd/*.html (' . $count . ')个';
            }

            $msgArr[] = '[完成]已全部处理';
            $msgArr[] = '[累计用时]' . $this->formatTime(time() - $startTime);
        }

        return ['code' => 1, 'msg' => '成功', 'msg_arr' => $msgArr, 'is_over' => $isChannelOver && $isContentOver, 'from_channel_id' => $fromChannelId, 'from_content_id' => $fromContentId, 'content_done' => $contentDone, 'start_time' => $startTime];
    }

    /**
     * 格式化时间
     * @param mixed $seconds
     * @return string
     */
    protected function formatTime($seconds)
    {
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . '分' . ($seconds % 60) . '秒';
        } else {
            return floor($seconds / 3600) . '时' . floor(($seconds % 3600) / 60) . '分' . ($seconds % 60) . '秒';
        }
    }

    /**
     * 生成栏目第一页
     *
     * @param array|CmsTemplate $template
     * @param array|CmsChannel $channel
     * @return array
     */
    public function makeChannel($template, $channel)
    {
        $page = new Page();
        $output = '';
        if (empty($channel['channel_path']) || $channel['channel_path'] == '#') {
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']channel_path为空不生成页面'];
        } else {
            $output = $page->channel($channel['id'], $template['id']);
        }

        $outPath = Processer::getOutPath();
        $this->fileWrite($outPath . Processer::resolveChannelPath($channel) . '.html', $output);
        $this->fileWrite($outPath . Processer::resolveChannelPath($channel) . '-1.html', $output);
        return ['code' => 1, 'msg' => '[' . $channel['name'] . ']栏目第一页生成成功，路径：' . Processer::resolveChannelPath($channel)];
    }

    /**
     * 生成详情页
     *
     * @param array|CmsTemplate $template
     * @param array|CmsChannel $channel
     * @param array|CmsContent $content
     * @return array
     */
    public function makeContent($template, $channel, $content)
    {
        $page = new Page();
        //第5参$is_static=1：静态生成上下文（Render层点击数只读、静态专属脚本注入）。
        //历史上此处只传3个参，1错位落在channel_id上，is_static从未生效
        $output = $page->content($content['id'], $template['id'], 0, '', 1);

        $outPath = Processer::getOutPath();
        $contentPath = Processer::resolveContentPath($content, $channel) . '.html';
        $this->fileWrite($outPath . $contentPath, $output);

        $singleHtml = $this->getSingleHtml($template['id'], $content['id']);

        if ($singleHtml) {
            $singleOutPath = App::getPublicPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
            $singleName = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?.html)$/i', '$1', $singleHtml['path']);
            $this->fileWrite($singleOutPath . $singleName, $output);
        }

        return ['code' => 1, 'msg' => '[' . $content['title'] . ']内容生成成功，路径：' . $contentPath];
    }

    /**
     * single绑定：每模板只查一次库取全量映射（发布一轮几十上百篇，逐篇find是无缓存点查）。
     * 实例生命周期=单次发布请求，无常驻进程陈旧问题
     *
     * @param int $templateId
     * @param int $toId
     * @return CmsTemplateHtml|null
     */
    protected function getSingleHtml($templateId, $toId)
    {
        if (!array_key_exists($templateId, $this->singleHtmlMap)) {
            $map = [];
            $rows = CmsTemplateHtml::where(['type' => 'single', 'template_id' => $templateId])
                ->field('id,to_id,path')
                ->order('id asc')
                ->select();
            foreach ($rows as $row) {
                if (!isset($map[$row['to_id']])) {
                    //同to_id重复绑定时取id最小的一条，与原逐篇find()的默认排序一致
                    $map[$row['to_id']] = $row;
                }
            }
            $this->singleHtmlMap[$templateId] = $map;
        }

        return $this->singleHtmlMap[$templateId][$toId] ?? null;
    }

    /**
     * Undocumented function
     *
     * @param array|CmsTemplate $template
     * @return array
     */
    public function makeIndex($template)
    {
        $page = new Page();
        $output = $page->index($template['id']);

        $outPath = App::getPublicPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
        if (!is_dir($outPath)) {
            mkdir($outPath, 0755, true);
        }

        $this->fileWrite($outPath . 'index.html', $output);
        return ['code' => 1, 'msg' => '[首页]生成成功'];
    }

    /**
     * 生成sitemap.xml
     *
     * @param array|CmsTemplate $template
     * @return array
     */
    public function makeSitemap($template)
    {
        $https = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https');
        $prefix = rtrim(str_replace(['\\', '/'], '/', $template['prefix']), '/');
        $baseUrl = ($https ? 'https://' : 'http://') . request()->host() . $prefix . '/';

        $channels = CmsChannel::where('is_show', 1)
            ->where('channel_path', '<>', '')
            ->where('channel_path', '<>', '#')
            ->column('id,channel_path,content_path,update_time', 'id');

        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $count = 1;
        $xml .= $this->sitemapUrl($baseUrl, time());
        $count += count($channels);
        foreach ($channels as $channel) {
            $xml .= $this->sitemapUrl($baseUrl . Processer::resolveChannelPath($channel) . '.html', $channel['update_time']);
        }

        if (count($channels) > 0) {
            CmsContent::where('is_show', 1)
                ->where('channel_id', 'in', array_keys($channels))
                ->field('id,channel_id,update_time')
                ->chunk(200, function ($contents) use (&$xml, &$count, $baseUrl, $channels) {
                    foreach ($contents as $content) {
                        $channel = $channels[$content['channel_id']] ?? null;
                        if (empty($channel) || empty($channel['content_path'])) {
                            continue;
                        }
                        $xml .= $this->sitemapUrl($baseUrl . Processer::resolveContentPath($content, $channel) . '.html', $content['update_time']);
                        $count += 1;
                    }
                });
        }

        $xml .= '</urlset>';

        $outPath = App::getPublicPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
        if (!is_dir($outPath)) {
            mkdir($outPath, 0755, true);
        }
        $this->fileWrite($outPath . 'sitemap.xml', $xml);

        return ['code' => 1, 'msg' => '[sitemap]生成成功，共' . $count . '个链接'];
    }

    /**
     * sitemap url节点
     * @param string $loc
     * @param int|string $lastmod 时间戳或日期字符串
     * @return string
     */
    protected function sitemapUrl($loc, $lastmod = 0)
    {
        $xml = '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_QUOTES) . '</loc>' . "\n";
        if ($lastmod) {
            $lastmod = is_numeric($lastmod) ? date('Y-m-d', (int) $lastmod) : substr($lastmod, 0, 10);
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        }
        $xml .= "  </url>\n";
        return $xml;
    }

    /**
     * 写入静态文件
     * @param string $path
     * @param string $content
     * @return bool|int
     */
    protected function fileWrite($path, $content)
    {
        //路径防呆：静态路径由栏目path等配置拼出，含..时可能越出输出目录写文件
        if (strpos($path, '..') !== false) {
            return false;
        }
        //只拦5xx瞬时错误(模板改坏/数据异常)，不让其写死在静态URL上；
        //404/403允许静态化——隐藏/删除内容的URL直链得到静态404，无需每次动态渲染(历史设计，保留)。
        //错误页模板化后title格式多变，旧嗅探'500'永不命中，改用__error_5xx__标记(旧嗅探保留兼容)
        if (strstr($content, '__error_5xx__') || strstr($content, '<title>500</title></head>')) {
            return false;
        }

        $content = str_replace('</head>', '<!--静态页面由cms程序生成，不要手动修改此html文件 ' . date('Y-m-d H:i:s') . '-->' . "\n" . '</head>', $content);

        return @file_put_contents($path, $content);
    }

    /**
     * 处理静态资源路径
     *
     * @param array|CmsTemplate $template
     * @return array
     */
    public function copyStatic($template)
    {
        $render = new Render();
        $res = $render->copyStatic($template);

        return $res;
    }

    /**
     * 清除已生成的html文件
     * @param array $template
     * @return void
     */
    public function clearHtml($template)
    {
        //空串/非法prefix（如../）会拼出public本身或越界路径，删除整站文件，一律拒绝
        if (!PathGuard::safePrefix($template['prefix'])) {
            trace('跳过清除：模板[id=' . $template['id'] . ']prefix非法：' . var_export($template['prefix'], true), 'error');
            return;
        }

        $outPath = App::getPublicPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);

        if ($template['prefix'] != '/') {
            //realpath解析须严格位于public内（防软链越界），目录不存在则跳过
            $publicReal = realpath(App::getPublicPath());
            $outReal = is_dir($outPath) ? realpath($outPath) : false;
            if ($outReal === false || $outReal === $publicReal || strpos($outReal, $publicReal . DIRECTORY_SEPARATOR) !== 0) {
                trace('跳过清除：模板[id=' . $template['id'] . ']路径越界或不存在：' . $template['prefix'], 'error');
                return;
            }
            Tool::deleteDir($outPath);
            return;
        }
        //清除首页
        if (is_file($outPath . 'index.html')) {
            @unlink($outPath . 'index.html');
        }
        //清除栏目页
        if (is_dir($outPath . 'c')) {
            Tool::deleteDir($outPath . 'c');
        }
        //清除内容页
        if (is_dir($outPath . 'd')) {
            Tool::deleteDir($outPath . 'd');
        }
        //清除单页
        $singlePages = CmsTemplateHtml::where('template_id', $template['id'])->where('type', 'single')->select();
        foreach ($singlePages as $page) {
            $file = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?.html)$/i', '$1', $page['path']);
            if (is_file($outPath . $file)) {
                @unlink($outPath . $file);
            }
        }
    }

    /**
     * 删除长时间未更新的文件
     *
     * @param string $path 路径
     * @param int|string $timeBefor 时间限制
     * @param integer $limit 删除数量
     * @return int
     */
    protected function delfiles($path, $timeBefor, $limit = 100)
    {
        if (!is_dir($path)) {
            return 0;
        }

        //要删除的文件类型
        $exts = ['html'];
        $count = 0;
        $files = scandir($path);
        //日期升序扫描文件
        usort($files, function ($a, $b) use ($path) {
            return filemtime("{$path}/{$a}") - filemtime("{$path}/{$b}");
        });

        foreach ($files as $file) {
            if (($file != '.') && ($file != '..')) {
                $fielPath = rtrim($path, "\\\/") . DIRECTORY_SEPARATOR . $file;
                if (is_dir($fielPath)) {
                    continue;
                }
                $extType = strtolower(pathinfo($fielPath, PATHINFO_EXTENSION));
                $time = filemtime($fielPath);
                if (!in_array($extType, $exts) || $time > $timeBefor) {
                    continue;
                }
                //删除文件
                @unlink($fielPath);
                $count += 1;
                if ($count >= $limit) {
                    break;
                }
            }
        }

        return $count;
    }
}
