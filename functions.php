<?php

use think\paginator\driver\Bootstrap;
use tpext\builder\logic\ImageHandler;
use tpext\cms\common\taglib\Processer;
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

if (!function_exists('sql_guard')) {

    function sql_guard($val)
    {
        return Processer::sqlGuard($val);
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

        $thumbFile = '/thumb/' . md5($file) . '-' . $width . 'x' . $height . '.' . $ext;

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
            'height' => $height ? $width : null,
            'to_path' => App::getPublicPath() . $thumbFile,
        ];

        try {
            return $handler->resize($file, $options);
        } catch (\Exception $e) {
            trace('thumb error:' . $e->getMessage());
            if (is_file(App::getPublicPath() . $thumbFile)) {
                @unlink(App::getPublicPath() . $thumbFile);
            }
            return $file;
        }
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
    $pageType = $vars['__page_type__'] ?? '';
    if ($pageType == 'channel' || $pageType == 'content') {
        $idVal = $vars['channel_id'] ?? 0;
    }
    return Processer::getParents($table, $idVal, $idKey, $pidKey);
}

function cms_query_list($table, $where, $scope, $fields, $tagOrder, $take, $pagesize, $cacheKey, $cacheTime, $simple, $links, $vars)
{
    $page = 1;
    $hasPaginator = false;
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
        ->cache($cacheKey ?: false, $cacheTime, $table)
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
        $total = $db::name($table)
            ->where($where)
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

function cms_query_detail($table, $where, $scope, $order, $fields, $cacheKey, $cacheTime)
{
    $db = Processer::getDbNamespace();

    $detail = $db::name($table)
        ->where($where)
        ->where($scope)
        ->order($order)
        ->field($fields)
        ->cache($cacheKey ?: false, $cacheTime, $table)
        ->find();

    $channel = $vars['channel'] ?? null;
    $extendTable = $channel ? ($channel['extend_table'] ?? '') : '';

    if ($detail && $extendTable && $extendTable != 'cms_content') {
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