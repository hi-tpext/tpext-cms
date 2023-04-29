<?php

namespace tpext\cms\common\model;

use think\Model;

class CmsTag extends Model
{
    protected $autoWriteTimestamp = 'datetime';

    protected static function init()
    {
        self::beforeInsert(function ($data) {
            if (empty($data['sort'])) {
                $data['sort'] = static::max('sort') + 5;
            }
        });
    }
}
