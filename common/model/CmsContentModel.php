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

class CmsContentModel extends Model
{
    protected $name = 'cms_content_model';

    protected $autoWriteTimestamp = 'datetime';

    public static function onBeforeInsert($data)
    {
        if (empty($data['sort'])) {
            $data['sort'] = static::max('sort') + 1;
        }
    }

    public static function onAfterWrite($data)
    {
        if (!isset($data['id'])) {
            return;
        }
        //模型配置变化必然伴随映射行写入，统一走tag清理（覆盖 cms_content_model_fields_* 全部键）
        Cache::deleteTag('cms_content_model_field');
    }

    public static function onAfterDelete($data)
    {
        Cache::deleteTag('cms_content_model_field');
    }

    public function setFieldsAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }
}
