<?php

use think\paginator\driver\Bootstrap;
use tpext\builder\logic\ImageHandler;
use tpext\cms\common\taglib\Processer;
use tpext\cms\common\taglib\SqlParser;
use tpext\cms\common\taglib\Table;
use tpext\think\App;

function get_channel($id)
{
    $table = 'cms_channel';
    $dbNameSpace = Processer::getDbNamespace();
    $scope = Table::defaultScope($table);
    $item = $dbNameSpace::name($table)->where('id', $id)
        ->where($scope)
        ->cache('cms_channel_' . $id, 3600 * 24 * 7, $table)
        ->find();

    return Processer::detail($table, $item);
}

function get_content($id)
{
    $table = 'cms_content';
    $dbNameSpace = Processer::getDbNamespace();
    $scope = Table::defaultScope($table);
    $item = $dbNameSpace::name($table)
        ->where('id', $id)
        ->where($scope)
        ->cache('cms_content_' . $id, 3600 * 24 * 7, $table)
        ->find();

    return Processer::detail($table, $item);
}

function get_banner($id)
{
    $table = 'cms_banner';
    $dbNameSpace = Processer::getDbNamespace();
    $scope = Table::defaultScope($table);
    $item = $dbNameSpace::name($table)
        ->where('id', $id)
        ->where($scope)
        ->cache('cms_banner_' . $id, 3600 * 24 * 7, $table)
        ->find();

    return Processer::detail($table, $item);
}

function get_tag($id)
{
    $table = 'cms_tag';
    $dbNameSpace = Processer::getDbNamespace();
    $scope = Table::defaultScope($table);
    $item = $dbNameSpace::name($table)
        ->where('id', $id)
        ->where($scope)
        ->cache('cms_tag_' . $id, 3600 * 24 * 7, $table)
        ->find();

    return Processer::detail($table, $item);
}

function channel_url($item)
{
    if (is_numeric($item)) {
        $item = get_channel($item);
    }

    return $item['url'];
}

function content_url($item)
{
    if (is_numeric($item)) {
        $item = get_content($item);
    }

    return $item['url'];
}

function banner_url($item)
{
    if (is_numeric($item)) {
        $item = get_banner($item);
    }

    return $item['url'];
}

function tag_url($item)
{
    if (is_numeric($item)) {
        $item = get_tag($item);
    }

    return $item['url'];
}

if (!function_exists('sql_guard')) {

    function sql_guard($val)
    {
        return Processer::sqlGuard($val);
    }
}

if (!function_exists('cms_order_expr')) {

    //order中全大写MySQL函数的透传载体：TP5.1/think-orm为Expression，webman新版为Raw，均无则原样返回
    function cms_order_expr($expr)
    {
        if (class_exists('\think\db\Expression')) {
            return new \think\db\Expression($expr);
        }
        if (class_exists('\think\db\Raw')) {
            return new \think\db\Raw($expr);
        }
        return $expr;
    }
}

if (!function_exists('cms_where_var')) {

    /**
     * where整体变量/方法按来源分流：变量值与$__get__包内同名值全等(未经模板改动)→GET来源，
     * 语法白名单解析+值绑定，失败返回恒假中性条件；值不同→模板已覆盖，模板值优先走sql_guard并trace提示命名冲突。
     * 方法($name为空串)无法定位参数名，与整个包比对：全等某值→GET路径；包含某值且能整体白名单解析→等价改写为绑定形式
     * (同字段同值，结构安全)；包含但解析不了(可能是作者复杂条件，不中性化误伤)或不包含→作者路径sql_guard。
     * 返回['__raw__'=>sql,'__binds__'=>binds]由编译产物转whereRaw，或原值(字符串/数组)走where()。
     */
    function cms_where_var($table, $name, $value, $get)
    {
        $get = is_array($get) ? $get : [];
        $gv = $name !== '' ? ($get[$name] ?? null) : null;

        if ($name === '' && is_string($value) && $value !== '') {
            if (in_array($value, $get, true)) {
                $gv = $value; //方法原样回传GET参数
            } else {
                foreach ($get as $v) {
                    if (is_string($v) && $v !== '' && strpos($value, $v) !== false) {
                        $parsed = SqlParser::parseWhereString($table, $value);
                        if ($parsed !== null) {
                            return ['__raw__' => $parsed[0], '__binds__' => $parsed[1]];
                        }
                        break;
                    }
                }
            }
        }

        if ($gv !== null && ($value === null || $value === $gv)) {
            $parsed = SqlParser::parseWhereString($table, (string) $gv);
            if ($parsed === null) {
                return ['__raw__' => '1=0', '__binds__' => []];
            }
            return ['__raw__' => $parsed[0], '__binds__' => $parsed[1]];
        }
        if ($gv !== null && $value !== null && $value !== $gv) {
            trace("where变量\${$name}与GET参数同名，模板值优先", 'error');
        }
        if (!is_string($value)) {
            return is_array($value) ? $value : '';
        }
        return sql_guard($value);
    }
}

