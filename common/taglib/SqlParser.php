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

/**
 * SQL条件/排序白名单解析器：GET来源整体变量where/order的结构化防护。
 * 手写词法器 + 递归下降，token仅ident/number/string/比较符/括号/逗号，
 * 注释符、分号、反引号等不属于任何token，词法层天然拒绝（无黑名单）；
 * 字段须为表真实列，右值一律绑定参数；不合语法整体拒绝（fail-closed）。
 */
class SqlParser
{
    /**
     * GET来源整体变量where的语法白名单解析（fail-closed）：
     * 合法形态 = 字段 (比较符|like|in) 值 的 and/or 与括号组合，字段须在表内，右值一律绑定参数(:wv_N)；
     * 任何不符合语法的输入返回null，由调用方作中性回退（恒假条件），不存在部分解析。
     * 语法（嵌套上限10层；同级混用and/or须加括号）：
     *   expr  := term (('and'|'or') term)*
     *   term  := '(' expr ')' | cond
     *   cond  := field op value | field ('not')? 'in' '(' value (',' value)* ')' | field ('not')? 'like' value
     *
     * @param string $table
     * @param string $str
     * @return array|null [sql, binds]
     */
    public static function parseWhereString($table, $str)
    {
        try {
            $fields = static::getTableFields($table);
            $tokens = static::lexSql($str);
            if (empty($tokens)) {
                throw new \RuntimeException('空条件');
            }
            $binds = [];
            $i = 0;
            $sql = static::wvExpr($tokens, $i, $fields, $binds, 0);
            if ($i < count($tokens)) {
                throw new \RuntimeException("多余的'{$tokens[$i]['text']}'");
            }
            return [$sql, $binds];
        } catch (\RuntimeException $e) {
            trace('cms_where_var拒绝: ' . $e->getMessage() . ' | ' . mb_substr((string) $str, 0, 80), 'error');
            return null;
        }
    }

    /**
     * GET来源整体变量order的语法白名单：字段 [asc|desc](,字段 [asc|desc])*，字段须在表内
     *
     * @param string $table
     * @param string $str
     * @return string|null 规范化排序串
     */
    public static function parseOrderString($table, $str)
    {
        try {
            $fields = static::getTableFields($table);
            $tokens = static::lexSql($str);
            if (empty($tokens)) {
                throw new \RuntimeException('空排序');
            }
            $items = [];
            $n = count($tokens);
            while (true) {
                $t = $tokens[0];
                if ($t['kind'] !== 'ident' || in_array(strtolower($t['text']), ['and', 'or', 'in', 'like', 'not', 'asc', 'desc'])) {
                    throw new \RuntimeException("排序项应为字段名:'{$t['text']}'");
                }
                $field = strtolower($t['text']);
                if (!in_array($field, $fields)) {
                    throw new \RuntimeException("未知字段'{$t['text']}'");
                }
                array_shift($tokens);
                $dir = '';
                if (isset($tokens[0]) && $tokens[0]['kind'] === 'ident' && in_array(strtolower($tokens[0]['text']), ['asc', 'desc'])) {
                    $dir = ' ' . strtolower($tokens[0]['text']);
                    array_shift($tokens);
                }
                $items[] = $field . $dir;

                if (empty($tokens)) {
                    break;
                }
                $c = array_shift($tokens);
                if ($c['kind'] !== 'comma') {
                    throw new \RuntimeException("排序项之间应为逗号:'{$c['text']}'");
                }
                if (empty($tokens)) {
                    throw new \RuntimeException('逗号后缺少排序项');
                }
            }
            return implode(', ', $items);
        } catch (\RuntimeException $e) {
            trace('cms_order_var拒绝: ' . $e->getMessage() . ' | ' . mb_substr((string) $str, 0, 80), 'error');
            return null;
        }
    }

