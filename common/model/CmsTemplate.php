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

namespace tpext\cms\common\model;

use think\Model;
use tpext\cms\common\Module;

class CmsTemplate extends Model
{
    protected $name = 'cms_template';
    protected $autoWriteTimestamp = 'datetime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {
            self::beforeInsert(function ($data) {
                return self::onBeforeInsert($data);
            });
            self::afterDelete(function ($data) {
                return self::onAfterDelete($data);
            });
        }
    }

    public static function onBeforeInsert($data)
    {
        if (empty($data['sort'])) {
            $data['sort'] = static::max('sort') + 5;
        }
    }

    public static function onAfterDelete($data)
    {
        CmsTemplateHtml::where(['template_id' => $data['id']])->delete();
        CmsContentPage::where(['template_id' => $data['id']])->delete();
    }

    public function getPagesCountAttr($value, $data)
    {
        return CmsTemplateHtml::where('template_id', $data['id'])->count();
    }

    public static function initPath($view_path)
    {
        $newTpl = file_get_contents(Module::getInstance()->getRoot() . 'tpl/new.html');

        if (!is_dir($view_path . '/channel')) {
            if (mkdir($view_path . '/channel', 0775, true)) {
                $text = file_get_contents(Module::getInstance()->getRoot() . 'tpl/channel.html');
                file_put_contents($view_path . '/channel/default.html', str_replace('__content__', $text, $newTpl));
            }
        }
        if (!is_dir($view_path . '/content')) {
            if (mkdir($view_path . '/content', 0775, true)) {
                $text = file_get_contents(Module::getInstance()->getRoot() . 'tpl/content.html');
                file_put_contents($view_path . '/content/default.html', str_replace('__content__', $text, $newTpl));
            }
        }
        if (!is_dir($view_path . '/static')) {
            if (mkdir($view_path . '/static/css', 0775, true)) {
                file_put_contents($view_path . '/static/css/site.css', '/*网站样式*/' . PHP_EOL);
            }
            if (mkdir($view_path . '/static/js', 0775, true)) {
                file_put_contents($view_path . '/static/js/site.js', '/*网站js*/' . PHP_EOL);
            }
            mkdir($view_path . '/static/fonts', 0775, true);
            mkdir($view_path . '/static/images', 0775, true);
        }
        if (!is_dir($view_path . '/common')) {
            if (mkdir($view_path . '/common', 0775, true)) {
                file_put_contents($view_path . '/common/header.html', file_get_contents(Module::getInstance()->getRoot() . 'tpl/header.html'));
                file_put_contents($view_path . '/common/fotter.html', file_get_contents(Module::getInstance()->getRoot() . 'tpl/fotter.html'));
                file_put_contents($view_path . '/common/layout.html', file_get_contents(Module::getInstance()->getRoot() . 'tpl/layout.html'));
            }
        }
        if (!is_dir($view_path . '/index.html')) {
            $text = $text = file_get_contents(Module::getInstance()->getRoot() . 'tpl/index.html');
            file_put_contents($view_path . '/index.html', str_replace(['__content__', '../static'], [$text, './static'], $newTpl));
        }
        if (!is_dir($view_path . '/about.html')) {
            $text = file_get_contents(Module::getInstance()->getRoot() . 'tpl/single.html');
            file_put_contents($view_path . '/about.html', str_replace(['__content__', '../static'], [$text, './static'], $newTpl));
        }
        if (!is_dir($view_path . '/contact.html')) {
            $text = file_get_contents(Module::getInstance()->getRoot() . 'tpl/single.html');
            file_put_contents($view_path . '/contact.html', str_replace(['__content__', '../static'], [$text, './static'], $newTpl));
        }
    }
}
