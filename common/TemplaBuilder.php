<?php

namespace tpext\cms\common;

use tpext\think\App;
use tpext\common\Tool;
use tpext\cms\common\View;
use tpext\cms\common\taglib\Cms;
use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\CmsContent;
use tpext\cms\common\model\CmsTemplate;
use tpext\cms\common\model\CmsContentPage;
use tpext\cms\common\model\CmsTemplateHtml;

class TemplaBuilder
{
    /**
     * 根据模板id生成所有内容
     *
     * @param int $template_id
     * @param array $channel_ids
     * @param array $type
     * @return array
     */
    public function make($template_id, $channel_ids = [], $type = ['channel', 'content'], $from_channel_id = 0, $from_content_id = 0)
    {
        $template = CmsTemplate::where('id', $template_id)->find();

        if (!$template) {
            return ['code' => 0, 'msg' => '未能找到模板信息:' . $template_id, 'over' => 1];
        }

        if (empty($type)) {
            return ['code' => 0, 'msg' => '生成类型[栏目/内容]至少指定其中一种', 'over' => 1];
        }

        $channelList = [];

        $channelModel = new CmsChannel;
        $contentModel = new CmsContent;

        $channel_all = 0;

        if (empty($channel_ids)) {
            $channelList = $channelModel->where('id', '>', $from_channel_id)->order('id asc')->select();
            $channel_all = $channelModel->count();
        } else {
            $channelList = $channelModel->where('id', 'in', $channel_ids)->where('id', '>', $from_channel_id)->order('id asc')->select();
            $channel_all = $channelModel->where('id', 'in', $channel_ids)->count();
        }

        $channel_count = count($channelList);

        $success = 0;

        $msg_arr = [];
        $is_over = $channel_count == 0;

        $channel_i = 0;
        $content_i = 0;

        foreach ($channelList as $channel) {

            $channel_i += 1;

            if (empty($channel_ids)) {
                $msg_arr[] = '[栏目进度:' .  $channelModel->where('id', '<=', $channel['id'])->count() . '/' . $channel_all . ']';
            } else {
                $msg_arr[] = '[栏目进度:' .  $channelModel->where('id', 'in', $channel_ids)->where('id', '<=', $channel['id'])->count() . '/' . $channel_all . ']';
            }

            $is_over = $channel_i == $channel_count;

            $from_channel_id = $channel['id'];
            //生成栏目
            if (in_array('channel', $type)) {
                $res_a = $this->makeChannel($template, $channel);

                $msg_arr[] = $res_a['msg'];
                if ($res_a['code']) {
                    $success += 1;
                }
            }

            //生成内容
            if (in_array('content', $type)) {

                $contentList = $contentModel->where('channel_id', $channel['id'])->where('id', '>', $from_content_id)->order('id asc')->select();

                $content_all = $contentModel->where('channel_id', $channel['id'])->count();

                $content_count = count($contentList);

                $from_content_id = 0;

                $content_i = 0;

                foreach ($contentList as $content) {

                    $content_i += 1;

                    $msg_arr[] = '&nbsp;&nbsp;&nbsp;&nbsp;[内容进度:' . $content_i . '/' . $content_all . ']';

                    $is_over = $is_over && $content_i == $content_count;

                    $from_content_id = $content['id'];

                    $res_b = $this->makeContent($template, $content);

                    $msg_arr[] = $res_b['msg'];

                    if ($res_b['code']) {
                        $success += 1;
                    }

                    if ($content_i >= 10) {
                        break;
                    }
                }
            }

            if ($channel_i >= 5) {
                break;
            }
        }

        if ($is_over) {
            $res_c = $this->makeIndex($template);
            $msg_arr[] = $res_c['msg'];
            $res_d = $this->copyStatic($template);
            $msg_arr[] = $res_d['msg'];

            $msg_arr[] = '全部处理完成';
        }

        return ['code' => 1, 'msg' =>  '成功', 'msg_arr' => $msg_arr, 'is_over' => $is_over, 'from_channel_id' => $from_channel_id, 'from_content_id' => $from_content_id];
    }

