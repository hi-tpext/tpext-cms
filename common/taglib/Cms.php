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

use think\template\TagLib;
use think\Exception;

/**
 * Cms标签库解析类
 */
class Cms extends Taglib
{
    // 标签定义
    protected $tags = [];

    protected $tables = [];

    protected $usedTags = [];

    public function __construct($template)
    {
        $this->tags = Table::getTagsList();
        $this->tables = Table::getTables();
        parent::__construct($template);
    }

    public function tagList($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "<!--数据表：{$table}未允许使用标签-->";
        }
        $cid_key = $tag['cid_key'] ?? '';
        $id_key = $tag['id_key'] ?? 'id';

        $take = $tag['num'] ?? 0;
        $pagesize = $tag['pagesize'] ?? 0;
        $item = !empty($tag['item']) ? $tag['item'] : ($tag['default_item'] ?? 'item');
        $assign = !empty($tag['assign']) ? $tag['assign'] : $table . '_list_' . time();
        $item = ltrim($item, '$');
        $assign = ltrim($assign, '$');
        $cache = explode(',', $tag['cache'] ?? '');
        $cacheKey = empty($cache[0]) ? 'false' : "'" . trim($cache[0]) . "'";
        $cacheTime = intval($cache[1] ?? 360);
        $tagOrder = !empty($tag['order']) ? $tag['order'] : Table::defaultOrder($table);
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $where = $tag['where'] ?? '1=1';
        $links = true;
        if (isset($tag['links']) && ($tag['links'] == '0' || $tag['links'] == 'false' || $tag['links'] == 'n' || $tag['links'] == 'no')) {
            $links = false;
        }

        $cid_val = '';
        if ($cid_key && isset($tag[$cid_key]) && $tag[$cid_key] !== '') {
            $cid_val = trim($tag[$cid_key]);
        }

        if ($cid_val !== '') {
            if ($cid_val[0] == '$' || $cid_val[0] == ':') { //变量或方法
                $cid_val = $this->filterIdVar($cid_val);
            } else if (is_int($cid_val)) {
                $cid_val = "{$cid_val}";
            } else {
                $whereExp = $this->parseIdVal($cid_key, $cid_val);
                if ($whereExp) {
                    $where .= $whereExp;
                    $cid_val = '0';
                }
            }
        } else if ($cid_key) {
            $cid_val = "(!empty(\${$cid_key}s) ? \${$cid_key}s : (\${$cid_key} ?? 0))";
        } else {
            $cid_val = '0';
        }

        $id_val = '';
        if ($id_key && isset($tag[$id_key]) && $tag[$id_key] !== '') {
            $id_val = trim($tag[$id_key]);
        }
        if ($id_val !== '') {
            if ($id_val[0] == '$' || $id_val[0] == ':') { //解析变量或方法
                $id_val = $this->filterIdVar($id_val);
                $where .= "and {$id_key} = {$id_val}";
            } else if (is_int($id_val)) {
                $id_val = "{$id_val}";
                $where .= "and {$id_key} = {$id_val}";
            } else {
                $whereExp = $this->parseIdVal($id_key, $id_val);
                if ($whereExp) {
                    $where .= $whereExp;
                }
            }
            $cid_val = '0';
        }
        $binds = '';
        if ($where && $where != '1=1') {
            [$where, $binds] = $this->parseWhere($where); //解析变量
        }

        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);
        $dbNameSpace = Processer::getDbNamespace();

        $parseStr = <<<EOT
        <?php
        \$__where_raw__ = "{$where}";
        \$__where__ = [];
        \$__cid_key__ = '{$cid_key}';
        if(\$__cid_key__) {
            \$__cid_val__ = {$cid_val} ?? 0;
            if(\$__cid_val__) {
                if(is_array(\$__cid_val__) || strstr(\$__cid_val__, ',')) {
                    \$__where__[] = [\$__cid_key__, 'in', \$__cid_val__];
                } else {
                    \$__where__[] = [\$__cid_key__, '=', \$__cid_val__];
                }
            }
        }
        \$__page__ = 1;
        \$__render_links__ = '{$links}' == '1';
        \$__has_paginator__ = false;
        \$__take__ = {$take};
        \$__pagesize__ = {$pagesize} ?: (\$__set_pagesize__ ?? 0);
        if(\$__take__ == 0) {
            if(\$__pagesize__ > 0) {
                \$__page__ = isset(\$page) && \$page > 0 ? \$page : 1;
                \$__take__ = \$__pagesize__;
                \$__has_paginator__ = true;
            } else {
                \$__take__ = 10;
            }
        }
        \$__list__ = [];
        \$__order_by__ = '{$table}' =='cms_content' && !empty(\$__set_order_by__) ? \$__set_order_by__ . '{$tagOrder}' : '{$tagOrder}';

        \$__data__ = {$dbNameSpace}::name('{$table}')
            ->where(\$__where__)
            ->whereRaw(\$__where_raw__, [{$binds}])
            ->where('{$scope}')
            ->field('{$fields}')
            ->order(\$__order_by__)
            ->limit((\$__page__ - 1) * \$__take__, \$__take__)
            ->cache({$cacheKey}, {$cacheTime}, '{$table}')
            ->select();
        foreach(\$__data__ as \$__d__) {
            \$__list__[] = \\tpext\\cms\\common\\taglib\\Processer::item('{$table}', \$__d__);
        }
        if(\$__has_paginator__) {
            \$__total__ = {$dbNameSpace}::name('{$table}')
                ->where(\$__where__)
                ->whereRaw(\$__where_raw__, [{$binds}])
                ->where('{$scope}')
                ->count();
                
            \$__paginator__ = new \\think\\paginator\\driver\\Bootstrap(\$__data__, \$__pagesize__, \$__page__, \$__total__, false, ['path' => \$__set_page_path__ ?? '']);
            \$__links_html__ = \$__paginator__->render();
        }
        ?>
        {volist name="__list__" id="{$item}"}
        {$content}
        {/volist}
        {if condition="\$__has_paginator__ && \$__render_links__ && !empty(\$__links_html__)"}
        {\$__links_html__|raw}
        {elseif condition="\$__has_paginator__"}
        <!-- 未自动输出分页，请在页面需要的位置调用 -->
        {/if}
        {assign name="{$assign}" value="\$__list__" /}
        <?php
        unset(\$__data__, \$__where_raw__, \$__where__, \$__cid_key__, \$__id_key__, \$__cid_val__, \$__id_val__, \$__paginator__, \$__total__, \$__take__, \$__pagesize__, \$__page__);
        ?>
