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

    public static function getDbNamesapce()
    {
        return class_exists(\think\facade\Db::class) ? '\think\facade\Db' : '\think\Db';
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
        $dbNameSpace = self::getDbNamesapce();
        if ($table == 'cms_channel') {
            $item['url'] = $item['link'] ?: self::$path .  self::resolveChannelPath($item) . '.html';
        } else if ($table == 'cms_content') { {
                $channelScope = Table::defaultScope($table);
                $channel = $dbNameSpace::name('cms_channel')->where('id', $item['channel_id'])->where($channelScope)->cache('cms_channel_' . $item['channel_id'])->find();
                if ($channel) {
                    $item['url'] = $item['link'] ?: self::$path . self::resolveContentPath($item, $channel) . '.html';
                    $item['channel_url'] = $channel['link'] ?: self::$path . self::resolveChannelPath($channel) . '.html';
                } else {
                    $item['url'] = '#';
                    $item['channel_url'] = '#';
                }
            }
        } else if ($table == 'cms_banner') {
            $item['url'] = $item['link'];
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
        $dbNameSpace = self::getDbNamesapce();
        $item['__not_found__'] = false;
        if ($table == 'cms_channel') {
            $item['url'] = $item['link'] ?: self::$path . self::resolveChannelPath($item) . '.html';
        } else if ($table == 'cms_content') {
            if (!empty($item['reference_id'])) {
                $detail = $dbNameSpace::name('cms_content_detail')->where('main_id', $item['reference_id'])->find();
            } else {
                $detail = $dbNameSpace::name('cms_content_detail')->where('main_id', $item['id'])->find();
            }
            $item['content'] = $detail ? $detail['content'] : '';
            $channelScope = Table::defaultScope($table);
            $channel = $dbNameSpace::name('cms_channel')->where('id', $item['channel_id'])->where($channelScope)->cache('cms_channel_' . $item['channel_id'])->find();
            if ($channel) {
                $item['url'] = $item['link'] ?: self::$path  . self::resolveContentPath($item, $channel) . '.html';
                $item['channel_url'] = $channel['link'] ?: self::$path .  self::resolveChannelPath($channel) . '.html';
            } else {
                $empty = new EmptyData;
                return $empty;
            }
            $item['channel'] = $channel;
        } else if ($table == 'cms_banner') {
            $item['url'] = $item['link'];
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
        $dbNameSpace = self::getDbNamesapce();

        return $dbNameSpace::name($table)->where($idKey, $id)->cache($table . '_' . $id)->find();
    }
}
