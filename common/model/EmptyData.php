<?php

namespace tpext\cms\common\model;

use think\Model;

class EmptyData extends Model
{
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