if (!function_exists('cms_order_var')) {

    /**
     * order整体变量/方法按来源分流：变量值与$__get__包内同名值全等(未经模板改动)→GET来源，
     * 字段白名单校验(字段 [asc|desc]列表)，不合法回退默认排序(方法回传非法串不再触发框架抛异常)；
     * 值不同→模板值优先原样返回，由框架Builder::parseOrder字段白名单把关。
     * 方法($name为空串)与整个包比对：全等→GET路径；包含某值且能字段白名单解析→等价改写；否则原样透传。
     */
    function cms_order_var($table, $name, $value, $get)
    {
        $get = is_array($get) ? $get : [];
        $gv = $name !== '' ? ($get[$name] ?? null) : null;

        if ($name === '' && is_string($value) && $value !== '') {
            if (in_array($value, $get, true)) {
                $gv = $value; //方法原样回传GET参数
            } else {
                foreach ($get as $v) {
                    if (is_string($v) && $v !== '' && strpos($value, $v) !== false) {
                        $parsed = SqlParser::parseOrderString($table, $value);
                        if ($parsed !== null) {
                            return $parsed;
                        }
                        break;
                    }
                }
            }
        }

        if ($gv !== null && ($value === null || $value === $gv)) {
            $parsed = SqlParser::parseOrderString($table, (string) $gv);
            if ($parsed === null) {
                return Table::defaultOrder($table);
            }
            return $parsed;
        }
        if ($gv !== null && $value !== null && $value !== $gv) {
            trace("order变量\${$name}与GET参数同名，模板值优先", 'error');
        }
        return $value;
    }
}

if (!function_exists('cms_order_expr_var')) {

    /**
     * order函数表达式(如FIELD($x,1,2))内嵌变量按来源分流：变量值与$__get__包内同名值全等→GET来源，
     * 只放行标识符/数字/逗号列表形态，否则中性为0(保持SQL合法)；值不同→模板值优先走sql_guard。
     */
    function cms_order_expr_var($name, $value, $get)
    {
        $gv = is_array($get) ? ($get[$name] ?? null) : null;
        if ($gv !== null && $gv === $value) {
            if (is_string($gv) && preg_match('/^\w+(?:\s*,\s*\w+)*$/', $gv)) {
                return $gv;
            }
            trace('cms_order_expr_var拒绝GET值(' . $name . '): ' . mb_substr((string) $gv, 0, 50), 'error');
            return '0';
        }
        if ($gv !== null && $value !== $gv && $value !== '') {
            trace("order变量\${$name}与GET参数同名，模板值优先", 'error');
        }
        return sql_guard((string) $value);
    }
}

if (!function_exists('more')) {
    function more($str, $len = 100, $more = '...')
    {
        if (mb_strlen($str, 'utf-8') > $len) {
            return mb_substr($str, 0, $len, 'utf-8') . $more;
        } else {
            return $str;
        }
    }
}

if (!function_exists('thumb')) {
    function thumb($file, $width = 0, $height = 0)
    {
        if (empty($width) && empty($height)) {
            return $file;
        }

        $handler = new ImageHandler;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'bmp', 'webp'])) {
            return $file;
        }

        // gif 可能是动图，保留原格式；其余缩放后统一输出 webp（Intervention 按目标后缀自动选编码，v2/v3 行为一致）
        $thumbFile = '/thumb/' . md5($file) . '-' . $width . 'x' . $height . '.' . ($ext == 'gif' ? 'gif' : 'webp');

        if (is_file(App::getPublicPath() . $thumbFile)) {
            return $thumbFile;
        }

        if (strstr($file, 'http')) {
            $data = @file_get_contents($file);
            if (!$data) {
                trace('download error 0');
                return $file;
            }
            if (!@file_put_contents(App::getPublicPath() . $thumbFile, $data)) {
                return $file;
            }
            $file = $thumbFile;
        } else if (!is_file(App::getPublicPath() . $file)) {
            return $file;
        }

        if (!is_dir(App::getPublicPath() . '/thumb/')) {
            mkdir(App::getPublicPath() . '/thumb/', 0777, true);
        }

        $options = [
            'width' => $width ? $width : null,
            'height' => $height ? $height : null,
            'to_path' => App::getPublicPath() . $thumbFile,
        ];

        try {
            $handler->resize($file, $options);
        } catch (\Exception $e) {
            trace('thumb error:' . $e->getMessage());
            if (is_file(App::getPublicPath() . $thumbFile)) {
                @unlink(App::getPublicPath() . $thumbFile);
            }
            return $file;
        }

        //resize返回的是源文件路径，缩略图生成成功后统一返回缩略图路径(与缓存命中时一致)
        if (is_file(App::getPublicPath() . $thumbFile)) {
            return $thumbFile;
        }

        return $file;
    }
}

if (!function_exists('url_filter')) {
    function url_filter($params = [])
    {
        $get = request()->get();
        $params = array_merge($get, $params ?: []);
        $params = array_filter($params);
        return '?' . http_build_query($params);
    }
}

