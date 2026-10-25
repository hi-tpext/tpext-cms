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
use tpext\cms\common\Cache;

class CmsContentModelField extends Model
{
    protected $name = 'cms_content_model_field';

    protected $autoWriteTimestamp = 'datetime';

    public static function onAfterWrite($data)
    {
        Cache::deleteTag('cms_content_model_field');
    }

    public static function onAfterDelete($data)
    {
        Cache::deleteTag('cms_content_model_field');
    }

    public function setRulesAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }
}
