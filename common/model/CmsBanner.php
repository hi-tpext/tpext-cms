<?php

namespace tpext\cms\common\model;

use think\Model;

class CmsBanner extends Model
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

    public function position()
    {
        return $this->belongsTo(CmsPosition::class, 'position_id', 'id');
    }
}
