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

use tpext\cms\common\model\CmsChannel;
use tpext\cms\common\model\EmptyData;
use tpext\think\App;
use Webman\Context;

class Processer
{
    protected static $path = '';
    protected static $isAdmin = false;

    protected static $isWebmanContext = null;

    protected static function isWebmanContext()
    {
        if (is_null(static::$isWebmanContext)) {
            static::$isWebmanContext = class_exists(Context::class);
        }

        return static::$isWebmanContext;
    }

    public static function setPath($val = '')
    {
        if (static::isWebmanContext()) {
            Context::set(static::class . '::path', $val);
        } else {
            static::$path = $val;
        }
    }

    public static function getPath()
    {
        if (static::isWebmanContext()) {
            return Context::get(static::class . '::path');
        }
        return static::$path;
    }

    public static function setIsAdmin($val = true)
    {
        if (static::isWebmanContext()) {
            Context::set(static::class . '::isAdmin', $val);
        } else {
            static::$isAdmin = $val;
        }
    }

    public static function isAdmin()
    {
        if (static::isWebmanContext()) {
            return Context::get(static::class . '::isAdmin');
        }
        return static::$isAdmin;
    }

    /**
     * Undocumented function
     *
     * @return \think\facade\Db|\think\Db|string
     */
    public static function getDbNamespace()
    {
        return class_exists('\think\facade\Db') ? '\think\facade\Db' : '\think\Db';
    }

    /**
     * sql 注入防御
     *
     * @param string $val
     * @return string
     */
    public static function sqlGuard($val)
    {
        if (!is_string($val) || $val === '') {
            return $val;
        }

        $val = strip_tags($val);

        //先去注释类混淆，如 sel/**/ect
        $plain = preg_replace('/\/\*.*?\*\//s', '', $val);

        //1. 经典恒真式（最常见、门槛最低）：or/and 后跟数字或引号字符串且带等号，如 or 1=1、or '1'='1'、1"or"1"="1
        if (preg_match('/\b(?:or|and)\s*(?:\d+\s*=|[\'"]\s*\w+\s*[\'"]\s*=)/is', $plain)) {
            return 'invalid words';
        }

        //2. 注释符与堆叠语句（截断原语句、追加新语句，绝大多数载荷会用到）
        if (preg_match('/--|#|\/\*|\*\/|;/is', $plain)) {
            return 'invalid words';
        }

        //3. union 联合查询注入（窃取数据最常用手法）
        if (preg_match('/\bunion\b.+?\bselect\b/is', $plain)) {
            return 'invalid words';
        }

        //4. select 子查询注入
        if (preg_match('/\bselect\b.+?\bfrom\b/is', $plain)) {
            return 'invalid words';
        }

        //5. 时间盲注函数（sqlmap、脚本常用）
        if (preg_match('/\b(?:sleep|benchmark)\s*\(/is', $plain)) {
            return 'invalid words';
        }

        //6. 写操作与 DDL（危害大，一般需配合堆叠语句，兜底）：delete...from、update...set、insert/replace...into、drop/truncate/alter/create...table
        if (preg_match('/\bdelete\b.+?\bfrom\b|\bupdate\b.+?\bset\b|\binsert\b.+?\binto\b|\breplace\b.+?\binto\b|\bdrop\b.+?\btable\b|\btruncate\b.+?\btable\b|\balter\b.+?\btable\b|\bcreate\b.+?\btable\b/is', $plain)) {
            return 'invalid words';
        }

        //7. 文件操作与信息泄露（高危但少见）
        if (preg_match('/\binto\s+(?:outfile|dumpfile)\b|\bload_file\s*\(|\binformation_schema\b/is', $plain)) {
            return 'invalid words';
        }

        //8. 编码混淆（用于绕过关键词检测）
        if (preg_match('/0x[0-9a-f]+|char\s*\(/is', $plain)) {
            return 'invalid words';
        }

        //9. MSSQL 专有（本项目环境少见，兜底）
        if (preg_match('/\bwaitfor\s+delay\b|\bxp_cmdshell\b/is', $plain)) {
            return 'invalid words';
        }

        return $val;
    }

    /**
     * 替换栏目路径
     * 
     * @param array|mixed $channel
     * @return string
     */
    public static function resolveChannelPath($channel)
    {
        return 'c/' . str_replace('[id]', $channel['id'], ltrim($channel['channel_path'], '/'));
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
        return 'd/' . str_replace('[id]', $content['id'], ltrim($channel['content_path'], '/'));
    }


    /**
     * 处理站内地址
     * @param string $path
     * @return string
     */
    public static function resolveWebPath($path)
    {
        if (empty($path)) {
            return '';
        }
        if (preg_match('/^http(s)?:\/\//', $path)) {
            return $path;
        }

        return static::getPath() . ltrim($path, '/');
    }

