<?php

use tpext\cms\common\taglib\Processer;
use tpext\cms\common\taglib\Table;
use think\paginator\driver\Bootstrap;

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

function get_tags($id)
{
    $table = 'cms_tags';
    $dbNameSpace = Processer::getDbNamespace();
    $scope = Table::defaultScope($table);
    $item = $dbNameSpace::name($table)
        ->where('id', $id)
        ->where($scope)
        ->cache('cms_tags_' . $id, 3600 * 24 * 7, $table)
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
        $item = get_tags($item);
    }

    return $item['url'];
}

function sql_guard($val)
{
    $val = strip_tags($val);

    if (preg_match('/\b(?:select|delete)\b.+?\bfrom\b/is', $val)) {
        return 'invalid words';
    }

    if (preg_match('/\bunion\b.+?\bselect\b/is', $val)) {
        return 'invalid words';
    }

    return $val;
}

function more($str, $len = 100, $more = '...')
{
    if (mb_strlen($str, 'utf-8') > $len) {
        return mb_substr($str, 0, $len, 'utf-8') . $more;
    } else {
        return $str;
    }
}

function cms_build_where($cidKey, $cidVal, $idKey, $idVal)
{
    $whereArr = [];

    if ($cidKey) {
        $cidVal = $cidVal ?? 0;
        if ($cidVal !== 0) {
            if (is_array($cidVal) || (is_string($cidVal) && strstr($cidVal, ','))) {
                $whereArr[] = [$cidKey, 'in', $cidVal];
            } else {
                $whereArr[] = [$cidKey, '=', $cidVal];
            }
        }
    }

    if ($idKey) {
        $idVal = $idVal ?? 0;
        if ($idVal !== 0) {
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
    $pageType = $vars['__page_type__'] ?? '';
    if ($pageType == 'channel' || $pageType == 'content') {
        $idVal = $vars['channel_id'] ?? 0;
    }
    return Processer::getParents($table, $idVal, $idKey, $pidKey);
}

function cms_query_list($table, $where, $whereRaw, $whereBinds, $scope, $fields, $tagOrder, $take, $pagesize, $cacheKey, $cacheTime, $simple, $links, $vars)
{
    $page = 1;
    $hasPaginator = false;
    $pagesize = $pagesize ?: ($vars['__set_pagesize__'] ?? 0);
    if ($take == 0) {
        if ($pagesize > 0) {
            $page = isset($vars['page']) && intval($vars['page']) > 0 ? intval($vars['page']) : 1;
            $take = $pagesize;
            $hasPaginator = true;
        } else {
            $take = 10;
        }
    }

    $orderBy = $tagOrder;
    if ($table == 'cms_content' && !empty($vars['__set_order_by__'])) {
        $orderBy = $vars['__set_order_by__'] . $tagOrder;
    }

    $db = Processer::getDbNamespace();
    $list = $db::name($table)
        ->where($where)
        ->whereRaw($whereRaw, $whereBinds)
        ->where($scope)
        ->field($fields)
        ->order($orderBy)
        ->limit(($page - 1) * $take, $take)
        ->cache($cacheKey ?: false, $cacheTime, $table)
        ->select();
    $list = Processer::list($table, $list);

    $linksHtml = '';
    if ($hasPaginator) {
        $total = $db::name($table)
            ->where($where)
            ->whereRaw($whereRaw, $whereBinds)
            ->where($scope)
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

function cms_query_detail($table, $where, $whereRaw, $whereBinds, $scope, $order, $fields, $cacheKey, $cacheTime)
{
    $db = Processer::getDbNamespace();

    $detail = $db::name($table)
        ->where($where)
        ->whereRaw($whereRaw, $whereBinds)
        ->where($scope)
        ->order($order)
        ->field($fields)
        ->cache($cacheKey ?: false, $cacheTime, $table)
        ->find();

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
