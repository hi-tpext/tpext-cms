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
use tpext\common\ExtLoader;
use think\model\concern\SoftDelete;

class CmsContent extends Model
{
    use SoftDelete;
    protected $name = 'cms_content';
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
            self::beforeWrite(function ($data) {
                return self::onBeforeWrite($data);
            });
        }
    }

    public static function onBeforeInsert($data)
    {
        if (empty($data['sort'])) {
            $data['sort'] = static::max('sort') + 5;
        }
    }

    public static function onBeforeWrite($data)
    {
        if (empty($data['description']) && empty($data['reference_id'])) {
            $content = $data->getData('content');
            $content = preg_replace('/<script[^>]*?>.*?<\/script>/is', '', $content);
            $content = strip_tags($content);
            $content = preg_replace('/[\r|\n|\t|\s]/is', '', $content);
            $content = str_replace(['\u00A0', '\u0020', '\u2800', '\u3000', '　', '&nbsp;', '&gt;', '&lt;', '&eq;', '&egt;', '&elt;'], '', $content);
            $data['description'] = static::getDesc($content);
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
            'content' => !empty($data['reference_id']) ? '@' . $data['reference_id'] : $data->getData('content'),
        ]);

        ExtLoader::trigger('cms_content_on_after_insert', $data);
    }

    public static function onAfterUpdate($data)
    {
        if (!isset($data['id'])) {
            return;
        }

        cache('cms_content_' . $data['id'], null);
        cache('cms_content_click_' . $data['id'], null);
        cache('cms_content_detail_' . $data['id'], null);

        $detail = CmsContentDetail::where('main_id', $data['id'])->find();
        if (!$detail) {
            $detail = new CmsContentDetail;
        } else {
            cache('cms_content_detail_' . $detail['id'], null);
        }

        if (isset($data['reference_id'])) {
            if ($data['reference_id'] > 0) {
                $detail->save([
                    'main_id' => $data['id'],
                    'content' => '@' . $data['reference_id']
                ]);
            } else {
                $arrayData = $data->toArray();

                if (isset($arrayData['content'])) {
                    $detail->save([
                        'main_id' => $data['id'],
                        'content' => $data->getData('content')
                    ]);
                }

                self::where('reference_id', $data['id'])
                    ->update([
                        'keywords' => $data['keywords'],
                        'link' => $data['link'],
                        'description' => $data['description'],
                        'author' => $data['author'],
                        'source' => $data['source'],
                        'logo' => $data['logo'],
                        'attachment' => $data['attachment'],
                    ]);
            }
        }

        ExtLoader::trigger('cms_content_on_after_update', $data);
    }

    public static function onAfterDelete($data)
    {
        CmsContentDetail::where('main_id', $data['id'])->delete();

        cache('cms_content_' . $data['id'], null);
        cache('cms_content_detail_' . $data['id'], null);

        ExtLoader::trigger('cms_content_on_after_delete', $data);
    }

    protected static function getDesc($content)
    {
        $arr = explode('。', $content);
        $text = '';
        $n = 0;
        foreach ($arr as $a) {
            if (mb_strlen($text . $a . '。') > 255) {
                if ($n == 0) {
                    $a = mb_substr($a, 0, 254);
                    $arr2 = explode('，', $a);
                    if (count($arr2) > 1) {
                        array_pop($arr2);
                        $text = implode('，', $arr2) . '。';
                    } else {
                        $text = $a . '。';
                    }
                }
                break;
            }
            $text .= $a . '。';
            $n += 1;
            if (mb_strlen($text) > 90) {
                break;
            }
            if ($n > 2) {
                break;
            }
        }

        return $text;
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
        if (!empty($data['reference_id'])) {
            $detail = CmsContentDetail::where('main_id', $data['reference_id'])->find();
            return $detail ? $detail['content'] : '';
        }

        return isset($this->detail) ? $this->detail['content'] : '';
    }

    public function getPublishDateAttr($value, $data)
    {
        return date('Y-m-d', strtotime($data['publish_time']));
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

        return is_array($value) ? implode(',', $value) : trim($value, ',');
    }

    public function setMentionIdsAttr($value)
    {
        if (empty($value)) {
            return '';
        }

        return is_array($value) ? implode(',', $value) : trim($value, ',');
    }

    public function getTagNamesAttr($value, $data)
    {
        $ids = trim($data['tags'], ',');
        if (empty($ids)) {
            return '';
        }
        $list = CmsTag::where('id', 'in', $ids)->column('name');
        return implode(',', $list);
    }

    public function getClickAttr($value, $data)
    {
        return cache('cms_content_click_' . $data['id']) ?: $data['click'];
    }
}
