<?php

namespace tpext\cms\common\taglib;

use think\template\TagLib;
use think\Db;
use tpext\cms\common\model\EmptyData;

/**
 * Cms标签库解析类
 */
class Cms extends Taglib
{
    protected static $path = '';

    public static function setPath($val = '')
    {
        static::$path = $val;
    }

    protected static $tableData = [];

    // 标签定义
    protected $tags = [
        // 标签定义： attr 属性列表 close 是否闭合（0 或者1 默认1） alias 标签别名 level 嵌套层次
        'list' => ['attr' => 'table,ck,num,order,where,fields,item'],
        'detail' => ['attr' => 'table,pk,where,fields,item', 'expression' => true],
        //
        'articles' => ['attr' => 'table,ck,num,order,where,fields,item', 'alias' => 'articlelist'],
        'channels' => ['attr' => 'table,ck,num,order,where,fields,item', 'alias' => 'channellist'],
        'banners' => ['attr' => 'table,ck,num,order,where,fields,item', 'alias' => 'bannerlist'],
        'positions' => ['attr' => 'table,ck,num,order,where,fields,item', 'alias' => 'positionlist'],
        'tags' => ['attr' => 'table,ck,num,order,where,fields,item', 'alias' => 'taglist'],
        //
        'article' => ['attr' => 'table,pk,where,fields,item', 'expression' => true],
        'channel' => ['attr' => 'table,pk,where,fields,item', 'expression' => true],
        'banner' => ['attr' => 'table,pk,where,fields,item', 'expression' => true],
        'position' => ['attr' => 'table,pk,where,fields,item', 'expression' => true],
        'tag' => ['attr' => 'table,pk,where,fields,item', 'expression' => true],
    ];

    public function tagArticles($tag, $content)
    {
        $table = 'cms_content';
        $tag['table'] = $table;

        return $this->tagList($tag, $content);
    }

    public function tagArticle($tag, $content)
    {
        $table = 'cms_content';
        $tag['table'] = $table;

        return $this->tagDetail($tag, $content);
    }

    public function tagChannels($tag, $content)
    {
        $table = 'cms_channel';
        $tag['table'] = $table;

        return $this->tagList($tag, $content);
    }

    public function tagChannel($tag, $content)
    {
        $table = 'cms_channel';
        $tag['table'] = $table;

        return $this->tagDetail($tag, $content);
    }

    public function tagBanners($tag, $content)
    {
        $table = 'cms_banner';
        $tag['table'] = $table;

        return $this->tagList($tag, $content);
    }

    public function tagPositions($tag, $content)
    {
        $table = 'cms_position';
        $tag['table'] = $table;

        return $this->tagList($tag, $content);
    }

    public function tagPosition($tag, $content)
    {
        $table = 'cms_position';
        $tag['table'] = $table;

        return $this->tagDetail($tag, $content);
    }

    public function tagTags($tag, $content)
    {
        $table = 'cms_tag';
        $tag['table'] = $table;

        return $this->tagList($tag, $content);
    }

    public function tagTag($tag, $content)
    {
        $table = 'cms_tag';
        $tag['table'] = $table;

        return $this->tagDetail($tag, $content);
    }

    public function tagList($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!$this->isAllowTable($table)) {
            return "数据表：{$table}未允许使用标签。";
        }

        $ck = $tag['ck'] ?? $this->defaultCk($table);
        $num = $tag['num'] ?? 20;
        $item = $tag['item'] ?? 'item';
        $order = $tag['order'] ?? $this->defaultOrder($table);
        $fields = $tag['fields'] ?? $this->defaultFields($table);
        $where = $tag['where'] ?? '';

        $__cid_v__ = '';
        if ($ck && !empty($tag[$ck])) {
            $__cid_v__ = $tag[$ck];
        } else if ($ck && !empty($tag['cid'])) {
            $__cid_v__ = $tag['cid'];
        }

        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = $this->defaultScope($table);

        $parseStr = <<<EOT
        <?php
        \$__where_exp__ = '{$where}';
        \$__where__ = [];
        \$__cid__ = '{$ck}';
        \$__cid_v__ = '';
        if(\$__cid__){
            \$__cid_v__ = '{$__cid_v__}' ?: (\$cid ?? '');
            \$__where__[] = [\$__cid__, '=', \$__cid_v__];
        }
        \$__page__ = input('get.page/d', 1);
        \$__page__ = \$__page__ < 1 ? 1 : \$__page__;

        \$__LIST__ = Db::name('{$table}')
            ->where(\$__where__)
            ->where(\$__where_exp__)
            ->where('{$scope}')
            ->order('{$order}')
            ->field('{$fields}')
            ->limit((\$__page__ - 1) * {$num}, {$num})
            ->select();
        foreach(\$__LIST__ as &\$__LI__)
        {
            \$__LI__ = \\tpext\\cms\\common\\taglib\\Cms::processItem('{$table}', \$__LI__, '{$fields}');
        }
        trace(\$__LIST__);
        ?>
        {volist name="__LIST__" id="{$item}"}
        {$content}
        {/volist}

EOT;
        return $parseStr;
    }

    public function tagDetail($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!$this->isAllowTable($table)) {
            return "数据表：{$table}未允许使用标签。";
        }

        $pk = $tag['pk'] ?? $this->defaultPk($table);
        $item = $tag['item'] ?? 'item';
        $fields = $tag['fields'] ?? $this->defaultFields($table);
        $where = $tag['where'] ?? '';

        $__id_v__ = '';
        if ($pk && !empty($tag[$pk])) {
            $__id_v__ = $tag[$pk];
        }

        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = $this->defaultScope($table);

        $parseStr = <<<EOT
        <?php
        \$__where_exp__ = '{$where}';
        \$__where__ = [];
        \$__id__ = '{$pk}';
        \$__id_v__ = '{$__id_v__}' ?: (\$id ?? '');
        \$__where__[] = [\$__id__, '=', \$__id_v__];

        \$__DETAIL__ = Db::name('{$table}')
            ->where(\$__where__)
            ->where(\$__where_exp__)
            ->where('{$scope}')
            ->field('{$fields}')
            ->find();
        
        \$__DETAIL__ = \\tpext\\cms\\common\\taglib\\Cms::processDetail('{$table}', \$__DETAIL__, '{$fields}');
        ?>

        {assign name="{$item}" value="\$__DETAIL__"/}
        {notempty name="{$item}"}
        {$content}
        {/notempty}

