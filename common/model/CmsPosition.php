<?php

namespace tpext\cms\common\model;

use think\Model;

class CmsPosition extends Model
{
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
            $data['sort'] = static::where(['parent_id' => $data['parent_id']])->max('sort') + 5;
        }
    }

    public static function onAfterDelete($data)
    {
        static::where(['parent_id' => $data['id']])->update(['parent_id' => $data['parent_id']]);
        Cmsbanner::where(['position_id' => $data['id']])->update(['position_id' => $data['parent_id']]);
    }

    public function getBannerCountAttr($value, $data)
    {
        return CmsBanner::where('position_id', $data['id'])->count();
    }
}
