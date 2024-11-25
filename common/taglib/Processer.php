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

namespace tpext\cms\common\taglib;

use tpext\cms\common\model\EmptyData;
use tpext\think\App;

class Processer
{
    protected static $tableData = [];
    protected static $path = '';

    public static function setPath($val = '')
    {
        static::$path = $val;
    }

    public static function getDbNamespace()
    {
        return class_exists('\think\facade\Db') ? '\think\facade\Db' : '\think\Db';
    }

    /**
     * 替换栏目路径
     * 
     * @param array|mixed $channel
     * @return string
     */
    public static function resolveChannelPath($channel)
    {
        return 'channel/' . str_replace('[id]', $channel['id'], ltrim($channel['channel_path'], '/'));
    }

    /**
     * 替换内容路径
     * 
     * @param array|mixed $content
     * @param array|mixed $channel
     * @return string
     */
    public static function resolveContentPath($content, $channel)
    {
        return  'content/' . str_replace('[id]', $content['id'], ltrim($channel['content_path'], '/'));
    }

    /**
     * 替换标签路径
     * 
     * @param array|mixed $tag
     * @return string
     */
    public static function resolveTagPath($tag)
    {
        return  'dynamic/tag?id=' . $tag['id'];
    }

    public static function getChannelOutPath()
    {
        $outPath = App::getPublicPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, self::$path);
        if (!is_dir($outPath . 'channel/')) {
            mkdir($outPath . 'channel/', 0755, true);
        }
        return $outPath;
    }

    public static function getContentOutPath()
    {
        $outPath = App::getPublicPath() . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, self::$path);
        if (!is_dir($outPath . 'content/')) {
            mkdir($outPath . 'content/', 0755, true);
        }
        return $outPath;
    }

    /**
     * 处理列表条目
     *
     * @param string $table
     * @param array $item
     * @return array|\think\model
     */
    public static function item($table, $item)
    {
        if (empty($item)) {
            $empty = new EmptyData;
            return $empty;
        }
        $dbNameSpace = self::getDbNamespace();
        if ($table == 'cms_channel') {
            $item['channel_id'] = $item['id'];
            $item['url'] = $item['link'] ?: ($item['channel_path'] == '#' ? '#' : self::$path .  self::resolveChannelPath($item) . '.html');
        } else if ($table == 'cms_content') {
            $channelScope = Table::defaultScope($table);
            $channel = $dbNameSpace::name('cms_channel')
                ->where('id', $item['channel_id'])
                ->where($channelScope)
                ->cache('cms_channel_' . $item['channel_id'], 0, 'cms_channel')
                ->find();
            if ($channel) {
                $item['url'] = $item['link'] ?: self::$path  . self::resolveContentPath($item, $channel) . '.html';
                $item['channel_url'] = $channel['link'] ?: self::$path .  self::resolveChannelPath($channel) . '.html';
            } else {
                $empty = new EmptyData;
                return $empty;
            }
            $item['channel'] = $channel;
            $item['content_id'] = $item['id'];
            $item['publish_date'] = date('Y-m-d', strtotime($item['publish_time'] ?? '2024-01-01'));
        } else if ($table == 'cms_banner') {
            $item['url'] = $item['link'];
        } else if ($table == 'cms_tag') {
            $item['url'] = self::$path . self::resolveTagPath($item);
        } else {
            $item['url'] = '#';
        }

        return $item;
    }

    /**
     * 处理数据详情
     *
     * @param string $table
     * @param array $item
     * @return array|\think\model
     */
    public static function detail($table, $item)
    {
        if (empty($item)) {
            $empty = new EmptyData;
            return $empty;
        }
        $dbNameSpace = self::getDbNamespace();
        $item['__not_found__'] = false;
        if ($table == 'cms_channel') {
            $item['channel_id'] = $item['id'];
            $item['url'] = $item['link'] ?: self::$path . self::resolveChannelPath($item) . '.html';
        } else if ($table == 'cms_content') {
            $channelScope = Table::defaultScope($table);
            $channel = $dbNameSpace::name('cms_channel')
                ->where('id', $item['channel_id'])
                ->where($channelScope)
                ->cache('cms_channel_' . $item['channel_id'], 0, 'cms_channel')
                ->find();
            if ($channel) {
                $item['url'] = $item['link'] ?: self::$path  . self::resolveContentPath($item, $channel) . '.html';
                $item['channel_url'] = $item['link'] ?: ($item['channel_path'] == '#' ? '#' : self::$path .  self::resolveChannelPath($item) . '.html');
                $childrenIds = [];
                if ($channel['type'] == 1 || $channel['type'] == 2) { //不限|目录
                    $childrenIds = $dbNameSpace::name('cms_channel')
                        ->where('parent_id', $item['id'])
                        ->where($channelScope)
                        ->where('type', '<>', 2)
                        ->cache('cms_channel_children_ids_' . $item['channel_id'], 0, 'cms_channel')
                        ->colunm('id');
                    if ($channel['type'] == 1) {
                        $childrenIds = array_merge([$channel['id']], $childrenIds);
                    }
                }
                $item['children_ids'] = $childrenIds;
            } else {
                $empty = new EmptyData;
                return $empty;
            }
            $item['channel'] = $channel;
            $item['content_id'] = $item['id'];
            $item['publish_date'] = date('Y-m-d', strtotime($item['publish_time'] ?? '2024-01-01'));
            if (!empty($item['reference_id'])) {
                $detail = $dbNameSpace::name('cms_content_detail')
                    ->where('main_id', $item['reference_id'])
                    ->cache('cms_content_detail_' . $item['id'], 3600, $table)
                    ->find();
            } else {
                $detail = $dbNameSpace::name('cms_content_detail')
                    ->where('main_id', $item['id'])
                    ->cache('cms_content_detail_' . $item['id'], 3600, $table)
                    ->find();
            }
            $item['content'] = $detail ? $detail['content'] : '';
        } else if ($table == 'cms_banner') {
            $item['url'] = $item['link'];
        } else if ($table == 'cms_tag') {
            $item['url'] = self::$path . self::resolveTagPath($item);
        } else {
            $item['url'] = '#';
        }

        return $item;
    }

    public static function getParents($table, $id, $idKey = 'id', $pidKey = 'parent_id')
    {
        $list = [];
        $data = static::getData($table, $idKey, $id);
        if ($data) {
            $list = static::getUpperNodes($table, $idKey, $pidKey, $data, $list);
        }
        foreach ($list as &$li) {
            $li = static::item($table, $li);
        }
        return array_reverse($list);
    }

    public static function getUpperNodes($table, $idKey, $pidKey, $node, $list = [], $limit = 9)
    {
        foreach ($list as $li) {
            if ($li[$idKey] == $node[$idKey]) {
                trace("死循环:" . json_encode($node));
                return $list;
            }
        }
        if (count($list) >= $limit) {
            return $list;
        }
        $list[] = $node;
        if ($node[$pidKey] == 0 || $node[$pidKey] == $node[$pidKey]) {
            return $list;
        }
        $up = static::getData($table, $idKey, $node[$pidKey]);
        if ($up) {
            $list = static::getUpperNodes($table, $idKey, $pidKey, $up, $list, $limit);
        }
        return $list;
    }

    /**
     * Undocumented function
     *
     * @param string $table
     * @param int $id
     * @return array
     */
    public static function getData($table, $idKey, $id)
    {
        $dbNameSpace = self::getDbNamespace();

        return $dbNameSpace::name($table)->where($idKey, $id)->cache($table . '_' . $id, 3600, $table)->find();
    }
}