    /**
     * 替换标签路径
     * 
     * @param array|mixed $tag
     * @return string
     */
    public static function resolveTagPath($tag)
    {
        return 'e/tag-' . $tag['id'];
    }

    public static function getOutPath()
    {
        $outPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, App::getPublicPath() . ltrim(static::getPath(), '/'));
        if (!is_dir($outPath . 'c/')) {
            mkdir($outPath . 'c/', 0755, true);
        }
        if (!is_dir($outPath . 'd/')) {
            mkdir($outPath . 'd/', 0755, true);
        }

        return $outPath;
    }

    /**
     * 处理列表关联
     * @param string $table
     * @param array|\think\Collection $data
     * @return array
     */
    public static function list($table, $data)
    {
        $channelIds = [];
        foreach ($data as $k => $item) {
            $data[$k] = static::item($table, $item);
            if (isset($item['channel_id'])) {
                $channelIds[$item['channel_id']] = $item['channel_id'];
            }
        }

        if (count($channelIds)) {
            $channels = [];
            $channelScope = Table::defaultScope('cms_channel');
            $chnList = CmsChannel::where('id', 'in', array_values($channelIds))
                ->where($channelScope)
                ->select();
            foreach ($chnList as $chn) {
                $channels[$chn['id']] = $chn;
            }

            foreach ($data as &$item) {
                $channel = null;
                if (isset($channels[$item['channel_id']])) {
                    $channel = static::item('cms_channel', $channels[$item['channel_id']]);
                    $item['url'] = static::resolveWebPath($item['link'] ?? '') ?: static::getPath() . static::resolveContentPath($item, $channel) . '.html';
                    $item['channel_url'] = $channel['url'];
                } else {
                    $channel = new EmptyData;
                    $item['url'] = static::resolveWebPath($item['link'] ?? '') ?: static::getPath() . static::resolveContentPath($item, ['content_path' => 'a[id]']) . '.html';
                    $item['channel_url'] = '#';
                }
                $item['channel'] = $channel;
            }
        }

        return $data;
    }

    /**
     * 处理列表条目
     *
     * @param string $table
     * @param array $item
     * @return array|EmptyData
     */
    public static function item($table, $item)
    {
        if (empty($item)) {
            $empty = new EmptyData;
            return $empty;
        }

        if ($table == 'cms_channel') {
            $item['channel_id'] = $item['id'];
            $item['url'] = static::resolveWebPath($item['link'] ?? '') ?: ($item['channel_path'] == '#' ? '#' : static::getPath() . static::resolveChannelPath($item) . '.html');
        } else if ($table == 'cms_content') {
            $item['content_id'] = $item['id'];
            $item = static::resolveContentDate($item);
        } else if ($table == 'cms_banner') {
            $item['url'] = static::resolveWebPath($item['link']);
        } else if ($table == 'cms_tag') {
            $item['url'] = static::getPath() . static::resolveTagPath($item);
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
     * @return array|EmptyData
     */
    public static function detail($table, $item)
    {
        if (empty($item)) {
            $empty = new EmptyData('content');
            return $empty;
        }

        $dbNameSpace = static::getDbNamespace();
        $item['__not_found__'] = false;
        if ($table == 'cms_channel') {
            $item['channel_id'] = $item['id'];
            $item['url'] = static::resolveWebPath($item['link']) ?: static::getPath() . static::resolveChannelPath($item) . '.html';
            $childrenIds = [];
            if ($item['type'] == 1 || $item['type'] == 2) { //不限|目录
                $channelScope = Table::defaultScope($table);
                $childrenIds = CmsChannel::where('parent_id', $item['id'])
                    ->where($channelScope)
                    ->where('type', '<>', 2)
                    ->cache(static::isAdmin() ? false : 'cms_channel_children_ids_' . $item['channel_id'], 0, 'cms_channel')
                    ->column('id');
            }
            $item['children_ids'] = $childrenIds;
        } else if ($table == 'cms_content') {

            if (empty($item['channel_id'])) {
                $channel = new EmptyData;
                $item['url'] = static::resolveWebPath($item['link'] ?? '') ?: static::getPath() . static::resolveContentPath($item, ['content_path' => 'a[id]']) . '.html';
                $item['channel_url'] = '#';
            } else {
                $channelScope = Table::defaultScope('cms_channel');
                $channel = CmsChannel::where('id', $item['channel_id'])
                    ->where($channelScope)
                    ->cache(static::isAdmin() ? false : 'cms_channel_' . $item['channel_id'], 0, 'cms_channel')
                    ->find();
                if ($channel) {
                    $item['url'] = static::resolveWebPath($item['link'] ?? '') ?: static::getPath() . static::resolveContentPath($item, $channel) . '.html';
                    $item['channel_url'] = $channel['link'] ?: ($channel['channel_path'] == '#' ? '#' : static::getPath() . static::resolveChannelPath($channel) . '.html');
                    $channel['url'] = $item['channel_url'];
                    $channel['channel_id'] = $channel['id'];
                } else {
                    $channel = new EmptyData;
                }
            }
            $item['channel'] = $channel;
            $item['content_id'] = $item['id'];
            $item = static::resolveContentDate($item);

            $detail = null;
            if (!empty($item['reference_id'])) {
                $detail = $dbNameSpace::name('cms_content_detail')
                    ->where('main_id', $item['reference_id'])
                    ->cache(static::isAdmin() ? false : 'cms_content_detail_' . $item['reference_id'], 3600, $table)
                    ->find();
            } else if (empty($item['extend_table'])) {
                $detail = $dbNameSpace::name('cms_content_detail')
                    ->where('main_id', $item['id'])
                    ->cache(static::isAdmin() ? false : 'cms_content_detail_' . $item['id'], 3600, $table)
                    ->find();
                $item['content'] = $detail ? $detail['content'] : '';
            }
            $item['attachments'] = $detail ? ($detail['attachments'] ?? '') : '';
            $item['attachments_array'] = static::getAttachments($item);
        } else if ($table == 'cms_banner') {
            $item['url'] = static::resolveWebPath($item['link']);
        } else if ($table == 'cms_tag') {
            $item['url'] = static::getPath() . static::resolveTagPath($item);
        } else {
            $item['url'] = '#';
        }

        return $item;
    }

    /**
     * 处理内容时间字段
     * @param array $item
     * @return array
     */
    protected static function resolveContentDate($item)
    {
        $item['publish_time'] = $item['publish_time'] ?? ($item['create_time'] ?? '');
        if (!$item['publish_time']) {
            return $item;
        }
        $item['datetime'] = $item['publish_time'];
        $item['publish_date'] = date('Y-m-d', strtotime($item['publish_time']));
        $item['date'] = $item['publish_date'];
        $item['time'] = date('H:i:s', strtotime($item['publish_time']));
        $ymdArr = explode('-', $item['publish_date']);
        $item['yy'] = $ymdArr[0];
        $item['mm'] = $ymdArr[1];
        $item['dd'] = $ymdArr[2];

        return $item;
    }

    /**
     * 提前处理附件
     * @param array $item
     * @return array
     */
    protected static function getAttachments($item)
    {
        $files = [];
        if (!empty($item['attachments'])) {
            $attachments_array = array_filter(explode(',', $item['attachments']));
            $content_array = array_filter(explode("\n", strip_tags($item['content'])));
            foreach ($attachments_array as $k => $v) {
                $file = str_replace(['http://' . request()->host(), 'https://' . request()->host()], '', $v);
                $fileSize = is_file('./' . ltrim($file, '/')) ? filesize('./' . ltrim($file, '/')) : 0;
                if ($fileSize >= 1024 ** 2) {
                    $fileSize = round($fileSize / (1024 ** 2), 2) . 'MB';
                } else {
                    $fileSize = round($fileSize / 1024, 2) . 'KB';
                }
                $files[$file] = [
                    'file' => $file,
                    'desc' => trim($content_array[$k] ?? $file),
                    'size' => $fileSize,
                ];
            }
        }
        if (!empty($item['content']) && preg_match_all('/<a[^<>]*href=[\"\']([^<>\"\']+?\.(pdf|doc|docx|xls|xlsx|ppt|pptx|rar|zip|7z|tar|gz|bz2))[\"\'][^<>]*>(.+?)<\/a>/is', $item['content'], $mchs)) {
            foreach ($mchs[1] as $k => $mch) {
                $file = str_replace(['http://' . request()->host(), 'https://' . request()->host()], '', $mch);
                if (isset($files[$file])) {
                    continue;
                }
                $fileSize = is_file('./' . $mch) ? filesize('./' . $mch) : 0;
                if ($fileSize >= 1024 ** 2) {
                    $fileSize = round($fileSize / (1024 ** 2), 2) . 'MB';
                } else {
                    $fileSize = round($fileSize / 1024, 2) . 'KB';
                }
                $files[$file] = [
                    'file' => $file,
                    'desc' => trim(strip_tags($mchs[2][$k] ?? '')),
                    'size' => $fileSize,
                ];
            }
        }
        return $files;
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
     * @param string $idKey
     * @param int $id
     * @return array
     */
    public static function getData($table, $idKey, $id)
    {
        $dbNameSpace = static::getDbNamespace();

        return $dbNameSpace::name($table)
            ->where($idKey, $id)
            ->cache($table . '_' . $id, static::isAdmin() ? 120 : 3600, $table)
            ->find();
    }
}
