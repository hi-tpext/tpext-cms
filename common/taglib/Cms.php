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
class Cms extends TagLib
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

    /**
     * tagList
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagList($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "<!--数据表：{$table}未允许使用标签--><?php if(false): ?>" . $content . "<?php endif; ?>";
        }

        $take = $tag['num'] ?? 0;
        $pagesize = $tag['pagesize'] ?? 0;
        $pagesizeExpr = $this->isVarOrMethod($pagesize) ? "intval(" . trim($pagesize, ':') . " ?? 0)" : intval($pagesize);
        $item = !empty($tag['item']) ? $tag['item'] : ($tag['default_item'] ?? 'item');
        $assign = !empty($tag['assign']) ? $tag['assign'] : $table . '_list_' . time() . mt_rand(100, 999);
        $item = ltrim($item, '$');
        $assign = ltrim($assign, '$');
        $cache = explode(',', $tag['cache'] ?? '');
        $cacheKey = empty($cache[0]) ? 'false' : "'" . trim($cache[0]) . "'";
        $cacheTime = intval($cache[1] ?? 360);
        $tagOrder = !empty($tag['order']) ? $tag['order'] : Table::defaultOrder($table);
        $orderExpr = $this->parseOrder($tagOrder);
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $simple = $tag['simple'] ?? 'false';
        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);
        $links = true;
        if (isset($tag['links']) && ($tag['links'] == '0' || $tag['links'] == 'false' || $tag['links'] == 'n' || $tag['links'] == 'no')) {
            $links = false;
        }

        $parseStr = $this->bindKeyValWhere($tag);

        $parseStr .= <<<EOT
        \$__data__ = cms_query_list(
            '{$table}', \$__where__, '{$scope}', '{$fields}', {$orderExpr}, {$take}, {$pagesizeExpr}, {$cacheKey}, {$cacheTime}, {$simple}, '{$links}', \$vars
        );
        extract(\$__data__);
        \${$assign} = \$__list__;
        ?>

        {volist name="__list__" id="{$item}"}
        {$content}
        {/volist}
        {if condition="\$__has_paginator__ && \$__render_links__ && !empty(\$__links_html__)"}
        {\$__links_html__|raw}
        {/if}
        <?php

        \$__data__ = cms_restore_page_vars('{$table}', \$vars);
        extract(\$__data__);
        unset(\$__list__, \$__data__);
        ?>
EOT;
        $this->usedTags[] = $tag;
        return $parseStr;
    }

    /**
     * tagParents
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagParents($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "<!--数据表：{$table}未允许使用标签--><?php if(false): ?>" . $content . "<?php endif; ?>";
        }
        $pid_key = $tag['pid_key'] ?? 'parent_id';
        $id_key = $tag['id_key'] ?? 'id';
        $item = !empty($tag['item']) ? $tag['item'] : ($tag['default_item'] ?? 'item');
        $assign = !empty($tag['assign']) ? $tag['assign'] : $table . '_list_' . time() . mt_rand(100, 999);
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

        \$__list__ = cms_get_parents('{$table}', {$id_val}, '{$id_key}', '{$pid_key}', \$vars);
        ?>
        {volist name="__list__" id="{$item}"}
        {$content}
        {/volist}
        {assign name="{$assign}" value="\$__list__" /}
        <?php

        \$__data__ = cms_restore_page_vars('{$table}', \$vars);
        extract(\$__data__);
        unset(\$__list__, \$__data__);
        ?>
EOT;
        $this->usedTags[] = $tag;
        return $parseStr;
    }

    /**
     * tagGet
     * @param array $tag
     * @param string $content
     * @return string
     */
    public function tagGet($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "<!--数据表：{$table}未允许使用标签-->";
        }
        $assign = !empty($tag['assign']) ? $tag['assign'] : ($tag['default_assign'] ?? 'data');
        $assign = ltrim($assign, '$');
        $cache = explode(',', $tag['cache'] ?? '');
        $cacheKey = empty($cache[0]) ? 'false' : "'" . trim($cache[0]) . "'";
        $cacheTime = intval($cache[1] ?? 360);
        $order = $tag['order'] ?? '';
        $orderExpr = $this->parseOrder($order);
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);

        $parseStr = $this->bindKeyValWhere($tag);

        $parseStr .= <<<EOT
        \$__detail__ = cms_query_detail(
            '{$table}', \$__where__, '{$scope}', {$orderExpr}, '{$fields}', {$cacheKey}, {$cacheTime}
        );
        ?>
        {assign name="{$assign}" value="\$__detail__" /}
        {notempty name="{$assign}"}
        {$content}
        {/notempty}
         <?php
        unset(\$__detail__);
        ?>