    /**
     * 表字段清单（小写），进程内缓存；取不到（表不存在等）返回空表 → 一切字段判定失败
     *
     * @param string $table
     * @return array
     */
    protected static function getTableFields($table)
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $db = Processer::getDbNamespace();
                $cache[$table] = array_map('strtolower', $db::name($table)->getTableFields() ?: []);
            } catch (\Throwable $e) {
                $cache[$table] = [];
            }
        }
        return $cache[$table];
    }

    /**
     * SQL条件词法器：token仅 ident/number/string/op/lparen/rparen/comma；
     * 注释符、分号、引号外%等攻击字符不属于任何token，直接词法拒绝（白名单词法，无需黑名单）
     *
     * @param string $input
     * @return array
     */
    protected static function lexSql($input)
    {
        $tokens = [];
        $len = strlen($input);
        $i = 0;
        while ($i < $len) {
            $c = $input[$i];

            if ($c === ' ' || $c === "\t" || $c === "\r" || $c === "\n") {
                $i++;
                continue;
            }

            if (preg_match('/[A-Za-z_]/', $c)) {
                $j = $i;
                while ($j < $len && preg_match('/[A-Za-z0-9_]/', $input[$j])) {
                    $j++;
                }
                $tokens[] = ['kind' => 'ident', 'text' => substr($input, $i, $j - $i)];
                $i = $j;
                continue;
            }

            if (preg_match('/\d/', $c)) {
                $j = $i;
                while ($j < $len && preg_match('/\d/', $input[$j])) {
                    $j++;
                }
                if ($j + 1 < $len && $input[$j] === '.' && preg_match('/\d/', $input[$j + 1])) {
                    $j++;
                    while ($j < $len && preg_match('/\d/', $input[$j])) {
                        $j++;
                    }
                }
                $tokens[] = ['kind' => 'number', 'text' => substr($input, $i, $j - $i)];
                $i = $j;
                continue;
            }

            if ($c === "'" || $c === '"') {
                $quote = $c;
                $j = $i + 1;
                $buf = '';
                $closed = false;
                while ($j < $len) {
                    $ch = $input[$j];
                    if ($ch === $quote) {
                        if ($j + 1 < $len && $input[$j + 1] === $quote) {
                            $buf .= $quote;
                            $j += 2;
                            continue;
                        }
                        $closed = true;
                        $j++;
                        break;
                    }
                    $buf .= $ch;
                    $j++;
                }
                if (!$closed) {
                    throw new \RuntimeException('引号未闭合');
                }
                $tokens[] = ['kind' => 'string', 'text' => $buf];
                $i = $j;
                continue;
            }

            $two = substr($input, $i, 2);
            if ($two === '>=' || $two === '<=' || $two === '!=' || $two === '<>') {
                $tokens[] = ['kind' => 'op', 'text' => $two];
                $i += 2;
                continue;
            }
            if ($c === '=' || $c === '>' || $c === '<') {
                $tokens[] = ['kind' => 'op', 'text' => $c];
                $i++;
                continue;
            }
            if ($c === '(') {
                $tokens[] = ['kind' => 'lparen', 'text' => '('];
                $i++;
                continue;
            }
            if ($c === ')') {
                $tokens[] = ['kind' => 'rparen', 'text' => ')'];
                $i++;
                continue;
            }
            if ($c === ',') {
                $tokens[] = ['kind' => 'comma', 'text' => ','];
                $i++;
                continue;
            }

            throw new \RuntimeException("非法字符'{$c}'");
        }
        return $tokens;
    }

    private static function wvExpr(array &$tokens, int &$i, array $fields, array &$binds, int $depth)
    {
        $parts = [static::wvTerm($tokens, $i, $fields, $binds, $depth)];
        $logics = [];
        while (isset($tokens[$i]) && $tokens[$i]['kind'] === 'ident' && in_array(strtolower($tokens[$i]['text']), ['and', 'or'])) {
            $logics[] = strtolower($tokens[$i]['text']);
            $parts[] = strtolower($tokens[$i]['text']);
            $i++;
            $parts[] = static::wvTerm($tokens, $i, $fields, $binds, $depth);
        }
        if (count(array_unique($logics)) > 1) {
            throw new \RuntimeException('同级混用and/or，请用括号分组');
        }
        return implode(' ', $parts);
    }

    private static function wvTerm(array &$tokens, int &$i, array $fields, array &$binds, int $depth)
    {
        if ($depth >= 10) {
            throw new \RuntimeException('嵌套超过10层');
        }
        if (!isset($tokens[$i])) {
            throw new \RuntimeException('条件不完整');
        }
        if ($tokens[$i]['kind'] === 'lparen') {
            $i++;
            $sql = static::wvExpr($tokens, $i, $fields, $binds, $depth + 1);
            if (!isset($tokens[$i]) || $tokens[$i]['kind'] !== 'rparen') {
                throw new \RuntimeException('括号未闭合');
            }
            $i++;
            return '(' . $sql . ')';
        }
        return static::wvCond($tokens, $i, $fields, $binds);
    }

    private static function wvCond(array &$tokens, int &$i, array $fields, array &$binds)
    {
        if (!isset($tokens[$i]) || $tokens[$i]['kind'] !== 'ident') {
            $t = $tokens[$i]['text'] ?? '';
            throw new \RuntimeException("应为字段名:'{$t}'");
        }
        $raw = $tokens[$i]['text'];
        $field = strtolower($raw);
        $i++;
        if (in_array($field, ['and', 'or', 'in', 'like', 'not'])) {
            throw new \RuntimeException("关键字'{$raw}'不能作字段名");
        }
        if (!in_array($field, $fields)) {
            throw new \RuntimeException("未知字段'{$raw}'");
        }

        if (isset($tokens[$i]) && $tokens[$i]['kind'] === 'ident' && strtolower($tokens[$i]['text']) === 'not') {
            $i++;
            if (!isset($tokens[$i]) || $tokens[$i]['kind'] !== 'ident' || !in_array(strtolower($tokens[$i]['text']), ['in', 'like'])) {
                throw new \RuntimeException('not后只支持in/like');
            }
            $kw = strtolower($tokens[$i]['text']);
            $i++;
            return static::wvInLike($field, $kw, true, $tokens, $i, $binds);
        }
        if (isset($tokens[$i]) && $tokens[$i]['kind'] === 'ident' && in_array(strtolower($tokens[$i]['text']), ['in', 'like'])) {
            $kw = strtolower($tokens[$i]['text']);
            $i++;
            return static::wvInLike($field, $kw, false, $tokens, $i, $binds);
        }

        if (!isset($tokens[$i]) || $tokens[$i]['kind'] !== 'op') {
            $t = $tokens[$i]['text'] ?? '';
            throw new \RuntimeException("应为操作符:'{$t}'");
        }
        $op = $tokens[$i]['text'];
        $i++;
        return $field . ' ' . $op . ' ' . static::wvValue($tokens, $i, $binds);
    }

    private static function wvInLike($field, $kw, $not, array &$tokens, int &$i, array &$binds)
    {
        if ($kw === 'like') {
            return $field . ($not ? ' not' : '') . ' like ' . static::wvValue($tokens, $i, $binds);
        }

        if (!isset($tokens[$i]) || $tokens[$i]['kind'] !== 'lparen') {
            throw new \RuntimeException("in后应为'('");
        }
        $i++;
        $bindsList = [static::wvValue($tokens, $i, $binds)];
        while (isset($tokens[$i]) && $tokens[$i]['kind'] === 'comma') {
            $i++;
            $bindsList[] = static::wvValue($tokens, $i, $binds);
        }
        if (!isset($tokens[$i]) || $tokens[$i]['kind'] !== 'rparen') {
            $t = $tokens[$i]['text'] ?? '';
            throw new \RuntimeException("in列表应为值列表:'{$t}'");
        }
        $i++;
        return $field . ($not ? ' not' : '') . ' in (' . implode(', ', $bindsList) . ')';
    }

    private static function wvValue(array &$tokens, int &$i, array &$binds)
    {
        if (!isset($tokens[$i]) || ($tokens[$i]['kind'] !== 'string' && $tokens[$i]['kind'] !== 'number')) {
            $t = $tokens[$i]['text'] ?? '';
            throw new \RuntimeException("应为值:'{$t}'");
        }
        $t = $tokens[$i];
        $i++;
        $name = 'wv_' . count($binds);
        $binds[$name] = $t['kind'] === 'number' ? (strpos($t['text'], '.') !== false ? (float) $t['text'] : (int) $t['text']) : $t['text'];
        return ':' . $name;
    }

}
