<?php

namespace tpext\cms\common\model;

use think\Model;
use tpext\builder\traits\TreeModel;

class CmsChannel extends Model
{
    use TreeModel;

    protected $autoWriteTimestamp = 'dateTime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {
            //调用tp6事件，达到兼容
            self::beforeWrite(function ($data) {
                return self::onBeforeWrite($data);
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

    public static function onBeforeWrite($data)
    {
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] == 0) {
                $data['deep'] = 1;
                $data['path'] = ',';
            } else {
                $parent = static::find($data['parent_id']);
                if ($parent) {
                    $data['deep'] = $parent['deep'] + 1;
                    $data['path'] = $parent['path'] . $data['parent_id'] . ',';
                }
            }
        }
    }

    public static function onAfterDelete($data)
    {
        static::where(['parent_id' => $data['id']])->update(['parent_id' => $data['parent_id']]);
        CmsContent::where(['channel_id' => $data['id']])->update(['channel_id' => $data['parent_id']]);
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

        $up = $this->getUper($node['parent_id']);

        if ($up) {
            $list = $this->getUpperNodes($up, $list, $limit);
        }

        return $list;
    }

    protected function getUper($parent_id)
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
}
