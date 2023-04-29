<?php

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
            return "数据表：{$table}未允许使用标签。";
        }
        $cid_key = $tag['cid_key'] ?? '';
        $id_key = $tag['id_key'] ?? 'id';

        $take = $tag['num'] ?? 0;
        $item = !empty($tag['item']) ? $tag['item'] : ($tag['default_item'] ?? 'item');
        $assign = !empty($tag['assign']) ? $tag['assign'] : $table . '_list_' . time();
        $item = ltrim($item, '$');
        $assign = ltrim($assign, '$');
        $order = $tag['order'] ?? Table::defaultOrder($table);
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
                $cid_val = $this->autoBuildVar($cid_val);
            } else if (is_int($cid_val)) {
                $cid_val = "{$cid_val}";
            } else if (preg_match('/^\(?\d[,\d]+\)?$/is', $cid_val)) {
                $cid_val = trim($cid_val, '()');
                $where .= " and {$cid_key} in ({$cid_val})";
                $cid_val = '';
            } else if (preg_match('/^in([^\(]+)$/is', $cid_val, $mch)) {
                $where .= " and {$cid_key} in ({$mch[1]})";
                $cid_val = '';
            } else if (preg_match('/^not\s*in([^\(]+)$/is', $cid_val, $mch)) {
                $where .= " and {$cid_key} not in ({$mch[1]})";
                $cid_val = '';
            } else if (preg_match('/^not\s*in(.+)$/is', $cid_val, $mch)) {
                $where .= " and {$cid_key} not in {$mch[1]}";
                $cid_val = '';
            } else if (preg_match('/^!=(.+)$/is', $cid_val, $mch)) {
                $where .= " and {$cid_key} <> {$mch[1]}";
                $cid_val = '';
            } else if (preg_match('/^(in|>|=|<|>=|<=|<>)/is', $cid_val)) {
                $where .= " and {$cid_key} {$cid_val}";
                $cid_val = '';
            }
        } else {
            $cid_val = "\${$cid_key}";
        }

        $id_val = '';
        if ($id_key && isset($tag[$id_key]) && $tag[$id_key] !== '') {
            $id_val = trim($tag[$id_key]);
        }
        if ($id_val) {
            if (preg_match('/^\(?\d[,\d]+\)?$/is', $id_val)) {
                $id_val = trim($id_val, '()');
                $where .= " and {$id_key} in ({$id_val})";
            } else if (preg_match('/^in([^\(]+)/is', $id_val, $mch)) {
                $where .= " and {$id_key} in ({$mch[1]})";
            } else if (preg_match('/^not\s*in([^\(]+)/is', $id_val, $mch)) {
                $where .= " and {$id_key} not in ({$mch[1]})";
            } else if (preg_match('/^not\s*in(.+)$/is', $id_val, $mch)) {
                $where .= " and {$id_key} not in {$mch[1]}";
            } else if (preg_match('/^!=(.+)$/is', $id_val, $mch)) {
                $where .= " and {$id_key} <> {$mch[1]}";
            } else if (preg_match('/^(in|>|=|<|>=|<=|<>)/is', $id_val)) {
                $where .= " and {$id_key} {$id_val}";
            } else {
                $where .= " and {$id_key} = {$id_val}";
            }
            $cid_val = '0';
        }

        if ($where && $where != '1=1') { //解析变量
            $where = str_replace('!=', '<>', $where);
            preg_match_all('/([\$:][\w\.]+)/', $where, $matches);
            if (isset($matches[1]) && count($matches[1]) > 0) {
                $keys = [];
                $replace = [];
                foreach ($matches[1] as $match) {
                    $keys[] = $match;
                    if (strpos($match, '.')) {
                        $vars  = explode('.', $match);
                        $first = array_shift($vars);
                        $replace[] = '\'" . ' . $first . '[\'' . implode('\'][\'', $vars) . '\']' . ' . "\'';
                    } else {
                        $replace[] = '\'" . ' . $match . ' . "\'';
                    }
                }
                $where = str_replace($keys, $replace, $where);
            }
        }

        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);
        $dbNameSpace = class_exists(\think\facade\Db::class) ? '\think\facade\Db' : '\think\Db';

        $parseStr = <<<EOT
        <?php

        \$__where_exp__ = "{$where}";
        \$__where__ = [];
        \$__cid_key__ = '{$cid_key}';
        
        if(\$__cid_key__){
            \$__cid_val__ = {$cid_val} ?? 0;
            if(\$__cid_val__)
            {
                \$__where__[] = [\$__cid_key__, '=', \$__cid_val__];
            }
        }
        
        \$__page__ = 1;
        \$__render_links__ = '{$links}' == '1';

        \$has_paginator = false;

        \$__take__ = {$take};
        if(\$__take__ == 0)
        {
            if(isset(\$pagesize) && \$pagesize > 0)
            {
                \$__page__ = \$page < 1 ? 1 : \$page;
                \$__take__ = \$pagesize;
                \$has_paginator = true;
            }
            else
            {
                \$__take__ = 10;
            }
        }

        \$__list__ = {$dbNameSpace}::name('{$table}')
            ->where(\$__where__)
            ->where(\$__where_exp__)
            ->where('{$scope}')
            ->order('{$order}')
            ->field('{$fields}')
            ->limit((\$__page__ - 1) * \$__take__, \$__take__)
            ->select();
        foreach(\$__list__ as &\$__li__)
        {
            \$__li__ = \\tpext\\cms\\common\\taglib\\Processer::item('{$table}', \$__li__);
        }

        unset(\$__li__);

        \$__links_html__ = null;

        if(\$has_paginator)
        {
            \$total = {$dbNameSpace}::name('{$table}')
                ->where(\$__where__)
                ->where(\$__where_exp__)
                ->where('{$scope}')
                ->count();
                
            \$__paginator__ = new \\think\\Paginator\\driver\\Bootstrap(\$__list__, \$pagesize, \$__page__, \$total, false, ['path' => \$path ?? 'no_path']);
            \$__links_html__ = \$__paginator__->render();
        }
        ?>

        {volist name="__list__" id="{$item}"}
        {$content}
        {/volist}
        {if condition="\$__render_links__ && \$__links_html__"}
        {\$__links_html__|raw}
        {elseif condition="\$has_paginator"}
        <!-- 未自动输出分页，请在页面需要的位置调用 -->
        {/if}
        {assign name="{$assign}" value="\$__list__" /}
        
        <?php
        unset(\$__where_exp__, \$__where__, \$__cid_key__, \$__id_key__, \$__cid_val__, \$__id_val__, \$__paginator__, \$total);
        ?>
