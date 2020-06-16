<?php

namespace tpext\cms\admin\model;

use think\Model;

class CmsContent extends Model
{
    protected $autoWriteTimestamp = 'dateTime';

    protected static function init()
    {
        self::beforeInsert(function ($data) {
            if (empty($data['sort'])) {
                $data['sort'] = static::max('sort') + 1;
            }
        });
    }

    public function getCategoryAttr($value, $data)
    {
        $category = CmsCategory::get($data['category_id']);
        return $category ? $category['name'] : '--';
    }
}
