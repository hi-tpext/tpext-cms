<?php

namespace tpext\cms\common\model;

use think\Model;
use think\model\concern\SoftDelete;

class CmsContent extends Model
{
    use SoftDelete;
    protected $autoWriteTimestamp = 'datetime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {

            self::beforeInsert(function ($data) {
                return self::onBeforeInsert($data);
            });

            self::afterInsert(function ($data) {
                return self::onAfterInsert($data);
            });

            self::afterUpdate(function ($data) {
                return self::onAfterUpdate($data);
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

    public static function onAfterInsert($data)
    {
        if (!isset($data['id'])) {
            return;
        }
        $id = $data['id'];

        $detail = new CmsContentDetail;
        $detail->save([
            'main_id' => $id,
            'content' => $data->getData('content')
        ]);
    }

    public static function onAfterUpdate($data)
    {
        if (!isset($data['id'])) {
            return;
        }
        $id = $data['id'];

        $detail = CmsContentDetail::where('main_id', $id)->find();
        if (!$detail) {
            $detail = new CmsContentDetail;
        }
        $detail->save([
            'main_id' => $id,
            'content' => $data->getData('content')
        ]);
    }

    public static function onAfterDelete($data)
    {
        CmsContentDetail::where('main_id', $data['id'])->delete();
    }

    public function channel()
    {
        return $this->belongsTo(CmsChannel::class, 'channel_id', 'id');
    }

    public function detail()
    {
        return $this->hasOne(CmsContentDetail::class, 'main_id', 'id');
    }

    public function getContentAttr($value, $data)
    {
        return isset($this->detail) ? $this->detail['content'] : '';
    }

    public function getAttrAttr($value, $data)
    {
        $attr = [];
        if ($data['is_recommend']) {
            $attr[] = 'is_recommend';
        }
        if ($data['is_hot']) {
            $attr[] = 'is_hot';
        }
        if ($data['is_top']) {
            $attr[] = 'is_top';
        }

        return $attr;
    }

    public function setTagsAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        return is_array($value) ? ',' . implode(',', $value) . ',' : ',' . trim($value, ',') . ',';
    }
}