EOT;
        $this->usedTags[] = $tag;
        return $parseStr;
    }

    public function tagParents($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "<!--数据表：{$table}未允许使用标签-->";
        }
        $pid_key = $tag['pid_key'] ?? 'parent_id';
        $id_key = $tag['id_key'] ?? 'id';
        $item = !empty($tag['item']) ? $tag['item'] : ($tag['default_item'] ?? 'item');
        $assign = !empty($tag['assign']) ? $tag['assign'] : $table . '_list_' . time();
        $item = ltrim($item, '$');
        $assign = ltrim($assign, '$');
        $fields = $tag['fields'] ?? Table::defaultFields($table);

        $id_val = '';
        if ($id_key && isset($tag[$id_key]) && $tag[$id_key] !== '') {
            $id_val = trim($tag[$id_key]);
        }

        if ($id_val !== '') {
            if ($id_val[0] == '$' || $id_val[0] == ':') { //解析变量或方法
                $id_val = $this->filterIdVar($id_val);
            } else if (is_int($id_val)) {
                $id_val = "{$id_val}";
            }
        } else {
            $id_val = "\${$id_key}";
        }

        $fields = is_array($fields) ? implode(',', $fields) : $fields;

        $parseStr = <<<EOT
        <?php
        \$__pid_key__ ='{$pid_key}';
        \$__id_key__ = '{$id_key}';
        \$__id_val__ = {$id_val} ?? 0;
        \$__list__ = \\tpext\\cms\\common\\taglib\\Processer::getParents('{$table}', \$__id_val__, \$__id_key__, \$__pid_key__);
        ?>
        {volist name="__list__" id="{$item}"}
        {$content}
        {/volist}
        {assign name="{$assign}" value="\$__list__" /}
        <?php
        unset(\$__pid_key__, \$__id_key__, \$__id_val__);
        ?>
