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

class EmptyData extends Model
{
    protected $name = 'empty_data';
    
    public function __get($name)
    {
        if ($name == '__not_found__') {
            return true;
        }
        if ($name == 'title' || $name == 'name' || $name == 'content' || $name == 'description') {
            return '无数据';
        }
        if ($name == 'url' || $name == 'link') {
            return '#';
        }

        return '__not_found__';
    }

    public function offsetExists($name)
    {
        return true;
    }

    public function offsetGet($name)
    {
        if ($name == '__not_found__') {
            return true;
        }
        if ($name == 'title' || $name == 'name' || $name == 'content' || $name == 'description') {
            return '无数据';
        }
        if ($name == 'url' || $name == 'link') {
            return '#';
        }

        return '__not_found__';
    }

    public function __toString()
    {
        return '<!--无数据-->';
    }

    public function __call($name, $arguments = [])
    {
        return $this;
    }
}
