<?php

namespace tpext\cms\common;

use tpext\think\App;
use tpext\common\Tool;
use tpext\cms\common\View;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsContent;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\model\CmsTemplateHtml;

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
     * @return array
     */
    public function make($templateId, $channelIds = [], $types = ['channel', 'content', 'index', 'static'], $fromChannelId = 0, $fromChannelPage = 1, $fromContentId = 0, $contentDone = 0)
    {
        $template = CmsTemplate::where('id', $templateId)->find();

        if (!$template) {
            return ['code' => 0, 'msg' => '未能找到模板信息:' . $templateId, 'over' => 1];
        }
        if (empty($types)) {
            return ['code' => 0, 'msg' => '生成类型[栏目/内容/首页/静态资源]至少指定其中一种', 'over' => 1];
        }

        if (in_array('channel', $types) || in_array('content', $types)) {
            $channelSize = 3;
            $contentSize = 8;
            $channelList = [];
            $channelCount = 0;
            $contentCount = 0;
            $contentTotal = 0;
            if (empty($channelIds)) {
                $channelList = CmsChannel::withTrashed()->where('id', '>', $fromChannelId)->limit($channelSize)->order('id asc')->select();
                $channelCount = CmsChannel::count();
                if (in_array('content', $types)) {
                    $contentTotal =  CmsContent::count();
                }
            } else {
                $channelList = CmsChannel::withTrashed()->where('id', 'in', $channelIds)->where('id', '>', $fromChannelId)->limit($channelSize)->order('id asc')->select();
                $channelCount = CmsChannel::where('id', 'in', $channelIds)->count();
                if (in_array('content', $types)) {
                    $contentTotal =  CmsContent::where('channel_id', 'in', $channelIds)->count();
                }
            }

            $msgArr = [];
            $isChannelOver = count($channelList) < $channelSize;
            $isContentOver = false;
            $isChannelPageOver = false;

            foreach ($channelList as $channel) {
                if (empty($channelIds)) {
                    $msgArr[] = '[栏目进度]' . $channel['name'] . '(' . CmsChannel::where('id', '<=', $channel['id'])->count() . '/' . $channelCount . ')';
                } else {
                    $msgArr[] = '[栏目进度]' . $channel['name'] . '(' .  CmsChannel::where('id', 'in', $channelIds)->where('id', '<=', $channel['id'])->count() . '/' . $channelCount . ')';
                }
                $contentCount = CmsContent::where('channel_id', $channel['id'])->count();
                //生成内容
                if (in_array('content', $types)) {
                    $contentList = CmsContent::withTrashed()->where('channel_id', $channel['id'])->where('id', '>', $fromContentId)->limit($contentSize)->order('id asc')->select();

                    foreach ($contentList as $content) {
                        $resB = $this->makeContent($template, $content);
                        if ($content['is_show'] == 0 || $content['delete_time']) {
                            continue;
                        }
                        $contentDone += 1;
                        $msgArr[] = '[内容详情进度](当前栏目：' . CmsContent::where('channel_id', $channel['id'])->where('id', '<=', $content['id'])->count() . '/' . $contentCount . ') | (全部：' . $contentDone . '/' . $contentTotal . ')';

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
                    for ($c = 0; $c < 6; $c += 1) {
                        $resA = $this->makeChannel($template, $channel, $fromChannelPage, $contentCount);
                        $isChannelPageOver = $resA['is_over'];
                        if ($isChannelPageOver) {
                            $fromChannelPage = 1;
                        } else {
                            $fromChannelPage += 1;
                        }
                        if ($channel['is_show'] == 0 || $channel['delete_time']) {
                            continue;
                        }
                        $msgArr[] = $resA['msg'];
                        if ($isChannelPageOver) {
                            break;
                        }
                    }
                } else {
                    $isChannelPageOver = true;
                }

                if (!$isChannelPageOver) {
                    $isChannelOver = false;
                    break;
                }

                $fromChannelId = $channel['id'];
                if ($isContentOver) {
                    $fromContentId = 0;
                }
            }
            if (count($channelList) < $channelSize) {
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
            $msgArr[] = '[完成]已全部处理';
        }

        return ['code' => 1, 'msg' =>  '成功', 'msg_arr' => $msgArr, 'is_over' => $isChannelOver && $isContentOver, 'from_channel_id' => $fromChannelId, 'from_channel_page' => $fromChannelPage, 'from_content_id' => $fromContentId];
    }

    /**
     * 生成栏目页
     *
     * @param array|CmsTemplate $template
     * @param array|CmsChannel $channel
     * @return array
     */
    public function makeChannel($template, $channel, $currentPage = 1, $contentTotal = 1)
    {
        $pageInfo = CmsContentPage::where(['html_type' => 'channel', 'template_id' => $template['id'], 'to_id' => $channel['id']])
            ->find(); //获取绑定的模板

        $tplHtml = null;

        if ($channel['pagesize'] < 5) {
            $channel['pagesize'] = 5;
        }

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
            $outPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix'] . 'channel/');
            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }
            $file = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            $out = '';
            if ($channel['is_show'] == 0 || $channel['delete_time']) {
                $out = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>404</title></head><body><h3>页面不存在</h3></body></html>';
            } else {
                Processer::setPath($template['prefix']);
                $vars = [
                    'id' => $channel['id'],
                    '__SITE_HOME__' => $template['prefix'],
                    'page' => $currentPage,
                    'pagesize' => $channel['pagesize'],
                    'path' => "/channel/c{$channel['id']}p[PAGE].html"
                ];
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                ];
                $view = new View(App::getRootPath() . $file, $vars,  $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);
            }
            file_put_contents($outPath . "c{$channel['id']}p{$currentPage}.html", $out);
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目生成成功，页码：' . $currentPage . '，模板文件：' . $file, 'is_over' => $currentPage * $channel['pagesize'] >= $contentTotal];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目生成出错，页码：' . $currentPage . '，<b>' . $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage() . '</b>，模板文件：' . $file, 'is_over' => $currentPage * $channel['pagesize'] >= $contentTotal];
        }
    }

    /**
     * 生成详情页
     *
     * @param array|CmsTemplate $template
     * @param array|CmsContent $content
     * @return array
     */
    public function makeContent($template, $content)
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
            $outPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix'] . 'content/');
            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }
            $file =  str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            $out = '';

            if ($content['is_show'] == 0 || $content['delete_time']) {
                $out = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"/><title>404</title></head><body><h3>页面不存在</h3></body></html>';
            } else {
                Processer::setPath($template['prefix']);
                $vars = [
                    'id' => $content['id'],
                    '__SITE_HOME__' => $template['prefix'],
                ];
                $config = [
                    'cache_prefix' => $tplHtml['path'],
                    'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                    'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
                ];
                $view = new View(App::getRootPath() . $file, $vars,  $config);
                $out = $view->getContent();
                $out = $this->replaceStaticPath($template, $out);
            }
            file_put_contents($outPath . "a{$content['id']}.html", $out);
            if ($pageInfo && $pageInfo['html_type'] == 'single') {
                $singleOutPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
                $singleName = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?.html)$/i', '$1', $tplHtml['path']);

                file_put_contents($singleOutPath . $singleName, $this->replaceStaticPath($template, $out));
            }

            return ['code' => 1, 'msg' => '[' . $content['title'] . ']内容生成成功，模板文件：' . $file];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容生成出错，<b>' . $e->getFile() . '#' . $e->getLine() . '|' . $e->getMessage() . '</b>，模板文件：' . $file];
        }
    }

    /**
     * Undocumented function
     *
     * @param array|CmsTemplate $template
     * @return array
     */
    public function makeIndex($template)
    {
        $tplHtml = CmsTemplateHtml::where('is_default', 1)
            ->where(['type' => 'index', 'template_id' => $template['id']])
            ->find();

        if (!$tplHtml) {
            return ['code' => 0, 'msg' => '[' . $template['name'] . ']无首页模板'];
        }

        try {
            $outPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }
            $file =  str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $tplHtml['path']);
            Processer::setPath($template['prefix']);
            $vars = [
                '__SITE_HOME__' => $template['prefix'],
            ];
            $config = [
                'tpl_replace_string' => ['@static' => $template['prefix'] . 'static'],
                'cache_prefix' => $tplHtml['path'],
                'view_path' => App::getRootPath() . 'theme' . DIRECTORY_SEPARATOR . $template['view_path'] . DIRECTORY_SEPARATOR,
            ];
            $view = new View(App::getRootPath() . $file, $vars,  $config);
            $out = $view->getContent();
            $out = $this->replaceStaticPath($template, $out);
            file_put_contents($outPath . "index.html", $out);
            return ['code' => 1, 'msg' => '[首页]生成成功，模板文件：' . $file];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[首页]生成出错，' . $e->getMessage()];
        }
    }

    /**
     * 替换静态资源路径
     *
     * @param array|CmsTemplate $template
     * @param string $content
     * @return string
     */
    protected function replaceStaticPath($template, $content)
    {
        $staticDir = '/theme/' . $template['view_path']  . '/';
        $content = preg_replace('/(<link\s+[^>]*?href=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.css[\'\"])/is', "$1{$staticDir}$2", $content);
        $content = preg_replace('/(<script\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.js[\'\"])/is', "$1{$staticDir}$2", $content);
        $content = preg_replace('/(<img\s+[^>]*?src=[\'\"])(?:\.{1,2}\/)?static\/(.+?\.\w+[\'\"])/is', "$1{$staticDir}$2", $content);

        return $content;
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
}
