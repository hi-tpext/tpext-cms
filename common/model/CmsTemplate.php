<?php

namespace tpext\cms\common\model;

use think\Model;
use tpext\cms\common\Module;

class CmsTemplate extends Model
{
    protected $autoWriteTimestamp = 'dateTime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {
            self::beforeInsert(function ($data) {
                return self::onBeforeInsert($data);
            });
        }
    }

    public static function onBeforeInsert($data)
    {
        if (empty($data['sort'])) {
            $data['sort'] = static::max('sort') + 5;
        }
    }

    public function getPagesCountAttr($value, $data)
    {
        return CmsTemplatePage::where('template_id', $data['id'])->count();
    }

    public static function initPath($view_path)
    {
        $newTpl = file_get_contents(Module::getInstance()->getRoot() . 'tpl/new.html');

        if (!is_dir($view_path . '/channel')) {
            if (mkdir($view_path . '/channel', 0775, true)) {
                file_put_contents($view_path . '/channel/default.html', str_replace('__content__', '栏目默认模板', $newTpl));
            }
        }

        if (!is_dir($view_path . '/content')) {
            if (mkdir($view_path . '/content', 0775, true)) {
                file_put_contents($view_path . '/content/default.html', str_replace('__content__', '内容默认模板', $newTpl));
            }
        }

        if (!is_dir($view_path . '/assets')) {
            if (mkdir($view_path . '/assets/css', 0775, true)) {
                file_put_contents($view_path . '/assets/css/site.css', '/*网站样式*/' . PHP_EOL);
            }
            if (mkdir($view_path . '/assets/js', 0775, true)) {
                file_put_contents($view_path . '/assets/js/site.js', '/*网站js*/' . PHP_EOL);
            }
            mkdir($view_path . '/assets/fonts', 0775, true);
            mkdir($view_path . '/assets/images', 0775, true);
        }

        if (!is_dir($view_path . '/common')) {
            if (mkdir($view_path . '/common', 0775, true)) {

                $newTplCommon = file_get_contents(Module::getInstance()->getRoot() . 'tpl/common.html');

                file_put_contents($view_path . '/common/header.html', str_replace('__content__', '公共(示例)header', $newTplCommon));
                file_put_contents($view_path . '/common/fotter.html', str_replace('__content__', '公共(示例)fotter', $newTplCommon));
                file_put_contents($view_path . '/common/layout.html', str_replace('__content__', '主布局页面layout(可选)', $newTpl));
            }
        }

        if (!is_dir($view_path . '/index.html')) {
            file_put_contents($view_path . '/index.html', str_replace(['__content__', '../assets'], ['网站首页', './assets'], $newTpl));
        }
        if (!is_dir($view_path . '/about.html')) {
            file_put_contents($view_path . '/about.html', str_replace(['__content__', '../assets'], ['单页(示例)：关于我们', './assets'], $newTpl));
        }
        if (!is_dir($view_path . '/contact.html')) {
            file_put_contents($view_path . '/contact.html', str_replace(['__content__', '../assets'], ['单页(示例)：联系我们', './assets'], $newTpl));
        }
    }
}