function cms_build_where($cidKey, $cidVal, $idKey, $idVal)
{
    $whereArr = [];

    if ($cidKey) {
        $cidVal = $cidVal ?? 0;
        if ($cidVal !== 0 && $cidVal !== '') {
            if (is_array($cidVal) || (is_string($cidVal) && strstr($cidVal, ','))) {
                $whereArr[] = [$cidKey, 'in', $cidVal];
            } else {
                $whereArr[] = [$cidKey, '=', $cidVal];
            }
        }
    }

    if ($idKey) {
        $idVal = $idVal ?? 0;
        if ($idVal !== 0 && $idVal !== '') {
            if (is_array($idVal) || (is_string($idVal) && strstr($idVal, ','))) {
                $whereArr[] = [$idKey, 'in', $idVal];
            } else {
                $whereArr[] = [$idKey, '=', $idVal];
            }
        }
    }

    return $whereArr;
}

function cms_get_parents($table, $idVal, $idKey, $pidKey, $vars)
{
    $idVal = $idVal ?? 0;
    if (empty($idVal)) {
        $pageType = $vars['__page_type__'] ?? '';
        if ($pageType == 'channel' || $pageType == 'content') {
            $idVal = $vars['channel_id'] ?? 0;
        }
    }
    return Processer::getParents($table, $idVal, $idKey, $pidKey);
}

function cms_query_list($table, $where, $scope, $fields, $tagOrder, $take, $pagesize, $cacheKey, $cacheTime, $simple, $links, $vars)
{
    $page = 1;
    $hasPaginator = false;
    $take = max(0, intval($take));
    $pagesize = max(0, intval($pagesize));
    $pagesize = $pagesize ?: ($vars['__set_pagesize__'] ?? 0);
    $mainList = false;
    if ($take == 0) {
        if ($pagesize > 0) {
            $page = isset($vars['page']) && intval($vars['page']) > 0 ? intval($vars['page']) : 1;
            $take = $pagesize;
            $hasPaginator = true;
        } else {
            $take = 10;
        }
        $mainList = true;
    }

    $orderBy = $tagOrder;
    if ($mainList && $table == 'cms_content' && !empty($vars['__set_order_by__'])) {
        $orderBy = $vars['__set_order_by__'] . $tagOrder;
    }

    $db = Processer::getDbNamespace();

    $list = $db::name($table)
        ->where($where)
        ->where($scope)
        ->field($fields)
        ->order($orderBy)
        ->limit(($page - 1) * $take, $take)
        ->cache($cacheKey ?: false, $cacheTime, $table . '_list')
        ->select();

    if ($list instanceof \think\Collection) {
        $list = $list->toArray();
    }

    $channel = $vars['channel'] ?? null;
    $extendTable = $channel ? ($channel['extend_table'] ?? '') : '';

    if ($extendTable) {
        foreach ($list as &$li) {
            if (empty($li['channel_id'])) {
                $li['channel_id'] = $channel['id'];
            }
        }
    }

    $list = Processer::list($extendTable && $extendTable == $table ? 'cms_content' : $table, $list);

    $linksHtml = '';
    if ($hasPaginator) {
        //count与列表同缓存(键名加_count后缀、同tag)，避免每次分页都打库
        $countCacheKey = $cacheKey ? $cacheKey . '_count' : false;
        $total = $db::name($table)
            ->where($where)
            ->where($scope)
            ->cache($countCacheKey, $cacheTime, $table . '_list')
            ->count('id');

        $simple = $simple ? true : false;
        if ($simple && count($list) == $pagesize) {
            $pagesize -= 1;//简单分页问题
        }
        $path = $vars['__set_page_path__'] ?? '';
        $paginator = new Bootstrap($list, $pagesize, $page, $total, $simple, ['path' => $path]);
        $linksHtml = $paginator->render();
    }

    return [
        '__list__' => $list,
        '__has_paginator__' => $hasPaginator,
        '__render_links__' => $links == '1',
        '__links_html__' => $linksHtml,
    ];
}

function cms_query_detail($table, $where, $scope, $order, $fields, $cacheKey, $cacheTime, $vars = [])
{
    $db = Processer::getDbNamespace();

    $detail = $db::name($table)
        ->where($where)
        ->where($scope)
        ->order($order)
        ->field($fields)
        ->cache($cacheKey ?: false, $cacheTime, $table . '_list')
        ->find();

    $channel = $vars['channel'] ?? null;
    $extendTable = $channel ? ($channel['extend_table'] ?? '') : '';

    if ($detail && $extendTable && $extendTable == $table && $extendTable != 'cms_content') {
        $detail['channel_id'] = $channel['id'];
        $detail['extend_table'] = $table;
        $table = 'cms_content';
    }

    return Processer::detail($table, $detail);
}

function cms_restore_page_vars($table, $vars)
{
    $restore = [];
    $pageType = $vars['__page_type__'] ?? '';
    if ($pageType == 'content' && $table == 'cms_content') {
        $restore['content'] = $vars['content'] ?? null;
    } elseif (($pageType == 'channel' || $pageType == 'content') && $table == 'cms_channel') {
        $restore['channel'] = $vars['channel'] ?? null;
    }
    return $restore;
}