EOT;
        return $parseStr;
    }

    public function tagParents($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "数据表：{$table}未允许使用标签。";
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
                $id_val = $this->autoBuildVar($id_val);
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
        return $parseStr;
    }

    public function tagDetail($tag, $content)
    {
        $table = $tag['table'] ?? '';
        if (!Table::isAllowTable($table)) {
            return "数据表：{$table}未允许使用标签。";
        }
        $id_key = $tag['id_key'] ?? 'id';
        $assign = !empty($tag['assign']) ? $tag['assign'] : ($tag['default_assign'] ?? 'data');
        $assign = ltrim($assign, '$');
        $order = $tag['order'] ?? '';
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $where = $tag['where'] ?? '1=1';
        $id_val = '';
        if ($id_key && isset($tag[$id_key]) && $tag[$id_key] !== '') {
            $id_val = trim($tag[$id_key]);
        }
        if ($id_val !== '') {
            if ($id_val[0] == '$' || $id_val[0] == ':') { //解析变量或方法
                $id_val = $this->autoBuildVar($id_val);
            } else if (is_int($id_val)) {
                $id_val = "{$id_val}";
            } else if (preg_match('/^\(?\d[,\d]+\)?$/is', $id_val)) {
                $id_val = trim($id_val, '()');
                $where .= " and {$id_key} in ({$id_val})";
                $id_val = '0';
            } else if (preg_match('/^in([^\(]+)/is', $id_val, $mch)) {
                $where .= " and {$id_key} in ({$mch[1]})";
                $id_val = '0';
            } else if (preg_match('/^not\s*in([^\(]+)/is', $id_val, $mch)) {
                $where .= " and {$id_key} not in ({$mch[1]})";
                $id_val = '0';
            } else if (preg_match('/^not\s*in(.+)$/is', $id_val, $mch)) {
                $where .= " and {$id_key} not in {$mch[1]}";
                $id_val = '0';
            } else if (preg_match('/^!=(.+)$/is', $id_val, $mch)) {
                $where .= " and {$id_key} <> {$mch[1]}";
                $id_val = '0';
            } else if (preg_match('/^(in|>|=|<|>=|<=|<>)/is', $id_val)) {
                $where .= " and {$id_key} {$id_val}";
                $id_val = '0';
            }
        } else {
            $id_val = "\${$id_key}";
        }

        if ($where && $where != '1=1') { //解析变量
            $where = str_replace('!=', '<>', $where);
            preg_match_all('/([\$:][\w\.]+)/', $where, $matches);
            if (isset($matches[1]) && count($matches[1]) > 0) {
                $keys = [];
                $replace = [];
                foreach ($matches[1] as $match) {
                    $keys[] = $match;
                    if (strpos($match, '.')) {
                        $vars  = explode('.', $match);
                        $first = array_shift($vars);
                        $replace[] = '\'" . ' . $first . '[\'' . implode('\'][\'', $vars) . '\']' . ' . "\'';
                    } else {
                        $replace[] = '\'" . ' . $match . ' . "\'';
                    }
                }
                $where = str_replace($keys, $replace, $where);
            }
        }
        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);
        $dbNameSpace = class_exists(\think\facade\Db::class) ? '\think\facade\Db' : '\think\Db';

        $parseStr = <<<EOT
        <?php
        
        \$__where_exp__ = "{$where}";
        \$__where__ = [];
        \$__id_key__ = '{$id_key}';
        \$__id_val__ = {$id_val} ?? 0;

        if(empty(\$__where_exp__) || \$__where_exp__ == '1=1')
        {
            \$__where__[] = [\$__id_key__, '=', \$__id_val__];
        }

        \$__detail__ = {$dbNameSpace}::name('{$table}')
            ->where(\$__where__)
            ->where(\$__where_exp__)
            ->where('{$scope}')
            ->order('{$order}')
            ->field('{$fields}')
            ->find();
        
        \$__detail__ = \\tpext\\cms\\common\\taglib\\Processer::detail('{$table}', \$__detail__);
        ?>

        {assign name="{$assign}" value="\$__detail__" /}
        {notempty name="{$assign}"}
        {$content}
        {/notempty}

        <?php
        unset(\$__where_exp__, \$__where__, \$__id_key__, \$__id_val__);
        ?>

