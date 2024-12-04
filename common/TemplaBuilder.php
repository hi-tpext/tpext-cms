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
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsContent;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\model\CmsTemplateHtml;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\model\CmsTemplate;

class TemplaBuilder
{
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
    public function make($templateId, $channelIds = [], $types = ['channel', 'content', 'index', 'static', 'route'], $fromChannelId = 0, $fromContentId = 0, $contentDone = 0, $startTime = 0)
    {
        $template = CmsTemplate::where('id', $templateId)->cache('cms_template_' . $templateId)->find();

        if (!$template) {
            return ['code' => 0, 'msg' => '未能找到模板信息:' . $templateId, 'over' => 1];
        }
        if (empty($types)) {
            return ['code' => 0, 'msg' => '生成类型[栏目/内容/首页/静态资源]至少指定其中一种', 'over' => 1];
        }

        Processer::setPath($template['prefix']);

        if (in_array('channel', $types) || in_array('content', $types)) {
            $channelSize = 3;
            $contentSize = 50;
            $channelList = [];
            $channelCount = 0;
            $contentCount = 0;
            $contentTotal = 0;
            if (empty($channelIds)) {
                $channelList = CmsChannel::withTrashed()->where('id', '>', $fromChannelId)->limit($channelSize)->order('id asc')->select();
                $channelCount = CmsChannel::withTrashed()->count();
                if (in_array('content', $types)) {
                    $contentTotal = CmsContent::withTrashed()->count();
                }
            } else {
                $channelList = CmsChannel::withTrashed()->where('id', 'in', $channelIds)->where('id', '>', $fromChannelId)->limit($channelSize)->order('id asc')->select();
                $channelCount = CmsChannel::withTrashed()->where('id', 'in', $channelIds)->count();
                if (in_array('content', $types)) {
                    $contentTotal = CmsContent::withTrashed()->where('channel_id', 'in', $channelIds)->count();
                }
            }

            $msgArr = [];
            $isChannelOver = count($channelList) < $channelSize;
            $isContentOver = false;

            foreach ($channelList as $channel) {
                if (empty($channelIds)) {
                    $msgArr[] = '[栏目进度]' . $channel['name'] . '(' . CmsChannel::withTrashed()->where('id', '<=', $channel['id'])->count() . '/' . $channelCount . ')，已用时：' . $this->formatTime(time() - $startTime);
                } else {
                    $msgArr[] = '[栏目进度]' . $channel['name'] . '(' . CmsChannel::withTrashed()->where('id', 'in', $channelIds)->where('id', '<=', $channel['id'])->count() . '/' . $channelCount . ')，已用时：' . $this->formatTime(time() - $startTime);
                }
                $contentCount = CmsContent::withTrashed()->where('channel_id', $channel['id'])->count();
                //生成内容
                if (in_array('content', $types)) {
                    $contentList = CmsContent::withTrashed()->where('channel_id', $channel['id'])->where('id', '>', $fromContentId)->limit($contentSize)->order('id asc')->select();

                    foreach ($contentList as $content) {
                        $resB = $this->makeContent($template, $channel, $content);
                        if ($content['is_show'] == 0 || $content['delete_time']) {
                            continue;
                        }
                        $contentDone += 1;
                        $msgArr[] = '[内容详情进度](当前栏目：' . CmsContent::withTrashed()->where('channel_id', $channel['id'])->where('id', '<=', $content['id'])->count() . '/' . $contentCount . ') | (全部：' . $contentDone . '/' . $contentTotal . ')';

                        $fromContentId = $content['id'];
                        $msgArr[] = $resB['msg'];
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
                    $msgArr[] = $resA['msg'];
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
                $msgArr[] = $resC['msg'];
            } else {
                $msgArr[] = '[首页]未选择生成';
            }
            if (in_array('static', $types)) {
                $resD = $this->copyStatic($template);
                $msgArr[] = $resD['msg'];
            } else {
                $msgArr[] = '[静态资源]未选择发布';
            }
            if (in_array('route', $types)) {
                $routeBuilder = new RouteBuilder;
                $routeBuilder->builder(true);
                $msgArr[] = '[路由]生成成功';
            } else {
                $msgArr[] = '[路由]未选择生成';
            }

            if (in_array('channel', $types) && empty($channelIds)) {
                $htmlPath = Processer::getOutPath() . 'channel/';
                $count = $this->delfiles($htmlPath, $startTime - 60, 100);
                $msgArr[] = '清理过期html文件' . $template['prefix'] . 'channel/*.html (' . $count . ')个';
            }
            if (in_array('content', $types) && empty($channelIds)) {
                $htmlPath = Processer::getOutPath() . 'content/';
                $count = $this->delfiles($htmlPath, $startTime - 60 * 4, 100);
                $msgArr[] = '清理过期html文件' . $template['prefix'] . 'content/*.html (' . $count . ')个';
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
        file_put_contents($outPath . Processer::resolveChannelPath($channel) . '.html', $output);
        file_put_contents($outPath . Processer::resolveChannelPath($channel) . '-1.html', $output);
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
        $output = $page->content($content['id'], $template['id']);

        $outPath = Processer::getOutPath();
        $contentPath = Processer::resolveContentPath($content, $channel) . '.html';
        file_put_contents($outPath . $contentPath, $output);

        $singlePage = CmsContentPage::where(['html_type' => 'single', 'template_id' => $template['id'], 'to_id' => $content['id']])
            ->find(); //获取绑定的单页模板

        if ($singlePage) {
            $tplHtml = CmsTemplateHtml::where('id', $singlePage['html_id'])->find();
            if ($tplHtml) {
                $singleOutPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
                $singleName = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?.html)$/i', '$1', $tplHtml['path']);
                file_put_contents($singleOutPath . $singleName, $output);
            }
        }

        return ['code' => 1, 'msg' => '[' . $content['title'] . ']内容生成成功，路径：' . $contentPath];
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

        file_put_contents($outPath . "index.html", $output);
        return ['code' => 1, 'msg' => '[首页]生成成功'];
    }

    /**
     * 处理静态资源路径
     *
     * @param array|CmsTemplate $template
     * @return array
     */
    protected function copyStatic($template)
    {
        $render = new Render();
        $res = $render->copyStatic($template);

        return $res;
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
