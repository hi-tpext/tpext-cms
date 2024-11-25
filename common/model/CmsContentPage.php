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

class CmsContentPage extends Model
{
    protected $name = 'cms_content_page';
    protected $autoWriteTimestamp = 'datetime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {
            self::afterUpdate(function ($data) {
                return self::onAfterUpdate($data);
            });
            self::afterDelete(function ($data) {
                return self::onAfterDelete($data);
            });
        }
    }

    public static function onAfterUpdate($data)
    {
        if (!isset($data['html_type']) || !isset($data['to_id']) || !isset($data['template_id'])) {
            return;
        }
        cache('cms_page_' . $data['template_id'] . '_' . $data['html_type'] . '_' . $data['to_id'], null);
    }

    public static function onAfterDelete($data)
    {
        if (!isset($data['html_type']) || !isset($data['to_id']) || !isset($data['template_id'])) {
            return;
        }
        cache('cms_page_' . $data['template_id'] . '_' . $data['html_type'] . '_' . $data['to_id'], null);
    }

    public function template()
    {
        return $this->belongsTo(CmsTemplate::class, 'template_id', 'id');
    }

    public function templateHtml()
    {
        return $this->belongsTo(CmsTemplateHtml::class, 'html_id', 'id');
    }
}