EOT;
        $this->usedTags[] = $tag;
        return $parseStr;
    }

    protected function showVars()
    {
        $parseStr = <<<EOT

        <?php
        
        \$vars = \$vars ?? [];
        if(isset(\$vars['content']) && is_array(\$vars['content'])) {
            \$vars['content']['content'] = '**这里是文章内容(省略' . mb_strlen(\$vars['content']['content']) . '字)**';
        }
        
        \$vars = json_encode(\$vars ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        echo '<pre>' . \$vars . '</pre>';
        ?>
EOT;

        return $parseStr;
    }

    /**
     * 判断是否为变量或方法调用(如$where、getWhere()、\xx\yy::getWhere())
     *
     * @param string $str
     * @return bool
     */
    protected function isVarOrMethod($str)
    {
        return preg_match('/^\s*\$[a-zA-Z_][a-zA-Z_\d]*\s*$/', $str) || preg_match('/^\s*:?[a-zA-Z_\\\\:]+\([^\(\)]*?\)\s*$/', $str);
    }

    /**
     * order参数支持变量或方法，如order="$order"、order="getOrder()"
     *
     * @param string $order
     * @return string
     */
    protected function parseOrder($order)
    {
        if ($this->isVarOrMethod($order)) {
            $order = ltrim($order, ':');
        }
        return "'{$order}'";
    }

    /**
     * 解析标签中的 cid_key, id_key, where 等参数
     *
     * @param mixed $tag
     * @return string
     */
    protected function bindKeyValWhere($tag)
    {
        $cid_key = $tag['cid_key'] ?? '';
        $id_key = $tag['id_key'] ?? 'id';
        $where = $tag['where'] ?? '1=1';

        $cid_val = '';
        if ($cid_key && isset($tag[$cid_key]) && $tag[$cid_key] !== '') {
            $cid_val = trim($tag[$cid_key]);
        } else if ($cid_key && isset($tag['cid']) && $tag['cid'] !== '') {
            $cid_val = trim($tag['cid']);
        } else if ($cid_key == 'parent_id' && isset($tag['pid']) && $tag['pid'] !== '') {
            $cid_val = trim($tag['pid']);
        }

        if ($cid_val !== '') {
            if ($cid_val[0] == '$' || $cid_val[0] == ':') { //变量或方法
                $cid_val = $this->filterIdVar($cid_val);
            } else if (ctype_digit($cid_val)) {
                $cid_val = "{$cid_val}";
            } else if (preg_match('/^[\d,]+$/', $cid_val)) {
                $cid_val = "'{$cid_val}'";
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
            } else if (ctype_digit($id_val)) {
                $id_val = "{$id_val}";
            } else if (preg_match('/^[\d,]+$/', $id_val)) {
                $id_val = "'{$id_val}'";
            } else {
                $whereExp = $this->parseIdVal($id_key, $id_val);
                if ($whereExp) {
                    $where .= $whereExp;
                    $id_val = '0';
                }
            }
            $cid_val = '0';
        } else {
            $id_val = '0';
        }

        $binds = '';
        $whereVar = '[]';
        $whereRaw = '1=1';
        if ($where && $where != '1=1') {
            if ($this->isVarOrMethod($where)) {
                //where整体是一个变量或方法(如$where)，直接把变量的值作为查询条件(支持字符串或数组)
                $whereVar = ltrim($where, ':');
            } else {
                [$whereRaw, $binds] = $this->parseWhere($where, $cid_key); //解析变量
            }
        }

        $parseStr = <<<EOT

        <?php
        \$__where__ = cms_build_where('{$cid_key}', {$cid_val}, '{$id_key}', {$id_val});
        \$__where_var__ = {$whereVar};
        \$__where_var__ = is_string(\$__where_var__) ? sql_guard(\$__where_var__) : \$__where_var__;
        \$__where_raw__ = "{$whereRaw}";
        \$__where_binds__ = [{$binds}];
        \$__where__ = function (\$query) use (\$__where__, \$__where_var__, \$__where_raw__, \$__where_binds__) {
            \$query->where(\$__where__)->where(\$__where_var__)->whereRaw(\$__where_raw__, \$__where_binds__);
        };

EOT;

        return $parseStr;
    }

    /**
     * 解析where中的变量
     *
     * @param string $where
     * @param string $cid_key
     * 
     * @return array
     */
    protected function parseWhere($where, $cid_key)
    {
        $where = $this->whereExp($where, $cid_key);
        $binds = [];

        $where = preg_replace_callback(
            '/([\'\"])?\%?(\$[a-zA-Z_][a-zA-Z_\.\[\]\'\"]*)\%?\1?/',
            function ($m) use (&$binds) {
                $match = $m[2];
                $full = $m[0];
                $varName = preg_replace('/\W/is', '_', $match) . count($binds);

                if (strpos($match, '.') !== false) {
                    $bind = preg_replace('/\.([a-zA-Z_][a-zA-Z_0-9]*)/', '[\'\1\']', $match);
                } else {
                    $bind = $match;
                }

                if ($full[0] == '%') {
                    $bind = "'%' . {$bind}";
                }
                if (substr($full, -1) == '%') {
                    $bind = "{$bind} . '%'";
                }

                $binds[] = "'{$varName}' => {$bind}";
                return ':' . $varName;
            },
            $where
        );

        return [$where, implode(', ', $binds)];
    }

    /**
     * 解析id值
     *
     * @param string $idKey
     * @param string $idVal
     * @return string
     */
    protected function parseIdVal($idKey, $idVal)
    {
        $op = '=';
        if (preg_match('/^\(?\d,[,\d]+\)?$/is', $idVal)) {
            $op = 'in';
            $idVal = trim($idVal, ',');
        } else if (preg_match('/^(in|not\s*in)\s*\(?(.+?)\)?$/is', $idVal, $mch)) {
            $op = $mch[1];
            $idVal = '(' . trim($mch[2]) . ')';
        } else if (preg_match('/^(between|not\s*between)\s*\(?(.+?)\)?$/is', $idVal, $mch)) {
            $op = $mch[1];
            $idVal = trim($mch[2]);
            if (!strstr($idVal, 'and')) {
                $idVal = str_replace(',', ' and ', $idVal);
            }
        } else if (preg_match('/^(>|=|<|>=|<=|<>)\s+(.+?)$/is', $idVal, $mch)) {
            $op = $mch[1];
            $idVal = trim($mch[2]);
        } else if (preg_match('/^(gt|eq|lt|egt|elt|neq|!=)\s+(.+?)$/is', $idVal, $mch)) {
            $op = $mch[1];
            $idVal = trim($mch[2]);
        } else if (preg_match('/^(like|not\s*like)\s+(.+)$/is', $idVal, $mch)) {
            $op = $mch[1];
            $idVal = trim($mch[2], "'\"");
            if (!strstr($idVal, '%')) {
                $idVal = '%' . $idVal . '%';
            }
        }

        return " and {$idKey} {$op} {$idVal}";
    }

    /**
     * 替换表达式
     *
     * @param string $where
     * @param string $cid_key
     * 
     * @return string
     */
    protected function whereExp($where, $cid_key)
    {
        //替换where中的cid语法糖为真实字段
        if ($cid_key && stripos($where, 'cid') !== false) {
            $where = preg_replace('/(\bcid\s+)(gt|eq|lt|egt|elt|neq|not\s*in|like|not\s*like|between|not\s*between)\b/is', $cid_key . '$2', $where);
            $where = preg_replace('/(\bcid\s*)(\<|\>|=|!=)/is', $cid_key . '$2', $where);
        }
        if ($cid_key == 'parent_id' && stripos($where, 'pid') !== false) {
            $where = preg_replace('/(\bpid\s+)(gt|eq|lt|egt|elt|neq|not\s*in|like|not\s*like|between|not\s*between)\b/is', $cid_key . '$2', $where);
            $where = preg_replace('/(\bpid\s*)(\<|\>|=|!=)/is', $cid_key . '$2', $where);
        }
        //替换表达式
        if (preg_match('/egt|elt|neq|!=|not(?:between|in|like)|\b(?:gt|eq|lt)\b/i', $where)) {
            $where = str_ireplace(
                ['egt', 'elt', 'neq', 'notbetween', 'notin', 'notlike', 'gt', 'eq', 'lt', '!='],
                ['>=', '<=', '<>', 'not between', 'not in', 'not like', '>', '=', '<', '<>'],
                $where
            );
        }

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
            $var = "sql_guard({$var})";
        }
        return $var;
    }

    /**
     * 构建上一篇/下一篇标签
     *
     * @param array $tag
     * @param string $content
     * @param array $info
     * @param string $table
     * @param array $tagArr
     * @param bool $isPrev
     * @return string
     */
    protected function buildPrevNextTag($tag, $content, $info, $table, $tagArr, $isPrev)
    {
        $tag['table'] = $table;
        $tag['tag_name'] = $tagArr[0] . '@' . ($isPrev ? 'prev' : 'next');
        $tag['assign'] = empty($tag['assign']) ? ($isPrev ? 'prev' : 'next') : $tag['assign'];
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
        $orders = explode(',', $order);
        $first = preg_replace('/\s*(?:desc|asc)/i', '', $orders[0]);
        $sort = $tag['sort'] ?? "\${$tagArr[0]}." . trim($first);
        $isDesc = preg_match('/\s+desc\s*$/i', $orders[0]);

        if ($isPrev) {
            $cmp = $isDesc ? '>' : '<';
            $fields = [];
            foreach ($orders as $sod) {
                if (preg_match('/\s+desc\s*$/i', $sod)) {
                    $fields[] = preg_replace('/\s+desc\s*$/i', ' asc', trim($sod));
                } else if (preg_match('/\s+asc\s*$/i', $sod)) {
                    $fields[] = preg_replace('/\s+asc\s*$/i', ' desc', trim($sod));
                } else {
                    $fields[] = trim($sod) . ' desc';
                }
            }
            $tag['order'] = implode(',', $fields);
        } else {
            $cmp = $isDesc ? '<' : '>';
            $tag['order'] = $order;
        }

        $tag['where'] = "{$tag['id_key']} != {$id} and {$first} {$cmp} {$sort} and " . $where;
        $tag[$tag['id_key']] = '';
        return $this->tagGet($tag, $content);
    }

    public function __call($name, $arguments = [])
    {
        if (preg_match('/^tag(\w+@\w+)$/i', $name, $mchs) && count($arguments) == 2) {
            $tagName = strtolower($mchs[1]);
            if ('show@vars' == $tagName) {
                return $this->showVars();
            }
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
                    $tag['cid_key'] = $info['cid_key'] ?? '';
                    return $this->tagGet($tag, $content);
                }
                if ($info['tag_name'] . '@prev' == $tagName) {
                    return $this->buildPrevNextTag($tag, $content, $info, $table, $tagArr, true);
                }
                if ($info['tag_name'] . '@next' == $tagName) {
                    return $this->buildPrevNextTag($tag, $content, $info, $table, $tagArr, false);
                }
            }
            throw new Exception("未知标签：{$tagName}");
        }

        throw new Exception('Call to undefined method : ' . $name);
    }
}