EOT;
        return $parseStr;
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
                // $tags[$info['tag_name'] . '@arounds'] = ['attr' => $listAttr];
                // $tags[$info['tag_name'] . '@prev'] = ['attr' => $getAttr, 'close' => 0];
                // $tags[$info['tag_name'] . '@next'] = ['attr' => $getAttr, 'close' => 0];
                // $tags[$info['tag_name'] . '@parent'] = ['attr' => $parentsAttr];

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
                    return $this->tagDetail($tag, $content);
                }
                if ($info['tag_name'] . '@prev' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['default_assign'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    $where = $tag['where'] ?? '1=1';
                    $id = $tag[$tag['id_key']] ?? '$id';
                    $order = $tag['order'] ?? Table::defaultOrder($table);
                    $sort = $tag['sort'] ?? $id;

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
                    $cmp = $isDesc ? '>=' : '<=';
                    $tag['where'] = "{$tag['id_key']} != {$id} and {$first} {$cmp} {$sort} and " . $where;

                    trace($tag['where']) ;
                    $tag['order']  = implode(',', $fields);
                    $tag[$tag['id_key']] = '';
                    return $this->tagDetail($tag, $content);
                }
                if ($info['tag_name'] . '@next' == $tagName) {
                    $tag['table'] = $table;
                    $tag['tag_name'] = $tagName;
                    $tag['default_assign'] = $tagArr[0];
                    $tag['id_key'] = $info['id_key'] ?? 'id';
                    $where = $tag['where'] ?? '1=1';
                    $id = $tag[$tag['id_key']] ?? '$id';
                    $order = $tag['order'] ?? Table::defaultOrder($table);
                    $sort = $tag['sort'] ?? $id;
                    $orders = explode(',', $order);
                    $first = preg_replace('/\s*(?:desc|asc)/', '', $orders[0]);
                    $isDesc = stripos($orders[0], 'desc') !== false;
                    $cmp = $isDesc ? '<=' : '>=';
                    $tag['where'] = "{$tag['id_key']} != {$id} and {$first} {$cmp} {$sort} and " . $where;
                    $tag['order']  = $order;
                    $tag[$tag['id_key']] = '';
                    return $this->tagDetail($tag, $content);
                }
            }
            throw new Exception("未知标签：{$tagName}");
        }

        throw new Exception('Call to undefined method : ' . $name);
    }
}