EOT;
        $this->usedTags[] = $tag;
        return $parseStr;
    }

    public function tagGet($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "<!--数据表：{$table}未允许使用标签-->";
        }
        $id_key = $tag['id_key'] ?? 'id';
        $assign = !empty($tag['assign']) ? $tag['assign'] : ($tag['default_assign'] ?? 'data');
        $assign = ltrim($assign, '$');
        $cache = explode(',', $tag['cache'] ?? '');
        $cacheKey = empty($cache[0]) ? 'false' : "'" . trim($cache[0]) . "'";
        $cacheTime = intval($cache[1] ?? 360);
        $order = $tag['order'] ?? '';
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $where = $tag['where'] ?? '1=1';
        $id_val = '';
        if ($id_key && isset($tag[$id_key]) && $tag[$id_key] !== '') {
            $id_val = trim($tag[$id_key]);
        }
        if ($id_val !== '') {
            if ($id_val[0] == '$' || $id_val[0] == ':') { //解析变量或方法
                $id_val = $this->filterIdVar($id_val);
            } else if (is_int($id_val)) {
                $id_val = "{$id_val}";
            } else {
                $whereExp = $this->parseIdVal($id_key, $id_val);
                if ($whereExp) {
                    $id_val = '0';
                    $where .= $whereExp;
                }
            }
        } else {
            $id_val = "\${$id_key}";
        }
        $binds = '';
        if ($where && $where != '1=1') {
            [$where, $binds] = $this->parseWhere($where); //解析变量
        }

        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);
        $dbNameSpace = Processer::getDbNamespace();

        $parseStr = <<<EOT
        <?php
        \$__where_raw__ = "{$where}";
        \$__where__ = [];
        \$__id_key__ = '{$id_key}';
        \$__id_val__ = {$id_val} ?? 0;
        if(empty(\$__where_raw__) || \$__where_raw__ == '1=1') {
            \$__where__[] = [\$__id_key__, '=', \$__id_val__];
        }
        \$__detail__ = {$dbNameSpace}::name('{$table}')
            ->where(\$__where__)
            ->whereRaw(\$__where_raw__, [$binds])
            ->where('{$scope}')
            ->order('{$order}')
            ->field('{$fields}')
            ->cache({$cacheKey}, {$cacheTime}, '{$table}')
            ->find();
        \$__detail__ = \\tpext\\cms\\common\\taglib\\Processer::detail('{$table}', \$__detail__);
        ?>
        {assign name="{$assign}" value="\$__detail__" /}
        {notempty name="{$assign}"}
        {$content}
        {/notempty}
        <?php
        unset(\$__where_raw__, \$__where__, \$__id_key__, \$__id_val__);
        ?>
