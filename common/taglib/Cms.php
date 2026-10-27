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

    const IN_VAR_PAT = '/^in\s*\(?\s*(\$[a-zA-Z_][a-zA-Z_\d]*(?:\.[a-zA-Z_][a-zA-Z_\d]*)*)\s*\)?$/is';

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
        //num/pagesize/simple 同模式：变量或方法编译为运行时取值+类型兜底，字面量编译期定型
        $pagesizeExpr = $this->isVarOrMethod($pagesize) ? "intval(" . trim($pagesize, ':') . " ?? 0)" : intval($pagesize);
        $takeExpr = $this->isVarOrMethod($take) ? "intval(" . trim($take, ':') . " ?? 0)" : intval($take);
        $item = !empty($tag['item']) ? $tag['item'] : ($tag['default_item'] ?? 'item');
        $assign = !empty($tag['assign']) ? $tag['assign'] : $table . '_list_' . time() . mt_rand(100, 999);
        $item = ltrim($item, '$');
        $assign = ltrim($assign, '$');
        [$cacheKey, $cacheTime] = $this->parseCache($tag['cache'] ?? '');
        $tagOrder = !empty($tag['order']) ? $tag['order'] : Table::defaultOrder($table);
        $orderExpr = $this->parseOrder($tagOrder, $table);
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $simple = $tag['simple'] ?? 'false';
        $simpleExpr = $this->isVarOrMethod($simple)
            ? "(bool)(" . trim($simple, ':') . " ?? false)"
            : (in_array(strtolower($simple), ['1', 'true'], true) ? 'true' : 'false');
        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);
        $links = true;
        if (isset($tag['links']) && ($tag['links'] == '0' || $tag['links'] == 'false' || $tag['links'] == 'n' || $tag['links'] == 'no')) {
            $links = false;
        }

        $parseStr = $this->bindKeyValWhere($tag, 'list', $table);

        $parseStr .= <<<EOT
        \$__data__ = cms_query_list(
            '{$table}', \$__where__, '{$scope}', '{$fields}', {$orderExpr}, {$takeExpr}, {$pagesizeExpr}, {$cacheKey}, {$cacheTime}, {$simpleExpr}, '{$links}', \$vars
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
            $id_val = '0';
        }

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
        [$cacheKey, $cacheTime] = $this->parseCache($tag['cache'] ?? '');
        $order = $tag['order'] ?? '';
        $orderExpr = $this->parseOrder($order, $table);
        $fields = $tag['fields'] ?? Table::defaultFields($table);
        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $scope = Table::defaultScope($table);

        $parseStr = $this->bindKeyValWhere($tag, 'get', $table);

        $parseStr .= <<<EOT
        \$__detail__ = cms_query_detail(
            '{$table}', \$__where__, '{$scope}', {$orderExpr}, '{$fields}', {$cacheKey}, {$cacheTime}, \$vars
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
     * 约定：全大写的"函数名(...)"视为MySQL函数而非php方法，如FIND_IN_SET(...)、DATE_FORMAT(...)，
     *      返回false，将作为sql条件解析(变量转为绑定参数)后经whereRaw执行；
     *      小写或驼峰的"函数名(...)"视为php方法调用，如getWhere()、getPageFilter()。
     *
     * @param string $str
     * @return bool
     */
    protected function isVarOrMethod($str)
    {
        if (preg_match('/^\s*\$[a-zA-Z_][a-zA-Z_\d]*\s*$/', $str)) {
            return true;
        }

        if (preg_match('/^\s*:?[a-zA-Z_\\\\:]+\([^\(\)]*?\)\s*$/', $str)) {
            //全大写函数名按MySQL函数处理；其余(含命名空间\xx\yy::zz())按php方法处理
            if (preg_match('/^\s*:?[A-Z][A-Z_\d]*\(/', $str)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * order参数支持变量或方法，如order="$order"、order="getOrder()"
     *
     * @param string $order
     * @return string
     */
    protected function parseOrder($order, $table = '')
    {
        $order = trim($order);
        if ($this->isVarOrMethod($order)) {
            $order = ltrim($order, ':');
            if ($order[0] == '$') {
                if (preg_match('/^\$([\w]+)/', $order, $m)) {
                    //变量按来源分流：值与GET包内同名值全等→字段白名单校验(cms_order_var)，其余来源框架parseOrder白名单把关
                    return "cms_order_var('{$table}', '{$m[1]}', (" . $order . " ?? ''), \$__get__ ?? [])";
                }
                return '(' . $order . " ?? '')";
            }
            //方法：返回值与GET包比对(全等或包含)后分流，其余原样透传由框架字段白名单把关
            return "cms_order_var('{$table}', '', (" . $order . " ?? ''), \$__get__ ?? [])";
        }
        if (preg_match('/^[A-Z][A-Z_\d]*\s*\([^\(\)]*\)$/', $order)) {
            //全大写MySQL函数(如FIELD(sort,1,2,3))：经Expression透传，内嵌变量按来源分流(cms_order_expr_var)
            $parts = preg_split('/(\$[a-zA-Z_][a-zA-Z_\d]*(?:\.[a-zA-Z_][a-zA-Z_\d]*)?)/', $order, -1, PREG_SPLIT_DELIM_CAPTURE);
            $expr = '';
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if ($part[0] == '$') {
                    preg_match('/^\$([\w]+)/', $part, $pm);
                    $expr .= " . cms_order_expr_var('{$pm[1]}', (" . $this->autoBuildVar($part) . " ?? ''), \$__get__ ?? []) . ";
                } else {
                    $expr .= " . '" . addslashes($part) . "'";
                }
            }
            return 'cms_order_expr(' . substr($expr, 3) . ')';
        }
        return "'" . addslashes($order) . "'";
    }

    /**
     * 解析cache属性：cache="key,秒数"。
     * key支持内嵌简单变量，运行时把变量值拼进缓存键，如cache="key$a"、cache="list_$id_p$page"，
     * 用于where/分页依赖模板变量的标签，让不同参数各自缓存；不支持{$a}大括号写法和方法调用；
     * 无变量时行为与原先一致。
     *
     * @param string $cache
     * @return array [$cacheKey, $cacheTime] $cacheKey为编译后的php表达式字符串或false
     */
    protected function parseCache($cache)
    {
        $cache = explode(',', $cache ?? '');
        $cacheTime = intval($cache[1] ?? 360);
        $raw = trim($cache[0] ?? '');

        if ($raw === '') {
            return ['false', $cacheTime];
        }

        if (!preg_match('/\$[a-zA-Z]/', $raw)) {
            return ["'" . addslashes($raw) . "'", $cacheTime];
        }

        //key内嵌变量：拆分为 字面量 与 ($var ?? '') 的拼接表达式；变量名不允许下划线开头(排除$_GET等超全局)
        $parts = preg_split('/(\$[a-zA-Z][a-zA-Z_\d]*)/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
        $expr = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] == '$') {
                $expr .= " . (" . $part . " ?? '')";
            } else {
                $expr .= " . '" . addslashes($part) . "'";
            }
        }

        return [substr($expr, 3), $cacheTime];
    }

    /**
     * 解析标签中的 cid_key, id_key, where 等参数
     *
     * @param mixed $tag
     * @param string $type
     * @param string $table
     * @return string
     */
    protected function bindKeyValWhere($tag, $type, $table = '')
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
            } else if (preg_match(self::IN_VAR_PAT, $cid_val, $mch)) {
                $cid_val = $this->autoBuildVar($mch[1]);
            } else {
                $whereExp = $this->parseIdVal($cid_key, $cid_val);
                if ($whereExp) {
                    if ($where != '1=1' && $where !== '') {
                        $where = '(' . $where . ')';
                    }
                    $where .= $whereExp;
                    $cid_val = '0';
                }
            }
        } else if ($cid_key && $type == 'list') {
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
            } else if (preg_match(self::IN_VAR_PAT, $id_val, $mch)) {
                $id_val = $this->autoBuildVar($mch[1]);
            } else {
                $whereExp = $this->parseIdVal($id_key, $id_val);
                if ($whereExp) {
                    if ($where != '1=1' && $where !== '') {
                        $where = '(' . $where . ')';
                    }
                    $where .= $whereExp;
                    $id_val = '0';
                }
            }
            if ($type == 'list') {
                $cid_val = '0';
            }
        } else if ($type == 'get' && $cid_val === '0' && ($where == '1=1' || empty($where))) {
            $id_val = "\${$id_key} ?? 0";
        } else {
            $id_val = '0';
        }

        $binds = '';
        $whereVar = '[]';
        $whereRaw = '1=1';
        if ($where && $where != '1=1') {
            if ($this->isVarOrMethod($where)) {
                //where整体是一个变量或方法(如$where)，直接把变量/返回值作为查询条件(支持字符串或数组)，按来源分流防护
                $whereVar = ltrim($where, ':');
                if (preg_match('/^\$([\w]+)/', $whereVar, $m)) {
                    //变量：值与GET包内同名值全等→语法白名单+绑定，模板覆盖值→sql_guard兜底
                    $whereVar = "cms_where_var('{$table}', '{$m[1]}', ({$whereVar} ?? null), \$__get__ ?? [])";
                } else {
                    //方法：返回值与GET包比对(全等或包含)后分流，其余sql_guard兜底
                    $whereVar = "cms_where_var('{$table}', '', {$whereVar}, \$__get__ ?? [])";
                }
            } else {
                [$whereRaw, $binds] = $this->parseWhere($where, $cid_key); //解析变量
            }
        }

        $parseStr = <<<EOT

        <?php
        \$__where__ = cms_build_where('{$cid_key}', {$cid_val}, '{$id_key}', {$id_val});
        \$__where_var__ = {$whereVar};
        \$__where_raw__ = "{$whereRaw}";
        \$__where_binds__ = [{$binds}];
        \$__where__ = function (\$query) use (\$__where__, \$__where_var__, \$__where_raw__, \$__where_binds__) {
            \$query->where(\$__where__);
            if (is_array(\$__where_var__) && isset(\$__where_var__['__raw__'])) {
                \$query->whereRaw(\$__where_var__['__raw__'], \$__where_var__['__binds__']);
            } else {
                \$query->where(\$__where_var__);
            }
            \$query->whereRaw(\$__where_raw__, \$__where_binds__);
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
            '/([\'\"])?(\%?)(\$[a-zA-Z_][a-zA-Z_\.\[\]\'\"]*)(\%?)\1?/',
            function ($m) use (&$binds) {
                $match = $m[3];
                $last = substr($match, -1);
                if ($last == "'" || $last == '"') {
                    //变量模式包含引号字符，结尾的闭合引号会被误吞(如'$kwd')，剥离
                    $match = substr($match, 0, -1);
                }
                $varName = preg_replace('/\W/is', '_', $match) . count($binds);

                if (strpos($match, '.') !== false) {
                    $bind = preg_replace('/\.([a-zA-Z_][a-zA-Z_0-9]*)/', '[\'\1\']', $match);
                } else {
                    $bind = $match;
                }

                //紧贴变量的百分号(含引号内的'%$var%'写法)拼接为模糊匹配
                if ($m[2] == '%') {
                    $bind = "'%' . {$bind}";
                }
                if ($m[4] == '%') {
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
        //替换where中的cid语法糖为真实字段(词操作符后补空格，避免粘成 channel_idgt)
        if ($cid_key && stripos($where, 'cid') !== false) {
            $where = preg_replace('/(\bcid\s+)(gt|eq|lt|egt|elt|neq|not\s*in|like|not\s*like|between|not\s*between)\b/is', $cid_key . ' $2', $where);
            $where = preg_replace('/(\bcid\s*)(\<|\>|=|!=)/is', $cid_key . '$2', $where);
        }
        if ($cid_key == 'parent_id' && stripos($where, 'pid') !== false) {
            $where = preg_replace('/(\bpid\s+)(gt|eq|lt|egt|elt|neq|not\s*in|like|not\s*like|between|not\s*between)\b/is', $cid_key . ' $2', $where);
            $where = preg_replace('/(\bpid\s*)(\<|\>|=|!=)/is', $cid_key . '$2', $where);
        }
        //替换表达式：引号内的值不替换，引号外仅替换带词边界的语法糖词，
        //避免误伤字段名或值中恰好包含的子串(如seq字段、'%neq%'值)
        if (preg_match('/\b(?:egt|elt|neq|notbetween|notin|notlike|gt|eq|lt)\b|!=/i', $where)) {
            $parts = preg_split('/([\'"][^\'"]*[\'"])/', $where, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $i => $part) {
                if ($i % 2 == 1 || $part === '') {
                    continue; //引号内的部分
                }
                $part = preg_replace(
                    ['/\begt\b/i', '/\belt\b/i', '/\bneq\b/i', '/\bnotbetween\b/i', '/\bnotin\b/i', '/\bnotlike\b/i', '/\bgt\b/i', '/\beq\b/i', '/\blt\b/i'],
                    ['>=', '<=', '<>', 'not between', 'not in', 'not like', '>', '=', '<'],
                    $part
                );
                $parts[$i] = str_replace('!=', '<>', $part);
            }
            $where = implode('', $parts);
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

        //解析排序列：列名 => 是否倒序
        $cols = [];
        foreach (explode(',', $order) as $sod) {
            $sod = trim($sod);
            if ($sod === '') {
                continue;
            }
            $desc = preg_match('/\s+desc\s*$/i', $sod) ? true : false;
            $name = trim(preg_replace('/\s+(?:desc|asc)$/i', '', $sod));
            if ($name !== '' && !isset($cols[$name])) {
                $cols[$name] = $desc;
            }
        }

        //末尾补充id列兜底，保证排序唯一，排序列值并列时前后篇仍能正确导航
        if (!isset($cols[$tag['id_key']])) {
            $cols[$tag['id_key']] = true;
        }

        //查询排序：上一篇反转各列方向
        $fields = [];
        foreach ($cols as $name => $desc) {
            $fields[] = $name . (($isPrev xor !$desc) ? ' asc' : ' desc');
        }
        $tag['order'] = implode(',', $fields);

        //按全部排序列构建元组比较：col1比较 or (col1相等 and col2比较) or ...
        $alias = "\${$tagArr[0]}";
        $i = 0;
        $tuple = [];
        $eqChain = '';
        foreach ($cols as $name => $desc) {
            $val = ($i == 0 && isset($tag['sort'])) ? $tag['sort'] : $alias . '.' . $name;
            $cmp = ($isPrev == $desc) ? '>' : '<'; //desc列上一篇取更大值/下一篇取更小值，asc列相反
            $tuple[] = $eqChain . "{$name} {$cmp} {$val}";
            $eqChain .= "{$name} = {$val} and ";
            $i++;
        }

        $tag['where'] = "{$tag['id_key']} != {$id} and (" . implode(' or ', $tuple) . ") and " . $where;
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
