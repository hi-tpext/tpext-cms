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

class Processer
{
    protected static $tableData = [];
    protected static $path = '';

    public static function setPath($val = '')
    {
        static::$path = $val;
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
        $dbNameSpace = class_exists(\think\facade\Db::class) ? '\think\facade\Db' : '\think\Db';
        if ($table == 'cms_channel') {
            $item['url'] = $item['link'] ?: self::$path . 'channel/' . str_ireplace('[id]', $item['id'], $item['channel_path']) . '.html';
        } else if ($table == 'cms_content') { {
                $channel = $dbNameSpace::name('cms_channel')->where('id', $item['channel_id'])->find();
                if ($channel) {
                    $item['url'] = $item['link'] ?: self::$path . 'content/' . str_ireplace('[id]', $item['id'], ltrim($channel['content_path'], '/')) . '.html';
                    $item['channel_url'] = $channel['link'] ?: self::$path . 'channel/' . str_ireplace('[id]', $channel['id'], ltrim($channel['channel_path'], '/')) . '.html';
                } else {
                    $item['url'] = self::$path . 'content/a' . $item['id'] . '.html';
                    $item['channel_url'] = self::$path . 'channel/c' . $item['channel_id'] . '.html';
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
        $dbNameSpace = class_exists(\think\facade\Db::class) ? '\think\facade\Db' : '\think\Db';
        $item['__not_found__'] = false;
        if ($table == 'cms_channel') {
            $item['url'] = $item['link'] ?: self::$path . 'channel/' . str_ireplace('[id]', $item['id'], $item['channel_path']) . '.html';
        } else if ($table == 'cms_content') {
            $detail = $dbNameSpace::name('cms_content_detail')->where('main_id', $item['id'])->find();
            $item['content'] = $detail ? $detail['content'] : '--';
            $channel = $dbNameSpace::name('cms_channel')->where('id', $item['channel_id'])->find();
            if ($channel) {
                $item['url'] = $item['link'] ?: self::$path . 'content/' . str_ireplace('[id]', $item['id'], ltrim($channel['content_path'], '/')) . '.html';
                $item['channel_url'] = $channel['link'] ?: self::$path . 'channel/' . str_ireplace('[id]', $channel['id'], ltrim($channel['channel_path'], '/')) . '.html';
            } else {
                $empty = new EmptyData;
                return $empty;
            }
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
        $dbNameSpace = class_exists(\think\facade\Db::class) ? '\think\facade\Db' : '\think\Db';
        return $dbNameSpace::name($table)->where($idKey, $id)->find();
    }
}
