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

namespace tpext\cms\common;

use tpext\think\App;

/**
 * 后台文件管理路径限定：静态资源/模板页面管理器的读、写、删目标必须落在授权目录内
 * （授权域=theme 下各模板目录，见安全评估报告 SEC-3）
 */
class PathGuard
{
    /**
     * 静态资源允许的扩展名（同时用作目录名与新建类型白名单）
     * @var array
     */
    public static $staticExts = ['css', 'js'];

    /**
     * 模板页面允许的子目录（新建页面类型白名单）
     * @var array
     */
    public static $htmlTypes = ['index', 'channel', 'content', 'common', 'single', 'dynamic'];

    /**
     * 校验相对路径并在根目录内解析为绝对路径
     * 拒绝：空、..段、反斜杠、空字节、绝对路径写法、软链/拼接后越出根目录
     *
     * @param string $base 根目录绝对路径
     * @param string $relative 以/分隔的相对路径
     * @return string|false 绝对路径，非法返回false
     */
    public static function resolveWithin($base, $relative)
    {
        if (!is_string($relative) || $relative === '' || strpos($relative, "\0") !== false || strpos($relative, '\\') !== false) {
            return false;
        }

        $base = rtrim(str_replace('\\', '/', $base), '/');

        $parts = explode('/', ltrim($relative, '/'));
        $out = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return false;
            }
            $out[] = $part;
        }

        if (empty($out)) {
            return false;
        }

        $full = $base . '/' . implode('/', $out);

        //realpath核对已存在的部分，防止经软链等越出根目录
        $realBase = realpath($base);
        $realDir = realpath(is_dir($full) ? $full : dirname($full));
        if ($realBase === false || $realDir === false || strpos($realDir, $realBase) !== 0) {
            return false;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $full);
    }

    /**
     * 静态资源文件路径：theme/<view_path>/static/<css|js>/<name>.<ext>
     *
     * @param string $path 相对应用根的路径
     * @return string|false 绝对路径，非法返回false
     */
    public static function staticFile($path)
    {
        if (!is_string($path) || !preg_match('#^theme/[\w\-]+/static/(css|js)/[\w\-.]+\.(css|js)$#', $path, $mch)) {
            return false;
        }
        if ($mch[1] != $mch[2]) { //目录名与扩展名一致（css/xx.css），不允许css/xx.js
            return false;
        }
        return static::resolveWithin(App::getRootPath(), $path);
    }

    /**
     * 模板页面文件路径：theme/<view_path>/[index.html|<type>/<name>.html|<name>.html]
     *
     * @param string $path 相对应用根的路径
     * @return string|false 绝对路径，非法返回false
     */
    public static function htmlFile($path)
    {
        if (!is_string($path) || !preg_match('#^theme/[\w\-]+/((index\.html)|((channel|content|common|single|dynamic)/[\w\-.]+\.html)|([\w\-.]+\.html))$#', $path)) {
            return false;
        }
        return static::resolveWithin(App::getRootPath(), $path);
    }

    /**
     * 新建文件名：仅字母数字-_（不带扩展名），防穿越/防注入扩展名
     *
     * @param string $name
     * @return bool
     */
    public static function safeName($name)
    {
        return is_string($name) && preg_match('/^[\w\-]+$/', $name);
    }

    /**
     * 模板prefix合法性：'/'、'/x'、'/x/'（必须有前导斜杠、仅[\w-]；空串与../等一律非法）
     * 用于路由生成与clearHtml删除前的防呆
     *
     * @param string $prefix
     * @return bool
     */
    public static function safePrefix($prefix)
    {
        return is_string($prefix) && preg_match('#^/([\w\-]+/?)?$#', $prefix);
    }
}
