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
use tpext\builder\traits\TreeModel;

class CmsChannel extends Model
{
    use TreeModel;
    use SoftDelete;

    protected $name = 'cms_channel';

    protected $autoWriteTimestamp = 'datetime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {
            //调用tp6事件，达到兼容
            self::beforeWrite(function ($data) {
                return self::onBeforeWrite($data);
            });
            self::afterInsert(function ($data) {
                return self::onAfterInsert($data);
            });
            self::afterUpdate(function ($data) {
                return self::onAfterUpdate($data);
            });
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

    public static function onAfterInsert($data)
    {
        ExtLoader::trigger('cms_channel_on_after_insert', $data);

        if (!empty($data['parent_id'])) {
            cache('cms_channel_children_ids_' . $data['parent_id'], null);
        }
    }

    public static function onAfterUpdate($data)
    {
        if (!isset($data['id'])) {
            return;
        }
        cache('cms_channel_' . $data['id'], null);

        if (!empty($data['parent_id'])) {
            cache('cms_channel_children_ids_' . $data['parent_id'], null);
        }

        ExtLoader::trigger('cms_channel_on_after_insert', $data);
    }

    public static function onBeforeWrite($data)
    {
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] == 0) {
                $data['full_name'] = $data['name'];
                $data['path'] = ',0,';
                $data['deep'] = 1;
            } else {
                $upNodes = (new static)->getUpperNodes($data);
                $names = [];
                $ids = [];
                foreach ($upNodes as $node) {
                    if (!empty($data['id']) && $node['id'] == $data['id']) {
                        continue;
                    }
                    $names[] = $node['name'];
                    $ids[] = $node['id'];
                }
                $data['full_name'] =  implode('->', array_reverse($names));
                $data['path'] = ',' . implode(',', array_reverse($ids)) . ',';
                $data['deep'] =  count($ids);
            }
        }
    }

    public static function onAfterDelete($data)
    {
        static::where(['parent_id' => $data['id']])->update(['parent_id' => $data['parent_id']]);
        CmsContent::where(['channel_id' => $data['id']])->update(['channel_id' => $data['parent_id']]);
        ExtLoader::trigger('cms_channel_on_after_delete', $data);

        cache('cms_channel_' . $data['id'], null);
        cache('cms_channel_children_ids_' . $data['parent_id'], null);
    }

    protected function treeInit()
    {
        $this->treeTextField = 'name';
        $this->treeSortField = 'sort';
    }

    public function getContentCountAttr($value, $data)
    {
        return CmsContent::where('channel_id', $data['id'])->count();
    }

    /**
     * Undocumented function
     *
     * @param array $membnodeer 用户
     * @param array $list 已获取的上级列表，第一次是传空数组
     * @param integer $limit 层级限制
     * @return array
     */
    public function getUpperNodes($node, $list = [], $limit = 999)
    {
        if (!isset($node['id'])) {
            $node['id'] = 0;
        }
        foreach ($list as $li) {
            if ($li['id'] == $node['id']) {
                trace("死循环:" . json_encode($node));
                return $list;
            }
        }
        if (count($list) >= $limit) {
            return $list;
        }

        $list[] = $node;

        if ($node['parent_id'] == 0 || $node['parent_id'] == $node['id']) {
            return $list;
        }

        $up = $this->getUpper($node['parent_id']);

        if ($up) {
            $list = $this->getUpperNodes($up, $list, $limit);
        }

        return $list;
    }

    protected function getUpper($parent_id)
    {
        if (empty($this->allTreeData)) {
            $this->allTreeData = static::select();
        }
        foreach ($this->allTreeData as $data) {
            if ($data['id'] == $parent_id) {
                return $data;
            }
        }
        return null;
    }

    public function getChannelPathAttr($value, $data)
    {
        if (empty($value)) {
            $value = 'c[id]';
        }
        return $value;
    }

    public function getContentPathAttr($value, $data)
    {
        if (empty($value)) {
            $value = 'a[id]';
        }
        return $value;
    }
}
