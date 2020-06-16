<?php

namespace tpext\cms\admin\model;

use think\Model;

class CmsPosition extends Model
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
}