    /**
     * 生成栏目页
     *
     * @param array|CmsTemplate $template
     * @param array|CmsChannel $channel
     * @return array
     */
    public function makeChannel($template, $channel)
    {
        $page = CmsContentPage::where(['html_type' => 'channel', 'template_id' => $template['id'], 'to_id' => $channel['id']])
            ->find(); //获取绑定的模板

        $html = null;

        if ($page) {
            $html = CmsTemplateHtml::where('id', $page['html_id'])->find();
        } else {
            //无绑定，使用默认模板
            $html = CmsTemplateHtml::where('is_default', 1)
                ->where(['type' => 'channel', 'template_id' => $template['id']])
                ->find();
        }

        if (!$html) {
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目无绑定模板'];
        }

        try {

            $outPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix'] . 'channel/');

            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }

            $out = '';
            if ($channel['is_show'] == 1) {
                $file = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $html['path']);
                Cms::setPath($template['prefix']);

                $view = new View($file, ['cid' => $channel['id']]);
                $out = $view->getContent();
            } else {
                $out = '页面不存在';
            }

            file_put_contents($outPath . "c{$channel['id']}.html", $out);
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目生成成功，模板文件：' . $file];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $channel['name'] . ']栏目生成出错，' . $e->getMessage()];
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
        $page = CmsContentPage::where(['html_type' => 'single', 'template_id' => $template['id'], 'to_id' => $content['id']])
            ->find(); //获取绑定的单页模板

        if (!$page) {
            $page = CmsContentPage::where(['html_type' => 'content', 'template_id' => $template['id'], 'to_id' => $content['id']])
                ->find(); //获取绑定的模板
        }

        $html = null;

        if ($page) {
            $html = CmsTemplateHtml::where('id', $page['html_id'])->find();
        } else {
            //无绑定，使用默认模板
            $html = CmsTemplateHtml::where('is_default', 1)
                ->where(['type' => 'content', 'template_id' => $template['id']])
                ->find();
        }

        if (!$html) {
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容无绑定模板'];
        }

        try {

            $outPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix'] . 'content/');

            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }

            $out = '';
            if ($content['is_show'] == 1) {
                $file = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $html['path']);
                Cms::setPath($template['prefix']);

                $view = new View($file, ['id' => $content['id']]);
                $out = $view->getContent();
            } else {
                $out = '页面不存在';
            }

            file_put_contents($outPath . "a{$content['id']}.html", $out);

            if ($page && $page['html_type'] == 'single') {
                $singleOutPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);
                $singleName = preg_replace('/theme\/[\w\-]+?\/([\w\-]+?.html)$/i', '$1', $html['path']);

                file_put_contents($singleOutPath . $singleName, $out);
            }

            return ['code' => 1, 'msg' => '[' . $content['title'] . ']内容生成成功，模板文件：' . $file];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[' . $content['title'] . ']内容生成出错，' . $e->getMessage()];
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
        $html = CmsTemplateHtml::where('is_default', 1)
            ->where(['type' => 'index', 'template_id' => $template['id']])
            ->find();

        if (!$html) {
            return ['code' => 0, 'msg' => '[' . $template['name'] . ']无首页模板'];
        }

        try {

            $outPath = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix']);

            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }
            $file = App::getRootPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $html['path']);
            Cms::setPath($template['prefix']);

            $view = new View($file);
            $out = $view->getContent();

            file_put_contents($outPath . "index.html", $out);

            return ['code' => 1, 'msg' => '[首页]生成成功，模板文件：' . $file];
        } catch (\Throwable $e) {
            trace($e->__toString());
            return ['code' => 0, 'msg' => '[首页]生成出错，' . $e->getMessage()];
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

        $staticDir = '.' . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $template['prefix'] . 'static');

        $res = Tool::copyDir($staticPath, $staticDir);

        if ($res) {
            return ['code' => 1, 'msg' => '静态资源发布成功：' . "{$staticPath} => {$staticDir}"];
        }

        return ['code' => 0, 'msg' =>  '静态资源发布失败：' . "{$staticPath} => {$staticDir}"];
    }
}