EOT;
        return $parseStr;
    }

    /**
     * 列表分类字段
     *
     * @param string $table
     * @return string
     */
    protected function defaultCk($table)
    {
        $ck = '';

        if ($table == 'cms_channel') {
            $ck = 'parent_id';
        } else if ($table == 'cms_content') {
            $ck = 'channel_id';
        } else if ($table == 'cms_position') {
            $ck = '';
        } else if ($table == 'cms_banner') {
            $ck = 'position_id';
        } else  if ($table == 'cms_tag') {
            $ck = '';
        }

        return $ck;
    }

    /**
     * 详情主键字段
     *
     * @param string $table
     * @return string
     */
    protected function defaultPk($table)
    {
        return 'id';
    }

    /**
     * 列表默认排序
     *
     * @param string $table
     * @return string
     */
    protected function defaultOrder($table)
    {
        return 'id desc';
    }

    /**
     * 默认查询字段
     *
     * @param string $table
     * @return string
     */
    protected function defaultFields($table)
    {
        return '*';
    }

    /**
     * 默认查询条件，字符串形式，如： 'is_show=1'。不支持变量，如： 'name=' . input('name')。
     *
     * @param string $table
     * @return string
     */
    protected function defaultScope($table)
    {
        $where = '';

        if ($table == 'cms_channel') {
            $where = 'is_show=1';
        } else if ($table == 'cms_content') {
            $where = 'is_show=1';
        } else if ($table == 'cms_position') {
            $where = 'is_show=1';
        } else if ($table == 'cms_banner') {
            $where = 'is_show=1';
        } else  if ($table == 'cms_tag') {
            $where = 'is_show=1';
        }

        return $where;
    }

    /**
     * 是否允许数据表使用标签
     *
     * @param string $table
     * @return boolean
     */
    protected function isAllowTable($table)
    {
        return in_array($table, ['cms_channel', 'cms_content', 'cms_position', 'cms_banner', 'cms_tag']);
    }

    /**
     * 处理列表条目
     *
     * @param string $table
     * @param array $item
     * @return array
     */
    public static function processItem($table, &$item, $fields = '*')
    {
        if (empty($item)) {
            return $item;
        }

        trace($table);
        if ($table == 'cms_channel') {
            $item['url'] = self::$path . '/c' . $item['id'] . '.html';
            $item['parent'] = static::getData('cms_channel', $item['parent_id']);
        } else if ($table == 'cms_content') {
            $item['url'] = self::$path . '/c' . $item['channel_id'] . '/a' . $item['id'] . '.html';
            $item['channel'] = static::getData('cms_channel', $item['channel_id']);

            trace(json_encode($item['channel']));
        } else if ($table == 'cms_position') {
            $item['url'] = self::$path . '/p' . $item['id'] .   '.html';
        } else if ($table == 'cms_banner') {
            $item['url'] = self::$path . '/p' . $item['position_id'] . '/b' . $item['id'] . '.html';
            $item['position'] = static::getData('cms_position', $item['position_id']);
        } else if ($table == 'cms_tag') {
            $item['url'] = self::$path . '/t' . $item['id'] . '.html';
        } else {
            $item['url'] = self::$path . '/' . $table . $item['id'] . '.html';
        }

        return $item;
    }

    /**
     * 处理数据详情
     *
     * @param string $table
     * @param array $item
     * @return array
     */
    public static function processDetail($table, $item, $fields = '*')
    {
        if (empty($item)) {
            $empty = new EmptyData;
            return $empty;
        }

        $item['__not_found__'] = false;

        if ($table == 'cms_channel') {
            $item['parent'] = static::getData('cms_channel', $item['parent_id']);
        } else if ($table == 'cms_content') {
            $item['channel'] = static::getData('cms_channel', $item['channel_id']);
            $item['prev'] = Db::name($table)->where('channel_id', $item['channel_id'])->where('is_show', 1)->where('id', '<', $item['id'])->find();
            $item['next'] = Db::name($table)->where('channel_id', $item['channel_id'])->where('is_show', 1)->where('id', '>', $item['id'])->find();
        } else if ($table == 'cms_position') {
            //
        } else if ($table == 'cms_banner') {
            $item['position'] = static::getData('cms_position', $item['position_id']);
        } else if ($table == 'cms_tag') {
            //
        } else {
            //
        }

        return $item;
    }

    /**
     * Undocumented function
     *
     * @param string $table
     * @param int $id
     * @return array
     */
    protected static function getData($table, $id)
    {
        $key = $table . '_' . $id;

        if (!isset(static::$tableData[$key])) {
            static::$tableData[$key] = Db::name($table)->where('id', $id)->find();
        }

        return static::$tableData[$key] ?? [];
    }
}