EOT;
        $this->usedTags[] = $tag;
        return $parseStr;
    }

    protected function whereExp($where)
    {
        $where = str_ireplace(['egt', 'elt', 'neq'], ['>=', '<=', '<>'], $where);
        $where = str_ireplace(['gt', 'eq', 'lt', '!='], ['>', '=', '<', '<>'], $where);

        return $where;
    }

    /**
     * 安全转换变量
     * 
     * @param string $var
     * @return string
     */
    protected function filterIdVar($var)
    {
        $var = $this->autoBuildVar($var);
        if (preg_match('/\$_(SERVER|REQUEST|GET|POST|COOKIE|SESSION)/i', $var) || preg_match('/app\(/i', $var)) {
            $var = "filter_var({$var}, FILTER_VALIDATE_INT)";
        }
        return $var;
    }

    /**
     * 解析where中的变量
     *
     * @param string $where
     * @return array
     */
    protected function parseWhere($where)
    {
        $where = $this->whereExp($where);

        $binds = [];
        preg_match_all('/\%?(\$[\w\.]+)\%?/', $where, $matches);
        if (isset($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $i => $match) {
                $varName = preg_replace('/\W/is', '_', $match) . $i;
                $replace = ':' . $varName;
                $bind = '';
                if (strpos($match, '.') !== false) {
                    $names = explode('.', $match);
                    $first = array_shift($names);
                    $bind = $first . '[\'' . implode('\'][\'', $names) . '\']';
                } else {
                    $bind = $match;
                }
                $find = $match;
                if ($matches[0][$i][0] == '%') {
                    $find = '%' . $find;
                    $bind = "'%' . {$bind}";
                }
                if ($matches[0][$i][-1] == '%') {
                    $find = $find . '%';
                    $bind = "{$bind} . '%'";
                }
                $binds[] = "'{$varName}' => {$bind}";
                $where = substr_replace($where, $replace, strpos($where, $find), strlen($find));
            }
        }
        return [$where, implode(', ', $binds)];
    }

    /**
     * 解析id值
     *
     * @param string $where
     * @return string
     */
    protected function parseIdVal($idKey, $idVal)
    {
        $where = '';
        if (preg_match('/^\(?\d[,\d]+\)?$/is', $idVal)) {
            $idVal = trim($idVal, '()');
            $where = " and {$idKey} in ({$idVal})";
        } else if (preg_match('/^in([^\(]+)/is', $idVal, $mch)) {
            $where = " and {$idKey} in ({$mch[1]})";
        } else if (preg_match('/^not\s*in([^\(]+)/is', $idVal, $mch)) {
            $where = " and {$idKey} not in ({$mch[1]})";
        } else if (preg_match('/^not\s*in(.+)$/is', $idVal, $mch)) {
            $where = " and {$idKey} not in {$mch[1]}";
        } else if (preg_match('/^!=(.+)$/is', $idVal, $mch)) {
            $where = " and {$idKey} <> {$mch[1]}";
        } else if (preg_match('/^(?:in|>|=|<|>=|<=|<>)/is', $idVal)) {
            $where = " and {$idKey} {$idVal}";
        } else if (preg_match('/^(?:gt|eq|lt|egt|elt|neq|!=)/is', $idVal)) {
            $idVal = $this->whereExp($idVal);
            $where = " and {$idKey} {$idVal}";
        } else if (preg_match('/^(like|not\s*like)\s+(.+)$/is', $idVal, $mch)) {
            $word = trim($mch[2], "'\"");
            if (!strstr($word, '%')) {
                $mch[2] = '%' . $word . '%';
            }
            $where = " and {$idKey} {$mch[1]} '{$word}'";
        } else if (preg_match('/^(between|not\s*between)\s+(.+)$/is', $idVal, $mch)) {
            $where = " and {$idKey} {$mch[1]} {$mch[2]}";
        } else {
            $where = " and {$idKey} = {$idVal}";
        }
        return $where;
    }

    public function __call($name, $arguments = [])
    {
        if (preg_match('/^tag(\w+@\w+)$/i', $name, $mchs) && count($arguments) == 2) {
            $tagName = strtolower($mchs[1]);
            $tag = $arguments[0];
            $content = $arguments[1];
            $tagArr = explode('@', $tagName);
            foreach ($this->tables as $table => $info) {
                if (empty($info['tag_name'])) {
                    continue;
                }

                if ($info['tag_name'] . '@list' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['default_item'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    $tag['cid_key'] = $info['cid_key'] ?? '';
                    $tag['pid_key'] = $info['pid_key'] ?? '';
                    return $this->tagList($tag, $content);
                }
                if ($info['tag_name'] . '@parents' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['default_item'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    $tag['cid_key'] = $info['cid_key'] ?? '';
                    $tag['pid_key'] = $info['pid_key'] ?? '';
                    return $this->tagParents($tag, $content);
                }
                if ($info['tag_name'] . '@get' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['default_assign'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    return $this->tagGet($tag, $content);
                }
                if ($info['tag_name'] . '@prev' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['assign'] = empty($tag['assign']) ? 'prev' : $tag['assign'];
                    $tag['default_assign'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    $where = '';
                    if (empty($tag['where'])) {
                        $cid_key = $info['cid_key'] ?? '';
                        if ($cid_key) {
                            $where = $cid_key . "=\${$tagArr[0]}." . $cid_key;
                        } else {
                            $where = '1=1';
                        }
                    } else {
                        $where = $tag['where'];
                    }

                    $id = $tag[$tag['id_key']] ?? '$id';
                    $order = $tag['order'] ?? Table::defaultOrder($table);
                    $sort = $tag['sort'] ?? "\${$tagArr[0]}.sort";
                    $fields = [];
                    $orders = explode(',', $order);
                    foreach ($orders as $sod) {
                        if (stripos($sod, 'desc') !== false) {
                            $fields[] = preg_replace('/^(\w+\s+)desc$/is', '$1asc', $sod);
                        } else {
                            $fields[] = preg_replace('/^(\w+)(?:\s+asc)?$/is', '$1 desc', $sod);
                        }
                    }
                    $first = preg_replace('/\s*(?:desc|asc)/', '', $orders[0]);
                    $isDesc = stripos($orders[0], 'desc') !== false;
                    $cmp = $isDesc ? '>' : '<';
                    $tag['where'] = "{$tag['id_key']} != {$id} and {$first} {$cmp} {$sort} and " . $where;
                    $tag['order'] = implode(',', $fields);
                    $tag[$tag['id_key']] = '';
                    return $this->tagGet($tag, $content);
                }
                if ($info['tag_name'] . '@next' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['assign'] = empty($tag['assign']) ? 'next' : $tag['assign'];
                    $tag['default_assign'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    $where = '';
                    if (empty($tag['where'])) {
                        $cid_key = $info['cid_key'] ?? '';
                        if ($cid_key) {
                            $where = $cid_key . "=\${$tagArr[0]}." . $cid_key;
                        } else {
                            $where = '1=1';
                        }
                    } else {
                        $where = $tag['where'];
                    }
                    $id = $tag[$tag['id_key']] ?? '$id';
                    $order = $tag['order'] ?? Table::defaultOrder($table);
                    $sort = $tag['sort'] ?? "\${$tagArr[0]}.sort";
                    $orders = explode(',', $order);
                    $first = preg_replace('/\s*(?:desc|asc)/', '', $orders[0]);
                    $isDesc = stripos($orders[0], 'desc') !== false;
                    $cmp = $isDesc ? '<' : '>';
                    $tag['where'] = "{$tag['id_key']} != {$id} and {$first} {$cmp} {$sort} and " . $where;
                    $tag['order'] = $order;
                    $tag[$tag['id_key']] = '';
                    return $this->tagGet($tag, $content);
                }
            }
            throw new Exception("未知标签：{$tagName}");
        }

        throw new Exception('Call to undefined method : ' . $name);
    }
}
