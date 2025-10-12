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

namespace tpext\cms\common\event;

use tpext\cms\common\model;

class ModelEvent
{
    public function watch()
    {
        $classMap = [
            model\CmsBanner::class,
            model\CmsChannel::class,
            model\CmsContent::class,
            model\CmsContentDetail::class,
            model\CmsContentField::class,
            model\CmsContentModel::class,
            model\CmsContentModelField::class,
            model\CmsContentPage::class,
            model\CmsPosition::class,
            model\CmsTag::class,
            model\CmsTemplate::class,
            model\CmsTemplateHtml::class
        ];

        $observe = [
            'before_write',
            'after_write',
            'before_insert',
            'after_insert',
            'before_update',
            'after_update',
            'before_delete',
            'after_delete',
            'before_restore',
            'after_restore'
        ];

        foreach ($classMap as $class) {
            foreach ($observe as $method) {
                $on = 'on' . $this->parseName($method);
                if (method_exists($class, $on)) {
                    $class::event($method, function ($data) use ($class, $on) {
                        $class::$on($data);
                    });
                }
            }
        }
    }

    /**
     * 下划线转驼峰
     * @access public
     * @param  string  $name 字符串
     * @return string
     */
    public static function parseName($name)
    {
        $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name);

        return ucfirst($name);
    }
}
